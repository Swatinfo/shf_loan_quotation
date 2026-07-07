@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Disbursement — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-disbursement.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    @php
        // Rows for the JS renderer: old() input wins (validation failure), else saved entries.
        $entryRows = old('entries');
        if (! is_array($entryRows)) {
            $entryRows = array_map(function (array $e): array {
                $e['disbursement_date'] = ! empty($e['disbursement_date'])
                    ? \Carbon\Carbon::parse($e['disbursement_date'])->format('d/m/Y')
                    : '';

                return $e;
            }, $entries);
        }
        $entryRows = array_values($entryRows);
        $remaining = max(0, $target - $disbursedSoFar);
        $canMarkComplete = ! $isLocked && $disbursement && ! empty($entries) && $disbursedSoFar < $target;
    @endphp

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
                        <span class="badge red" style="margin-left:6px;vertical-align:middle;">{{ $stageCompleted && $loan->status === 'active' ? 'Completed' : ucfirst($loan->status) }}</span>
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
        @if (session('success'))
            <div class="card ld-alert ld-alert-green">
                <div class="card-bd">{{ session('success') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="card ld-alert ld-alert-red">
                <div class="card-bd">
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($isLocked)
            <div class="card ld-alert ld-alert-{{ $loan->status === 'rejected' ? 'red' : ($loan->status === 'cancelled' ? 'amber' : 'green') }}">
                <div class="card-bd">
                    @if ($stageCompleted)
                        <strong>Disbursement completed.</strong> Entries are read-only.
                    @else
                        <strong>Loan {{ ucfirst($loan->status) }}.</strong> Details are read-only.
                    @endif
                </div>
            </div>
        @endif

        {{-- ===== Target / Disbursed / Remaining ===== --}}
        <div class="ld-summary">
            <div class="ld-summary-box is-blue">
                <div class="k">{{ $sanctionedAmount ? 'Sanctioned Amount' : 'Loan Amount (no sanction figure)' }}</div>
                <div class="v">₹ {{ number_format($target) }}</div>
            </div>
            <div class="ld-summary-box is-green">
                <div class="k">Disbursed So Far</div>
                <div class="v">₹ {{ number_format($disbursedSoFar) }}</div>
            </div>
            <div class="ld-summary-box">
                <div class="k">Remaining</div>
                <div class="v">₹ {{ number_format($remaining) }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('loans.disbursement.store', $loan) }}" id="ldForm" autocomplete="off">
            @csrf
            <fieldset {{ $isLocked ? 'disabled' : '' }} class="ld-fieldset">

                {{-- ===== Disbursement Entries ===== --}}
                <div class="card ld-card">
                    <div class="card-hd">
                        <div class="t"><span class="num">1</span>Disbursement Entries / હપ્તા</div>
                        @if (! $isLocked)
                            <button type="button" id="addEntry" class="btn primary sm">
                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                                Add Entry
                            </button>
                        @endif
                    </div>
                    <div class="card-bd">
                        @if (! $isLocked)
                            <div class="ld-info">
                                Each entry is one tranche. The disbursement stage completes automatically once the
                                total reaches <strong>₹ {{ number_format($target) }}</strong>. Partial totals can be
                                saved now and more entries added later.
                            </div>
                        @endif

                        <div id="entryList"></div>
                        <div class="ld-warning" id="entryValidationError" style="display:none;"></div>

                        <div class="ld-entry-total">
                            <span>Total:</span>
                            <strong id="entryTotal">₹ 0</strong>
                            <span class="ld-warning ld-warning-inline" id="entryTotalNote" style="display:none;"></span>
                        </div>
                        <div class="ld-words" data-total-words></div>
                    </div>
                </div>

                {{-- ===== Notes ===== --}}
                <div class="card ld-card">
                    <div class="card-hd"><div class="t"><span class="num">2</span>Notes</div></div>
                    <div class="card-bd">
                        <div class="ld-field">
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
                @if ($canMarkComplete)
                    <button type="button" class="btn sm" id="ldMarkComplete">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Mark as Fully Disbursed
                    </button>
                @endif
                @if (! $isLocked)
                    <button type="submit" class="btn primary" id="ldSubmit">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Save Disbursement
                    </button>
                @endif
            </div>
        </form>

        @if ($canMarkComplete)
            <form method="POST" action="{{ route('loans.disbursement.complete', $loan) }}" id="ldCompleteForm" class="d-none">
                @csrf
            </form>
        @endif
    </main>
@endsection

@push('page-scripts')
{{-- SHF.initAmountFields lives in shf-app.js (the global newtheme runtime
     omits it deliberately). The inline script below depends on it for both
     the page-load sweep over `.shf-amount-input` fields and the call after
     a new entry row is appended. Without this, the visible amount inputs
     never sync into their hidden `.shf-amount-raw` siblings, and the form
     posts empty `entries[N][amount]` values that fail server-side validation. --}}
<script src="{{ asset('newtheme/js/shf-app.js') }}?v={{ config('app.shf_version') }}"></script>
@php
    $ldConfig = [
        'products' => $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
        'entries' => $entryRows,
        'target' => (int) $target,
        'locked' => (bool) $isLocked,
        'entryDateMin' => $loan->created_at->format('d/m/Y'),
        'entryDateMax' => now()->addDays(3)->format('d/m/Y'),
        'chequeDateMin' => $loan->created_at->format('d/m/Y'),
        'chequeDateMax' => now()->addDays(90)->format('d/m/Y'),
    ];
@endphp
<script>
$(function() {
    var CFG = @json($ldConfig);
    var entryIndex = 0;

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function productOptions(selectedId) {
        var html = '<option value="">Select Product...</option>';
        CFG.products.forEach(function(p) {
            html += '<option value="' + p.id + '"' + (String(p.id) === String(selectedId) ? ' selected' : '') + '>' + esc(p.name) + '</option>';
        });
        return html;
    }

    function entryRowHtml(idx, data) {
        data = data || {};
        var method = data.method || 'fund_transfer';
        return '<div class="ld-entry-row entry-row" data-index="' + idx + '">' +
            '<input type="hidden" name="entries[' + idx + '][row_id]" value="' + esc(data.row_id || '') + '">' +
            '<div class="ld-entry-grid">' +
                '<div class="ld-field"><label class="lbl lbl-sm"><span class="ld-entry-num entry-num"></span>Date <span class="ld-req">*</span></label>' +
                    '<input type="text" name="entries[' + idx + '][disbursement_date]" class="input shf-datepicker-custom entry-date" ' +
                        'data-min-date="' + CFG.entryDateMin + '" data-max-date="' + CFG.entryDateMax + '" placeholder="dd/mm/yyyy" value="' + esc(data.disbursement_date || '') + '"></div>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Method <span class="ld-req">*</span></label>' +
                    '<select name="entries[' + idx + '][method]" class="input entry-method">' +
                        '<option value="fund_transfer"' + (method === 'fund_transfer' ? ' selected' : '') + '>Fund Transfer (NEFT/RTGS)</option>' +
                        '<option value="cheque"' + (method === 'cheque' ? ' selected' : '') + '>Cheque</option>' +
                    '</select></div>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Product <span class="ld-req">*</span></label>' +
                    '<select name="entries[' + idx + '][product_id]" class="input entry-product">' + productOptions(data.product_id) + '</select></div>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Loan A/c Number <span class="ld-req">*</span></label>' +
                    '<input type="text" name="entries[' + idx + '][loan_account_number]" class="input entry-account" placeholder="Loan Account Number" value="' + esc(data.loan_account_number || '') + '"></div>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Amount <span class="ld-req">*</span></label>' +
                    '<div class="ld-amount ld-amount-sm shf-amount-wrap"><span class="ld-rupee">₹</span>' +
                        '<input type="text" class="input shf-amount-input ld-amount-input entry-amount-display" value="' + esc(data.amount || '') + '">' +
                        '<input type="hidden" name="entries[' + idx + '][amount]" class="shf-amount-raw entry-amount" value="' + esc(data.amount || '') + '">' +
                    '</div></div>' +
                '<div class="ld-entry-remove">' +
                    '<button type="button" class="btn danger sm remove-entry" aria-label="Remove entry">' +
                        '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                    '</button></div>' +
            '</div>' +
            '<div class="ld-entry-cheque entry-cheque"' + (method === 'cheque' ? '' : ' style="display:none;"') + '>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Name on Cheque <span class="ld-req">*</span></label>' +
                    '<input type="text" name="entries[' + idx + '][cheque_name]" class="input entry-cheque-name" placeholder="Name" value="' + esc(data.cheque_name || '') + '"></div>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Cheque No. <span class="ld-req">*</span></label>' +
                    '<input type="text" name="entries[' + idx + '][cheque_number]" class="input entry-cheque-number" placeholder="Cheque Number" value="' + esc(data.cheque_number || '') + '"></div>' +
                '<div class="ld-field"><label class="lbl lbl-sm">Cheque Date <span class="ld-req">*</span></label>' +
                    '<input type="text" name="entries[' + idx + '][cheque_date]" class="input shf-datepicker-custom entry-cheque-date" ' +
                        'data-min-date="' + CFG.chequeDateMin + '" data-max-date="' + CFG.chequeDateMax + '" placeholder="dd/mm/yyyy" value="' + esc(data.cheque_date || '') + '"></div>' +
            '</div>' +
        '</div>';
    }

    function initRow($row) {
        $row.find('.shf-datepicker-custom').each(function() {
            var opts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, clearBtn: true };
            if ($(this).data('min-date')) opts.startDate = $(this).data('min-date');
            if ($(this).data('max-date')) opts.endDate = $(this).data('max-date');
            $(this).datepicker(opts);
        });
        if (window.SHF && typeof SHF.initAmountFields === 'function') { SHF.initAmountFields(); }
    }

    function renumberRows() {
        $('#entryList .entry-row').each(function(i) {
            $(this).find('.entry-num').text(i + 1);
        });
    }

    function addEntry(data) {
        $('#entryList').append(entryRowHtml(entryIndex, data));
        initRow($('#entryList .entry-row:last'));
        entryIndex++;
        renumberRows();
        updateTotal();
    }

    // Hydrate saved / old-input rows; start with one empty row on a fresh form.
    if (CFG.entries.length) {
        CFG.entries.forEach(function(e) { addEntry(e); });
    } else if (!CFG.locked) {
        addEntry();
    }

    $('#addEntry').on('click', function() {
        addEntry({ disbursement_date: '{{ now()->format('d/m/Y') }}' });
    });

    $(document).on('click', '.remove-entry', function() {
        $(this).closest('.entry-row').remove();
        renumberRows();
        updateTotal();
    });

    $(document).on('change', '.entry-method', function() {
        var $row = $(this).closest('.entry-row');
        $row.find('.entry-cheque').toggle($(this).val() === 'cheque');
    });

    function updateTotal() {
        var total = 0;
        $('.entry-amount').each(function() { total += parseFloat($(this).val()) || 0; });
        $('#entryTotal').text('₹ ' + total.toLocaleString('en-IN'));
        if (window.SHF && typeof SHF.bilingualAmountWords === 'function') {
            $('[data-total-words]').text(total > 0 ? SHF.bilingualAmountWords(total) : '');
        }
        var $note = $('#entryTotalNote');
        if (total > CFG.target) {
            $note.text('(exceeds ₹ ' + CFG.target.toLocaleString('en-IN') + ' — stage will complete on save)').show().removeClass('is-danger').addClass('is-warning');
        } else if (total > 0 && total >= CFG.target) {
            $note.text('(stage will complete on save)').show().removeClass('is-danger').addClass('is-warning');
        } else if (total > 0) {
            $note.text('(remaining ₹ ' + (CFG.target - total).toLocaleString('en-IN') + ' — you can add more entries later)').show().removeClass('is-danger is-warning');
        } else {
            $note.hide();
        }
    }
    $(document).on('input', '.entry-amount-display', updateTotal);
    updateTotal();

    $(document).on('input change', '.is-invalid', function() { $(this).removeClass('is-invalid'); });

    $('#ldForm').on('submit', function(e) {
        var valid = true;
        var $first = null;

        function fail($input) {
            $input.addClass('is-invalid');
            if (!$first) $first = $input;
            valid = false;
        }

        var $rows = $('#entryList .entry-row');
        var $err = $('#entryValidationError').hide();
        if (!$rows.length) {
            $err.text('Please add at least one disbursement entry.').show();
            if (!$first) $first = $('#addEntry');
            valid = false;
        }

        $rows.each(function() {
            var $row = $(this);
            ['.entry-date', '.entry-method', '.entry-product', '.entry-account'].forEach(function(sel) {
                var $f = $row.find(sel);
                if (!($f.val() || '').trim()) fail($f);
            });
            var amt = parseFloat($row.find('.entry-amount').val()) || 0;
            if (amt <= 0) fail($row.find('.entry-amount-display'));

            if ($row.find('.entry-method').val() === 'cheque') {
                ['.entry-cheque-name', '.entry-cheque-number', '.entry-cheque-date'].forEach(function(sel) {
                    var $f = $row.find(sel);
                    if (!($f.val() || '').trim()) fail($f);
                });
            }
        });

        if (!valid) {
            e.preventDefault();
            if ($first && $first.length) {
                $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $first.trigger('focus');
            }
        }
    });

    $('#ldMarkComplete').on('click', function() {
        Swal.fire({
            title: 'Mark as Fully Disbursed?',
            html: 'The saved total is below <strong>₹ ' + CFG.target.toLocaleString('en-IN') + '</strong>.<br>' +
                'This completes the disbursement stage — no more entries can be added.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Complete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#f15a29',
            cancelButtonColor: '#6c757d'
        }).then(function(result) {
            if (result.isConfirmed) { $('#ldCompleteForm').trigger('submit'); }
        });
    });
});
</script>
@endpush
