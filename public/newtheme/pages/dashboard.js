/*
 * Newtheme dashboard renderer.
 * Reads window.__DASHBOARD (built by DashboardController::newthemePayload).
 * Mirrors the panel structure of public/newtheme/dashboard.html — but every
 * value comes from real Laravel data instead of randomised demo arrays.
 */
(function () {
    'use strict';

    const D = window.__DASHBOARD;
    if (!D) {
        console.warn('[newtheme dashboard] window.__DASHBOARD missing');
        return;
    }

    const $ = (id) => document.getElementById(id);
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ==================== Greeting ==================== */
    const hour = new Date().getHours();
    const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    $('greeting').textContent = greet + ', ' + (D.currentUser?.short || D.currentUser?.name || '');

    /* ==================== Subheader ==================== */
    const sh = D.subheader || {};
    $('dashSub').innerHTML =
        'Branch: <strong>' + escapeHtml(sh.branch || '—') + '</strong> · ' +
        (sh.activeFiles || 0) + ' active files · ' +
        (sh.disbursementsToday || 0) + ' disbursements scheduled today';

    /* ==================== KPI strip ==================== */
    $('kpiStrip').innerHTML = (D.kpi || []).map((s, i) => {
        const sep = i > 0 ? '<span class="kpi-sep"></span>' : '';
        return sep +
            '<div class="kpi-chip kpi-tone-' + escapeHtml(s.tone) + '">' +
            '<span class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="' + s.icon + '"/></svg></span>' +
            '<span class="kpi-val">' + Number(s.val).toLocaleString('en-IN') + '</span>' +
            '<span class="kpi-lbl">' + escapeHtml(s.lbl) + '</span>' +
            '</div>';
    }).join('');

    /* ==================== Tab counts ====================
       Counter <span> elements only exist for tabs the controller marked
       `visible` (permission-gated in resources/views/newtheme/dashboard.blade.php).
       We must skip writes for missing nodes — otherwise a non-super-admin user
       hits "Cannot set properties of null" the moment the dashboard initialises. */
    const tc = D.tabCounts || {};
    function setCount(id, val) {
        const el = document.getElementById(id);
        if (el) { el.textContent = val == null ? 0 : val; }
    }
    setCount('cnt-ptasks', tc.personal_tasks);
    setCount('cnt-tasks', tc.my_tasks);
    setCount('cnt-loans', tc.loans);
    setCount('cnt-dvr', tc.dvr);
    setCount('cnt-quot', tc.quotations);

    /* ==================== Tab switching (no shared tabs.js dependency) ==================== */
    function activatePanel(panelId) {
        document.querySelectorAll('.tabs .tab').forEach((t) => t.classList.toggle('active', t.dataset.panel === panelId));
        document.querySelectorAll('[data-panel-id]').forEach((p) => {
            p.style.display = p.dataset.panelId === panelId ? '' : 'none';
        });
    }
    document.querySelectorAll('.tabs .tab').forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            activatePanel(tab.dataset.panel);
        });
    });
    // Honour the controller's data-driven default tab (mirrors the existing
    // dashboard's defaultTab logic). Falls back to whichever .tab.active exists.
    const initialTab = D.defaultTab
        || document.querySelector('.tabs .tab.active')?.dataset.panel
        || 'personal-tasks';
    activatePanel(initialTab);

    /* ==================== Helpers ==================== */
    // Map controller "color" values to newtheme native badge color classes.
    // Newtheme CSS already defines .badge.{green|amber|red|blue|violet|orange|dark}
    // so we lean on those tokens instead of duplicating colors via inline style.
    const COLOR_CLASS = {
        green: 'green', amber: 'amber', red: 'red', blue: 'blue',
        violet: 'violet', orange: 'orange', dark: 'dark', gray: '',
    };
    function colorClass(c) { return COLOR_CLASS[c] ?? ''; }

    // Stage-bar fill color: matches the badge tone so the progress bar reads
    // the same hue as the stage chip above it (mirrors the demo's BAR map).
    const BAR_COLOR = {
        blue: 'var(--blue)',
        amber: 'var(--amber)',
        orange: 'var(--accent)',
        violet: 'var(--violet)',
        green: 'var(--green)',
        red: 'var(--red)',
        dark: 'var(--ink-3)',
        gray: 'var(--ink-4)',
    };
    function barColor(c) { return BAR_COLOR[c] || 'var(--accent)'; }

    function pill(label, color) {
        const cls = colorClass(color);
        // .pill defaults to gray; for colored variants reuse the .badge color tokens
        // by stacking .badge.<color> rules (background-color + color). Using an inline
        // override only for font weight to match the demo pill style.
        if (cls && cls !== 'gray') {
            return '<span class="badge ' + cls + '" style="border-radius:999px;font-weight:600;font-size:10.5px;text-transform:none;letter-spacing:0;">' + escapeHtml(label) + '</span>';
        }
        return '<span class="pill" style="font-weight:600;font-size:10.5px;">' + escapeHtml(label) + '</span>';
    }

    function badge(label, color) {
        const cls = colorClass(color);
        return '<span class="badge sq ' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function bankChip(b) {
        if (!b) {
            return '<span class="text-xs text-muted">—</span>';
        }
        return '<span class="bank-chip" style="background:' + b.bg + ';color:' + b.fg + ';">' + escapeHtml(b.name) + '</span>';
    }
    function emptyState(emoji, title, sub) {
        return '<div class="empty" style="padding:40px 24px;text-align:center;color:var(--ink-3);">' +
            '<div style="font-size:30px;margin-bottom:8px;">' + emoji + '</div>' +
            '<div style="font-weight:600;">' + escapeHtml(title) + '</div>' +
            (sub ? '<div class="text-xs text-muted mt-1">' + escapeHtml(sub) + '</div>' : '') +
            '</div>';
    }

    /* ==================== Personal Tasks ==================== */
    const pt = D.personalTasks || [];
    const overdue = pt.filter((t) => t.overdue).length;
    $('ptasksSub').textContent = pt.length + ' pending · ' + overdue + ' overdue';
    $('rows-ptasks').innerHTML = pt.length ? (
        '<table class="tbl"><thead><tr>' +
        '<th>Task</th><th>Priority</th><th>Status</th><th>Due</th><th>Assignee</th>' +
        '</tr></thead><tbody>' +
        pt.map((t) => (
            '<tr onclick="location=\'' + t.showUrl + '\'" class="clickable">' +
            '<td><strong>' + escapeHtml(t.title) + '</strong>' +
            '<div class="text-xs text-muted">' +
            (t.loanNumber ? '<span class="font-mono" style="color:var(--accent);">' + escapeHtml(t.loanNumber) + '</span> · ' : '') +
            'Created by ' + escapeHtml(t.createdBy) +
            '</div>' +
            '</td>' +
            '<td>' + pill(t.priorityLabel, t.priorityColor) + '</td>' +
            '<td>' + pill(t.statusLabel, t.statusColor) + '</td>' +
            '<td>' +
            '<div class="text-xs"' + (t.overdue ? ' style="color:var(--red);font-weight:600;"' : '') + '>' + (t.dueDate || '—') + '</div>' +
            (t.overdue ? '<div class="text-xs" style="color:var(--red);">Overdue</div>' : '') +
            '</td>' +
            '<td><div>' + escapeHtml(t.assignee) + '</div>' + (t.assignedToMe ? '<div class="text-xs" style="color:var(--accent);">Me</div>' : '') + '</td>' +
            '</tr>'
        )).join('') + '</tbody></table>'
    ) : emptyState('✅', 'All caught up!', 'No pending personal tasks.');

    /* ==================== My Loan Tasks ==================== */
    const mt = D.myLoanTasks || [];
    const stageSel = $('dashTaskStageFilter');
    (D.stagesDropdown || []).forEach((s) => {
        const o = document.createElement('option');
        o.value = s.key;
        o.textContent = s.n + '. ' + s.label;
        stageSel.appendChild(o);
    });

    function renderMyTasks() {
        const filter = stageSel.value;
        const rows = mt.filter((l) => !filter || l.stageKey === filter);
        $('rows-mytasks').innerHTML = rows.length ? (
            '<table class="tbl"><thead><tr>' +
            '<th>Loan #</th><th>Customer</th><th>Stage</th><th class="num">Amount</th><th>Bank</th>' +
            '</tr></thead><tbody>' +
            rows.map((l) => (
                '<tr onclick="location=\'' + l.showUrl + '\'" class="clickable">' +
                '<td><span class="font-mono" style="font-weight:600;">' + escapeHtml(l.loanNumber) + '</span></td>' +
                '<td><strong>' + escapeHtml(l.customer) + '</strong><div class="text-xs text-muted">' + escapeHtml(l.customerType) + '</div></td>' +
                '<td>' + badge(l.stageName, l.stageBadgeClass) +
                '<div class="progress thin mt-1"><div class="fill" style="width:' + l.progress + '%;background:' + barColor(l.stageBadgeClass) + ';"></div></div></td>' +
                '<td class="num tnum"><strong>' + escapeHtml(l.amountFormatted) + '</strong></td>' +
                '<td>' + bankChip(l.bank) + (l.productName ? '<div class="text-xs text-muted mt-1">' + escapeHtml(l.productName) + '</div>' : '') + '</td>' +
                '</tr>'
            )).join('') + '</tbody></table>'
        ) : emptyState('🎉', 'No assigned stages');
    }
    stageSel.addEventListener('change', renderMyTasks);
    renderMyTasks();

    /* ==================== Loans ==================== */
    const loans = D.loans || [];
    $('rows-loans').innerHTML = loans.length ? (
        '<table class="tbl"><thead><tr>' +
        '<th>Loan #</th><th>Customer</th><th>Bank / Product</th><th class="num">Amount</th><th>Stage</th><th>Owner</th>' +
        '</tr></thead><tbody>' +
        loans.map((l) => (
            '<tr onclick="location=\'' + l.showUrl + '\'" class="clickable">' +
            '<td><span class="font-mono" style="font-weight:600;">' + escapeHtml(l.loanNumber) + '</span></td>' +
            '<td><strong>' + escapeHtml(l.customer) + '</strong><div class="text-xs text-muted">' + escapeHtml(l.customerType) + '</div></td>' +
            '<td>' + bankChip(l.bank) + (l.productName ? '<div class="text-xs text-muted mt-1">' + escapeHtml(l.productName) + '</div>' : '') + '</td>' +
            '<td class="num tnum"><strong>' + escapeHtml(l.amountFormatted) + '</strong></td>' +
            '<td>' + badge(l.stageName, l.stageBadgeClass) + '</td>' +
            '<td>' + escapeHtml(l.owner) + '</td>' +
            '</tr>'
        )).join('') + '</tbody></table>'
    ) : emptyState('📂', 'No active loans');

    /* ==================== DVR ==================== */
    const dvr = D.dvr || [];
    const pendingFu = dvr.filter((v) => v.followUp.state === 'pending').length;
    const overdueFu = dvr.filter((v) => v.followUp.overdue).length;
    $('dvrSub').textContent = dvr.length + ' visits · ' + pendingFu + ' pending follow-ups · ' + overdueFu + ' overdue';
    $('rows-dvr').innerHTML = dvr.length ? (
        '<table class="tbl"><thead><tr>' +
        '<th>Visit</th><th>Contact</th><th>Type</th><th>Purpose</th><th>Outcome</th><th>Follow-up</th>' +
        '</tr></thead><tbody>' +
        dvr.map((v) => {
            let fu;
            if (v.followUp.state === 'none') {
                fu = '<span class="text-xs text-muted">—</span>';
            } else if (v.followUp.state === 'done' || v.followUp.state === 'completed') {
                fu = badge('Completed', 'green');
            } else if (v.followUp.overdue) {
                fu = badge('Overdue · ' + v.followUp.date, 'red');
            } else {
                fu = badge('Pending · ' + v.followUp.date, 'amber');
            }
            if (v.followUpsTaken && v.followUpsTaken > 0) {
                const label = v.followUpsTaken === 1 ? 'follow-up taken' : 'follow-ups taken';
                fu += '<div style="margin-top:4px;">' + badge(v.followUpsTaken + ' ' + label, 'blue') + '</div>';
            }
            return '<tr onclick="location=\'' + v.showUrl + '\'" class="clickable">' +
                '<td><div class="text-xs">' + escapeHtml(v.visitDate) + '</div></td>' +
                '<td><strong>' + escapeHtml(v.contactName) + '</strong>' +
                (v.contactPhone ? '<div class="text-xs text-muted font-mono">' + escapeHtml(v.contactPhone) + '</div>' : '') + '</td>' +
                '<td>' + pill(v.contactType, 'gray') + '</td>' +
                '<td>' + escapeHtml(v.purpose) + '</td>' +
                '<td><div class="text-xs">' + escapeHtml(v.outcome || '—') + '</div></td>' +
                '<td>' + fu + '</td>' +
                '</tr>';
        }).join('') + '</tbody></table>'
    ) : emptyState('📍', 'No visits yet');

    /* ==================== Quotations ==================== */
    const quots = D.quotations || [];
    function renderQuotations() {
        const filter = $('dashQuotStatusFilter').value;
        const rows = quots.filter((q) => !filter || q.status === filter);
        $('quotSub').textContent = rows.length + ' quotations' + (filter ? ' · filtered' : '');
        $('rows-quot').innerHTML = rows.length ? (
            '<table class="tbl"><thead><tr>' +
            '<th>#</th><th>Customer</th><th class="num">Amount</th><th>Banks</th><th>Status</th><th>Date</th>' +
            '</tr></thead><tbody>' +
            rows.map((q) => (
                '<tr onclick="location=\'' + q.showUrl + '\'" class="clickable">' +
                '<td><span class="font-mono" style="font-weight:600;">' + escapeHtml(q.quotNumber) + '</span></td>' +
                '<td><strong>' + escapeHtml(q.customer) + '</strong><div class="text-xs text-muted">' + escapeHtml(q.customerType) + '</div></td>' +
                '<td class="num tnum"><strong>' + escapeHtml(q.amountFormatted) + '</strong></td>' +
                '<td><div style="display:flex;gap:4px;flex-wrap:wrap;">' + (q.banks || []).map(bankChip).join('') + '</div></td>' +
                '<td>' + pill(q.statusLabel, q.statusColor) + '</td>' +
                '<td><div class="text-xs">' + escapeHtml(q.date || '—') + '</div></td>' +
                '</tr>'
            )).join('') + '</tbody></table>'
        ) : emptyState('📄', 'No quotations match filter');
    }
    $('dashQuotStatusFilter').addEventListener('change', renderQuotations);
    renderQuotations();

    /* ==================== Pipeline ==================== */
    const pipe = D.pipeline || [];
    $('pipelineGrid').innerHTML = pipe.map((s) => (
        '<div>' +
        '<div class="text-xs text-muted">' + s.n + '. ' + escapeHtml(s.label) + '</div>' +
        '<div style="font-family:Jost;font-size:22px;font-weight:600;margin-top:3px;' + (s.key === 'completed_mtd' ? 'color:var(--green);' : '') + '">' + s.count + '</div>' +
        '<div class="progress thin mt-1"><div class="fill" style="width:' + Math.min(100, s.count * 10) + '%;background:' + barColor(s.color) + ';"></div></div>' +
        '</div>'
    )).join('');

    /* ==================== Sidebar: today's follow-ups ==================== */
    const todayItems = D.todayFollowUps || [];
    $('timelineList').innerHTML = todayItems.length
        ? todayItems.map((i) => (
            '<li' + (i.active ? ' class="active"' : '') + '>' +
            '<div class="tl-t">' + escapeHtml(i.title) + '</div>' +
            '<div class="tl-meta">' + escapeHtml(i.meta) +
            (i.owner ? ' · ' + escapeHtml(i.owner) : '') +
            '</div>' +
            '</li>'
        )).join('')
        : '<li><div class="tl-meta">No follow-ups due today.</div></li>';

    /* ==================== Sidebar: open queries ==================== */
    const queries = D.openQueries || [];
    $('openQueryCount').textContent = queries.length;
    $('openQueriesList').innerHTML = queries.length
        ? queries.map((q, i) => {
            const last = i === queries.length - 1;
            return '<div style="padding:12px 18px;' + (last ? '' : 'border-bottom:1px solid var(--line);') + '">' +
                '<div style="font-size:12px;font-weight:500;">' + escapeHtml(q.title) + '</div>' +
                '<div class="text-xs text-muted mt-1"><span class="font-mono">' + escapeHtml(q.loan) + '</span> · ' + escapeHtml(q.role) + ' · ' + escapeHtml(q.age) + '</div>' +
                '</div>';
        }).join('')
        : '<div style="padding:24px;text-align:center;color:var(--ink-3);font-size:12px;">No open queries.</div>';

    /* ==================== Sidebar: field activity ==================== */
    const fa = D.fieldActivity || [];
    $('fieldStrip').innerHTML = fa.map((s) => (
        '<div><div class="lbl">' + escapeHtml(s.lbl) + '</div><div class="val">' + escapeHtml(s.val) + '</div></div>'
    )).join('');

    /* ==================== Sidebar: bank mix donut ==================== */
    const mix = D.bankMix || { total: 0, banks: [] };
    let offset = 25;
    let donutSegs = '<circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--paper-2)" stroke-width="6"/>';
    let legendHtml = '';
    mix.banks.forEach((b) => {
        const dash = mix.total ? (b.count / mix.total) * 100 : 0;
        donutSegs += '<circle cx="21" cy="21" r="15.915" fill="none" stroke="' + b.bg + '" stroke-width="6" stroke-dasharray="' + dash + ' 100" stroke-dashoffset="' + offset + '" transform="rotate(-90 21 21)"/>';
        offset -= dash;
        legendHtml += '<div style="display:flex;justify-content:space-between;font-size:11.5px;padding:3px 0;">' +
            '<span style="display:inline-flex;gap:6px;align-items:center;">' +
            '<span style="width:8px;height:8px;background:' + b.bg + ';border-radius:2px;"></span>' + escapeHtml(b.code) +
            '</span><strong>' + b.pct + '%</strong></div>';
    });
    donutSegs += '<text x="21" y="22" text-anchor="middle" font-family="Jost" font-weight="600" font-size="6" fill="#1c1a1b">' + mix.total + '</text>';
    donutSegs += '<text x="21" y="27" text-anchor="middle" font-family="Archivo" font-size="3" fill="#8a8285">loans</text>';
    $('bankDonut').innerHTML = donutSegs;
    $('bankLegend').innerHTML = legendHtml || '<div class="text-xs text-muted">No loans this month.</div>';
})();
