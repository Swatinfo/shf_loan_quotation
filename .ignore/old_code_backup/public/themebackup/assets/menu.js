// Shared UI helpers — vanilla JS, no framework
(function () {
  'use strict';

  // Icons (inline SVG generator)
  const iconPaths = {
    dashboard: 'M3 12l2-2 7-7 7 7 2 2M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    file: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    folder: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    task: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    pin: 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
    users: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5 5 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    user: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 22a8 8 0 1116 0',
    bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    cog: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    search: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    plus: 'M12 4v16m8-8H4',
    check: 'M5 13l4 4L19 7',
    x: 'M6 18L18 6M6 6l12 12',
    chevron: 'M9 5l7 7-7 7',
    chevronDown: 'M19 9l-7 7-7-7',
    chevronUp: 'M5 15l7-7 7 7',
    arrow: 'M14 5l7 7m0 0l-7 7m7-7H3',
    download: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 12V4m0 8l-4-4m4 4l4-4',
    upload: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v8m0-8l-4 4m4-4l4 4',
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    trash: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    eye: 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    cal: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    phone: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11 11 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
    mail: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    more: 'M12 5v.01M12 12v.01M12 19v.01',
    menu: 'M4 6h16M4 12h16M4 18h16',
    filter: 'M3 4a1 1 0 011-1h16a1 1 0 01.78 1.625l-6.28 7.85V20a1 1 0 01-1.45.894l-4-2A1 1 0 019 18v-5.525L2.22 4.625A1 1 0 013 4z',
    refresh: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    warn: 'M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16a2 2 0 001.73 3z',
    link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    logout: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    bank: 'M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m4-11v11m4-11v11m4-11v11m4-11v11',
    building: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    bar: 'M12 20V10M18 20V4M6 20v-4',
    flash: 'M13 10V3L4 14h7v7l9-11h-7z',
    shield: 'M9 12l2 2 4-4M12 21a9 9 0 01-9-9V5l9-3 9 3v7a9 9 0 01-9 9z',
    key: 'M15 7a2 2 0 012 2M9 19v-1a2 2 0 012-2h2a2 2 0 012 2v1M8 12a5 5 0 1110 0 5 5 0 01-10 0z',
    doc: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2zM13 3v6h6',
    paperclip: 'M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13',
    star: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
    send: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
    msg: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    rupee: 'M6 3h12M6 8h12m-9 4h3c2 0 4 1 4 4s-2 4-4 4H8l-4-4 5-4',
    activity: 'M22 12h-4l-3 9L9 3l-3 9H2',
    ext: 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14',
    list: 'M4 6h16M4 12h16M4 18h7',
    grid: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    home: 'M3 12l2-2 7-7 7 7 2 2M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    copy: 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
    sig: 'M3 21v-4a4 4 0 014-4h4m4 0l5-5a2.828 2.828 0 00-4-4l-5 5v4h4z',
    flag: 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9',
    play: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    target: 'M10 6H5a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7.586-5l7-7M22 3l-8.5 8.5-3-3L22 3z',
    fp: 'M12 11v2a4 4 0 01-4 4m8-6v2a8 8 0 01-8 8m4-10V8a4 4 0 018 0v4m-4 4a4 4 0 01-4-4V8a4 4 0 014-4 4 4 0 014 4m-8 8a8 8 0 01-4-2',
    book: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    globe: 'M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
    lock: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z',
    sun: 'M12 3v2m0 14v2M5.6 5.6l1.4 1.4m10 10l1.4 1.4M3 12h2m14 0h2M5.6 18.4l1.4-1.4m10-10l1.4-1.4M16 12a4 4 0 11-8 0 4 4 0 018 0z',
    moon: 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z',
    net: 'M4 5a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm0 10a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zm10-10a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2h-4a2 2 0 01-2-2V5zm0 10a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2h-4a2 2 0 01-2-2v-4z',
    impersonate: 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  };

  function icon(name, size) {
    const p = iconPaths[name];
    if (!p) return '';
    const s = size || 16;
    return `<svg class="i" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="${p}"/></svg>`;
  }
  window.icon = icon;

  // Navigation items (desktop + mobile)
  const NAV_ITEMS = [
    { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html', icon: 'dashboard' },
    { key: 'quotations', label: 'Quotations', href: 'quotations.html', icon: 'file' },
    { key: 'loans', label: 'Loans', href: 'loans.html', icon: 'folder' },
    { key: 'tasks', label: 'Tasks', href: 'general-tasks.html', icon: 'task' },
    { key: 'dvr', label: 'DVR', href: 'dvr.html', icon: 'pin' },
    { key: 'users', label: 'Users', href: 'users.html', icon: 'users' },
    { key: 'customers', label: 'Customers', href: 'customers.html', icon: 'user' },
    { key: 'reports', label: 'Reports', href: 'reports.html', icon: 'chart' },
    { key: 'settings', label: 'Settings', href: 'settings-hub.html', icon: 'cog', dropdown: [
      { label: 'All settings', href: 'settings-hub.html' },
      { label: 'Quotation Settings', href: 'settings.html' },
      { label: 'Loan Settings', href: 'loan-settings.html' },
      { label: 'Permissions', href: 'permissions.html' },
      { label: 'Roles', href: 'roles.html' },
      { label: 'Activity Log', href: 'activity-log.html' },
    ] },
  ];

  function renderTopbar(activeKey) {
    const navHtml = NAV_ITEMS.map((n) => {
      const isActive = n.key === activeKey ? 'active' : '';
      if (n.dropdown) {
        const items = n.dropdown.map(d => `<a class="nav-dd-item" href="${d.href}">${d.label}</a>`).join('');
        return `<div class="nav-dd-wrap"><a class="nav-item ${isActive}" href="${n.href}">${icon(n.icon, 15)}<span>${n.label}</span>${icon('chevronDown', 12)}</a><div class="nav-dd">${items}</div></div>`;
      }
      return `<a class="nav-item ${isActive}" href="${n.href}">${icon(n.icon, 15)}<span>${n.label}</span></a>`;
    }).join('');

    return `
<header class="topbar">
  <a class="logo" href="dashboard.html" aria-label="SHF World">
    <img src="/images/logo3.png" alt="SHF World" class="brand-logo">
  </a>
  <nav class="nav-primary">${navHtml}</nav>
  <div class="topbar-right">
    <div class="search-wrap">${icon('search', 14)}<input class="top-search" placeholder="Search loans, customers, quotations…"></div>
    <button type="button" class="icon-btn" id="shfImpersonateBtn" title="Impersonate User" aria-label="Impersonate User">${icon('impersonate', 18)}</button>
    <a class="icon-btn" href="notifications.html" title="Notifications">${icon('bell', 16)}<span class="dot">3</span></a>
    <span class="role-pill">${icon('shield', 11)} <span class="role-pill-text">Branch Manager</span></span>
    <a class="user-chip" href="profile.html"><span class="avatar">RP</span><span>Rahul P.</span></a>
  </div>
</header>`;
  }
  window.renderTopbar = renderTopbar;

  // Bottom nav: 4 primary items + More (opens offcanvas). Mirrors
  // resources/views/partials/bottom-nav.blade.php from the live app.
  function renderMobileBottomNav(activeKey) {
    const primaries = [
      { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html',     icon: 'dashboard' },
      { key: 'loans',     label: 'Loans',     href: 'loans.html',         icon: 'folder' },
      { key: 'dvr',       label: 'DVR',       href: 'dvr.html',           icon: 'pin' },
      { key: 'tasks',     label: 'Tasks',     href: 'general-tasks.html', icon: 'task' },
    ];
    const moreActiveKeys = ['quotations', 'users', 'customers', 'reports', 'settings', 'notifications'];
    const moreActive = moreActiveKeys.includes(activeKey);
    const navHtml = primaries.map(p => {
      const a = p.key === activeKey ? 'active' : '';
      return `<a class="bn-item ${a}" href="${p.href}">${icon(p.icon, 20)}<span>${p.label}</span></a>`;
    }).join('');

    const moreItems = [
      { label: 'Quotations',         href: 'quotations.html' },
      { label: 'Customers',          href: 'customers.html' },
      { label: 'Users',              href: 'users.html' },
      { label: 'Notifications',      href: 'notifications.html' },
      { label: 'Quotation Settings', href: 'settings.html' },
      { label: 'Loan Settings',      href: 'loan-settings.html' },
      { label: 'Permissions',        href: 'permissions.html' },
      { label: 'Roles',              href: 'roles.html' },
      { label: 'Activity Log',       href: 'activity-log.html' },
      { label: 'Reports',            href: 'reports.html' },
      { label: 'Profile',            href: 'profile.html' },
    ];
    const sheetHtml = moreItems.map(m =>
      `<a class="shf-more-item" href="${m.href}">${m.label}</a>`
    ).join('');

    return `
<nav class="m-bottomnav" aria-label="Main navigation">
  ${navHtml}
  <button type="button" class="bn-item ${moreActive ? 'active' : ''}" id="shfMoreBtn" aria-label="More menu">${icon('menu', 20)}<span>More</span></button>
</nav>
<div class="shf-more-backdrop" id="shfMoreBackdrop" aria-hidden="true"></div>
<div class="shf-more-sheet" id="shfMoreSheet" role="dialog" aria-label="More menu">
  <div class="shf-more-hd">
    <h3>More</h3>
    <button type="button" class="icon-btn" id="shfMoreClose" aria-label="Close">${icon('x', 18)}</button>
  </div>
  <div class="shf-more-body">
    ${sheetHtml}
    <button type="button" class="shf-more-item shf-more-danger" onclick="if(window.shfToast)window.shfToast('Logout (demo)');">Log Out</button>
  </div>
</div>`;
  }
  window.renderMobileBottomNav = renderMobileBottomNav;

  // Tweaks panel — allows jumping between loan stages (global context)
  const STAGES = [
    { n: 1, key: 'inquiry', en: 'Inquiry', gu: 'પૂછપરછ' },
    { n: 2, key: 'doc-sel', en: 'Document Selection', gu: 'દસ્તાવેજ પસંદગી' },
    { n: 3, key: 'doc-col', en: 'Document Collection', gu: 'દસ્તાવેજ સંગ્રહ' },
    { n: 4, key: 'parallel', en: 'Parallel Processing', gu: 'સમાંતર પ્રક્રિયા' },
    { n: 5, key: 'rate-pf', en: 'Rate & PF', gu: 'વ્યાજ દર અને પી.એફ.' },
    { n: 6, key: 'sanction', en: 'Sanction Letter', gu: 'મંજૂરી પત્ર' },
    { n: 7, key: 'docket', en: 'Docket Login', gu: 'ડોકેટ લૉગિન' },
    { n: 8, key: 'kfs', en: 'KFS', gu: 'કેએફએસ' },
    { n: 9, key: 'esign', en: 'E-Sign & eNACH', gu: 'ઈ-સાઇન અને ઈનેક' },
    { n: 10, key: 'disb', en: 'Disbursement', gu: 'વિતરણ' },
    { n: 11, key: 'otc', en: 'OTC Clearance', gu: 'ઓ.ટી.સી. મંજૂરી' },
    { n: 12, key: 'done', en: 'Completed', gu: 'પૂર્ણ' },
  ];
  window.SHF_STAGES = STAGES;

  function getActiveStage() {
    const s = localStorage.getItem('shf.stage');
    const n = parseInt(s, 10);
    return (n >= 1 && n <= 12) ? n : 4;
  }
  function setActiveStage(n) {
    localStorage.setItem('shf.stage', String(n));
  }
  window.getActiveStage = getActiveStage;
  window.setActiveStage = setActiveStage;

  // Tweaks button removed per user request. Stage selector remains accessible via
  // localStorage (window.setActiveStage) if needed programmatically.
  function renderTweaks() { return ''; }
  window.renderTweaks = renderTweaks;

  // Floating Create FAB — Quotation / Task / New Visit (DVR). Site-wide.
  function renderCreateFab() {
    const items = [
      { key: 'quotation', label: 'New Quotation', href: 'quotation-new.html', iconCls: 'shf-fab-icon-quotation', iconKey: 'file' },
      { key: 'task',      label: 'New Task',      href: 'task-create.html',   iconCls: 'shf-fab-icon-task',      iconKey: 'task' },
      { key: 'visit',     label: 'New Visit',     href: 'dvr-create.html',    iconCls: 'shf-fab-icon-visit',     iconKey: 'pin'  },
    ];
    const menuHtml = items.map(it =>
      `<a class="shf-fab-item" href="${it.href}"><span class="shf-fab-item-icon ${it.iconCls}">${icon(it.iconKey, 15)}</span><span class="shf-fab-item-label">${it.label}</span></a>`
    ).join('');
    return `
<div class="shf-fab-backdrop" aria-hidden="true"></div>
<div class="shf-fab-wrap">
  <div class="shf-fab-menu" role="menu">${menuHtml}</div>
  <button type="button" class="shf-fab-main" id="shfFabMain" aria-label="Create menu" aria-expanded="false">${icon('plus', 22)}</button>
</div>`;
  }
  window.renderCreateFab = renderCreateFab;

  // Mount scaffolding for each page
  function mount(activeKey) {
    const container = document.getElementById('__shell_topbar');
    if (container) container.innerHTML = renderTopbar(activeKey);
    const t = document.getElementById('__shell_tweaks');
    if (t) t.innerHTML = renderTweaks();
    const mb = document.getElementById('__shell_mobile_nav');
    if (mb) mb.innerHTML = renderMobileBottomNav(activeKey);

    // Ensure the mobile bottom-nav is always mounted at body root (visible on tablet+mobile via CSS)
    if (!document.querySelector('.m-bottomnav')) {
      const navHost = document.createElement('div');
      navHost.innerHTML = renderMobileBottomNav(activeKey);
      while (navHost.firstChild) document.body.appendChild(navHost.firstChild);
    }

    // Mount the site-wide Create FAB (adds its own nodes to <body>)
    if (!document.querySelector('.shf-fab-wrap')) {
      const fabHost = document.createElement('div');
      fabHost.innerHTML = renderCreateFab();
      while (fabHost.firstChild) document.body.appendChild(fabHost.firstChild);
    }
    // Wire impersonate button — demo-only toast (live app opens a dropdown w/ search)
    const imp = document.getElementById('shfImpersonateBtn');
    if (imp && !imp.dataset.shfBound) {
      imp.dataset.shfBound = '1';
      imp.addEventListener('click', (e) => {
        e.preventDefault();
        if (window.shfToast) {
          window.shfToast('Impersonate user (demo)');
        }
      });
    }

    // Wire FAB main + backdrop + Escape
    const fabMain = document.getElementById('shfFabMain');
    if (fabMain && !fabMain.dataset.shfBound) {
      fabMain.dataset.shfBound = '1';
      const close = () => { document.body.classList.remove('shf-fab-open'); fabMain.setAttribute('aria-expanded', 'false'); };
      fabMain.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = document.body.classList.toggle('shf-fab-open');
        fabMain.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.querySelectorAll('.shf-fab-backdrop').forEach(b => b.addEventListener('click', close));
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && document.body.classList.contains('shf-fab-open')) close(); });
    }

    // Wrap any .tabs element with left/right scroll arrows (auto-shown on overflow)
    document.querySelectorAll('.tabs').forEach(function (tabs) {
      if (tabs.dataset.shfTabsWrapped) return;
      tabs.dataset.shfTabsWrapped = '1';

      var wrap = document.createElement('div');
      wrap.className = 'tabs-wrap';
      tabs.parentNode.insertBefore(wrap, tabs);

      var leftBtn = document.createElement('button');
      leftBtn.type = 'button';
      leftBtn.className = 'tabs-arrow tabs-arrow-left';
      leftBtn.setAttribute('aria-label', 'Scroll tabs left');
      leftBtn.innerHTML = '<svg viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>';

      var rightBtn = document.createElement('button');
      rightBtn.type = 'button';
      rightBtn.className = 'tabs-arrow tabs-arrow-right';
      rightBtn.setAttribute('aria-label', 'Scroll tabs right');
      rightBtn.innerHTML = '<svg viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';

      wrap.appendChild(leftBtn);
      wrap.appendChild(tabs);
      wrap.appendChild(rightBtn);

      function scrollAmount() {
        // Scroll by ~70% of visible width, minimum 160px
        return Math.max(160, Math.round(tabs.clientWidth * 0.7));
      }
      leftBtn.addEventListener('click', function (e) { e.preventDefault(); tabs.scrollBy({ left: -scrollAmount(), behavior: 'smooth' }); });
      rightBtn.addEventListener('click', function (e) { e.preventDefault(); tabs.scrollBy({ left: scrollAmount(), behavior: 'smooth' }); });

      function updateArrows() {
        var canL = tabs.scrollLeft > 4;
        var canR = tabs.scrollLeft + tabs.clientWidth < tabs.scrollWidth - 4;
        wrap.classList.toggle('has-left',  canL);
        wrap.classList.toggle('has-right', canR);
      }
      tabs.addEventListener('scroll', updateArrows, { passive: true });
      window.addEventListener('resize', updateArrows);
      // Initial delay to allow fonts/layout to settle
      setTimeout(updateArrows, 50);
      setTimeout(updateArrows, 300);

      // Re-check when the active tab changes (e.g., data-panel switches may re-flow)
      tabs.querySelectorAll('.tab').forEach(function (t) {
        t.addEventListener('click', function () {
          // Scroll the clicked tab into view within the overflow container
          setTimeout(function () {
            var activeRect = t.getBoundingClientRect();
            var wrapRect = tabs.getBoundingClientRect();
            if (activeRect.left < wrapRect.left) tabs.scrollBy({ left: activeRect.left - wrapRect.left - 20, behavior: 'smooth' });
            else if (activeRect.right > wrapRect.right) tabs.scrollBy({ left: activeRect.right - wrapRect.right + 20, behavior: 'smooth' });
            updateArrows();
          }, 20);
        });
      });
    });

    // Wire the "More" bottom-sheet (mobile/tablet nav)
    const moreBtn = document.getElementById('shfMoreBtn');
    const moreSheet = document.getElementById('shfMoreSheet');
    const moreBackdrop = document.getElementById('shfMoreBackdrop');
    const moreClose = document.getElementById('shfMoreClose');
    if (moreBtn && !moreBtn.dataset.shfBound) {
      moreBtn.dataset.shfBound = '1';
      const closeSheet = () => { document.body.classList.remove('shf-more-open'); };
      moreBtn.addEventListener('click', (e) => { e.stopPropagation(); document.body.classList.toggle('shf-more-open'); });
      if (moreClose) moreClose.addEventListener('click', closeSheet);
      if (moreBackdrop) moreBackdrop.addEventListener('click', closeSheet);
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && document.body.classList.contains('shf-more-open')) closeSheet(); });
    }

    // Wire dropdown — click trigger toggles, click outside closes (touch-friendly)
    document.querySelectorAll('.nav-dd-wrap').forEach((wrap) => {
      const trigger = wrap.querySelector('.nav-item');
      if (!trigger) return;
      trigger.addEventListener('click', (e) => {
        // Mobile drawer (<=768): dropdown is always inline-expanded, let link navigate
        if (window.matchMedia('(max-width: 768px)').matches) return;
        // Desktop: if user tapped chevron (svg), toggle; otherwise navigate
        if (window.matchMedia('(hover: none)').matches || e.target.closest('svg')) {
          e.preventDefault();
          wrap.classList.toggle('open');
        }
      });
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.nav-dd-wrap')) {
        document.querySelectorAll('.nav-dd-wrap.open').forEach(w => w.classList.remove('open'));
      }
    });

    // Wire the stage selector to persist + reload
    const sel = document.getElementById('tweakStage');
    if (sel) {
      sel.addEventListener('change', (e) => {
        setActiveStage(e.target.value);
        location.reload();
      });
    }
  }
  window.shfMount = mount;
})();
