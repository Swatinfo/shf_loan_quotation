/*
 * Newtheme Loan Pipeline report
 *  - Status chips double as the status filter (default: active).
 *  - Column set adapts to the selected status; Active view renders stage
 *    lines (in-progress + pending-in-parallel with queued days).
 *  - Workload tab groups in-progress stages by current holder.
 *  - Plain fetch + inline loader (list-page convention, no global overlay).
 */
(function ($) {
    'use strict';

    var URLS = window.__PL || {};
    var state = { status: 'active', tab: 'loans' };

    var elRows = document.getElementById('plRows');
    var elWorkRows = document.getElementById('plWorkRows');
    var elRowCount = document.getElementById('plRowCount');
    var elWorkCount = document.getElementById('plWorkCount');
    var elModeLabel = document.getElementById('plModeLabel');

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
            case 'current_month': return { from: fmt(new Date(y, m, 1)), to: fmt(now) };
            case 'last_month': return { from: fmt(new Date(y, m - 1, 1)), to: fmt(new Date(y, m, 0)) };
            case 'current_quarter': {
                var qs = m - (m % 3);
                return { from: fmt(new Date(y, qs, 1)), to: fmt(now) };
            }
            case 'last_quarter': {
                var lqs = m - (m % 3) - 3;
                var qy = y;
                if (lqs < 0) { lqs += 12; qy -= 1; }
                return { from: fmt(new Date(qy, lqs, 1)), to: fmt(new Date(qy, lqs + 3, 0)) };
            }
            case 'current_year': return { from: fmt(new Date(y, 0, 1)), to: fmt(now) };
            case 'last_year': return { from: fmt(new Date(y - 1, 0, 1)), to: fmt(new Date(y - 1, 11, 31)) };
            case 'all_time': return { from: '', to: '' };
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
            tab: state.tab,
            status: state.status,
            date_from: ddmmyyyyToISO(dates.from),
            date_to: ddmmyyyyToISO(dates.to),
            bank_id: document.getElementById('filterBank').value || '',
            product_id: document.getElementById('filterProduct').value || '',
            branch_id: (document.getElementById('filterBranch') || {}).value || '',
            user_id: (document.getElementById('filterUser') || {}).value || '',
            stage_key: document.getElementById('filterStage').value || '',
            stuck_days: document.getElementById('filterStuck').value || '',
        };
    }

    function buildQuery(params) {
        var q = new URLSearchParams();
        Object.keys(params).forEach(function (k) {
            if (params[k] !== undefined && params[k] !== null) { q.set(k, params[k]); }
        });
        return q.toString();
    }

    function daysClass(n) {
        if (n == null) return 'queued';
        if (n <= 7) return 'good';
        if (n <= 14) return 'mid';
        return 'bad';
    }

    function emptyStateHtml(msg) {
        return '<div class="pl-empty">' +
            '<svg class="pl-empty-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>' +
            '<div class="pl-empty-t">' + escapeHtml(msg) + '</div>' +
            '<div class="pl-empty-s">Try a wider period or fewer filters.</div>' +
            '</div>';
    }

    function stageLinesHtml(lines) {
        if (!lines || !lines.length) { return '<span class="pl-muted">—</span>'; }
        return lines.map(function (l) {
            if (l.kind === 'pending') {
                return '<div class="pl-stage-line is-pending">' +
                    '<span class="pl-stage-name">⏳ ' + escapeHtml(l.stage_name) + '</span>' +
                    '<span class="pl-stage-owner">' + escapeHtml(l.owner || 'unassigned') + '</span>' +
                    (l.queued_days != null ? '<span class="pl-days queued">queued ' + l.queued_days + 'd</span>' : '<span class="pl-days queued">queued</span>') +
                    '</div>';
            }
            return '<div class="pl-stage-line">' +
                '<span class="pl-stage-name">' + escapeHtml(l.stage_name) + '</span>' +
                '<span class="pl-stage-owner">' + escapeHtml(l.owner || '—') + '</span>' +
                '<span class="pl-days ' + daysClass(l.days_in_stage) + '">' + (l.days_in_stage != null ? l.days_in_stage + 'd' : '—') + '</span>' +
                (l.days_with_owner != null && l.days_with_owner !== l.days_in_stage
                    ? '<span class="pl-muted">(' + l.days_with_owner + 'd with owner)</span>' : '') +
                (l.open_queries ? '<span class="pl-query-flag" title="Unresolved queries">⚠ ' + l.open_queries + '</span>' : '') +
                '</div>';
        }).join('');
    }

    /* Column sets per status */
    function columnsFor(status) {
        var base = ['Loan #', 'Customer', 'Bank / Product', 'Branch', 'Advisor', 'Amount', 'Age'];
        if (status === 'active') { return base.concat(['Current Stage(s)']); }
        if (status === 'on_hold') { return base.concat(['Current Stage(s)', 'Hold Reason', 'On Hold Since']); }
        if (status === 'completed') { return base.concat(['Sanctioned', 'Disbursed', 'TAT']); }
        if (status === 'rejected') { return base.concat(['Rejected At Stage', 'Reason', 'By', 'Date']); }
        if (status === 'cancelled') { return base.concat(['Reason', 'Date']); }
        return base.concat(['Status', 'Current Stage(s)']);
    }

    function cellsFor(status, r) {
        var base = [
            '<td class="pl-loan">' + (r.stages_url ? '<a href="' + escapeHtml(r.stages_url) + '">' + escapeHtml(r.loan_number) + '</a>' : escapeHtml(r.loan_number)) + '</td>',
            '<td>' + escapeHtml(r.customer_name || '—') + '</td>',
            '<td class="pl-muted">' + escapeHtml(r.bank_product || '—') + '</td>',
            '<td class="pl-muted">' + escapeHtml(r.branch_name || '—') + '</td>',
            '<td>' + escapeHtml(r.advisor_name || '—') + '</td>',
            '<td class="num">' + escapeHtml(r.loan_amount || '—') + '</td>',
            '<td class="num">' + (r.loan_age_days != null ? r.loan_age_days + 'd' : '—') + '</td>',
        ];
        var stageCell = '<td>' + stageLinesHtml(r.stage_lines) + '</td>';
        if (status === 'active') { return base.concat([stageCell]); }
        if (status === 'on_hold') {
            return base.concat([stageCell,
                '<td>' + escapeHtml(r.status_reason || '—') + '</td>',
                '<td>' + escapeHtml(r.status_since || '—') + '</td>']);
        }
        if (status === 'completed') {
            return base.concat([
                '<td class="num">' + escapeHtml(r.sanctioned_amount || '—') + '</td>',
                '<td class="num">' + escapeHtml(r.disbursed_amount || '—') + '</td>',
                '<td class="num">' + (r.tat_days != null ? r.tat_days + 'd' : '—') + '</td>']);
        }
        if (status === 'rejected') {
            return base.concat([
                '<td>' + escapeHtml(r.rejected_stage || '—') + '</td>',
                '<td>' + escapeHtml(r.rejection_reason || '—') + '</td>',
                '<td>' + escapeHtml(r.rejected_by || '—') + '</td>',
                '<td>' + escapeHtml(r.rejected_at || '—') + '</td>']);
        }
        if (status === 'cancelled') {
            return base.concat([
                '<td>' + escapeHtml(r.status_reason || '—') + '</td>',
                '<td>' + escapeHtml(r.status_since || '—') + '</td>']);
        }
        return base.concat([
            '<td><span class="pl-status ' + escapeHtml(r.status || '') + '">' + escapeHtml((r.status || '—').replace('_', ' ')) + '</span></td>',
            stageCell]);
    }

    function renderLoans(rows) {
        if (elRowCount) { elRowCount.textContent = rows.length + (rows.length === 1 ? ' row' : ' rows'); }
        if (elModeLabel) { elModeLabel.textContent = state.status.replace('_', ' '); }
        if (!rows.length) {
            elRows.innerHTML = emptyStateHtml('No loans for the selected filters.');
            return;
        }
        var head = '<table class="tbl"><thead><tr>' +
            columnsFor(state.status).map(function (c) { return '<th>' + c + '</th>'; }).join('') +
            '</tr></thead><tbody>';
        var body = rows.map(function (r) {
            return '<tr>' + cellsFor(state.status, r).join('') + '</tr>';
        }).join('');
        elRows.innerHTML = head + body + '</tbody></table>';
    }

    function renderChips(summary, queued) {
        if (!summary) { return; }
        Object.keys(summary).forEach(function (key) {
            var c = document.querySelector('[data-count="' + key + '"]');
            var a = document.querySelector('[data-amount="' + key + '"]');
            if (c) { c.textContent = summary[key].count; }
            if (a) { a.textContent = summary[key].amount; }
        });
        var note = document.getElementById('plQueuedNote');
        if (note) { note.textContent = queued ? queued + ' stage(s) queued in parallel' : ''; }
    }

    function renderWorkload(rows) {
        if (elWorkCount) { elWorkCount.textContent = rows.length + (rows.length === 1 ? ' user' : ' users'); }
        if (!rows.length) {
            elWorkRows.innerHTML = emptyStateHtml('No in-progress stages for the selected filters.');
            return;
        }
        var head = '<table class="tbl"><thead><tr>' +
            '<th>User</th><th class="num">Stages Held</th><th class="num">Oldest</th>' +
            '<th class="num">Average</th><th class="num">Stuck &gt; 7d</th><th>Stages</th>' +
            '</tr></thead><tbody>';
        var body = rows.map(function (r) {
            return '<tr>' +
                '<td class="pl-loan">' + escapeHtml(r.user_name) + '</td>' +
                '<td class="num">' + r.held + '</td>' +
                '<td class="num"><span class="pl-days ' + daysClass(r.oldest_days) + '">' + r.oldest_days + 'd</span></td>' +
                '<td class="num">' + r.avg_days + 'd</td>' +
                '<td class="num">' + (r.stuck ? '<span class="pl-days bad">' + r.stuck + '</span>' : '0') + '</td>' +
                '<td class="pl-muted">' + escapeHtml(r.stages) + '</td>' +
                '</tr>';
        }).join('');
        elWorkRows.innerHTML = head + body + '</tbody></table>';
    }

    function refresh() {
        var target = state.tab === 'workload' ? elWorkRows : elRows;
        target.innerHTML = '<div class="pl-loader">Loading…</div>';
        fetch(URLS.dataUrl + '?' + buildQuery(getFilters()), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (resp) {
            if (state.tab === 'workload') {
                renderWorkload(resp.data || []);
            } else {
                renderChips(resp.summary, resp.queued_parallel);
                renderLoans(resp.data || []);
            }
        }).catch(function () {
            target.innerHTML = '<div class="pl-loader" style="color:var(--red);">Failed to load. Try again.</div>';
        });
    }

    function syncActiveOnlyFilters() {
        var showStageFilters = state.tab === 'loans' && (state.status === 'active' || state.status === 'on_hold' || state.status === 'all');
        document.querySelectorAll('.pl-active-only').forEach(function (el) {
            el.style.display = showStageFilters ? '' : 'none';
        });
    }

    /* ==================== Init ==================== */
    document.addEventListener('DOMContentLoaded', function () {
        var init = getPeriodDates('all_time');
        document.getElementById('filterDateFrom').value = init.from;
        document.getElementById('filterDateTo').value = init.to;

        if ($ && $.fn && $.fn.datepicker) {
            $('.shf-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                clearBtn: true,
            });
        }

        // Status chips
        document.querySelectorAll('.pl-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                state.status = chip.dataset.status;
                document.querySelectorAll('.pl-chip').forEach(function (c) {
                    c.classList.toggle('is-active', c === chip);
                });
                syncActiveOnlyFilters();
                if (state.tab !== 'loans') {
                    state.tab = 'loans';
                    activateTab('loans', true);
                }
                refresh();
            });
        });

        // Period preset
        var periodSel = document.getElementById('filterPeriod');
        periodSel.addEventListener('change', function () {
            var isCustom = this.value === 'custom';
            document.querySelectorAll('.pl-custom-dates').forEach(function (el) {
                el.style.display = isCustom ? '' : 'none';
            });
            if (!isCustom) {
                var d = getPeriodDates(this.value);
                document.getElementById('filterDateFrom').value = d.from;
                document.getElementById('filterDateTo').value = d.to;
                refresh();
            }
        });

        ['filterBank', 'filterProduct', 'filterBranch', 'filterUser', 'filterStage'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.tagName === 'SELECT') { el.addEventListener('change', refresh); }
        });

        function activateTab(panel, skipRefresh) {
            state.tab = panel;
            document.querySelectorAll('.tabs .tab').forEach(function (t) {
                t.classList.toggle('active', t.dataset.panel === panel);
            });
            document.querySelectorAll('.pl-panel').forEach(function (p) {
                p.style.display = p.dataset.panelId === panel ? '' : 'none';
            });
            syncActiveOnlyFilters();
            if (!skipRefresh) { refresh(); }
        }

        document.querySelectorAll('.tabs .tab').forEach(function (t) {
            t.addEventListener('click', function (e) {
                e.preventDefault();
                activateTab(t.dataset.panel);
            });
        });

        document.getElementById('plApply').addEventListener('click', refresh);
        var exportBtn = document.getElementById('plExport');
        if (exportBtn) {
            // Server re-applies the same filters/scope and exports ALL
            // matching rows (never just the rendered page).
            exportBtn.addEventListener('click', function () {
                window.location = URLS.exportUrl + '?' + buildQuery(getFilters());
            });
        }
        document.getElementById('plClear').addEventListener('click', function () {
            document.getElementById('filterPeriod').value = 'all_time';
            document.querySelectorAll('.pl-custom-dates').forEach(function (el) { el.style.display = 'none'; });
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            ['filterBank', 'filterProduct', 'filterBranch', 'filterUser', 'filterStage', 'filterStuck'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.value = ''; }
            });
            refresh();
        });

        syncActiveOnlyFilters();
        refresh();
    });
})(window.jQuery);
