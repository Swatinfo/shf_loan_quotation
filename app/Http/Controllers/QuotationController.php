<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\DailyVisitReport;
use App\Models\Location;
use App\Models\Quotation;
use App\Models\User;
use App\Services\ConfigService;
use App\Services\NotificationService;
use App\Services\PdfGenerationService;
use App\Services\QuotationService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function __construct(
        private ConfigService $configService,
        private QuotationService $quotationService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Dedicated quotations listing page. Delegates data to
     * DashboardController::quotationData, so the table stays DRY.
     */
    public function index(): View
    {
        $user = Auth::user();

        if (
            ! $user->hasPermission('create_quotation')
            && ! $user->hasPermission('view_own_quotations')
            && ! $user->hasPermission('view_all_quotations')
        ) {
            abort(403);
        }

        $canViewAll = $user->hasPermission('view_all_quotations');
        $users = $canViewAll
            ? User::select('id', 'name')->orderBy('name')->get()
            : collect();

        $permissions = [
            'view_all' => $canViewAll,
            'download_pdf' => $user->hasPermission('download_pdf'),
            'download_pdf_branded' => $user->hasPermission('download_pdf_branded'),
            'download_pdf_plain' => $user->hasPermission('download_pdf_plain'),
            'delete_quotations' => $user->hasPermission('delete_quotations'),
            'create_quotation' => $user->hasPermission('create_quotation'),
        ];

        $template = 'newtheme.quotations.index';

        return view($template, [
            'users' => $users,
            'permissions' => $permissions,
            'pageKey' => 'quotations',
        ]);
    }

    /**
     * Show the quotation creation form.
     */
    public function create()
    {
        $config = $this->configService->load();
        $user = Auth::user();

        // Get user's assigned locations — admin/super_admin see all
        $isAdminOrSuper = $user->hasAnyRole(['super_admin', 'admin']);
        $userLocations = $isAdminOrSuper
            ? Location::cities()->active()->with('parent')->get()
            : $user->locations()->with('parent')->get();
        $defaultLocationId = $userLocations->count() === 1 ? $userLocations->first()->id : null;

        // Get all banks with their locations for JS filtering
        $allBanks = Bank::active()->with('locations')->orderBy('name')->get();
        $config['banks'] = $allBanks->pluck('name')->toArray();

        // Build bank → location map (from bank_location pivot)
        $bankLocationMap = [];
        foreach ($allBanks as $bank) {
            $bankLocationMap[$bank->name] = $bank->locations->pluck('id')->toArray();
        }

        // Get locations grouped by state for the dropdown
        $locStates = Location::with('children')->states()->active()->orderBy('name')->get();

        // Get user's branches for branch selection
        $userBranches = $isAdminOrSuper
            ? Branch::active()->with('location.parent')->orderBy('name')->get()
            : $user->branches()->where('is_active', true)->with('location.parent')->orderBy('name')->get();
        $defaultBranchId = $user->default_branch_id;

        // Admin / super_admin / bdh can attribute a quotation to another user
        // at creation time. Everyone else creates under their own id only.
        $canAssignCreator = $user->hasAnyRole(['super_admin', 'admin', 'bdh']);
        $assignableUsers = $canAssignCreator
            ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $template = 'newtheme.quotations.create';

        return view($template, [
            'config' => $config,
            'user' => $user,
            'userLocations' => $userLocations,
            'defaultLocationId' => $defaultLocationId,
            'bankLocationMap' => $bankLocationMap,
            'locStates' => $locStates,
            'userBranches' => $userBranches,
            'defaultBranchId' => $defaultBranchId,
            'canAssignCreator' => $canAssignCreator,
            'assignableUsers' => $assignableUsers,
            'pageKey' => 'quotations',
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

        // Admin / super_admin / bdh can attribute a quotation to another user.
        // Anyone else is locked to their own id regardless of what's posted.
        $targetUserId = $user->id;
        if (! empty($input['createdByUserId']) && $user->hasAnyRole(['super_admin', 'admin', 'bdh'])) {
            $requested = (int) $input['createdByUserId'];
            if (User::where('id', $requested)->where('is_active', true)->exists()) {
                $targetUserId = $requested;
            }
        }

        $result = $this->quotationService->generate($input, $targetUserId);

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
     * Show the edit form for an existing quotation. Authorisation is enforced
     * by `Quotation::isEditableBy()` (creator + branch_manager/bdh of the
     * quotation's branch + super_admin / view_all_quotations).
     */
    public function edit(Quotation $quotation): View|RedirectResponse
    {
        $user = Auth::user();

        abort_unless($quotation->isEditableBy($user), 403, 'You cannot edit this quotation.');

        $quotation->load(['banks.emiEntries', 'documents' => fn ($q) => $q->orderBy('sequence')->orderBy('id')]);

        $config = $this->configService->load();
        $config['banks'] = Bank::active()->orderBy('name')->pluck('name')->toArray();

        $allBanks = Bank::active()->with('locations')->orderBy('name')->get();
        $bankLocationMap = [];
        foreach ($allBanks as $bank) {
            $bankLocationMap[$bank->name] = $bank->locations->pluck('id')->toArray();
        }

        $isAdminOrSuper = $user->hasAnyRole(['super_admin', 'admin']);
        $userLocations = $isAdminOrSuper
            ? Location::cities()->active()->with('parent')->get()
            : $user->locations()->with('parent')->get();
        $locStates = Location::with('children')->states()->active()->orderBy('name')->get();
        $userBranches = $isAdminOrSuper
            ? Branch::active()->with('location.parent')->orderBy('name')->get()
            : $user->branches()->where('is_active', true)->with('location.parent')->orderBy('name')->get();

        return view('newtheme.quotations.edit', [
            'quotation' => $quotation,
            'config' => $config,
            'user' => $user,
            'userLocations' => $userLocations,
            'defaultLocationId' => $quotation->location_id,
            'bankLocationMap' => $bankLocationMap,
            'locStates' => $locStates,
            'userBranches' => $userBranches,
            'defaultBranchId' => $quotation->branch_id,
            'canAssignCreator' => false,
            'assignableUsers' => collect(),
            'pageKey' => 'quotations',
        ]);
    }

    /**
     * Persist changes to an existing quotation. Same validation surface as
     * `generate()`; replaces banks/EMIs/documents wholesale and regenerates
     * the cached PDF.
     */
    public function update(Request $request, Quotation $quotation)
    {
        $user = Auth::user();

        abort_unless($quotation->isEditableBy($user), 403, 'You cannot edit this quotation.');

        $input = $request->all();

        // Preserve original prepared-by if the form did not supply replacements.
        if (empty($input['preparedByName'])) {
            $input['preparedByName'] = $quotation->prepared_by_name ?? $user->name;
        }
        if (empty($input['preparedByMobile'])) {
            $input['preparedByMobile'] = $quotation->prepared_by_mobile ?? ($user->phone ?? '');
        }

        $result = $this->quotationService->update($quotation, $input);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 422);
        }

        ActivityLog::log('update_quotation', $quotation->fresh(), [
            'customer_name' => $input['customerName'] ?? '',
            'loan_amount' => $input['loanAmount'] ?? 0,
            'banks_count' => count($input['banks'] ?? []),
            'docs_excluded' => count(array_filter($input['documents'] ?? [], fn ($d) => ! empty($d['excluded']))),
        ]);

        return response()->json([
            'success' => true,
            'redirect' => route('quotations.show', $quotation),
        ]);
    }

    /**
     * Show quotation details.
     */
    public function show(Quotation $quotation)
    {
        $user = Auth::user();

        // Authorization: user can view own quotations, or all if has permission
        if (! $quotation->isVisibleTo($user)) {
            abort(403, 'You do not have access to this quotation.');
        }

        $quotation->load([
            'banks.emiEntries',
            'documents' => fn ($q) => $q->orderBy('sequence')->orderBy('id'),
            'user', 'location.parent', 'heldBy', 'cancelledBy',
        ]);

        $config = $this->configService->load();

        $template = 'newtheme.quotations.show';

        return view($template, [
            'quotation' => $quotation,
            'holdReasons' => $config['quotationHoldReasons'] ?? [],
            'cancelReasons' => $config['quotationCancelReasons'] ?? [],
            'pageKey' => 'quotations',
        ]);
    }

    /**
     * Download a quotation PDF (branded or plain).
     */
    public function download(Quotation $quotation, Request $request)
    {
        $user = Auth::user();
        $branded = (bool) $request->query('branded', '1');

        // Authorization
        if (! $quotation->isVisibleTo($user)) {
            abort(403, 'You do not have access to this quotation.');
        }

        // Check specific branding permission
        $requiredPermission = $branded ? 'download_pdf_branded' : 'download_pdf_plain';
        if (! $user->hasPermission($requiredPermission)) {
            abort(403, 'You do not have permission to download this PDF variant.');
        }

        // Plain PDFs are always regenerated (no caching for unbranded)
        if (! $branded) {
            $result = $this->regeneratePdf($quotation, false);
            if ($result && file_exists($result['path'])) {
                return response()->download($result['path'], $result['filename'], [
                    'Content-Type' => 'application/pdf',
                ])->deleteFileAfterSend(true);
            }
            abort(404, 'PDF could not be generated. Please try again.');
        }

        // Branded: use cached PDF (current behavior)
        $filepath = null;
        $filename = $quotation->pdf_filename;

        if ($filename) {
            $filepath = storage_path('app/pdfs/'.$filename);
            if (! file_exists($filepath) && $quotation->pdf_path && file_exists($quotation->pdf_path)) {
                $filepath = $quotation->pdf_path;
            }
        }

        if (! $filepath || ! file_exists($filepath)) {
            $found = $this->findPdfOnDisk($quotation);
            if ($found) {
                $filepath = $found;
                $filename = basename($found);
                $quotation->update(['pdf_filename' => $filename, 'pdf_path' => $found]);
            }
        }

        if (! $filepath || ! file_exists($filepath)) {
            $result = $this->regeneratePdf($quotation, true);
            if ($result && file_exists($result['path'])) {
                $filepath = $result['path'];
                $filename = $result['filename'];
            }
        }

        if (! $filepath || ! file_exists($filepath)) {
            abort(404, 'PDF could not be generated. Please try again.');
        }

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Preview PDF HTML (super_admin only).
     */
    public function previewHtml(Quotation $quotation, Request $request)
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            abort(403, 'HTML preview is restricted to super admins.');
        }

        $branded = (bool) $request->query('branded', '1');
        $templateData = $this->buildTemplateData($quotation, $branded);
        $pdfService = app(PdfGenerationService::class);
        $html = $pdfService->renderHtml($templateData);

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Find a PDF file on disk matching the quotation's customer name and creation date.
     */
    private function findPdfOnDisk(Quotation $quotation): ?string
    {
        if (! $quotation->customer_name) {
            return null;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $quotation->customer_name);
        $pdfDir = storage_path('app/pdfs');

        // Try exact date match first
        if ($quotation->created_at) {
            $datePrefix = $quotation->created_at->format('Y-m-d');
            $matches = glob("{$pdfDir}/Loan_Proposal_{$safeName}_{$datePrefix}*");
            if (! empty($matches)) {
                return end($matches);
            }
        }

        // Fallback: match by customer name only (latest file)
        $matches = glob("{$pdfDir}/Loan_Proposal_{$safeName}_*");

        return ! empty($matches) ? end($matches) : null;
    }

    /**
     * Build template data array from a stored quotation.
     */
    private function buildTemplateData(Quotation $quotation, bool $branded = true): array
    {
        $quotation->load(['banks.emiEntries', 'documents']);

        $config = $this->configService->load();

        $templateData = [
            'customerName' => $quotation->customer_name,
            'customerType' => $quotation->customer_type,
            'loanAmount' => $quotation->loan_amount,
            'date' => $quotation->created_at->format('d F Y'),
            'companyPhone' => $config['companyPhone'] ?? '+91 XXXXX XXXXX',
            'companyEmail' => $config['companyEmail'] ?? 'info@shf.com',
            'tenures' => $quotation->selected_tenures ?? [5, 10, 15, 20],
            'banks' => [],
            'documents' => $quotation->documents
                ->reject(fn ($d) => $d->is_excluded)
                ->map(fn ($d) => [
                    'en' => $d->document_name_en,
                    'gu' => $d->document_name_gu ?? '',
                ])
                ->values()
                ->toArray(),
            'additionalNotes' => $quotation->additional_notes ?? '',
            'ourServices' => $config['ourServices'] ?? '',
            'preparedByName' => $quotation->prepared_by_name ?? '',
            'preparedByMobile' => $quotation->prepared_by_mobile ?? '',
            'branded' => $branded,
        ];

        foreach ($quotation->banks as $bank) {
            $emiByTenure = [];
            foreach ($bank->emiEntries as $emi) {
                $emiByTenure[(int) $emi->tenure_years] = [
                    'emi' => (int) $emi->monthly_emi,
                    'totalInterest' => (int) $emi->total_interest,
                    'totalPayment' => (int) $emi->total_payment,
                ];
            }

            // The PDF template (PdfGenerationService) keys charge rows by
            // pf / admin / stamp_notary / registration_fee / advocate / iom / tc.
            // Only the PF *percent* (pf_charge) and admin *base* (admin_charge)
            // are stored — the displayed PF/admin amounts (incl. GST) are not.
            // Admin amount = base + GST; PF amount is recovered by subtracting
            // every other charge from the stored total, so the regenerated
            // (plain) PDF matches the cached (branded) one exactly.
            $gst = (float) ($config['gstPercent'] ?? 0);
            $adminBase = (int) $bank->admin_charge;
            $adminAmount = $adminBase + (int) round($adminBase * $gst / 100);
            $stampNotary = (int) $bank->stamp_notary;
            $registrationFee = (int) $bank->registration_fee;
            $advocate = (int) $bank->advocate_fees;
            $iom = (int) $bank->iom_charge;
            $tc = (int) $bank->tc_report;
            $extra1Amt = (int) ($bank->extra1_amount ?? 0);
            $extra2Amt = (int) ($bank->extra2_amount ?? 0);
            $totalCharges = (int) $bank->total_charges;
            $pfAmount = max(0, $totalCharges - $adminAmount - $stampNotary - $registrationFee - $advocate - $iom - $tc - $extra1Amt - $extra2Amt);

            $templateData['banks'][] = [
                'name' => $bank->bank_name,
                'roiMin' => (float) $bank->roi_min,
                'roiMax' => (float) $bank->roi_max,
                'charges' => [
                    'pf' => $pfAmount,
                    'admin' => $adminAmount,
                    'stamp_notary' => $stampNotary,
                    'registration_fee' => $registrationFee,
                    'advocate' => $advocate,
                    'iom' => $iom,
                    'tc' => $tc,
                    'extra1Name' => $bank->extra1_name ?? '',
                    'extra1Amt' => $extra1Amt,
                    'extra2Name' => $bank->extra2_name ?? '',
                    'extra2Amt' => $extra2Amt,
                    'total' => $totalCharges,
                ],
                'emiByTenure' => $emiByTenure,
            ];
        }

        return $templateData;
    }

    /**
     * Regenerate a PDF from stored quotation data and save to disk.
     */
    private function regeneratePdf(Quotation $quotation, bool $branded = true): ?array
    {
        $templateData = $this->buildTemplateData($quotation, $branded);

        $pdfService = app(PdfGenerationService::class);
        $result = $pdfService->generate($templateData);

        if (isset($result['error'])) {
            return null;
        }

        // Only cache branded PDFs to the database
        if ($branded) {
            $quotation->update([
                'pdf_filename' => $result['filename'],
                'pdf_path' => $result['path'],
            ]);
        }

        return $result;
    }

    /**
     * Download PDF by filename (for legacy/JS compatibility).
     */
    public function downloadByFilename(Request $request)
    {
        $filename = basename($request->query('file', ''));

        if (! $filename) {
            abort(400, 'No file specified.');
        }

        $filepath = storage_path('app/pdfs/'.$filename);

        if (! file_exists($filepath)) {
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
        if (! $quotation->isVisibleTo($user)) {
            abort(403, 'You cannot delete this quotation.');
        }

        // Block deletion if converted to loan
        if ($quotation->loan_id) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Cannot delete quotation — it has been converted to Loan #'.$quotation->loan?->loan_number], 422);
            }

            return redirect()->back()->with('error', 'Cannot delete quotation — it has been converted to a loan.');
        }

        $customerName = $quotation->customer_name;
        $filename = $quotation->pdf_filename;

        // Delete PDF file
        if ($quotation->pdf_path && file_exists($quotation->pdf_path)) {
            @unlink($quotation->pdf_path);
        }
        $storagePath = storage_path('app/pdfs/'.$quotation->pdf_filename);
        if (file_exists($storagePath)) {
            @unlink($storagePath);
        }

        $quotation->delete();

        ActivityLog::log('delete_quotation', null, [
            'customer_name' => $customerName,
            'filename' => $filename,
        ]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Quotation deleted.']);
        }

        return redirect()->route('dashboard')->with('success', 'Quotation deleted.');
    }

    /**
     * Put a quotation on hold. Auto-creates a DVR with the provided follow-up date.
     */
    public function hold(Request $request, Quotation $quotation)
    {
        $user = Auth::user();

        $this->authorizeMutation($quotation, $user);

        if ($quotation->is_cancelled) {
            return $this->statusError('This quotation is cancelled and cannot be put on hold.');
        }
        if ($quotation->is_on_hold) {
            return $this->statusError('This quotation is already on hold.');
        }
        if ($quotation->is_converted) {
            return $this->statusError('This quotation has been converted to a loan and cannot be put on hold.');
        }

        $reasonKeys = $this->reasonKeys('quotationHoldReasons');

        $validated = $request->validate([
            'reason_key' => ['required', 'string', 'in:'.implode(',', $reasonKeys)],
            'note' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['required', 'date_format:d/m/Y', 'after:today'],
        ], [
            'reason_key.in' => 'Please choose a valid hold reason.',
            'follow_up_date.after' => 'Follow-up date must be in the future.',
        ]);

        $followUpDate = Carbon::createFromFormat('d/m/Y', $validated['follow_up_date'])->toDateString();

        DB::transaction(function () use ($quotation, $user, $validated, $followUpDate) {
            $quotation->update([
                'status' => Quotation::STATUS_ON_HOLD,
                'hold_reason_key' => $validated['reason_key'],
                'hold_note' => $validated['note'] ?? null,
                'hold_follow_up_date' => $followUpDate,
                'held_at' => now(),
                'held_by' => $user->id,
            ]);

            $this->createFollowUpDvr($quotation, $user, $followUpDate, $validated);
        });

        $this->notifyCreatorOfAction($quotation, $user, 'put on hold', 'warning');

        ActivityLog::log('hold_quotation', $quotation, [
            'reason_key' => $validated['reason_key'],
            'follow_up_date' => $followUpDate,
        ]);

        return $this->statusSuccess('Quotation put on hold. Follow-up visit created.');
    }

    /**
     * Cancel a quotation (terminal state — not resumable).
     */
    public function cancel(Request $request, Quotation $quotation)
    {
        $user = Auth::user();

        $this->authorizeMutation($quotation, $user);

        if ($quotation->is_cancelled) {
            return $this->statusError('This quotation is already cancelled.');
        }
        if ($quotation->is_converted) {
            return $this->statusError('This quotation has been converted to a loan and cannot be cancelled.');
        }

        $reasonKeys = $this->reasonKeys('quotationCancelReasons');

        $validated = $request->validate([
            'reason_key' => ['required', 'string', 'in:'.implode(',', $reasonKeys)],
            'note' => ['nullable', 'string', 'max:5000'],
        ], [
            'reason_key.in' => 'Please choose a valid cancel reason.',
        ]);

        $quotation->update([
            'status' => Quotation::STATUS_CANCELLED,
            'cancel_reason_key' => $validated['reason_key'],
            'cancel_note' => $validated['note'] ?? null,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $this->notifyCreatorOfAction($quotation, $user, 'cancelled', 'error');

        ActivityLog::log('cancel_quotation', $quotation, [
            'reason_key' => $validated['reason_key'],
        ]);

        return $this->statusSuccess('Quotation cancelled.');
    }

    /**
     * Resume an on-hold quotation back to active. Cancelled quotations are terminal.
     */
    public function resume(Quotation $quotation)
    {
        $user = Auth::user();

        $this->authorizeMutation($quotation, $user);

        if (! $quotation->is_on_hold) {
            return $this->statusError('Only on-hold quotations can be resumed.');
        }

        $quotation->update([
            'status' => Quotation::STATUS_ACTIVE,
            'hold_reason_key' => null,
            'hold_note' => null,
            'hold_follow_up_date' => null,
            'held_at' => null,
            'held_by' => null,
        ]);

        $this->notifyCreatorOfAction($quotation, $user, 'resumed', 'info');

        ActivityLog::log('resume_quotation', $quotation);

        return $this->statusSuccess('Quotation resumed.');
    }

    // ── Helpers for hold / cancel / resume ──

    private function authorizeMutation(Quotation $quotation, User $user): void
    {
        if (! $quotation->isVisibleTo($user)) {
            abort(403, 'You cannot modify this quotation.');
        }
    }

    private function reasonKeys(string $configKey): array
    {
        $reasons = $this->configService->get($configKey, []);

        return array_values(array_filter(array_map(
            fn ($r) => $r['key'] ?? null,
            (array) $reasons
        )));
    }

    private function createFollowUpDvr(Quotation $quotation, User $user, string $followUpDate, array $validated): void
    {
        $reasonLabel = $this->resolveReasonLabel('quotationHoldReasons', $validated['reason_key']);
        $noteSuffix = ! empty($validated['note']) ? ' — '.$validated['note'] : '';

        DailyVisitReport::create([
            'user_id' => $user->id,
            'visit_date' => today()->toDateString(),
            'contact_name' => $quotation->customer_name,
            'contact_phone' => $quotation->prepared_by_mobile,
            'contact_type' => 'existing_customer',
            'purpose' => 'follow_up',
            'notes' => "Quotation #{$quotation->id} put on hold. Reason: {$reasonLabel}.{$noteSuffix}",
            'follow_up_needed' => true,
            'follow_up_date' => $followUpDate,
            'is_follow_up_done' => false,
            'quotation_id' => $quotation->id,
            'branch_id' => $quotation->branch_id ?? $user->default_branch_id,
        ]);
    }

    private function resolveReasonLabel(string $configKey, string $key): string
    {
        $reasons = $this->configService->get($configKey, []);
        foreach ((array) $reasons as $reason) {
            if (($reason['key'] ?? null) === $key) {
                return $reason['label_en'] ?? $key;
            }
        }

        return $key;
    }

    private function notifyCreatorOfAction(Quotation $quotation, User $actor, string $actionLabel, string $type): void
    {
        if (! $quotation->user_id || $quotation->user_id === $actor->id) {
            return;
        }

        $this->notificationService->notify(
            $quotation->user_id,
            'Quotation '.ucfirst($actionLabel),
            "Quotation #{$quotation->id} ({$quotation->customer_name}) was {$actionLabel} by {$actor->name}.",
            $type,
            null,
            null,
            route('quotations.show', $quotation),
        );
    }

    private function statusError(string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['error' => $message], 422);
        }

        return redirect()->back()->with('error', $message);
    }

    private function statusSuccess(string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }
}
