<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payout net breakdown. `payout_amount` stays the gross commission (base×slab);
 * these columns record the rest of the net calc so the ledger shows the full
 * working and the figures are auditable after config rates change:
 *   net = commission + (insurance_base × insurance_rate) − (pf_base × pf_gst_rate) − TDS
 *   TDS = tds_rate × (commission + insurance_payout − gst)
 * Rates are snapshots of payoutConfig.*.calc at finalize time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('pf_base_amount')->default(0)->after('payout_amount');
            $table->unsignedBigInteger('gst_amount')->default(0)->after('pf_base_amount');
            $table->unsignedBigInteger('insurance_base_amount')->default(0)->after('gst_amount');
            $table->unsignedBigInteger('insurance_payout_amount')->default(0)->after('insurance_base_amount');
            $table->unsignedBigInteger('tds_amount')->default(0)->after('insurance_payout_amount');
            $table->unsignedBigInteger('net_payout_amount')->default(0)->after('tds_amount');
            $table->decimal('pf_gst_rate', 6, 4)->default(0)->after('net_payout_amount');
            $table->decimal('tds_rate', 6, 4)->default(0)->after('pf_gst_rate');
            $table->decimal('insurance_rate', 6, 4)->default(0)->after('tds_rate');
        });
    }

    public function down(): void
    {
        Schema::table('loan_payouts', function (Blueprint $table) {
            $table->dropColumn([
                'pf_base_amount', 'gst_amount', 'insurance_base_amount', 'insurance_payout_amount',
                'tds_amount', 'net_payout_amount', 'pf_gst_rate', 'tds_rate', 'insurance_rate',
            ]);
        });
    }
};
