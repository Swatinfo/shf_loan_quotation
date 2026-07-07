@extends('newtheme.layouts.app', ['pageKey' => 'users'])

@section('title', 'Edit ' . $user->name . ' · SHF World')

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
                    <span>{{ $user->name }}</span>
                </div>
                <h1>Edit User</h1>
                <div class="sub">
                    <strong>{{ $user->name }}</strong>
                    @if ($user->email) · <span class="uf-muted">{{ $user->email }}</span>@endif
                    @if ($user->is_active) <span class="badge green" style="margin-left:6px;vertical-align:middle;">Active</span>
                    @else <span class="badge red" style="margin-left:6px;vertical-align:middle;">Inactive</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('users.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <a href="{{ route('users.create', ['copy' => $user->id]) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                    Copy
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
                @include('newtheme.users._form', ['mode' => 'edit'])
            </div>
        </div>
    </main>
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/pages/user-form.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
