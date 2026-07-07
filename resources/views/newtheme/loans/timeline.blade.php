@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Timeline — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-timeline.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $toneMap = [
        'primary' => 'blue',
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'info' => 'blue',
        'secondary' => 'dark',
    ];
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
                    <span>Timeline</span>
                </div>
                <h1>Timeline</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($loan->product?->name) / {{ $loan->product->name }}@endif
                    <span class="lt-count">{{ $timeline->count() }} {{ Str::plural('entry', $timeline->count()) }}</span>
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Loan
                </a>
                <a href="{{ route('loans.stages', $loan) }}" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    Stages
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if ($timeline->isEmpty())
            <div class="card">
                <div class="card-bd lt-empty">
                    <div class="lt-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="lt-empty-title">No timeline entries yet</div>
                    <div class="lt-empty-sub">Events show up here as the loan moves through stages.</div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-hd">
                    <div class="t"><span class="num">T</span>Activity <span class="sub">{{ $timeline->count() }} {{ Str::plural('entry', $timeline->count()) }}</span></div>
                </div>
                <div class="card-bd lt-wrap">
                    <ol class="lt-timeline">
                        @foreach ($timeline as $entry)
                            @php $tone = $toneMap[$entry['color']] ?? 'dark'; @endphp
                            <li class="lt-item lt-tone-{{ $tone }}">
                                <span class="lt-dot" aria-hidden="true"></span>
                                <div class="lt-body">
                                    <div class="lt-head">
                                        <div class="lt-title-wrap">
                                            <strong class="lt-title">{{ $entry['title'] }}</strong>
                                            @if (! empty($entry['user']) && $entry['user'] !== '—')
                                                <span class="lt-by">by {{ $entry['user'] }}</span>
                                            @endif
                                        </div>
                                        @if (! empty($entry['date']))
                                            <div class="lt-time">
                                                <span class="lt-date">{{ $entry['date']->format('d M Y') }}</span>
                                                <span class="lt-clock">{{ $entry['date']->format('h:i A') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if (! empty($entry['description']))
                                        <p class="lt-desc">{{ $entry['description'] }}</p>
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
