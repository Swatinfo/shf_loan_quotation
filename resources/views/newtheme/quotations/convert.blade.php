{{--
    Newtheme quotation-convert — shell-swap wrapper.
    Body + scripts live in quotations/_convert-body and _convert-scripts,
    shared with the legacy view; we only swap the page chrome.
--}}
@extends('newtheme.layouts.app', ['pageKey' => 'quotations'])

@section('title', 'Convert Quotation #' . $quotation->id . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/vendor/bootstrap/css/bootstrap.min.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/quotation-show.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('quotations.index') }}">Quotations</a>
                    <span class="sep">/</span>
                    <a href="{{ route('quotations.show', $quotation) }}">#{{ $quotation->id }}</a>
                    <span class="sep">/</span>
                    <span>Convert</span>
                </div>
                <h1>Convert to Loan</h1>
                <div class="sub">
                    <strong>{{ $quotation->customer_name }}</strong>
                    @if ($quotation->customer_type) · {{ ucwords(str_replace('_', ' ', $quotation->customer_type)) }}@endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('quotations.show', $quotation) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Quotation
                </a>
            </div>
        </div>
    </header>

    @include('newtheme.quotations._convert-body')
@endsection

@push('page-scripts')
    @include('newtheme.quotations._convert-scripts')
@endpush
