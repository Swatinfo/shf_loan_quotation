<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard "Open Queries" widget must be scoped per user:
 *   - queries assigned to the user, OR
 *   - queries on a loan visible to the user (owner / advisor / branch / stage / transfer).
 * Admins with view_all_loans keep seeing every open query.
 */
class DashboardOpenQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['loan_advisor', 'super_admin'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
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

    private function makeLoan(User $owner): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

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
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ]);
    }

    private function makeQuery(LoanDetail $loan, int $assignedTo, int $raisedBy, string $text): StageQuery
    {
        $assignment = StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $assignedTo,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
        ]);

        return StageQuery::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'query_text' => $text,
            'raised_by' => $raisedBy,
            'assigned_to_user_id' => $assignedTo,
            'status' => StageQuery::STATUS_PENDING,
        ]);
    }

    /** @return array<int,string> loan numbers present in the widget payload */
    private function openQueriesFor(User $user): array
    {
        $controller = app(DashboardController::class);
        $method = (new \ReflectionClass($controller))->getMethod('newthemeOpenQueries');
        $method->setAccessible(true);

        return array_column($method->invoke($controller, $user), 'loan');
    }

    public function test_user_sees_query_assigned_to_them_but_not_unrelated_ones(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $loanB = $this->makeLoan($userB);

        // Query on B's loan, assigned to A → A should see it (assigned), B should see it (owner).
        $this->makeQuery($loanB, assignedTo: $userA->id, raisedBy: $userB->id, text: 'Assigned to A');

        $this->assertContains($loanB->loan_number, $this->openQueriesFor($userA));
        $this->assertContains($loanB->loan_number, $this->openQueriesFor($userB));
    }

    public function test_user_does_not_see_queries_unrelated_to_them(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $loanB = $this->makeLoan($userB);

        // Query on B's loan, assigned to B → A is neither assignee nor owner.
        $this->makeQuery($loanB, assignedTo: $userB->id, raisedBy: $userB->id, text: 'B only');

        $this->assertNotContains($loanB->loan_number, $this->openQueriesFor($userA));
        $this->assertContains($loanB->loan_number, $this->openQueriesFor($userB));
    }

    public function test_admin_sees_all_open_queries(): void
    {
        $admin = $this->makeUser('super_admin');
        $userB = $this->makeUser();
        $loanB = $this->makeLoan($userB);

        $this->makeQuery($loanB, assignedTo: $userB->id, raisedBy: $userB->id, text: 'Anyone\'s query');

        $this->assertContains($loanB->loan_number, $this->openQueriesFor($admin));
    }
}
