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
                    <div class="k">Matched</div>
                    <div class="v">{{ count($result['matched']) }}</div>
                </div>
                <div class="ld-summary-box">
                    <div class="k">In DB, not in Excel</div>
                    <div class="v">{{ count($result['db_only']) }}</div>
                </div>
                <div class="ld-summary-box">
                    <div class="k">In Excel, not in DB</div>
                    <div class="v">{{ count($result['excel_only']) }}</div>
                </div>
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

            {{-- Matched — full breakdown (mirrors the upload-Excel sheet) --}}
            <div class="card">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">✅ Matched ({{ count($result['matched']) }})</h3>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>User</th><th>Product</th><th>Loan A/c</th><th>Customer</th><th>Cheque No</th><th>Date</th>
                                <th class="po-num">Loan Amt</th><th class="po-num">PF</th><th class="po-num">Admin</th>
                                <th class="po-num">Base</th><th class="po-num">Payout %</th><th class="po-num">Commission</th>
                                <th class="po-num">Insurance</th><th class="po-num">Ins. Payout</th><th class="po-num">− GST</th>
                                <th class="po-num">Total</th><th class="po-num">− TDS</th><th class="po-num">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($result['matched'] as $m)
                                @php $c = $m['calc']; $e = $m['entry']; @endphp
                                <tr @if (!$c['ok']) class="po-row-excel-only" @endif>
                                    <td>{{ $e->loan?->payoutUser?->name ?? '—' }}</td>
                                    <td>{{ $e->loan?->product?->name ?? '—' }}</td>
                                    <td>{{ $e->loan_account_number ?? '—' }}</td>
                                    <td>{{ $e->loan->customer_name ?? '—' }}</td>
                                    <td>{{ $e->cheque_number ?: '—' }}</td>
                                    <td>{{ optional($e->disbursement_date)->format('d/m/Y') }}</td>
                                    <td class="po-num">₹ {{ inr($e->amount) }}</td>
                                    <td class="po-num">₹ {{ inr($c['pf']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['admin']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['base']) }}</td>
                                    <td class="po-num">{{ $c['rate_type'] === 'percent' ? rtrim(rtrim(number_format($c['rate_value'], 2), '0'), '.').'%' : ($c['rate_type'] ? '₹ '.inr($c['rate_value']) : '—') }}</td>
                                    <td class="po-num">₹ {{ inr($c['commission']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['insurance']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['insurance_payout']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['gst']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['gross']) }}</td>
                                    <td class="po-num">₹ {{ inr($c['tds']) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($c['net']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="18" class="po-empty">No matches.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- In DB, not in Excel --}}
            <div class="card po-card-db-only">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">🟡 In our DB, not in Excel ({{ count($result['db_only']) }})</h3>
                    <table class="tbl">
                        <thead><tr><th>Loan A/c</th><th>Customer</th><th>Date</th><th class="po-num">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($result['db_only'] as $e)
                                <tr class="po-row-db-only">
                                    <td>{{ $e->loan_account_number ?? '—' }}</td>
                                    <td>{{ $e->loan->customer_name ?? '—' }}</td>
                                    <td>{{ optional($e->disbursement_date)->format('d/m/Y') }}</td>
                                    <td class="po-num">₹ {{ inr($e->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="po-empty">None — all our entries are in the file.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- In Excel, not in DB --}}
            <div class="card po-card-excel-only">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">🔴 In Excel, not in our DB ({{ count($result['excel_only']) }})</h3>
                    <table class="tbl">
                        <thead><tr><th>Loan A/c</th><th>Customer</th><th>Date</th><th class="po-num">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($result['excel_only'] as $r)
                                <tr class="po-row-excel-only">
                                    <td>{{ $r['loan_acc_no'] ?? '—' }}</td>
                                    <td>{{ $r['customer_name'] ?? '—' }}</td>
                                    <td>{{ $r['_date'] ?? '—' }}</td>
                                    <td class="po-num">₹ {{ inr($r['loan_amount'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="po-empty">None — the file has no extra rows.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== Summary (matched): by Bank/Product + by User ===== --}}
            @php
                $prod = collect($result['by_product']);
                $usr = collect($result['by_user']);
                $sumCols = ['loan', 'pf', 'commission', 'insurance', 'insurance_payout', 'gst', 'gross', 'tds', 'total'];
            @endphp
            <div class="card">
                <div class="card-bd po-scroll">
                    <h3 class="po-sub">Summary — by Bank / Product</h3>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Bank</th><th>Product</th><th class="po-num">Matched</th><th class="po-num">Total Loan</th>
                                <th class="po-num">Total PF</th><th class="po-num">Commission</th><th class="po-num">Insurance</th>
                                <th class="po-num">Ins. Payout</th><th class="po-num">− GST</th><th class="po-num">Total</th>
                                <th class="po-num">− TDS</th><th class="po-num">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($prod as $r)
                                <tr>
                                    <td>{{ $r['bank'] }}</td><td>{{ $r['product'] }}</td><td class="po-num">{{ $r['count'] }}</td>
                                    <td class="po-num">₹ {{ inr($r['loan']) }}</td><td class="po-num">₹ {{ inr($r['pf']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['commission']) }}</td><td class="po-num">₹ {{ inr($r['insurance']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['insurance_payout']) }}</td><td class="po-num">₹ {{ inr($r['gst']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['gross']) }}</td><td class="po-num">₹ {{ inr($r['tds']) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($r['total']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="po-empty">No matched entries.</td></tr>
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
                                <th class="po-num">Total PF</th><th class="po-num">Commission</th><th class="po-num">Insurance</th>
                                <th class="po-num">Ins. Payout</th><th class="po-num">− GST</th><th class="po-num">Total</th>
                                <th class="po-num">− TDS</th><th class="po-num">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($usr as $r)
                                <tr>
                                    <td>{{ $r['user'] }}</td><td class="po-num">{{ $r['count'] }}</td>
                                    <td class="po-num">₹ {{ inr($r['loan']) }}</td><td class="po-num">₹ {{ inr($r['pf']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['commission']) }}</td><td class="po-num">₹ {{ inr($r['insurance']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['insurance_payout']) }}</td><td class="po-num">₹ {{ inr($r['gst']) }}</td>
                                    <td class="po-num">₹ {{ inr($r['gross']) }}</td><td class="po-num">₹ {{ inr($r['tds']) }}</td>
                                    <td class="po-num"><strong>₹ {{ inr($r['total']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="po-empty">No matched entries.</td></tr>
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
            });
        </script>
    @endpush
@endsection
