<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\LoanConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoanConversionController extends Controller
{
    public function __construct(
        private LoanConversionService $conversionService,
    ) {}

    public function showConvertForm(Quotation $quotation)
    {
        if (! auth()->user()->canCreateLoans()) {
            abort(403, 'You do not have permission to create loans.');
        }
        if ($quotation->is_converted) {
            return redirect()->route('loans.show', $quotation->loan_id)
                ->with('info', 'This quotation has already been converted to Loan #'.$quotation->loan->loan_number);
        }
        if ($quotation->is_cancelled) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'This quotation is cancelled and cannot be converted to a loan.');
        }

        // Authorization: view_all / own / branch scope
        if (! $quotation->isVisibleTo(auth()->user())) {
            abort(403);
        }

        $quotation->load(['banks', 'documents', 'branch.location.parent', 'user.roles']);
        $products = Product::active()->with(['bank', 'locations'])->orderBy('name')->get();
        $advisors = User::advisorEligible()->with(['branches', 'locations', 'roles'])->orderBy('name')->get();

        $uid = auth()->id();

        // Loan payout user — holds none of super_admin / admin / bank_employee / office_employee.
        $payoutUsers = User::payoutEligible()->with('roles')->orderBy('name')->get();
        // Default: a connector who created the quotation (the lead source) keeps the
        // payout; otherwise default to the current user converting it (when eligible).
        $creator = $quotation->user;
        $defaultPayoutUserId = ($creator && $creator->hasRole('connector'))
            ? $creator->id
            : ($payoutUsers->contains('id', $uid) ? $uid : null);

        // Map quotation bank names to banks table IDs for product filtering
        $allBanks = Bank::active()->get();
        $bankNameToId = $allBanks->pluck('id', 'name')->toArray();

        // Branch comes from quotation (locked) or fallback to user's default
        $lockedBranch = $quotation->branch;
        $defaultBranchId = $lockedBranch?->id ?? auth()->user()->default_branch_id;

        // Default advisor to the current user when they are advisor-eligible.
        $defaultAdvisorId = $advisors->contains('id', $uid) ? $uid : null;

        $template = 'newtheme.quotations.convert';

        return view($template, compact('quotation', 'products', 'advisors', 'payoutUsers', 'defaultPayoutUserId', 'bankNameToId', 'defaultBranchId', 'defaultAdvisorId', 'lockedBranch') + ['pageKey' => 'quotations']);
    }

    public function convert(Request $request, Quotation $quotation)
    {
        if ($quotation->is_cancelled) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'This quotation is cancelled and cannot be converted to a loan.');
        }

        $validated = $request->validate([
            'bank_index' => 'required|integer|min:0',
            'branch_id' => 'nullable|exists:branches,id',
            'product_id' => 'required|exists:products,id',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'date_of_birth' => 'required|date_format:d/m/Y',
            'pan_number' => ['required', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'],
            'assigned_advisor' => 'required|exists:users,id',
            'payout_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:5000',
        ], [
            'pan_number.regex' => 'PAN number must be in format ABCDE1234F.',
            'date_of_birth.date_format' => 'Date of birth must be in dd/mm/yyyy format.',
        ]);

        // Convert date format for storage
        $validated['date_of_birth'] = Carbon::createFromFormat('d/m/Y', $validated['date_of_birth'])->toDateString();
        $validated['pan_number'] = strtoupper($validated['pan_number']);

        // Use quotation's branch if available, otherwise use form input
        $validated['branch_id'] = $quotation->branch_id ?? $validated['branch_id'] ?? auth()->user()->default_branch_id;

        // Payout user must be payout-eligible (not super_admin / admin / bank_employee / office_employee).
        if (! empty($validated['payout_user_id'])) {
            $pu = User::with('roles')->find($validated['payout_user_id']);
            if (! $pu || ! $pu->isPayoutEligible()) {
                return redirect()->back()->withInput()->with('error', 'Selected loan payout user is not eligible (super admin, admin, bank employee and office employee cannot be a payout user).');
            }
        }

        try {
            $quotation->load(['banks', 'documents']);
            $loan = $this->conversionService->convertFromQuotation(
                $quotation,
                (int) $validated['bank_index'],
                $validated,
            );

            return redirect()->route('loans.show', $loan)
                ->with('success', 'Quotation converted to Loan #'.$loan->loan_number);
        } catch (\RuntimeException $e) {
            // Business-rule failures carry a user-facing message.
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // DB/unexpected failure — surface a clear error instead of a 500 so
            // the user knows the loan was NOT created and can retry.
            report($e);

            return redirect()->back()->withInput()
                ->with('error', 'Could not convert the quotation to a loan. Please try again.');
        }
    }
}
