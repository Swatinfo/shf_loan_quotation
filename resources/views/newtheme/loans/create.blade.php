@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'New Loan · SHF World')

@push('page-styles')
    {{-- Reuse the loan-edit page styles — same grid, inputs, amount-wrap, actions bar. --}}
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
                    <span>New</span>
                </div>
                <h1>Create New Loan</h1>
                <div class="sub">Enter customer details, loan amount, bank + product, and assign an advisor.</div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.index') }}" class="btn">
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

        <form method="POST" action="{{ route('loans.store') }}" id="leForm" autocomplete="off">
            @csrf

            {{-- ===== Customer Information ===== --}}
            <div class="card le-card">
                <div class="card-hd"><div class="t"><span class="num">1</span>Customer Information</div></div>
                <div class="card-bd">
                    <div class="le-grid">
                        <div class="le-field">
                            <label class="lbl" for="leCustomerName">Customer Name <span style="color:var(--red);">*</span></label>
                            <input type="text" name="customer_name" id="leCustomerName" class="input" value="{{ old('customer_name') }}" maxlength="255">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leCustomerType">Customer Type <span style="color:var(--red);">*</span></label>
                            <select name="customer_type" id="leCustomerType" class="input">
                                <option value="">— Select —</option>
                                @foreach (\App\Models\LoanDetail::CUSTOMER_TYPE_LABELS as $key => $label)
                                    <option value="{{ $key }}" {{ old('customer_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leCustomerPhone">Phone <span style="color:var(--red);">*</span></label>
                            <input type="text" name="customer_phone" id="leCustomerPhone" class="input" value="{{ old('customer_phone') }}" maxlength="20">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leCustomerEmail">Email</label>
                            <input type="email" name="customer_email" id="leCustomerEmail" class="input" value="{{ old('customer_email') }}" maxlength="255">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leDob">Date of Birth <span style="color:var(--red);">*</span></label>
                            <input type="text" name="date_of_birth" id="leDob" class="input shf-datepicker" value="{{ old('date_of_birth') }}" placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="lePan">PAN Number <span style="color:var(--red);">*</span></label>
                            <input type="text" name="pan_number" id="lePan" class="input" value="{{ old('pan_number') }}" maxlength="10" style="text-transform:uppercase;letter-spacing:0.06em;">
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
                                    value="{{ old('loan_amount') }}" placeholder="e.g. 50,00,000">
                                <input type="hidden" name="loan_amount" class="shf-amount-raw" value="{{ old('loan_amount') }}">
                            </div>
                            <div class="le-amount-words" data-amount-words></div>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leBank">Bank <span style="color:var(--red);">*</span></label>
                            <select name="bank_id" id="leBank" class="input">
                                <option value="">— Select Bank —</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leProduct">Product <span style="color:var(--red);">*</span></label>
                            <select name="product_id" id="leProduct" class="input">
                                <option value="">— Select Product —</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-bank-id="{{ $product->bank_id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="le-field">
                            <label class="lbl" for="leBranch">Branch <span style="color:var(--red);">*</span></label>
                            <select name="branch_id" id="leBranch" class="input">
                                <option value="">— Select Branch —</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Assignment + Notes ===== --}}
            <div class="card le-card">
                <div class="card-hd"><div class="t"><span class="num">3</span>Assignment</div></div>
                <div class="card-bd">
                    <div class="le-field">
                        <label class="lbl" for="leAdvisor">Assigned Advisor</label>
                        <select name="assigned_advisor" id="leAdvisor" class="input">
                            <option value="">— Current User ({{ auth()->user()->name }}) —</option>
                            @foreach ($advisors as $advisor)
                                <option value="{{ $advisor->id }}" {{ old('assigned_advisor') == $advisor->id ? 'selected' : '' }}>
                                    {{ $advisor->name }} ({{ $advisor->workflow_role_label }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="le-field">
                        <label class="lbl" for="leNotes">Notes</label>
                        <textarea name="notes" id="leNotes" class="input" rows="4" maxlength="5000"
                            style="height:auto;padding:10px;line-height:1.45;">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="le-actions">
                <a href="{{ route('loans.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancel
                </a>
                <button type="submit" class="btn primary" id="leSubmit">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                    Create Loan
                </button>
            </div>
        </form>
    </main>
@endsection

@push('page-scripts')
    {{-- Reuse the loan-edit page script — same bank→product filter, PAN uppercase, datepicker, SHF.validateForm. --}}
    <script src="{{ asset('newtheme/pages/loan-edit.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
