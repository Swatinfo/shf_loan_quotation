<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The loan payout user (disbursement payout beneficiary): changeable only with
 * the change_payout_user permission, and only to a payout-eligible user —
 * holding none of super_admin / admin / bank_employee / office_employee.
 */
class LoanPayoutUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->clearAllCaches();
    }

    private function user(string $role): User
    {
        $u = User::create([
            'name' => ucfirst($role).'-'.uniqid(), 'email' => uniqid().'@t',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));
        app(PermissionService::class)->clearAllCaches();

        return $u->fresh('roles');
    }

    private function makeLoan(User $creator): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'active', 'current_stage' => 'kfs',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $creator->id, 'assigned_advisor' => $creator->id,
        ]);
    }

    public function test_admin_role_is_granted_change_payout_user(): void
    {
        // The migration grants change_payout_user to admin / branch_manager / bdh.
        $this->assertTrue($this->user('admin')->hasPermission('change_payout_user'));
        $this->assertTrue($this->user('branch_manager')->hasPermission('change_payout_user'));
        $this->assertFalse($this->user('loan_advisor')->hasPermission('change_payout_user'));
        $this->assertFalse($this->user('bank_employee')->hasPermission('change_payout_user'));
    }

    public function test_requires_change_payout_user_permission(): void
    {
        $advisor = $this->user('loan_advisor'); // not granted change_payout_user
        $loan = $this->makeLoan($advisor);

        $this->actingAs($advisor)
            ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => $advisor->id])
            ->assertForbidden();
    }

    public function test_can_set_and_clear_payout_user(): void
    {
        $admin = $this->user('super_admin');
        $target = $this->user('loan_advisor');
        $loan = $this->makeLoan($admin);

        $this->actingAs($admin)
            ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => $target->id])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame($target->id, $loan->fresh()->payout_user_id);

        $this->actingAs($admin)
            ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => null])
            ->assertOk();
        $this->assertNull($loan->fresh()->payout_user_id);
    }

    public function test_cannot_set_bank_or_office_employee_as_payout_user(): void
    {
        $admin = $this->user('super_admin');
        $loan = $this->makeLoan($admin);

        foreach (['bank_employee', 'office_employee'] as $role) {
            $this->actingAs($admin)
                ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => $this->user($role)->id])
                ->assertStatus(422);
            $this->assertNull($loan->fresh()->payout_user_id);
        }
    }

    public function test_cannot_set_super_admin_or_admin_as_payout_user(): void
    {
        $admin = $this->user('super_admin');
        $loan = $this->makeLoan($admin);

        foreach (['super_admin', 'admin'] as $role) {
            $this->actingAs($admin)
                ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => $this->user($role)->id])
                ->assertStatus(422);
            $this->assertNull($loan->fresh()->payout_user_id);
        }
    }

    public function test_user_holding_an_excluded_role_is_rejected_even_with_other_roles(): void
    {
        // A user who is BOTH loan_advisor and bank_employee must still be rejected.
        $admin = $this->user('super_admin');
        $loan = $this->makeLoan($admin);
        $mixed = $this->user('loan_advisor');
        $mixed->roles()->syncWithoutDetaching(Role::where('slug', 'bank_employee')->pluck('id'));
        app(PermissionService::class)->clearAllCaches();

        $this->actingAs($admin)
            ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => $mixed->id])
            ->assertStatus(422);
        $this->assertNull($loan->fresh()->payout_user_id);
    }

    public function test_connector_is_eligible_as_payout_user(): void
    {
        $admin = $this->user('super_admin');
        $connector = $this->user('connector');
        $loan = $this->makeLoan($admin);

        $this->actingAs($admin)
            ->postJson(route('loans.payout-user.update', $loan), ['payout_user_id' => $connector->id])
            ->assertOk();
        $this->assertSame($connector->id, $loan->fresh()->payout_user_id);
    }
}
