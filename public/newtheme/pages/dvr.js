/*
 * Newtheme DVR index — fetches the existing dvr.data endpoint
 * (DataTables-shaped JSON) and renders rows into the newtheme `.tbl`
 * plus mobile cards, with debounced search + pagination.
 */
(function () {
    'use strict';

    var URLS = window.__DX || {};
    var state = { start: 0, length: 25, draw: 0 };

    var rowsEl = document.getElementById('dxRows');
    var mobileEl = document.getElementById('dxMobileRows');
    var pagerEl = document.getElementById('dxPager');
    var statsEl = document.getElementById('dxStatsLine');
    var resultEl = document.getElementById('dxResultCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function readFilters() {
        var get = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
        return {
            view: get('dxView') || 'my_visits',
            search: get('dxSearch'),
            contact_type: get('dxContactType'),
            purpose: get('dxPurpose'),
            follow_up: get('dxFollowUp') || 'active',
            user_id: get('dxUser'),
            date_from: get('dxDateFrom'),
            date_to: get('dxDateTo'),
        };
    }

    function buildQuery() {
        state.draw++;
        var f = readFilters();
        var params = new URLSearchParams();
        params.set('draw', state.draw);
        params.set('start', state.start);
        params.set('length', state.length);
        params.set('order[0][column]', '0');
        params.set('order[0][dir]', 'desc');
        if (f.search) { params.set('search[value]', f.search); }
        Object.keys(f).forEach(function (k) {
            if (k === 'search') { return; }
            if (f[k] !== '' && f[k] != null) { params.set(k, f[k]); }
        });
        return params.toString();
    }

    /* ======= Row actions (newtheme icon strip) ======= */
    function buildActions(v) {
        var items = '<a class="dx-act tone-info" href="' + escapeHtml(URLS.showUrlBase + '/' + v.id) + '" title="View" aria-label="View">' +
            '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>';
        return '<div class="dx-actions">' + items + '</div>';
    }

    function renderRows(data) {
        if (!data.length) {
            rowsEl.innerHTML = '<div class="dx-loader" style="padding:36px 24px;"><div style="font-size:30px;margin-bottom:6px;">📭</div><div style="font-weight:600;color:var(--ink-3);">No visits match the filters</div></div>';
            mobileEl.innerHTML = '';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th>Visit Date</th>' +
            '<th>Contact</th>' +
            '<th>Type</th>' +
            '<th>Purpose</th>' +
            '<th>Loan / Quotation</th>' +
            '<th>Follow-up</th>' +
            '<th>User</th>' +
            '<th class="col-actions"></th>' +
            '</tr></thead><tbody>';

        var body = data.map(function (v) {
            return '<tr>' +
                '<td data-label="Visit Date">' + escapeHtml(v.visit_date) + '</td>' +
                '<td data-label="Contact"><strong>' + v.contact_name + '</strong>' +
                    (v.contact_phone ? '<div class="text-xs text-muted">' + v.contact_phone + '</div>' : '') + '</td>' +
                '<td data-label="Type">' + escapeHtml(v.contact_type) + '</td>' +
                '<td data-label="Purpose">' + escapeHtml(v.purpose) + '</td>' +
                '<td data-label="Loan">' + (v.loan_info || '—') + '</td>' +
                '<td data-label="Follow-up">' + (v.follow_up_html || '—') + '</td>' +
                '<td data-label="User">' + v.user_name + '</td>' +
                '<td class="col-actions" data-label="Actions">' + buildActions(v) + '</td>' +
                '</tr>';
        }).join('');

        rowsEl.innerHTML = head + body + '</tbody></table>';

        mobileEl.innerHTML = data.map(function (v) {
            return '<div class="dx-m-card">' +
                '<div class="m-hd">' +
                    '<div style="min-width:0;flex:1;"><strong>' + v.contact_name + '</strong>' +
                    '<div class="text-xs text-muted">' + escapeHtml(v.visit_date) + '</div></div>' +
                    '<div class="flex-shrink-0">' + buildActions(v) + '</div>' +
                '</div>' +
                '<div class="m-row"><span class="k">Type</span><span class="v">' + escapeHtml(v.contact_type) + '</span></div>' +
                '<div class="m-row"><span class="k">Purpose</span><span class="v">' + escapeHtml(v.purpose) + '</span></div>' +
                (v.loan_info ? '<div class="m-row"><span class="k">Loan</span><span class="v">' + v.loan_info + '</span></div>' : '') +
                '<div class="m-row"><span class="k">Follow-up</span><span class="v">' + (v.follow_up_html || '—') + '</span></div>' +
                '<div class="m-row"><span class="k">User</span><span class="v">' + v.user_name + '</span></div>' +
            '</div>';
        }).join('');
    }

    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="dx-pages">';
        html += '<button type="button" class="dx-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="dx-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="dx-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        pagerEl.innerHTML = html;
        pagerEl.querySelectorAll('.dx-pg-btn').forEach(function (b) {
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
        refreshActiveFilterCount();
        rowsEl.innerHTML = '<div class="dx-loader">Loading…</div>';
        mobileEl.innerHTML = '';
        fetch(URLS.dataUrl + '?' + buildQuery(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                renderRows(resp.data || []);
                renderPager(resp.recordsTotal || 0, resp.recordsFiltered || 0);
                if (statsEl) {
                    statsEl.innerHTML = '<strong>' + (resp.recordsTotal || 0).toLocaleString('en-IN') + '</strong> total · '
                        + (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' shown';
                }
                if (resultEl) { resultEl.textContent = (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' results'; }
            })
            .catch(function () {
                rowsEl.innerHTML = '<div class="dx-loader" style="color:var(--red);">Failed to load. Please try again.</div>';
            });
    }

    /* ======= Wire filters ======= */
    var debounce = null;
    var searchEl = document.getElementById('dxSearch');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { state.start = 0; load(); }, 300);
        });
    }
    ['dxView', 'dxContactType', 'dxPurpose', 'dxFollowUp', 'dxUser'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.addEventListener('change', function () { state.start = 0; load(); }); }
    });
    var perPage = document.getElementById('dxPerPage');
    if (perPage) {
        perPage.addEventListener('change', function () {
            state.length = parseInt(this.value, 10);
            state.start = 0;
            load();
        });
    }
    document.getElementById('dxFilter').addEventListener('click', function () { state.start = 0; load(); });
    document.getElementById('dxClear').addEventListener('click', function () {
        ['dxView', 'dxSearch', 'dxContactType', 'dxPurpose', 'dxFollowUp', 'dxUser', 'dxDateFrom', 'dxDateTo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) { return; }
            if (el.tagName === 'SELECT') { el.selectedIndex = 0; }
            else {
                el.value = '';
                if (window.jQuery && jQuery(el).data('datepicker')) { jQuery(el).datepicker('clearDates'); }
            }
        });
        state.start = 0;
        load();
    });

    /* Datepickers */
    if (window.jQuery && jQuery.fn.datepicker) {
        jQuery('.shf-datepicker').datepicker({
            format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, clearBtn: true,
            orientation: 'bottom auto', container: 'body',
        }).on('changeDate', function () { state.start = 0; load(); });
    }

    /* Filters card collapse */
    var filtersCard = document.getElementById('dxFiltersCard');
    var filtersToggle = document.getElementById('dxFiltersToggle');
    var activeCountEl = document.getElementById('dxActiveFilterCount');
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
        var defaults = { view: 'my_visits', search: '', contact_type: '', purpose: '', follow_up: 'active', user_id: '', date_from: '', date_to: '' };
        var active = 0;
        Object.keys(defaults).forEach(function (k) {
            if ((f[k] || '') !== defaults[k]) { active++; }
        });
        activeCountEl.textContent = active;
        activeCountEl.classList.toggle('has-active', active > 0);
    }

    load();
})();
