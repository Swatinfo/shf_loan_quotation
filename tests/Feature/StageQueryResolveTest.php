<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use App\Services\LoanStageService;
use App\Services\StageQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Query resolve authorization:
 *   - Resolvable while status is pending OR responded (not already resolved).
 *   - Allowed: the raiser, the current assignee of the query's stage
 *     assignment, or admin/super_admin.
 *   - Everyone else gets 403 (covers the escalation deadlock where the raiser
 *     hands the stage to BM/BDH and never returns).
 * Plus: transferStage() hands open queries owned by the outgoing assignee to
 * the new assignee (queries routed to the advisor keep their recipient).
 */
class StageQueryResolveTest extends TestCase
{
    use RefreshDatabase;

    private StageQueryService $queryService;

    private LoanStageService $stageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryService = app(StageQueryService::class);
        $this->stageService = app(LoanStageService::class);
        $this->seedRolesAndPermissions();
        $this->seedStage('sanction_decision');
    }

    private function seedRolesAndPermissions(): void
    {
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );
        }

        // The resolve route is gated by `permission:manage_loan_stages`.
        $perm = Permission::firstOrCreate(
            ['slug' => 'manage_loan_stages'],
            ['name' => 'Manage Loan Stages', 'group' => 'Loans'],
        );
        foreach (['admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            $roleId = Role::where('slug', $slug)->value('id');
            DB::table('role_permission')->insertOrIgnore([
                ['role_id' => $roleId, 'permission_id' => $perm->id],
            ]);
        }
    }

    private function seedStage(string $key): void
    {
        Stage::firstOrCreate(
            ['stage_key' => $key],
            [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)),
                'sequence_order' => 1,
                'is_parallel' => false,
                'parent_stage_key' => null,
                'stage_type' => 'sequential',
                'is_enabled' => true,
            ]
        );
    }

    private function makeUser(string $roleSlug): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeLoan(?User $advisor = null): LoanDetail
    {
        $advisor ??= $this->makeUser('loan_advisor');

        $bank = Bank::create(['name' => 'TestBank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'TestBranch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'TestProduct-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'sanction_decision',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ]);
    }

    private function makeAssignment(LoanDetail $loan, ?int $assignedTo): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'sanction_decision',
            'assigned_to' => $assignedTo,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
        ]);
    }

    private function raiseQuery(StageAssignment $assignment, User $raiser): StageQuery
    {
        return $this->queryService->raiseQuery($assignment, 'Need clarification', $raiser->id);
    }

    public function test_raiser_can_resolve_responded_query(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $raiser = $this->makeUser('office_employee');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->makeAssignment($loan, $raiser->id);
        $query = $this->raiseQuery($assignment, $raiser);
        $this->queryService->respondToQuery($query, 'Here you go', $advisor->id);

        $response = $this->actingAs($raiser)->postJson(route('loans.queries.resolve', $query));

        $response->assertOk();
        $this->assertDatabaseHas('stage_queries', ['id' => $query->id, 'status' => 'resolved', 'resolved_by' => $raiser->id]);
        // Self-resolve → no "Query Resolved" notification to the raiser.
        $this->assertDatabaseMissing('shf_notifications', ['user_id' => $raiser->id, 'title' => 'Query Resolved']);
    }

    public function test_raiser_can_resolve_pending_query_without_response(): void
    {
        $raiser = $this->makeUser('office_employee');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $raiser->id);
        $query = $this->raiseQuery($assignment, $raiser);

        $this->assertSame('pending', $query->status);
        $response = $this->actingAs($raiser)->postJson(route('loans.queries.resolve', $query));

        $response->assertOk();
        $this->assertDatabaseHas('stage_queries', ['id' => $query->id, 'status' => 'resolved']);
    }

    public function test_current_assignee_can_resolve_after_escalation(): void
    {
        // Loan-104 scenario: office_employee raises, stage escalates to BDH,
        // raiser never returns — the BDH holding the stage can now close it.
        $raiser = $this->makeUser('office_employee');
        $bdh = $this->makeUser('bdh');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $raiser->id);
        $query = $this->raiseQuery($assignment, $raiser);

        $this->actingAs($raiser);
        $this->stageService->transferStage($loan, 'sanction_decision', $bdh->id, 'Escalated to bdh');

        $response = $this->actingAs($bdh)->postJson(route('loans.queries.resolve', $query));

        $response->assertOk();
        $this->assertDatabaseHas('stage_queries', ['id' => $query->id, 'status' => 'resolved', 'resolved_by' => $bdh->id]);
        // Raiser is told their query was closed by someone else.
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $raiser->id, 'title' => 'Query Resolved']);
        // Resolution is activity-logged.
        $this->assertDatabaseHas('activity_log', ['description' => 'resolve_query', 'subject_id' => $query->id]);
    }

    public function test_admin_can_resolve_any_active_query(): void
    {
        $raiser = $this->makeUser('office_employee');
        $admin = $this->makeUser('admin');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $raiser->id);
        $query = $this->raiseQuery($assignment, $raiser);

        $response = $this->actingAs($admin)->postJson(route('loans.queries.resolve', $query));

        $response->assertOk();
        $this->assertDatabaseHas('stage_queries', ['id' => $query->id, 'status' => 'resolved', 'resolved_by' => $admin->id]);
    }

    public function test_unrelated_user_cannot_resolve(): void
    {
        $raiser = $this->makeUser('office_employee');
        $outsider = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $raiser->id);
        $query = $this->raiseQuery($assignment, $raiser);

        $response = $this->actingAs($outsider)->postJson(route('loans.queries.resolve', $query));

        $response->assertStatus(403);
        $this->assertDatabaseHas('stage_queries', ['id' => $query->id, 'status' => 'pending']);
    }

    public function test_already_resolved_query_returns_422(): void
    {
        $raiser = $this->makeUser('office_employee');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $raiser->id);
        $query = $this->raiseQuery($assignment, $raiser);
        $this->queryService->resolveQuery($query, $raiser->id);

        $response = $this->actingAs($raiser)->postJson(route('loans.queries.resolve', $query->fresh()));

        $response->assertStatus(422);
    }

    public function test_resolving_unblocks_stage_completion(): void
    {
        $raiser = $this->makeUser('office_employee');
        $bdh = $this->makeUser('bdh');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $bdh->id);
        $query = $this->raiseQuery($assignment, $raiser);

        $this->assertTrue($assignment->hasPendingQueries());
        try {
            $this->stageService->updateStageStatus($loan, 'sanction_decision', 'completed', $bdh->id);
            $this->fail('Expected completion to be blocked by the unresolved query.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unresolved queries', $e->getMessage());
        }

        $this->actingAs($bdh)->postJson(route('loans.queries.resolve', $query))->assertOk();

        $this->assertFalse($assignment->fresh()->hasPendingQueries());
    }

    public function test_approve_with_unresolved_query_returns_422_without_half_applying(): void
    {
        $raiser = $this->makeUser('office_employee');
        $bdh = $this->makeUser('bdh');
        $loan = $this->makeLoan();
        $assignment = $this->makeAssignment($loan, $bdh->id);
        $query = $this->raiseQuery($assignment, $raiser);

        $response = $this->actingAs($bdh)->postJson(
            route('loans.stages.sanction-decision-action', $loan),
            ['action' => 'approve'],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('unresolved query', $response->json('error'));
        // Nothing half-applied: loan not sanctioned, stage still in_progress.
        $this->assertFalse((bool) $loan->fresh()->is_sanctioned);
        $this->assertSame('in_progress', $assignment->fresh()->status);

        // After the assignee resolves the query, approve goes through.
        $this->actingAs($bdh)->postJson(route('loans.queries.resolve', $query))->assertOk();
        $this->actingAs($bdh)->postJson(
            route('loans.stages.sanction-decision-action', $loan),
            ['action' => 'approve'],
        )->assertOk();

        $this->assertTrue((bool) $loan->fresh()->is_sanctioned);
        $this->assertSame('completed', $assignment->fresh()->status);
    }

    public function test_transfer_hands_open_queries_owned_by_outgoing_assignee_to_new_assignee(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $officeEmployee = $this->makeUser('office_employee');
        $bankEmployee = $this->makeUser('bank_employee');
        $bdh = $this->makeUser('bdh');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->makeAssignment($loan, $officeEmployee->id);

        // Routed to the office_employee (current assignee) per routing rules.
        $ownedByAssignee = $this->queryService->raiseQuery($assignment, 'For the assignee', $bankEmployee->id);
        // Routed to the advisor — must NOT be touched by the transfer.
        $ownedByAdvisor = $this->queryService->raiseQuery($assignment, 'For the advisor', $officeEmployee->id);

        $this->assertSame($officeEmployee->id, $ownedByAssignee->assigned_to_user_id);
        $this->assertSame($advisor->id, $ownedByAdvisor->assigned_to_user_id);

        $this->actingAs($officeEmployee);
        $this->stageService->transferStage($loan, 'sanction_decision', $bdh->id, 'Escalated');

        $this->assertSame($bdh->id, $ownedByAssignee->fresh()->assigned_to_user_id);
        $this->assertSame($advisor->id, $ownedByAdvisor->fresh()->assigned_to_user_id);
    }
}
