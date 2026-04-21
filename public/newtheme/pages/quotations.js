/*
 * Newtheme Quotations index — fetches the existing dashboard.quotation-data
 * endpoint (DataTables-shaped JSON) and renders rows into the newtheme `.tbl`
 * with adaptive filters, search debounce, and a portaled per-row action menu.
 */
(function () {
    'use strict';

    var URLS = window.__QX || {};
    var canViewAll = !!URLS.canViewAll;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var state = {
        start: 0,
        length: 25,
        order: { col: 'created_at', dir: 'desc' },
        filters: {},
        draw: 0,
    };

    var rowsEl = document.getElementById('qxRows');
    var pagerEl = document.getElementById('qxPager');
    var statsEl = document.getElementById('qxStatsLine');
    var resultEl = document.getElementById('qxResultCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function readFilters() {
        return {
            search: (document.getElementById('qxSearch') || {}).value || '',
            customer_type: (document.getElementById('qxType') || {}).value || '',
            status: (document.getElementById('qxStatus') || {}).value || 'not_cancelled',
            loan_status: (document.getElementById('qxLoanStatus') || {}).value || 'not_converted',
            date_from: (document.getElementById('qxDateFrom') || {}).value || '',
            date_to:   (document.getElementById('qxDateTo')   || {}).value || '',
            created_by: canViewAll ? ((document.getElementById('qxCreatedBy') || {}).value || '') : '',
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
        params.set('order[0][column]', '5');
        params.set('order[0][dir]', state.order.dir);
        params.set('search[value]', f.search);
        Object.keys(f).forEach(function (k) { if (k !== 'search' && f[k] !== '') params.set(k, f[k]); });
        return params.toString();
    }

    function renderRows(data) {
        if (!data.length) {
            rowsEl.innerHTML = '<div class="qx-loader" style="padding:36px 24px;"><div style="font-size:30px;margin-bottom:6px;">📄</div><div style="font-weight:600;color:var(--ink-3);">No quotations match the filters</div></div>';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th style="width:54px;">#</th>' +
            '<th>Customer</th>' +
            '<th>Type</th>' +
            '<th class="num">Amount</th>' +
            '<th>Banks</th>' +
            '<th>Status</th>' +
            (canViewAll ? '<th>Created By</th>' : '') +
            '<th>Date</th>' +
            '<th class="col-actions"></th>' +
        '</tr></thead><tbody>';

        var body = data.map(function (q) {
            var actions = buildRowActions(q);
            var statusBadge = q.status_html || '';

            var typeLabel = q.type_label || q.customer_type || '';
            var typeColor = ({ proprietor: 'green', partnership_llp: 'blue', pvt_ltd: 'orange', salaried: 'violet' })[q.customer_type] || 'gray';

            var bankChips = (q.banks || []).map(function (n) {
                var palette = bankPalette(n);
                return '<span class="bank-chip" style="background:' + palette.bg + ';color:' + palette.fg + ';">' + escapeHtml(n) + '</span>';
            }).join('');

            return '<tr onclick="location=\'' + q.show_url + '\'" class="clickable">' +
                '<td data-label="#"><span class="font-mono" style="font-weight:600;">#' + q.id + '</span></td>' +
                '<td data-label="Customer"><strong>' + escapeHtml(q.customer_name) + '</strong>' +
                    (q.location_name ? '<div class="text-xs text-muted">' + escapeHtml(q.location_name) + '</div>' : '') + '</td>' +
                '<td data-label="Type"><span class="badge sq ' + typeColor + '">' + escapeHtml(typeLabel) + '</span></td>' +
                '<td class="num tnum" data-label="Amount"><strong>' + escapeHtml(q.formatted_amount) + '</strong></td>' +
                '<td data-label="Banks"><div class="qx-bank-chips">' + bankChips + '</div></td>' +
                '<td data-label="Status">' + statusBadge +
                    (q.is_converted ? '<div class="text-xs text-muted mt-1">→ Loan ' + (q.loan_status_label || '') + '</div>' : '') + '</td>' +
                (canViewAll ? '<td data-label="Created By">' + escapeHtml(q.created_by || '—') + '</td>' : '') +
                '<td data-label="Date"><div class="text-xs">' + escapeHtml(q.date) + '</div></td>' +
                '<td class="col-actions" data-label="Actions" onclick="event.stopPropagation();">' + actions + '</td>' +
            '</tr>';
        }).join('');

        rowsEl.innerHTML = head + body + '</tbody></table>';
        wireRowMenus();
    }

    function bankPalette(name) {
        var u = String(name).toUpperCase();
        if (u.indexOf('HDFC')  >= 0) return { bg: '#004C8F', fg: '#fff' };
        if (u.indexOf('ICICI') >= 0) return { bg: '#F37E20', fg: '#fff' };
        if (u.indexOf('AXIS')  >= 0) return { bg: '#97144D', fg: '#fff' };
        if (u.indexOf('KOTAK') >= 0) return { bg: '#fa1432', fg: '#fff' };
        if (u.indexOf('SBI')   >= 0) return { bg: '#22409a', fg: '#fff' };
        return { bg: '#6b7280', fg: '#fff' };
    }

    /* ======= Per-row inline action strip (no submenu) =======
       Every available action renders as an icon-only button in the actions
       column, with a `title` tooltip showing the full label. Compact enough
       to fit several actions on a row, accessible (aria-label + tooltip),
       and discoverable on touch (no hover-only menu). */
    function buildRowActions(q) {
        var items = [];
        // Tone palette mirrors the standard semantic vocabulary:
        //   info    → blue (View, Open Loan)
        //   accent  → orange (Branded download — primary download)
        //   gray    → secondary neutral (Plain download)
        //   success → green (Convert, Resume)
        //   warning → amber (Hold)
        //   danger  → red (Cancel, Delete)
        items.push({ kind: 'a', href: q.show_url, label: 'View', tone: 'info', icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' });
        if (q.download_branded_url) items.push({ kind: 'a', href: q.download_branded_url, label: 'Download (Branded)', tone: 'accent',  icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 12V4m0 8l-4-4m4 4l4-4' });
        if (q.download_plain_url)   items.push({ kind: 'a', href: q.download_plain_url,   label: 'Download (Plain)',   tone: 'gray',    icon: 'M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3' });
        if (q.convert_url)          items.push({ kind: 'a', href: q.convert_url,          label: 'Convert to Loan',    tone: 'success', icon: 'M13 7l5 5m0 0l-5 5m5-5H6' });
        if (q.loan_url)             items.push({ kind: 'a', href: q.loan_url,             label: 'Open Loan',          tone: 'info',    icon: 'M14 5l7 7m0 0l-7 7m7-7H3' });
        if (q.hold_url)             items.push({ kind: 'a', href: q.show_url + '?action=hold',   label: 'Put on Hold', tone: 'warning', icon: 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' });
        if (q.cancel_url)           items.push({ kind: 'a', href: q.show_url + '?action=cancel', label: 'Cancel',      tone: 'danger',  icon: 'M6 18L18 6M6 6l12 12' });
        if (q.resume_url)           items.push({ kind: 'a', href: q.show_url + '?action=resume', label: 'Resume',      tone: 'success', icon: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z' });
        if (q.delete_url)           items.push({ kind: 'form', action: q.delete_url, method: 'DELETE', label: 'Delete', tone: 'danger', icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', confirm: 'Delete this quotation? This cannot be undone.' });

        return '<div class="qx-actions">' + items.map(function (it) {
            var icon = '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' + it.icon + '"/></svg>';
            var toneCls = it.tone ? (' tone-' + it.tone) : '';
            var cls = 'qx-act' + (it.danger ? ' danger' : '') + toneCls;
            if (it.kind === 'a') {
                return '<a class="' + cls + '" href="' + it.href + '" title="' + escapeHtml(it.label) + '" aria-label="' + escapeHtml(it.label) + '">' + icon + '</a>';
            }
            // form (POST/DELETE)
            return '<form method="POST" action="' + it.action + '" data-confirm="' + escapeHtml(it.confirm || '') + '" style="margin:0;display:inline-flex;">' +
                '<input type="hidden" name="_token" value="' + csrf + '">' +
                '<input type="hidden" name="_method" value="' + it.method + '">' +
                '<button type="submit" class="' + cls + '" title="' + escapeHtml(it.label) + '" aria-label="' + escapeHtml(it.label) + '">' + icon + '</button>' +
            '</form>';
        }).join('') + '</div>';
    }

    function wireRowMenus() {
        // Inline-action rows just need a confirm guard on each delete form.
        rowsEl.querySelectorAll('.qx-actions form[data-confirm]').forEach(function (f) {
            if (f.dataset.shfBound) { return; }
            f.dataset.shfBound = '1';
            f.addEventListener('submit', function (ev) {
                if (!confirm(f.dataset.confirm || 'Are you sure?')) { ev.preventDefault(); }
            });
        });
    }

    /* ======= Pagination ======= */
    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="qx-pages">';
        html += '<button type="button" class="qx-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="qx-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="qx-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        pagerEl.innerHTML = html;
        pagerEl.querySelectorAll('.qx-pg-btn').forEach(function (b) {
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
        rowsEl.innerHTML = '<div class="qx-loader">Loading…</div>';
        fetch(URLS.dataUrl + '?' + buildQuery(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                renderRows(resp.data || []);
                renderPager(resp.recordsTotal || 0, resp.recordsFiltered || 0);
                statsEl.innerHTML = '<strong>' + (resp.recordsTotal || 0).toLocaleString('en-IN') + '</strong> total · '
                    + (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' shown';
                resultEl.textContent = (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' results';
            })
            .catch(function () {
                rowsEl.innerHTML = '<div class="qx-loader" style="color:var(--red);">Failed to load. Please try again.</div>';
            });
    }

    /* ======= Filter wiring ======= */
    var debounce = null;
    document.getElementById('qxSearch') && document.getElementById('qxSearch').addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(function () { state.start = 0; load(); }, 300);
    });
    ['qxType', 'qxStatus', 'qxLoanStatus', 'qxCreatedBy'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.addEventListener('change', function () { state.start = 0; load(); }); }
    });
    var perPage = document.getElementById('qxPerPage');
    if (perPage) { perPage.addEventListener('change', function () { state.length = parseInt(this.value, 10); state.start = 0; load(); }); }
    document.getElementById('qxFilter').addEventListener('click', function () { state.start = 0; load(); });
    document.getElementById('qxClear').addEventListener('click', function () {
        document.getElementById('qxSearch').value = '';
        document.getElementById('qxType').value = '';
        document.getElementById('qxStatus').value = 'not_cancelled';
        document.getElementById('qxLoanStatus').value = 'not_converted';
        document.getElementById('qxDateFrom').value = '';
        document.getElementById('qxDateTo').value = '';
        if (canViewAll && document.getElementById('qxCreatedBy')) { document.getElementById('qxCreatedBy').value = ''; }
        state.start = 0;
        load();
    });

    /* ======= Datepickers ======= */
    if (window.jQuery && jQuery.fn.datepicker) {
        jQuery('.shf-datepicker-past').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true,
            endDate: '+0d',
            container: 'body',
        }).on('changeDate', function () { state.start = 0; load(); });
    }

    /* ======= Filters card collapse / counter ======= */
    var filtersCard = document.getElementById('qxFiltersCard');
    var filtersToggle = document.getElementById('qxFiltersToggle');
    var activeCountEl = document.getElementById('qxActiveFilterCount');
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
        var defaults = { search: '', customer_type: '', status: 'not_cancelled', loan_status: 'not_converted', date_from: '', date_to: '', created_by: '' };
        var active = 0;
        Object.keys(defaults).forEach(function (k) {
            if ((f[k] || '') !== defaults[k]) { active++; }
        });
        // Always render the count — "0" when none, accent-coloured when N > 0.
        activeCountEl.textContent = active;
        activeCountEl.classList.toggle('has-active', active > 0);
    }
    // Patch load() so count refreshes with each request
    var originalLoad = load;
    load = function () { refreshActiveFilterCount(); originalLoad(); };

    /* ======= Initial load ======= */
    load();
})();
