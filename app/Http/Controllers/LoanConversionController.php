<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\LoanConversionService;
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

        // Authorization: own or view_all_quotations
        if ($quotation->user_id !== auth()->id() && ! auth()->user()->hasPermission('view_all_quotations')) {
            abort(403);
        }

        $quotation->load(['banks', 'documents']);
        $branches = Branch::active()->with('location.parent')->orderBy('name')->get();
        $products = Product::active()->with(['bank', 'locations'])->orderBy('name')->get();
        $advisors = User::advisorEligible()->with(['branches', 'locations'])->orderBy('name')->get();

        // Map quotation bank names to banks table IDs for product filtering
        $allBanks = \App\Models\Bank::active()->get();
        $bankNameToId = $allBanks->pluck('id', 'name')->toArray();

        // Auto-select user's branch (if they have exactly one)
        $userBranches = auth()->user()->branches;
        $defaultBranchId = $userBranches->count() === 1 ? $userBranches->first()->id : (auth()->user()->default_branch_id ?? null);

        // Auto-select current user as advisor (if they have a task_role)
        $defaultAdvisorId = auth()->user()->task_role ? auth()->id() : null;

        return view('quotations.convert', compact('quotation', 'branches', 'products', 'advisors', 'bankNameToId', 'defaultBranchId', 'defaultAdvisorId'));
    }

    public function convert(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'bank_index' => 'required|integer|min:0',
            'branch_id' => 'required|exists:branches,id',
            'product_id' => 'required|exists:products,id',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'assigned_advisor' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:5000',
        ]);

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
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
