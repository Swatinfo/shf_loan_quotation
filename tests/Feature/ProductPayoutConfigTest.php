<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Product;
use App\Models\ProductPayoutSlab;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product payout configuration: `is_pf_based` flag + optional `max_payout_amount`
 * cap on products, and payout slabs (low/high range + fixed-₹ or % payout) in the
 * dedicated `product_payout_slabs` table. Storage only — no payout calculation yet.
 */
class ProductPayoutConfigTest extends TestCase
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

    private function slab(int $low, int $high, string $type = 'amount', float $value = 5000): array
    {
        return [
            'low_amount' => $low,
            'high_amount' => $high,
            'payout_type' => $type,
            'payout_value' => $value,
        ];
    }

    public function test_store_product_with_payout_config_persists_slabs(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('loan-settings.products.store'), [
                'bank_id' => $bank->id,
                'name' => 'Home Loan',
                'is_pf_based' => '1',
                'max_payout_amount' => '50000.75',
                'slabs' => [
                    $this->slab(2500001, 5000000, 'percent', 0.5),
                    $this->slab(0, 1000000, 'amount', 2000),
                    $this->slab(1000001, 2500000, 'amount', 5000),
                ],
            ])
            ->assertSessionHas('success');

        $product = Product::where('name', 'Home Loan')->firstOrFail();
        $this->assertTrue($product->is_pf_based);
        // decimal:2 cast returns a string with exactly two decimal places.
        $this->assertSame('50000.75', $product->max_payout_amount);

        $slabs = $product->payoutSlabs;
        $this->assertCount(3, $slabs);
        // Relation orders by low_amount regardless of input order.
        $this->assertSame([0, 1000001, 2500001], $slabs->pluck('low_amount')->all());
        $this->assertSame('percent', $slabs->last()->payout_type);
        $this->assertSame(0.5, $slabs->last()->payout_value);
    }

    public function test_edit_replaces_slabs_and_updates_flags(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['bank_id' => $bank->id, 'name' => 'LAP', 'is_pf_based' => true, 'max_payout_amount' => 90000]);
        $product->payoutSlabs()->create($this->slab(0, 500000));
        $product->payoutSlabs()->create($this->slab(500001, 900000));

        $this->actingAs($this->admin())
            ->post(route('loan-settings.products.store'), [
                'id' => $product->id,
                'bank_id' => $bank->id,
                'name' => 'LAP Renamed',
                'max_payout_amount' => '',
                'slabs' => [$this->slab(0, 2000000, 'percent', 1.25)],
            ])
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('LAP Renamed', $product->name);
        $this->assertFalse($product->is_pf_based); // unchecked checkbox → false
        $this->assertNull($product->max_payout_amount);
        $this->assertCount(1, $product->payoutSlabs);
        $this->assertSame(2000000, $product->payoutSlabs->first()->high_amount);
        $this->assertSame(1, ProductPayoutSlab::count());
    }

    public function test_max_payout_amount_rejects_more_than_two_decimals(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin())
            ->from(route('loan-settings.index'))
            ->post(route('loan-settings.products.store'), [
                'bank_id' => $bank->id,
                'name' => 'Precise Product',
                'max_payout_amount' => '50000.759',
            ])
            ->assertSessionHasErrors('max_payout_amount');

        $this->assertDatabaseMissing('products', ['name' => 'Precise Product']);
    }

    public function test_overlapping_slab_ranges_are_rejected(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin())
            ->from(route('loan-settings.index'))
            ->post(route('loan-settings.products.store'), [
                'bank_id' => $bank->id,
                'name' => 'Overlap Product',
                'slabs' => [
                    $this->slab(0, 1000000),
                    $this->slab(900000, 2000000), // overlaps previous
                ],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('products', ['name' => 'Overlap Product']);
        $this->assertSame(0, ProductPayoutSlab::count());
    }

    public function test_high_range_must_exceed_low_range(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin())
            ->from(route('loan-settings.index'))
            ->post(route('loan-settings.products.store'), [
                'bank_id' => $bank->id,
                'name' => 'Bad Range',
                'slabs' => [$this->slab(500000, 500000)],
            ])
            ->assertSessionHasErrors('slabs.0.high_amount');

        $this->assertDatabaseMissing('products', ['name' => 'Bad Range']);
    }

    public function test_percentage_payout_above_100_is_rejected(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin())
            ->from(route('loan-settings.index'))
            ->post(route('loan-settings.products.store'), [
                'bank_id' => $bank->id,
                'name' => 'Pct Product',
                'slabs' => [$this->slab(0, 1000000, 'percent', 150)],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('products', ['name' => 'Pct Product']);
    }

    public function test_product_without_payout_config_still_saves(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('loan-settings.products.store'), [
                'bank_id' => $bank->id,
                'name' => 'Plain Product',
            ])
            ->assertSessionHas('success');

        $product = Product::where('name', 'Plain Product')->firstOrFail();
        $this->assertFalse($product->is_pf_based);
        $this->assertNull($product->max_payout_amount);
        $this->assertCount(0, $product->payoutSlabs);
    }

    public function test_product_listing_shows_min_and_max_slab_range(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['bank_id' => $bank->id, 'name' => 'Range Product']);
        $product->payoutSlabs()->create($this->slab(100000, 1000000));
        $product->payoutSlabs()->create($this->slab(1000001, 75000000));

        $this->actingAs($this->admin())
            ->get(route('loan-settings.index'))
            ->assertOk()
            // Lowest slab low_amount – highest slab high_amount, Indian format.
            ->assertSee("₹\u{00A0}1,00,000")
            ->assertSee("₹\u{00A0}7,50,00,000");
    }

    public function test_soft_deleting_product_keeps_slabs_for_restore(): void
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['bank_id' => $bank->id, 'name' => 'Deletable']);
        $product->payoutSlabs()->create($this->slab(0, 1000000));

        $this->actingAs($this->admin())
            ->deleteJson(route('loan-settings.products.destroy', $product))
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        // Soft delete → FK cascade does not fire; slabs stay for a potential restore.
        $this->assertSame(1, ProductPayoutSlab::where('product_id', $product->id)->count());
    }
}
