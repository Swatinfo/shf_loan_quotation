<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the loan payout user for existing loans: any loan without a
 * payout_user_id gets its creator (created_by). New loans set it at
 * quotation→loan conversion, so this only touches the pre-feature backlog.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('loan_details')
            ->whereNull('payout_user_id')
            ->whereNotNull('created_by')
            ->update(['payout_user_id' => DB::raw('created_by')]);
    }

    public function down(): void
    {
        // One-time data backfill — not reversible (the prior NULLs aren't tracked).
    }
};
