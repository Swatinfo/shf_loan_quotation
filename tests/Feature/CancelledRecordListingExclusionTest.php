<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\GeneralTask;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelled (and otherwise closed) loans/quotations must not surface in
 * "my work" listings: stage-assignment task lists, the open-queries widget,
 * and general tasks whose linked loan/quotation is cancelled.
 */
class CancelledRecordListingExclusionTest extends TestCase
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

    private function makeLoan(User $owner, string $status = 'active'): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => $status,
            'current_stage' => 'legal_verification',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ]);
    }

    private function makeAssignment(LoanDetail $loan, int $assignedTo): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $assignedTo,
            'status' => 'pending',
            'is_parallel_stage' => false,
        ]);
    }

    /** @return array<int,string> loan numbers in the My Loan Tasks payload */
    private function myLoanTasksFor(User $user): array
    {
        return array_column($this->invokePrivate('newthemeMyLoanTasks', $user), 'loanNumber');
    }

    /** @return array<int,string> loan numbers in the Open Queries payload */
    private function openQueriesFor(User $user): array
    {
        return array_column($this->invokePrivate('newthemeOpenQueries', $user), 'loan');
    }

    /** @return array<int,int> task ids in the Personal Tasks payload */
    private function personalTaskIdsFor(User $user): array
    {
        return array_column($this->invokePrivate('newthemePersonalTasks', $user), 'id');
    }

    private function invokePrivate(string $method, User $user): array
    {
        $controller = app(DashboardController::class);
        $ref = (new \ReflectionClass($controller))->getMethod($method);
        $ref->setAccessible(true);

        return $ref->invoke($controller, $user);
    }

    public function test_my_loan_tasks_excludes_cancelled_and_rejected_loans(): void
    {
        $user = $this->makeUser();
        $active = $this->makeLoan($user, 'active');
        $cancelled = $this->makeLoan($user, 'cancelled');
        $rejected = $this->makeLoan($user, 'rejected');
        $completed = $this->makeLoan($user, 'completed');

        foreach ([$active, $cancelled, $rejected, $completed] as $loan) {
            $this->makeAssignment($loan, $user->id);
        }

        $loanNumbers = $this->myLoanTasksFor($user);

        $this->assertContains($active->loan_number, $loanNumbers);
        $this->assertNotContains($cancelled->loan_number, $loanNumbers);
        $this->assertNotContains($rejected->loan_number, $loanNumbers);
        $this->assertNotContains($completed->loan_number, $loanNumbers);
    }

    public function test_open_queries_excludes_cancelled_loan(): void
    {
        $user = $this->makeUser();
        $cancelled = $this->makeLoan($user, 'cancelled');
        $assignment = $this->makeAssignment($cancelled, $user->id);

        StageQuery::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $cancelled->id,
            'stage_key' => 'legal_verification',
            'query_text' => 'On cancelled loan',
            'raised_by' => $user->id,
            'assigned_to_user_id' => $user->id,
            'status' => StageQuery::STATUS_PENDING,
        ]);

        $this->assertNotContains($cancelled->loan_number, $this->openQueriesFor($user));
    }

    public function test_personal_tasks_excludes_task_linked_to_cancelled_loan(): void
    {
        $user = $this->makeUser();
        $cancelledLoan = $this->makeLoan($user, 'cancelled');

        $linkedToCancelled = GeneralTask::create([
            'title' => 'Linked to cancelled loan',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'loan_detail_id' => $cancelledLoan->id,
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $unlinked = GeneralTask::create([
            'title' => 'Standalone task',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $ids = $this->personalTaskIdsFor($user);

        $this->assertContains($unlinked->id, $ids);
        $this->assertNotContains($linkedToCancelled->id, $ids);
    }

    public function test_general_task_scope_excludes_cancelled_quotation_link(): void
    {
        $user = $this->makeUser();

        $quotation = Quotation::create([
            'user_id' => $user->id,
            'customer_name' => 'Cust',
            'customer_type' => 'salaried',
            'loan_amount' => 500000,
            'status' => Quotation::STATUS_CANCELLED,
        ]);

        $linked = GeneralTask::create([
            'title' => 'Linked to cancelled quotation',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'quotation_id' => $quotation->id,
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $visibleIds = GeneralTask::visibleTo($user)->withActiveLinks()->pluck('id')->all();

        $this->assertNotContains($linked->id, $visibleIds);
    }
}
