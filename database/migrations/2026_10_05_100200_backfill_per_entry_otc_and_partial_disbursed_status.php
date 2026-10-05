<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-time back-compat backfill for the per-entry OTC + partial_disbursed model.
 * Validated against the imported live DB (2026-10-05):
 *
 *   completed / cheque (141)  → cheque entries=cleared (date/by/at from the
 *                               otc_clearance stage assignment), neft=skipped;
 *                               completion_intent=full. STATUS UNCHANGED.
 *   completed / neft (51)     → entries=skipped; completion_intent=full. UNCHANGED.
 *   active + disb completed (10)        → entries=pending; intent=full  → partial_disbursed
 *   active + disb in_progress w/entries → entries=pending; intent=open  → partial_disbursed
 *   everything else (on_hold / rejected / cancelled / pending-disb) → UNTOUCHED.
 *
 * Status flips ONLY for active loans that have actually started disbursing.
 * Completed loans never have their status or stage assignments changed — only
 * new columns are populated to reflect the handover that already happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('disbursement_details')->orderBy('id')->chunkById(100, function ($disbursements) {
            foreach ($disbursements as $d) {
                $loan = DB::table('loan_details')->where('id', $d->loan_id)->first(['id', 'status']);
                if (! $loan) {
                    continue;
                }

                $entries = DB::table('disbursement_entries')
                    ->where('disbursement_detail_id', $d->id)
                    ->whereNull('deleted_at')
                    ->get(['id', 'method']);

                if ($loan->status === 'completed') {
                    $this->backfillCompleted($d, $entries);

                    continue;
                }

                if ($loan->status === 'active') {
                    $this->backfillActive($d, $loan, $entries);
                }
                // on_hold / rejected / cancelled → untouched.
            }
        });
    }

    public function down(): void
    {
        // Irreversible data backfill — the column-drop migrations handle schema rollback.
    }

    /**
     * Completed loan: reflect the handover that already happened. Status untouched.
     */
    private function backfillCompleted(object $d, $entries): void
    {
        $otc = DB::table('stage_assignments')
            ->where('loan_id', $d->loan_id)
            ->where('stage_key', 'otc_clearance')
            ->first(['status', 'completed_by', 'completed_at', 'notes']);

        $handoverDate = null;
        if ($otc && $otc->notes) {
            $notes = json_decode($otc->notes, true);
            if (! empty($notes['handover_date'])) {
                try {
                    $handoverDate = Carbon::createFromFormat('d/m/Y', $notes['handover_date'])->toDateString();
                } catch (Throwable $e) {
                    $handoverDate = null;
                }
            }
        }

        foreach ($entries as $entry) {
            if ($entry->method === 'cheque') {
                DB::table('disbursement_entries')->where('id', $entry->id)->update([
                    'otc_status' => 'cleared',
                    'otc_handover_date' => $handoverDate,
                    'otc_cleared_by' => $otc->completed_by ?? null,
                    'otc_cleared_at' => $otc->completed_at ?? null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('disbursement_entries')->where('id', $entry->id)->update([
                    'otc_status' => 'skipped',
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('disbursement_details')->where('id', $d->id)->update(['completion_intent' => 'full']);
    }

    /**
     * Active loan that has started disbursing → partial_disbursed. Entries stay
     * pending so the per-entry handover can be recorded going forward.
     */
    private function backfillActive(object $d, object $loan, $entries): void
    {
        $disbStatus = DB::table('stage_assignments')
            ->where('loan_id', $d->loan_id)
            ->where('stage_key', 'disbursement')
            ->value('status');

        $disbComplete = $disbStatus === 'completed';

        // Only flip loans that have actually started disbursing.
        if (! $disbComplete && $entries->isEmpty()) {
            return;
        }

        DB::table('disbursement_entries')
            ->where('disbursement_detail_id', $d->id)
            ->whereNull('deleted_at')
            ->update(['otc_status' => 'pending', 'updated_at' => now()]);

        DB::table('disbursement_details')->where('id', $d->id)->update([
            'completion_intent' => $disbComplete ? 'full' : 'open',
        ]);

        DB::table('loan_details')->where('id', $loan->id)->update(['status' => 'partial_disbursed']);
    }
};
