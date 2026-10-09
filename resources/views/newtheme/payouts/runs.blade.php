@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Payout Runs · SHF World')

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
                    <span>Payout Runs</span>
                </div>
                <h1>Payout Runs</h1>
            </div>
            <div class="head-actions">
                <a class="btn" href="{{ route('payouts.reconcile') }}">Reconcile</a>
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
                    Pick a date range to calculate payouts. The product-wide disbursed volume in the range picks each
                    product's slab tier; each user earns their disbursed volume × that rate (PF-based products pay on
                    PF). Only tranches not yet covered by a run are counted. Then <strong>Finalize</strong> to lock the
                    run (it stamps those tranches paid and snapshots the rates for the record).
                </div>
                <form method="GET" action="{{ route('payouts.runs') }}" class="po-filters">
                    <div class="field">
                        <label class="lbl">From <span class="ld-req">*</span></label>
                        <input type="text" name="from" id="prFrom" class="input po-date"
                            value="{{ $input['from'] ?? '' }}" placeholder="yyyy-mm-dd" autocomplete="off" required>
                    </div>
                    <div class="field">
                        <label class="lbl">To <span class="ld-req">*</span></label>
                        <input type="text" name="to" id="prTo" class="input po-date"
                            value="{{ $input['to'] ?? '' }}" placeholder="yyyy-mm-dd" autocomplete="off" required>
                    </div>
                    <div class="po-btns">
                        <button type="submit" class="btn primary">Calculate</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($preview)
            @if (!empty($preview['errors']))
                <div class="card ld-alert ld-alert-amber"><div class="card-bd">
                    <strong>{{ count($preview['errors']) }} loan(s) skipped:</strong>
                    <ul class="mb-0 mt-1">@foreach (array_slice($preview['errors'], 0, 8) as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div></div>
            @endif

            <div class="ld-summary">
                <div class="ld-summary-box is-blue"><div class="k">Gross Payout</div><div class="v">₹ {{ inr($preview['totals']['gross']) }}</div></div>
                <div class="ld-summary-box"><div class="k">TDS ({{ rtrim(rtrim(number_format($preview['rates']['tds'] * 100, 2), '0'), '.') }}%)</div><div class="v">₹ {{ inr($preview['totals']['tds']) }}</div></div>
                <div class="ld-summary-box is-green"><div class="k">Net Payable</div><div class="v">₹ {{ inr($preview['totals']['net']) }}</div></div>
                <div class="ld-summary-box"><div class="k">Users</div><div class="v">{{ count($preview['users']) }}</div></div>
            </div>

            @if ($canFinalize && $preview['can_finalize'])
                <form method="POST" action="{{ route('payouts.runs.finalize') }}" style="margin:0 0 14px;"
                    onsubmit="return confirm('Finalize this payout run ({{ $preview['from'] }} → {{ $preview['to'] }})? ₹ {{ inr($preview['totals']['net']) }} net will be locked and the covered tranches marked paid.');">
                    @csrf
                    <input type="hidden" name="from" value="{{ $preview['from'] }}">
                    <input type="hidden" name="to" value="{{ $preview['to'] }}">
                    <button type="submit" class="btn primary">Finalize Run</button>
                </form>
            @elseif (!$preview['can_finalize'])
                <div class="card ld-alert ld-alert-amber"><div class="card-bd">Nothing to pay in this range (no uncovered disbursed tranches).</div></div>
            @endif

            @if ($preview['can_finalize'])
                {{-- Per-product tiers --}}
                <div class="card">
                    <div class="card-hd"><div class="t">Product tiers (volume → rate)</div></div>
                    <div class="card-bd" style="overflow-x:auto;">
                        <table class="tbl" style="width:100%;">
                            <thead><tr>
                                <th>Product</th><th>Type</th><th class="text-end">Aggregate base</th>
                                <th>Slab</th><th class="text-end">Rate</th><th class="text-end">Max payout</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($preview['products'] as $p)
                                    <tr>
                                        <td>{{ $p['product_name'] }}</td>
                                        <td>{{ $p['is_pf_based'] ? 'PF-based' : 'Volume' }}</td>
                                        <td class="text-end">₹ {{ inr($p['aggregate_base']) }}</td>
                                        <td>{{ $p['slab_low'] !== null ? '₹ '.inr($p['slab_low']).' – ₹ '.inr($p['slab_high']) : '—' }}</td>
                                        <td class="text-end">{{ $p['rate_type'] === 'percent' ? rtrim(rtrim(number_format($p['rate_value'], 2), '0'), '.').'%' : '₹ '.inr($p['rate_value']) }}</td>
                                        <td class="text-end">{{ $p['max_payout'] === null || $p['max_payout'] < 0 ? '—' : '₹ '.inr($p['max_payout']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Per-user payable --}}
                <div class="card">
                    <div class="card-hd"><div class="t">Per-user payout</div></div>
                    <div class="card-bd" style="overflow-x:auto;">
                        <table class="tbl" style="width:100%;">
                            <thead><tr>
                                <th>User</th><th class="text-end">Commission</th><th class="text-end">PF payout</th>
                                <th class="text-end">Insurance</th><th class="text-end">Total</th>
                                <th class="text-end">TDS</th><th class="text-end">Net</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($preview['users'] as $u)
                                    <tr>
                                        <td>{{ $preview['user_names'][$u['payout_user_id']] ?? ('#'.$u['payout_user_id']) }}
                                            @if ($u['role_context'] === 'connector') <span class="shf-badge shf-badge-purple shf-text-2xs">connector</span>@endif
                                        </td>
                                        <td class="text-end">₹ {{ inr($u['total_commission']) }}</td>
                                        <td class="text-end">₹ {{ inr($u['total_pf_payout']) }}</td>
                                        <td class="text-end">₹ {{ inr($u['total_insurance_payout']) }}</td>
                                        <td class="text-end">₹ {{ inr($u['total_payout']) }}</td>
                                        <td class="text-end">₹ {{ inr($u['tds_amount']) }}</td>
                                        <td class="text-end"><strong>₹ {{ inr($u['net_payout']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        {{-- Finalized runs history --}}
        <div class="card">
            <div class="card-hd"><div class="t">Finalized runs</div></div>
            <div class="card-bd" style="overflow-x:auto;">
                <table class="tbl" style="width:100%;">
                    <thead><tr><th>#</th><th>Range</th><th class="text-end">Gross</th><th class="text-end">TDS</th><th class="text-end">Net</th><th>Finalized</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr>
                                <td>{{ $run->id }}</td>
                                <td>{{ $run->from_date->format('d/m/Y') }} – {{ $run->to_date->format('d/m/Y') }}</td>
                                <td class="text-end">₹ {{ inr($run->total_gross) }}</td>
                                <td class="text-end">₹ {{ inr($run->total_tds) }}</td>
                                <td class="text-end"><strong>₹ {{ inr($run->total_net) }}</strong></td>
                                <td>{{ optional($run->finalized_at)->format('d/m/Y') }} · {{ $run->finalizedBy?->name ?? '—' }}</td>
                                <td class="text-end"><a class="btn btn-accent-sm" href="{{ route('payouts.runs.show', $run) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted" style="padding:14px;">No finalized runs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection

@push('page-scripts')
    <script>
        $(function () {
            $('.po-date').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true, clearBtn: true });
        });
    </script>
@endpush
