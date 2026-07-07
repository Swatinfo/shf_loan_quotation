{{--
    Newtheme classic valuation wrapper — shell-swap. Body + scripts live in
    loans/_valuation-body and _valuation-scripts, shared with the legacy
    wrapper. We only swap the chrome.
--}}
@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Valuation — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/vendor/bootstrap/css/bootstrap.min.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/quotation-show.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php $v = $valuations->first(); @endphp

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
                    <span>Valuation</span>
                </div>
                <h1>Valuation</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($v && $v->final_valuation)
                        <span class="ld-chip" style="background:var(--green, #1f8c4d);">Final ₹ {{ number_format($v->final_valuation) }}</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.stages', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Stages
                </a>
                <a href="{{ route('loans.valuation.map', $loan) }}" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Map View
                </a>
            </div>
        </div>
    </header>

    @include('newtheme.loans._valuation-body')
@endsection

@push('page-scripts')
    @include('newtheme.loans._valuation-scripts')
@endpush
