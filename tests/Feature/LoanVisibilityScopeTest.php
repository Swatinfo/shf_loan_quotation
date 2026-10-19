<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LoanDetail::scopeVisibleTo role-gating (2026-10-19):
 *  - BM/BDH see own + their branch, NOT loans they're merely stage-assigned to
 *    outside their branch.
 *  - Bank/office employees + advisors still see loans they're stage-assigned to.
 */
class LoanVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
    }

    private function user(string $roleSlug): User
    {
        $u = User::create(['name' => 'U-'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

        return $u->fresh('roles');
    }

    private function loan(Branch $branch, User $creator): LoanDetail
    {
        $bank = Bank::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'active', 'current_stage' => 'inquiry',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $creator->id, 'assigned_advisor' => $creator->id,
        ]);
    }

    public function test_bm_sees_own_and_branch_but_not_stage_assigned_outside_branch(): void
    {
        $branchA = Branch::create(['name' => 'A-'.uniqid(), 'is_active' => true]);
        $branchB = Branch::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $other = $this->user('loan_advisor');
        $bm = $this->user('branch_manager');
        $bm->branches()->attach($branchA->id);

        $inBranch = $this->loan($branchA, $other);          // visible: their branch
        $createdByBm = $this->loan($branchB, $bm);          // visible: own (created)
        $stageAssignedOutside = $this->loan($branchB, $other); // must NOT be visible
        StageAssignment::create([
            'loan_id' => $stageAssignedOutside->id, 'stage_key' => 'legal_verification',
            'assigned_to' => $bm->id, 'status' => 'in_progress', 'started_at' => now(),
        ]);

        $visible = LoanDetail::visibleTo($bm)->pluck('id')->all();

        $this->assertContains($inBranch->id, $visible);
        $this->assertContains($createdByBm->id, $visible);
        $this->assertNotContains($stageAssignedOutside->id, $visible);
    }

    public function test_bank_employee_still_sees_stage_assigned_loan(): void
    {
        $branch = Branch::create(['name' => 'Br-'.uniqid(), 'is_active' => true]);
        $other = $this->user('loan_advisor');
        $emp = $this->user('bank_employee');

        $loan = $this->loan($branch, $other);
        StageAssignment::create([
            'loan_id' => $loan->id, 'stage_key' => 'legal_verification',
            'assigned_to' => $emp->id, 'status' => 'in_progress', 'started_at' => now(),
        ]);

        $this->assertContains($loan->id, LoanDetail::visibleTo($emp)->pluck('id')->all());
    }
}
