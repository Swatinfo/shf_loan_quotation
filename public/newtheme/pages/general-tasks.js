/*
 * Newtheme Tasks index — drives:
 *   - List fetch via general-tasks.data (DataTables-shaped JSON)
 *   - Filters (view / status / priority / search) + collapsible card w/ counter
 *   - Pager
 *   - Inline row actions (View / Mark done / Delete)
 *   - Create-task modal + loan autocomplete
 */
(function () {
    'use strict';

    var URLS = window.__GT || {};
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var state = {
        start: 0,
        length: 25,
        order: { col: 'created_at', dir: 'desc' },
        filters: {},
        draw: 0,
    };

    var rowsEl = document.getElementById('gtRows');
    var pagerEl = document.getElementById('gtPager');
    var statsEl = document.getElementById('gtStatsLine');
    var resultEl = document.getElementById('gtResultCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function readFilters() {
        return {
            view:     (document.getElementById('gtView')     || {}).value || 'my_tasks_and_assigned',
            status:   (document.getElementById('gtStatus')   || {}).value || 'active',
            priority: (document.getElementById('gtPriority') || {}).value || '',
            search:   (document.getElementById('gtSearch')   || {}).value || '',
        };
    }

    function buildQuery() {
        state.draw++;
        var f = readFilters();
        state.filters = f;

        var params = new URLSearchParams();
        params.set('draw', state.draw);
        params.set('start', state.start);
        params.set('length', state.length);
        params.set('order[0][column]', '6');
        params.set('order[0][dir]', state.order.dir);
        params.set('search[value]', f.search);
        if (f.view)     { params.set('view',     f.view); }
        if (f.status)   { params.set('status',   f.status); }
        if (f.priority) { params.set('priority', f.priority); }
        return params.toString();
    }

    function renderRows(data) {
        if (!data.length) {
            rowsEl.innerHTML = '<div class="gt-loader" style="padding:36px 24px;"><div style="font-size:30px;margin-bottom:6px;">📋</div><div style="font-weight:600;color:var(--ink-3);">No tasks match the filters</div></div>';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th>Task</th>' +
            '<th>Priority</th>' +
            '<th>Status</th>' +
            '<th>Due</th>' +
            '<th>Assignee</th>' +
            '<th class="col-actions"></th>' +
        '</tr></thead><tbody>';

        var body = data.map(function (t) {
            var selfTag = t.is_self_task ? ' <span class="text-xs" style="color:var(--accent);font-weight:600;">(self)</span>' : '';
            var loanHtml = t.loan_info ? '<div class="text-xs text-muted">' + t.loan_info + '</div>' : '';

            return '<tr onclick="location=\'' + t.show_url + '\'" class="clickable">' +
                '<td data-label="Task"><strong>' + escapeHtml(t.title) + '</strong>' +
                    (t.description ? '<div class="text-xs text-muted">' + escapeHtml(t.description) + '</div>' : '') +
                    loanHtml +
                    '<div class="text-xs text-muted" style="margin-top:4px;">Created by ' + escapeHtml(t.creator_name) + '</div>' +
                '</td>' +
                '<td data-label="Priority">' + (t.priority_html || '') + '</td>' +
                '<td data-label="Status">' + (t.status_html || '') +
                    (t.completed_at ? '<div class="text-xs text-muted">' + escapeHtml(t.completed_at) + '</div>' : '') + '</td>' +
                '<td data-label="Due">' + (t.due_date_html || '—') + '</td>' +
                '<td data-label="Assignee"><div>' + escapeHtml(t.assignee_name) + '</div>' + selfTag + '</td>' +
                '<td class="col-actions" data-label="Actions" onclick="event.stopPropagation();">' + buildRowActions(t) + '</td>' +
            '</tr>';
        }).join('');

        rowsEl.innerHTML = head + body + '</tbody></table>';
        wireRowForms();
    }

    function buildRowActions(t) {
        var items = [];
        items.push({ kind: 'a', href: t.show_url, label: 'View', tone: 'info', icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' });
        if (t.can_edit && t.status !== 'completed' && t.status !== 'cancelled') {
            items.push({ kind: 'form', action: '/general-tasks/' + t.id + '/status', method: 'PATCH', extra: { status: 'completed' }, label: 'Mark done', tone: 'success', icon: 'M5 13l4 4L19 7' });
        }
        if (t.can_edit) {
            items.push({ kind: 'a', href: t.show_url + '?edit=1', label: 'Edit', tone: 'accent', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' });
        }
        if (t.can_delete) {
            items.push({ kind: 'form', action: '/general-tasks/' + t.id, method: 'DELETE', label: 'Delete', tone: 'danger', icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', confirm: 'Delete this task? This cannot be undone.' });
        }

        return '<div class="gt-actions">' + items.map(function (it) {
            var icon = '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' + it.icon + '"/></svg>';
            var cls = 'gt-act tone-' + it.tone;
            if (it.kind === 'a') {
                return '<a class="' + cls + '" href="' + it.href + '" title="' + escapeHtml(it.label) + '" aria-label="' + escapeHtml(it.label) + '">' + icon + '</a>';
            }
            var extras = '';
            if (it.extra) {
                Object.keys(it.extra).forEach(function (k) {
                    extras += '<input type="hidden" name="' + k + '" value="' + escapeHtml(it.extra[k]) + '">';
                });
            }
            return '<form method="POST" action="' + it.action + '" data-confirm="' + escapeHtml(it.confirm || '') + '" style="margin:0;display:inline-flex;">' +
                '<input type="hidden" name="_token" value="' + csrf + '">' +
                '<input type="hidden" name="_method" value="' + it.method + '">' +
                extras +
                '<button type="submit" class="' + cls + '" title="' + escapeHtml(it.label) + '" aria-label="' + escapeHtml(it.label) + '">' + icon + '</button>' +
            '</form>';
        }).join('') + '</div>';
    }

    function wireRowForms() {
        rowsEl.querySelectorAll('.gt-actions form[data-confirm]').forEach(function (f) {
            if (f.dataset.shfBound) { return; }
            f.dataset.shfBound = '1';
            f.addEventListener('submit', function (ev) {
                if (!confirm(f.dataset.confirm || 'Are you sure?')) { ev.preventDefault(); }
            });
        });
    }

    /* ======= Pager ======= */
    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="gt-pages">';
        html += '<button type="button" class="gt-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="gt-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="gt-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        pagerEl.innerHTML = html;
        pagerEl.querySelectorAll('.gt-pg-btn').forEach(function (b) {
            b.addEventListener('click', function () {
                var v = b.dataset.page;
                if (v === 'prev') { state.start = Math.max(0, state.start - state.length); }
                else if (v === 'next') { state.start = state.start + state.length; }
                else { state.start = (parseInt(v, 10) - 1) * state.length; }
                load();
            });
        });
    }

    function load() {
        rowsEl.innerHTML = '<div class="gt-loader">Loading…</div>';
        fetch(URLS.dataUrl + '?' + buildQuery(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                renderRows(resp.data || []);
                renderPager(resp.recordsTotal || 0, resp.recordsFiltered || 0);
                statsEl.innerHTML = '<strong>' + (resp.recordsTotal || 0).toLocaleString('en-IN') + '</strong> total · '
                    + (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' shown';
                resultEl.textContent = (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' results';
                refreshActiveFilterCount();
            })
            .catch(function () {
                rowsEl.innerHTML = '<div class="gt-loader" style="color:var(--red);">Failed to load. Please try again.</div>';
            });
    }

    /* ======= Filter wiring ======= */
    var debounce = null;
    var searchEl = document.getElementById('gtSearch');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { state.start = 0; load(); }, 300);
        });
    }
    ['gtView', 'gtStatus', 'gtPriority'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.addEventListener('change', function () { state.start = 0; load(); }); }
    });
    var perPage = document.getElementById('gtPerPage');
    if (perPage) { perPage.addEventListener('change', function () { state.length = parseInt(this.value, 10); state.start = 0; load(); }); }
    document.getElementById('gtFilter').addEventListener('click', function () { state.start = 0; load(); });
    document.getElementById('gtClear').addEventListener('click', function () {
        document.getElementById('gtView').value = 'my_tasks_and_assigned';
        document.getElementById('gtStatus').value = 'active';
        document.getElementById('gtPriority').value = '';
        document.getElementById('gtSearch').value = '';
        state.start = 0;
        load();
    });

    /* ======= Filters collapse + counter ======= */
    var filtersCard = document.getElementById('gtFiltersCard');
    var filtersToggle = document.getElementById('gtFiltersToggle');
    var activeCountEl = document.getElementById('gtActiveFilterCount');
    if (filtersToggle && filtersCard) {
        function toggleFilters() {
            var collapsed = filtersCard.classList.toggle('collapsed');
            filtersToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
        filtersToggle.addEventListener('click', toggleFilters);
        filtersToggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleFilters(); }
        });
    }
    function refreshActiveFilterCount() {
        if (!activeCountEl) { return; }
        var f = readFilters();
        var defaults = { view: 'my_tasks_and_assigned', status: 'active', priority: '', search: '' };
        var active = 0;
        Object.keys(defaults).forEach(function (k) {
            if ((f[k] || '') !== defaults[k]) { active++; }
        });
        activeCountEl.textContent = active;
        activeCountEl.classList.toggle('has-active', active > 0);
    }

    /* ======= Listen for task-created from the shared site-wide modal =======
       Create-task modal + its SHF.validateForm-driven JS live in
       resources/views/newtheme/partials/create-task-modal.blade.php +
       public/newtheme/assets/shf-create-task.js. After a successful create
       it dispatches `shf:task-created` — we just refresh the list. */
    document.addEventListener('shf:task-created', function () { state.start = 0; load(); });

    /* Auto-open the shared modal when dashboard FAB linked here with ?create=1 */
    try {
        var params = new URLSearchParams(location.search);
        if (params.get('create') === '1') {
            document.dispatchEvent(new CustomEvent('shf:open-create-task'));
            params.delete('create');
            var newQuery = params.toString();
            history.replaceState(null, '', location.pathname + (newQuery ? ('?' + newQuery) : '') + location.hash);
        }
    } catch (_) { /* no-op */ }

    /* ======= Initial load ======= */
    load();
})();
