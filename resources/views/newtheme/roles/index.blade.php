@extends('newtheme.layouts.app', ['pageKey' => 'roles'])

@section('title', 'Roles · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/roles.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $totalUsers = $roles->sum('users_count');
    $systemCount = $roles->where('is_system', true)->count();
    $advisorCount = $roles->where('can_be_advisor', true)->count();
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Roles</span>
                </div>
                <h1>Role Management</h1>
                <div class="sub">
                    <strong>{{ $roles->count() }}</strong> roles ·
                    <strong>{{ $systemCount }}</strong> system ·
                    <strong>{{ $advisorCount }}</strong> advisor-eligible ·
                    <strong>{{ $totalUsers }}</strong> user assignments
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('roles.create') }}" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                    New Role
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('success'))
            <div class="rl-flash">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="card rl-card">
            <div class="card-hd">
                <div class="t"><span class="num">R</span>Roles <span class="sub">{{ $roles->count() }} total</span></div>
            </div>

            {{-- Desktop table --}}
            <div class="card-bd d-desktop-only" style="padding:0;overflow-x:auto;">
                <table class="tbl rl-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Slug</th>
                            <th>Description</th>
                            <th class="num">Advisor</th>
                            <th class="num">Users</th>
                            <th class="num">Type</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td class="rl-name">{{ $role->name }}</td>
                                <td><code class="rl-slug">{{ $role->slug }}</code></td>
                                <td class="rl-desc">{{ $role->description ?: '—' }}</td>
                                <td class="num">
                                    @if ($role->can_be_advisor)
                                        <span class="badge green">Yes</span>
                                    @else
                                        <span class="badge">No</span>
                                    @endif
                                </td>
                                <td class="num">
                                    <span class="rl-user-count {{ $role->users_count > 0 ? '' : 'zero' }}">{{ $role->users_count }}</span>
                                </td>
                                <td class="num">
                                    @if ($role->is_system)
                                        <span class="badge orange">System</span>
                                    @else
                                        <span class="badge blue">Workflow</span>
                                    @endif
                                </td>
                                <td class="col-actions">
                                    <a href="{{ route('roles.edit', $role) }}" class="rl-act tone-gray" title="Edit" aria-label="Edit">
                                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="rl-empty">No roles defined.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="card-bd d-mobile-only" style="padding:0;">
                @forelse ($roles as $role)
                    <div class="rl-m-card">
                        <div class="rl-m-hd">
                            <div style="min-width:0;flex:1;">
                                <strong>{{ $role->name }}</strong>
                                <code class="rl-slug d-block">{{ $role->slug }}</code>
                            </div>
                            @if ($role->is_system)
                                <span class="badge orange">System</span>
                            @else
                                <span class="badge blue">Workflow</span>
                            @endif
                        </div>
                        @if ($role->description)
                            <div class="rl-m-desc">{{ $role->description }}</div>
                        @endif
                        <div class="rl-m-row">
                            <span class="k">Advisor</span>
                            <span class="v">
                                @if ($role->can_be_advisor)
                                    <span class="badge green">Yes</span>
                                @else
                                    <span class="badge">No</span>
                                @endif
                            </span>
                        </div>
                        <div class="rl-m-row">
                            <span class="k">Users</span>
                            <span class="v"><span class="rl-user-count {{ $role->users_count > 0 ? '' : 'zero' }}">{{ $role->users_count }}</span></span>
                        </div>
                        <div class="rl-m-actions">
                            <a href="{{ route('roles.edit', $role) }}" class="btn sm">
                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="rl-empty">No roles defined.</div>
                @endforelse
            </div>
        </div>
    </main>
@endsection
