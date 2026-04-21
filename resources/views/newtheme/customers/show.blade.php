@extends('newtheme.layouts.app', ['pageKey' => 'customers'])

@section('title', $customer->customer_name . ' · Customer · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/customers.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $canEdit = $customer->isEditableBy(auth()->user());
    $loanStatusTone = fn ($s) => match ($s) {
        'active' => 'blue',
        'completed' => 'green',
        'rejected' => 'red',
        'on_hold' => 'amber',
        'cancelled' => '',
        default => '',
    };
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('customers.index') }}">Customers</a>
                    <span class="sep">/</span>
                    <span>{{ $customer->customer_name }}</span>
                </div>
                <h1>{{ $customer->customer_name }}</h1>
                <div class="sub">
                    @if ($customer->mobile)
                        <a href="tel:{{ $customer->mobile }}" style="color:inherit;">{{ $customer->mobile }}</a>
                    @else
                        <span>No mobile on file</span>
                    @endif
                    <span class="badge blue" style="margin-left:8px;vertical-align:middle;">{{ $customer->loans->count() }} loans</span>
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('customers.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                @if ($canEdit)
                    <a href="{{ route('customers.edit', $customer) }}" class="btn primary">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="content">
        <div class="grid c-main cxs-grid">
            {{-- ===== Left column ===== --}}
            <div>
                {{-- Details card --}}
                <div class="card">
                    <div class="card-hd"><div class="t">Details</div></div>
                    <div class="card-bd">
                        <div class="cxs-grid-3">
                            <div class="cxs-kv-item">
                                <span class="cxs-k">Name</span>
                                <span class="cxs-v">{{ $customer->customer_name }}</span>
                            </div>
                            <div class="cxs-kv-item">
                                <span class="cxs-k">Mobile</span>
                                <span class="cxs-v">
                                    @if ($customer->mobile)
                                        <a href="tel:{{ $customer->mobile }}" class="cxs-link">{{ $customer->mobile }}</a>
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="cxs-kv-item">
                                <span class="cxs-k">Email</span>
                                <span class="cxs-v">
                                    @if ($customer->email)
                                        <a href="mailto:{{ $customer->email }}" class="cxs-link">{{ $customer->email }}</a>
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="cxs-kv-item">
                                <span class="cxs-k">PAN</span>
                                <span class="cxs-v cxs-mono">{{ $customer->pan_number ?: '—' }}</span>
                            </div>
                            <div class="cxs-kv-item">
                                <span class="cxs-k">Date of Birth</span>
                                <span class="cxs-v">{{ $customer->date_of_birth?->format('d M Y') ?: '—' }}</span>
                            </div>
                            <div class="cxs-kv-item">
                                <span class="cxs-k">Created</span>
                                <span class="cxs-v">{{ $customer->created_at?->format('d M Y') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Loans card --}}
                <div class="card mt-4">
                    <div class="card-hd">
                        <div class="t">Loans <span class="sub">({{ $customer->loans->count() }})</span></div>
                    </div>
                    <div class="card-bd" style="padding:0;">
                        @forelse ($customer->loans as $loan)
                            <div class="cxs-loan-row">
                                <div class="cxs-loan-main">
                                    <a href="{{ route('loans.show', $loan) }}" class="cxs-loan-link">#{{ $loan->loan_number }}</a>
                                    <span class="cxs-loan-meta">
                                        {{ $loan->bank?->name ?? $loan->bank_name ?? '—' }}
                                        @if ($loan->branch)
                                            · {{ $loan->branch->name }}
                                        @endif
                                    </span>
                                </div>
                                <span class="badge {{ $loanStatusTone($loan->status) }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                            </div>
                        @empty
                            <div class="cxs-empty">No loans linked to this customer yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ===== Right column ===== --}}
            <aside>
                <div class="card">
                    <div class="card-hd"><div class="t">Summary</div></div>
                    <div class="card-bd">
                        <div class="cxs-summary">
                            <div><span>Total Loans</span><span>{{ $customer->loans->count() }}</span></div>
                            <div><span>Active</span><span>{{ $customer->loans->where('status', 'active')->count() }}</span></div>
                            <div><span>Completed</span><span>{{ $customer->loans->where('status', 'completed')->count() }}</span></div>
                            <div><span>Created</span><span>{{ $customer->created_at?->format('d M Y') }}</span></div>
                        </div>
                    </div>
                </div>

                @if ($canEdit)
                    <a href="{{ route('customers.edit', $customer) }}" class="btn primary mt-4" style="width:100%;justify-content:center;">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit Customer
                    </a>
                @endif
            </aside>
        </div>
    </main>
@endsection
