<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * On MySQL the earlier PAN unique index (`customers_pan_number_unique`) is a
 * PLAIN unique that also covers soft-deleted rows — so a returning customer
 * whose master was ever soft-deleted hits a duplicate-key 500 on loan/customer
 * creation. SQLite/Postgres already use a partial index (deleted_at IS NULL),
 * so this only needs fixing on MySQL.
 *
 * MySQL has no partial indexes, so we emulate one with a STORED generated
 * column that is the PAN only while the row is live (NULL once soft-deleted).
 * MySQL allows repeated NULLs in a unique index, so soft-deleted duplicates no
 * longer collide while active PANs stay unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Drop the plain unique that also caught soft-deleted rows.
        Schema::table('customers', function ($table) {
            $table->dropUnique('customers_pan_number_unique');
        });

        DB::statement(
            'ALTER TABLE customers ADD COLUMN pan_active VARCHAR(20) '
            .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN UPPER(TRIM(pan_number)) ELSE NULL END) STORED'
        );
        DB::statement('CREATE UNIQUE INDEX customers_pan_active_unique ON customers (pan_active)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP INDEX customers_pan_active_unique ON customers');
        DB::statement('ALTER TABLE customers DROP COLUMN pan_active');

        Schema::table('customers', function ($table) {
            $table->unique('pan_number', 'customers_pan_number_unique');
        });
    }
};
