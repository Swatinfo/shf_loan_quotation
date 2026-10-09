<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product payout config: connector slab columns + payout cycle days save and
 * validate alongside the existing standard slab config.
 */
class ProductPayoutConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionService::class)->clearAllCaches();
    }

    private function superAdmin(): User
    {
        $u = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
    }

    private function product(Bank $bank, string $name = 'Home Loan'): Product
    {
        return Product::create(['bank_id' => $bank->id, 'name' => $name, 'is_active' => true]);
    }

    public function test_product_store_creates_identity_without_payout(): void
    {
        $admin = $this->superAdmin();
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        // The product form no longer carries payout — identity only.
        $this->actingAs($admin)->post(route('loan-settings.products.store'), [
            'bank_id' => $bank->id,
            'name' => 'Home Loan',
            'code' => 'HL',
        ])->assertRedirect();

        $product = Product::where('name', 'Home Loan')->first();
        $this->assertNotNull($product);
        $this->assertFalse((bool) $product->is_pf_based);
        $this->assertNull($product->max_payout_amount);
    }

    public function test_saves_connector_slab_and_cycle_days(): void
    {
        $admin = $this->superAdmin();
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = $this->product($bank);

        $this->actingAs($admin)->post(route('loan-settings.payout-product.save'), [
            'product_id' => $product->id,
            'is_pf_based' => 1,
            'max_payout_amount' => 50000,
            'payout_cycle_start_day' => 16,
            'payout_cycle_end_day' => 15,
            'slabs' => [
                ['low_amount' => 0, 'high_amount' => 100000000, 'payout_type' => 'percent', 'payout_value' => 1.0,
                    'connector_payout_type' => 'percent', 'connector_payout_value' => 2.5],
            ],
        ])->assertRedirect();

        $product->refresh();
        $this->assertTrue((bool) $product->is_pf_based);
        $this->assertSame(16, $product->payout_cycle_start_day);
        $this->assertSame(15, $product->payout_cycle_end_day);

        $slab = $product->payoutSlabs()->first();
        $this->assertSame('percent', $slab->connector_payout_type);
        $this->assertSame(2.5, (float) $slab->connector_payout_value);
    }

    public function test_rejects_connector_percent_over_100(): void
    {
        $admin = $this->superAdmin();
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = $this->product($bank, 'Bad Loan');

        $this->actingAs($admin)->from(route('loan-settings.index'))->post(route('loan-settings.payout-product.save'), [
            'product_id' => $product->id,
            'slabs' => [
                ['low_amount' => 0, 'high_amount' => 100000000, 'payout_type' => 'percent', 'payout_value' => 1.0,
                    'connector_payout_type' => 'percent', 'connector_payout_value' => 150],
            ],
        ])->assertSessionHas('error');

        $this->assertSame(0, $product->payoutSlabs()->count());
    }
}
