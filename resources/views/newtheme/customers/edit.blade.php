@extends('newtheme.layouts.app', ['pageKey' => 'customers'])

@section('title', 'Edit ' . $customer->customer_name . ' · Customer · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/customers.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('customers.index') }}">Customers</a>
                    <span class="sep">/</span>
                    <a href="{{ route('customers.show', $customer) }}">{{ $customer->customer_name }}</a>
                    <span class="sep">/</span>
                    <span>Edit</span>
                </div>
                <h1>Edit Customer</h1>
                <div class="sub">Update contact details, PAN, and date of birth.</div>
            </div>
            <div class="head-actions">
                <a href="{{ route('customers.show', $customer) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="grid c-form mt-4" style="max-width: 760px;">

            <div class="card">
                <div class="card-hd">
                    <div class="t"><span class="num">1</span>Customer Details</div>
                </div>
                <div class="card-bd">
                    <form method="POST" action="{{ route('customers.update', $customer) }}">
                        @csrf
                        @method('PUT')

                        <div class="cxf-row">
                            <label for="customer_name" class="cxf-lbl">Name <span style="color:var(--red);">*</span></label>
                            <input type="text" id="customer_name" name="customer_name" class="input"
                                value="{{ old('customer_name', $customer->customer_name) }}" required maxlength="255" autofocus>
                            @error('customer_name')<div class="cxf-err">{{ $message }}</div>@enderror
                        </div>

                        <div class="cxf-grid-2">
                            <div class="cxf-row">
                                <label for="mobile" class="cxf-lbl">Mobile</label>
                                <input type="text" id="mobile" name="mobile" class="input"
                                    value="{{ old('mobile', $customer->mobile) }}" maxlength="20" autocomplete="tel">
                                @error('mobile')<div class="cxf-err">{{ $message }}</div>@enderror
                            </div>

                            <div class="cxf-row">
                                <label for="email" class="cxf-lbl">Email</label>
                                <input type="email" id="email" name="email" class="input"
                                    value="{{ old('email', $customer->email) }}" maxlength="255" autocomplete="email">
                                @error('email')<div class="cxf-err">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="cxf-grid-2">
                            <div class="cxf-row">
                                <label for="pan_number" class="cxf-lbl">PAN</label>
                                <input type="text" id="pan_number" name="pan_number"
                                    class="input cxf-mono cxf-upper"
                                    value="{{ old('pan_number', $customer->pan_number) }}" maxlength="10"
                                    pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]" placeholder="AAAAA9999A">
                                @error('pan_number')<div class="cxf-err">{{ $message }}</div>@enderror
                            </div>

                            <div class="cxf-row">
                                <label for="date_of_birth" class="cxf-lbl">Date of Birth</label>
                                <input type="text" id="date_of_birth" name="date_of_birth"
                                    class="input shf-datepicker-past"
                                    autocomplete="off" placeholder="dd/mm/yyyy"
                                    value="{{ old('date_of_birth', $customer->date_of_birth?->format('d/m/Y')) }}">
                                @error('date_of_birth')<div class="cxf-err">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="cxf-actions">
                            <a href="{{ route('customers.show', $customer) }}" class="btn">Cancel</a>
                            <button type="submit" class="btn primary">
                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
@endsection

@push('page-scripts')
    <script>
        (function ($) {
            if (!$ || !$.fn.datepicker) return;
            $(function () {
                $('.shf-datepicker-past').datepicker({
                    format: 'dd/mm/yyyy',
                    autoclose: true,
                    todayHighlight: true,
                    endDate: '+0d',
                    clearBtn: true,
                });
            });
        })(window.jQuery);
    </script>
@endpush
