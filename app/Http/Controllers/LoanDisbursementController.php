<?php

namespace App\Http\Controllers;

use App\Models\LoanDetail;
use App\Services\DisbursementService;
use Illuminate\Http\Request;

class LoanDisbursementController extends Controller
{
    public function show(LoanDetail $loan)
    {
        $disbursement = $loan->disbursement;

        return view('loans.disbursement', compact('loan', 'disbursement'));
    }

    public function store(Request $request, LoanDetail $loan)
    {
        $validated = $request->validate([
            'disbursement_type' => 'required|in:fund_transfer,cheque',
            'disbursement_date' => 'required|date_format:d/m/Y',
            'amount_disbursed' => 'required|numeric|min:1|max:100000000000',
            'bank_account_number' => 'nullable|string|max:50',
            'cheques' => 'nullable|array',
            'cheques.*.cheque_number' => 'required_with:cheques|string|max:50',
            'cheques.*.cheque_date' => 'required_with:cheques|string|max:20',
            'cheques.*.cheque_amount' => 'required_with:cheques|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        $validated['disbursement_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['disbursement_date'])->toDateString();

        // Validate cheque total doesn't exceed amount
        if ($validated['disbursement_type'] === 'cheque' && ! empty($validated['cheques'])) {
            $chequeTotal = array_sum(array_column($validated['cheques'], 'cheque_amount'));
            if ($chequeTotal > $validated['amount_disbursed']) {
                return redirect()->back()->withInput()->with('error', 'Total cheque amount (₹ '.number_format($chequeTotal).') exceeds disbursement amount (₹ '.number_format($validated['amount_disbursed']).').');
            }
        }

        $disbursement = app(DisbursementService::class)->processDisbursement($loan, $validated);

        $successMsg = $validated['disbursement_type'] === 'fund_transfer'
            ? 'Loan disbursed and completed!'
            : 'Disbursement saved. OTC stage opened.';

        return redirect()->route('loans.show', $loan)->with('success', $successMsg);
    }
}
