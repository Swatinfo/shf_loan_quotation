<?php

use App\Services\ConfigService;
use App\Services\PayoutConfigService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated history for the global payout rates (admin_gst, pf_gst,
 * user_tds, user_insurance). The rate in force for a date D is the row with the
 * greatest effective_from <= D (valid until the next version's date). The live
 * `payoutConfig` (app_config) is kept as the denormalized "current" mirror.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_rate_versions', function (Blueprint $table) {
            $table->id();
            $table->string('rate_key', 40);              // admin_gst | pf_gst | user_tds | user_insurance
            $table->decimal('value', 8, 2)->default(0);  // percent
            $table->decimal('calc', 8, 4)->default(0);   // value / 100
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rate_key', 'effective_from']);
            $table->index(['rate_key', 'effective_from']);
        });

        // Backfill one seed version per rate from the current payoutConfig.
        $cfg = [];
        try {
            $cfg = app(ConfigService::class)->get('payoutConfig', []);
        } catch (Throwable $e) {
            $cfg = config('app-defaults.payoutConfig', []);
        }

        foreach (['admin_gst', 'pf_gst', 'user_tds', 'user_insurance'] as $key) {
            $row = $cfg[$key] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $value = (float) ($row['value'] ?? 0);
            DB::table('payout_rate_versions')->insert([
                'rate_key' => $key,
                'value' => $value,
                'calc' => isset($row['calc']) ? (float) $row['calc'] : round($value / 100, 4),
                // Seed all payout config effective from 2026-04-01 (lookups before
                // this date fall back to the earliest version).
                'effective_from' => '2026-04-01',
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Sync the payoutConfig "current" mirror (app_config) to the seeded
        // versions so the UI's effective_from shows 2026-04-01 right after deploy.
        try {
            app(PayoutConfigService::class)->refreshRateMirror();
        } catch (Throwable $e) {
            // Non-fatal: the mirror also self-heals on the next Payout Config save.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_rate_versions');
    }
};
