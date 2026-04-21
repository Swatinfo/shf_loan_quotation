/*
 * Newtheme Loans index — fetches the existing loans.data endpoint
 * (DataTables-shaped JSON) and renders rows into the newtheme `.tbl` with
 * adaptive filters, pagination, and mobile m-cards.
 */
(function () {
    'use strict';

    var URLS = window.__LX || {};
    var canSeeBank = !!URLS.canSeeBank;
    var canSeeBranch = !!URLS.canSeeBranch;
    var canSeeRole = !!URLS.canSeeRole;

    var state = {
        start: 0,
        length: 50,
        draw: 0,
    };

    var rowsEl = document.getElementById('lxRows');
    var mobileRowsEl = document.getElementById('lxMobileRows');
    var pagerEl = document.getElementById('lxPager');
    var statsEl = document.getElementById('lxStatsLine');
    var resultEl = document.getElementById('lxResultCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function convertDate(val) {
        if (!val) { return ''; }
        var parts = val.split('/');
        return parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : val;
    }

    function readFilters() {
        var get = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
        return {
            status: get('lxStatus'),
            customer_type: get('lxType'),
            bank_id: canSeeBank ? get('lxBank') : '',
            branch_id: canSeeBranch ? get('lxBranch') : '',
            stage: get('lxStage'),
            role: canSeeRole ? get('lxRole') : '',
            docket: canSeeBank ? get('lxDocket') : '',
            docket_date: convertDate(get('lxDocketDate')),
            date_from: convertDate(get('lxDateFrom')),
            date_to: convertDate(get('lxDateTo')),
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
        Object.keys(f).forEach(function (k) {
            if (f[k] !== '' && f[k] != null) { params.set(k, f[k]); }
        });
        return params.toString();
    }

    /* ======= Rendering ======= */
    function renderRows(data) {
        if (!data.length) {
            rowsEl.innerHTML = '<div class="lx-loader" style="padding:36px 24px;"><div style="font-size:30px;margin-bottom:6px;">📭</div><div style="font-weight:600;color:var(--ink-3);">No loans match the filters</div></div>';
            mobileRowsEl.innerHTML = '';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th>Loan #</th>' +
            '<th>Customer</th>' +
            '<th>Bank / Product</th>' +
            '<th class="num">Amount</th>' +
            '<th>Stage</th>' +
            '<th>Owner</th>' +
            '<th>Status</th>' +
            '<th>Date</th>' +
            '<th class="col-actions"></th>' +
            '</tr></thead><tbody>';

        // Fields from the backend that are already HTML (pre-rendered in LoanController::loanData):
        //   loan_number, bank_product, amount_info, current_stage_name, owner_info,
        //   status_label, created_at, actions_html.
        // Plain-text fields: customer_name, location_name.
        var body = data.map(function (l) {
            return '<tr>' +
                '<td data-label="Loan #"><span class="font-mono" style="font-weight:600;">' + (l.loan_number || '') + '</span></td>' +
                '<td data-label="Customer"><strong>' + escapeHtml(l.customer_name || '') + '</strong></td>' +
                '<td data-label="Bank / Product">' + (l.bank_product || '') + '</td>' +
                '<td class="num tnum" data-label="Amount">' + (l.amount_info || '') + '</td>' +
                '<td data-label="Stage">' + (l.current_stage_name || '') + '</td>' +
                '<td data-label="Owner">' + (l.owner_info || '') + '</td>' +
                '<td data-label="Status">' + (l.status_label || '') + '</td>' +
                '<td data-label="Date">' + (l.created_at || '') + '</td>' +
                '<td class="col-actions" data-label="Actions">' + (l.actions_html || '') + '</td>' +
                '</tr>';
        }).join('');

        rowsEl.innerHTML = head + body + '</tbody></table>';

        // Mobile m-cards. Same HTML/plain-text split as the desktop row.
        var cards = data.map(function (l) {
            var ownerPlain = (l.owner_info || '—').replace(/<br\s*\/?>/gi, ' · ').replace(/<[^>]+>/g, '').trim();
            return '<div class="lx-m-card">' +
                '<div class="m-hd">' +
                    '<div style="min-width:0;flex:1;"><strong>' + escapeHtml(l.customer_name || '') + '</strong>' +
                    '<div class="text-xs text-muted font-mono">' + (l.loan_number || '') + '</div></div>' +
                    '<div class="flex-shrink-0">' + (l.status_label || '') + '</div>' +
                '</div>' +
                '<div class="m-row"><span class="k">Amount</span><span class="v">' + (l.amount_info || escapeHtml(l.formatted_amount || '')) + '</span></div>' +
                '<div class="m-row"><span class="k">Bank</span><span class="v">' + (l.bank_product || escapeHtml(l.bank_name || '')) + '</span></div>' +
                '<div class="m-row"><span class="k">Stage</span><span class="v">' + (l.current_stage_name || '') + '</span></div>' +
                '<div class="m-row"><span class="k">Owner</span><span class="v">' + escapeHtml(ownerPlain) + '</span></div>' +
                '<div class="m-row"><span class="k">Date</span><span class="v">' + (l.created_at || '') + '</span></div>' +
                (l.actions_html ? '<div class="lx-m-actions">' + l.actions_html + '</div>' : '') +
            '</div>';
        }).join('');
        mobileRowsEl.innerHTML = cards;
    }

    /* ======= Pagination ======= */
    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="lx-pages">';
        html += '<button type="button" class="lx-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="lx-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="lx-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        pagerEl.innerHTML = html;
        pagerEl.querySelectorAll('.lx-pg-btn').forEach(function (b) {
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
        rowsEl.innerHTML = '<div class="lx-loader">Loading…</div>';
        mobileRowsEl.innerHTML = '';
        fetch(URLS.dataUrl + '?' + buildQuery(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                renderRows(resp.data || []);
                renderPager(resp.recordsTotal || 0, resp.recordsFiltered || 0);
                if (statsEl) {
                    statsEl.innerHTML = '<strong>' + (resp.recordsTotal || 0).toLocaleString('en-IN') + '</strong> total · '
                        + (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' shown';
                }
                if (resultEl) {
                    resultEl.textContent = (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' results';
                }
            })
            .catch(function () {
                rowsEl.innerHTML = '<div class="lx-loader" style="color:var(--red);">Failed to load. Please try again.</div>';
            });
    }

    /* ======= Filter wiring ======= */
    var allFilterIds = ['lxStatus', 'lxType', 'lxBank', 'lxBranch', 'lxStage', 'lxRole', 'lxDocket', 'lxDocketDate', 'lxDateFrom', 'lxDateTo'];

    // Status filter auto-reloads (matches legacy behavior)
    var statusEl = document.getElementById('lxStatus');
    if (statusEl) { statusEl.addEventListener('change', function () { state.start = 0; load(); }); }

    // Docket: show/hide custom date, auto-reload unless custom
    var docketEl = document.getElementById('lxDocket');
    var docketDateWrap = document.getElementById('lxDocketDateWrap');
    var docketDateEl = document.getElementById('lxDocketDate');
    if (docketEl) {
        docketEl.addEventListener('change', function () {
            var isCustom = this.value === 'custom';
            if (docketDateWrap) { docketDateWrap.classList.toggle('lx-hidden', !isCustom); }
            if (!isCustom) {
                if (docketDateEl) {
                    docketDateEl.value = '';
                    if (window.jQuery && jQuery(docketDateEl).data('datepicker')) {
                        jQuery(docketDateEl).datepicker('clearDates');
                    }
                }
                state.start = 0;
                load();
            }
        });
    }
    if (docketDateEl) {
        docketDateEl.addEventListener('change', function () { state.start = 0; load(); });
    }

    // Explicit apply + clear buttons
    var filterBtn = document.getElementById('lxFilter');
    if (filterBtn) { filterBtn.addEventListener('click', function () { state.start = 0; load(); }); }

    var clearBtn = document.getElementById('lxClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            allFilterIds.forEach(function (id) {
                var el = document.getElementById(id);
                if (!el) { return; }
                if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                } else {
                    el.value = '';
                    if (window.jQuery && jQuery(el).data('datepicker')) {
                        jQuery(el).datepicker('clearDates');
                    }
                }
            });
            if (docketDateWrap) { docketDateWrap.classList.add('lx-hidden'); }
            state.start = 0;
            load();
        });
    }

    // Per-page
    var perPage = document.getElementById('lxPerPage');
    if (perPage) {
        perPage.addEventListener('change', function () {
            state.length = parseInt(this.value, 10);
            state.start = 0;
            load();
        });
    }

    /* ======= Datepickers ======= */
    if (window.jQuery && jQuery.fn.datepicker) {
        jQuery('.shf-datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true,
            clearBtn: true,
            orientation: 'bottom auto',
            container: 'body',
        });
    }

    /* ======= Filters card collapse / counter ======= */
    var filtersCard = document.getElementById('lxFiltersCard');
    var filtersToggle = document.getElementById('lxFiltersToggle');
    var activeCountEl = document.getElementById('lxActiveFilterCount');

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
        // Defaults: status=active is the pre-selected baseline; others are empty.
        var defaults = { status: 'active', customer_type: '', bank_id: '', branch_id: '', stage: '', role: '', docket: '', docket_date: '', date_from: '', date_to: '' };
        var active = 0;
        Object.keys(defaults).forEach(function (k) {
            if ((f[k] || '') !== defaults[k]) { active++; }
        });
        activeCountEl.textContent = active;
        activeCountEl.classList.toggle('has-active', active > 0);
    }

    /* ======= Initial load ======= */
    load();
})();
