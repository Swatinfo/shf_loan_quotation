<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Services\DisbursementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoanDisbursementController extends Controller
{
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
        $isLocked = $stageCompleted || ! in_array($loan->status, [LoanDetail::STATUS_ACTIVE, LoanDetail::STATUS_ON_HOLD]);

        $entries = $disbursement?->entryList() ?? [];
        $disbursedSoFar = $disbursement?->entryTotal() ?? 0;
        $target = $service->disbursementTarget($loan);
        $products = $this->bankProducts($loan);

        $template = 'newtheme.loans.disbursement';

        return view($template, compact(
            'loan', 'disbursement', 'sanctionedAmount', 'isLocked', 'stageCompleted',
            'entries', 'disbursedSoFar', 'target', 'products',
        ) + ['pageKey' => 'loans']);
    }

    public function store(Request $request, LoanDetail $loan)
    {
        if (! in_array($loan->status, [LoanDetail::STATUS_ACTIVE, LoanDetail::STATUS_ON_HOLD])) {
            return redirect()->route('loans.stages', $loan)->with('error', 'Loan is '.ucfirst($loan->status).'. Changes are not allowed.');
        }

        $stageAssignment = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        if ($stageAssignment?->status === 'completed') {
            return redirect()->route('loans.stages', $loan)->with('error', 'Disbursement is already completed. Entries can no longer be changed.');
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
            'notes' => 'nullable|string|max:5000',
        ], [
            'entries.*.product_id.in' => 'The selected product does not belong to this loan\'s bank.',
        ]);

        // Cheque entries require the cheque instrument fields; snapshot product name + normalize dates.
        $chequeErrors = [];
        foreach ($validated['entries'] as $i => $entry) {
            if ($entry['method'] === DisbursementDetail::TYPE_CHEQUE) {
                foreach (['cheque_name', 'cheque_number', 'cheque_date'] as $field) {
                    if (empty($entry[$field])) {
                        $chequeErrors["entries.{$i}.{$field}"] = 'This field is required for cheque entries.';
                    }
                }
            }
            $validated['entries'][$i]['disbursement_date'] = Carbon::createFromFormat('d/m/Y', $entry['disbursement_date'])->toDateString();
            $validated['entries'][$i]['product_id'] = (int) $entry['product_id'];
            $validated['entries'][$i]['product_name'] = $productNames[(int) $entry['product_id']];
            $validated['entries'][$i]['amount'] = (int) $entry['amount'];
        }
        if ($chequeErrors) {
            throw ValidationException::withMessages($chequeErrors);
        }

        $disbursement = app(DisbursementService::class)->processDisbursement($loan, $validated);

        $stageCompleted = $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status') === 'completed';

        if ($stageCompleted) {
            $successMsg = $disbursement->hasChequeEntries()
                ? 'Disbursement completed. OTC stage opened.'
                : 'Loan fully disbursed and completed!';

            return redirect()->route('loans.show', $loan)->with('success', $successMsg);
        }

        $remaining = max(0, app(DisbursementService::class)->disbursementTarget($loan) - $disbursement->entryTotal());

        return redirect()->route('loans.disbursement', $loan)
            ->with('success', 'Disbursement entries saved — remaining ₹ '.number_format($remaining).'.');
    }

    public function complete(LoanDetail $loan)
    {
        if (! in_array($loan->status, [LoanDetail::STATUS_ACTIVE, LoanDetail::STATUS_ON_HOLD])) {
            return redirect()->route('loans.stages', $loan)->with('error', 'Loan is '.ucfirst($loan->status).'. Changes are not allowed.');
        }

        $disbursement = $loan->disbursement;
        if (! $disbursement || empty($disbursement->entryList())) {
            return redirect()->route('loans.disbursement', $loan)->with('error', 'Save at least one disbursement entry first.');
        }

        $stageAssignment = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        if ($stageAssignment?->status !== 'in_progress') {
            return redirect()->route('loans.stages', $loan)->with('error', 'Disbursement stage is not open.');
        }

        app(DisbursementService::class)->markFullyDisbursed($loan);

        $successMsg = $disbursement->hasChequeEntries()
            ? 'Disbursement marked as complete. OTC stage opened.'
            : 'Disbursement marked as complete. Loan completed!';

        return redirect()->route('loans.show', $loan)->with('success', $successMsg);
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
