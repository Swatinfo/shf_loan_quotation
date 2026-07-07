@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Transfers — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-transfers.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $manualCount = $transfers->where('transfer_type', '!=', 'auto')->count();
    $autoCount = $transfers->where('transfer_type', 'auto')->count();
@endphp

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
                    <span>Transfers</span>
                </div>
                <h1>Transfer History</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($loan->product?->name) / {{ $loan->product->name }}@endif
                    <span class="lx-count">
                        {{ $transfers->count() }} {{ Str::plural('transfer', $transfers->count()) }}
                        @if ($manualCount > 0) · <span class="lx-count-manual">{{ $manualCount }} manual</span>@endif
                        @if ($autoCount > 0) · <span class="lx-count-auto">{{ $autoCount }} auto</span>@endif
                    </span>
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.stages', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Stages
                </a>
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Loan
                </a>
                <a href="{{ route('loans.timeline', $loan) }}" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Timeline
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if ($transfers->isEmpty())
            <div class="card">
                <div class="card-bd lx-empty">
                    <div class="lx-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </div>
                    <div class="lx-empty-title">No transfers yet</div>
                    <div class="lx-empty-sub">Stage ownership changes will appear here as the loan moves through roles.</div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-hd">
                    <div class="t"><span class="num">T</span>Transfers <span class="sub">{{ $transfers->count() }} {{ Str::plural('entry', $transfers->count()) }}</span></div>
                </div>
                <div class="card-bd lx-wrap">
                    <ol class="lx-timeline">
                        @foreach ($transfers as $transfer)
                            @php
                                $isAuto = $transfer->transfer_type === 'auto';
                                $stageName = $transfer->stageAssignment?->stage?->stage_name_en
                                    ?? ucwords(str_replace('_', ' ', $transfer->stage_key));
                            @endphp
                            <li class="lx-item {{ $isAuto ? 'lx-tone-blue' : 'lx-tone-dark' }}">
                                <span class="lx-dot" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                </span>
                                <div class="lx-body">
                                    <div class="lx-head">
                                        <div class="lx-title-wrap">
                                            <strong class="lx-stage">{{ $stageName }}</strong>
                                            <span class="badge {{ $isAuto ? 'blue' : 'dark' }}">{{ $isAuto ? 'Auto' : 'Manual' }}</span>
                                        </div>
                                        @if ($transfer->created_at)
                                            <div class="lx-time">
                                                <span class="lx-date">{{ $transfer->created_at->format('d M Y') }}</span>
                                                <span class="lx-clock">{{ $transfer->created_at->format('h:i A') }}</span>
                                                <span class="lx-ago">· {{ $transfer->created_at->diffForHumans() }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="lx-flow">
                                        <span class="lx-party">
                                            <span class="lx-party-label">From</span>
                                            <strong>{{ $transfer->fromUser?->name ?? '—' }}</strong>
                                        </span>
                                        <span class="lx-arrow" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 5l7 7-7 7M5 12h15"/></svg>
                                        </span>
                                        <span class="lx-party lx-party-to">
                                            <span class="lx-party-label">To</span>
                                            <strong>{{ $transfer->toUser?->name ?? '—' }}</strong>
                                        </span>
                                    </div>

                                    @if ($transfer->reason)
                                        <blockquote class="lx-reason">“{{ $transfer->reason }}”</blockquote>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @endif
    </main>
@endsection
