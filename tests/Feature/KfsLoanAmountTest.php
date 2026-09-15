<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editable loan amount at the KFS stage. The KFS owner (or admin) can change the
 * working `loan_amount`; `original_loan_amount` preserves the as-applied figure.
 */
class KfsLoanAmountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $advisor = Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);

        // The stage routes are gated by permission:manage_loan_stages.
        $perm = Permission::firstOrCreate(
            ['slug' => 'manage_loan_stages'],
            ['name' => 'Manage Loan Stages', 'group' => 'Loans']
        );
        $advisor->permissions()->syncWithoutDetaching([$perm->id]);
    }

    private function user(string $role): User
    {
        $u = User::create([
            'name' => 'U-'.uniqid(),
            'email' => uniqid().'@test.local',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    /**
     * Loan with a KFS assignment. $creator owns/advises the loan; $kfsOwner is the
     * KFS assignee.
     */
    private function makeLoan(User $creator, User $kfsOwner, array $attrs = [], string $kfsStatus = 'in_progress'): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'original_loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'kfs',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $creator->id,
            'assigned_advisor' => $creator->id,
        ], $attrs));

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'kfs',
            'status' => $kfsStatus,
            'assigned_to' => $kfsOwner->id,
            'started_at' => now(),
        ]);

        return $loan->fresh('stageAssignments');
    }

    public function test_kfs_owner_can_edit_amount_and_original_is_preserved(): void
    {
        $creator = $this->user('loan_advisor');
        $kfsOwner = $this->user('loan_advisor');
        $loan = $this->makeLoan($creator, $kfsOwner);

        $this->actingAs($kfsOwner)
            ->postJson(route('loans.kfs.amount.update', $loan), ['loan_amount' => 1250000])
            ->assertOk()
            ->assertJsonPath('success', true);

        $loan->refresh();
        $this->assertSame(1250000, $loan->loan_amount);
        $this->assertSame(1000000, $loan->original_loan_amount); // preserved
    }

    public function test_admin_can_edit_amount(): void
    {
        $creator = $this->user('loan_advisor');
        $kfsOwner = $this->user('loan_advisor');
        $loan = $this->makeLoan($creator, $kfsOwner);

        $this->actingAs($this->user('super_admin'))
            ->postJson(route('loans.kfs.amount.update', $loan), ['loan_amount' => 900000])
            ->assertOk();

        $this->assertSame(900000, $loan->fresh()->loan_amount);
    }

    public function test_non_kfs_owner_cannot_edit_even_as_loan_creator(): void
    {
        $creator = $this->user('loan_advisor'); // passes authorizeView (created_by) but not the KFS owner
        $kfsOwner = $this->user('loan_advisor');
        $loan = $this->makeLoan($creator, $kfsOwner);

        $this->actingAs($creator)
            ->postJson(route('loans.kfs.amount.update', $loan), ['loan_amount' => 1250000])
            ->assertForbidden();

        $this->assertSame(1000000, $loan->fresh()->loan_amount);
    }

    public function test_cannot_edit_when_kfs_not_in_progress(): void
    {
        $creator = $this->user('loan_advisor');
        $kfsOwner = $this->user('loan_advisor');
        $loan = $this->makeLoan($creator, $kfsOwner, [], kfsStatus: 'completed');

        $this->actingAs($kfsOwner)
            ->postJson(route('loans.kfs.amount.update', $loan), ['loan_amount' => 1250000])
            ->assertStatus(422);
    }

    public function test_cannot_edit_when_loan_not_active(): void
    {
        $creator = $this->user('loan_advisor');
        $kfsOwner = $this->user('loan_advisor');
        $loan = $this->makeLoan($creator, $kfsOwner, ['status' => 'rejected']);

        $this->actingAs($kfsOwner)
            ->postJson(route('loans.kfs.amount.update', $loan), ['loan_amount' => 1250000])
            ->assertStatus(422);
    }

    public function test_kfs_completion_blocked_when_loan_amount_invalid(): void
    {
        $creator = $this->user('loan_advisor');
        $kfsOwner = $this->user('loan_advisor');
        // loan_amount 0 is a valid DB value but not a valid loan amount → completion must block.
        $loan = $this->makeLoan($creator, $kfsOwner, ['loan_amount' => 0]);

        $this->actingAs($kfsOwner)
            ->postJson(route('loans.stages.status', [$loan, 'kfs']), ['status' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('in_progress', $loan->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->status);
    }

    public function test_legacy_null_original_is_backfilled_on_first_edit(): void
    {
        $creator = $this->user('loan_advisor');
        $kfsOwner = $this->user('loan_advisor');
        $loan = $this->makeLoan($creator, $kfsOwner, ['original_loan_amount' => null]);

        $this->actingAs($kfsOwner)
            ->postJson(route('loans.kfs.amount.update', $loan), ['loan_amount' => 1250000])
            ->assertOk();

        $loan->refresh();
        $this->assertSame(1250000, $loan->loan_amount);
        $this->assertSame(1000000, $loan->original_loan_amount); // backfilled from the old value
    }
}
