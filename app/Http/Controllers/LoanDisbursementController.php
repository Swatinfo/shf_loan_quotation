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
            'disbursement_type' => 'required|in:fund_transfer,cheque,demand_draft',
            'disbursement_date' => 'nullable|date',
            'amount_disbursed' => 'nullable|numeric|min:0|max:100000000000',
            'bank_account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'cheque_number' => 'nullable|string|max:50',
            'cheque_date' => 'nullable|date',
            'dd_number' => 'nullable|string|max:50',
            'dd_date' => 'nullable|date',
            'is_otc' => 'boolean',
            'otc_branch' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);

        $validated['is_otc'] = $request->boolean('is_otc');

        $disbursement = app(DisbursementService::class)->processDisbursement($loan, $validated);

        if ($disbursement->needsOtcClearance()) {
            return redirect()->route('loans.disbursement', $loan)
                ->with('info', 'Disbursement saved. OTC clearance pending.');
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan disbursed and completed!');
    }

    public function clearOtc(LoanDetail $loan)
    {
        $disbursement = $loan->disbursement;
        abort_unless($disbursement && $disbursement->needsOtcClearance(), 404);

        app(DisbursementService::class)->clearOtc($disbursement);

        return redirect()->route('loans.show', $loan)
            ->with('success', 'OTC cleared. Loan completed!');
    }
}
