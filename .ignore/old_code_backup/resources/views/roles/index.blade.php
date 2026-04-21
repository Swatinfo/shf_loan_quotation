@extends('layouts.app')
@section('title', 'Roles — SHF')

@section('header')
    <div class="d-flex align-items-center justify-content-between">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            Role Management
        </h2>
        <a href="{{ route('roles.create') }}" class="btn-accent">
            <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Role
        </a>
    </div>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">
            <div class="shf-card p-0">

                {{-- Desktop Table --}}
                <div class="d-none d-md-block">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th class="text-center">Advisor Eligible</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Type</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td class="fw-medium">{{ $role->name }}</td>
                                    <td><code class="small">{{ $role->slug }}</code></td>
                                    <td class="small shf-text-gray">{{ $role->description ?? '—' }}</td>
                                    <td class="text-center">
                                        @if ($role->can_be_advisor)
                                            <span class="shf-badge shf-badge-green">Yes</span>
                                        @else
                                            <span class="shf-badge shf-badge-gray">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $role->users_count }}</td>
                                    <td class="text-center">
                                        @if ($role->is_system)
                                            <span class="shf-badge shf-badge-orange">System</span>
                                        @else
                                            <span class="shf-badge shf-badge-blue">Workflow</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('roles.edit', $role) }}" class="btn-accent-outline btn-accent-sm">
                                            <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="d-md-none p-3">
                    @foreach ($roles as $role)
                        <div class="shf-card mb-3 p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>{{ $role->name }}</strong>
                                    <code class="d-block small shf-text-gray">{{ $role->slug }}</code>
                                </div>
                                @if ($role->is_system)
                                    <span class="shf-badge shf-badge-orange">System</span>
                                @else
                                    <span class="shf-badge shf-badge-blue">Workflow</span>
                                @endif
                            </div>
                            @if ($role->description)
                                <p class="small shf-text-gray mb-2">{{ $role->description }}</p>
                            @endif
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small">
                                    Advisor: {!! $role->can_be_advisor ? '<span class="shf-badge shf-badge-green">Yes</span>' : '<span class="shf-badge shf-badge-gray">No</span>' !!}
                                    <span class="ms-2">{{ $role->users_count }} users</span>
                                </div>
                                <a href="{{ route('roles.edit', $role) }}" class="btn-accent-outline btn-accent-sm">Edit</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
