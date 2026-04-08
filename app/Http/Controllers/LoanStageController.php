<?php

namespace App\Http\Controllers;

use App\Models\LoanDetail;
use App\Models\StageQuery;
use App\Models\User;
use App\Services\LoanStageService;
use App\Services\StageQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanStageController extends Controller
{
    public function __construct(
        private LoanStageService $stageService,
        private StageQueryService $queryService,
    ) {}

    public function index(LoanDetail $loan)
    {
        $this->authorizeView($loan);

        $loan->load('valuationDetails');
        $stageAssignments = $this->stageService->getLoanStageStatus($loan);
        $mainStages = $stageAssignments->filter(fn ($sa) => ! $sa->is_parallel_stage && $sa->parent_stage_key === null);
        $subStages = $stageAssignments->filter(fn ($sa) => $sa->is_parallel_stage || $sa->parent_stage_key !== null);
        $progress = $loan->progress;
        $allActiveUsers = User::whereNotNull('task_role')->where('is_active', true)->with(['employerBanks', 'locations', 'branches'])->orderBy('name')->get();
        $stageRoleEligibility = LoanStageService::getAllStageRoleEligibility();

        // Build skip-allowed map from product stage config
        $skipAllowed = [];
        if ($loan->product_id) {
            $productStages = \App\Models\ProductStage::where('product_id', $loan->product_id)
                ->with('stage')
                ->get();
            foreach ($productStages as $ps) {
                if ($ps->stage) {
                    $skipAllowed[$ps->stage->stage_key] = $ps->allow_skip;
                }
            }
        }

        return view('loans.stages', compact('loan', 'mainStages', 'subStages', 'progress', 'allActiveUsers', 'stageRoleEligibility', 'skipAllowed'));
    }

    public function updateStatus(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:in_progress,completed,rejected,skipped',
        ]);

        if ($validated['status'] === 'skipped' && ! auth()->user()->hasPermission('skip_loan_stages')) {
            return response()->json(['error' => 'You do not have permission to skip stages'], 403);
        }

        // Docket phase 2: office employee completes → transfer back to owner (phase 3), don't actually complete yet
        if ($validated['status'] === 'completed' && $stageKey === 'docket') {
            $docketAssignment = $loan->stageAssignments()->where('stage_key', 'docket')->first();
            if ($docketAssignment) {
                $docketNotes = $docketAssignment->getNotesData();
                if (($docketNotes['docket_phase'] ?? '1') === '2') {
                    $docketAssignment->mergeNotesData(['docket_phase' => '3']);
                    $originalAssignee = $docketNotes['docket_original_assignee'] ?? $loan->created_by;
                    if ($originalAssignee) {
                        $this->stageService->transferStage($loan, 'docket', (int) $originalAssignee, 'Docket login done, returned to task owner');
                    }

                    return response()->json([
                        'success' => true,
                        'assignment' => ['stage_key' => 'docket', 'status' => 'in_progress'],
                        'current_stage' => $loan->current_stage,
                        'progress' => ['completed' => $loan->progress?->completed_stages ?? 0, 'total' => $loan->progress?->total_stages ?? 10, 'percentage' => $loan->progress?->overall_percentage ?? 0],
                    ]);
                }
            }
        }

        // Block completion if required stage data is missing
        if ($validated['status'] === 'completed') {
            $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
            if ($assignment) {
                // Check for unresolved queries
                if ($assignment->hasPendingQueries()) {
                    $pendingCount = $assignment->queries()->whereIn('status', ['pending', 'responded'])->count();

                    return response()->json(['error' => "Cannot complete — {$pendingCount} unresolved query/queries. Resolve all queries first."], 422);
                }

                // Check document collection separately
                if ($stageKey === 'document_collection') {
                    $docService = app(\App\Services\LoanDocumentService::class);
                    if (! $docService->allRequiredResolved($loan)) {
                        return response()->json(['error' => 'Cannot complete — not all required documents have been collected.'], 422);
                    }
                } elseif (in_array($stageKey, ['technical_valuation', 'property_valuation'])) {
                    // Valuation stages need valuation_details record
                    if (! $loan->valuationDetails()->where('valuation_type', 'property')->whereNotNull('final_valuation')->exists()) {
                        return response()->json(['error' => 'Cannot complete — fill the valuation form first.'], 422);
                    }
                } elseif (! $this->isStageDataComplete($stageKey, $assignment)) {
                    $missingFields = $this->getFieldErrors($stageKey, $assignment->getNotesData());
                    $msg = ! empty($missingFields)
                        ? 'Cannot complete — missing: '.implode(', ', array_values($missingFields))
                        : 'Cannot complete — required details not filled.';

                    return response()->json(['error' => $msg], 422);
                }
            }
        }

        try {
            $assignment = $this->stageService->updateStageStatus(
                $loan, $stageKey, $validated['status'], auth()->id()
            );

            $loan->refresh();
            $progress = $this->stageService->recalculateProgress($loan);

            return response()->json([
                'success' => true,
                'assignment' => [
                    'stage_key' => $assignment->stage_key,
                    'status' => $assignment->status,
                ],
                'current_stage' => $loan->current_stage,
                'progress' => [
                    'completed' => $progress->completed_stages,
                    'total' => $progress->total_stages,
                    'percentage' => $progress->overall_percentage,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function assign(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $assignment = $this->stageService->assignStage($loan, $stageKey, $validated['user_id']);

        return response()->json([
            'success' => true,
            'assigned_to' => $assignment->assignee?->name,
        ]);
    }

    public function skip(LoanDetail $loan, string $stageKey): JsonResponse
    {
        try {
            $this->stageService->skipStage($loan, $stageKey, auth()->id());
            $loan->refresh();

            return response()->json([
                'success' => true,
                'current_stage' => $loan->current_stage,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function transfer(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $assignment = $this->stageService->transferStage(
                $loan, $stageKey, (int) $validated['user_id'], $validated['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'assigned_to' => $assignment->assignee?->name,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function transferHistory(LoanDetail $loan)
    {
        $transfers = $loan->stageTransfers()
            ->with(['fromUser', 'toUser', 'stageAssignment.stage'])
            ->latest('created_at')
            ->get();

        return view('loans.transfers', compact('loan', 'transfers'));
    }

    public function reject(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $this->stageService->rejectLoan($loan, $stageKey, $validated['reason']);

        return response()->json(['success' => true, 'message' => 'Loan rejected']);
    }

    public function raiseQuery(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
    {
        $validated = $request->validate([
            'query_text' => 'required|string|max:5000',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();
        $query = $this->queryService->raiseQuery($assignment, $validated['query_text'], auth()->id());

        return response()->json(['success' => true, 'query' => $query->load('raisedByUser')]);
    }

    public function respondToQuery(Request $request, StageQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'response_text' => 'required|string|max:5000',
        ]);

        $response = $this->queryService->respondToQuery($query, $validated['response_text'], auth()->id());

        return response()->json(['success' => true, 'response' => $response->load('respondedByUser')]);
    }

    public function resolveQuery(StageQuery $query): JsonResponse
    {
        // Only the user who raised the query can resolve it
        if ($query->raised_by !== auth()->id()) {
            return response()->json(['error' => 'Only the user who raised this query can resolve it.'], 403);
        }

        $this->queryService->resolveQuery($query, auth()->id());

        return response()->json(['success' => true]);
    }

    public function sanctionAction(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:send_for_sanction,sanction_generated',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'sanction')->firstOrFail();

        if ($validated['action'] === 'send_for_sanction') {
            // Store the user who initiated this action (to transfer back after bank completes)
            $assignment->mergeNotesData([
                'sanction_phase' => '2',
                'sanction_original_assignee' => auth()->id(),
            ]);

            // Find bank employee to transfer to
            $bankEmployeeId = $loan->assigned_bank_employee;
            if (! $bankEmployeeId) {
                $bankEmployee = User::where('task_role', 'bank_employee')
                    ->where('task_bank_id', $loan->bank_id)
                    ->where('is_active', true)
                    ->first();
                $bankEmployeeId = $bankEmployee?->id;
            }

            if ($bankEmployeeId) {
                $this->stageService->transferStage($loan, 'sanction', $bankEmployeeId, 'Sent for sanction letter generation');
            }

            return response()->json(['success' => true, 'message' => 'Sent for sanction letter generation']);
        }

        if ($validated['action'] === 'sanction_generated') {
            $notesData = $assignment->getNotesData();
            $assignment->mergeNotesData(['sanction_phase' => '3']);

            // Transfer back to original assignee or loan creator
            // Ensure we don't transfer back to a bank_employee
            $originalAssignee = $notesData['sanction_original_assignee'] ?? null;
            if ($originalAssignee) {
                $origUser = User::find($originalAssignee);
                if ($origUser && $origUser->task_role === 'bank_employee') {
                    $originalAssignee = null;
                }
            }
            $transferTo = $originalAssignee ?? $loan->created_by;
            if ($transferTo) {
                $this->stageService->transferStage($loan, 'sanction', (int) $transferTo, 'Sanction letter generated');
            }

            return response()->json(['success' => true, 'message' => 'Sanction letter marked as generated']);
        }

        return response()->json(['error' => 'Invalid action'], 422);
    }

    public function legalAction(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:send_to_bank,initiate_legal',
            'suggested_legal_advisor' => 'nullable|string|max:255',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'legal_verification')->firstOrFail();

        if ($validated['action'] === 'send_to_bank') {
            $assignment->mergeNotesData([
                'legal_phase' => '2',
                'legal_original_assignee' => auth()->id(),
                'suggested_legal_advisor' => $validated['suggested_legal_advisor'] ?? '',
            ]);

            // Transfer to bank employee
            $bankEmployeeId = $loan->assigned_bank_employee;
            if (! $bankEmployeeId) {
                $bankEmployee = User::where('task_role', 'bank_employee')
                    ->where('task_bank_id', $loan->bank_id)
                    ->where('is_active', true)
                    ->first();
                $bankEmployeeId = $bankEmployee?->id;
            }

            if ($bankEmployeeId) {
                $this->stageService->transferStage($loan, 'legal_verification', $bankEmployeeId, 'Sent to bank for legal verification');
            }

            return response()->json(['success' => true, 'message' => 'Sent to bank employee for legal verification']);
        }

        if ($validated['action'] === 'initiate_legal') {
            $assignment->mergeNotesData([
                'legal_phase' => '3',
                'confirmed_legal_advisor' => $validated['suggested_legal_advisor'] ?? $assignment->getNotesData()['suggested_legal_advisor'] ?? '',
            ]);

            // Transfer back to loan creator (task owner)
            $originalAssignee = $assignment->getNotesData()['legal_original_assignee'] ?? $loan->created_by;
            if ($originalAssignee) {
                $this->stageService->transferStage($loan, 'legal_verification', (int) $originalAssignee, 'Legal initiated, transferred back to task owner');
            }

            return response()->json(['success' => true, 'message' => 'Legal initiated, transferred to task owner']);
        }

        return response()->json(['error' => 'Invalid action'], 422);
    }

    public function esignAction(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:esign_generated,esign_customer_done',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'esign')->firstOrFail();

        if ($validated['action'] === 'esign_generated') {
            $assignment->mergeNotesData([
                'esign_phase' => '2',
                'esign_bank_employee' => auth()->id(),
            ]);

            // Transfer to loan creator / eligible user
            $eligibleUser = $loan->created_by;
            if ($eligibleUser) {
                $this->stageService->transferStage($loan, 'esign', $eligibleUser, 'E-Sign & eNACH generated, sent for customer completion');
            }

            return response()->json(['success' => true, 'message' => 'Sent to task owner for customer completion']);
        }

        if ($validated['action'] === 'esign_customer_done') {
            $notesData = $assignment->getNotesData();
            $assignment->mergeNotesData(['esign_phase' => '3']);

            // Transfer back to bank employee
            $bankEmployeeId = $notesData['esign_bank_employee'] ?? $loan->assigned_bank_employee;
            if ($bankEmployeeId) {
                $this->stageService->transferStage($loan, 'esign', (int) $bankEmployeeId, 'E-Sign completed with customer, returned to bank');
            }

            return response()->json(['success' => true, 'message' => 'Returned to bank employee for final confirmation']);
        }

        return response()->json(['error' => 'Invalid action'], 422);
    }

    public function docketAction(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:send_to_office',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'docket')->firstOrFail();
        $notesData = $assignment->getNotesData();

        if (empty($notesData['login_date'])) {
            return response()->json(['error' => 'Login Date is required'], 422);
        }

        $assignment->mergeNotesData([
            'docket_phase' => '2',
            'docket_original_assignee' => auth()->id(),
        ]);

        // Find office employee to assign
        $officeEmployee = User::where('task_role', 'office_employee')
            ->where('is_active', true)
            ->first();

        if ($officeEmployee) {
            $this->stageService->transferStage($loan, 'docket', $officeEmployee->id, 'Sent for docket login');
        }

        return response()->json(['success' => true, 'message' => 'Sent to office employee for docket login']);
    }

    public function ratePfAction(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:send_to_bank,return_to_owner',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'rate_pf')->firstOrFail();
        $notesData = $assignment->getNotesData();

        // Validate all required fields are filled before action
        $requiredFields = [
            'interest_rate' => 'Interest Rate',
            'repo_rate' => 'Repo Rate',
            'bank_rate' => 'Bank Margin',
            'rate_offered_date' => 'Rate Offered Date',
            'rate_valid_until' => 'Valid Until',
            'bank_reference' => 'Bank Reference',
            'processing_fee' => 'Processing Fee',
            'admin_charges' => 'Admin Charges',
            'processing_fee_gst' => 'PF GST',
            'total_pf' => 'Total PF',
        ];

        $missing = [];
        foreach ($requiredFields as $field => $label) {
            if (! isset($notesData[$field]) || $notesData[$field] === '' || $notesData[$field] === null) {
                $missing[] = $label;
            }
        }
        if (! empty($missing)) {
            return response()->json(['error' => 'Missing required fields: '.implode(', ', $missing)], 422);
        }

        if ($validated['action'] === 'send_to_bank') {
            // Snapshot original values before sending to bank
            $originalValues = [];
            foreach ($requiredFields as $field => $label) {
                $originalValues[$field] = $notesData[$field] ?? '';
            }
            $originalValues['special_conditions'] = $notesData['special_conditions'] ?? '';

            $assignment->mergeNotesData([
                'rate_pf_phase' => '2',
                'rate_pf_original_assignee' => auth()->id(),
                'original_values' => $originalValues,
            ]);

            $bankEmployeeId = $loan->assigned_bank_employee;
            if (! $bankEmployeeId) {
                $bankEmployee = User::where('task_role', 'bank_employee')
                    ->where('task_bank_id', $loan->bank_id)
                    ->where('is_active', true)
                    ->first();
                $bankEmployeeId = $bankEmployee?->id;
            }

            if ($bankEmployeeId) {
                $this->stageService->transferStage($loan, 'rate_pf', $bankEmployeeId, 'Sent for bank rate review');
            }

            return response()->json(['success' => true, 'message' => 'Sent to bank employee for review']);
        }

        if ($validated['action'] === 'return_to_owner') {
            $assignment->mergeNotesData(['rate_pf_phase' => '3']);

            $originalAssignee = $notesData['rate_pf_original_assignee'] ?? $loan->created_by;
            if ($originalAssignee) {
                $this->stageService->transferStage($loan, 'rate_pf', (int) $originalAssignee, 'Bank reviewed rate details, returned to task owner');
            }

            return response()->json(['success' => true, 'message' => 'Returned to task owner']);
        }

        return response()->json(['error' => 'Invalid action'], 422);
    }

    public function saveNotes(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
    {
        $validated = $request->validate([
            'notes_data' => 'required|array',
        ]);

        $notesData = $validated['notes_data'];

        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();

        // Validate required fields only for non-completed stages
        if ($assignment->status !== 'completed') {
            $fieldErrors = $this->getFieldErrors($stageKey, $notesData);
            if (! empty($fieldErrors)) {
                $messages = array_values($fieldErrors);

                return response()->json([
                    'error' => implode(', ', $messages),
                    'field_errors' => $fieldErrors,
                ], 422);
            }
        }

        $assignment->mergeNotesData($notesData);

        // Sync specific fields to loan_details
        if ($stageKey === 'app_number' && ! empty($notesData['application_number'])) {
            $loan->update(['application_number' => $notesData['application_number']]);
        }

        // Calculate and store expected docket date when sanction details are saved
        if ($stageKey === 'sanction' && ! empty($notesData['sanction_date'])) {
            $appNumberAssignment = $loan->stageAssignments()->where('stage_key', 'app_number')->first();
            $appNotes = $appNumberAssignment ? $appNumberAssignment->getNotesData() : [];
            $docketOffset = $appNotes['docket_days_offset'] ?? null;

            if ($docketOffset && $docketOffset !== '0') {
                $sanctionDateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $notesData['sanction_date']);
                $loan->update(['due_date' => $sanctionDateObj->copy()->addDays((int) $docketOffset)->toDateString()]);
            } elseif ($docketOffset === '0' && ! empty($appNotes['custom_docket_date'])) {
                $loan->update(['due_date' => \Carbon\Carbon::createFromFormat('d/m/Y', $appNotes['custom_docket_date'])->toDateString()]);
            }
        }

        // Auto-complete if stage criteria met
        $stageAdvanced = false;
        if (in_array($assignment->status, ['pending', 'in_progress']) && $this->isStageDataComplete($stageKey, $assignment)) {
            if ($assignment->status === 'pending') {
                $this->stageService->updateStageStatus($loan, $stageKey, 'in_progress', auth()->id());
            }
            $this->stageService->updateStageStatus($loan, $stageKey, 'completed', auth()->id());
            $stageAdvanced = true;
        }

        return response()->json(['success' => true, 'stage_advanced' => $stageAdvanced]);
    }

    /**
     * Required fields per stage — returns [field_name => "Label is required"] for missing fields.
     */
    private function getFieldErrors(string $stageKey, array $data): array
    {
        $errors = [];

        $rules = match ($stageKey) {
            'app_number' => ['application_number' => 'Application Number', 'docket_days_offset' => 'Docket Timeline'],
            'bsm_osv' => [],
            'legal_verification' => [],
            'technical_valuation', 'property_valuation' => [], // validated via valuation_details table, not notes
            'rate_pf' => [],
            'sanction' => $this->getSanctionRequiredFields($data),
            'docket' => ['login_date' => 'Login Date'],
            'kfs' => [],
            'esign' => [],
            'otc_clearance' => ['handover_date' => 'Handover Date'],
            default => [],
        };

        foreach ($rules as $field => $label) {
            if (! isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[$field] = "{$label} is required";
            }
        }

        return $errors;
    }

    /**
     * Rate & PF has role-gated sections: bank employee fills rate, task owner fills PF/charges.
     * Only validate required fields that belong to the submitted section.
     */
    /**
     * Sanction stage phase 3: validate form fields only when task owner fills details.
     */
    private function getSanctionRequiredFields(array $data): array
    {
        if (! array_key_exists('sanction_date', $data)) {
            return [];
        }

        return [
            'sanction_date' => 'Sanction Date',
            'sanctioned_amount' => 'Sanctioned Amount',
            'emi_amount' => 'EMI Amount',
        ];
    }

    /**
     * BSM/OSV: validate form fields only in phase 3 or 4.
     */
    /**
     * Check if all required data is present in the assignment's notes for auto-completion.
     */
    private function isStageDataComplete(string $stageKey, $assignment): bool
    {
        $notesData = $assignment->getNotesData();

        return match ($stageKey) {
            'app_number' => ! empty($notesData['application_number']) && isset($notesData['docket_days_offset']) && $notesData['docket_days_offset'] !== '',
            'bsm_osv' => true,
            'legal_verification' => ($notesData['legal_phase'] ?? '') === '3',
            'technical_valuation', 'property_valuation' => false, // auto-completed by ValuationController, not saveNotes
            'rate_pf' => ($notesData['rate_pf_phase'] ?? '') === '3',
            'sanction' => ($notesData['sanction_phase'] ?? '') === '3'
                && ! empty($notesData['sanction_date'])
                && isset($notesData['sanctioned_amount']) && $notesData['sanctioned_amount'] !== '',
            'docket' => in_array($notesData['docket_phase'] ?? '', ['2', '3']),
            'kfs' => true,
            'esign' => ($notesData['esign_phase'] ?? '') === '3',
            'otc_clearance' => ! empty($notesData['handover_date']),
            default => false,
        };
    }

    private function authorizeView(LoanDetail $loan): void
    {
        $user = auth()->user();
        if ($user->hasPermission('view_all_loans')) {
            return;
        }
        if ($loan->created_by === $user->id || $loan->assigned_advisor === $user->id) {
            return;
        }
        if ($loan->stageAssignments()->where('assigned_to', $user->id)->exists()) {
            return;
        }
        if ($user->isTaskRole('branch_manager') && $loan->branch_id) {
            if ($user->branches()->where('branches.id', $loan->branch_id)->exists()) {
                return;
            }
        }
        abort(403);
    }
}
