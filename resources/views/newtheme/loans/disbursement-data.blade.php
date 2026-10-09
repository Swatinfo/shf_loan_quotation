@extends('newtheme.layouts.app', ['pageKey' => 'settings'])

@section('title', 'Disbursement Data · SHF World')

@push('page-styles')
    <style>
        .dd-note { color: var(--ink-3); font-size: 12.5px; line-height: 1.5; }
        .dd-alert { border-left: 3px solid var(--line); margin-bottom: 14px; }
        .dd-alert.is-green { border-left-color: var(--green, #1f8c4d); }
        .dd-alert.is-red { border-left-color: var(--red, #c0392b); }
        .dd-alert.is-amber { border-left-color: var(--amber, #d97706); }
        .dd-scroll { overflow-x: auto; }
        .dd-scroll .tbl { width: 100%; min-width: 520px; }
        .dd-err { color: var(--red, #c0392b); font-size: 12.5px; }
        .dd-diff { font-size: 12px; color: var(--ink-2, #444); }
        /* Preview before/after */
        .dd-loan { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
        .dd-loan-hd { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-bottom: 8px; }
        .dd-loan-hd a { font-weight: 600; }
        .dd-ld { font-size: 11.5px; color: var(--ink-3); }
        .dd-ld .o { color: var(--ink-3); }
        .dd-ld.is-chg { color: var(--ink); }
        .dd-ld.is-chg .n { color: var(--red, #c0392b); font-weight: 700; }
        .dd-entry { margin-top: 8px; }
        .dd-badge { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 1px 7px; border-radius: 10px; }
        .dd-badge.dd-update { background: rgba(37,99,235,.12); color: #2563eb; }
        .dd-badge.dd-delete { background: rgba(192,57,43,.12); color: var(--red, #c0392b); }
        .dd-badge.dd-delete-blocked { background: rgba(217,119,6,.14); color: var(--amber, #b85a00); }
        .dd-fields { width: 100%; margin-top: 4px; font-size: 12px; }
        .dd-fields th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-3); }
        .dd-fields td { padding: 3px 8px; }
        .dd-fields .dd-old { color: var(--ink-3); }
        .dd-row-chg .dd-old { text-decoration: line-through; }
        .dd-row-chg .dd-new { color: var(--red, #c0392b); font-weight: 700; }
    </style>
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Disbursement Data</span>
                </div>
                <h1>Disbursement Data — Correction Tool</h1>
                <div class="sub">Super-admin only. Export every disbursed tranche, fix the real values, re-import to update everywhere.</div>
            </div>
            <div class="head-actions">
                <a class="btn primary" href="{{ route('loans.disbursement-data.export') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export Disbursed Tranches
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('success'))
            <div class="card dd-alert is-green"><div class="card-bd">{{ session('success') }}</div></div>
        @endif
        @if ($errors->any())
            <div class="card dd-alert is-red"><div class="card-bd">
                <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div></div>
        @endif

        {{-- Import result summary (after a committed import) --}}
        @php $imp = session('importResult'); @endphp
        @if ($imp)
            <div class="card dd-alert {{ $imp['errors'] ? 'is-amber' : 'is-green' }}">
                <div class="card-bd">
                    <strong>Import complete:</strong> {{ $imp['updated'] }} loan(s) updated,
                    {{ $imp['skippedLoans'] }} skipped, {{ count($imp['errors']) }} issue(s).
                    @if (! empty($imp['statusChanges']))
                        <div class="dd-note" style="margin-top:6px;"><strong>Status changes:</strong>
                            {{ implode(' · ', $imp['statusChanges']) }}</div>
                    @endif
                    @if ($imp['errors'])
                        <ul class="dd-err mb-0" style="margin-top:6px;">
                            @foreach (array_slice($imp['errors'], 0, 50) as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        {{-- Upload / Preview --}}
        <div class="card">
            <div class="card-hd"><div class="t">Upload corrected sheet</div></div>
            <div class="card-bd">
                <div class="dd-note mb-3">
                    One row per disbursed tranche. Editable: Bank, Product, Sanctioned Amount, Method
                    (<strong>NEFT / Cheque</strong>), Amount, Cheque Name, Cheque No, Disbursement Date, <strong>Transfer Date</strong>,
                    OTC Clearance (<strong>Yes / No / Skip</strong>) and OTC Clearance
                    Date. For <strong>NEFT</strong> the <strong>Transfer Date</strong> is the settlement date (defaults to the Disbursement
                    Date) and becomes the OTC Clearance Date — payouts/reports count NEFT on that date; for a <strong>cheque</strong>,
                    OTC Clearance = Yes records the OTC Clearance Date as the settlement date. <strong>Loan Advisor</strong> + <strong>Payout User</strong> are <strong>one-time per loan</strong>
                    (enter on the loan's <strong>first</strong> tranche row; match the user's name exactly; blank = keep).
                    <strong>PF Amount</strong>, <strong>Admin Charges</strong> and <strong>Insurance Amount</strong>
                    are also <strong>one-time per loan</strong> — enter them on the loan's <strong>first</strong> tranche row
                    (GST-inclusive; insurance has no GST). <strong>Payout Run</strong> is read-only (shows the finalized run #).
                    Each loan's <strong>first tranche row</strong> is highlighted (orange top border + light shading) so you can see where a new loan begins.
                    The <strong>Action</strong> column is <code>Keep</code> (default) or <code>Delete</code> —
                    <code>Delete</code> removes that tranche (a payout-finalized tranche can't be deleted).
                    <strong>Loan ID</strong> + <strong>Entry ID</strong> are the keys — don't change them. Dates export as
                    <code>dd-mm-yyyy</code> (import also accepts <code>yyyy-mm-dd</code> / <code>dd/mm/yyyy</code>). On import the loan status is re-resolved from
                    the totals (<em>gross = amount + PF + admin</em>) + OTC — it can move to completed <em>or back to
                    partial_disbursed</em>. <strong>Preview</strong> first, then <strong>Confirm Import</strong>.
                </div>
                <form method="POST" action="{{ route('loans.disbursement-data.preview') }}" enctype="multipart/form-data"
                    class="row g-2 align-items-end" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    @csrf
                    <div class="field" style="margin-bottom:0;">
                        <label class="lbl">Filled sheet (.xlsx)</label>
                        <input type="file" name="file" class="input" accept=".xlsx" required>
                    </div>
                    <button type="submit" class="btn primary">Preview Changes</button>
                </form>
            </div>
        </div>

        {{-- Preview (dry-run) --}}
        @if (! empty($preview))
            @php
                $loanChanges = collect($preview['loans']);
                $okLoans = $loanChanges->filter(fn ($c) => empty($c['error']));
                $badLoans = $loanChanges->filter(fn ($c) => ! empty($c['error']));
            @endphp
            <div class="card" style="margin-top:14px;">
                <div class="card-hd"><div class="t">Preview — {{ $preview['rowCount'] }} row(s), {{ $okLoans->count() }} loan(s) to update</div></div>
                <div class="card-bd">
                    @if ($preview['errors'])
                        <div class="dd-alert is-amber" style="padding:8px 12px;border:1px solid var(--line);border-radius:8px;margin-bottom:12px;">
                            <strong class="dd-err">{{ count($preview['errors']) }} row issue(s) (those rows are skipped):</strong>
                            <ul class="dd-err mb-0">@foreach (array_slice($preview['errors'], 0, 50) as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if ($badLoans->isNotEmpty())
                        <ul class="dd-err">@foreach ($badLoans as $c)<li>{{ $c['error'] }}</li>@endforeach</ul>
                    @endif

                    @php
                        $actionLabel = ['update' => 'Update', 'delete' => 'Delete', 'delete-blocked' => 'Delete blocked'];
                    @endphp
                    @forelse ($okLoans as $c)
                        <div class="dd-loan">
                            <div class="dd-loan-hd">
                                <a href="{{ route('loans.show', $c['loan']) }}">{{ $c['loan']->loan_number }}</a>
                                @foreach (['advisor', 'payout_user', 'bank', 'product', 'sanctioned', 'pf', 'admin', 'insurance', 'status'] as $k)
                                    @php $d = $c['loan_diff'][$k]; @endphp
                                    <span class="dd-ld {{ $d['changed'] ? 'is-chg' : '' }}">
                                        {{ $d['label'] }}: <span class="o">{{ $d['old'] }}</span>@if ($d['changed']) → <span class="n">{{ $d['new'] }}</span>@endif
                                    </span>
                                @endforeach
                            </div>

                            @forelse ($c['preview_entries'] as $pe)
                                <div class="dd-entry">
                                    <span class="dd-badge dd-{{ $pe['action'] }}">{{ $actionLabel[$pe['action']] ?? $pe['action'] }}</span>
                                    <span class="dd-note">Entry #{{ $pe['entry_id'] }}</span>
                                    <div class="dd-scroll">
                                        <table class="tbl dd-fields">
                                            <thead><tr><th>Field</th><th>Original</th><th>Imported</th></tr></thead>
                                            <tbody>
                                                @foreach ($pe['fields'] as $f)
                                                    <tr class="{{ $f['changed'] ? 'dd-row-chg' : '' }}">
                                                        <td>{{ $f['label'] }}</td>
                                                        <td class="dd-old">{{ $f['old'] }}</td>
                                                        <td class="dd-new">{{ $f['new'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @empty
                                <div class="dd-note">Loan-level changes only (no tranche edits).</div>
                            @endforelse
                        </div>
                    @empty
                        <div class="dd-note" style="padding:14px;">Nothing to update.</div>
                    @endforelse

                    @if ($okLoans->isNotEmpty())
                        <form method="POST" action="{{ route('loans.disbursement-data.import') }}" style="margin-top:14px;"
                            onsubmit="return confirm('Apply these corrections to {{ $okLoans->count() }} loan(s)? This rewrites disbursement + OTC data and re-resolves loan status.');">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="btn primary">Confirm Import ({{ $okLoans->count() }} loan(s))</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </main>
@endsection
