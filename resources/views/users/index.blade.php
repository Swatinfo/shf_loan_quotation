@extends('layouts.app')

@section('header')
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; line-height: 1.75rem; margin: 0;">
            <svg style="width:16px;height:16px;display:inline;margin-right:6px;color:#f15a29;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            User Management
        </h2>
        @if(auth()->user()->hasPermission('create_users'))
            <a href="{{ route('users.create') }}" class="btn-accent btn-accent-sm">
                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New User
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">
            <div class="shf-section">
                <!-- Desktop table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $user->name }}</div>
                                        @if($user->phone)
                                            <div class="small" style="color: #6b7280;">{{ $user->phone }}</div>
                                        @endif
                                    </td>
                                    <td style="color: #6b7280;">{{ $user->email }}</td>
                                    <td>
                                        <span class="shf-badge {{ $user->isSuperAdmin() ? 'shf-badge-orange' : ($user->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray') }}">
                                            {{ $user->role_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="shf-badge {{ $user->is_active ? 'shf-badge-green' : 'shf-badge-red' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td style="color: #6b7280; white-space: nowrap;">
                                        {{ $user->created_at->format('d M Y') }}
                                        @if($user->creator)
                                            <div class="small" style="color: #9ca3af;">by {{ $user->creator->name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-3">
                                            @if(auth()->user()->hasPermission('edit_users'))
                                                <a href="{{ route('users.edit', $user) }}" class="fw-semibold small" style="color: #f15a29; text-decoration: none;">Edit</a>

                                                @if($user->id !== auth()->id())
                                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-link p-0 fw-semibold small text-decoration-none" style="color: {{ $user->is_active ? '#ca8a04' : '#16a34a' }};">
                                                            {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if(auth()->user()->hasPermission('delete_users') && $user->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                                      onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link p-0 fw-semibold small text-decoration-none" style="color: #dc2626;">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5" style="color: #6b7280;">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile card layout -->
                <div class="d-md-none p-3">
                    @forelse($users as $user)
                        <div class="shf-card mb-3 p-3">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div>
                                    <div class="fw-semibold" style="font-size: 0.9rem;">{{ $user->name }}</div>
                                    <div style="color: #6b7280; font-size: 0.78rem;">{{ $user->email }}</div>
                                    @if($user->phone)
                                        <div style="color: #6b7280; font-size: 0.72rem;">{{ $user->phone }}</div>
                                    @endif
                                </div>
                                <span class="shf-badge {{ $user->is_active ? 'shf-badge-green' : 'shf-badge-red' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="shf-badge {{ $user->isSuperAdmin() ? 'shf-badge-orange' : ($user->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray') }}">
                                    {{ $user->role_label }}
                                </span>
                                <span style="color: #9ca3af; font-size: 0.7rem;">
                                    {{ $user->created_at->format('d M Y') }}
                                    @if($user->creator)
                                        by {{ $user->creator->name }}
                                    @endif
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-3 pt-2" style="border-top: 1px solid #f0f0f0;">
                                @if(auth()->user()->hasPermission('edit_users'))
                                    <a href="{{ route('users.edit', $user) }}" class="fw-semibold" style="color: #f15a29; font-size: 0.78rem; text-decoration: none;">Edit</a>

                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-link p-0 fw-semibold text-decoration-none" style="color: {{ $user->is_active ? '#ca8a04' : '#16a34a' }}; font-size: 0.78rem;">
                                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                @if(auth()->user()->hasPermission('delete_users') && $user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline ms-auto"
                                          onsubmit="return confirm('Are you sure you want to delete this user?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link p-0 fw-semibold text-decoration-none" style="color: #dc2626; font-size: 0.78rem;">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5" style="color: #6b7280;">No users found.</div>
                    @endforelse
                </div>

                <div class="px-4 py-3 shf-pagination" style="border-top: 1px solid #f0f0f0;">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
