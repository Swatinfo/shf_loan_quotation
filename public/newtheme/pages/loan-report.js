/*
 * Newtheme Loan Report (sanctioned / disbursed)
 *  - Plain fetch against /reports/loans/data with an inline loader
 *    (list-page convention — NOT the global SHF.loader overlay).
 *  - Period preset -> date range mapping mirrors turnaround.js.
 */
(function ($) {
    'use strict';

    var URLS = window.__LR || {};

    var elRows = document.getElementById('lrRows');
    var elRowCount = document.getElementById('lrRowCount');
    var elModeLabel = document.getElementById('lrModeLabel');
    var elTotalCount = document.getElementById('lrTotalCount');
    var elTotalSanctioned = document.getElementById('lrTotalSanctioned');
    var elTotalDisbursed = document.getElementById('lrTotalDisbursed');

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
                var lqStart = m - (m % 3) - 3;
                var qy = y;
                if (lqStart < 0) { lqStart += 12; qy -= 1; }
                return { from: fmt(new Date(qy, lqStart, 1)), to: fmt(new Date(qy, lqStart + 3, 0)) };
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
            status: document.getElementById('filterStatus').value || 'sanctioned',
            date_from: ddmmyyyyToISO(dates.from),
            date_to: ddmmyyyyToISO(dates.to),
            bank_id: document.getElementById('filterBank').value || '',
            product_id: document.getElementById('filterProduct').value || '',
            branch_id: (document.getElementById('filterBranch') || {}).value || '',
            user_id: (document.getElementById('filterUser') || {}).value || '',
        };
    }

    function buildQuery(params) {
        var q = new URLSearchParams();
        Object.keys(params).forEach(function (k) {
            if (params[k] !== undefined && params[k] !== null) { q.set(k, params[k]); }
        });
        return q.toString();
    }

    function emptyStateHtml(msg) {
        return '<div class="lr-empty">' +
            '<svg class="lr-empty-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>' +
            '<div class="lr-empty-t">' + escapeHtml(msg) + '</div>' +
            '<div class="lr-empty-s">Try a wider period or fewer filters.</div>' +
            '</div>';
    }

    function render(rows) {
        if (elRowCount) {
            elRowCount.textContent = rows.length + (rows.length === 1 ? ' row' : ' rows');
        }
        if (!rows.length) {
            elRows.innerHTML = emptyStateHtml('No loans for the selected filters.');
            return;
        }
        var head = '<table class="tbl"><thead><tr>' +
            '<th>Loan #</th>' +
            '<th>Customer</th>' +
            '<th>Bank / Product</th>' +
            '<th>Branch</th>' +
            '<th>Advisor</th>' +
            '<th class="num">Loan Amount</th>' +
            '<th class="num">Sanctioned</th>' +
            '<th class="num">Disbursed</th>' +
            '<th>Sanctioned On</th>' +
            '<th>Disbursed On</th>' +
            '<th>Status</th>' +
            '</tr></thead><tbody>';
        var body = rows.map(function (r) {
            var loanCell = r.stages_url
                ? '<a href="' + escapeHtml(r.stages_url) + '">' + escapeHtml(r.loan_number || '—') + '</a>'
                : escapeHtml(r.loan_number || '—');
            return '<tr>' +
                '<td class="lr-loan">' + loanCell + '</td>' +
                '<td>' + escapeHtml(r.customer_name || '—') + '</td>' +
                '<td class="lr-muted">' + escapeHtml(r.bank_product || '—') + '</td>' +
                '<td class="lr-muted">' + escapeHtml(r.branch_name || '—') + '</td>' +
                '<td>' + escapeHtml(r.advisor_name || '—') + '</td>' +
                '<td class="num">' + escapeHtml(r.loan_amount || '—') + '</td>' +
                '<td class="num">' + escapeHtml(r.sanctioned_amount || '—') + '</td>' +
                '<td class="num">' + escapeHtml(r.disbursed_amount || '—') + '</td>' +
                '<td>' + escapeHtml(r.sanctioned_on || '—') + '</td>' +
                '<td>' + escapeHtml(r.disbursed_on || '—') + '</td>' +
                '<td><span class="lr-status ' + escapeHtml(r.status || '') + '">' + escapeHtml((r.status || '—').replace('_', ' ')) + '</span></td>' +
                '</tr>';
        }).join('');
        elRows.innerHTML = head + body + '</tbody></table>';
    }

    function renderTotals(totals) {
        if (!totals) { return; }
        if (elTotalCount) { elTotalCount.textContent = totals.count != null ? String(totals.count) : '—'; }
        if (elTotalSanctioned) { elTotalSanctioned.textContent = totals.sanctioned || '—'; }
        if (elTotalDisbursed) { elTotalDisbursed.textContent = totals.disbursed || '—'; }
        // Period totals cover BOTH milestones (management-funnel semantics) —
        // surface each milestone's loan count in its card label.
        var sLbl = document.getElementById('lrTotalSanctionedLbl');
        var dLbl = document.getElementById('lrTotalDisbursedLbl');
        if (sLbl) { sLbl.textContent = 'Total Sanctioned' + (totals.sanctioned_count != null ? ' (' + totals.sanctioned_count + ' loans)' : ''); }
        if (dLbl) { dLbl.textContent = 'Total Disbursed' + (totals.disbursed_count != null ? ' (' + totals.disbursed_count + ' loans)' : ''); }
    }

    function refresh() {
        elRows.innerHTML = '<div class="lr-loader">Loading…</div>';
        var status = document.getElementById('filterStatus').value;
        if (elModeLabel) { elModeLabel.textContent = status; }
        // The period filter runs on the milestone completion date server-side.
        var periodHint = document.getElementById('lrPeriodHint');
        if (periodHint) { periodHint.textContent = status === 'disbursed' ? '(disbursement date)' : '(sanction date)'; }
        fetch(URLS.dataUrl + '?' + buildQuery(getFilters()), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (resp) {
            render(resp.data || []);
            renderTotals(resp.totals);
        }).catch(function () {
            elRows.innerHTML = '<div class="lr-loader" style="color:var(--red);">Failed to load. Try again.</div>';
        });
    }

    /* ==================== Init ==================== */
    document.addEventListener('DOMContentLoaded', function () {
        var init = getPeriodDates('current_month');
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

        var periodSel = document.getElementById('filterPeriod');
        periodSel.addEventListener('change', function () {
            var isCustom = this.value === 'custom';
            document.querySelectorAll('.lr-custom-dates').forEach(function (el) {
                el.style.display = isCustom ? '' : 'none';
            });
            if (!isCustom) {
                var d = getPeriodDates(this.value);
                document.getElementById('filterDateFrom').value = d.from;
                document.getElementById('filterDateTo').value = d.to;
                refresh();
            }
        });

        ['filterStatus', 'filterBank', 'filterProduct', 'filterBranch', 'filterUser'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.tagName === 'SELECT') {
                el.addEventListener('change', refresh);
            }
        });

        document.getElementById('lrApply').addEventListener('click', refresh);
        var exportBtn = document.getElementById('lrExport');
        if (exportBtn) {
            // Server re-applies the same filters/scope and exports ALL
            // matching rows (never just the rendered page).
            exportBtn.addEventListener('click', function () {
                window.location = URLS.exportUrl + '?' + buildQuery(getFilters());
            });
        }
        document.getElementById('lrClear').addEventListener('click', function () {
            document.getElementById('filterStatus').value = 'sanctioned';
            document.getElementById('filterPeriod').value = 'current_month';
            document.querySelectorAll('.lr-custom-dates').forEach(function (el) { el.style.display = 'none'; });
            var d = getPeriodDates('current_month');
            document.getElementById('filterDateFrom').value = d.from;
            document.getElementById('filterDateTo').value = d.to;
            ['filterBank', 'filterProduct', 'filterBranch', 'filterUser'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.value = ''; }
            });
            refresh();
        });

        refresh();
    });
})(window.jQuery);
