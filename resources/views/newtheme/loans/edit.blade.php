@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Edit Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-edit.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('loans.index') }}">Loans</a>
                    <span class="sep">/</span>
                    <a href="{{ route('loans.show', $loan) }}">#{{ $loan->loan_number }}</a>
                    <span class="sep">/</span>
                    <span>Edit</span>
                </div>
                <h1>Edit Loan #{{ $loan->loan_number }}</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if ($errors->any())
            <div class="card le-alert">
                <div class="card-bd">
                    <strong>Please fix the following:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('loans.update', $loan) }}" id="leForm" autocomplete="off">
            @csrf
            @method('PUT')

            {{-- ===== Customer Information ===== --}}
            <div class="card le-card">
                <div class="card-hd"><div class="t"><span class="num">1</span>Customer Information</div></div>
                <div class="card-bd">
                    <div class="le-grid">
                        <div class="le-field">
                            <label class="lbl" for="leCustomerName">Customer Name <span style="color:var(--red);">*</span></label>
                            <input type="text" name="customer_name" id="leCustomerName" class="input"
                                value="{{ old('customer_name', $loan->customer_name) }}" maxlength="255">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leCustomerType">Customer Type <span style="color:var(--red);">*</span></label>
                            <select name="customer_type" id="leCustomerType" class="input">
                                @foreach (\App\Models\LoanDetail::CUSTOMER_TYPE_LABELS as $key => $label)
                                    <option value="{{ $key }}" {{ old('customer_type', $loan->customer_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leCustomerPhone">Phone <span style="color:var(--red);">*</span></label>
                            <input type="text" name="customer_phone" id="leCustomerPhone" class="input"
                                value="{{ old('customer_phone', $loan->customer_phone) }}" maxlength="20">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leCustomerEmail">Email</label>
                            <input type="email" name="customer_email" id="leCustomerEmail" class="input"
                                value="{{ old('customer_email', $loan->customer_email) }}" maxlength="255">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leDob">Date of Birth <span style="color:var(--red);">*</span></label>
                            <input type="text" name="date_of_birth" id="leDob" class="input shf-datepicker"
                                value="{{ old('date_of_birth', $loan->date_of_birth?->format('d/m/Y')) }}"
                                placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="lePan">PAN Number <span style="color:var(--red);">*</span></label>
                            <input type="text" name="pan_number" id="lePan" class="input"
                                value="{{ old('pan_number', $loan->pan_number) }}" maxlength="10"
                                style="text-transform:uppercase;letter-spacing:0.06em;">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Loan Details ===== --}}
            <div class="card le-card">
                <div class="card-hd"><div class="t"><span class="num">2</span>Loan Details</div></div>
                <div class="card-bd">
                    <div class="le-grid">
                        <div class="le-field">
                            <label class="lbl" for="leAmount">Loan Amount <span style="color:var(--red);">*</span></label>
                            <div class="shf-amount-wrap le-amount-wrap">
                                <span class="le-rupee">₹</span>
                                <input type="text" id="leAmount" class="input shf-amount-input le-amount-input"
                                    value="{{ old('loan_amount', $loan->loan_amount) }}" placeholder="e.g. 50,00,000">
                                <input type="hidden" name="loan_amount" class="shf-amount-raw"
                                    value="{{ old('loan_amount', $loan->loan_amount) }}">
                            </div>
                            <div class="le-amount-words" data-amount-words></div>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leBank">Bank <span style="color:var(--red);">*</span></label>
                            <select name="bank_id" id="leBank" class="input">
                                <option value="">— Select —</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}" {{ old('bank_id', $loan->bank_id) == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leProduct">Product <span style="color:var(--red);">*</span></label>
                            <select name="product_id" id="leProduct" class="input">
                                <option value="">— Select —</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-bank-id="{{ $product->bank_id }}"
                                        {{ old('product_id', $loan->product_id) == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leBranch">Branch <span style="color:var(--red);">*</span></label>
                            <select name="branch_id" id="leBranch" class="input">
                                <option value="">— Select —</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id', $loan->branch_id) == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Assignment + notes ===== --}}
            <div class="card le-card">
                <div class="card-hd"><div class="t"><span class="num">3</span>Assignment</div></div>
                <div class="card-bd">
                    <div class="le-field">
                        <label class="lbl" for="leAdvisor">Assigned Advisor</label>
                        <select name="assigned_advisor" id="leAdvisor" class="input">
                            <option value="">— None —</option>
                            @foreach ($advisors as $advisor)
                                <option value="{{ $advisor->id }}" {{ old('assigned_advisor', $loan->assigned_advisor) == $advisor->id ? 'selected' : '' }}>
                                    {{ $advisor->name }} ({{ $advisor->workflow_role_label }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="le-field">
                        <label class="lbl" for="leNotes">Notes</label>
                        <textarea name="notes" id="leNotes" class="input" rows="4" maxlength="5000"
                            style="height:auto;padding:10px;line-height:1.45;">{{ old('notes', $loan->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="le-actions">
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancel
                </a>
                <button type="submit" class="btn primary" id="leSubmit">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                    Update Loan
                </button>
            </div>
        </form>
    </main>
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/pages/loan-edit.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
