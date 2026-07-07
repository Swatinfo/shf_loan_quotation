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
 * `product_id` filter on the loans listing data endpoint (loans.data),
 * wired to the Product select in the loan-list filter panel.
 */
class LoanProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin '.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeLoan(User $owner, array $attrs = []): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ], $attrs));
    }

    public function test_product_filter_narrows_listing_to_matching_loans(): void
    {
        $admin = $this->admin();
        $target = $this->makeLoan($admin);
        $other = $this->makeLoan($admin);

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', ['product_id' => $target->product_id]))
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertNotNull($rows->firstWhere('customer_name', 'Customer'));
        $this->assertStringContainsString($target->loan_number, $rows->pluck('loan_number')->implode(' '));
        $this->assertStringNotContainsString($other->loan_number, $rows->pluck('loan_number')->implode(' '));
    }

    public function test_without_product_filter_all_loans_are_returned(): void
    {
        $admin = $this->admin();
        $this->makeLoan($admin);
        $this->makeLoan($admin);

        $this->actingAs($admin)
            ->getJson(route('loans.data'))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2);
    }

    public function test_loans_index_renders_product_filter_options(): void
    {
        $admin = $this->admin();
        $bank = Bank::create(['name' => 'HDFC-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Gruh Suvidha', 'bank_id' => $bank->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('loans.index'))
            ->assertOk()
            ->assertSee('id="lxProduct"', false)
            ->assertSee('data-bank-id="'.$bank->id.'"', false)
            ->assertSee('Gruh Suvidha');
    }

    public function test_loan_with_null_product_is_excluded_when_filter_active(): void
    {
        $admin = $this->admin();
        $target = $this->makeLoan($admin);
        $this->makeLoan($admin, ['product_id' => null]);

        $this->actingAs($admin)
            ->getJson(route('loans.data', ['product_id' => $target->product_id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);
    }
}
