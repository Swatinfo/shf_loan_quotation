<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    /**
     * Per-loan re-entrancy guard for syncDisbursementState(). Keyed by loan id.
     *
     * @var array<int, bool>
     */
    private static array $syncing = [];

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
    public function processDisbursement(LoanDetail $loan, array $data, bool $allowReopen = false): DisbursementDetail
    {
        return DB::transaction(function () use ($loan, $data, $allowReopen) {
            $entries = array_values($data['entries']);
            $net = (int) array_sum(array_column($entries, 'amount'));
            // PF / Admin / Insurance are ONE-TIME per disbursement (header-level,
            // GST-inclusive) — taken from the form, not summed across tranches.
            $pf = (int) ($data['pf_amount'] ?? 0);
            $admin = (int) ($data['admin_charges'] ?? 0);
            $insurance = (int) ($data['insurance_amount'] ?? 0);

            // Charges attach to the FIRST disbursement only. Once any entry's OTC
            // is settled they are frozen: a normal re-save (adding later tranches)
            // keeps the stored values instead of whatever the form posted. The
            // super-admin correction tool ($allowReopen) bypasses this to correct them.
            $existing = $loan->disbursement;
            if (! $allowReopen && $existing && $existing->chargesLocked()) {
                $pf = (int) $existing->pf_amount;
                $admin = (int) $existing->admin_charges;
                $insurance = (int) $existing->insurance_amount;
            }

            // Overall ("gross") disbursed = net transfers + PF + admin (insurance excluded).
            $total = $net + $pf + $admin;
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
                    'pf_amount' => $pf,
                    'admin_charges' => $admin,
                    'insurance_amount' => $insurance,
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

            $this->syncDisbursementState($loan, $allowReopen);

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

            // Keep the json tranche (what the stage cards render) in step with the row.
            $disbursement = $entry->disbursement;
            if ($disbursement && is_array($disbursement->entries)) {
                $disbursement->update(['entries' => array_map(function (array $e) use ($entry, $attrs) {
                    if ((int) ($e['row_id'] ?? 0) === $entry->id) {
                        $e['otc_status'] = $attrs['otc_status'];
                        $e['otc_handover_date'] = $attrs['otc_handover_date'];
                        $e['otc_remarks'] = $attrs['otc_remarks'];
                    }

                    return $e;
                }, $disbursement->entries)]);
            }

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
     * Operator declares disbursement finished (possibly below target). The loan's
     * working `loan_amount` becomes the gross disbursed total (entries + PF +
     * admin); the as-applied figure stays on `original_loan_amount`. The loan
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

            $gross = $this->savedGross($disbursement);
            $oldAmount = (int) $loan->loan_amount;
            if ($gross !== $oldAmount) {
                $updates = ['loan_amount' => $gross];
                // Preserve the as-applied amount the first time it changes (legacy
                // loans created before original_loan_amount existed have a null column).
                if ($loan->original_loan_amount === null) {
                    $updates['original_loan_amount'] = $oldAmount;
                }
                $loan->update($updates);

                ActivityLog::log('change_loan_amount', $loan, [
                    'loan_number' => $loan->loan_number,
                    'source' => 'mark_fully_disbursed',
                    'old_amount' => $oldAmount,
                    'new_amount' => $gross,
                ]);
            }

            ActivityLog::log('mark_fully_disbursed', $disbursement, [
                'loan_number' => $loan->loan_number,
                'amount' => $gross,
                'target' => $this->disbursementTarget($loan),
            ]);

            $this->syncDisbursementState($loan);
        });
    }

    /**
     * Single authority for the disbursement/OTC lifecycle. Resolves the loan
     * status (active → partial_disbursed ⇄ partial_completed → completed) and the
     * disbursement / otc_clearance stages from the saved tranches. Completed loans
     * are terminal — never reopened (except by the correction tool).
     */
    public function syncDisbursementState(LoanDetail $loan, bool $allowReopen = false): void
    {
        // Re-entrancy guard (per loan): completing the disbursement stage now
        // re-invokes this resolver via LoanStageService::handleStageCompletion, and
        // the disbursement form path already calls it — without the guard the nested
        // call would run twice and could double-fire the "loan completed" notification.
        if (! empty(self::$syncing[$loan->id])) {
            return;
        }
        self::$syncing[$loan->id] = true;

        try {
            $this->resolveDisbursementState($loan, $allowReopen);
        } finally {
            unset(self::$syncing[$loan->id]);
        }
    }

    /**
     * The actual disbursement/OTC lifecycle resolution. Always reached through
     * syncDisbursementState() so the per-loan re-entrancy guard applies.
     *
     * Any saved money closes the disbursement stage — partial or full — and opens
     * otc_clearance. From there:
     *   - a cheque OTC pending        → partial_disbursed, otc_clearance in progress
     *                                   (reopened if a new cheque arrives later)
     *   - all settled, below target   → partial_completed (awaiting the next tranche)
     *   - all settled, target reached
     *     or declared full             → completed
     */
    private function resolveDisbursementState(LoanDetail $loan, bool $allowReopen = false): void
    {
        // Completed is terminal: never downgrade or reopen (protects historical loans)
        // UNLESS $allowReopen (the super-admin correction tool fully re-resolves).
        if (! $allowReopen && $loan->status === LoanDetail::STATUS_COMPLETED) {
            return;
        }

        $disbursement = $loan->disbursement;
        if (! $disbursement) {
            return;
        }

        $wasCompleted = $loan->status === LoanDetail::STATUS_COMPLETED;
        $entries = $disbursement->entryRows()->get();
        $cumulative = (int) $entries->sum('amount');

        // Nothing (or no longer anything) disbursed → not in-flight.
        if ($cumulative === 0) {
            if (in_array($loan->status, [...LoanDetail::PARTIAL_STATUSES, LoanDetail::STATUS_COMPLETED], true)) {
                $loan->update(['status' => LoanDetail::STATUS_ACTIVE]);
                if ($allowReopen) {
                    $this->reopenStage($loan, 'disbursement');
                    $this->resetStageToPending($loan, 'otc_clearance');
                }
            }
            $this->stageService->recalculateProgress($loan);

            return;
        }

        $target = $this->disbursementTarget($loan);
        // Overall ("gross") disbursed = net transfers + PF + admin charges — this is
        // what consumes the sanctioned amount. Insurance is excluded. Legacy rows
        // carry 0 for both charges, so their gross equals the old net total.
        $gross = $cumulative + (int) $disbursement->pf_amount + (int) $disbursement->admin_charges;
        $moneyDone = $gross >= $target
            || $disbursement->completion_intent === DisbursementDetail::INTENT_FULL;
        $allSettled = $entries->every(fn (DisbursementEntry $e) => $e->isOtcSettled());

        // Money has been disbursed → close the disbursement stage (partial or full).
        // Its completion opens otc_clearance via the normal sequential advance.
        $this->ensureInProgress($loan, 'disbursement');
        $this->completeStageIfInProgress($loan, 'disbursement');
        $loan->load('stageAssignments');

        // If the disbursement stage could not close (an open query is blocking it),
        // do NOT advance OTC or complete the loan — that would leave an inconsistent
        // state (loan completed while disbursement is still in_progress).
        $disbursementAssignment = $loan->stageAssignments->firstWhere('stage_key', 'disbursement');
        if ($disbursementAssignment && $disbursementAssignment->status !== 'completed') {
            $this->setStatus($loan, LoanDetail::STATUS_PARTIAL_DISBURSED);
            $this->stageService->recalculateProgress($loan);

            return;
        }

        if (! $allSettled) {
            // A cheque handover is outstanding → OTC (re)opens. A completed OTC stage
            // is reopened when a new pending cheque is added to a partial loan.
            $this->reopenOtcForPendingCheque($loan);
            $this->ensureInProgress($loan, 'otc_clearance');
            $this->setStatus($loan, LoanDetail::STATUS_PARTIAL_DISBURSED);
            $this->stageService->recalculateProgress($loan);

            return;
        }

        // Every tranche settled → the OTC stage is done for now.
        $this->ensureInProgress($loan, 'otc_clearance');
        $this->completeStageIfInProgress($loan, 'otc_clearance');

        $otc = $loan->stageAssignments()->where('stage_key', 'otc_clearance')->first();
        $otcDone = ! $otc || $otc->status === 'completed';

        if (! $moneyDone) {
            // Below target and not declared full → awaiting the next disbursement.
            $this->setStatus($loan, $otcDone ? LoanDetail::STATUS_PARTIAL_COMPLETED : LoanDetail::STATUS_PARTIAL_DISBURSED);
            $this->stageService->recalculateProgress($loan->refresh());

            return;
        }

        if (! $otcDone) {
            // OTC blocked (e.g. an open query) — stay in-flight until it closes.
            $this->setStatus($loan, LoanDetail::STATUS_PARTIAL_DISBURSED);
            $this->stageService->recalculateProgress($loan->refresh());

            return;
        }

        // Fully disbursed AND all entries settled → complete the loan.
        $loan->update([
            'status' => LoanDetail::STATUS_COMPLETED,
            'current_stage' => 'otc_clearance',
        ]);
        // Only fire the completion notification on a genuine transition.
        if (! $wasCompleted) {
            app(NotificationService::class)->notifyLoanCompleted($loan);
        }

        $this->stageService->recalculateProgress($loan->refresh());
    }

    /**
     * Gross disbursed from the saved tranche rows: Σ entries + PF + admin
     * (insurance excluded) — the same figure the resolver compares to the target.
     */
    public function savedGross(DisbursementDetail $disbursement): int
    {
        return (int) $disbursement->entryRows()->sum('amount')
            + (int) $disbursement->pf_amount
            + (int) $disbursement->admin_charges;
    }

    /**
     * Update the loan status only when it actually changes.
     */
    private function setStatus(LoanDetail $loan, string $status): void
    {
        if ($loan->status !== $status) {
            $loan->update(['status' => $status]);
        }
    }

    /**
     * A new pending cheque on a loan whose otc_clearance was already completed
     * (partial_completed) reopens the stage and re-notifies its assignee.
     */
    private function reopenOtcForPendingCheque(LoanDetail $loan): void
    {
        $otc = $loan->stageAssignments()->where('stage_key', 'otc_clearance')->first();
        if (! $otc || $otc->status !== 'completed') {
            return;
        }

        $this->reopenStage($loan, 'otc_clearance');

        if ($otc->assigned_to) {
            app(NotificationService::class)->notifyStageAssignment($loan, 'otc_clearance', (int) $otc->assigned_to);
        }
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
                'transfer_date' => $entry['transfer_date'] ?? null,
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
        // Only cheques require an over-the-counter handover. A fund transfer (NEFT/
        // RTGS) has no physical instrument, so its OTC is always "skipped" and never
        // blocks the otc_clearance stage / loan completion.
        if (($entry['method'] ?? null) !== DisbursementEntry::METHOD_CHEQUE) {
            // NEFT/RTGS: OTC is "skipped" (no physical instrument), but it still
            // carries a settlement date = its transfer date (falling back to the
            // disbursement date). Payout + reports key on otc_handover_date.
            return [
                'otc_status' => DisbursementEntry::OTC_SKIPPED,
                'otc_handover_date' => $entry['transfer_date'] ?? $entry['disbursement_date'] ?? null,
                'otc_cleared_by' => null,
                'otc_cleared_at' => null,
                'otc_remarks' => $entry['otc_remarks'] ?? null,
            ];
        }

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

    /**
     * Reopen a completed stage back to in_progress (correction tool, and
     * otc_clearance when a new pending cheque arrives). Clears its completion
     * stamps and points the loan's current_stage back here.
     */
    private function reopenStage(LoanDetail $loan, string $stageKey): void
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        if ($assignment && $assignment->status === 'completed') {
            $assignment->update([
                'status' => 'in_progress',
                'started_at' => $assignment->started_at ?? now(),
                'completed_at' => null,
                'completed_by' => null,
            ]);
            $loan->update(['current_stage' => $stageKey]);
        }
    }

    /**
     * Push a completed/in-progress stage back to pending (correction tool only) —
     * used for otc_clearance when the loan is no longer fully disbursed.
     */
    private function resetStageToPending(LoanDetail $loan, string $stageKey): void
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        if ($assignment && $assignment->status !== 'pending') {
            $assignment->update([
                'status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
                'completed_by' => null,
            ]);
        }
    }
}
