<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage stamp for a NORMAL (amount-based) product's one-time PF + admin charges,
 * which join the commission base once per loan: records the payout run that counted
 * them, so later runs (next tranches) never add them again. Plain nullable column
 * (no cross-table FK) to stay SQLite-ALTER friendly for tests, like its siblings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->unsignedBigInteger('charges_payout_run_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropColumn('charges_payout_run_id');
        });
    }
};
