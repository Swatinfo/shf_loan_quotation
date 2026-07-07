<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShfNotification;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\StageQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Routing rules for raised queries:
 *   - Default recipient = loan.assigned_advisor (fallback created_by).
 *   - Bank-employee raiser hitting an assignment whose current assignee is an
 *     office_employee → route to that office_employee.
 *   - Always also notify the loan's assigned_advisor, deduped, skipping raiser.
 */
class StageQueryRoutingTest extends TestCase
{
    use RefreshDatabase;

    private StageQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StageQueryService::class);
        $this->seedRoles();
        $this->seedStage('legal_verification');
    }

    private function seedRoles(): void
    {
        foreach (['loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );
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

    private function makeLoan(?User $advisor = null, ?User $creator = null): LoanDetail
    {
        $advisor ??= $this->makeUser('loan_advisor');
        $creator ??= $advisor;

        $bank = Bank::create(['name' => 'TestBank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'TestBranch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'TestProduct-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'legal_verification',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $creator->id,
            'assigned_advisor' => $advisor->id,
        ]);
    }

    private function makeAssignment(LoanDetail $loan, ?int $assignedTo): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $assignedTo,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
        ]);
    }

    public function test_bank_employee_query_in_task_owner_phase_routes_to_advisor(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $bankEmployee = $this->makeUser('bank_employee');
        $loan = $this->makeLoan($advisor);
        // Phase 1 of legal_verification: task owner (advisor) holds the assignment.
        $assignment = $this->makeAssignment($loan, $advisor->id);

        $query = $this->service->raiseQuery($assignment, 'Need clarification', $bankEmployee->id);

        $this->assertSame($advisor->id, $query->assigned_to_user_id);
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $advisor->id, 'title' => 'Query Raised']);
        // Raiser must not receive a notification.
        $this->assertSame(0, ShfNotification::where('user_id', $bankEmployee->id)->count());
    }

    public function test_bank_employee_query_in_office_phase_routes_to_office_employee(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $officeEmployee = $this->makeUser('office_employee');
        $bankEmployee = $this->makeUser('bank_employee');
        $loan = $this->makeLoan($advisor);
        // Phase where office_employee currently owns the assignment (e.g. docket P2).
        $assignment = $this->makeAssignment($loan, $officeEmployee->id);

        $query = $this->service->raiseQuery($assignment, 'Need office input', $bankEmployee->id);

        $this->assertSame($officeEmployee->id, $query->assigned_to_user_id);
        // Both office_employee (recipient) and advisor (CC) get notified.
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $officeEmployee->id, 'title' => 'Query Raised']);
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $advisor->id, 'title' => 'Query Raised on Your Loan']);
    }

    public function test_bank_employee_query_in_office_phase_without_office_user_falls_back_to_advisor(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $bankEmployee = $this->makeUser('bank_employee');
        $loan = $this->makeLoan($advisor);
        // Office phase but the slot is unfilled — assignment.assigned_to is null.
        $assignment = $this->makeAssignment($loan, null);

        $query = $this->service->raiseQuery($assignment, 'Where is the valuation?', $bankEmployee->id);

        $this->assertSame($advisor->id, $query->assigned_to_user_id);
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $advisor->id]);
    }

    public function test_office_employee_query_routes_to_advisor(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $officeEmployee = $this->makeUser('office_employee');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->makeAssignment($loan, $officeEmployee->id);

        $query = $this->service->raiseQuery($assignment, 'Need advisor input', $officeEmployee->id);

        $this->assertSame($advisor->id, $query->assigned_to_user_id);
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $advisor->id, 'title' => 'Query Raised']);
        $this->assertSame(0, ShfNotification::where('user_id', $officeEmployee->id)->count());
    }

    public function test_advisor_self_raise_creates_query_without_self_notification(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->makeAssignment($loan, $advisor->id);

        $query = $this->service->raiseQuery($assignment, 'Note to self', $advisor->id);

        $this->assertSame($advisor->id, $query->assigned_to_user_id);
        // Recipient == advisor == raiser → no notification fan-out.
        $this->assertSame(0, ShfNotification::where('user_id', $advisor->id)->count());
    }

    public function test_recipient_and_advisor_both_notified_when_different(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $officeEmployee = $this->makeUser('office_employee');
        $bankEmployee = $this->makeUser('bank_employee');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->makeAssignment($loan, $officeEmployee->id);

        $this->service->raiseQuery($assignment, 'Cross-check please', $bankEmployee->id);

        $this->assertSame(1, ShfNotification::where('user_id', $officeEmployee->id)->count());
        $this->assertSame(1, ShfNotification::where('user_id', $advisor->id)->count());
        $this->assertSame(0, ShfNotification::where('user_id', $bankEmployee->id)->count());
    }

    public function test_recipient_falls_back_to_created_by_when_advisor_null(): void
    {
        $creator = $this->makeUser('loan_advisor');
        $bankEmployee = $this->makeUser('bank_employee');
        $loan = $this->makeLoan(creator: $creator);
        // Loan has no assigned_advisor set — recipient should fall back to created_by.
        $loan->update(['assigned_advisor' => null]);
        $loan->refresh();
        $assignment = $this->makeAssignment($loan, null);

        $query = $this->service->raiseQuery($assignment, 'Need owner input', $bankEmployee->id);

        $this->assertSame($creator->id, $query->assigned_to_user_id);
        $this->assertDatabaseHas('shf_notifications', ['user_id' => $creator->id, 'title' => 'Query Raised']);
    }
}
