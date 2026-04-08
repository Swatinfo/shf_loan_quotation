@extends('layouts.app')

@section('header')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('loans.stages', $loan) }}" style="color: rgba(255,255,255,0.4); text-decoration: none;">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">Valuation — {{ $loan->loan_number }}</h2>
    </div>
@endsection

@php $v = $valuations->first(); @endphp

@section('content')
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5" style="max-width: 48rem;">

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('loans.valuation.store', $loan) }}">
            @csrf
            <div class="shf-section mb-4">
                <div class="shf-section-header"><span class="shf-section-title">Valuation Details</span></div>
                <div class="shf-section-body">
                    <div class="row g-3">

                        {{-- Valuation Type --}}
                        <div class="col-sm-6">
                            <label class="shf-form-label">Valuation Type <span class="text-danger">*</span></label>
                            <select name="valuation_type" id="valuationType" class="shf-input w-100" required>
                                @foreach(\App\Models\ValuationDetail::TYPES as $key => $label)
                                    <option value="{{ $key }}" {{ old('valuation_type', $v?->valuation_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Valuation Date --}}
                        <div class="col-sm-6">
                            <label class="shf-form-label">Valuation Date <span class="text-danger">*</span></label>
                            <input type="text" name="valuation_date" class="shf-input shf-datepicker" value="{{ old('valuation_date', $v?->valuation_date?->format('d/m/Y') ?? now()->format('d/m/Y')) }}" required>
                        </div>

                        {{-- Property-specific fields --}}
                        <div class="col-sm-6 valuation-field" data-types="property">
                            <label class="shf-form-label">Property Type <span class="text-danger">*</span></label>
                            <select name="property_type" class="shf-input w-100 valuation-required" data-types="property">
                                <option value="">-- Select --</option>
                                @foreach(\App\Models\ValuationDetail::PROPERTY_TYPES as $key => $label)
                                    <option value="{{ $key }}" {{ old('property_type', $v?->property_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-sm-6 valuation-field" data-types="property">
                            <label class="shf-form-label">Area <span class="text-danger">*</span></label>
                            <input type="text" name="property_area" class="shf-input w-100 valuation-required" data-types="property" value="{{ old('property_area', $v?->property_area) }}" placeholder="e.g. 1200 sq ft">
                        </div>

                        <div class="col-12 valuation-field" data-types="property">
                            <label class="shf-form-label">Property Address <span class="text-danger">*</span></label>
                            <textarea name="property_address" class="shf-input w-100 valuation-required" data-types="property" rows="2">{{ old('property_address', $v?->property_address) }}</textarea>
                        </div>

                        {{-- Vehicle-specific fields --}}
                        <div class="col-sm-6 valuation-field" data-types="vehicle">
                            <label class="shf-form-label">Vehicle Type <span class="text-danger">*</span></label>
                            <select name="property_type" class="shf-input w-100 valuation-required" data-types="vehicle">
                                <option value="">-- Select --</option>
                                <option value="two_wheeler" {{ old('property_type', $v?->property_type) === 'two_wheeler' ? 'selected' : '' }}>Two Wheeler</option>
                                <option value="four_wheeler" {{ old('property_type', $v?->property_type) === 'four_wheeler' ? 'selected' : '' }}>Four Wheeler</option>
                                <option value="commercial" {{ old('property_type', $v?->property_type) === 'commercial' ? 'selected' : '' }}>Commercial Vehicle</option>
                                <option value="construction" {{ old('property_type', $v?->property_type) === 'construction' ? 'selected' : '' }}>Construction Equipment</option>
                            </select>
                        </div>

                        <div class="col-sm-6 valuation-field" data-types="vehicle">
                            <label class="shf-form-label">Registration / Chassis No.</label>
                            <input type="text" name="property_area" class="shf-input w-100" data-types="vehicle" value="{{ old('property_area', $v?->property_area) }}" placeholder="e.g. GJ-03-AB-1234">
                        </div>

                        {{-- Business-specific fields --}}
                        <div class="col-sm-6 valuation-field" data-types="business">
                            <label class="shf-form-label">Business Type <span class="text-danger">*</span></label>
                            <select name="property_type" class="shf-input w-100 valuation-required" data-types="business">
                                <option value="">-- Select --</option>
                                <option value="manufacturing" {{ old('property_type', $v?->property_type) === 'manufacturing' ? 'selected' : '' }}>Manufacturing</option>
                                <option value="trading" {{ old('property_type', $v?->property_type) === 'trading' ? 'selected' : '' }}>Trading</option>
                                <option value="service" {{ old('property_type', $v?->property_type) === 'service' ? 'selected' : '' }}>Service</option>
                                <option value="other" {{ old('property_type', $v?->property_type) === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div class="col-12 valuation-field" data-types="business">
                            <label class="shf-form-label">Business Address</label>
                            <textarea name="property_address" class="shf-input w-100" rows="2">{{ old('property_address', $v?->property_address) }}</textarea>
                        </div>

                        {{-- Common fields (all types) --}}
                        <div class="col-sm-4">
                            <label class="shf-form-label">Market Value <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">₹</span>
                                <input type="number" name="market_value" class="shf-input w-100" value="{{ old('market_value', $v?->market_value) }}" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <label class="shf-form-label">Government Value <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">₹</span>
                                <input type="number" name="government_value" class="shf-input w-100" value="{{ old('government_value', $v?->government_value) }}" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <label class="shf-form-label">Valuator Name <span class="text-danger">*</span></label>
                            <input type="text" name="valuator_name" class="shf-input w-100" value="{{ old('valuator_name', $v?->valuator_name) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="shf-form-label">Report Number</label>
                            <input type="text" name="valuator_report_number" class="shf-input w-100" value="{{ old('valuator_report_number', $v?->valuator_report_number) }}">
                        </div>
                        <div class="col-sm-6">
                            &nbsp;
                        </div>
                        <div class="col-12">
                            <label class="shf-form-label">Notes</label>
                            <textarea name="notes" class="shf-input w-100" rows="2">{{ old('notes', $v?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-3 mb-4">
                <a href="{{ route('loans.stages', $loan) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn-accent" style="padding: 10px 24px;">Save Valuation</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });

    function toggleValuationFields() {
        var type = $('#valuationType').val();

        // Hide all type-specific fields and disable their inputs
        $('.valuation-field').hide().find('input, select, textarea').each(function() {
            $(this).prop('required', false).prop('disabled', true);
            if ($(this).data('orig-name')) return; // already saved
            $(this).data('orig-name', $(this).attr('name'));
            $(this).removeAttr('name');
        });

        // Show and enable fields for selected type
        $('.valuation-field[data-types="' + type + '"]').show().find('input, select, textarea').each(function() {
            $(this).prop('disabled', false);
            if ($(this).data('orig-name')) $(this).attr('name', $(this).data('orig-name'));
        });

        // Set required on visible required fields
        $('.valuation-required[data-types="' + type + '"]').prop('required', true);
    }

    $('#valuationType').on('change', toggleValuationFields);
    toggleValuationFields(); // run on load
});
</script>
@endpush
