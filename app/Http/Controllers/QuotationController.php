<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Quotation;
use App\Services\ConfigService;
use App\Services\PdfGenerationService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuotationController extends Controller
{
    public function __construct(
        private ConfigService $configService,
        private QuotationService $quotationService,
    ) {}

    /**
     * Show the quotation creation form.
     */
    public function create()
    {
        $config = $this->configService->load();
        $user = Auth::user();

        // Get user's assigned locations — admin/super_admin see all
        $isAdminOrSuper = in_array($user->role, ['super_admin', 'admin']);
        $userLocations = $isAdminOrSuper
            ? \App\Models\Location::cities()->active()->with('parent')->get()
            : $user->locations()->with('parent')->get();
        $defaultLocationId = $userLocations->count() === 1 ? $userLocations->first()->id : null;

        // Get all banks with their locations for JS filtering
        $allBanks = \App\Models\Bank::active()->with('locations')->orderBy('name')->get();
        $config['banks'] = $allBanks->pluck('name')->toArray();

        // Build bank → location map (from bank_location pivot)
        $bankLocationMap = [];
        foreach ($allBanks as $bank) {
            $bankLocationMap[$bank->name] = $bank->locations->pluck('id')->toArray();
        }

        // Get locations grouped by state for the dropdown
        $locStates = \App\Models\Location::with('children')->states()->active()->orderBy('name')->get();

        return view('quotations.create', [
            'config' => $config,
            'user' => $user,
            'userLocations' => $userLocations,
            'defaultLocationId' => $defaultLocationId,
            'bankLocationMap' => $bankLocationMap,
            'locStates' => $locStates,
        ]);
    }

    /**
     * Generate a quotation PDF.
     */
    public function generate(Request $request)
    {
        $input = $request->all();
        $user = Auth::user();

        // Auto-fill prepared by from auth user if not provided
        if (empty($input['preparedByName'])) {
            $input['preparedByName'] = $user->name;
        }
        if (empty($input['preparedByMobile'])) {
            $input['preparedByMobile'] = $user->phone ?? '';
        }

        $result = $this->quotationService->generate($input, $user->id);

        if (isset($result['error']) && empty($result['filename'])) {
            return response()->json(['error' => $result['error']], 422);
        }

        $quotation = $result['quotation'] ?? null;
        $filename = $quotation?->pdf_filename ?? ($result['filename'] ?? '');

        // Log activity
        ActivityLog::log('create_quotation', $quotation, [
            'customer_name' => $input['customerName'] ?? '',
            'loan_amount' => $input['loanAmount'] ?? 0,
            'filename' => $filename,
        ]);

        // PDF generated even if DB save failed — user still gets their file
        return response()->json([
            'success' => true,
            'filename' => $filename,
            'id' => $quotation?->id,
            'warning' => $result['error'] ?? null,
        ]);
    }

    /**
     * Show quotation details.
     */
    public function show(Quotation $quotation)
    {
        $user = Auth::user();

        // Authorization: user can view own quotations, or all if has permission
        if (!$user->hasPermission('view_all_quotations') && $quotation->user_id !== $user->id) {
            abort(403, 'You can only view your own quotations.');
        }

        $quotation->load(['banks.emiEntries', 'documents', 'user', 'location.parent']);

        return view('quotations.show', [
            'quotation' => $quotation,
        ]);
    }

    /**
     * Download a quotation PDF.
     */
    public function download(Quotation $quotation)
    {
        $user = Auth::user();

        // Authorization
        if (!$user->hasPermission('view_all_quotations') && $quotation->user_id !== $user->id) {
            abort(403, 'You can only download your own quotations.');
        }

        $filepath = null;
        $filename = $quotation->pdf_filename;

        // Try direct path first
        if ($filename) {
            $filepath = storage_path('app/pdfs/' . $filename);
            if (!file_exists($filepath) && $quotation->pdf_path && file_exists($quotation->pdf_path)) {
                $filepath = $quotation->pdf_path;
            }
        }

        // Auto-find PDF on disk by customer name + date
        if (!$filepath || !file_exists($filepath)) {
            $found = $this->findPdfOnDisk($quotation);
            if ($found) {
                $filepath = $found;
                $filename = basename($found);
                $quotation->update(['pdf_filename' => $filename, 'pdf_path' => $found]);
            }
        }

        // Last resort: regenerate the PDF from stored quotation data
        if (!$filepath || !file_exists($filepath)) {
            $result = $this->regeneratePdf($quotation);
            if ($result && file_exists($result['path'])) {
                $filepath = $result['path'];
                $filename = $result['filename'];
            }
        }

        if (!$filepath || !file_exists($filepath)) {
            abort(404, 'PDF could not be generated. Please try again.');
        }

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Find a PDF file on disk matching the quotation's customer name and creation date.
     */
    private function findPdfOnDisk(Quotation $quotation): ?string
    {
        if (!$quotation->customer_name) {
            return null;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $quotation->customer_name);
        $pdfDir = storage_path('app/pdfs');

        // Try exact date match first
        if ($quotation->created_at) {
            $datePrefix = $quotation->created_at->format('Y-m-d');
            $matches = glob("{$pdfDir}/Loan_Proposal_{$safeName}_{$datePrefix}*");
            if (!empty($matches)) {
                return end($matches);
            }
        }

        // Fallback: match by customer name only (latest file)
        $matches = glob("{$pdfDir}/Loan_Proposal_{$safeName}_*");

        return !empty($matches) ? end($matches) : null;
    }

    /**
     * Regenerate a PDF from stored quotation data and save to disk.
     */
    private function regeneratePdf(Quotation $quotation): ?array
    {
        $quotation->load(['banks.emiEntries', 'documents']);

        $config = $this->configService->load();

        $templateData = [
            'customerName'    => $quotation->customer_name,
            'customerType'    => $quotation->customer_type,
            'loanAmount'      => $quotation->loan_amount,
            'date'            => $quotation->created_at->format('d F Y'),
            'companyPhone'    => $config['companyPhone'] ?? '+91 XXXXX XXXXX',
            'companyEmail'    => $config['companyEmail'] ?? 'info@shf.com',
            'tenures'         => $quotation->selected_tenures ?? [5, 10, 15, 20],
            'banks'           => [],
            'documents'       => $quotation->documents->map(fn ($d) => [
                'en' => $d->document_name_en,
                'gu' => $d->document_name_gu ?? '',
            ])->toArray(),
            'additionalNotes' => $quotation->additional_notes ?? '',
            'ourServices'     => $config['ourServices'] ?? '',
            'preparedByName'  => $quotation->prepared_by_name ?? '',
            'preparedByMobile' => $quotation->prepared_by_mobile ?? '',
        ];

        foreach ($quotation->banks as $bank) {
            $emiByTenure = [];
            foreach ($bank->emiEntries as $emi) {
                $emiByTenure[(int) $emi->tenure_years] = [
                    'emi'           => (int) $emi->monthly_emi,
                    'totalInterest' => (int) $emi->total_interest,
                    'totalPayment'  => (int) $emi->total_payment,
                ];
            }

            $templateData['banks'][] = [
                'name'        => $bank->bank_name,
                'roiMin'      => (float) $bank->roi_min,
                'roiMax'      => (float) $bank->roi_max,
                'charges'     => [
                    'pf'         => (float) $bank->pf_charge,
                    'admin'      => (int) $bank->admin_charge,
                    'stamp'      => (int) $bank->stamp_notary,
                    'reg'        => (int) $bank->registration_fee,
                    'advocate'   => (int) $bank->advocate_fees,
                    'iom'        => (int) $bank->iom_charge,
                    'tc'         => (int) $bank->tc_report,
                    'extra1Name' => $bank->extra1_name ?? '',
                    'extra1Amt'  => (int) ($bank->extra1_amount ?? 0),
                    'extra2Name' => $bank->extra2_name ?? '',
                    'extra2Amt'  => (int) ($bank->extra2_amount ?? 0),
                    'total'      => (int) $bank->total_charges,
                ],
                'emiByTenure' => $emiByTenure,
            ];
        }

        $pdfService = app(PdfGenerationService::class);
        $result = $pdfService->generate($templateData);

        if (isset($result['error'])) {
            return null;
        }

        // Save the PDF reference to the database for future downloads
        $quotation->update([
            'pdf_filename' => $result['filename'],
            'pdf_path'     => $result['path'],
        ]);

        return $result;
    }

    /**
     * Download PDF by filename (for legacy/JS compatibility).
     */
    public function downloadByFilename(Request $request)
    {
        $filename = basename($request->query('file', ''));

        if (!$filename) {
            abort(400, 'No file specified.');
        }

        $filepath = storage_path('app/pdfs/' . $filename);

        if (!file_exists($filepath)) {
            abort(404, 'PDF file not found.');
        }

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Delete a quotation.
     */
    public function destroy(Quotation $quotation)
    {
        $user = Auth::user();

        // Authorization
        if (!$user->hasPermission('view_all_quotations') && $quotation->user_id !== $user->id) {
            abort(403, 'You can only delete your own quotations.');
        }

        // Block deletion if converted to loan
        if ($quotation->loan_id) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Cannot delete quotation — it has been converted to Loan #' . $quotation->loan?->loan_number], 422);
            }

            return redirect()->back()->with('error', 'Cannot delete quotation — it has been converted to a loan.');
        }

        $customerName = $quotation->customer_name;
        $filename = $quotation->pdf_filename;

        // Delete PDF file
        if ($quotation->pdf_path && file_exists($quotation->pdf_path)) {
            @unlink($quotation->pdf_path);
        }
        $storagePath = storage_path('app/pdfs/' . $quotation->pdf_filename);
        if (file_exists($storagePath)) {
            @unlink($storagePath);
        }

        $quotation->delete();

        ActivityLog::log('delete_quotation', null, [
            'customer_name' => $customerName,
            'filename'      => $filename,
        ]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Quotation deleted.']);
        }

        return redirect()->route('dashboard')->with('success', 'Quotation deleted.');
    }
}
