@extends('newtheme.layouts.app', ['pageKey' => 'settings'])

@section('title', 'Permissions · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/permissions.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $totalPerms = $permissions->flatten()->count();
    $editableRoles = $roles->count();
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('settings.index') }}">Settings</a>
                    <span class="sep">/</span>
                    <span>Permissions</span>
                </div>
                <h1>Permission Management</h1>
                <div class="sub">
                    <strong>{{ $totalPerms }}</strong> permissions across
                    <strong>{{ $permissions->count() }}</strong> groups ·
                    <strong>{{ $editableRoles }}</strong> editable roles
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

    <main class="content">
        @if (session('success'))
            <div class="pm-flash">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="card pm-info-card">
            <div class="card-bd">
                <div class="pm-info-line">
                    <span class="pm-info-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <span>
                        Configure default permissions per role. <strong>Super Admin</strong> always has every permission.
                        Loan permissions are managed separately in
                        <a href="{{ route('loan-settings.index') }}#role-permissions">Loan Settings → Role Permissions</a>.
                    </span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('permissions.update') }}" id="pmForm">
            @csrf
            @method('PUT')

            <div class="card pm-card">
                <div class="card-hd">
                    <div class="t"><span class="num">P</span>Role × Permission Matrix</div>
                    <div class="actions pm-toolbar">
                        <input type="text" id="pmSearch" class="input pm-search" placeholder="Filter permissions…" autocomplete="off">
                    </div>
                </div>
                <div class="card-bd pm-table-wrap">
                    <table class="tbl pm-table">
                        <thead>
                            <tr>
                                <th class="pm-col-permission">Permission</th>
                                @foreach ($roles as $role)
                                    <th class="pm-col-role">
                                        <div class="pm-role-cell">
                                            <span class="pm-role-name">{{ $role->name }}</span>
                                            <button type="button" class="pm-bulk" data-role="{{ $role->slug }}" data-action="all" title="Grant all in this role">All</button>
                                            <button type="button" class="pm-bulk pm-bulk-none" data-role="{{ $role->slug }}" data-action="none" title="Revoke all in this role">None</button>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissions as $group => $perms)
                                <tr class="pm-group-row">
                                    <td colspan="{{ count($roles) + 1 }}">
                                        <span class="pm-group-label">{{ $group }}</span>
                                        <span class="pm-group-count">{{ $perms->count() }}</span>
                                    </td>
                                </tr>
                                @foreach ($perms as $perm)
                                    <tr class="pm-row" data-search="{{ strtolower($perm->name . ' ' . ($perm->description ?? '') . ' ' . $group) }}">
                                        <td class="pm-perm-cell">
                                            <div class="pm-perm-name">{{ $perm->name }}</div>
                                            @if ($perm->description)
                                                <div class="pm-perm-desc">{{ $perm->description }}</div>
                                            @endif
                                        </td>
                                        @foreach ($roles as $role)
                                            <td class="pm-check-cell">
                                                <label class="pm-check">
                                                    <input type="checkbox"
                                                        name="role[{{ $role->slug }}][]"
                                                        value="{{ $perm->id }}"
                                                        data-role="{{ $role->slug }}"
                                                        {{ in_array($perm->id, $rolePermissions[$role->slug] ?? []) ? 'checked' : '' }}>
                                                </label>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    <div id="pmNoResults" class="pm-no-results" style="display:none;">
                        No permissions match your filter.
                    </div>
                </div>
            </div>

            <div class="pm-actions">
                <a href="{{ route('settings.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancel
                </a>
                <button type="submit" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                    Save Permissions
                </button>
            </div>
        </form>
    </main>
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/pages/permissions.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
