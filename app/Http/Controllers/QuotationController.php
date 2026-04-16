<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Quotation;
use App\Services\ConfigService;
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

        return view('quotations.create', [
            'config' => $config,
            'user'   => $user,
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

        $quotation->load(['banks.emiEntries', 'documents', 'user']);

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

        $filepath = $quotation->pdf_path;

        if (!$filepath || !file_exists($filepath)) {
            // Try storage path
            $filepath = storage_path('app/pdfs/' . $quotation->pdf_filename);
        }

        if (!file_exists($filepath)) {
            abort(404, 'PDF file not found.');
        }

        return response()->download($filepath, $quotation->pdf_filename, [
            'Content-Type' => 'application/pdf',
        ]);
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
