<?php

use App\Models\LoanDetail;
use App\Services\DisbursementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time STATUS-ONLY backfill for the new stored `partial_completed` status.
 *
 * Before this, "Partially Completed" was derived on the fly: a `partial_disbursed`
 * loan whose every tranche is OTC-settled but which is still below its target and
 * not declared fully disbursed. Those loans are moved to `partial_completed` so the
 * label doesn't regress. Stage assignments, entries and every other column are left
 * untouched — they switch to the new disbursement flow on their next save.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(DisbursementService::class);

        LoanDetail::query()
            ->where('status', LoanDetail::STATUS_PARTIAL_DISBURSED)
            ->with('disbursement')
            ->orderBy('id')
            ->chunkById(100, function ($loans) use ($service) {
                foreach ($loans as $loan) {
                    $disbursement = $loan->disbursement;
                    if (! $disbursement || $disbursement->completion_intent === 'full') {
                        continue;
                    }

                    $entries = $disbursement->entryRows()->get();
                    if ($entries->isEmpty() || ! $entries->every(fn ($e) => $e->isOtcSettled())) {
                        continue;
                    }

                    $gross = (int) $entries->sum('amount') + (int) $disbursement->pf_amount + (int) $disbursement->admin_charges;
                    if ($gross >= $service->disbursementTarget($loan)) {
                        continue;
                    }

                    // Query-builder update: status only, no model events / audit stamps.
                    DB::table('loan_details')->where('id', $loan->id)
                        ->update(['status' => LoanDetail::STATUS_PARTIAL_COMPLETED]);
                }
            });
    }

    public function down(): void
    {
        DB::table('loan_details')
            ->where('status', LoanDetail::STATUS_PARTIAL_COMPLETED)
            ->update(['status' => LoanDetail::STATUS_PARTIAL_DISBURSED]);
    }
};
