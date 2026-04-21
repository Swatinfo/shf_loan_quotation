{{--
    Newtheme wrapper for the loan stages page.

    The stages view has ~4,400 lines of tightly-coupled HTML + JS covering the
    full workflow (multi-phase role handoffs, parallel sub-stages, sanction
    decision gates, query / transfer / notes modals, doc grids, valuation,
    disbursement, etc.). Rather than rewrite all of that, we keep the
    battle-tested body + scripts as shared partials and just swap the page
    chrome (topbar, header, footer) by extending the newtheme layout.

    The inner content still uses the legacy `shf-*` class vocabulary, so this
    view loads `public/css/shf.css` alongside the newtheme stylesheet. Both
    files are namespaced under `.shf-*` / newtheme tokens and don't collide on
    any of the layout chrome (.page-header, .btn, .card) we use here.
--}}
@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Stages — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    {{-- The legacy body uses Bootstrap 5 grid + utility classes (.row, .col-*,
         .g-1, .d-flex, .px-*, .py-*, .card, .border-*, .text-*, etc.) plus the
         full `shf-*` class vocabulary. The newtheme layout ships neither, so
         we load both here scoped to this page. Order matters: load Bootstrap
         first, then legacy shf.css, then the page-specific stylesheet, then a
         small overrides block at the end so newtheme header widgets win over
         Bootstrap's .btn / .badge resets. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/vendor/bootstrap/css/bootstrap.min.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-stages.css') }}?v={{ config('app.shf_version') }}">

    {{-- Local page styles from the legacy view (bottom-bar, wrap overflow). --}}
    <style>
        .shf-stages-wrap { overflow-x: hidden; }
        .shf-stages-wrap .card-body { word-break: break-word; }

        /* Fixed bottom action bar for mobile/tablet. Rides above the newtheme
           bottom-nav via --shf-bottom-nav-height; below offcanvas/modal. */
        .shf-bottom-bar {
            position: fixed;
            bottom: var(--shf-bottom-nav-height, 0px);
            left: 0;
            right: 0;
            z-index: 1040;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            padding-bottom: calc(10px + env(safe-area-inset-bottom, 0px));
            background: var(--white, #fff);
            border-top: 1px solid var(--border, #bcbec0);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.08);
        }
        .shf-bottom-bar.shf-bar-visible { display: flex; flex-wrap: wrap; }
        @media (min-width: 1200px) {
            .shf-bottom-bar { display: none !important; }
        }
        .shf-bar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: var(--radius, 10px);
            background: linear-gradient(135deg, var(--accent, #f15a29), var(--accent-warm, #f47929));
            color: #fff;
            font-family: 'Jost', sans-serif;
            font-weight: 600;
            font-size: var(--shf-text-sm, 0.85rem);
            white-space: nowrap;
            cursor: pointer;
            transition: transform 0.15s;
        }
        .shf-bar-btn:active { transform: scale(0.97); }
        .shf-bar-btn svg { width: 14px; height: 14px; }
        .shf-bar-btn--warning { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .shf-bar-btn--danger  { background: linear-gradient(135deg, #dc3545, #e85d6a); }
        .shf-bar-btn--success { background: linear-gradient(135deg, var(--green, #27ae60), #2ecc71); }

        .shf-bottom-bar.shf-bar-visible ~ .py-4,
        .shf-stages-wrap { padding-bottom: 100px !important; }
    </style>
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
                    <span>Stages</span>
                </div>
                <h1>Stages — Loan #{{ $loan->loan_number }}</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($loan->product?->name) / {{ $loan->product->name }}@endif
                    @if ($progress)
                        <span class="ls-progress-chip">
                            {{ $progress->completed_stages }}/{{ $progress->total_stages }}
                            · {{ number_format($progress->overall_percentage, 0) }}%
                        </span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Loan
                </a>
                <a href="{{ route('loans.timeline', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Timeline
                </a>
                <a href="{{ route('loans.transfers', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m-8 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transfers
                </a>
            </div>
        </div>
    </header>

    @include('newtheme.loans._stages-body')
@endsection

@push('page-scripts')
    @include('newtheme.loans._stages-scripts')
@endpush
