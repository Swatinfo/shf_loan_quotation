/*
 * Newtheme Customers index — fetches customers.data (DataTables-shaped)
 * and renders rows into the newtheme `.tbl` plus mobile cards.
 */
(function () {
    'use strict';

    var URLS = window.__CX || {};
    var canEdit = !!URLS.canEdit;
    var state = { start: 0, length: 25, draw: 0 };

    var rowsEl = document.getElementById('cxRows');
    var mobileEl = document.getElementById('cxMobileRows');
    var pagerEl = document.getElementById('cxPager');
    var statsEl = document.getElementById('cxStatsLine');
    var resultEl = document.getElementById('cxResultCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function buildQuery() {
        state.draw++;
        var search = (document.getElementById('cxSearch') || {}).value || '';
        var params = new URLSearchParams();
        params.set('draw', state.draw);
        params.set('start', state.start);
        params.set('length', state.length);
        params.set('order[0][column]', '0');
        params.set('order[0][dir]', 'asc');
        params.set('search[value]', search);
        return params.toString();
    }

    function buildActions(c) {
        var eyePath = 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z';
        var penPath = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
        var icon = function (p) { return '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' + p + '"/></svg>'; };
        var out = '<div class="cx-actions">';
        out += '<a class="cx-act tone-info" href="' + c.show_url + '" title="View" aria-label="View">' + icon(eyePath) + '</a>';
        if (c.edit_url) {
            out += '<a class="cx-act tone-gray" href="' + c.edit_url + '" title="Edit" aria-label="Edit">' + icon(penPath) + '</a>';
        }
        out += '</div>';
        return out;
    }

    function loanCountBadge(n) {
        var cls = n > 0 ? 'cx-loan-count' : 'cx-loan-count zero';
        return '<span class="' + cls + '">' + n + '</span>';
    }

    function renderRows(data) {
        if (!data.length) {
            rowsEl.innerHTML = '<div class="cx-loader" style="padding:36px 24px;"><div style="font-size:30px;margin-bottom:6px;">👥</div><div style="font-weight:600;color:var(--ink-3);">No customers match the search</div></div>';
            mobileEl.innerHTML = '';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th>Name</th>' +
            '<th>Mobile</th>' +
            '<th>Email</th>' +
            '<th>PAN</th>' +
            '<th class="num">Loans</th>' +
            '<th>Added</th>' +
            '<th class="col-actions"></th>' +
            '</tr></thead><tbody>';

        var body = data.map(function (c) {
            return '<tr>' +
                '<td data-label="Name"><strong>' + c.customer_name + '</strong></td>' +
                '<td data-label="Mobile">' + (c.mobile || '—') + '</td>' +
                '<td data-label="Email">' + (c.email || '—') + '</td>' +
                '<td data-label="PAN">' + (c.pan_number || '—') + '</td>' +
                '<td class="num" data-label="Loans">' + loanCountBadge(c.loans_count) + '</td>' +
                '<td data-label="Added">' + escapeHtml(c.created_at || '') + '</td>' +
                '<td class="col-actions" data-label="Actions">' + buildActions(c) + '</td>' +
                '</tr>';
        }).join('');

        rowsEl.innerHTML = head + body + '</tbody></table>';

        mobileEl.innerHTML = data.map(function (c) {
            return '<div class="cx-m-card">' +
                '<div class="m-hd">' +
                    '<div style="min-width:0;flex:1;"><strong>' + c.customer_name + '</strong>' +
                    '<div class="text-xs text-muted">' + (c.mobile || '—') + '</div></div>' +
                    '<div class="flex-shrink-0">' + loanCountBadge(c.loans_count) + '</div>' +
                '</div>' +
                (c.email ? '<div class="m-row"><span class="k">Email</span><span class="v">' + c.email + '</span></div>' : '') +
                (c.pan_number ? '<div class="m-row"><span class="k">PAN</span><span class="v">' + c.pan_number + '</span></div>' : '') +
                '<div class="m-row"><span class="k">Added</span><span class="v">' + escapeHtml(c.created_at || '') + '</span></div>' +
                '<div class="cx-m-actions">' + buildActions(c) + '</div>' +
            '</div>';
        }).join('');
    }

    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="cx-pages">';
        html += '<button type="button" class="cx-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="cx-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="cx-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        pagerEl.innerHTML = html;
        pagerEl.querySelectorAll('.cx-pg-btn').forEach(function (b) {
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
        rowsEl.innerHTML = '<div class="cx-loader">Loading…</div>';
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
                rowsEl.innerHTML = '<div class="cx-loader" style="color:var(--red);">Failed to load. Please try again.</div>';
            });
    }

    /* Wire filters */
    var debounce = null;
    var searchEl = document.getElementById('cxSearch');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { state.start = 0; load(); }, 300);
        });
    }
    var perPage = document.getElementById('cxPerPage');
    if (perPage) {
        perPage.addEventListener('change', function () {
            state.length = parseInt(this.value, 10);
            state.start = 0;
            load();
        });
    }

    load();
})();
