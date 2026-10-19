@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Payout Reconciliation · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/payouts.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Payout Reconciliation</span>
                </div>
                <h1>Bank Payout Reconciliation</h1>
            </div>
            <div class="head-actions">
                <a class="btn" href="{{ route('payouts.reconcile.template') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Download Template
                </a>
                <a class="btn" href="{{ route('payouts.report') }}">Payout Report</a>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('success'))
            <div class="card ld-alert ld-alert-green"><div class="card-bd">{{ session('success') }}</div></div>
        @endif
        @if (session('error'))
            <div class="card ld-alert ld-alert-red"><div class="card-bd">{{ session('error') }}</div></div>
        @endif
        @if ($errors->any())
            <div class="card ld-alert ld-alert-red"><div class="card-bd">
                <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div></div>
        @endif

        <div class="card">
            <div class="card-bd">
                <div class="po-hint">
                    Download the blank template (top-right) and paste the bank's statement into it, then pick a bank +
                    date range here and upload the filled file to reconcile against our disbursed entries. Match = loan
                    A/c + amount + date (customer name + amount + date for placeholder accounts).
                </div>
                <form method="POST" action="{{ route('payouts.reconcile.run') }}" enctype="multipart/form-data"
                    id="reconcileForm" class="po-filters">
                    @csrf
                    <div class="field">
                        <label class="lbl">Bank <span class="ld-req">*</span></label>
                        <select name="bank_id" id="rcBank" class="select" required>
                            <option value="">Select bank…</option>
                            @foreach ($banks as $b)
                                <option value="{{ $b->id }}" @selected(($input['bank_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('newtheme.payouts._product-filter')
                    <div class="field">
                        <label class="lbl">From <span class="ld-req">*</span></label>
                        <input type="text" name="from" id="rcFrom" class="input po-date"
                            value="{{ $input['from'] ?? '' }}" placeholder="yyyy-mm-dd" autocomplete="off" required>
                    </div>
                    <div class="field">
                        <label class="lbl">To <span class="ld-req">*</span></label>
                        <input type="text" name="to" id="rcTo" class="input po-date"
                            value="{{ $input['to'] ?? '' }}" placeholder="yyyy-mm-dd" autocomplete="off" required>
                    </div>
                    <div class="field">
                        <label class="lbl">Bank statement (.xlsx) <span class="ld-req">*</span></label>
                        <input type="file" name="file" class="input" accept=".xlsx" required>
                    </div>
                    <div class="po-btns">
                        <button type="submit" class="btn primary">Reconcile</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($result)
            <div class="ld-summary">
                <div class="ld-summary-box is-green">
                    <div class="k">Payout to Pay (matched)</div>
                    <div class="v">₹ {{ inr($result['total_payout']) }}</div>
                </div>
                <div class="ld-summary-box is-blue">
                    <div class="k">Matched (loans)</div>
                    <div class="v">{{ count($result['matched']) }}</div>
                </div>
                <div class="ld-summary-box" @if (($result['discrepancy_count'] ?? 0) > 0) style="border-left:4px solid var(--accent,#f15a29);" @endif>
                    <div class="k">⚠ With discrepancy</div>
                    <div class="v">{{ $result['discrepancy_count'] ?? 0 }}</div>
                </div>
                <div class="ld-summary-box">
                    <div class="k">In DB, not in Excel</div>
                    <div class="v">{{ count($result['db_only']) }}</div>
                </div>
                @if (count($result['excel_in_db'] ?? []))
                    <div class="ld-summary-box" style="border-left:4px solid var(--accent,#f15a29);">
                        <div class="k">In DB but mismatch (not payable)</div>
                        <div class="v">{{ count($result['excel_in_db']) }}</div>
                    </div>
                @endif
                <div class="ld-summary-box">
                    <div class="k">Not in DB at all</div>
                    <div class="v">{{ count($result['excel_only']) }}</div>
                </div>
                @if (($result['date_error_count'] ?? 0) > 0)
                    <div class="ld-summary-box" style="border-left:4px solid var(--red,#c0392b);">
                        <div class="k">Unreadable dates</div>
                        <div class="v">{{ $result['date_error_count'] }}</div>
                    </div>
                @endif
            </div>

            @if ($canFinalizePayout)
                {{-- Verification only — the figures above reconcile our disbursed entries
                     against the bank statement. Payouts are finalized product-wide on the
                     Payout Runs screen; this opens it pre-filled with the same date range. --}}
                <form method="GET" action="{{ route('payouts.runs') }}" class="po-actions">
                    <input type="hidden" name="from" value="{{ $input['from'] ?? '' }}">
                    <input type="hidden" name="to" value="{{ $input['to'] ?? '' }}">
                    <button type="submit" class="btn primary">
                        Run Payout for this period →
                    </button>
                    <span class="po-hint" style="margin-left:10px;">Opens Payout Runs for {{ $input['from'] ?? '' }} – {{ $input['to'] ?? '' }} to preview &amp; finalize.</span>
                </form>
            @endif

            {{-- Matched — one row per loan; matched on identifier (A/c → App # → name).
                 Loan Amount / Date / Insurance show the Excel value with our DB value on
                 a second line in the same cell; a mismatch highlights the cell + row. --}}
            @php
                $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
                $diffCell = 'background:#ffe0b2;';
                $dbLine = 'font-size:11px;color:var(--text-muted,#6b7280);';
            @endphp
            <div class="card">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">✅ Matched ({{ count($result['matched']) }}) — <span class="ls-muted" style="font-weight:400;">each cell: Excel value, then <strong>DB:</strong> our value below. Highlighted = mismatch.</span></h3>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>User</th><th>Product</th><th>Loan A/c · Loan # · App #</th><th>Customer</th>
                                <th class="po-num">Loan Amount<br><span class="ls-muted">Excel / DB</span></th>
                                <th>Date<br><span class="ls-muted">Excel / DB</span></th>
                                <th class="po-num">PF</th><th class="po-num">Admin</th>
                                <th class="po-num">Base</th><th class="po-num">Commission</th>
                                <th class="po-num">Insurance<br><span class="ls-muted">Excel / DB</span></th>
                                <th class="po-num">Ins. Payout</th><th class="po-num">− GST</th>
                                <th class="po-num">Total</th><th class="po-num">− TDS</th><th class="po-num">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($result['matched'] as $m)
                                @php $c = $m['calc']; $our = $m['our']; $ex = $m['excel']; $f = $m['flags']; @endphp
                                <tr @if ($m['has_discrepancy']) style="background:#fff8ec;" @endif>
                                    <td>{{ $m['loan']?->payoutUser?->name ?? '—' }}</td>
                                    <td>{{ $m['loan']?->product?->name ?? '—' }}</td>
                                    <td>
                                        {{ !empty($our['accounts']) ? implode(', ', $our['accounts']) : '—' }}
                                        <div style="{{ $dbLine }}">
                                            Loan #: {{ $m['loan']?->loan_number ?? '—' }}<br>
                                            App #: {{ $m['loan']?->application_number ?: '—' }}
                                        </div>
                                    </td>
                                    <td>{{ $m['loan']?->customer_name ?? '—' }}</td>
                                    <td class="po-num" @if ($f['amount']) style="{{ $diffCell }}" @endif>
                                        ₹ {{ inr($ex['_amount']) }}
                                        <br><span style="{{ $dbLine }}">DB: ₹ {{ inr($our['amount']) }}@if ($our['tranche_count'] > 1) ({{ $our['tranche_count'] }} tr.)@endif</span>
                                    </td>
                                    <td @if ($f['date']) style="{{ $diffCell }}" @endif>
                                        @if ($ex['_date_error'])
                                            <span style="color:var(--red,#c0392b);">⚠ {{ $ex['_raw_date'] ?: '—' }}</span>
                                        @else
                                            {{ $fmtDate($ex['_date']) }}
                                        @endif
                                        <br><span style="{{ $dbLine }}">DB: {{ $our['settlement_dates'] ? implode(', ', array_map($fmtDate, $our['settlement_dates'])) : '—' }}</span>
                                    </td>
                                    <td class="po-num">₹ {{ inr($c['pf']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['admin']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['base']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['commission']) }}</td>
                                    <td class="po-num" @if ($f['insurance']) style="{{ $diffCell }}" @endif>
                                        ₹ {{ inr($ex['_insurance'] ?? $our['insurance']) }}
                                        <br><span style="{{ $dbLine }}">DB: ₹ {{ inr($our['insurance']) }}</span>
                                    </td>
                                    <td class="po-num">₹ {{ inr($c['insurance_payout']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['gst']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['gross']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['tds']) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($c['net']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="16" class="po-empty">No matches.</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($result['matched']))
                            @php $M = collect($result['matched']); @endphp
                            <tfoot>
                                <tr style="font-weight:600;background:var(--bg-alt,#f5f6f7);">
                                    <td colspan="4" class="text-end">Totals ({{ $M->count() }} loans)</td>
                                    <td class="po-num">
                                        ₹ {{ inr($M->sum(fn ($m) => $m['excel']['_amount'])) }}
                                        <br><span style="{{ $dbLine }}">DB: ₹ {{ inr($M->sum(fn ($m) => $m['our']['amount'])) }}</span>
                                    </td>
                                    <td></td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['pf'])) }}</td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['admin'])) }}</td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['base'])) }}</td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['commission'])) }}</td>
                                    <td class="po-num">
                                        ₹ {{ inr($M->sum(fn ($m) => $m['excel']['_insurance'] ?? $m['our']['insurance'])) }}
                                        <br><span style="{{ $dbLine }}">DB: ₹ {{ inr($M->sum(fn ($m) => $m['our']['insurance'])) }}</span>
                                    </td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['insurance_payout'])) }}</td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['gst'])) }}</td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['gross'])) }}</td>
                                    <td class="po-num">₹ {{ inr($M->sum(fn ($m) => $m['calc']['tds'])) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($M->sum(fn ($m) => $m['calc']['net'])) }}</strong></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            {{-- In DB, not in Excel (loan grain) --}}
            <div class="card po-card-db-only">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">🟡 In our DB, not in Excel ({{ count($result['db_only']) }})</h3>
                    <table class="tbl">
                        <thead><tr><th>Loan A/c · Loan # · App #</th><th>Product</th><th>Customer</th><th>Settlement date(s)</th><th class="po-num">Our Disbursed</th></tr></thead>
                        <tbody>
                            @forelse ($result['db_only'] as $g)
                                <tr class="po-row-db-only">
                                    <td>
                                        {{ !empty($g['accounts']) ? implode(', ', $g['accounts']) : '—' }}
                                        <div style="{{ $dbLine }}">
                                            Loan #: {{ $g['loan']?->loan_number ?? '—' }}<br>
                                            App #: {{ $g['loan']?->application_number ?: '—' }}
                                        </div>
                                    </td>
                                    <td>{{ $g['loan']?->product?->name ?? '—' }}</td>
                                    <td>{{ $g['loan']?->customer_name ?? '—' }}</td>
                                    <td>{{ $g['settlement_dates'] ? implode(', ', array_map($fmtDate, $g['settlement_dates'])) : '—' }}</td>
                                    <td class="po-num">₹ {{ inr($g['amount']) }}@if ($g['tranche_count'] > 1) <span class="ls-muted">({{ $g['tranche_count'] }} tr.)</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="po-empty">None — every loan is in the file.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- In Excel & in our DB, but didn't match this period (data/period mismatch — not payable) --}}
            @if (count($result['excel_in_db'] ?? []))
                <div class="card">
                    <div class="card-bd po-scroll">
                        <h3 class="po-sub">⚠ In Excel &amp; in our DB — mismatch, not payable ({{ count($result['excel_in_db']) }})
                            <span class="ls-muted" style="font-weight:400;">· the record exists in our DB but didn't settle in this period. The <strong>DB date</strong> is the actual OTC clearance (settlement) date — blank/Pending means the cheque isn't cleared yet. See the Reason column.</span>
                        </h3>
                        <table class="tbl">
                            <thead><tr>
                                <th>Identifier<br><span class="ls-muted">Excel / DB Loan·App·A/c</span></th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Date (bank)<br><span class="ls-muted">Excel / DB cleared</span></th>
                                <th class="po-num">Loan Amount<br><span class="ls-muted">Excel / DB</span></th>
                                <th>Reason</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($result['excel_in_db'] as $m)
                                    @php $r = $m['excel']; $g = $m['loan']; @endphp
                                    <tr style="background:#fff8ec;">
                                        <td>
                                            {{ $r['loan_acc_no'] ?? '—' }}
                                            <div style="{{ $dbLine }}">
                                                DB: {{ $g['loan_number'] ?? '—' }} · {{ $g['application_number'] ?: '—' }}<br>
                                                A/c: {{ !empty($g['accounts']) ? implode(', ', $g['accounts']) : '—' }}
                                            </div>
                                        </td>
                                        <td>{{ $g['product_name'] ?? '—' }}</td>
                                        <td>{{ $r['customer_name'] ?? ($g['customer_name'] ?? '—') }}</td>
                                        <td>
                                            @if ($r['_date_error'])
                                                <span style="color:var(--red,#c0392b);">⚠ {{ $r['_raw_date'] ?: '—' }}</span>
                                            @else
                                                {{ $fmtDate($r['_date']) }}
                                            @endif
                                            <br><span style="{{ $dbLine }}">DB:
                                                @if (!empty($g['settlement_dates']))
                                                    {{ implode(', ', array_map($fmtDate, $g['settlement_dates'])) }}
                                                @elseif (!empty($g['pending']))
                                                    <span style="color:var(--accent,#f15a29);">Pending (not cleared)</span>
                                                @else — @endif
                                            </span>
                                        </td>
                                        <td class="po-num">
                                            ₹ {{ inr($r['_amount'] ?? 0) }}
                                            <br><span style="{{ $dbLine }}">DB: ₹ {{ inr($g['amount'] ?? 0) }}</span>
                                        </td>
                                        <td style="font-size:12px;">
                                            @php
                                                [$catLabel, $catClass] = match ($m['category'] ?? 'other') {
                                                    'difference' => ['Difference', 'shf-badge-orange'],
                                                    'status' => [\App\Models\LoanDetail::STATUS_LABELS[$g['status'] ?? '']['label'] ?? 'Loan status', 'shf-badge-red'],
                                                    'pending' => ['Pending', 'shf-badge-red'],
                                                    'outside' => ['Out of period', 'shf-badge-blue'],
                                                    default => ['Mismatch', 'shf-badge-gray'],
                                                };
                                            @endphp
                                            <span class="shf-badge {{ $catClass }}">{{ $catLabel }}</span>
                                            <div class="ls-muted" style="margin-top:3px;">{{ $m['reason'] ?? '' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- In Excel, genuinely NOT in our DB (identifier found nowhere) --}}
            <div class="card po-card-excel-only">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">🔴 Not in our DB at all ({{ count($result['excel_only']) }})
                        <span class="ls-muted" style="font-weight:400;">· the account/application number appears nowhere in our records for this bank.</span>
                    </h3>
                    <table class="tbl">
                        <thead><tr><th>Identifier (A/c / App #)</th><th>Customer</th><th>Date</th><th class="po-num">Loan Amount</th></tr></thead>
                        <tbody>
                            @forelse ($result['excel_only'] as $r)
                                <tr class="po-row-excel-only">
                                    <td>{{ $r['loan_acc_no'] ?? '—' }}</td>
                                    <td>{{ $r['customer_name'] ?? '—' }}</td>
                                    <td>
                                        @if ($r['_date_error'])
                                            <span style="color:var(--red,#c0392b);">⚠ {{ $r['_raw_date'] ?: '—' }}</span>
                                        @else
                                            {{ $fmtDate($r['_date']) }}
                                        @endif
                                    </td>
                                    <td class="po-num">₹ {{ inr($r['_amount'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="po-empty">None — every bank row exists in our DB.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== Summary (matched): by Bank/Product + by User ===== --}}
            @php
                $prod = collect($result['by_product']);
                $usr = collect($result['by_user']);
                $sumCols = ['loan', 'pf', 'admin', 'commission', 'insurance', 'insurance_payout', 'gst', 'gross', 'tds', 'total'];
            @endphp
            <div class="card">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">Summary — by Bank / Product</h3>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Bank</th><th>Product</th><th class="po-num">Matched</th><th class="po-num">Total Loan</th>
                                <th class="po-num">Total PF</th><th class="po-num">Total Admin</th><th class="po-num">Commission</th><th class="po-num">Insurance</th>
                                <th class="po-num">Ins. Payout</th><th class="po-num">− GST</th><th class="po-num">Total</th>
                                <th class="po-num">− TDS</th><th class="po-num">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($prod as $r)
                                <tr>
                                    <td>{{ $r['bank'] }}</td><td>{{ $r['product'] }}</td><td class="po-num">{{ $r['count'] }}</td>
                                    <td class="po-num">₹ {{ inr($r['loan']) }}</td><td class="po-num">₹ {{ inr($r['pf']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['admin']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['commission']) }}</td><td class="po-num">₹ {{ inr($r['insurance']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['insurance_payout']) }}</td><td class="po-num">₹ {{ inr($r['gst']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['gross']) }}</td><td class="po-num">₹ {{ inr($r['tds']) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($r['total']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="po-empty">No matched entries.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($prod->count())
                            <tfoot><tr>
                                <th colspan="2">Total</th><th class="po-num">{{ $prod->sum('count') }}</th>
                                @foreach ($sumCols as $col)<th class="po-num">₹ {{ inr($prod->sum($col)) }}</th>@endforeach
                            </tr></tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">Summary — by Payout User</h3>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Payout User</th><th class="po-num">Matched</th><th class="po-num">Total Loan</th>
                                <th class="po-num">Total PF</th><th class="po-num">Total Admin</th><th class="po-num">Commission</th><th class="po-num">Insurance</th>
                                <th class="po-num">Ins. Payout</th><th class="po-num">− GST</th><th class="po-num">Total</th>
                                <th class="po-num">− TDS</th><th class="po-num">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($usr as $r)
                                <tr>
                                    <td>{{ $r['user'] }}</td><td class="po-num">{{ $r['count'] }}</td>
                                    <td class="po-num">₹ {{ inr($r['loan']) }}</td><td class="po-num">₹ {{ inr($r['pf']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['admin']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['commission']) }}</td><td class="po-num">₹ {{ inr($r['insurance']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['insurance_payout']) }}</td><td class="po-num">₹ {{ inr($r['gst']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['gross']) }}</td><td class="po-num">₹ {{ inr($r['tds']) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($r['total']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="po-empty">No matched entries.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($usr->count())
                            <tfoot><tr>
                                <th>Total</th><th class="po-num">{{ $usr->sum('count') }}</th>
                                @foreach ($sumCols as $col)<th class="po-num">₹ {{ inr($usr->sum($col)) }}</th>@endforeach
                            </tr></tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endif
    </main>

    @push('page-scripts')
        <script>
            $(function () {
                $('.po-date').datepicker({
                    format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true, clearBtn: true,
                });
                // Picking the start date auto-sets the end date to one month minus a
                // day (16th → 15th of next month), i.e. a full one-month period.
                $('#rcFrom').on('changeDate', function (e) {
                    if (!e.date) { return; }
                    var d = new Date(e.date.getTime());
                    d.setMonth(d.getMonth() + 1);
                    d.setDate(d.getDate() - 1);
                    $('#rcTo').datepicker('setDate', d);
                });
            });
        </script>
    @endpush
@endsection
