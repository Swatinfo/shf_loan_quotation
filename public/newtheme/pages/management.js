/*
 * Newtheme Management Summary report
 *  - One fetch returns funnel + trend + scoreboard + exceptions.
 *  - Branch rows expand to advisor rows; trend uses CSS mini-bars.
 *  - Plain fetch + inline loaders (list-page convention, no global overlay).
 */
(function ($) {
    'use strict';

    var URLS = window.__MG || {};

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
            date_from: ddmmyyyyToISO(dates.from),
            date_to: ddmmyyyyToISO(dates.to),
            branch_id: document.getElementById('filterBranch').value || '',
        };
    }

    function buildQuery(params) {
        var q = new URLSearchParams();
        Object.keys(params).forEach(function (k) {
            if (params[k] !== undefined && params[k] !== null) { q.set(k, params[k]); }
        });
        return q.toString();
    }

    function emptyHtml(msg) { return '<div class="mg-empty">' + escapeHtml(msg) + '</div>'; }

    /* ---------- Funnel ---------- */
    function renderFunnel(f) {
        var el = document.getElementById('mgFunnel');
        if (!f) { el.innerHTML = emptyHtml('No data.'); return; }
        function step(label, s, showPct) {
            return '<div class="mg-step">' +
                '<div class="mg-step-lbl">' + label + '</div>' +
                '<div class="mg-step-count">' + (s.count || 0) + '</div>' +
                '<div class="mg-step-amount">' + escapeHtml(s.amount || '—') + '</div>' +
                '<div class="mg-step-meta">' +
                (showPct && s.pct != null ? '<span class="mg-pill">' + s.pct + '% of previous</span>' : '') +
                (s.avg_days != null ? '<span class="mg-pill days">avg ' + s.avg_days + 'd</span>' : '') +
                '</div>' +
                '</div>';
        }
        el.innerHTML =
            step('Quotations', f.quotations, false) +
            step('Converted to Loans', f.converted, true) +
            step('Sanctioned', f.sanctioned, true) +
            step('Disbursed', f.disbursed, true);
    }

    /* ---------- Trend ---------- */
    function renderTrend(months) {
        var el = document.getElementById('mgTrend');
        if (!months || !months.length) { el.innerHTML = emptyHtml('No data.'); return; }
        var maxAmt = 1;
        months.forEach(function (m) {
            ['created', 'sanctioned', 'disbursed'].forEach(function (k) {
                if (m[k].amount_raw > maxAmt) { maxAmt = m[k].amount_raw; }
            });
        });
        function cell(s, barClass) {
            var w = Math.round((s.amount_raw / maxAmt) * 100);
            return '<td class="num">' + s.count + ' · ' + escapeHtml(s.amount) +
                '<span class="mg-bar-wrap"><span class="mg-bar ' + barClass + '" style="width:' + w + '%"></span></span></td>';
        }
        var head = '<table class="tbl"><thead><tr>' +
            '<th>Month</th><th class="num">Created</th><th class="num">Sanctioned</th><th class="num">Disbursed</th>' +
            '</tr></thead><tbody>';
        var body = months.map(function (m) {
            return '<tr><td>' + escapeHtml(m.month) + '</td>' +
                cell(m.created, '') + cell(m.sanctioned, 'blue') + cell(m.disbursed, 'green') + '</tr>';
        }).join('');
        el.innerHTML = head + body + '</tbody></table>';
    }

    /* ---------- Scoreboard ---------- */
    function scoreCells(s) {
        return '<td class="num">' + s.created + '</td>' +
            '<td class="num">' + s.active + '</td>' +
            '<td class="num">' + s.completed + '</td>' +
            '<td class="num ' + (s.rejection_pct > 20 ? 'mg-pct-bad' : '') + '">' + s.rejection_pct + '%</td>' +
            '<td class="num">' + (s.avg_tat_days != null ? s.avg_tat_days + 'd' : '—') + '</td>' +
            '<td class="num">' + escapeHtml(s.disbursed) + '</td>' +
            '<td class="num">' + (s.stuck ? '<span class="mg-days">' + s.stuck + '</span>' : '0') + '</td>';
    }

    function renderScore(rows) {
        var el = document.getElementById('mgScore');
        if (!rows || !rows.length) { el.innerHTML = emptyHtml('No loans in the selected period.'); return; }
        var head = '<table class="tbl"><thead><tr>' +
            '<th>Branch / Advisor</th><th class="num">Created</th><th class="num">Active</th>' +
            '<th class="num">Completed</th><th class="num">Rejection %</th><th class="num">Avg TAT</th>' +
            '<th class="num">Disbursed ₹</th><th class="num">Stuck &gt; 14d</th>' +
            '</tr></thead><tbody>';
        var body = rows.map(function (b, i) {
            var branchRow = '<tr class="mg-branch-row" data-branch="' + i + '">' +
                '<td><span class="mg-caret">▸</span>' + escapeHtml(b.branch_name) + '</td>' + scoreCells(b) + '</tr>';
            var advRows = (b.advisors || []).map(function (a) {
                return '<tr class="mg-adv-row" data-branch-child="' + i + '" style="display:none;">' +
                    '<td>' + escapeHtml(a.advisor_name) + '</td>' + scoreCells(a) + '</tr>';
            }).join('');
            return branchRow + advRows;
        }).join('');
        el.innerHTML = head + body + '</tbody></table>';

        el.querySelectorAll('.mg-branch-row').forEach(function (row) {
            row.addEventListener('click', function () {
                var idx = row.dataset.branch;
                var open = row.classList.toggle('is-open');
                el.querySelectorAll('[data-branch-child="' + idx + '"]').forEach(function (child) {
                    child.style.display = open ? '' : 'none';
                });
            });
        });
    }

    /* ---------- Exceptions ---------- */
    function loanLink(r) {
        return '<td class="mg-ex-loan"><a href="' + escapeHtml(r.stages_url) + '">' + escapeHtml(r.loan_number) + '</a></td>';
    }

    function renderExceptions(ex) {
        var stagesEl = document.getElementById('mgExStages');
        var queriesEl = document.getElementById('mgExQueries');
        var holdsEl = document.getElementById('mgExHolds');
        if (!ex) {
            stagesEl.innerHTML = queriesEl.innerHTML = holdsEl.innerHTML = emptyHtml('No data.');
            return;
        }

        stagesEl.innerHTML = ex.stale_stages.length
            ? '<table class="tbl"><thead><tr><th>Loan</th><th>Stage</th><th>Owner</th><th class="num">Days</th></tr></thead><tbody>' +
              ex.stale_stages.map(function (r) {
                  return '<tr>' + loanLink(r) + '<td>' + escapeHtml(r.stage) + '</td><td>' + escapeHtml(r.owner) +
                      '</td><td class="num"><span class="mg-days">' + r.days + 'd</span></td></tr>';
              }).join('') + '</tbody></table>'
            : emptyHtml('Nothing stuck. 🎉');

        queriesEl.innerHTML = ex.stale_queries.length
            ? '<table class="tbl"><thead><tr><th>Loan</th><th>Stage</th><th>Raised By</th><th class="num">Days</th></tr></thead><tbody>' +
              ex.stale_queries.map(function (r) {
                  return '<tr>' + loanLink(r) + '<td>' + escapeHtml(r.stage) + '</td><td>' + escapeHtml(r.raised_by) +
                      '</td><td class="num"><span class="mg-days">' + r.days + 'd</span></td></tr>';
              }).join('') + '</tbody></table>'
            : emptyHtml('No stale queries.');

        holdsEl.innerHTML = ex.stale_holds.length
            ? '<table class="tbl"><thead><tr><th>Loan</th><th>Reason</th><th class="num">Days</th></tr></thead><tbody>' +
              ex.stale_holds.map(function (r) {
                  return '<tr>' + loanLink(r) + '<td>' + escapeHtml(r.reason) +
                      '</td><td class="num"><span class="mg-days">' + r.days + 'd</span></td></tr>';
              }).join('') + '</tbody></table>'
            : emptyHtml('No long holds.');
    }

    function refresh() {
        ['mgFunnel', 'mgTrend', 'mgScore', 'mgExStages', 'mgExQueries', 'mgExHolds'].forEach(function (id) {
            document.getElementById(id).innerHTML = '<div class="mg-loader">Loading…</div>';
        });
        fetch(URLS.dataUrl + '?' + buildQuery(getFilters()), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { return r.json(); }).then(function (resp) {
            renderFunnel(resp.funnel);
            renderTrend(resp.trend || []);
            renderScore(resp.scoreboard || []);
            renderExceptions(resp.exceptions);
        }).catch(function () {
            document.getElementById('mgFunnel').innerHTML = '<div class="mg-loader" style="color:var(--red);">Failed to load. Try again.</div>';
        });
    }

    /* ==================== Init ==================== */
    document.addEventListener('DOMContentLoaded', function () {
        var init = getPeriodDates('current_year');
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
            document.querySelectorAll('.mg-custom-dates').forEach(function (el) {
                el.style.display = isCustom ? '' : 'none';
            });
            if (!isCustom) {
                var d = getPeriodDates(this.value);
                document.getElementById('filterDateFrom').value = d.from;
                document.getElementById('filterDateTo').value = d.to;
                refresh();
            }
        });

        document.getElementById('filterBranch').addEventListener('change', refresh);
        document.getElementById('mgApply').addEventListener('click', refresh);
        document.getElementById('mgClear').addEventListener('click', function () {
            document.getElementById('filterPeriod').value = 'current_year';
            document.querySelectorAll('.mg-custom-dates').forEach(function (el) { el.style.display = 'none'; });
            var d = getPeriodDates('current_year');
            document.getElementById('filterDateFrom').value = d.from;
            document.getElementById('filterDateTo').value = d.to;
            document.getElementById('filterBranch').value = '';
            refresh();
        });

        refresh();
    });
})(window.jQuery);
