@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Payout Report · SHF World')

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
                    <span>Payout Report</span>
                </div>
                <h1>Payout Report</h1>
            </div>
            <div class="head-actions">
                <a class="btn" href="{{ route('payouts.reconcile') }}">Bank Reconciliation</a>
                <a class="btn primary"
                    href="{{ route('payouts.report', array_merge($filters, ['from' => $from, 'to' => $to, 'export' => 'xlsx'])) }}">Export</a>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="card">
            <div class="card-bd">
                <form method="GET" class="po-filters">
                    <div class="field">
                        <label class="lbl">From</label>
                        <input type="text" name="from" class="input po-date" value="{{ $from }}"
                            placeholder="yyyy-mm-dd" autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="lbl">To</label>
                        <input type="text" name="to" class="input po-date" value="{{ $to }}"
                            placeholder="yyyy-mm-dd" autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="lbl">Bank</label>
                        <select name="bank_id" class="select">
                            <option value="">All Banks</option>
                            @foreach ($banks as $b)
                                <option value="{{ $b->id }}" @selected(($filters['bank_id'] ?? '') == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="po-btns">
                        <button type="submit" class="btn primary">Filter</button>
                        <a href="{{ route('payouts.report') }}" class="btn ghost">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="ld-summary">
            <div class="ld-summary-box is-green">
                <div class="k">Net Payout ({{ $from }} – {{ $to }})</div>
                <div class="v">₹ {{ inr($total) }}</div>
            </div>
            <div class="ld-summary-box is-blue">
                <div class="k">Payout Users</div>
                <div class="v">{{ $byUser->count() }}</div>
            </div>
            <div class="ld-summary-box">
                <div class="k">Finalized Payouts</div>
                <div class="v">{{ $payouts->count() }}</div>
            </div>
        </div>

        @if ($byUser->isNotEmpty())
            <div class="card">
                <div class="card-bd">
                    <h3 class="po-sub">By User</h3>
                    <div class="po-scroll">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th class="po-num">Payouts</th>
                                    <th class="po-num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byUser as $u)
                                    <tr>
                                        <td>{{ $u['name'] }}</td>
                                        <td class="po-num">{{ $u['count'] }}</td>
                                        <td class="po-num"><strong>₹ {{ inr($u['total']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-bd po-scroll">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Loan #</th>
                            <th>Bank / Product</th>
                            <th class="po-num">Basis</th>
                            <th class="po-num">Commission</th>
                            <th class="po-num">+ Insurance</th>
                            <th class="po-num">− GST</th>
                            <th class="po-num">− TDS</th>
                            <th class="po-num">Net</th>
                            <th>Cycle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payouts as $p)
                            <tr>
                                <td>{{ optional($p->finalized_at)->format('d/m/Y') }}</td>
                                <td>{{ $p->payoutUser?->name ?? '—' }}</td>
                                <td><a href="{{ route('loans.show', $p->loan_id) }}">{{ $p->loan?->loan_number }}</a></td>
                                <td>{{ $p->loan?->bank?->name }} <span
                                        class="ls-muted">/ {{ $p->loan?->product?->name }}</span></td>
                                <td class="po-num">₹ {{ inr($p->basis_amount) }}</td>
                                <td class="po-num">₹ {{ inr($p->payout_amount) }}</td>
                                <td class="po-num">₹ {{ inr($p->insurance_payout_amount) }}</td>
                                <td class="po-num">₹ {{ inr($p->gst_amount) }}</td>
                                <td class="po-num">₹ {{ inr($p->tds_amount) }}</td>
                                <td class="po-num"><strong>₹ {{ inr($p->net_payout_amount) }}</strong></td>
                                <td>{{ optional($p->cycle_start)->format('d/m') }}–{{ optional($p->cycle_end)->format('d/m') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="po-empty">No finalized payouts in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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
