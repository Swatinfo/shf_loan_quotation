{{--
    Newtheme topbar / header.
    Reuses the .topbar* CSS already defined in public/newtheme/assets/shf-workflow.css.
    Menu visibility is gated on the same permissions used in resources/views/layouts/navigation.blade.php.
--}}
@php
    $u = auth()->user();
    $is = fn (string $key) => ($pageKey ?? '') === $key;

    $first = strtok($u->name, ' ') ?: $u->name;
    $last = trim(substr($u->name, strlen($first)));
    $initials = strtoupper(substr($first, 0, 1).substr($last, 0, 1)) ?: 'U';
    $shortName = trim($first.' '.($last !== '' ? strtoupper($last[0]).'.' : ''));

    $canQuotations = $u->hasPermission('create_quotation') || $u->hasPermission('view_own_quotations') || $u->hasPermission('view_all_quotations');
    $canLoans = $u->hasPermission('view_loans');
    $canDvr = $u->hasPermission('view_dvr');
    $canUsers = $u->hasPermission('view_users');
    $canCustomers = $u->hasPermission('view_customers');
    $canSettings = $u->hasPermission('view_settings') || $u->hasPermission('manage_workflow_config') || $u->isSuperAdmin() || $u->hasPermission('view_activity_log');
@endphp

<header class="topbar">
    <a class="logo" href="{{ route('dashboard') }}" aria-label="SHF World">
        <img src="{{ asset('images/logo3.png') }}" alt="SHF World" class="brand-logo">
    </a>

    <nav class="nav-primary">
        <a class="nav-item {{ $is('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l2-2 7-7 7 7 2 2M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
        </a>

        @if ($canQuotations)
            <a class="nav-item {{ $is('quotations') ? 'active' : '' }}" href="{{ route('quotations.index') }}">
                <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Quotations</span>
            </a>
        @endif

        @if ($canLoans)
            <a class="nav-item {{ $is('loans') ? 'active' : '' }}" href="{{ route('loans.index') }}">
                <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span>Loans</span>
            </a>
        @endif

        <a class="nav-item {{ $is('tasks') ? 'active' : '' }}" href="{{ route('general-tasks.index') }}">
            <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <span>Tasks</span>
        </a>

        @if ($canDvr)
            <a class="nav-item {{ $is('dvr') ? 'active' : '' }}" href="{{ route('dvr.index') }}">
                <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>DVR</span>
            </a>
        @endif

        @if ($canUsers)
            <a class="nav-item {{ $is('users') ? 'active' : '' }}" href="{{ route('users.index') }}">
                <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5 5 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Users</span>
            </a>
        @endif

        @if ($canCustomers)
            <a class="nav-item {{ $is('customers') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 22a8 8 0 1116 0"/></svg>
                <span>Customers</span>
            </a>
        @endif

        <a class="nav-item {{ $is('reports') ? 'active' : '' }}" href="{{ route('reports.turnaround') }}">
            <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Reports</span>
        </a>

        @if ($canSettings)
            <div class="nav-dd-wrap">
                <a class="nav-item {{ $is('settings') ? 'active' : '' }}" href="#">
                    <svg class="i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Settings</span>
                    <svg class="i" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="nav-dd">
                    @if ($u->hasPermission('view_settings'))
                        <a class="nav-dd-item" href="{{ route('settings.index') }}">Quotation Settings</a>
                    @endif
                    @if ($u->hasPermission('manage_workflow_config'))
                        <a class="nav-dd-item" href="{{ route('loan-settings.index') }}">Loan Settings</a>
                    @endif
                    @if ($u->hasPermission('manage_permissions'))
                        <a class="nav-dd-item" href="{{ route('permissions.index') }}">Permissions</a>
                    @endif
                    @if ($u->isSuperAdmin())
                        <a class="nav-dd-item" href="{{ route('roles.index') }}">Roles</a>
                    @endif
                    @if ($u->hasPermission('view_activity_log'))
                        <a class="nav-dd-item" href="{{ route('activity-log') }}">Activity Log</a>
                    @endif
                </div>
            </div>
        @endif
    </nav>

    <div class="topbar-right">
        <div class="search-wrap">
            <svg class="i" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input class="top-search" type="text" placeholder="Search loans, customers, quotations…">
        </div>

        @impersonating
            <a class="icon-btn" href="{{ route('impersonate.leave') }}" title="Leave impersonation" style="color: var(--accent-deep, #c0392b);">
                <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
        @else
            @canImpersonate
                <div class="shf-imp-wrap" style="position:relative;">
                    <button type="button" class="icon-btn" id="shfImpBtn" title="Impersonate User" aria-label="Impersonate User" aria-expanded="false">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div class="shf-imp-pop" id="shfImpPop" role="dialog" aria-label="Impersonate User"
                         style="display:none;position:fixed;top:60px;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 12px 28px rgba(0,0,0,0.18);padding:12px;z-index:1100;box-sizing:border-box;">
                        <div style="font-family:Jost,sans-serif;font-weight:600;font-size:12px;color:var(--ink-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em;">Impersonate User</div>
                        <input type="text" id="shfImpSearch" placeholder="Search by name, email or role…" autocomplete="off"
                               style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:6px;font-size:13px;font-family:Archivo,sans-serif;outline:none;">
                        <div id="shfImpResults" style="max-height:260px;overflow-y:auto;margin-top:8px;font-size:12.5px;color:var(--ink-3);">
                            <span style="color:var(--ink-4);">Type to search users…</span>
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    'use strict';
                    var btn = document.getElementById('shfImpBtn');
                    var pop = document.getElementById('shfImpPop');
                    var inp = document.getElementById('shfImpSearch');
                    var res = document.getElementById('shfImpResults');
                    if (!btn || !pop || !inp || !res) {
                        console.warn('[shf-impersonate] missing elements', { btn: !!btn, pop: !!pop, inp: !!inp, res: !!res });
                        return;
                    }

                    // Use relative paths so the request stays on the current host —
                    // an absolute APP_URL that differs from the browser's host would
                    // navigate cross-origin and drop the session cookie.
                    var URL_SEARCH = '/api/impersonate/users';
                    var URL_TAKE   = '/impersonate/take';
                    var PROMPT     = '<span style="color:var(--ink-4);">Type to search users…</span>';

                    // Portal the popover to <body> so no parent (.topbar sticky/etc.)
                    // can clip it, and so its z-index stacks correctly above the page.
                    document.body.appendChild(pop);
                    pop.style.position = 'fixed';

                    var GAP = 8;        // px below the icon
                    var EDGE = 8;       // px viewport breathing room
                    var TARGET_W = 320; // preferred desktop width

                    function isOpen() { return pop.style.display === 'block'; }
                    function position() {
                        var vw = window.innerWidth;
                        var rect = btn.getBoundingClientRect();

                        // Width: shrink to fit the viewport on phones.
                        var w = Math.min(TARGET_W, vw - 2 * EDGE);
                        pop.style.width = w + 'px';

                        // Vertical: anchor below the icon, clamp to viewport top.
                        pop.style.top = Math.max(EDGE, rect.bottom + GAP) + 'px';

                        // Horizontal: align right edge to icon's right, clamp to
                        // viewport so the panel never overshoots either side.
                        var desiredLeft = rect.right - w;
                        var maxLeft = vw - w - EDGE;
                        var left = Math.max(EDGE, Math.min(desiredLeft, maxLeft));
                        pop.style.left = left + 'px';
                        pop.style.right = 'auto';
                    }
                    function openPop() {
                        position();
                        pop.style.display = 'block';
                        btn.setAttribute('aria-expanded', 'true');
                        inp.value = '';
                        res.innerHTML = PROMPT;
                        setTimeout(function () { inp.focus(); }, 30);
                    }
                    function closePop() {
                        pop.style.display = 'none';
                        btn.setAttribute('aria-expanded', 'false');
                    }

                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (isOpen()) { closePop(); } else { openPop(); }
                    });
                    document.addEventListener('click', function (e) {
                        if (isOpen() && !pop.contains(e.target) && !btn.contains(e.target)) {
                            closePop();
                        }
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && isOpen()) { closePop(); }
                    });
                    window.addEventListener('resize', function () { if (isOpen()) { position(); } });
                    window.addEventListener('orientationchange', function () { if (isOpen()) { position(); } });

                    var timer;
                    inp.addEventListener('input', function () {
                        clearTimeout(timer);
                        var q = this.value.trim();
                        if (q.length < 1) {
                            res.innerHTML = PROMPT;
                            return;
                        }
                        timer = setTimeout(function () {
                            fetch(URL_SEARCH + '?search=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                                .then(function (r) { return r.json(); })
                                .then(function (users) {
                                    if (!Array.isArray(users) || !users.length) {
                                        res.innerHTML = '<div style="padding:8px 4px;color:var(--ink-4);">No users found</div>';
                                        return;
                                    }
                                    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
                                    res.innerHTML = users.map(function (u) {
                                        var role = (u.roles && u.roles.length) ? u.roles.map(function (r) { return r.name; }).join(', ') : '—';
                                        return '<a href="#" class="shf-imp-user" data-id="' + esc(u.id) + '" data-name="' + esc(u.name) + '" ' +
                                                 'style="display:block;padding:8px 6px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink);">' +
                                                 '<strong>' + esc(u.name) + '</strong><br>' +
                                                 '<small style="color:var(--ink-4);">' + esc(u.email) + ' · ' + esc(role) + '</small>' +
                                               '</a>';
                                    }).join('');
                                })
                                .catch(function (err) {
                                    console.error('[shf-impersonate] search failed', err);
                                    res.innerHTML = '<div style="padding:8px 4px;color:var(--red);">Search failed</div>';
                                });
                        }, 300);
                    });

                    res.addEventListener('click', function (e) {
                        var link = e.target.closest('.shf-imp-user');
                        if (!link) { return; }
                        e.preventDefault();
                        var userId   = link.dataset.id;
                        var userName = link.dataset.name;

                        var go = function () { window.location.href = URL_TAKE + '/' + encodeURIComponent(userId); };

                        if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
                            Swal.fire({
                                title: 'Impersonate User?',
                                html: 'You will be logged in as <strong>' + userName + '</strong>.<br><small style="color:#6b7280;">You can return to your account anytime by clicking "Leave".</small>',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#f15a29',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Yes, impersonate',
                                cancelButtonText: 'Cancel',
                                focusCancel: true,
                            }).then(function (r) { if (r && r.isConfirmed) { go(); } });
                        } else if (window.confirm('Impersonate ' + userName + '?')) {
                            go();
                        }
                    });
                })();
                </script>
            @endCanImpersonate
        @endImpersonating

        <a class="icon-btn" href="{{ route('notifications.index') }}" title="Notifications">
            <svg class="i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span class="dot js-notif-badge d-none">0</span>
        </a>

        <span class="role-pill">
            <svg class="i" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4M12 21a9 9 0 01-9-9V5l9-3 9 3v7a9 9 0 01-9 9z"/></svg>
            <span class="role-pill-text">{{ $u->role_label }}</span>
        </span>

        <div class="nav-dd-wrap user-chip-wrap">
            <button type="button" class="user-chip" id="shfUserChipBtn" aria-haspopup="true" aria-expanded="false">
                <span class="avatar">{{ $initials }}</span>
                <span>{{ $shortName }}</span>
                <svg class="i" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-left:2px;opacity:0.6;"><path d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="nav-dd user-chip-dd" role="menu">
                <a class="nav-dd-item" href="{{ route('profile.edit') }}" role="menuitem">
                    <svg class="i" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;vertical-align:middle;opacity:0.7;"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profile
                </a>
                @if ($u->hasPermission('change_own_password'))
                    <a class="nav-dd-item" href="{{ route('profile.edit') }}#password" role="menuitem">
                        <svg class="i" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;vertical-align:middle;opacity:0.7;"><path d="M15 7a2 2 0 012 2M9 19v-1a2 2 0 012-2h2a2 2 0 012 2v1M8 12a5 5 0 1110 0 5 5 0 01-10 0z"/></svg>
                        Change Password
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="nav-dd-item" role="menuitem" style="background:none;border:none;width:100%;text-align:left;cursor:pointer;font-family:inherit;">
                        <svg class="i" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;vertical-align:middle;opacity:0.7;"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Log Out
                    </button>
                </form>
            </div>
        </div>
        <script>
            (function () {
                var btn = document.getElementById('shfUserChipBtn');
                var wrap = btn && btn.closest('.user-chip-wrap');
                if (!btn || !wrap || btn.dataset.shfBound) { return; }
                btn.dataset.shfBound = '1';
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var open = wrap.classList.toggle('open');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.addEventListener('click', function (e) {
                    if (!wrap.contains(e.target)) {
                        wrap.classList.remove('open');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && wrap.classList.contains('open')) {
                        wrap.classList.remove('open');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
            })();
        </script>
    </div>
</header>
