<nav class="navbar navbar-expand-lg navbar-dark position-relative" style="background: rgba(58, 53, 54, 0.85); backdrop-filter: blur(10px); box-shadow: 0 2px 20px rgba(0,0,0,0.15); border-bottom: 3px solid #f15a29; z-index: 50;">
    <div class="container-fluid px-3 px-lg-4">
        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2 py-0" href="{{ route('dashboard') }}">
            <img src="/images/logo3.png" alt="SHF" style="height: 28px; width: auto;">
        </a>

        <!-- Hamburger -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#shfNavbar" aria-controls="shfNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="shfNavbar">
            <!-- Desktop Nav Links -->
            <ul class="navbar-nav me-auto gap-3 d-none d-lg-flex" style="margin-left: 1.5rem;">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'shf-nav-active' : 'shf-nav-link' }}" href="{{ route('dashboard') }}">
                        <svg class="me-1" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                </li>

                @if (auth()->user()->hasPermission('create_quotation'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('quotations.*') ? 'shf-nav-active' : 'shf-nav-link' }}" href="{{ route('quotations.create') }}">
                            <svg class="me-1" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Quotation
                        </a>
                    </li>
                @endif

                @if (auth()->user()->hasPermission('view_users'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('users.*') ? 'shf-nav-active' : 'shf-nav-link' }}" href="{{ route('users.index') }}">
                            <svg class="me-1" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Users
                        </a>
                    </li>
                @endif

                @if (auth()->user()->hasPermission('view_settings'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('settings.*') ? 'shf-nav-active' : 'shf-nav-link' }}" href="{{ route('settings.index') }}">
                            <svg class="me-1" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </a>
                    </li>
                @endif

                @if (auth()->user()->hasPermission('manage_permissions'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('permissions.*') ? 'shf-nav-active' : 'shf-nav-link' }}" href="{{ route('permissions.index') }}">
                            <svg class="me-1" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Permissions
                        </a>
                    </li>
                @endif

                @if (auth()->user()->hasPermission('view_activity_log'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('activity-log') ? 'shf-nav-active' : 'shf-nav-link' }}" href="{{ route('activity-log') }}">
                            <svg class="me-1" style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Activity Log
                        </a>
                    </li>
                @endif
            </ul>

            <!-- Desktop User Menu (Dropdown) -->
            <div class="d-none d-lg-flex align-items-center ms-auto">
                <span class="shf-badge me-3 {{ auth()->user()->isSuperAdmin() ? 'shf-badge-orange' : (auth()->user()->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray') }}">
                    {{ auth()->user()->role_label }}
                </span>

                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none dropdown-toggle p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: rgba(255,255,255,0.8); font-size: 0.875rem; font-weight: 500;">
                        {{ Auth::user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 160px;">
                        <li>
                            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                <svg class="me-1" style="width:14px;height:14px;display:inline;color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <svg class="me-1" style="width:14px;height:14px;display:inline;color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Log Out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Mobile Responsive Links -->
            <div class="d-lg-none pt-2 pb-3">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-bold' : '' }}" style="color: rgba(255,255,255,0.7);" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>

                    @if (auth()->user()->hasPermission('create_quotation'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('quotations.*') ? 'active fw-bold' : '' }}" style="color: rgba(255,255,255,0.7);" href="{{ route('quotations.create') }}">New Quotation</a>
                        </li>
                    @endif

                    @if (auth()->user()->hasPermission('view_users'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active fw-bold' : '' }}" style="color: rgba(255,255,255,0.7);" href="{{ route('users.index') }}">Users</a>
                        </li>
                    @endif

                    @if (auth()->user()->hasPermission('view_settings'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active fw-bold' : '' }}" style="color: rgba(255,255,255,0.7);" href="{{ route('settings.index') }}">Settings</a>
                        </li>
                    @endif

                    @if (auth()->user()->hasPermission('manage_permissions'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('permissions.*') ? 'active fw-bold' : '' }}" style="color: rgba(255,255,255,0.7);" href="{{ route('permissions.index') }}">Permissions</a>
                        </li>
                    @endif

                    @if (auth()->user()->hasPermission('view_activity_log'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('activity-log') ? 'active fw-bold' : '' }}" style="color: rgba(255,255,255,0.7);" href="{{ route('activity-log') }}">Activity Log</a>
                        </li>
                    @endif
                </ul>

                <!-- Mobile User Info -->
                <div class="border-top mt-3 pt-3" style="border-color: rgba(255,255,255,0.1) !important;">
                    <div class="px-2 d-flex align-items-center gap-3">
                        <div>
                            <div class="fw-medium" style="color: #fff;">{{ Auth::user()->name }}</div>
                            <div class="small" style="color: rgba(255,255,255,0.5);">{{ Auth::user()->email }}</div>
                        </div>
                        <span class="shf-badge ms-auto {{ auth()->user()->isSuperAdmin() ? 'shf-badge-orange' : (auth()->user()->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray') }}">
                            {{ auth()->user()->role_label }}
                        </span>
                    </div>
                    <ul class="navbar-nav mt-2">
                        <li class="nav-item">
                            <a class="nav-link" style="color: rgba(255,255,255,0.7);" href="{{ route('profile.edit') }}">Profile</a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <a class="nav-link" style="color: rgba(255,255,255,0.7); cursor: pointer;" onclick="event.preventDefault(); this.closest('form').submit();" href="{{ route('logout') }}">Log Out</a>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
