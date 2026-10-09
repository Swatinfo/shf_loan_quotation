<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Processing fee (PF), admin charges and insurance captured per disbursement
 * tranche (not as separate cheque/NEFT entries). PF + admin are added to the
 * overall ("gross") disbursed amount and feed payout; insurance is recorded
 * but excluded from the disbursed total and from payout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('pf_amount')->default(0)->after('amount');
            $table->unsignedBigInteger('admin_charges')->default(0)->after('pf_amount');
            $table->unsignedBigInteger('insurance_amount')->default(0)->after('admin_charges');
        });

        // Denormalized per-loan totals (mirrors the existing amount_disbursed
        // pattern) for easy reporting without summing the entry rows.
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->unsignedBigInteger('pf_amount')->default(0)->after('amount_disbursed');
            $table->unsignedBigInteger('admin_charges')->default(0)->after('pf_amount');
            $table->unsignedBigInteger('insurance_amount')->default(0)->after('admin_charges');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->dropColumn(['pf_amount', 'admin_charges', 'insurance_amount']);
        });

        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropColumn(['pf_amount', 'admin_charges', 'insurance_amount']);
        });
    }
};
