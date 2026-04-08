<?php

namespace Tests\Feature;

use App\Models\LoanDetail;
use App\Models\LoanProgress;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use App\Services\LoanStageService;
use App\Services\StageQueryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoanStageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(StageSeeder::class);
    }

    private function createUser(string $role = 'admin', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'task_role' => 'loan_advisor',
        ], $overrides));
    }

    private function createLoanWithStages(User $user): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'status' => LoanDetail::STATUS_ACTIVE,
            'current_stage' => 'inquiry',
            'created_by' => $user->id,
        ]);

        app(LoanStageService::class)->initializeStages($loan);

        return $loan;
    }

    // ── Stage Initialization ──

    public function test_initialize_stages_creates_base_assignments(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        // Should have 14 base stages + their sub-stages
        $mainCount = $loan->stageAssignments()->mainStages()->count();
        $this->assertGreaterThanOrEqual(10, $mainCount);

        // All should start as pending
        $this->assertEquals(
            $loan->stageAssignments()->count(),
            $loan->stageAssignments()->where('status', 'pending')->count()
        );
    }

    public function test_initialize_stages_creates_progress_record(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $progress = $loan->progress;
        $this->assertNotNull($progress);
        $this->assertEquals(0, $progress->completed_stages);
        $this->assertEquals(0, $progress->overall_percentage);
    }

    // ── Stage Transitions ──

    public function test_can_transition_pending_to_in_progress(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $assignment = $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);

        $this->assertEquals('in_progress', $assignment->status);
        $this->assertNotNull($assignment->started_at);
    }

    public function test_can_transition_in_progress_to_completed(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $assignment = $service->updateStageStatus($loan, 'inquiry', 'completed', $user->id);

        $this->assertEquals('completed', $assignment->status);
        $this->assertNotNull($assignment->completed_at);
        $this->assertEquals($user->id, $assignment->completed_by);
    }

    public function test_cannot_transition_completed_to_in_progress(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $service->updateStageStatus($loan, 'inquiry', 'completed', $user->id);

        $this->expectException(\RuntimeException::class);
        $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
    }

    public function test_cannot_transition_pending_to_completed_directly(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $this->expectException(\RuntimeException::class);
        $service->updateStageStatus($loan, 'inquiry', 'completed', $user->id);
    }

    public function test_skip_stage(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $assignment = $service->skipStage($loan, 'inquiry', $user->id);

        $this->assertEquals('skipped', $assignment->status);
        $this->assertNotNull($assignment->completed_at);
    }

    // ── Auto-Advance ──

    public function test_completing_stage_advances_current_stage(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $service->updateStageStatus($loan, 'inquiry', 'completed', $user->id);

        $loan->refresh();
        $this->assertEquals('document_selection', $loan->current_stage);
    }

    public function test_progress_recalculates_on_completion(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $service->updateStageStatus($loan, 'inquiry', 'completed', $user->id);

        $progress = $loan->progress()->first();
        $this->assertGreaterThan(0, $progress->completed_stages);
        $this->assertGreaterThan(0, $progress->overall_percentage);
    }

    public function test_auto_complete_stages(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->autoCompleteStages($loan, ['inquiry', 'document_selection']);

        $inquiry = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $docSelection = $loan->stageAssignments()->where('stage_key', 'document_selection')->first();

        $this->assertEquals('completed', $inquiry->status);
        $this->assertEquals('completed', $docSelection->status);
    }

    // ── Assignment ──

    public function test_can_manually_assign_stage(): void
    {
        $user = $this->createUser();
        $assignee = $this->createUser('staff');
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $assignment = $service->assignStage($loan, 'inquiry', $assignee->id);

        $this->assertEquals($assignee->id, $assignment->assigned_to);
    }

    // ── Transfer ──

    public function test_can_transfer_stage(): void
    {
        $user = $this->createUser();
        $toUser = $this->createUser('staff');
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->assignStage($loan, 'inquiry', $user->id);

        Auth::login($user);
        $assignment = $service->transferStage($loan, 'inquiry', $toUser->id, 'User on leave');

        $this->assertEquals($toUser->id, $assignment->assigned_to);

        // Transfer history should be recorded
        $this->assertDatabaseHas('stage_transfers', [
            'loan_id' => $loan->id,
            'stage_key' => 'inquiry',
            'transferred_from' => $user->id,
            'transferred_to' => $toUser->id,
            'reason' => 'User on leave',
            'transfer_type' => 'manual',
        ]);
    }

    public function test_transfer_via_http(): void
    {
        $user = $this->createUser();
        $toUser = $this->createUser('staff');
        $loan = $this->createLoanWithStages($user);
        app(LoanStageService::class)->assignStage($loan, 'inquiry', $user->id);

        $response = $this->actingAs($user)->postJson(
            route('loans.stages.transfer', [$loan, 'inquiry']),
            ['user_id' => $toUser->id, 'reason' => 'Reassignment']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $this->assertEquals($toUser->id, $assignment->assigned_to);
    }

    // ── Rejection ──

    public function test_can_reject_loan(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);
        $service->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);

        $rejectedLoan = $service->rejectLoan($loan, 'inquiry', 'Credit score too low', $user->id);

        $this->assertEquals(LoanDetail::STATUS_REJECTED, $rejectedLoan->status);
        $this->assertEquals('inquiry', $rejectedLoan->rejected_stage);
        $this->assertEquals('Credit score too low', $rejectedLoan->rejection_reason);
        $this->assertNotNull($rejectedLoan->rejected_at);
    }

    public function test_reject_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        // Start the stage first
        app(LoanStageService::class)->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);

        $response = $this->actingAs($user)->postJson(
            route('loans.stages.reject', [$loan, 'inquiry']),
            ['reason' => 'Insufficient documents']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_REJECTED, $loan->status);
    }

    // ── Queries ──

    public function test_raising_query_blocks_stage_completion(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $stageService = app(LoanStageService::class);
        $queryService = app(StageQueryService::class);

        $stageService->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();

        // Raise a query
        $queryService->raiseQuery($assignment, 'Need clarification on income proof', $user->id);

        // Try to complete — should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unresolved queries');
        $stageService->updateStageStatus($loan, 'inquiry', 'completed', $user->id);
    }

    public function test_resolving_query_allows_completion(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $stageService = app(LoanStageService::class);
        $queryService = app(StageQueryService::class);

        $stageService->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();

        $query = $queryService->raiseQuery($assignment, 'Need clarification', $user->id);
        $queryService->respondToQuery($query, 'Here is the clarification', $user->id);
        $queryService->resolveQuery($query, $user->id);

        // Now completion should work
        $assignment = $stageService->updateStageStatus($loan, 'inquiry', 'completed', $user->id);
        $this->assertEquals('completed', $assignment->status);
    }

    public function test_query_lifecycle(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $stageService = app(LoanStageService::class);
        $queryService = app(StageQueryService::class);

        $stageService->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();

        // Raise
        $query = $queryService->raiseQuery($assignment, 'What is the property value?', $user->id);
        $this->assertEquals('pending', $query->status);

        // Respond
        $response = $queryService->respondToQuery($query, 'Estimated 50 lakh', $user->id);
        $query->refresh();
        $this->assertEquals('responded', $query->status);
        $this->assertNotNull($response->id);

        // Resolve
        $queryService->resolveQuery($query, $user->id);
        $query->refresh();
        $this->assertEquals('resolved', $query->status);
        $this->assertNotNull($query->resolved_at);
    }

    // ── Parallel Processing ──

    public function test_parallel_substages_initialized(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $subStages = $loan->stageAssignments()
            ->where('parent_stage_key', 'parallel_processing')
            ->get();

        // Should have 4 base sub-stages: app_number, bsm_osv, legal_verification, technical_valuation
        $this->assertGreaterThanOrEqual(4, $subStages->count());
    }

    public function test_completing_all_substages_completes_parallel_parent(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $service = app(LoanStageService::class);

        // Auto-complete up to parallel_processing
        $service->autoCompleteStages($loan, ['inquiry', 'document_selection', 'document_collection']);
        $loan->update(['current_stage' => 'parallel_processing']);

        // Start parallel parent
        $parentAssignment = $loan->stageAssignments()->where('stage_key', 'parallel_processing')->first();
        $parentAssignment->update(['status' => 'in_progress', 'started_at' => now()]);

        // Complete all sub-stages
        $subStages = $loan->stageAssignments()
            ->where('parent_stage_key', 'parallel_processing')
            ->get();

        foreach ($subStages as $sub) {
            $sub->update(['status' => 'in_progress', 'started_at' => now()]);
            $service->updateStageStatus($loan, $sub->stage_key, 'completed', $user->id);
        }

        // Parent should now be completed
        $parentAssignment->refresh();
        $this->assertEquals('completed', $parentAssignment->status);

        // Loan should advance past parallel_processing
        $loan->refresh();
        $this->assertEquals('rate_pf', $loan->current_stage);
    }

    // ── HTTP Routes ──

    public function test_stages_index_page_loads(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $response = $this->actingAs($user)->get(route('loans.stages', $loan));
        $response->assertOk();
    }

    public function test_update_stage_status_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $response = $this->actingAs($user)->postJson(
            route('loans.stages.status', [$loan, 'inquiry']),
            ['status' => 'in_progress']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $this->assertEquals('in_progress', $assignment->status);
    }

    public function test_skip_stage_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $response = $this->actingAs($user)->postJson(
            route('loans.stages.skip', [$loan, 'inquiry'])
        );

        $response->assertOk()->assertJson(['success' => true]);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $this->assertEquals('skipped', $assignment->status);
    }

    public function test_raise_query_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);
        app(LoanStageService::class)->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);

        $response = $this->actingAs($user)->postJson(
            route('loans.stages.query', [$loan, 'inquiry']),
            ['query_text' => 'Need more info about property']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('stage_queries', [
            'loan_id' => $loan->id,
            'stage_key' => 'inquiry',
            'status' => 'pending',
        ]);
    }

    public function test_respond_to_query_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);
        $stageService = app(LoanStageService::class);
        $queryService = app(StageQueryService::class);

        $stageService->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $query = $queryService->raiseQuery($assignment, 'Need clarification', $user->id);

        $response = $this->actingAs($user)->postJson(
            route('loans.queries.respond', $query),
            ['response_text' => 'Here is the answer']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $query->refresh();
        $this->assertEquals('responded', $query->status);
    }

    public function test_resolve_query_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);
        $stageService = app(LoanStageService::class);
        $queryService = app(StageQueryService::class);

        $stageService->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $assignment = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $query = $queryService->raiseQuery($assignment, 'Need clarification', $user->id);
        $queryService->respondToQuery($query, 'Response here', $user->id);

        $response = $this->actingAs($user)->postJson(
            route('loans.queries.resolve', $query)
        );

        $response->assertOk()->assertJson(['success' => true]);
        $query->refresh();
        $this->assertEquals('resolved', $query->status);
    }

    public function test_transfer_history_page_loads(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanWithStages($user);

        $response = $this->actingAs($user)->get(route('loans.transfers', $loan));
        $response->assertOk();
    }
}
