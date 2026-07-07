/*
 * Newtheme Activity Log
 *  - Plain fetch against /activity-log/data (server-side pagination & filters).
 *  - Desktop table + mobile cards.
 *  - Action-type color map mirrors the newtheme pill/badge tones.
 */
(function ($) {
    'use strict';

    var URLS = window.__AL || {};
    var state = { start: 0, length: 50, draw: 0 };

    var elRows = document.getElementById('alRows');
    var elMobile = document.getElementById('alMobileRows');
    var elPager = document.getElementById('alPager');
    var elStats = document.getElementById('alStatsLine');
    var elCount = document.getElementById('alResultCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    // Action -> newtheme color class. Anything not mapped falls through to gray.
    var ACTION_CLASS = {
        login: 'al-act-green',
        logout: 'al-act-gray',
        create_quotation: 'al-act-blue',
        update_quotation: 'al-act-blue',
        delete_quotation: 'al-act-red',
        hold_quotation: 'al-act-amber',
        cancel_quotation: 'al-act-red',
        resume_quotation: 'al-act-green',
        create_loan: 'al-act-blue',
        update_loan: 'al-act-blue',
        delete_loan: 'al-act-red',
        create_user: 'al-act-blue',
        update_user: 'al-act-blue',
        delete_user: 'al-act-red',
        update_settings: 'al-act-orange',
        update_permissions: 'al-act-orange',
        save_product_stages: 'al-act-orange',
        impersonate_start: 'al-act-violet',
        impersonate_end: 'al-act-gray',
        create_dvr: 'al-act-blue',
        update_dvr: 'al-act-blue',
        delete_dvr: 'al-act-red',
        task_created: 'al-act-blue',
        task_updated: 'al-act-blue',
        task_status_changed: 'al-act-amber',
        task_comment_added: 'al-act-violet',
        task_deleted: 'al-act-red',
    };
    function actionPill(action, label) {
        var cls = ACTION_CLASS[action] || 'al-act-gray';
        return '<span class="al-act ' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function buildQuery() {
        state.draw++;
        var params = new URLSearchParams();
        params.set('draw', state.draw);
        params.set('start', state.start);
        params.set('length', state.length);
        params.set('order[0][column]', '0');
        params.set('order[0][dir]', 'desc');
        params.set('search[value]', document.getElementById('alSearch').value || '');
        params.set('user_id', document.getElementById('filterUser').value || '');
        params.set('action_type', document.getElementById('filterAction').value || '');
        params.set('date_from', ddmmyyyyToISO(document.getElementById('alDateFrom').value));
        params.set('date_to', ddmmyyyyToISO(document.getElementById('alDateTo').value));
        return params.toString();
    }

    function ddmmyyyyToISO(s) {
        if (!s) return '';
        var p = s.split('/');
        if (p.length !== 3) return '';
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function emptyStateHtml() {
        return '<div class="al-empty">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
            '<div class="al-empty-t">No activity recorded</div>' +
            '<div class="al-empty-s">Activity will appear here as users perform actions.</div>' +
            '</div>';
    }

    function renderRows(rows) {
        if (!rows.length) {
            elRows.innerHTML = emptyStateHtml();
            elMobile.innerHTML = '';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th>Date</th>' +
            '<th>User</th>' +
            '<th>Action</th>' +
            '<th>Subject</th>' +
            '<th>Details</th>' +
            '<th>IP</th>' +
            '</tr></thead><tbody>';

        var body = rows.map(function (r) {
            return '<tr>' +
                '<td class="al-date">' + escapeHtml(r.date_short || '') + '</td>' +
                '<td class="al-user">' + escapeHtml(r.user_name || 'System') + '</td>' +
                '<td>' + actionPill(r.action, r.action_label) + '</td>' +
                '<td class="al-subject">' + escapeHtml(r.subject || '—') + '</td>' +
                '<td class="al-details" title="' + escapeHtml(r.details || '') + '">' +
                    // `details` may already contain minor HTML from the server (e() output),
                    // but treat as plain text to be safe.
                    escapeHtml(r.details || '—') +
                '</td>' +
                '<td class="al-ip">' + escapeHtml(r.ip_address || '—') + '</td>' +
                '</tr>';
        }).join('');

        elRows.innerHTML = head + body + '</tbody></table>';

        elMobile.innerHTML = rows.map(function (r) {
            return '<div class="al-m-card">' +
                '<div class="al-m-hd">' +
                    actionPill(r.action, r.action_label) +
                    '<span class="al-date">' + escapeHtml(r.date_short || '') + '</span>' +
                '</div>' +
                '<div class="al-m-row"><span class="k">User</span><span class="v">' + escapeHtml(r.user_name || 'System') + '</span></div>' +
                (r.subject && r.subject !== '—' ? '<div class="al-m-row"><span class="k">Subject</span><span class="v">' + escapeHtml(r.subject) + '</span></div>' : '') +
                (r.details && r.details !== '—' ? '<div class="al-m-row"><span class="k">Details</span><span class="v">' + escapeHtml(r.details) + '</span></div>' : '') +
                (r.ip_address && r.ip_address !== '—' ? '<div class="al-m-row"><span class="k">IP</span><span class="v al-ip">' + escapeHtml(r.ip_address) + '</span></div>' : '') +
                '</div>';
        }).join('');
    }

    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="al-pages">';
        html += '<button type="button" class="al-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="al-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="al-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        elPager.innerHTML = html;
        elPager.querySelectorAll('.al-pg-btn').forEach(function (b) {
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
        elRows.innerHTML = '<div class="al-loader">Loading…</div>';
        elMobile.innerHTML = '';
        fetch(URLS.dataUrl + '?' + buildQuery(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (resp) {
            renderRows(resp.data || []);
            renderPager(resp.recordsTotal || 0, resp.recordsFiltered || 0);
            if (elStats) {
                elStats.innerHTML = '<strong>' + (resp.recordsTotal || 0).toLocaleString('en-IN') + '</strong> total · '
                    + (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' shown';
            }
            if (elCount) {
                elCount.textContent = (resp.recordsFiltered || 0).toLocaleString('en-IN') + ' entries';
            }
        }).catch(function () {
            elRows.innerHTML = '<div class="al-loader" style="color:var(--red);">Failed to load. Try again.</div>';
        });
    }

    /* ==================== Init ==================== */
    document.addEventListener('DOMContentLoaded', function () {
        if ($ && $.fn && $.fn.datepicker) {
            $('.shf-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                clearBtn: true,
            });
        }

        var perPage = document.getElementById('alPerPage');
        if (perPage) {
            perPage.addEventListener('change', function () {
                state.length = parseInt(this.value, 10);
                state.start = 0;
                load();
            });
        }

        var debounce = null;
        var searchEl = document.getElementById('alSearch');
        if (searchEl) {
            searchEl.addEventListener('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () { state.start = 0; load(); }, 300);
            });
        }

        ['filterUser', 'filterAction'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('change', function () { state.start = 0; load(); }); }
        });

        document.getElementById('alApply').addEventListener('click', function () { state.start = 0; load(); });
        document.getElementById('alClear').addEventListener('click', function () {
            document.getElementById('alSearch').value = '';
            document.getElementById('filterUser').value = '';
            document.getElementById('filterAction').value = '';
            if ($ && $.fn && $.fn.datepicker) {
                $('#alDateFrom, #alDateTo').val('').datepicker('update', '');
            } else {
                document.getElementById('alDateFrom').value = '';
                document.getElementById('alDateTo').value = '';
            }
            state.start = 0;
            load();
        });

        load();
    });
})(window.jQuery);
