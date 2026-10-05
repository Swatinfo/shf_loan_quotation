<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Services\DisbursementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoanDisbursementController extends Controller
{
    /** Loan statuses where disbursement entries / OTC can still be edited. */
    private const EDITABLE_STATUSES = [
        LoanDetail::STATUS_ACTIVE,
        LoanDetail::STATUS_PARTIAL_DISBURSED,
        LoanDetail::STATUS_ON_HOLD,
    ];

    public function show(LoanDetail $loan)
    {
        $disbursement = $loan->disbursement;
        $service = app(DisbursementService::class);

        // Sanctioned amount is captured at docket login; fall back to sanction notes for legacy loans.
        $docketAssignment = $loan->stageAssignments()->where('stage_key', 'docket')->first();
        $docketNotes = $docketAssignment ? $docketAssignment->getNotesData() : [];
        $sanctionAssignment = $loan->stageAssignments()->where('stage_key', 'sanction')->first();
        $sanctionNotes = $sanctionAssignment ? $sanctionAssignment->getNotesData() : [];
        $sanctionedAmount = $loan->sanctioned_amount ?? $docketNotes['sanctioned_amount'] ?? $sanctionNotes['sanctioned_amount'] ?? null;

        $stageAssignment = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        $stageCompleted = $stageAssignment?->status === 'completed';
        $isLocked = ! in_array($loan->status, self::EDITABLE_STATUSES);

        // Merge per-entry OTC state (held on the mirror rows) into the json entries.
        $otcByRow = $disbursement
            ? $disbursement->entryRows()->get()->keyBy('id')
            : collect();
        $entries = array_map(function (array $entry) use ($otcByRow) {
            $row = $otcByRow->get($entry['row_id'] ?? null);
            $entry['otc_status'] = $row->otc_status ?? DisbursementEntry::OTC_PENDING;
            $entry['otc_handover_date'] = $row && $row->otc_handover_date ? $row->otc_handover_date->format('d/m/Y') : null;
            $entry['otc_remarks'] = $row->otc_remarks ?? null;

            return $entry;
        }, $disbursement?->entryList() ?? []);

        $disbursedSoFar = $disbursement?->entryTotal() ?? 0;
        $target = $service->disbursementTarget($loan);
        $products = $this->bankProducts($loan);

        return view('newtheme.loans.disbursement', compact(
            'loan', 'disbursement', 'sanctionedAmount', 'isLocked', 'stageCompleted',
            'entries', 'disbursedSoFar', 'target', 'products',
        ) + ['pageKey' => 'loans']);
    }

    public function store(Request $request, LoanDetail $loan)
    {
        if (! in_array($loan->status, self::EDITABLE_STATUSES)) {
            return redirect()->route('loans.stages', $loan)->with('error', 'Loan is '.ucfirst(str_replace('_', ' ', $loan->status)).'. Changes are not allowed.');
        }

        $products = $this->bankProducts($loan);
        $productNames = $products->pluck('name', 'id');

        $validated = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.row_id' => 'nullable|integer',
            'entries.*.disbursement_date' => 'required|date_format:d/m/Y',
            'entries.*.method' => 'required|in:fund_transfer,cheque',
            'entries.*.product_id' => 'required|integer|in:'.$productNames->keys()->implode(','),
            'entries.*.loan_account_number' => 'required|string|max:50',
            'entries.*.amount' => 'required|numeric|min:1|max:100000000000',
            'entries.*.cheque_name' => 'nullable|string|max:100',
            'entries.*.cheque_number' => 'nullable|string|max:50',
            'entries.*.cheque_date' => 'nullable|string|max:20',
            'entries.*.otc_status' => 'nullable|in:pending,cleared,skipped',
            'entries.*.otc_handover_date' => 'nullable|date_format:d/m/Y',
            'entries.*.otc_remarks' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
        ], [
            'entries.*.product_id.in' => 'The selected product does not belong to this loan\'s bank.',
        ]);

        // Cheque entries require the instrument fields; cleared OTC needs a date.
        // Snapshot product name + normalize dates to storage format.
        $rowErrors = [];
        foreach ($validated['entries'] as $i => $entry) {
            if ($entry['method'] === DisbursementDetail::TYPE_CHEQUE) {
                foreach (['cheque_name', 'cheque_number', 'cheque_date'] as $field) {
                    if (empty($entry[$field])) {
                        $rowErrors["entries.{$i}.{$field}"] = 'This field is required for cheque entries.';
                    }
                }
            }
            if (($entry['otc_status'] ?? null) === DisbursementEntry::OTC_CLEARED && empty($entry['otc_handover_date'])) {
                $rowErrors["entries.{$i}.otc_handover_date"] = 'Handover date is required when OTC is marked cleared.';
            }

            $validated['entries'][$i]['disbursement_date'] = Carbon::createFromFormat('d/m/Y', $entry['disbursement_date'])->toDateString();
            $validated['entries'][$i]['product_id'] = (int) $entry['product_id'];
            $validated['entries'][$i]['product_name'] = $productNames[(int) $entry['product_id']];
            $validated['entries'][$i]['amount'] = (int) $entry['amount'];
            $validated['entries'][$i]['otc_status'] = $entry['otc_status'] ?? DisbursementEntry::OTC_PENDING;
            $validated['entries'][$i]['otc_handover_date'] = ! empty($entry['otc_handover_date'])
                ? Carbon::createFromFormat('d/m/Y', $entry['otc_handover_date'])->toDateString()
                : null;
        }
        if ($rowErrors) {
            throw ValidationException::withMessages($rowErrors);
        }

        $disbursement = app(DisbursementService::class)->processDisbursement($loan, $validated);
        $loan->refresh();

        if ($loan->status === LoanDetail::STATUS_COMPLETED) {
            return redirect()->route('loans.show', $loan)->with('success', 'Loan fully disbursed and completed!');
        }

        $service = app(DisbursementService::class);
        $remaining = max(0, $service->disbursementTarget($loan) - $disbursement->entryTotal());
        $pendingOtc = $disbursement->entryRows()->whereNotIn('otc_status', DisbursementEntry::OTC_SETTLED)->count();

        $msg = $remaining > 0
            ? 'Disbursement entries saved — remaining ₹ '.number_format($remaining).'.'
            : 'Fully disbursed — '.$pendingOtc.' '.str('entry')->plural($pendingOtc).' awaiting OTC handover.';

        return redirect()->route('loans.disbursement', $loan)->with('success', $msg);
    }

    public function complete(LoanDetail $loan)
    {
        if (! in_array($loan->status, self::EDITABLE_STATUSES)) {
            return redirect()->route('loans.stages', $loan)->with('error', 'Loan is '.ucfirst(str_replace('_', ' ', $loan->status)).'. Changes are not allowed.');
        }

        $disbursement = $loan->disbursement;
        if (! $disbursement || empty($disbursement->entryList())) {
            return redirect()->route('loans.disbursement', $loan)->with('error', 'Save at least one disbursement entry first.');
        }

        app(DisbursementService::class)->markFullyDisbursed($loan);
        $loan->refresh();

        if ($loan->status === LoanDetail::STATUS_COMPLETED) {
            return redirect()->route('loans.show', $loan)->with('success', 'Disbursement marked as complete. Loan completed!');
        }

        $pendingOtc = $disbursement->entryRows()->whereNotIn('otc_status', DisbursementEntry::OTC_SETTLED)->count();

        return redirect()->route('loans.disbursement', $loan)
            ->with('success', 'Marked as fully disbursed — '.$pendingOtc.' '.str('entry')->plural($pendingOtc).' awaiting OTC handover.');
    }

    /**
     * Record / change the OTC handover state of a single disbursement tranche.
     */
    public function entryOtc(Request $request, LoanDetail $loan, int $entry)
    {
        if (! in_array($loan->status, self::EDITABLE_STATUSES)) {
            return redirect()->route('loans.stages', $loan)->with('error', 'Loan is '.ucfirst(str_replace('_', ' ', $loan->status)).'. Changes are not allowed.');
        }

        $validated = $request->validate([
            'otc_status' => 'required|in:cleared,skipped,pending',
            'otc_handover_date' => 'nullable|date_format:d/m/Y|required_if:otc_status,cleared',
            'otc_remarks' => 'nullable|string|max:2000',
        ]);

        $entryRow = $loan->disbursementEntries()->findOrFail($entry);

        $handoverDate = ! empty($validated['otc_handover_date'])
            ? Carbon::createFromFormat('d/m/Y', $validated['otc_handover_date'])->toDateString()
            : null;

        app(DisbursementService::class)->recordEntryOtc(
            $entryRow,
            $validated['otc_status'],
            $handoverDate,
            $validated['otc_remarks'] ?? null,
        );

        $loan->refresh();
        $msg = $loan->status === LoanDetail::STATUS_COMPLETED
            ? 'OTC handover recorded. Loan completed!'
            : 'OTC handover updated.';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Active products of the loan's bank (bank_id, with a name-based fallback for legacy loans).
     */
    private function bankProducts(LoanDetail $loan): Collection
    {
        $bankId = $loan->bank_id ?? Bank::where('name', $loan->bank_name)->value('id');

        if (! $bankId) {
            return collect();
        }

        return Product::query()
            ->where('bank_id', $bankId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
