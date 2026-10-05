<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loan listing (`loans.data`) defaults to ACTIVE loans only. on_hold / cancelled
 * / rejected / completed appear only when explicitly filtered (or status=all).
 */
class LoanListingActiveDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'A-'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
    }

    private function loan(User $owner, string $status): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'SHF-'.$status.'-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => $status, 'current_stage' => 'kfs',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $owner->id, 'assigned_advisor' => $owner->id,
        ]);
    }

    private function nums($response): string
    {
        return collect($response->json('data'))->pluck('loan_number_raw')->implode(' ');
    }

    public function test_default_shows_only_active(): void
    {
        $admin = $this->admin();
        $active = $this->loan($admin, 'active');
        $onHold = $this->loan($admin, 'on_hold');
        $cancelled = $this->loan($admin, 'cancelled');
        $rejected = $this->loan($admin, 'rejected');

        $response = $this->actingAs($admin)->getJson(route('loans.data'))->assertOk();
        $nums = $this->nums($response);

        $this->assertStringContainsString($active->loan_number, $nums);
        $this->assertStringNotContainsString($onHold->loan_number, $nums);
        $this->assertStringNotContainsString($cancelled->loan_number, $nums);
        $this->assertStringNotContainsString($rejected->loan_number, $nums);
    }

    public function test_explicit_status_filter_shows_that_status(): void
    {
        $admin = $this->admin();
        $active = $this->loan($admin, 'active');
        $onHold = $this->loan($admin, 'on_hold');

        $response = $this->actingAs($admin)->getJson(route('loans.data', ['status' => 'on_hold']))->assertOk();
        $nums = $this->nums($response);

        $this->assertStringContainsString($onHold->loan_number, $nums);
        $this->assertStringNotContainsString($active->loan_number, $nums);
    }

    public function test_status_all_shows_everything(): void
    {
        $admin = $this->admin();
        $active = $this->loan($admin, 'active');
        $cancelled = $this->loan($admin, 'cancelled');

        $response = $this->actingAs($admin)->getJson(route('loans.data', ['status' => 'all']))->assertOk();
        $nums = $this->nums($response);

        $this->assertStringContainsString($active->loan_number, $nums);
        $this->assertStringContainsString($cancelled->loan_number, $nums);
    }

    public function test_default_includes_partial_disbursed_as_in_flight(): void
    {
        $admin = $this->admin();
        $active = $this->loan($admin, 'active');
        $partial = $this->loan($admin, 'partial_disbursed');
        $completed = $this->loan($admin, 'completed');

        $nums = $this->nums($this->actingAs($admin)->getJson(route('loans.data'))->assertOk());

        $this->assertStringContainsString($active->loan_number, $nums);
        $this->assertStringContainsString($partial->loan_number, $nums);
        $this->assertStringNotContainsString($completed->loan_number, $nums);
    }

    public function test_active_filter_includes_partial_but_partial_filter_isolates(): void
    {
        $admin = $this->admin();
        $active = $this->loan($admin, 'active');
        $partial = $this->loan($admin, 'partial_disbursed');

        $activeNums = $this->nums($this->actingAs($admin)->getJson(route('loans.data', ['status' => 'active']))->assertOk());
        $this->assertStringContainsString($active->loan_number, $activeNums);
        $this->assertStringContainsString($partial->loan_number, $activeNums);

        $partialNums = $this->nums($this->actingAs($admin)->getJson(route('loans.data', ['status' => 'partial_disbursed']))->assertOk());
        $this->assertStringContainsString($partial->loan_number, $partialNums);
        $this->assertStringNotContainsString($active->loan_number, $partialNums);
    }
}
