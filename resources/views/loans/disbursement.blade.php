@extends('layouts.app')

@section('header')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('loans.stages', $loan) }}" style="color: rgba(255,255,255,0.4); text-decoration: none;">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">Disbursement — {{ $loan->loan_number }}</h2>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5" style="max-width: 48rem;">

        @if($disbursement && $disbursement->needsOtcClearance())
            <div class="alert alert-warning">
                <strong>OTC Pending:</strong> Cheque disbursement at {{ $disbursement->otc_branch }}.
                <form method="POST" action="{{ route('loans.disbursement.clear-otc', $loan) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success ms-2"
                            onclick="return confirm('Mark OTC as cleared?')">Clear OTC</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('loans.disbursement.store', $loan) }}">
            @csrf
            <div class="shf-section mb-4">
                <div class="shf-section-header"><span class="shf-section-title">Disbursement Method</span></div>
                <div class="shf-section-body">
                    <div class="row g-3">
                        @foreach(\App\Models\DisbursementDetail::TYPES as $key => $label)
                            <div class="col-sm-4">
                                <div class="form-check p-3 border rounded {{ old('disbursement_type', $disbursement?->disbursement_type) === $key ? 'border-primary' : '' }}">
                                    <input class="form-check-input" type="radio" name="disbursement_type" value="{{ $key }}" id="type_{{ $key }}"
                                           {{ old('disbursement_type', $disbursement?->disbursement_type) === $key ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="type_{{ $key }}"><strong>{{ $label }}</strong></label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="shf-section mb-4">
                <div class="shf-section-header"><span class="shf-section-title">Details</span></div>
                <div class="shf-section-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="shf-form-label">Amount</label>
                            <div class="input-group"><span class="input-group-text">₹</span>
                                <input type="number" name="amount_disbursed" class="shf-input w-100" value="{{ old('amount_disbursed', $disbursement?->amount_disbursed ?? $loan->loan_amount) }}" min="0">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="shf-form-label">Date</label>
                            <input type="text" name="disbursement_date" class="shf-input shf-datepicker" value="{{ old('disbursement_date', $disbursement?->disbursement_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="shf-form-label">Reference / UTR Number</label>
                            <input type="text" name="reference_number" class="shf-input w-100" value="{{ old('reference_number', $disbursement?->reference_number) }}">
                        </div>

                        {{-- Fund Transfer fields --}}
                        <div class="col-sm-6 shf-fund-transfer-fields">
                            <label class="shf-form-label">Account Number</label>
                            <input type="text" name="bank_account_number" class="shf-input w-100" value="{{ old('bank_account_number', $disbursement?->bank_account_number) }}">
                        </div>
                        <div class="col-sm-6 shf-fund-transfer-fields">
                            <label class="shf-form-label">IFSC Code</label>
                            <input type="text" name="ifsc_code" class="shf-input w-100" value="{{ old('ifsc_code', $disbursement?->ifsc_code) }}">
                        </div>

                        {{-- Cheque fields --}}
                        <div class="col-sm-6 shf-cheque-fields" style="display:none;">
                            <label class="shf-form-label">Cheque Number</label>
                            <input type="text" name="cheque_number" class="shf-input w-100" value="{{ old('cheque_number', $disbursement?->cheque_number) }}">
                        </div>
                        <div class="col-sm-6 shf-cheque-fields" style="display:none;">
                            <label class="shf-form-label">Cheque Date</label>
                            <input type="text" name="cheque_date" class="shf-input shf-datepicker" value="{{ old('cheque_date', $disbursement?->cheque_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-12 shf-cheque-fields" style="display:none;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_otc" value="1" id="isOtc"
                                       {{ old('is_otc', $disbursement?->is_otc) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isOtc">OTC (Over The Counter)</label>
                            </div>
                        </div>
                        <div class="col-sm-6 shf-otc-fields" style="display:none;">
                            <label class="shf-form-label">OTC Branch</label>
                            <input type="text" name="otc_branch" class="shf-input w-100" value="{{ old('otc_branch', $disbursement?->otc_branch) }}">
                        </div>

                        {{-- DD fields --}}
                        <div class="col-sm-6 shf-dd-fields" style="display:none;">
                            <label class="shf-form-label">DD Number</label>
                            <input type="text" name="dd_number" class="shf-input w-100" value="{{ old('dd_number', $disbursement?->dd_number) }}">
                        </div>
                        <div class="col-sm-6 shf-dd-fields" style="display:none;">
                            <label class="shf-form-label">DD Date</label>
                            <input type="text" name="dd_date" class="shf-input shf-datepicker" value="{{ old('dd_date', $disbursement?->dd_date?->format('Y-m-d')) }}">
                        </div>

                        <div class="col-12">
                            <label class="shf-form-label">Notes</label>
                            <textarea name="notes" class="shf-input w-100" rows="2">{{ old('notes', $disbursement?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 mb-4">
                <a href="{{ route('loans.stages', $loan) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn-accent" style="padding: 10px 24px;">Process Disbursement</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('.shf-datepicker').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true });

    function toggleFields() {
        var type = $('input[name="disbursement_type"]:checked').val();
        $('.shf-fund-transfer-fields').toggle(type === 'fund_transfer');
        $('.shf-cheque-fields').toggle(type === 'cheque');
        $('.shf-dd-fields').toggle(type === 'demand_draft');
        $('.shf-otc-fields').toggle(type === 'cheque' && $('#isOtc').is(':checked'));
    }

    $('input[name="disbursement_type"]').on('change', function() {
        $('.form-check.border').removeClass('border-primary');
        $(this).closest('.form-check').addClass('border-primary');
        toggleFields();
    });
    $('#isOtc').on('change', toggleFields);
    toggleFields();
});
</script>
@endpush
