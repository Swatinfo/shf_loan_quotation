<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\LoanDetail;
use App\Services\CustomerService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
    ) {}

    /**
     * Global PAN lookup for autofill on the loan convert/edit/create forms.
     * Returns the latest known KYC details for the PAN (across all customers,
     * not visibility-scoped), so an operator can reuse an existing identity.
     */
    public function lookup(Request $request): JsonResponse
    {
        $pan = $this->customerService->normalizePan($request->query('pan'));

        if (! $pan || ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            return response()->json(['found' => false]);
        }

        $kyc = $this->customerService->latestKycForPan($pan);
        $master = Customer::whereRaw('UPPER(pan_number) = ?', [$pan])->first();
        $source = $kyc ?? $master;

        if (! $source) {
            return response()->json(['found' => false]);
        }

        // Open loans (active + on_hold) for this customer, so the operator is
        // warned about existing in-progress loans before creating another.
        $masterId = $master?->id ?? $kyc?->customer_id;
        $loans = $masterId
            ? LoanDetail::where('customer_id', $masterId)
                ->whereIn('status', ['active', 'on_hold'])
                ->orderByDesc('id')
                ->get(['id', 'loan_number'])
                ->map(fn ($l) => [
                    'loan_number' => $l->loan_number,
                    'url' => route('loans.show', $l->id),
                ])->values()
            : collect();

        return response()->json([
            'found' => true,
            'customer' => [
                'customer_name' => $source->customer_name,
                'mobile' => $source->mobile,
                'email' => $source->email,
                'date_of_birth' => $source->date_of_birth?->format('d/m/Y'),
            ],
            'loans' => $loans,
        ]);
    }

    public function index(): View
    {
        $user = Auth::user();
        $canEdit = $user->hasPermission('manage_customers');

        $template = 'newtheme.customers.index';

        return view($template, compact('canEdit'));
    }

    /**
     * Server-side DataTables endpoint for customer listing.
     */
    public function data(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = Customer::visibleTo($user)->withCount('loans');
        $recordsTotal = (clone $query)->count();

        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('pan_number', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = ['customer_name', 'mobile', 'email', 'pan_number', 'loans_count', 'created_at'];
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'customer_name';
        $query->orderBy($orderColumn, $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $customers = $query->skip($start)->take($length)->get();

        $canEdit = $user->hasPermission('manage_customers');

        $data = $customers->map(fn (Customer $c) => [
            'id' => $c->id,
            'customer_name' => e($c->customer_name),
            'mobile' => e($c->mobile ?? ''),
            'email' => e($c->email ?? ''),
            'pan_number' => e($c->pan_number ?? ''),
            'loans_count' => (int) $c->loans_count,
            'created_at' => $c->created_at?->format('d M Y'),
            'show_url' => route('customers.show', $c),
            'edit_url' => $canEdit ? route('customers.edit', $c) : null,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    public function show(Customer $customer): View
    {
        $user = Auth::user();
        if (! $customer->isVisibleTo($user)) {
            abort(403);
        }

        $customer->load(['loans.branch', 'loans.bank']);

        $template = 'newtheme.customers.show';

        return view($template, compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        $user = Auth::user();
        if (! $customer->isEditableBy($user)) {
            abort(403);
        }

        $template = 'newtheme.customers.edit';

        return view($template, compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $user = Auth::user();
        if (! $customer->isEditableBy($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date_format:d/m/Y'],
            'pan_number' => ['nullable', 'string', 'max:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ], [
            'pan_number.regex' => 'PAN number must be in the format AAAAA9999A.',
        ]);

        if (! empty($validated['date_of_birth'])) {
            $validated['date_of_birth'] = Carbon::createFromFormat('d/m/Y', $validated['date_of_birth'])->toDateString();
        }

        $customer->update($validated);

        ActivityLog::log('update_customer', $customer, [
            'customer_name' => $customer->customer_name,
        ]);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }
}
