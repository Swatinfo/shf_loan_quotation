<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loan listing exposes the current task owner (active-stage assignee, distinct
 * from the loan owner) as a column and a filter.
 */
class LoanListingTaskOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'loan_advisor'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        Stage::firstOrCreate(
            ['stage_key' => 'legal_verification'],
            ['stage_name_en' => 'Legal Verification', 'sequence_order' => 1, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'is_enabled' => true]
        );
    }

    private function makeUser(string $roleSlug = 'loan_advisor'): User
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

    private function makeLoan(User $owner, User $taskOwner): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'legal_verification',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ]);

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $taskOwner->id,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
        ]);

        return $loan;
    }

    /**
     * A loan sitting in parallel_processing with two in-progress sub-stages,
     * each assigned to a different user.
     *
     * @return array{0: LoanDetail, 1: User, 2: User}
     */
    private function makeParallelLoan(User $owner, User $legalUser, User $techUser): array
    {
        foreach (['legal_verification', 'technical_valuation'] as $i => $key) {
            Stage::firstOrCreate(
                ['stage_key' => $key],
                ['stage_name_en' => $key, 'sequence_order' => 10 + $i, 'is_parallel' => true, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'is_enabled' => true]
            );
        }

        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'parallel_processing',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ]);

        // Parent assignment has no assignee; work lives in the sub-stages.
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'parallel_processing', 'assigned_to' => null, 'status' => 'in_progress', 'is_parallel_stage' => false]);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'legal_verification', 'parent_stage_key' => 'parallel_processing', 'assigned_to' => $legalUser->id, 'status' => 'in_progress', 'is_parallel_stage' => true]);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'technical_valuation', 'parent_stage_key' => 'parallel_processing', 'assigned_to' => $techUser->id, 'status' => 'in_progress', 'is_parallel_stage' => true]);

        return [$loan, $legalUser, $techUser];
    }

    public function test_parallel_loan_shows_all_active_sub_stage_owners_and_filters_by_each(): void
    {
        $admin = $this->makeUser('super_admin');
        $owner = $this->makeUser();
        $legalUser = $this->makeUser();
        $techUser = $this->makeUser();
        [$loan] = $this->makeParallelLoan($owner, $legalUser, $techUser);

        // Column lists BOTH sub-stage owners.
        $loan->load('stageAssignments.assignee');
        $names = $loan->current_task_owners->pluck('name')->all();
        $this->assertContains($legalUser->name, $names);
        $this->assertContains($techUser->name, $names);

        // Filtering by either active sub-stage owner returns the loan.
        foreach ([$legalUser, $techUser] as $u) {
            $matched = $this->actingAs($admin)
                ->getJson(route('loans.data', ['draw' => 1, 'start' => 0, 'length' => 25, 'user' => $u->id]))
                ->assertOk()
                ->json('data');
            $this->assertNotNull(collect($matched)->firstWhere('loan_number', $loan->loan_number), "filter by {$u->name} should match");
        }

        // The loan owner (not a sub-stage assignee) does NOT match.
        $excluded = $this->actingAs($admin)
            ->getJson(route('loans.data', ['draw' => 1, 'start' => 0, 'length' => 25, 'user' => $owner->id]))
            ->assertOk()
            ->json('data');
        $this->assertNull(collect($excluded)->firstWhere('loan_number', $loan->loan_number));
    }

    public function test_current_task_owner_accessor_returns_active_stage_assignee(): void
    {
        $owner = $this->makeUser();
        $taskOwner = $this->makeUser();
        $loan = $this->makeLoan($owner, $taskOwner)->load('stageAssignments.assignee');

        $this->assertSame($taskOwner->id, $loan->current_task_owner?->id);
        // The loan owner remains the advisor/creator, not the task owner.
        $this->assertSame($owner->id, $loan->current_owner?->id);
    }

    public function test_loan_data_includes_task_owner_and_filters_by_it(): void
    {
        $admin = $this->makeUser('super_admin');
        $owner = $this->makeUser();
        $taskOwner = $this->makeUser();
        $loan = $this->makeLoan($owner, $taskOwner);

        // Column present and correct.
        $rows = $this->actingAs($admin)
            ->getJson(route('loans.data', ['draw' => 1, 'start' => 0, 'length' => 25]))
            ->assertOk()
            ->json('data');
        $row = collect($rows)->firstWhere('loan_number', $loan->loan_number);
        $this->assertNotNull($row);
        $this->assertSame($taskOwner->name, $row['task_owner_info']);
        // Loan # links to stages, customer name links to the loan view.
        $this->assertStringContainsString('/stages', $row['stages_url']);
        $this->assertStringContainsString('/loans/'.$loan->id, $row['show_url']);

        // Filtering by the task owner returns the loan.
        $matched = $this->actingAs($admin)
            ->getJson(route('loans.data', ['draw' => 1, 'start' => 0, 'length' => 25, 'user' => $taskOwner->id]))
            ->assertOk()
            ->json('data');
        $this->assertNotNull(collect($matched)->firstWhere('loan_number', $loan->loan_number));

        // Filtering by a user who is NOT the current task owner excludes it.
        $excluded = $this->actingAs($admin)
            ->getJson(route('loans.data', ['draw' => 1, 'start' => 0, 'length' => 25, 'user' => $owner->id]))
            ->assertOk()
            ->json('data');
        $this->assertNull(collect($excluded)->firstWhere('loan_number', $loan->loan_number));
    }
}
