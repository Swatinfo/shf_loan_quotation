<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    public function __construct(
        private LoanStageService $stageService,
    ) {}

    /**
     * Save the disbursement tranches. Legacy columns are derived from entries so
     * existing read sites (OTC-skip, timeline, listings) keep working. The stage
     * auto-completes only once the entry total reaches the disbursement target.
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

            // Mirror tranches into disbursement_entries (update/create/soft-delete),
            // then persist the assigned row_ids back into the json entries.
            $entries = $this->syncEntryRows($loan, $disbursement, $entries);
            $disbursement->update(['entries' => $entries]);

            // Mirror the disbursed amount to its dedicated loan column (queryable, used by listings).
            $loan->update(['disbursed_amount' => $total]);

            // Refresh the relationship so handleStageCompletion sees the latest row (OTC skip check)
            $loan->setRelation('disbursement', $disbursement);

            $completed = false;
            if ($total >= $this->disbursementTarget($loan)) {
                $completed = $this->completeStage($loan);
            }

            ActivityLog::log('process_disbursement', $disbursement, [
                'loan_number' => $loan->loan_number,
                'type' => $disbursement->disbursement_type,
                'amount' => $total,
                'entry_count' => count($entries),
                'stage_completed' => $completed,
            ]);

            $this->notifyIfLoanCompleted($loan);

            return $disbursement;
        });
    }

    /**
     * Explicitly complete the disbursement stage when the final total is
     * intentionally below the target (under-disbursement).
     */
    public function markFullyDisbursed(LoanDetail $loan): void
    {
        DB::transaction(function () use ($loan) {
            $disbursement = $loan->disbursement;

            if (! $disbursement || empty($disbursement->entryList())) {
                throw new \RuntimeException('Cannot mark as fully disbursed — no disbursement entries saved.');
            }

            $this->completeStage($loan);

            ActivityLog::log('mark_fully_disbursed', $disbursement, [
                'loan_number' => $loan->loan_number,
                'amount' => $disbursement->entryTotal(),
                'target' => $this->disbursementTarget($loan),
            ]);

            $this->notifyIfLoanCompleted($loan);
        });
    }

    /**
     * Amount at which the disbursement auto-completes: sanctioned amount
     * (column, then legacy docket/sanction notes), falling back to loan amount.
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
     * Mirror the tranches into `disbursement_entries`: posted row_id (owned by
     * this disbursement) → update in place; no/foreign row_id → create; live
     * rows missing from the payload → soft delete (HasAuditColumns stamps
     * deleted_by). Returns the entries with row_id filled in.
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
            ];

            $rowId = (int) ($entry['row_id'] ?? 0);
            if ($rowId && $existing->has($rowId)) {
                $existing[$rowId]->update($attrs);
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
     * Complete the disbursement stage if it is currently open.
     * handleStageCompletion routes to OTC (any cheque entry) or loan completion.
     */
    private function completeStage(LoanDetail $loan): bool
    {
        $assignment = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        if (! $assignment || $assignment->status !== 'in_progress') {
            return false;
        }

        $this->stageService->updateStageStatus($loan, 'disbursement', 'completed', auth()->id());

        return true;
    }

    private function notifyIfLoanCompleted(LoanDetail $loan): void
    {
        $loan->refresh();
        if ($loan->status === LoanDetail::STATUS_COMPLETED) {
            app(NotificationService::class)->notifyLoanCompleted($loan);
        }
    }
}
