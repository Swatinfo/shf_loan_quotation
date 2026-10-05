<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    public function __construct(
        private LoanStageService $stageService,
    ) {}

    /**
     * Save disbursement tranches (each with its own per-entry OTC state), mirror
     * them into disbursement_entries, then let syncDisbursementState() resolve
     * the loan/stage status. Multiple tranches can be added over time; the loan
     * only completes once it is fully disbursed AND every entry is OTC-settled.
     *
     * @param  array{entries: array<int, array<string, mixed>>, notes?: string|null}  $data
     */
    public function processDisbursement(LoanDetail $loan, array $data): DisbursementDetail
    {
        return DB::transaction(function () use ($loan, $data) {
            $entries = array_values($data['entries']);
            $total = (int) array_sum(array_column($entries, 'amount'));
            $hasCheque = collect($entries)->contains(fn (array $entry) => $entry['method'] === DisbursementDetail::TYPE_CHEQUE);

            $disbursement = DisbursementDetail::updateOrCreate(
                ['loan_id' => $loan->id],
                [
                    'entries' => $entries,
                    'notes' => $data['notes'] ?? null,
                    // Derived legacy columns — kept in sync for older read sites.
                    'disbursement_type' => $hasCheque ? DisbursementDetail::TYPE_CHEQUE : DisbursementDetail::TYPE_FUND_TRANSFER,
                    'disbursement_date' => collect($entries)->pluck('disbursement_date')->filter()->max(),
                    'amount_disbursed' => $total,
                    'bank_account_number' => $entries[0]['loan_account_number'] ?? null,
                ],
            );

            // Mirror tranches (incl. per-entry OTC) into disbursement_entries,
            // then persist the assigned row_ids back into the json entries.
            $entries = $this->syncEntryRows($loan, $disbursement, $entries);
            $disbursement->update(['entries' => $entries]);

            // Mirror the disbursed amount to its dedicated loan column (queryable, used by listings).
            $loan->update(['disbursed_amount' => $total]);

            // Refresh the relationship so syncDisbursementState sees the latest rows.
            $loan->setRelation('disbursement', $disbursement);

            $this->syncDisbursementState($loan);

            ActivityLog::log('process_disbursement', $disbursement, [
                'loan_number' => $loan->loan_number,
                'type' => $disbursement->disbursement_type,
                'amount' => $total,
                'entry_count' => count($entries),
                'loan_status' => $loan->fresh()->status,
            ]);

            return $disbursement;
        });
    }

    /**
     * Record (or change) the OTC handover state of a single tranche, then
     * re-resolve the loan/stage status. Used by the OTC-stage quick actions
     * and any per-entry handover control.
     */
    public function recordEntryOtc(DisbursementEntry $entry, string $status, ?string $handoverDate = null, ?string $remarks = null): void
    {
        DB::transaction(function () use ($entry, $status, $handoverDate, $remarks) {
            $attrs = ['otc_status' => $status, 'otc_remarks' => $remarks];

            if ($status === DisbursementEntry::OTC_CLEARED) {
                $attrs['otc_handover_date'] = $handoverDate;
                $attrs['otc_cleared_by'] = $entry->otc_cleared_by ?? auth()->id();
                $attrs['otc_cleared_at'] = $entry->otc_cleared_at ?? now();
            } else {
                $attrs['otc_handover_date'] = null;
                $attrs['otc_cleared_by'] = null;
                $attrs['otc_cleared_at'] = null;
            }

            $entry->update($attrs);

            ActivityLog::log('record_entry_otc', $entry, [
                'loan_id' => $entry->loan_id,
                'entry_id' => $entry->id,
                'otc_status' => $status,
            ]);

            $loan = $entry->loan()->first();
            $loan->load('disbursement');
            $this->syncDisbursementState($loan);
        });
    }

    /**
     * Operator declares disbursement finished (possibly below target). The loan
     * still completes only once every entry is OTC-settled.
     */
    public function markFullyDisbursed(LoanDetail $loan): void
    {
        DB::transaction(function () use ($loan) {
            $disbursement = $loan->disbursement;

            if (! $disbursement || $disbursement->entryRows()->count() === 0) {
                throw new \RuntimeException('Cannot mark as fully disbursed — no disbursement entries saved.');
            }

            $disbursement->update(['completion_intent' => DisbursementDetail::INTENT_FULL]);
            $loan->setRelation('disbursement', $disbursement);

            ActivityLog::log('mark_fully_disbursed', $disbursement, [
                'loan_number' => $loan->loan_number,
                'amount' => $disbursement->entryTotal(),
                'target' => $this->disbursementTarget($loan),
            ]);

            $this->syncDisbursementState($loan);
        });
    }

    /**
     * Single authority for the disbursement/OTC lifecycle. Resolves the loan
     * status (active → partial_disbursed → completed) and completes the
     * disbursement / otc_clearance stages based on cumulative amount and
     * per-entry OTC settlement. Completed loans are terminal — never reopened.
     */
    public function syncDisbursementState(LoanDetail $loan): void
    {
        // Completed is terminal: never downgrade or reopen (protects historical loans).
        if ($loan->status === LoanDetail::STATUS_COMPLETED) {
            return;
        }

        $disbursement = $loan->disbursement;
        if (! $disbursement) {
            return;
        }

        $entries = $disbursement->entryRows()->get();
        $cumulative = (int) $entries->sum('amount');

        // Nothing (or no longer anything) disbursed → not in-flight.
        if ($cumulative === 0) {
            if ($loan->status === LoanDetail::STATUS_PARTIAL_DISBURSED) {
                $loan->update(['status' => LoanDetail::STATUS_ACTIVE]);
            }

            return;
        }

        $target = $this->disbursementTarget($loan);
        $moneyDone = $cumulative >= $target
            || $disbursement->completion_intent === DisbursementDetail::INTENT_FULL;
        $allSettled = $entries->every(fn (DisbursementEntry $e) => $e->isOtcSettled());

        // Once disbursement has started, the loan is in-flight. Don't override a
        // deliberate hold/close — only promote a plain active loan.
        if ($loan->status === LoanDetail::STATUS_ACTIVE) {
            $loan->update(['status' => LoanDetail::STATUS_PARTIAL_DISBURSED]);
        }

        // Money not yet complete → keep disbursement open, stay partial.
        if (! $moneyDone) {
            $this->ensureInProgress($loan, 'disbursement');
            $this->stageService->recalculateProgress($loan);

            return;
        }

        // Money complete → close the disbursement stage (this opens otc_clearance).
        $this->completeStageIfInProgress($loan, 'disbursement');
        $loan->load('stageAssignments');

        if (! $allSettled) {
            // Fully disbursed but handovers outstanding → stay partial on OTC.
            $this->ensureInProgress($loan, 'otc_clearance');
            $this->stageService->recalculateProgress($loan);

            return;
        }

        // Fully disbursed AND all entries settled → finish OTC + complete the loan.
        $this->ensureInProgress($loan, 'otc_clearance');
        $this->completeStageIfInProgress($loan, 'otc_clearance');

        $otc = $loan->stageAssignments()->where('stage_key', 'otc_clearance')->first();
        if ($otc && $otc->status === 'completed') {
            $loan->update([
                'status' => LoanDetail::STATUS_COMPLETED,
                'current_stage' => 'otc_clearance',
            ]);
            app(NotificationService::class)->notifyLoanCompleted($loan);
        }

        $this->stageService->recalculateProgress($loan->refresh());
    }

    /**
     * Amount at which the loan counts as fully disbursed (when completion_intent
     * is not "full"): sanctioned amount (column, then legacy docket/sanction
     * notes), falling back to loan amount.
     */
    public function disbursementTarget(LoanDetail $loan): int
    {
        if ($loan->sanctioned_amount) {
            return (int) $loan->sanctioned_amount;
        }

        foreach (['docket', 'sanction'] as $stageKey) {
            $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
            $notes = $assignment ? $assignment->getNotesData() : [];
            if (! empty($notes['sanctioned_amount'])) {
                return (int) $notes['sanctioned_amount'];
            }
        }

        return (int) $loan->loan_amount;
    }

    /**
     * Mirror the tranches into `disbursement_entries` including per-entry OTC:
     * posted row_id (owned by this disbursement) → update in place; no/foreign
     * row_id → create; live rows missing from the payload → soft delete.
     * Returns the entries with row_id filled in.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function syncEntryRows(LoanDetail $loan, DisbursementDetail $disbursement, array $entries): array
    {
        $existing = $disbursement->entryRows()->get()->keyBy('id');
        $isActive = ! in_array($loan->status, [
            LoanDetail::STATUS_CANCELLED, LoanDetail::STATUS_REJECTED, LoanDetail::STATUS_ON_HOLD,
        ]);
        $keptIds = [];

        foreach ($entries as $i => $entry) {
            $rowId = (int) ($entry['row_id'] ?? 0);
            $current = ($rowId && $existing->has($rowId)) ? $existing[$rowId] : null;

            $attrs = [
                'loan_id' => $loan->id,
                'disbursement_date' => $entry['disbursement_date'] ?? null,
                'method' => $entry['method'],
                'product_id' => $entry['product_id'] ?? null,
                'product_name' => $entry['product_name'] ?? null,
                'loan_account_number' => $entry['loan_account_number'] ?? null,
                'amount' => (int) $entry['amount'],
                'cheque_name' => $entry['cheque_name'] ?? null,
                'cheque_number' => $entry['cheque_number'] ?? null,
                'cheque_date' => $entry['cheque_date'] ?? null,
                'is_active' => $isActive,
            ] + $this->otcAttrs($entry, $current);

            if ($current) {
                $current->update($attrs);
            } else {
                $rowId = $disbursement->entryRows()->create($attrs)->id;
            }

            $entries[$i]['row_id'] = $rowId;
            $keptIds[] = $rowId;
        }

        // Entries removed from the form → soft delete their mirror rows.
        $disbursement->entryRows()->whereNotIn('id', $keptIds)->get()->each->delete();

        return array_values($entries);
    }

    /**
     * Build the per-entry OTC columns from the posted entry, preserving an
     * existing clearance timestamp/author when the status stays "cleared".
     * Defaults to pending when the payload carries no OTC status.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function otcAttrs(array $entry, ?DisbursementEntry $current): array
    {
        $status = $entry['otc_status'] ?? $current?->otc_status ?? DisbursementEntry::OTC_PENDING;

        if ($status === DisbursementEntry::OTC_CLEARED) {
            return [
                'otc_status' => DisbursementEntry::OTC_CLEARED,
                'otc_handover_date' => $entry['otc_handover_date'] ?? $current?->otc_handover_date?->toDateString(),
                'otc_cleared_by' => $current?->otc_cleared_by ?? auth()->id(),
                'otc_cleared_at' => $current?->otc_cleared_at ?? now(),
                'otc_remarks' => $entry['otc_remarks'] ?? $current?->otc_remarks,
            ];
        }

        return [
            'otc_status' => $status,
            'otc_handover_date' => null,
            'otc_cleared_by' => null,
            'otc_cleared_at' => null,
            'otc_remarks' => $entry['otc_remarks'] ?? $current?->otc_remarks,
        ];
    }

    /**
     * Complete a stage only when it is currently in_progress and not blocked by
     * unresolved queries. Routes through the stage service so transition rules,
     * progress and sequential advancement all run.
     */
    private function completeStageIfInProgress(LoanDetail $loan, string $stageKey): void
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        if ($assignment && $assignment->status === 'in_progress' && ! $assignment->hasPendingQueries()) {
            $this->stageService->updateStageStatus($loan, $stageKey, 'completed', auth()->id());
        }
    }

    /**
     * Lift a pending stage to in_progress (no-op if already in_progress/completed).
     */
    private function ensureInProgress(LoanDetail $loan, string $stageKey): void
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        if ($assignment && $assignment->status === 'pending') {
            $assignment->update(['status' => 'in_progress', 'started_at' => $assignment->started_at ?? now()]);
        }
    }
}
