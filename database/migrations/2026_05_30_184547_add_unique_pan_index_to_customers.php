<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hard backstop for PAN uniqueness on the customer master.
 *
 * MUST run AFTER `customers:backfill-kyc` has deduped masters and any same-PAN
 * conflicts (different people sharing a PAN) have been resolved manually. The
 * guard below aborts with a clear message if active duplicates remain, so this
 * migration won't silently corrupt or half-apply.
 *
 * A partial unique index (SQLite/Postgres) is used so soft-deleted duplicates
 * and NULL PANs don't trip the constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::table('customers')
            ->whereNull('deleted_at')
            ->whereNotNull('pan_number')
            ->selectRaw('UPPER(TRIM(pan_number)) as p, COUNT(*) as c')
            ->groupBy('p')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('p')
            ->all();

        if ($dupes) {
            throw new RuntimeException(
                'Cannot add unique PAN index — active customers still share these PANs: '
                .implode(', ', $dupes)
                .'. Run `php artisan customers:backfill-kyc` and resolve the reported conflicts first.'
            );
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX customers_pan_number_unique ON customers (pan_number) WHERE deleted_at IS NULL AND pan_number IS NOT NULL');
        } else {
            // MySQL has no partial indexes; rely on app-level dedupe + this plain
            // unique (NULLs are allowed to repeat). Soft-deleted dupes must have
            // been hard-removed or PAN-nulled for this to apply on MySQL.
            Schema::table('customers', function ($table) {
                $table->unique('pan_number', 'customers_pan_number_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS customers_pan_number_unique');
        } else {
            Schema::table('customers', function ($table) {
                $table->dropUnique('customers_pan_number_unique');
            });
        }
    }
};
