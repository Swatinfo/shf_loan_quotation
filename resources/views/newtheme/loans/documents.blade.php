@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Documents — ' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    {{-- Legacy SHF CSS — the documents body uses `.shf-*` classes
         (shf-card, shf-doc-*, shf-checkbox, shf-badge-*, shf-input,
         shf-btn-*, shf-progress, etc.). The scoped loan-documents.css
         below repaints them in newtheme style. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-documents.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $docsLocked = ! in_array($loan->status, ['active', 'on_hold']) || $loan->stageAssignments()
        ->where('parent_stage_key', 'parallel_processing')
        ->where('status', 'completed')
        ->exists();
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
                    <span>Documents</span>
                </div>
                <h1>Documents</h1>
                <div class="sub">
                    <strong>#{{ $loan->loan_number }}</strong>
                    @if ($loan->customer_name)
                        · {{ $loan->customer_name }}
                    @endif
                    @if ($loan->bank?->name ?? $loan->bank_name)
                        · {{ $loan->bank?->name ?? $loan->bank_name }}
                    @endif
                    @if ($docsLocked)
                        <span class="badge red" style="margin-left:8px;vertical-align:middle;">Locked</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content loan-documents-nt">
        <div class="card loan-documents-nt-card">
            <div class="card-hd loan-documents-nt-card-hd">
                <div class="t">
                    <span class="num">D</span>
                    <span>Collection</span>
                </div>
                <div class="actions">
                    <span class="loan-documents-nt-breadcrumb">
                        {{ $progress['resolved'] }}/{{ $progress['total'] }} collected · {{ $progress['percentage'] }}%
                    </span>
                </div>
            </div>
            <div class="card-bd loan-documents-nt-card-bd">
                @include('newtheme.loans._documents-body')
            </div>
        </div>
    </main>
@endsection

@push('page-scripts')
    @include('newtheme.loans._documents-scripts')
@endpush
