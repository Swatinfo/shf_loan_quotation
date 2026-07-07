@extends('newtheme.layouts.app', ['pageKey' => 'loan-settings'])

@section('title', $product->bank->name . ' / ' . $product->name . ' · Stage Configuration · SHF World')

@push('page-styles')
    {{-- Legacy SHF CSS — the form body uses `.shf-*` classes extensively
         (cards, badges, inputs, stage blocks, location rows). --}}
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/product-stages.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('loan-settings.index') }}#products">Loan Settings</a>
                    <span class="sep">/</span>
                    <span>{{ $product->bank->name }} / {{ $product->name }}</span>
                </div>
                <h1>{{ $product->bank->name }} / {{ $product->name }}</h1>
                <div class="sub">Configure stages and user availability for this product.</div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loan-settings.index') }}#products" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content product-stages-nt">
        <div class="card product-stages-nt-card">
            <div class="card-hd product-stages-nt-card-hd">
                <div class="t">
                    <span class="num">PS</span>
                    <span>Stage Configuration</span>
                </div>
                <div class="actions">
                    <span class="product-stages-nt-breadcrumb">
                        {{ $product->bank->name }}
                        @if ($product->code)
                            · {{ $product->code }}
                        @endif
                    </span>
                </div>
            </div>
            <div class="card-bd product-stages-nt-card-bd">
                @include('newtheme.settings._workflow-product-stages-body')
            </div>
        </div>
    </main>
@endsection

@push('page-scripts')
    @include('newtheme.settings._workflow-product-stages-scripts')
@endpush
