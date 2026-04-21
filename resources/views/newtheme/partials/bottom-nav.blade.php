{{--
    Newtheme mobile bottom navigation.
    CSS already exists in public/newtheme/assets/shf-workflow.css for
    .m-bottomnav, .bn-item, .shf-more-sheet, .shf-more-backdrop, .shf-more-item.
    Visible on tablets + phones (≤1024px) per shf-workflow.css media rule.
--}}
@php
    $u = auth()->user();

    $canQuotations = $u->hasPermission('create_quotation') || $u->hasPermission('view_own_quotations') || $u->hasPermission('view_all_quotations');

    $primaries = collect([
        ['key' => 'dashboard',  'label' => 'Dashboard',  'url' => route('dashboard'),                                            'active' => request()->routeIs('dashboard'),       'show' => true],
        ['key' => 'quotations', 'label' => 'Quotations', 'url' => $canQuotations ? route('quotations.index') : '#',              'active' => request()->routeIs('quotations.*'),    'show' => $canQuotations],
        ['key' => 'loans',      'label' => 'Loans',      'url' => $u->hasPermission('view_loans') ? route('loans.index') : '#', 'active' => request()->routeIs('loans.*'),         'show' => $u->hasPermission('view_loans')],
        ['key' => 'dvr',        'label' => 'DVR',        'url' => $u->hasPermission('view_dvr')   ? route('dvr.index')   : '#', 'active' => request()->routeIs('dvr.*'),           'show' => $u->hasPermission('view_dvr')],
        ['key' => 'tasks',      'label' => 'Tasks',      'url' => route('general-tasks.index'),                                  'active' => request()->routeIs('general-tasks.*'), 'show' => true],
    ])->filter(fn ($p) => $p['show'])->values();

    $moreActive = request()->routeIs('customers.*', 'users.*', 'settings.*', 'permissions.*', 'loan-settings.*', 'activity-log*', 'reports.*', 'profile.*', 'notifications.*', 'roles.*');

    // Per-key SVG path used to render a small inline icon.
    $icon = [
        'dashboard'  => 'M3 12l2-2 7-7 7 7 2 2M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'quotations' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'loans'      => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'dvr'        => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
        'tasks'      => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    ];
@endphp

<nav class="m-bottomnav" aria-label="Main navigation">
    @foreach ($primaries as $p)
        <a class="bn-item {{ $p['active'] ? 'active' : '' }}" href="{{ $p['url'] }}" aria-label="{{ $p['label'] }}">
            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon[$p['key']] }}"/></svg>
            <span>{{ $p['label'] }}</span>
        </a>
    @endforeach

    <button type="button" class="bn-item {{ $moreActive ? 'active' : '' }}" id="shfMoreBtn" aria-label="More menu">
        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span>More</span>
    </button>
</nav>

<div class="shf-more-backdrop" id="shfMoreBackdrop" aria-hidden="true"></div>

<div class="shf-more-sheet" id="shfMoreSheet" role="dialog" aria-label="More menu">
    <div class="shf-more-hd">
        <h3>More</h3>
        <button type="button" class="icon-btn" id="shfMoreClose" aria-label="Close">
            <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="shf-more-body">
        @if ($u->hasPermission('view_customers'))
            <a class="shf-more-item" href="{{ route('customers.index') }}">Customers</a>
        @endif
        @if ($u->hasPermission('view_users'))
            <a class="shf-more-item" href="{{ route('users.index') }}">Users</a>
        @endif
        <a class="shf-more-item" href="{{ route('notifications.index') }}">Notifications</a>
        @if ($u->hasPermission('view_settings'))
            <a class="shf-more-item" href="{{ route('settings.index') }}">Quotation Settings</a>
        @endif
        @if ($u->hasPermission('manage_workflow_config'))
            <a class="shf-more-item" href="{{ route('loan-settings.index') }}">Loan Settings</a>
        @endif
        @if ($u->hasPermission('manage_permissions'))
            <a class="shf-more-item" href="{{ route('permissions.index') }}">Permissions</a>
            <a class="shf-more-item" href="{{ route('roles.index') }}">Roles</a>
        @endif
        @if ($u->hasPermission('view_activity_log'))
            <a class="shf-more-item" href="{{ route('activity-log') }}">Activity Log</a>
        @endif
        @if ($u->hasPermission('view_reports'))
            <a class="shf-more-item" href="{{ route('reports.turnaround') }}">Reports</a>
        @endif
        <a class="shf-more-item" href="{{ route('profile.edit') }}">Profile</a>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="shf-more-item shf-more-danger">Log Out</button>
        </form>
    </div>
</div>
