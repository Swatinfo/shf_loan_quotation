<nav class="navbar navbar-expand-xl navbar-dark position-relative"
    style="background: rgba(58, 53, 54, 0.85); backdrop-filter: blur(10px); box-shadow: 0 2px 20px rgba(0,0,0,0.15); border-bottom: 3px solid #f15a29; z-index: 1030;">
    <div class="container-fluid px-3 px-lg-4">
        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2 py-0" href="<?php echo e(route('dashboard')); ?>">
            <img src="<?php echo e(asset('images/logo3.png')); ?>" alt="SHF" style="height: 28px; width: auto;">
        </a>

        <!-- Mobile-only icons (beside hamburger) -->
        <div class="d-flex d-xl-none align-items-center gap-2 ms-auto me-2">
            <span class="shf-badge shf-badge-username shf-text-2xs <?php echo e(auth()->user()->isSuperAdmin() ? 'shf-badge-orange' : (auth()->user()->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray')); ?>">
                <?php echo e(auth()->user()->role_label); ?>

            </span>

            <?php if (is_impersonating()) : ?>
                <a href="<?php echo e(route('impersonate.leave')); ?>"
                    class="btn btn-warning btn-sm d-flex align-items-center gap-1 py-1 px-2"
                    style="font-size: 0.75rem;">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Leave
                </a>
            <?php else: ?>
                <?php if (can_impersonate()) : ?>
                    <div class="dropdown" id="mobileImpersonateDropdown">
                        <a class="nav-link p-1 shf-text-white-70" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false" title="Impersonate User">
                            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="width: 300px; padding: 0.75rem;">
                            <h6 class="dropdown-header px-0">Impersonate User</h6>
                            <input type="text" class="form-control form-control-sm mb-2 shf-mobile-impersonate-search"
                                placeholder="Search by name, email or role..." autocomplete="off">
                            <div class="shf-mobile-impersonate-results" style="max-height: 250px; overflow-y: auto;">
                                <small class="text-muted">Type to search users...</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Hamburger -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
            data-bs-target="#shfNavbar" aria-controls="shfNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="shfNavbar">
            <!-- Desktop Nav Links -->
            <ul class="navbar-nav me-auto gap-3 d-none d-xl-flex" style="margin-left: 1.5rem;">
                <li class="nav-item">
                    <a class="nav-link <?php echo e(request()->routeIs('dashboard') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                        href="<?php echo e(route('dashboard')); ?>">
                        <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                </li>

                <?php if(auth()->user()->hasPermission('create_quotation')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('quotations.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                            href="<?php echo e(route('quotations.create')); ?>">
                            <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            New Quotation
                        </a>
                    </li>
                <?php endif; ?>

                <?php if(auth()->user()->hasPermission('view_loans')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('loans.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                            href="<?php echo e(route('loans.index')); ?>">
                            <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            Loans
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link <?php echo e(request()->routeIs('general-tasks.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                        href="<?php echo e(route('general-tasks.index')); ?>">
                        <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Tasks
                    </a>
                </li>

                <?php if(auth()->user()->hasPermission('view_dvr')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('dvr.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                            href="<?php echo e(route('dvr.index')); ?>">
                            <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            DVR
                        </a>
                    </li>
                <?php endif; ?>

                <?php if(auth()->user()->hasPermission('view_users')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('users.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                            href="<?php echo e(route('users.index')); ?>">
                            <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Users
                        </a>
                    </li>
                <?php endif; ?>

                
                <?php if(auth()->user()->hasPermission('view_settings') || auth()->user()->hasPermission('manage_workflow_config') || auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('view_activity_log')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo e(request()->routeIs('settings.*') || request()->routeIs('permissions.*') || request()->routeIs('loan-settings.*') || request()->routeIs('roles.*') || request()->routeIs('activity-log*') || request()->routeIs('reports.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>"
                            href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <?php if(auth()->user()->hasPermission('view_settings')): ?>
                                <li><a class="dropdown-item <?php echo e(request()->routeIs('settings.*') || request()->routeIs('permissions.*') ? 'active' : ''); ?>" href="<?php echo e(route('settings.index')); ?>">Quotation Settings</a></li>
                            <?php endif; ?>
                            <?php if(auth()->user()->hasPermission('manage_workflow_config')): ?>
                                <li><a class="dropdown-item <?php echo e(request()->routeIs('loan-settings.*') ? 'active' : ''); ?>" href="<?php echo e(route('loan-settings.index')); ?>">Loan Settings</a></li>
                            <?php endif; ?>
                            <?php if(auth()->user()->isSuperAdmin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?php echo e(request()->routeIs('roles.*') ? 'active' : ''); ?>" href="<?php echo e(route('roles.index')); ?>">Roles</a></li>
                            <?php endif; ?>
                            <?php if(auth()->user()->hasPermission('view_activity_log')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?php echo e(request()->routeIs('activity-log*') ? 'active' : ''); ?>" href="<?php echo e(route('activity-log')); ?>">Activity Log</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                
                <li class="nav-item">
                    <a class="nav-link <?php echo e(request()->routeIs('reports.*') ? 'shf-nav-active' : 'shf-nav-link'); ?>" href="<?php echo e(route('reports.turnaround')); ?>">
                        <svg class="me-1 shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Reports
                    </a>
                </li>
            </ul>

            <!-- Desktop User Menu (Dropdown) -->
            <div class="d-none d-xl-flex align-items-center ms-auto gap-2">
                
                <?php if (is_impersonating()) : ?>
                    <a href="<?php echo e(route('impersonate.leave')); ?>"
                        class="btn btn-warning btn-sm d-flex align-items-center gap-1 text-nowrap"
                        style="font-size: 0.8rem;">
                        <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Impersonating <strong><?php echo e(Auth::user()->name); ?></strong></span>
                        <span class="badge bg-dark ms-1 shf-text-2xs">Leave</span>
                    </a>
                <?php endif; ?>

                
                <?php if (can_impersonate()) : ?>
                <?php if(!app('impersonate')->isImpersonating()): ?>
                    <div class="dropdown">
                        <a class="nav-link p-1 shf-text-white-70" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false" title="Impersonate User">
                            <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="width: 340px; padding: 0.75rem;">
                            <h6 class="dropdown-header px-0">Impersonate User</h6>
                            <input type="text" class="form-control form-control-sm mb-2" id="impersonateSearch"
                                placeholder="Search by name, email or role..." autocomplete="off">
                            <div id="impersonateResults" style="max-height: 250px; overflow-y: auto;">
                                <small class="text-muted">Type to search users...</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>

                
                <a class="nav-link p-1 position-relative shf-text-white-70" href="<?php echo e(route('notifications.index')); ?>"
                    title="Notifications">
                    <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span
                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none shf-text-2xs"
                        id="notifBadge" style="padding: 2px 5px;"></span>
                </a>

                <span
                    class="shf-badge me-1 shf-badge-username <?php echo e(auth()->user()->isSuperAdmin() ? 'shf-badge-orange' : (auth()->user()->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray')); ?>">
                    <?php echo e(auth()->user()->role_label); ?>

                </span>

                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none dropdown-toggle p-0" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        style="color: rgba(255,255,255,0.8); font-size: 0.875rem; font-weight: 500;">
                        <?php echo e(Auth::user()->name); ?>

                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 160px;">
                        <li>
                            <a class="dropdown-item" href="<?php echo e(route('profile.edit')); ?>">
                                <svg class="me-1" style="width:14px;height:14px;display:inline;color:#9ca3af;"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Profile
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="dropdown-item">
                                    <svg class="me-1" style="width:14px;height:14px;display:inline;color:#9ca3af;"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Log Out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Mobile Responsive Links -->
            <div class="d-xl-none pt-2 pb-3">
                
                <?php if (is_impersonating()) : ?>
                    <div class="px-2 mb-2">
                        <a href="<?php echo e(route('impersonate.leave')); ?>"
                            class="btn btn-warning btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                            <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Impersonating <strong><?php echo e(Auth::user()->name); ?></strong>
                            <span class="badge bg-dark ms-1">Leave</span>
                        </a>
                    </div>
                <?php endif; ?>

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('dashboard') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                            href="<?php echo e(route('dashboard')); ?>">Dashboard</a>
                    </li>

                    <?php if(auth()->user()->hasPermission('create_quotation')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('quotations.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('quotations.create')); ?>">New
                                Quotation</a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('view_loans')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('loans.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('loans.index')); ?>">Loans</a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('general-tasks.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                            href="<?php echo e(route('general-tasks.index')); ?>">Tasks</a>
                    </li>

                    <?php if(auth()->user()->hasPermission('view_dvr')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('dvr.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('dvr.index')); ?>">DVR</a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('view_users')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('users.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('users.index')); ?>">Users</a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('view_settings')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('settings.*') || request()->routeIs('permissions.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('settings.index')); ?>">Quotation Settings</a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('manage_workflow_config')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('loan-settings.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('loan-settings.index')); ?>">Loan Settings</a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->isSuperAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('roles.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('roles.index')); ?>">Roles</a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('view_activity_log')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('activity-log') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                                href="<?php echo e(route('activity-log')); ?>">Activity Log</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('reports.*') ? 'active fw-bold' : ''); ?> shf-text-white-70"
                            href="<?php echo e(route('reports.turnaround')); ?>">Reports</a>
                    </li>
                </ul>

                <!-- Mobile User Info -->
                <div class="border-top mt-3 pt-3" style="border-color: rgba(255,255,255,0.1) !important;">
                    <div class="px-2 d-flex align-items-center gap-3">
                        <div>
                            <div class="fw-medium" style="color: #fff;"><?php echo e(Auth::user()->name); ?></div>
                            <div class="small" style="color: rgba(255,255,255,0.5);"><?php echo e(Auth::user()->email); ?></div>
                        </div>
                        <span
                            class="shf-badge ms-auto shf-badge-username <?php echo e(auth()->user()->isSuperAdmin() ? 'shf-badge-orange ' : (auth()->user()->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray')); ?>">
                            <?php echo e(auth()->user()->role_label); ?>

                        </span>
                    </div>
                    <ul class="navbar-nav mt-2">
                        <li class="nav-item">
                            <a class="nav-link shf-text-white-70" href="<?php echo e(route('profile.edit')); ?>">Profile</a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <a class="nav-link" style="color: rgba(255,255,255,0.7); cursor: pointer;"
                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                    href="<?php echo e(route('logout')); ?>">Log Out</a>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>


<script>
    (function() {
        function updateNotifBadge() {
            fetch('<?php echo e(route('api.notifications.count')); ?>')
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    var badge = document.getElementById('notifBadge');
                    if (!badge) return;
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }).catch(function() {});
        }
        updateNotifBadge();
        setInterval(updateNotifBadge, 60000);
    })();
</script>

<?php if (can_impersonate()) : ?>
<?php if(!app('impersonate')->isImpersonating()): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize impersonate search for both desktop and mobile dropdowns
            var pairs = [
                { search: document.getElementById('impersonateSearch'), results: document.getElementById('impersonateResults') },
                { search: document.querySelector('.shf-mobile-impersonate-search'), results: document.querySelector('.shf-mobile-impersonate-results') }
            ];

            pairs.forEach(function(pair) {
                var searchInput = pair.search;
                var resultsDiv = pair.results;
                if (!searchInput || !resultsDiv) return;

                var timer;
                searchInput.addEventListener('input', function() {
                    clearTimeout(timer);
                    var search = this.value.trim();
                    if (search.length < 1) {
                        resultsDiv.innerHTML = '<small class="text-muted">Type to search users...</small>';
                        return;
                    }
                    timer = setTimeout(function() {
                        fetch('<?php echo e(route('impersonate.users')); ?>?search=' + encodeURIComponent(search))
                            .then(function(r) {
                                return r.json();
                            })
                            .then(function(users) {
                                if (!users.length) {
                                    resultsDiv.innerHTML =
                                        '<small class="text-muted">No users found</small>';
                                    return;
                                }
                                var html = '';
                                users.forEach(function(u) {
                                    var name = u.name.replace(/</g, '&lt;');
                                    var email = u.email.replace(/</g, '&lt;');
                                    var role = (u.roles && u.roles.length) ? u.roles.map(function(r) { return r.name; }).join(', ') : '—';
                                    html +=
                                        '<a href="#" class="dropdown-item py-2 border-bottom shf-impersonate-user" ' +
                                        'data-id="' + u.id + '" data-name="' + name.replace(
                                            /"/g, '&quot;') + '">' +
                                        '<strong>' + name + '</strong><br>' +
                                        '<small class="text-muted">' + email +
                                        ' &middot; ' + role + '</small>' +
                                        '</a>';
                                });
                                resultsDiv.innerHTML = html;
                            });
                    }, 300);
                });

                // SweetAlert confirmation on user click
                resultsDiv.addEventListener('click', function(e) {
                    var link = e.target.closest('.shf-impersonate-user');
                    if (!link) return;
                    e.preventDefault();

                    var userId = link.dataset.id;
                    var userName = link.dataset.name;

                    Swal.fire({
                        title: 'Impersonate User?',
                        html: 'You will be logged in as <strong>' + userName +
                            '</strong>.<br><small class="text-muted">You can return to your account anytime by clicking "Leave".</small>',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#f15a29',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, impersonate',
                        cancelButtonText: 'Cancel',
                        focusCancel: true
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            window.location.href = '/impersonate/take/' + userId;
                        }
                    });
                });

                // Auto-focus search when dropdown opens
                var dropdown = searchInput.closest('.dropdown');
                if (dropdown) {
                    dropdown.addEventListener('shown.bs.dropdown', function() {
                        searchInput.focus();
                        searchInput.value = '';
                        resultsDiv.innerHTML = '<small class="text-muted">Type to search users...</small>';
                    });
                }
            });
        });
    </script>
<?php endif; ?>
<?php endif; ?>
<?php /**PATH F:\G Drive\Projects\quotationshf\resources\views/layouts/navigation.blade.php ENDPATH**/ ?>