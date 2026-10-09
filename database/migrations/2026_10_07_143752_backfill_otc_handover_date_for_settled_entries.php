<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Payout + reports now key on otc_handover_date (the settlement date). Existing
 * NEFT/skipped tranches predate this and have null transfer_date / handover,
 * which would drop them from every settled-date window. Backfill:
 *   - fund_transfer rows: transfer_date := disbursement_date (where null)
 *   - fund_transfer (NEFT): always settled on its transfer date — set
 *     otc_handover_date := COALESCE(transfer_date, disbursement_date) + status 'skipped'
 *     wherever the handover is still null (covers legacy pending/other NEFT rows)
 *   - any other skipped row: otc_handover_date := COALESCE(transfer_date, disbursement_date)
 * Cheque-cleared rows keep their handover date; cheque-pending stay null (correctly uncounted).
 */
return new class extends Migration
{
    public function up(): void
    {
        // No table alias — SQLite rejects aliased correlated UPDATEs.
        DB::statement("
            UPDATE disbursement_entries
               SET transfer_date = disbursement_date
             WHERE method = 'fund_transfer'
               AND transfer_date IS NULL
               AND disbursement_date IS NOT NULL
               AND deleted_at IS NULL
        ");

        // NEFT is always settled on its transfer date — normalize any NEFT row whose
        // handover is still null (legacy pending/other statuses) to skipped + handover.
        DB::statement("
            UPDATE disbursement_entries
               SET otc_handover_date = COALESCE(transfer_date, disbursement_date),
                   otc_status = 'skipped'
             WHERE method = 'fund_transfer'
               AND otc_handover_date IS NULL
               AND COALESCE(transfer_date, disbursement_date) IS NOT NULL
               AND deleted_at IS NULL
        ");

        DB::statement("
            UPDATE disbursement_entries
               SET otc_handover_date = COALESCE(transfer_date, disbursement_date)
             WHERE otc_status = 'skipped'
               AND otc_handover_date IS NULL
               AND COALESCE(transfer_date, disbursement_date) IS NOT NULL
               AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        // One-time data backfill — not reversible (prior nulls aren't tracked).
    }
};
