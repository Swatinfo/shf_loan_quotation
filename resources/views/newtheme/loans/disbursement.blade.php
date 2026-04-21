@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Disbursement — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-disbursement.css') }}?v={{ config('app.shf_version') }}">
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
                    <span>Disbursement</span>
                </div>
                <h1>Disbursement</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($sanctionedAmount)
                        <span class="ld-chip">Sanctioned ₹ {{ number_format((float) $sanctionedAmount) }}</span>
                    @endif
                    @if ($isLocked)
                        <span class="badge red" style="margin-left:6px;vertical-align:middle;">{{ ucfirst($loan->status) }}</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.stages', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('error'))
            <div class="card ld-alert ld-alert-red">
                <div class="card-bd">{{ session('error') }}</div>
            </div>
        @endif

        @if ($isLocked)
            <div class="card ld-alert ld-alert-{{ $loan->status === 'rejected' ? 'red' : ($loan->status === 'cancelled' ? 'amber' : 'green') }}">
                <div class="card-bd">
                    <strong>Loan {{ ucfirst($loan->status) }}.</strong> Details are read-only.
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('loans.disbursement.store', $loan) }}" id="ldForm" autocomplete="off">
            @csrf
            <fieldset {{ $isLocked ? 'disabled' : '' }} class="ld-fieldset">

                {{-- ===== Disbursement Method ===== --}}
                <div class="card ld-card">
                    <div class="card-hd"><div class="t"><span class="num">1</span>Disbursement Method</div></div>
                    <div class="card-bd">
                        <div class="ld-method-grid">
                            @foreach (\App\Models\DisbursementDetail::TYPES as $key => $label)
                                @php $checked = old('disbursement_type', $disbursement?->disbursement_type ?? 'fund_transfer') === $key; @endphp
                                <label class="ld-method-card {{ $checked ? 'is-selected' : '' }}" data-type="{{ $key }}">
                                    <input type="radio" name="disbursement_type" value="{{ $key }}" {{ $checked ? 'checked' : '' }} class="ld-method-radio">
                                    <span class="ld-method-tick" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    <span class="ld-method-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ===== Details ===== --}}
                <div class="card ld-card">
                    <div class="card-hd"><div class="t"><span class="num">2</span>Details</div></div>
                    <div class="card-bd">
                        @if ($sanctionedAmount)
                            <div class="ld-info">Sanctioned Amount: <strong>₹ {{ number_format((float) $sanctionedAmount) }}</strong></div>
                        @endif

                        <div class="ld-grid">
                            <div class="ld-field">
                                <label class="lbl" for="disbAmount">Amount <span class="ld-req">*</span></label>
                                <div class="ld-amount">
                                    <span class="ld-rupee">₹</span>
                                    <input type="text" id="disbAmount" class="input shf-amount-input ld-amount-input"
                                        value="{{ old('amount_disbursed', $disbursement?->amount_disbursed ?? ($sanctionedAmount ?? $loan->loan_amount)) }}"
                                        data-max-amount="{{ $sanctionedAmount ?? '' }}">
                                    <input type="hidden" name="amount_disbursed" class="shf-amount-raw"
                                        value="{{ old('amount_disbursed', $disbursement?->amount_disbursed ?? ($sanctionedAmount ?? $loan->loan_amount)) }}">
                                </div>
                                <div class="ld-words" data-amount-words></div>
                                <div class="ld-warning" id="amountExceedError" style="display:none;"></div>
                            </div>

                            <div class="ld-field">
                                <label class="lbl" for="disbDate">Disbursement Date <span class="ld-req">*</span></label>
                                <input type="text" name="disbursement_date" id="disbDate" class="input shf-datepicker-custom"
                                    data-min-date="{{ $disbursement?->created_at ? $disbursement->created_at->format('d/m/Y') : now()->subDays(3)->format('d/m/Y') }}"
                                    data-max-date="{{ $disbursement?->created_at ? now()->format('d/m/Y') : now()->addDays(3)->format('d/m/Y') }}"
                                    value="{{ old('disbursement_date', $disbursement?->disbursement_date?->format('d/m/Y') ?? now()->format('d/m/Y')) }}"
                                    placeholder="dd/mm/yyyy" autocomplete="off">
                            </div>

                            <div class="ld-field">
                                <label class="lbl" for="ldAccount">Loan Account Number <span class="ld-req">*</span></label>
                                <input type="text" name="bank_account_number" id="ldAccount" class="input"
                                    value="{{ old('bank_account_number', $disbursement?->bank_account_number) }}">
                            </div>
                        </div>

                        {{-- Cheque sub-section ===== --}}
                        <div class="ld-cheque-wrap" id="ldChequeWrap" style="display:none;">
                            <div class="ld-cheque-hd">
                                <span class="ld-cheque-title">Cheques</span>
                                <button type="button" id="addCheque" class="btn primary sm">
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                                    Add Cheque
                                </button>
                            </div>

                            <div id="chequeList">
                                @php $existingCheques = old('cheques', $disbursement?->cheques ?? []); @endphp
                                @foreach ($existingCheques as $i => $chq)
                                    <div class="ld-cheque-row cheque-row">
                                        <div class="ld-cheque-field">
                                            <label class="lbl lbl-sm">Name</label>
                                            <input type="text" name="cheques[{{ $i }}][cheque_name]" class="input" placeholder="Name" value="{{ $chq['cheque_name'] ?? '' }}">
                                        </div>
                                        <div class="ld-cheque-field">
                                            <label class="lbl lbl-sm">Cheque No.</label>
                                            <input type="text" name="cheques[{{ $i }}][cheque_number]" class="input" placeholder="Cheque Number" value="{{ $chq['cheque_number'] ?? '' }}">
                                        </div>
                                        <div class="ld-cheque-field">
                                            <label class="lbl lbl-sm">Date</label>
                                            <input type="text" name="cheques[{{ $i }}][cheque_date]" class="input shf-datepicker-custom"
                                                data-min-date="{{ $disbursement?->created_at ? $disbursement->created_at->format('d/m/Y') : now()->subDays(30)->format('d/m/Y') }}"
                                                data-max-date="{{ $disbursement?->created_at ? now()->format('d/m/Y') : now()->addDays(30)->format('d/m/Y') }}"
                                                placeholder="dd/mm/yyyy" value="{{ $chq['cheque_date'] ?? '' }}">
                                        </div>
                                        <div class="ld-cheque-field">
                                            <label class="lbl lbl-sm">Amount</label>
                                            <div class="ld-amount ld-amount-sm">
                                                <span class="ld-rupee">₹</span>
                                                <input type="text" class="input shf-amount-input ld-amount-input cheque-amount-display" value="{{ $chq['cheque_amount'] ?? '' }}">
                                                <input type="hidden" name="cheques[{{ $i }}][cheque_amount]" class="shf-amount-raw cheque-amount" value="{{ $chq['cheque_amount'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="ld-cheque-remove">
                                            <button type="button" class="btn danger sm remove-cheque" aria-label="Remove cheque">
                                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="ld-cheque-total">
                                <span>Cheque Total:</span>
                                <strong id="chequeTotal">₹ 0</strong>
                                <span class="ld-warning ld-warning-inline" id="chequeTotalError" style="display:none;"></span>
                            </div>
                            <div class="ld-warning" id="chequeValidationError" style="display:none;"></div>
                        </div>

                        <div class="ld-field">
                            <label class="lbl" for="ldNotes">Notes</label>
                            <textarea name="notes" id="ldNotes" class="input ld-textarea" rows="3">{{ old('notes', $disbursement?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="ld-actions">
                <a href="{{ route('loans.stages', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    {{ $isLocked ? 'Back' : 'Cancel' }}
                </a>
                @if (! $isLocked)
                    <button type="submit" class="btn primary" id="ldSubmit">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Process Disbursement
                    </button>
                @endif
            </div>
        </form>
    </main>
@endsection

@push('page-scripts')
<script>
$(function() {
    // Datepickers
    $('.shf-datepicker-custom').each(function() {
        var opts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, clearBtn: true };
        if ($(this).data('min-date')) opts.startDate = $(this).data('min-date');
        if ($(this).data('max-date')) opts.endDate = $(this).data('max-date');
        $(this).datepicker(opts);
    });

    var chequeIndex = {{ count($existingCheques ?? []) }};

    function toggleMethodCard() {
        var selected = $('input[name="disbursement_type"]:checked').val();
        $('.ld-method-card').removeClass('is-selected');
        $('.ld-method-card[data-type="' + selected + '"]').addClass('is-selected');
        $('#ldChequeWrap').toggle(selected === 'cheque');
    }
    $('input[name="disbursement_type"]').on('change', toggleMethodCard);
    toggleMethodCard();

    function chequeRowHtml(idx) {
        return '<div class="ld-cheque-row cheque-row">' +
            '<div class="ld-cheque-field"><label class="lbl lbl-sm">Name</label>' +
                '<input type="text" name="cheques[' + idx + '][cheque_name]" class="input" placeholder="Name"></div>' +
            '<div class="ld-cheque-field"><label class="lbl lbl-sm">Cheque No.</label>' +
                '<input type="text" name="cheques[' + idx + '][cheque_number]" class="input" placeholder="Cheque Number"></div>' +
            '<div class="ld-cheque-field"><label class="lbl lbl-sm">Date</label>' +
                '<input type="text" name="cheques[' + idx + '][cheque_date]" class="input shf-datepicker-custom" ' +
                    'data-min-date="{{ now()->subDays(30)->format('d/m/Y') }}" data-max-date="{{ now()->addDays(30)->format('d/m/Y') }}" placeholder="dd/mm/yyyy"></div>' +
            '<div class="ld-cheque-field"><label class="lbl lbl-sm">Amount</label>' +
                '<div class="ld-amount ld-amount-sm"><span class="ld-rupee">₹</span>' +
                    '<input type="text" class="input shf-amount-input ld-amount-input cheque-amount-display">' +
                    '<input type="hidden" name="cheques[' + idx + '][cheque_amount]" class="shf-amount-raw cheque-amount">' +
                '</div></div>' +
            '<div class="ld-cheque-remove">' +
                '<button type="button" class="btn danger sm remove-cheque" aria-label="Remove cheque">' +
                    '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                '</button></div>' +
        '</div>';
    }

    $('#addCheque').on('click', function() {
        $('#chequeList').append(chequeRowHtml(chequeIndex));
        var $row = $('#chequeList .cheque-row:last');
        $row.find('.shf-datepicker-custom').each(function() {
            var opts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, clearBtn: true };
            if ($(this).data('min-date')) opts.startDate = $(this).data('min-date');
            if ($(this).data('max-date')) opts.endDate = $(this).data('max-date');
            $(this).datepicker(opts);
        });
        if (window.SHF && typeof SHF.initAmountFields === 'function') { SHF.initAmountFields(); }
        chequeIndex++;
        updateChequeTotal();
    });

    $(document).on('click', '.remove-cheque', function() {
        $(this).closest('.cheque-row').remove();
        updateChequeTotal();
    });

    function updateChequeTotal() {
        var total = 0;
        $('.cheque-amount').each(function() { total += parseFloat($(this).val()) || 0; });
        $('#chequeTotal').text('₹ ' + total.toLocaleString('en-IN'));
        var disbAmt = parseFloat($('[name="amount_disbursed"]').val()) || 0;
        var maxAmount = parseFloat($('#disbAmount').data('max-amount')) || 0;
        var $err = $('#chequeTotalError');
        if (total > disbAmt) {
            $err.text(' (exceeds disbursement amount!)').show().removeClass('is-warning').addClass('is-danger');
        } else if (maxAmount && total > maxAmount) {
            $err.text(' (exceeds sanctioned amount)').show().removeClass('is-danger').addClass('is-warning');
        } else {
            $err.hide();
        }
    }
    $(document).on('input', '.cheque-amount-display', updateChequeTotal);
    $('#disbAmount').on('input', function() { updateChequeTotal(); validateDisbursementAmount(); });
    updateChequeTotal();

    function validateDisbursementAmount() {
        var maxAmount = parseFloat($('#disbAmount').data('max-amount')) || 0;
        if (!maxAmount) return;
        var amount = parseFloat($('[name="amount_disbursed"]').val()) || 0;
        var $err = $('#amountExceedError');
        if (amount > maxAmount) {
            $err.text('Warning: Amount exceeds sanctioned amount (₹ ' + maxAmount.toLocaleString('en-IN') + ').').show();
        } else {
            $err.hide();
        }
    }
    validateDisbursementAmount();

    $(document).on('input change', '.is-invalid', function() { $(this).removeClass('is-invalid'); });

    $('#ldForm').on('submit', function(e) {
        var valid = true;
        var $first = null;

        function fail($input) {
            $input.addClass('is-invalid');
            if (!$first) $first = $input;
            valid = false;
        }

        var amount = parseFloat($('[name="amount_disbursed"]').val()) || 0;
        if (amount <= 0) fail($('#disbAmount'));
        if (!$('[name="disbursement_date"]').val().trim()) fail($('[name="disbursement_date"]'));
        if (!$('[name="bank_account_number"]').val().trim()) fail($('[name="bank_account_number"]'));

        var type = $('input[name="disbursement_type"]:checked').val();
        var $cvErr = $('#chequeValidationError').hide();
        if (type === 'cheque') {
            var $rows = $('.cheque-row');
            if (!$rows.length) {
                $cvErr.text('Please add at least one cheque entry.').show();
                if (!$first) $first = $('#addCheque');
                valid = false;
            } else {
                var total = 0;
                $rows.each(function() {
                    var $row = $(this);
                    ['[name$="[cheque_name]"]', '[name$="[cheque_number]"]', '[name$="[cheque_date]"]'].forEach(function(sel) {
                        var $f = $row.find(sel);
                        if (!($f.val() || '').trim()) fail($f);
                    });
                    var amt = parseFloat($row.find('.cheque-amount').val()) || 0;
                    if (amt <= 0) fail($row.find('.cheque-amount-display'));
                    total += amt;
                });
                var maxDisb = parseFloat($('[name="amount_disbursed"]').val()) || 0;
                if (total > maxDisb) {
                    $cvErr.text('Cheque total (₹ ' + total.toLocaleString('en-IN') + ') exceeds disbursement amount.').show();
                    valid = false;
                }
            }
        }

        if (!valid) {
            e.preventDefault();
            if ($first && $first.length) {
                $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $first.trigger('focus');
            }
        }
    });
});
</script>
@endpush
