@extends('newtheme.layouts.app', ['pageKey' => 'settings'])

@section('title', 'Workflow Configuration · SHF World')

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
                    <a href="{{ route('settings.index') }}">Settings</a>
                    <span class="sep">/</span>
                    <span>Workflow</span>
                </div>
                <h1>Workflow Configuration</h1>
                <div class="sub">
                    Configure which banks and branches get which stages.
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('settings.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Settings
                </a>
            </div>
        </div>
    </header>

    @include('newtheme.settings._workflow-body')
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/vendor/bootstrap/js/bootstrap.bundle.min.js') }}?v={{ config('app.shf_version') }}"></script>
    @include('newtheme.settings._workflow-scripts')
@endpush
