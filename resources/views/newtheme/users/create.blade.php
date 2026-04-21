@extends('newtheme.layouts.app', ['pageKey' => 'users'])

@section('title', ($copyFrom ? 'Copy User' : 'Create User') . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/user-form.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('users.index') }}">Users</a>
                    <span class="sep">/</span>
                    <span>{{ $copyFrom ? 'Copy' : 'New' }}</span>
                </div>
                <h1>{{ $copyFrom ? 'Copy User' : 'Create User' }}</h1>
                @if ($copyFrom)
                    <div class="sub">Copying from <strong>{{ $copyFrom->name }}</strong> — change the name, email and password.</div>
                @else
                    <div class="sub">Add a new user with a role and role-specific assignments.</div>
                @endif
            </div>
            <div class="head-actions">
                <a href="{{ route('users.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if ($errors->any())
            <div class="card uf-alert-card">
                <div class="card-bd">
                    <strong>Please fix the following:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-hd"><div class="t"><span class="num">1</span>User Information</div></div>
            <div class="card-bd">
                @include('newtheme.users._form', ['mode' => 'create'])
            </div>
        </div>
    </main>
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/pages/user-form.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
