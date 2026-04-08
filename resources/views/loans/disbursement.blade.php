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

        @if(session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('loans.disbursement.store', $loan) }}">
            @csrf
            <div class="shf-section mb-4">
                <div class="shf-section-header"><span class="shf-section-title">Disbursement Method</span></div>
                <div class="shf-section-body">
                    <div class="row g-3">
                        @foreach(\App\Models\DisbursementDetail::TYPES as $key => $label)
                            <div class="col-sm-6">
                                <div class="form-check p-3 border rounded {{ old('disbursement_type', $disbursement?->disbursement_type) === $key ? 'border-primary' : '' }}">
                                    <input class="form-check-input" type="radio" name="disbursement_type" value="{{ $key }}" id="type_{{ $key }}"
                                           {{ old('disbursement_type', $disbursement?->disbursement_type ?? 'fund_transfer') === $key ? 'checked' : '' }} required>
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
                            <label class="shf-form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">₹</span>
                                <input type="number" name="amount_disbursed" id="disbAmount" class="shf-input w-100" value="{{ old('amount_disbursed', $disbursement?->amount_disbursed ?? $loan->loan_amount) }}" min="1" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="shf-form-label">Disbursement Date <span class="text-danger">*</span></label>
                            <input type="text" name="disbursement_date" class="shf-input shf-datepicker" value="{{ old('disbursement_date', $disbursement?->disbursement_date?->format('d/m/Y') ?? now()->format('d/m/Y')) }}" required>
                        </div>

                        {{-- Fund Transfer fields --}}
                        <div class="col-sm-6 shf-fund-transfer-fields">
                            <label class="shf-form-label">Loan Account Number</label>
                            <input type="text" name="bank_account_number" class="shf-input w-100" value="{{ old('bank_account_number', $disbursement?->bank_account_number) }}">
                        </div>

                        {{-- Cheque fields --}}
                        <div class="col-12 shf-cheque-fields" style="display:none;">
                            <label class="shf-form-label">Cheques</label>
                            <div id="chequeList">
                                @php $existingCheques = old('cheques', $disbursement?->cheques ?? []); @endphp
                                @if(!empty($existingCheques))
                                    @foreach($existingCheques as $i => $chq)
                                        <div class="row g-2 mb-2 cheque-row">
                                            <div class="col-sm-4">
                                                <input type="text" name="cheques[{{ $i }}][cheque_number]" class="shf-input shf-input-sm" placeholder="Cheque Number" value="{{ $chq['cheque_number'] ?? '' }}" required>
                                            </div>
                                            <div class="col-sm-3">
                                                <input type="text" name="cheques[{{ $i }}][cheque_date]" class="shf-input shf-input-sm shf-datepicker" placeholder="dd/mm/yyyy" value="{{ $chq['cheque_date'] ?? '' }}" required>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="input-group input-group-sm"><span class="input-group-text">₹</span>
                                                    <input type="number" name="cheques[{{ $i }}][cheque_amount]" class="shf-input cheque-amount" value="{{ $chq['cheque_amount'] ?? '' }}" min="0" step="0.01" required>
                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-cheque w-100">Remove</button>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            <button type="button" id="addCheque" class="btn-accent-sm mt-1" style="font-size:0.75rem;">+ Add Cheque</button>
                            <div class="mt-2">
                                <small class="text-muted">Cheque Total: <strong id="chequeTotal">₹ 0</strong></small>
                                <small class="text-danger d-none" id="chequeTotalError"> (exceeds disbursement amount!)</small>
                            </div>
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
    $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });

    var chequeIndex = {{ count($existingCheques ?? []) }};

    function toggleFields() {
        var type = $('input[name="disbursement_type"]:checked').val();
        $('.shf-fund-transfer-fields').toggle(type === 'fund_transfer');
        $('.shf-cheque-fields').toggle(type === 'cheque');
    }

    $('input[name="disbursement_type"]').on('change', function() {
        $('.form-check.border').removeClass('border-primary');
        $(this).closest('.form-check').addClass('border-primary');
        toggleFields();
    });
    toggleFields();

    // Add cheque row
    $('#addCheque').on('click', function() {
        var html = '<div class="row g-2 mb-2 cheque-row">'
            + '<div class="col-sm-4"><input type="text" name="cheques[' + chequeIndex + '][cheque_number]" class="shf-input shf-input-sm" placeholder="Cheque Number" required></div>'
            + '<div class="col-sm-3"><input type="text" name="cheques[' + chequeIndex + '][cheque_date]" class="shf-input shf-input-sm shf-datepicker" placeholder="dd/mm/yyyy" required></div>'
            + '<div class="col-sm-3"><div class="input-group input-group-sm"><span class="input-group-text">₹</span><input type="number" name="cheques[' + chequeIndex + '][cheque_amount]" class="shf-input cheque-amount" min="0" step="0.01" required></div></div>'
            + '<div class="col-sm-2"><button type="button" class="btn btn-sm btn-outline-danger remove-cheque w-100">Remove</button></div>'
            + '</div>';
        $('#chequeList').append(html);
        $('#chequeList .cheque-row:last .shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });
        chequeIndex++;
        updateChequeTotal();
    });

    // Remove cheque row
    $(document).on('click', '.remove-cheque', function() {
        $(this).closest('.cheque-row').remove();
        updateChequeTotal();
    });

    // Update cheque total
    function updateChequeTotal() {
        var total = 0;
        $('.cheque-amount').each(function() { total += parseFloat($(this).val()) || 0; });
        $('#chequeTotal').text('₹ ' + total.toLocaleString('en-IN'));
        var amount = parseFloat($('#disbAmount').val()) || 0;
        $('#chequeTotalError').toggleClass('d-none', total <= amount);
    }

    $(document).on('input', '.cheque-amount', updateChequeTotal);
    $('#disbAmount').on('input', updateChequeTotal);
    updateChequeTotal();
});
</script>
@endpush
