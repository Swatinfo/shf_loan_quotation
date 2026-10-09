<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage stamps for the aggregate payout engine (idempotency):
 * - a disbursement tranche records which run paid its disbursed volume + the amount counted;
 * - a loan's one-time PF / insurance record which run paid them (counted once).
 * Plain nullable columns (no cross-table FK) to stay SQLite-ALTER friendly for tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('payout_run_id')->nullable()->after('loan_payout_id');
            $table->unsignedBigInteger('paid_amount_counted')->nullable()->after('payout_run_id');
            $table->index('payout_run_id');
        });

        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->unsignedBigInteger('pf_payout_run_id')->nullable();
            $table->unsignedBigInteger('insurance_payout_run_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->dropIndex(['payout_run_id']);
            $table->dropColumn(['payout_run_id', 'paid_amount_counted']);
        });

        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropColumn(['pf_payout_run_id', 'insurance_payout_run_id']);
        });
    }
};
