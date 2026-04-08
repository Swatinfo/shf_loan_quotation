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
                } elseif (in_array($stageKey, ['technical_valuation', 'property_valuation', 'vehicle_valuation', 'business_valuation'])) {
                    // Valuation stages need valuation_details record
                    $valType = match ($stageKey) {
                        'property_valuation' => 'property', 'vehicle_valuation' => 'vehicle', 'business_valuation' => 'business', default => 'property'
                    };
                    if (! $loan->valuationDetails()->where('valuation_type', $valType)->whereNotNull('market_value')->exists()) {
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

    public function bsmAction(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:initiate_bsm,bsm_initiated,bsm_transfer_to_owner',
            'legal_advisor_id' => 'nullable|exists:users,id',
        ]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'bsm_osv')->firstOrFail();

        if ($validated['action'] === 'initiate_bsm') {
            $assignment->mergeNotesData([
                'bsm_phase' => '2',
                'bsm_original_assignee' => auth()->id(),
                'bsm_selected_legal_advisor' => $validated['legal_advisor_id'] ?? null,
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
                $this->stageService->transferStage($loan, 'bsm_osv', $bankEmployeeId, 'Initiated BSM/OSV');
            }

            return response()->json(['success' => true, 'message' => 'BSM/OSV initiated and assigned to bank employee']);
        }

        if ($validated['action'] === 'bsm_initiated') {
            $notesData = $assignment->getNotesData();
            $assignment->mergeNotesData(['bsm_phase' => '3']);

            $legalAdvisorId = $notesData['bsm_selected_legal_advisor'] ?? null;
            if ($legalAdvisorId) {
                $this->stageService->transferStage($loan, 'bsm_osv', (int) $legalAdvisorId, 'BSM initiated, assigned to legal advisor');
            }

            return response()->json(['success' => true, 'message' => 'BSM initiated, assigned to legal advisor']);
        }

        if ($validated['action'] === 'bsm_transfer_to_owner') {
            $notesData = $assignment->getNotesData();
            $assignment->mergeNotesData(['bsm_phase' => '4']);

            $originalAssignee = $notesData['bsm_original_assignee'] ?? $loan->created_by;
            if ($originalAssignee) {
                $this->stageService->transferStage($loan, 'bsm_osv', (int) $originalAssignee, 'BSM completed by legal, transferred to task owner');
            }

            return response()->json(['success' => true, 'message' => 'Transferred to task owner for completion']);
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
            'app_number' => ['application_number' => 'Application Number'],
            'bsm_osv' => $this->getBsmRequiredFields($data),
            'legal_verification' => ['legal_remarks' => 'Legal Remarks'],
            'technical_valuation', 'property_valuation', 'vehicle_valuation', 'business_valuation' => [], // validated via valuation_details table, not notes
            'title_search' => ['title_search_remarks' => 'Title Search Remarks'],
            'financial_analysis' => ['financial_analysis_remarks' => 'Financial Analysis Remarks'],
            'site_visit' => ['site_visit_remarks' => 'Site Visit Remarks'],
            'rate_pf' => $this->getRatePfRequiredFields($data),
            'sanction' => $this->getSanctionRequiredFields($data),
            'docket' => ['docket_number' => 'Docket Number', 'login_date' => 'Login Date'],
            'kfs' => ['kfs_reference' => 'KFS Reference'],
            'esign' => ['ecs_reference' => 'ECS Reference', 'esign_status' => 'E-Sign Status'],
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
    private function getRatePfRequiredFields(array $data): array
    {
        $bankFields = ['interest_rate' => 'Interest Rate'];
        $officeFields = ['processing_fee' => 'Processing Fee'];

        $rules = [];

        // Determine which section was submitted by checking which fields are present as keys
        if (array_key_exists('interest_rate', $data)) {
            $rules = array_merge($rules, $bankFields);
        }
        if (array_key_exists('processing_fee', $data)) {
            $rules = array_merge($rules, $officeFields);
        }

        return $rules;
    }

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
        ];
    }

    /**
     * BSM/OSV: validate form fields only in phase 3 or 4.
     */
    private function getBsmRequiredFields(array $data): array
    {
        if (! array_key_exists('bsm_remarks', $data)) {
            return [];
        }

        return [
            'bsm_remarks' => 'BSM/OSV Remarks',
            'bsm_verification_status' => 'Verification Status',
        ];
    }

    /**
     * Check if all required data is present in the assignment's notes for auto-completion.
     */
    private function isStageDataComplete(string $stageKey, $assignment): bool
    {
        $notesData = $assignment->getNotesData();

        return match ($stageKey) {
            'app_number' => ! empty($notesData['application_number']),
            'bsm_osv' => in_array($notesData['bsm_phase'] ?? '', ['3', '4'])
                && ! empty($notesData['bsm_remarks']) && ! empty($notesData['bsm_verification_status']),
            'legal_verification' => ! empty($notesData['legal_remarks']),
            'technical_valuation', 'property_valuation', 'vehicle_valuation', 'business_valuation' => false, // auto-completed by ValuationController, not saveNotes
            'title_search' => ! empty($notesData['title_search_remarks']),
            'financial_analysis' => ! empty($notesData['financial_analysis_remarks']),
            'site_visit' => ! empty($notesData['site_visit_remarks']),
            'rate_pf' => isset($notesData['interest_rate']) && $notesData['interest_rate'] !== ''
                && isset($notesData['processing_fee']) && $notesData['processing_fee'] !== '',
            'sanction' => ($notesData['sanction_phase'] ?? '') === '3'
                && ! empty($notesData['sanction_date'])
                && isset($notesData['sanctioned_amount']) && $notesData['sanctioned_amount'] !== '',
            'docket' => ! empty($notesData['docket_number']) && ! empty($notesData['login_date']),
            'kfs' => ! empty($notesData['kfs_reference']),
            'esign' => ! empty($notesData['ecs_reference']) && ($notesData['esign_status'] ?? '') === 'completed',
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
