<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin disbursement + OTC correction tool. Exports one row per active
 * (disbursed) tranche and re-imports the operator's real values, rewriting the
 * JSON + mirror rows + amounts + bank/product and re-resolving loan/stage state
 * through DisbursementService::processDisbursement — i.e. "update everywhere".
 */
class DisbursementDataService
{
    /**
     * Export/import column headers, in order. readAssoc() keys import rows by these
     * exact strings. 'Loan Advisor' + 'Payout User' are loan-level (written on each
     * loan's first row). 'Payout Run' is read-only (shows the finalized run # per
     * tranche) and ignored on import.
     */
    public const HEADERS = [
        'Loan ID', 'Entry ID', 'Loan Number', 'Application Number', 'Customer',
        'Loan Advisor', 'Payout User',
        'Bank', 'Product', 'Sanctioned Amount', 'Method', 'Amount',
        'PF Amount', 'Admin Charges', 'Insurance Amount', 'Cheque Name', 'Cheque No',
        'Disbursement Date', 'Transfer Date', 'OTC Clearance', 'OTC Clearance Date', 'Payout Run', 'Action',
    ];

    public function __construct(private DisbursementService $disbursementService) {}

    /**
     * Rows for the export: one per active disbursed tranche.
     *
     * @return array{headers: array<int,string>, rows: array<int,array<int,mixed>>, section_rows: array<int,int>, types: array<int,string>}
     */
    public function exportData(): array
    {
        $entries = DisbursementEntry::query()
            ->where('is_active', true)
            ->with(['loan.bank', 'loan.product', 'loan.advisor', 'loan.payoutUser', 'product'])
            ->orderBy('loan_id')->orderBy('id')
            ->get();

        // PF / Admin / Insurance are ONE-TIME (loan-level) — written on each loan's
        // FIRST tranche row only, blank on the rest. Entries are ordered by loan then id.
        // We also record each loan's first-row ordinal so the export can draw a
        // divider (top border + light fill) marking where a new loan begins.
        $seenLoan = [];
        $sectionRows = [];
        $rows = [];
        foreach ($entries->values() as $ordinal => $e) {
            $loan = $e->loan;
            $firstOfLoan = ! isset($seenLoan[$e->loan_id]);
            $seenLoan[$e->loan_id] = true;
            if ($firstOfLoan) {
                $sectionRows[] = $ordinal;
            }
            $header = $loan?->disbursement;

            $rows[] = [
                $e->loan_id,
                $e->id,
                $loan?->loan_number,
                $loan?->application_number,
                $loan?->customer_name,
                $firstOfLoan ? $loan?->advisor?->name : '',
                $firstOfLoan ? $loan?->payoutUser?->name : '',
                $loan?->bank?->name,
                $e->product_name ?: $loan?->product?->name,
                $loan?->sanctioned_amount,
                $this->methodToLabel($e->method),
                (int) $e->amount,
                $firstOfLoan ? (int) ($header?->pf_amount ?? 0) : '',
                $firstOfLoan ? (int) ($header?->admin_charges ?? 0) : '',
                $firstOfLoan ? (int) ($header?->insurance_amount ?? 0) : '',
                $e->cheque_name,
                $e->cheque_number,
                optional($e->disbursement_date)->format('d-m-Y'),
                optional($e->transfer_date)->format('d-m-Y'),
                $this->otcToLabel($e->otc_status),
                optional($e->otc_handover_date)->format('d-m-Y'),
                $e->payout_run_id ? '#'.$e->payout_run_id : '',
                'Keep',
            ];
        }

        return [
            'headers' => self::HEADERS,
            'rows' => $rows,
            'section_rows' => $sectionRows,
            // Dates stay as plain d-m-Y TEXT (not Excel date serials) so they
            // read back consistently as "07-10-2026" in every viewer and round-trip
            // cleanly on import (parseDate accepts d-m-Y). Excel never
            // auto-converts inline-string cells to date serials.
            'types' => [
                0 => XlsxExportService::TYPE_DECIMAL,  // Loan ID — real number, General format (no comma grouping)
                1 => XlsxExportService::TYPE_DECIMAL,  // Entry ID — real number, General format (no comma grouping)
                9 => XlsxExportService::TYPE_NUMBER,   // Sanctioned Amount
                11 => XlsxExportService::TYPE_NUMBER,  // Amount
                12 => XlsxExportService::TYPE_NUMBER,  // PF Amount
                13 => XlsxExportService::TYPE_NUMBER,  // Admin Charges
                14 => XlsxExportService::TYPE_NUMBER,  // Insurance Amount
            ],
        ];
    }

    /**
     * Validate + plan the import without writing (dry-run). Returns per-loan
     * change sets and row-level errors.
     *
     * @param  array<int,array<string,mixed>>  $excelRows
     * @return array{loans: array<int,array<string,mixed>>, errors: array<int,string>, rowCount: int}
     */
    public function preview(array $excelRows): array
    {
        return $this->plan($excelRows);
    }

    /**
     * Apply the import. Each loan is committed in its own transaction; a bad loan
     * is skipped and reported without blocking the rest.
     *
     * @param  array<int,array<string,mixed>>  $excelRows
     * @return array{updated:int, skippedLoans:int, errors:array<int,string>, statusChanges:array<int,string>}
     */
    public function apply(array $excelRows, User $actor): array
    {
        $plan = $this->plan($excelRows);
        $errors = $plan['errors'];
        $updated = 0;
        $skippedLoans = 0;
        $statusChanges = [];

        foreach ($plan['loans'] as $loanId => $change) {
            if (! empty($change['error'])) {
                $skippedLoans++;
                $errors[] = $change['error'];

                continue;
            }

            try {
                DB::transaction(function () use ($change, &$statusChanges, &$updated) {
                    /** @var LoanDetail $loan */
                    $loan = $change['loan'];
                    $before = $loan->status;

                    // Loan-level corrections first (bank / product / sanctioned).
                    $loanUpdates = [];
                    if ($change['bank_id'] !== null && $change['bank_id'] !== $loan->bank_id) {
                        $loanUpdates['bank_id'] = $change['bank_id'];
                    }
                    if ($change['product_id'] !== null && $change['product_id'] !== $loan->product_id) {
                        $loanUpdates['product_id'] = $change['product_id'];
                    }
                    if ($change['sanctioned_amount'] !== null) {
                        $loanUpdates['sanctioned_amount'] = $change['sanctioned_amount'];
                    }
                    if ($change['advisor_id'] !== $loan->assigned_advisor) {
                        $loanUpdates['assigned_advisor'] = $change['advisor_id'];
                    }
                    if ($change['payout_user_id'] !== $loan->payout_user_id) {
                        $loanUpdates['payout_user_id'] = $change['payout_user_id'];
                    }
                    if ($loanUpdates) {
                        $loan->update($loanUpdates);
                    }

                    // Rewrite entries + JSON + amounts + fully re-resolve status/stages
                    // ($allowReopen = true → can downgrade a wrongly-completed loan).
                    $this->disbursementService->processDisbursement(
                        $loan->fresh(),
                        [
                            'entries' => $change['entries'],
                            'notes' => $loan->disbursement?->notes,
                            'pf_amount' => $change['charges']['pf_amount'],
                            'admin_charges' => $change['charges']['admin_charges'],
                            'insurance_amount' => $change['charges']['insurance_amount'],
                        ],
                        allowReopen: true,
                    );

                    $after = $loan->fresh()->status;
                    if ($before !== $after) {
                        $statusChanges[] = "{$loan->loan_number}: {$before} → {$after}";
                    }
                    $updated++;
                });
            } catch (\Throwable $e) {
                $skippedLoans++;
                $errors[] = "Loan #{$loanId}: ".$e->getMessage();
            }
        }

        ActivityLog::log('import_disbursement_data', null, [
            'loans_updated' => $updated, 'loans_skipped' => $skippedLoans, 'errors' => count($errors),
        ]);

        return ['updated' => $updated, 'skippedLoans' => $skippedLoans, 'errors' => $errors, 'statusChanges' => $statusChanges];
    }

    /**
     * Shared parse + validate + build. Groups rows by loan and produces, per loan,
     * the full entries payload for processDisbursement plus loan-level corrections.
     *
     * @param  array<int,array<string,mixed>>  $excelRows
     * @return array{loans: array<int,array<string,mixed>>, errors: array<int,string>, rowCount: int}
     */
    private function plan(array $excelRows): array
    {
        $errors = [];
        $byLoan = [];
        $rowCount = 0;

        // Group raw rows by loan_id (keyed by entry_id).
        foreach ($excelRows as $i => $r) {
            $line = $i + 2; // human row number (header = row 1)
            $loanId = (int) ($r['Loan ID'] ?? 0);
            $entryId = (int) ($r['Entry ID'] ?? 0);
            if (! $loanId || ! $entryId) {
                $errors[] = "Row {$line}: missing Loan ID / Entry ID — skipped.";

                continue;
            }
            $rowCount++;
            $byLoan[$loanId]['rows'][$entryId] = $r + ['_line' => $line];
        }

        $loans = [];
        foreach ($byLoan as $loanId => $bucket) {
            $loans[$loanId] = $this->buildLoanChange($loanId, $bucket['rows'], $errors);
        }

        return ['loans' => $loans, 'errors' => $errors, 'rowCount' => $rowCount];
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows  keyed by entry_id
     * @param  array<int,string>  $errors  (by-ref) row-level errors
     * @return array<string,mixed>
     */
    private function buildLoanChange(int $loanId, array $rows, array &$errors): array
    {
        $loan = LoanDetail::with(['disbursement', 'advisor', 'payoutUser', 'disbursementEntries' => fn ($q) => $q->where('is_active', true)])->find($loanId);
        if (! $loan) {
            return ['error' => "Loan #{$loanId}: not found — skipped."];
        }

        $existing = $loan->disbursementEntries->keyBy('id');

        // Bank must be consistent across the loan's rows.
        $bankNames = collect($rows)->pluck('Bank')->map(fn ($b) => trim((string) $b))->filter()->unique();
        if ($bankNames->count() > 1) {
            return ['error' => "Loan #{$loanId} ({$loan->loan_number}): rows disagree on Bank — skipped."];
        }
        $bankId = $loan->bank_id;
        if ($bankNames->count() === 1) {
            $bank = Bank::whereRaw('LOWER(name) = ?', [strtolower($bankNames->first())])->first();
            if (! $bank) {
                return ['error' => "Loan #{$loanId}: bank \"{$bankNames->first()}\" not found — skipped."];
            }
            $bankId = $bank->id;
        }

        $sanctioned = collect($rows)->pluck('Sanctioned Amount')->filter(fn ($v) => $v !== '' && $v !== null)->first();
        $sanctioned = $sanctioned !== null ? (int) round((float) $this->num($sanctioned)) : null;

        // Loan-level user assignments (first non-blank across the loan's rows; blank = keep).
        $advisorId = $this->resolveLoanUser('Loan Advisor', $rows, $loan->assigned_advisor, $loan->loan_number, $errors);
        $payoutUserId = $this->resolveLoanUser('Payout User', $rows, $loan->payout_user_id, $loan->loan_number, $errors, User::PAYOUT_INELIGIBLE_ROLES);

        // One-time (loan-level) PF / Admin / Insurance — take the first non-blank value
        // across the loan's rows (export writes them on the first row); else keep the
        // existing header values. PF + admin are GST-inclusive; insurance has no GST.
        $header = $loan->disbursement;
        $charges = [
            'pf_amount' => (int) ($header?->pf_amount ?? 0),
            'admin_charges' => (int) ($header?->admin_charges ?? 0),
            'insurance_amount' => (int) ($header?->insurance_amount ?? 0),
        ];
        foreach (['PF Amount' => 'pf_amount', 'Admin Charges' => 'admin_charges', 'Insurance Amount' => 'insurance_amount'] as $col => $key) {
            foreach ($rows as $r) {
                if (($r[$col] ?? '') === '') {
                    continue;
                }
                $v = (int) round((float) $this->num($r[$col]));
                if ($v < 0) {
                    $errors[] = "Row {$r['_line']}: {$col} cannot be negative — kept existing.";
                    break;
                }
                $charges[$key] = $v;
                break;
            }
        }

        // Build the full entries payload — start from every active entry so none
        // are soft-deleted; apply edits to the ones present in the sheet. Capture a
        // field-level before/after diff per entry for the preview.
        $payload = [];
        $productIds = [];
        $previewEntries = [];
        foreach ($existing as $entryId => $entry) {
            $base = $this->entryToPayload($entry);
            $r = $rows[$entryId] ?? null;
            $action = $r ? strtolower(trim((string) ($r['Action'] ?? 'keep'))) : 'keep';

            // DELETE → drop from the payload so processDisbursement soft-deletes it.
            // Blocked when the tranche was already paid out — via a finalized payout
            // run (new system of record) or the legacy per-loan ledger — as deleting
            // it would orphan that payout's snapshot.
            if ($r && in_array($action, ['delete', 'remove', 'del'], true)) {
                if ($entry->payout_run_id || $entry->loan_payout_id) {
                    $errors[] = "Row {$r['_line']}: cannot delete entry {$entryId} of {$loan->loan_number} — it is already payout-finalized. Kept unchanged.";
                    $payload[] = $base;
                    $productIds[] = $base['product_id'];
                    $previewEntries[] = ['entry_id' => $entryId, 'action' => 'delete-blocked', 'fields' => $this->entryFieldDiffs($base, $base)];

                    continue;
                }
                $previewEntries[] = ['entry_id' => $entryId, 'action' => 'delete', 'fields' => $this->entryFieldDiffs($base, null)];

                continue; // excluded → soft-deleted on sync
            }

            if ($r) {
                $built = $this->applyRow($base, $r, $bankId, $loan, $errors);
                if ($built === null) {
                    // Row had a hard error (already recorded) — keep the entry unchanged.
                    $payload[] = $base;
                    $productIds[] = $base['product_id'];

                    continue;
                }
                $payload[] = $built['entry'];
                $productIds[] = $built['entry']['product_id'];
                $previewEntries[] = ['entry_id' => $entryId, 'action' => 'update', 'fields' => $this->entryFieldDiffs($base, $built['entry'])];
            } else {
                $payload[] = $base;
                $productIds[] = $base['product_id'];
            }
        }

        // Rows pointing at unknown / inactive entries for this loan.
        foreach ($rows as $entryId => $r) {
            if (! $existing->has($entryId)) {
                $errors[] = "Row {$r['_line']}: Entry ID {$entryId} is not an active tranche of loan {$loan->loan_number} — skipped.";
            }
        }

        // Loan product := the single product all entries resolved to (keeps payout consistent); else leave as-is.
        $distinctProducts = collect($productIds)->filter()->unique();
        $loanProductId = $distinctProducts->count() === 1 ? (int) $distinctProducts->first() : null;

        // Loan-level diffs + predicted status for the preview.
        $newBankName = Bank::find($bankId)?->name;
        $newProductName = $loanProductId ? Product::find($loanProductId)?->name : $loan->product?->name;
        $loanDiff = [
            'advisor' => $this->fieldDiff('Loan Advisor', $loan->advisor?->name, $advisorId ? User::find($advisorId)?->name : null),
            'payout_user' => $this->fieldDiff('Payout User', $loan->payoutUser?->name, $payoutUserId ? User::find($payoutUserId)?->name : null),
            'bank' => $this->fieldDiff('Bank', $loan->bank?->name, $newBankName),
            'product' => $this->fieldDiff('Product', $loan->product?->name, $newProductName),
            'sanctioned' => $this->fieldDiff('Sanctioned', $this->money($loan->sanctioned_amount), $this->money($sanctioned ?? $loan->sanctioned_amount)),
            'pf' => $this->fieldDiff('PF (incl GST)', $this->money($header?->pf_amount), $this->money($charges['pf_amount'])),
            'admin' => $this->fieldDiff('Admin (incl GST)', $this->money($header?->admin_charges), $this->money($charges['admin_charges'])),
            'insurance' => $this->fieldDiff('Insurance', $this->money($header?->insurance_amount), $this->money($charges['insurance_amount'])),
            'status' => $this->fieldDiff('Status', $loan->status, $this->predictStatus($payload, $charges, $sanctioned ?? (int) ($loan->sanctioned_amount ?: $loan->loan_amount))),
        ];

        return [
            'loan' => $loan,
            'entries' => $payload,
            'charges' => $charges,
            'bank_id' => $bankId,
            'product_id' => $loanProductId,
            'sanctioned_amount' => $sanctioned,
            'advisor_id' => $advisorId,
            'payout_user_id' => $payoutUserId,
            'preview_entries' => $previewEntries,
            'loan_diff' => $loanDiff,
        ];
    }

    /**
     * Resolve a loan-level user column (first non-blank value across the loan's
     * rows) to a user id by name. Blank → keep current. Not found / ambiguous /
     * holding an excluded role → records an error and keeps the current id.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,string>  $errors  (by-ref)
     * @param  array<int,string>  $excludeRoles  role slugs the resolved user may not hold
     */
    private function resolveLoanUser(string $col, array $rows, ?int $currentId, string $loanLabel, array &$errors, array $excludeRoles = []): ?int
    {
        $name = collect($rows)->pluck($col)->map(fn ($v) => trim((string) $v))->filter()->first();
        if ($name === null || $name === '') {
            return $currentId; // blank = keep
        }

        $matches = User::with('roles')->whereRaw('LOWER(name) = ?', [strtolower($name)])->get();
        if ($matches->isEmpty()) {
            $errors[] = "Loan {$loanLabel}: {$col} \"{$name}\" not found — kept existing.";

            return $currentId;
        }
        if ($matches->count() > 1) {
            $errors[] = "Loan {$loanLabel}: {$col} \"{$name}\" matches {$matches->count()} users — kept existing.";

            return $currentId;
        }

        $user = $matches->first();
        if ($excludeRoles && $user->roles->pluck('slug')->intersect($excludeRoles)->isNotEmpty()) {
            $errors[] = "Loan {$loanLabel}: {$col} \"{$name}\" is not eligible (super admin, admin, bank employee and office employee cannot be a payout user) — kept existing.";

            return $currentId;
        }

        return (int) $user->id;
    }

    /** @return array{label:string, old:string, new:string, changed:bool} */
    private function fieldDiff(string $label, mixed $old, mixed $new): array
    {
        $o = (string) ($old ?? '—');
        $n = (string) ($new ?? '—');

        return ['label' => $label, 'old' => $o === '' ? '—' : $o, 'new' => $n === '' ? '—' : $n, 'changed' => $o !== $n];
    }

    /**
     * Field-level before/after for one entry. $new === null means the tranche is being deleted.
     *
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>|null  $new
     * @return array<int,array{label:string, old:string, new:string, changed:bool}>
     */
    private function entryFieldDiffs(array $base, ?array $new): array
    {
        // PF / Admin / Insurance are loan-level now (shown in the loan diff), not per entry.
        $map = [
            'Method' => ['method', false], 'Amount' => ['amount', true],
            'Cheque Name' => ['cheque_name', false], 'Cheque No' => ['cheque_number', false],
            'Disb. Date' => ['disbursement_date', false], 'Transfer Date' => ['transfer_date', false],
            'OTC' => ['otc_status', false], 'OTC Date' => ['otc_handover_date', false],
        ];
        $fmt = function (string $key, bool $money, array $src) {
            $v = $src[$key] ?? null;
            if ($key === 'otc_status') {
                return $this->otcToLabel($v);
            }
            if ($key === 'method') {
                return $this->methodToLabel($v);
            }

            return $money ? $this->money($v) : ((string) ($v ?? '') === '' ? '—' : (string) $v);
        };

        $out = [];
        foreach ($map as $label => [$key, $money]) {
            $old = $fmt($key, $money, $base);
            $newv = $new === null ? '✕ removed' : $fmt($key, $money, $new);
            $out[] = ['label' => $label, 'old' => $old, 'new' => $newv, 'changed' => $new === null ? true : ($old !== $newv)];
        }

        return $out;
    }

    /**
     * Predicted loan status from the planned payload + one-time charges (display
     * only; the real resolve runs on apply).
     *
     * @param  array<int,array<string,mixed>>  $payload
     * @param  array{pf_amount:int, admin_charges:int, insurance_amount:int}  $charges
     */
    private function predictStatus(array $payload, array $charges, int $target): string
    {
        $net = (int) array_sum(array_column($payload, 'amount'));
        if ($net <= 0) {
            return 'active';
        }
        // One-time PF + admin add to the gross; insurance is excluded.
        $gross = $net + (int) $charges['pf_amount'] + (int) $charges['admin_charges'];
        $allSettled = collect($payload)->every(fn ($e) => in_array($e['otc_status'] ?? 'pending', ['cleared', 'skipped'], true));

        return ($gross >= $target && $allSettled) ? 'completed' : 'partial_disbursed';
    }

    private function money(mixed $v): string
    {
        return '₹ '.number_format((int) $v);
    }

    /**
     * Apply one sheet row's edits onto a base entry payload. Returns the new
     * entry + a human diff, or null when the row has a hard error.
     *
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $r
     * @param  array<int,string>  $errors  (by-ref)
     * @return array{entry: array<string,mixed>, diff: string}|null
     */
    private function applyRow(array $base, array $r, int $bankId, LoanDetail $loan, array &$errors): ?array
    {
        $line = $r['_line'];
        $entry = $base;

        // Method
        $method = strtolower(trim((string) ($r['Method'] ?? $base['method'])));
        $method = in_array($method, ['cheque'], true) ? 'cheque' : 'fund_transfer';
        $entry['method'] = $method;

        // Amount
        if (($r['Amount'] ?? '') !== '') {
            $amount = (int) round((float) $this->num($r['Amount']));
            if ($amount <= 0) {
                $errors[] = "Row {$line}: Amount must be greater than 0 — skipped.";

                return null;
            }
            $entry['amount'] = $amount;
        }

        // PF / Admin / Insurance are loan-level (handled once in buildLoanChange),
        // not per entry — the sheet's charge columns are read there.

        // Product (within the loan's resolved bank)
        $productName = trim((string) ($r['Product'] ?? ''));
        if ($productName !== '') {
            $product = Product::where('bank_id', $bankId)->whereRaw('LOWER(name) = ?', [strtolower($productName)])->first();
            if (! $product) {
                $errors[] = "Row {$line}: product \"{$productName}\" not found for the selected bank — skipped.";

                return null;
            }
            $entry['product_id'] = $product->id;
            $entry['product_name'] = $product->name;
        }

        // Cheque name + no (cheque only; cleared for NEFT).
        $entry['cheque_name'] = $method === 'cheque' ? (trim((string) ($r['Cheque Name'] ?? $base['cheque_name'])) ?: null) : null;
        $entry['cheque_number'] = $method === 'cheque' ? trim((string) ($r['Cheque No'] ?? $base['cheque_number'])) : null;
        if ($method === 'cheque' && ! $entry['cheque_number']) {
            $errors[] = "Row {$line}: Cheque No is required for cheque tranches — skipped.";

            return null;
        }

        // Disbursement date
        if (($r['Disbursement Date'] ?? '') !== '') {
            $d = $this->parseDate($r['Disbursement Date']);
            if (! $d) {
                $errors[] = "Row {$line}: invalid Disbursement Date — skipped.";

                return null;
            }
            $entry['disbursement_date'] = $d->toDateString();
        }

        if ($method === 'fund_transfer') {
            // NEFT: Transfer Date (defaults to the disbursement date) IS the settlement
            // date. OTC is always "skipped", and otc_handover_date = the transfer date.
            $td = null;
            if (($r['Transfer Date'] ?? '') !== '') {
                $td = $this->parseDate($r['Transfer Date']);
                if (! $td) {
                    $errors[] = "Row {$line}: invalid Transfer Date — skipped.";

                    return null;
                }
            }
            $entry['transfer_date'] = $td?->toDateString() ?? ($entry['disbursement_date'] ?? null);
            $entry['otc_status'] = 'skipped';
            $entry['otc_handover_date'] = $entry['transfer_date'];
        } else {
            // Cheque: no transfer date; OTC from the sheet (Yes → cleared + date).
            $entry['transfer_date'] = null;
            $otc = strtolower(trim((string) ($r['OTC Clearance'] ?? '')));
            if ($otc !== '') {
                if (in_array($otc, ['yes', 'y', 'cleared'], true)) {
                    $date = $this->parseDate($r['OTC Clearance Date'] ?? '');
                    if (! $date) {
                        $errors[] = "Row {$line}: OTC Clearance = Yes requires a valid OTC Clearance Date — skipped.";

                        return null;
                    }
                    $entry['otc_status'] = 'cleared';
                    $entry['otc_handover_date'] = $date->toDateString();
                } elseif (in_array($otc, ['skip', 'skipped', 'na', 'n/a'], true)) {
                    $entry['otc_status'] = 'skipped';
                    $entry['otc_handover_date'] = null;
                } else { // No / pending
                    $entry['otc_status'] = 'pending';
                    $entry['otc_handover_date'] = null;
                }
            }
        }

        $diff = sprintf('Entry %d: ₹%s %s, %s, OTC %s',
            $base['row_id'], number_format($entry['amount']), $this->methodToLabel($entry['method']),
            $entry['disbursement_date'] ?? '—', $this->otcToLabel($entry['otc_status']));

        return ['entry' => $entry, 'diff' => $diff];
    }

    /**
     * Full payload shape for one existing entry (preserves charges + cheque meta).
     *
     * @return array<string,mixed>
     */
    private function entryToPayload(DisbursementEntry $e): array
    {
        return [
            'row_id' => $e->id,
            'disbursement_date' => optional($e->disbursement_date)->toDateString(),
            'method' => $e->method,
            'product_id' => $e->product_id,
            'product_name' => $e->product_name,
            'loan_account_number' => $e->loan_account_number,
            'amount' => (int) $e->amount,
            'cheque_name' => $e->cheque_name,
            'cheque_number' => $e->cheque_number,
            'cheque_date' => optional($e->cheque_date)->toDateString(),
            'transfer_date' => optional($e->transfer_date)->toDateString(),
            'otc_status' => $e->otc_status,
            'otc_handover_date' => optional($e->otc_handover_date)->toDateString(),
        ];
    }

    private function otcToLabel(?string $status): string
    {
        return match ($status) {
            'cleared' => 'Yes',
            'skipped' => 'Skip',
            default => 'No',
        };
    }

    /** Human label for a disbursement method (NEFT / Cheque). */
    private function methodToLabel(?string $method): string
    {
        return $method === 'cheque' ? 'Cheque' : 'NEFT';
    }

    private function num(mixed $v): string
    {
        return str_replace([',', '₹', ' '], '', (string) $v);
    }

    private function parseDate(mixed $v): ?Carbon
    {
        if ($v === '' || $v === null) {
            return null;
        }
        // Excel serial number.
        if (is_numeric($v)) {
            $d = XlsxImportService::excelDate($v);

            return $d ? Carbon::instance($d->toDateTime()) : null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd/m/y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, trim((string) $v))->startOfDay();
            } catch (\Throwable $e) {
                // try next
            }
        }

        return null;
    }
}
