<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payout config tab (loan settings): operator enters percentage `value`; the
 * `calc` multiplier is derived server-side as value/100 (never trusted from the
 * client) and shown read-only; `effective_from` is stored per rate.
 */
class PayoutConfigSettingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $u = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
    }

    public function test_save_derives_calc_server_side_and_stores_effective_from(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('loan-settings.payout-config.save'), [
                'payout' => [
                    'admin_gst' => ['value' => '18', 'effective_from' => '2026-04-01'],
                    'pf_gst' => ['value' => '18', 'effective_from' => null],
                    'user_tds' => ['value' => '5', 'effective_from' => '2026-05-01'],
                    'user_insurance' => ['value' => '2.5', 'effective_from' => null],
                    // A bogus client calc must be ignored — the server recomputes it.
                ],
            ])
            ->assertRedirect();

        $cfg = app(ConfigService::class)->get('payoutConfig');

        $this->assertSame(18.0, (float) $cfg['admin_gst']['value']);
        $this->assertSame(0.18, (float) $cfg['admin_gst']['calc']);       // 18/100
        $this->assertSame('2026-04-01', $cfg['admin_gst']['effective_from']);

        $this->assertSame(0.05, (float) $cfg['user_tds']['calc']);        // 5/100
        // Posted date (≥ the 2026-04-01 seed) becomes the current version.
        $this->assertSame('2026-05-01', $cfg['user_tds']['effective_from']);

        $this->assertSame(0.025, (float) $cfg['user_insurance']['calc']); // 2.5/100
        // A blank effective_from now resolves to today's version date (temporal history).
        $this->assertSame(now()->toDateString(), $cfg['user_insurance']['effective_from']);
    }

    public function test_value_over_100_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('loan-settings.payout-config.save'), [
                'payout' => [
                    'admin_gst' => ['value' => '150'],
                    'pf_gst' => ['value' => '18'],
                    'user_tds' => ['value' => '5'],
                    'user_insurance' => ['value' => '2'],
                ],
            ])
            ->assertSessionHasErrors('payout.admin_gst.value');
    }

    public function test_defaults_are_present_without_any_save(): void
    {
        $cfg = app(ConfigService::class)->get('payoutConfig');

        $this->assertSame(18, $cfg['admin_gst']['value']);
        $this->assertSame(0.18, $cfg['admin_gst']['calc']);
        $this->assertArrayHasKey('effective_from', $cfg['user_insurance']);
    }
}
