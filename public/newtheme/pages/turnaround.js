/*
 * Newtheme Turnaround Report
 *  - Plain fetch against /reports/turnaround/data (no DataTables).
 *  - Two tabs (overall / stagewise) — stagewise lazy-loads on first click.
 *  - Period preset -> date range mapping mirrors the legacy report.
 */
(function ($) {
    'use strict';

    var URLS = window.__TAT || {};
    var state = { activeTab: 'overall', stageLoaded: false };

    var elOverallRows = document.getElementById('tatOverallRows');
    var elStageRows = document.getElementById('tatStageRows');
    var elOverallCount = document.getElementById('tatOverallCount');
    var elStageCount = document.getElementById('tatStageCount');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmt(d) { return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear(); }

    function getPeriodDates(period) {
        var now = new Date();
        var y = now.getFullYear();
        var m = now.getMonth();
        switch (period) {
            case 'current_month':
                return { from: fmt(new Date(y, m, 1)), to: fmt(now) };
            case 'last_month':
                return { from: fmt(new Date(y, m - 1, 1)), to: fmt(new Date(y, m, 0)) };
            case 'current_quarter': {
                var qStart = m - (m % 3);
                return { from: fmt(new Date(y, qStart, 1)), to: fmt(now) };
            }
            case 'last_quarter': {
                var qStart = m - (m % 3) - 3;
                var qy = y;
                if (qStart < 0) { qStart += 12; qy -= 1; }
                return { from: fmt(new Date(qy, qStart, 1)), to: fmt(new Date(qy, qStart + 3, 0)) };
            }
            case 'current_year':
                return { from: fmt(new Date(y, 0, 1)), to: fmt(now) };
            case 'last_year':
                return { from: fmt(new Date(y - 1, 0, 1)), to: fmt(new Date(y - 1, 11, 31)) };
            case 'all_time':
                return { from: '', to: '' };
            default:
                return {
                    from: document.getElementById('filterDateFrom').value,
                    to: document.getElementById('filterDateTo').value,
                };
        }
    }

    function ddmmyyyyToISO(s) {
        if (!s) return '';
        var p = s.split('/');
        if (p.length !== 3) return '';
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function getFilters() {
        var period = document.getElementById('filterPeriod').value;
        var dates = (period === 'custom')
            ? { from: document.getElementById('filterDateFrom').value, to: document.getElementById('filterDateTo').value }
            : getPeriodDates(period);
        return {
            tab: state.activeTab,
            date_from: ddmmyyyyToISO(dates.from),
            date_to: ddmmyyyyToISO(dates.to),
            bank_id: document.getElementById('filterBank').value || '',
            product_id: document.getElementById('filterProduct').value || '',
            branch_id: document.getElementById('filterBranch').value || '',
            user_id: document.getElementById('filterUser').value || '',
            stage_key: state.activeTab === 'stagewise'
                ? (document.getElementById('filterStage').value || '')
                : '',
        };
    }

    function buildQuery(params) {
        var q = new URLSearchParams();
        Object.keys(params).forEach(function (k) {
            if (params[k] !== undefined && params[k] !== null) { q.set(k, params[k]); }
        });
        return q.toString();
    }

    function colorClassDays(n) {
        if (n == null) return '';
        if (n <= 30) return 'tat-good';
        if (n <= 60) return 'tat-mid';
        return 'tat-bad';
    }

    function colorClassHours(h) {
        if (h == null) return '';
        var days = h / 24;
        if (days <= 3) return 'tat-good';
        if (days <= 7) return 'tat-mid';
        return 'tat-bad';
    }

    function emptyStateHtml(msg) {
        return '<div class="tat-empty">' +
            '<svg class="tat-empty-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>' +
            '<div class="tat-empty-t">' + escapeHtml(msg) + '</div>' +
            '<div class="tat-empty-s">Try a wider period or fewer filters.</div>' +
            '</div>';
    }

    function renderOverall(rows) {
        if (elOverallCount) {
            elOverallCount.textContent = rows.length + (rows.length === 1 ? ' row' : ' rows');
        }
        if (!rows.length) {
            elOverallRows.innerHTML = emptyStateHtml('No completed loans for the selected filters.');
            return;
        }
        var head = '<table class="tbl"><thead><tr>' +
            '<th>User</th>' +
            '<th>Bank</th>' +
            '<th class="num">Loans</th>' +
            '<th>Fastest</th>' +
            '<th>Average</th>' +
            '<th>Slowest</th>' +
            '</tr></thead><tbody>';
        var body = rows.map(function (r) {
            return '<tr>' +
                '<td class="tat-user">' + escapeHtml(r.user_name || '—') + '</td>' +
                '<td class="tat-bank">' + escapeHtml(r.bank_name || '—') + '</td>' +
                '<td class="num">' + (r.total_loans || 0) + '</td>' +
                '<td class="' + colorClassDays(r.min_days_raw) + '">' + escapeHtml(r.min_days || '—') + '</td>' +
                '<td class="' + colorClassDays(r.avg_days_raw) + '">' + escapeHtml(r.avg_days || '—') + '</td>' +
                '<td class="' + colorClassDays(r.max_days_raw) + '">' + escapeHtml(r.max_days || '—') + '</td>' +
                '</tr>';
        }).join('');
        elOverallRows.innerHTML = head + body + '</tbody></table>';
    }

    function renderStage(rows) {
        if (elStageCount) {
            elStageCount.textContent = rows.length + (rows.length === 1 ? ' row' : ' rows');
        }
        if (!rows.length) {
            elStageRows.innerHTML = emptyStateHtml('No stage data for the selected filters.');
            return;
        }
        var head = '<table class="tbl"><thead><tr>' +
            '<th>User</th>' +
            '<th>Bank</th>' +
            '<th>Stage</th>' +
            '<th class="num">Count</th>' +
            '<th>Fastest</th>' +
            '<th>Average</th>' +
            '<th>Slowest</th>' +
            '</tr></thead><tbody>';
        var body = rows.map(function (r) {
            return '<tr>' +
                '<td class="tat-user">' + escapeHtml(r.user_name || '—') + '</td>' +
                '<td class="tat-bank">' + escapeHtml(r.bank_name || '—') + '</td>' +
                '<td>' + escapeHtml(r.stage_name || r.stage_key || '—') + '</td>' +
                '<td class="num">' + (r.times_handled || 0) + '</td>' +
                '<td class="' + colorClassHours(r.min_hours_raw) + '">' + escapeHtml(r.min_time || '—') + '</td>' +
                '<td class="' + colorClassHours(r.avg_hours_raw) + '">' + escapeHtml(r.avg_time || '—') + '</td>' +
                '<td class="' + colorClassHours(r.max_hours_raw) + '">' + escapeHtml(r.max_time || '—') + '</td>' +
                '</tr>';
        }).join('');
        elStageRows.innerHTML = head + body + '</tbody></table>';
    }

    function loadOverall() {
        elOverallRows.innerHTML = '<div class="tat-loader">Loading…</div>';
        fetch(URLS.dataUrl + '?' + buildQuery(getFilters()), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (resp) {
            renderOverall(resp.data || []);
        }).catch(function () {
            elOverallRows.innerHTML = '<div class="tat-loader" style="color:var(--red);">Failed to load. Try again.</div>';
        });
    }

    function loadStage() {
        elStageRows.innerHTML = '<div class="tat-loader">Loading…</div>';
        fetch(URLS.dataUrl + '?' + buildQuery(getFilters()), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (resp) {
            renderStage(resp.data || []);
        }).catch(function () {
            elStageRows.innerHTML = '<div class="tat-loader" style="color:var(--red);">Failed to load. Try again.</div>';
        });
    }

    function refresh() {
        if (state.activeTab === 'overall') { loadOverall(); }
        else { loadStage(); }
    }

    function activateTab(panel) {
        state.activeTab = panel;
        document.querySelectorAll('.tabs .tab').forEach(function (t) {
            t.classList.toggle('active', t.dataset.panel === panel);
        });
        document.querySelectorAll('.tat-panel').forEach(function (p) {
            p.style.display = p.dataset.panelId === panel ? '' : 'none';
        });
        var stageWrap = document.querySelector('.tat-stage-wrap');
        if (stageWrap) { stageWrap.style.display = panel === 'stagewise' ? '' : 'none'; }

        if (panel === 'stagewise' && !state.stageLoaded) {
            state.stageLoaded = true;
            loadStage();
        } else {
            refresh();
        }
    }

    /* ==================== Init ==================== */
    document.addEventListener('DOMContentLoaded', function () {
        // Seed initial date inputs
        var init = getPeriodDates('current_month');
        document.getElementById('filterDateFrom').value = init.from;
        document.getElementById('filterDateTo').value = init.to;

        // Datepicker (jQuery + bootstrap-datepicker globals come from the layout)
        if ($ && $.fn && $.fn.datepicker) {
            $('.shf-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                clearBtn: true,
            });
        }

        // Period dropdown toggles custom-date fields
        var periodSel = document.getElementById('filterPeriod');
        periodSel.addEventListener('change', function () {
            var isCustom = this.value === 'custom';
            document.querySelectorAll('.tat-custom-dates').forEach(function (el) {
                el.style.display = isCustom ? '' : 'none';
            });
            if (!isCustom) {
                var d = getPeriodDates(this.value);
                document.getElementById('filterDateFrom').value = d.from;
                document.getElementById('filterDateTo').value = d.to;
                refresh();
            }
        });

        // Auto-refresh on non-date selects
        ['filterBank', 'filterProduct', 'filterBranch', 'filterUser', 'filterStage'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.tagName === 'SELECT') {
                el.addEventListener('change', refresh);
            }
        });

        // Tabs
        document.querySelectorAll('.tabs .tab').forEach(function (t) {
            t.addEventListener('click', function (e) {
                e.preventDefault();
                activateTab(t.dataset.panel);
            });
        });

        // Filter buttons
        document.getElementById('tatApply').addEventListener('click', refresh);
        document.getElementById('tatClear').addEventListener('click', function () {
            document.getElementById('filterPeriod').value = 'current_month';
            document.querySelectorAll('.tat-custom-dates').forEach(function (el) { el.style.display = 'none'; });
            var d = getPeriodDates('current_month');
            document.getElementById('filterDateFrom').value = d.from;
            document.getElementById('filterDateTo').value = d.to;
            ['filterBank', 'filterProduct', 'filterBranch', 'filterUser', 'filterStage'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.value = ''; }
            });
            refresh();
        });

        // Initial load
        loadOverall();
    });
})(window.jQuery);
