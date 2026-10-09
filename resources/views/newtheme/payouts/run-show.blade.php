@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Payout Run #' . $run->id . ' · SHF World')

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
                    <a href="{{ route('payouts.runs') }}">Payout Runs</a>
                    <span class="sep">/</span>
                    <span>#{{ $run->id }}</span>
                </div>
                <h1>Payout Run #{{ $run->id }}</h1>
                <div class="sub">
                    {{ $run->from_date->format('d/m/Y') }} – {{ $run->to_date->format('d/m/Y') }}
                    · finalized {{ optional($run->finalized_at)->format('d/m/Y') }} by {{ $run->finalizedBy?->name ?? '—' }}
                    · TDS {{ rtrim(rtrim(number_format($run->tds_rate * 100, 2), '0'), '.') }}% · Insurance {{ rtrim(rtrim(number_format($run->insurance_rate * 100, 2), '0'), '.') }}%
                </div>
            </div>
            <div class="head-actions">
                <a class="btn" href="{{ route('payouts.runs') }}">Back</a>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="ld-summary">
            <div class="ld-summary-box is-blue"><div class="k">Gross Payout</div><div class="v">₹ {{ inr($run->total_gross) }}</div></div>
            <div class="ld-summary-box"><div class="k">TDS</div><div class="v">₹ {{ inr($run->total_tds) }}</div></div>
            <div class="ld-summary-box is-green"><div class="k">Net Paid</div><div class="v">₹ {{ inr($run->total_net) }}</div></div>
            <div class="ld-summary-box"><div class="k">Users</div><div class="v">{{ $run->userTotals->count() }}</div></div>
        </div>

        {{-- Product tiers (snapshot) --}}
        <div class="card">
            <div class="card-hd"><div class="t">Product tiers (snapshot)</div></div>
            <div class="card-bd" style="overflow-x:auto;">
                <table class="tbl" style="width:100%;">
                    <thead><tr><th>Product</th><th>Type</th><th class="text-end">Aggregate base</th><th>Slab</th><th class="text-end">Rate</th><th class="text-end">Max payout</th></tr></thead>
                    <tbody>
                        @foreach ($run->products as $p)
                            <tr>
                                <td>{{ $p->product_name }}</td>
                                <td>{{ $p->is_pf_based ? 'PF-based' : 'Volume' }}</td>
                                <td class="text-end">₹ {{ inr($p->aggregate_base) }}</td>
                                <td>{{ $p->slab_low !== null ? '₹ '.inr($p->slab_low).' – ₹ '.inr($p->slab_high) : '—' }}</td>
                                <td class="text-end">{{ $p->tier_rate_type === 'percent' ? rtrim(rtrim(number_format($p->tier_rate, 2), '0'), '.').'%' : '₹ '.inr($p->tier_rate) }}</td>
                                <td class="text-end">{{ $p->max_payout === null || $p->max_payout < 0 ? '—' : '₹ '.inr($p->max_payout) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Per-user payable + per-product lines --}}
        <div class="card">
            <div class="card-hd"><div class="t">Per-user payout</div></div>
            <div class="card-bd" style="overflow-x:auto;">
                <table class="tbl" style="width:100%;">
                    <thead><tr><th>User</th><th class="text-end">Commission</th><th class="text-end">PF payout</th><th class="text-end">Insurance</th><th class="text-end">Total</th><th class="text-end">TDS</th><th class="text-end">Net</th></tr></thead>
                    <tbody>
                        @foreach ($run->userTotals as $u)
                            <tr>
                                <td>{{ $u->payoutUser?->name ?? ('#'.$u->payout_user_id) }}
                                    @if ($u->role_context === 'connector') <span class="shf-badge shf-badge-purple shf-text-2xs">connector</span>@endif
                                </td>
                                <td class="text-end">₹ {{ inr($u->total_commission) }}</td>
                                <td class="text-end">₹ {{ inr($u->total_pf_payout) }}</td>
                                <td class="text-end">₹ {{ inr($u->total_insurance_payout) }}</td>
                                <td class="text-end">₹ {{ inr($u->total_payout) }}</td>
                                <td class="text-end">₹ {{ inr($u->tds_amount) }}</td>
                                <td class="text-end"><strong>₹ {{ inr($u->net_payout) }}</strong></td>
                            </tr>
                            @foreach ($linesByUser[$u->payout_user_id] ?? [] as $l)
                                <tr class="text-muted" style="font-size:12px;">
                                    <td style="padding-left:22px;">↳ {{ $productName[$l->payout_run_product_id] ?? '' }}
                                        {{ $l->base_amount ? '(base ₹ '.inr($l->base_amount).' @ '.rtrim(rtrim(number_format($l->rate_applied, 2), '0'), '.').'%)' : '' }}</td>
                                    <td class="text-end">₹ {{ inr($l->commission) }}</td>
                                    <td class="text-end">₹ {{ inr($l->pf_payout) }}</td>
                                    <td class="text-end">₹ {{ inr($l->insurance_payout) }}</td>
                                    <td class="text-end">₹ {{ inr($l->line_total) }}</td>
                                    <td></td><td></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
