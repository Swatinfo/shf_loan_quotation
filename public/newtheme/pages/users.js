/*
 * Newtheme Users index — fetches users.data (DataTables-shaped) and
 * renders into newtheme `.tbl` + mobile cards. Wires toggle-active +
 * delete-user buttons to the same endpoints the legacy page uses,
 * with SweetAlert2 confirmations.
 */
(function () {
    'use strict';

    var URLS = window.__UX || {};
    var state = { start: 0, length: 25, draw: 0 };

    var rowsEl = document.getElementById('uxRows');
    var mobileEl = document.getElementById('uxMobileRows');
    var pagerEl = document.getElementById('uxPager');
    var statsEl = document.getElementById('uxStatsLine');
    var resultEl = document.getElementById('uxResultCount');
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function readFilters() {
        var get = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
        return {
            search: get('uxSearch'),
            role: get('uxRole'),
            status: get('uxStatus') || 'active',
        };
    }

    function buildQuery() {
        state.draw++;
        var f = readFilters();
        var params = new URLSearchParams();
        params.set('draw', state.draw);
        params.set('start', state.start);
        params.set('length', state.length);
        params.set('order[0][column]', '6');
        params.set('order[0][dir]', 'desc');
        if (f.search) { params.set('search[value]', f.search); }
        if (f.role) { params.set('role', f.role); }
        if (f.status) { params.set('status', f.status); }
        return params.toString();
    }

    function renderRows(data) {
        if (!data.length) {
            rowsEl.innerHTML = '<div class="ux-loader" style="padding:36px 24px;"><div style="font-size:30px;margin-bottom:6px;">👤</div><div style="font-weight:600;color:var(--ink-3);">No users match the filters</div></div>';
            mobileEl.innerHTML = '';
            return;
        }

        var head = '<table class="tbl"><thead><tr>' +
            '<th>Name</th>' +
            '<th>Email</th>' +
            '<th>Roles / Bank</th>' +
            '<th>Branch</th>' +
            '<th>Status</th>' +
            '<th>Created</th>' +
            '<th class="col-actions"></th>' +
            '</tr></thead><tbody>';

        var body = data.map(function (u) {
            return '<tr>' +
                '<td data-label="Name">' + (u.name_html || '') + '</td>' +
                '<td data-label="Email">' + (u.email || '') + '</td>' +
                '<td data-label="Roles">' + (u.role_html || '') + '</td>' +
                '<td data-label="Branch">' + (u.branch_html || '') + '</td>' +
                '<td data-label="Status">' + (u.status_html || '') + '</td>' +
                '<td data-label="Created">' + (u.created_html || '') + '</td>' +
                '<td class="col-actions" data-label="Actions">' + (u.actions_html || '') + '</td>' +
                '</tr>';
        }).join('');

        rowsEl.innerHTML = head + body + '</tbody></table>';

        mobileEl.innerHTML = data.map(function (u) {
            return '<div class="ux-m-card">' +
                '<div class="m-hd">' +
                    '<div style="min-width:0;flex:1;">' + (u.name_html || '') + '</div>' +
                    '<div class="flex-shrink-0">' + (u.status_html || '') + '</div>' +
                '</div>' +
                '<div class="m-row"><span class="k">Email</span><span class="v">' + (u.email || '—') + '</span></div>' +
                '<div class="m-row"><span class="k">Roles</span><span class="v">' + (u.role_html || '—') + '</span></div>' +
                '<div class="m-row"><span class="k">Branch</span><span class="v">' + (u.branch_html || '—') + '</span></div>' +
                '<div class="m-row"><span class="k">Created</span><span class="v">' + (u.created_html || '—') + '</span></div>' +
                '<div class="ux-m-actions">' + (u.actions_html || '') + '</div>' +
            '</div>';
        }).join('');
    }

    function renderPager(total, filtered) {
        var pages = Math.max(1, Math.ceil(filtered / state.length));
        var current = Math.floor(state.start / state.length) + 1;

        var html = '<div>Showing ' + (filtered === 0 ? 0 : (state.start + 1)) + '–' +
            Math.min(state.start + state.length, filtered) + ' of ' + filtered.toLocaleString('en-IN') +
            (filtered !== total ? ' (filtered from ' + total.toLocaleString('en-IN') + ')' : '') + '</div>';

        html += '<div class="ux-pages">';
        html += '<button type="button" class="ux-pg-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
        var maxBtns = 5;
        var startP = Math.max(1, current - 2), endP = Math.min(pages, startP + maxBtns - 1);
        startP = Math.max(1, endP - maxBtns + 1);
        for (var p = startP; p <= endP; p++) {
            html += '<button type="button" class="ux-pg-btn ' + (p === current ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="ux-pg-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>›</button>';
        html += '</div>';

        pagerEl.innerHTML = html;
        pagerEl.querySelectorAll('.ux-pg-btn').forEach(function (b) {
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
        rowsEl.innerHTML = '<div class="ux-loader">Loading…</div>';
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
                rowsEl.innerHTML = '<div class="ux-loader" style="color:var(--red);">Failed to load. Please try again.</div>';
            });
    }

    /* Wire filters */
    var debounce = null;
    var searchEl = document.getElementById('uxSearch');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { state.start = 0; load(); }, 300);
        });
    }
    ['uxRole', 'uxStatus'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.addEventListener('change', function () { state.start = 0; load(); }); }
    });
    var perPage = document.getElementById('uxPerPage');
    if (perPage) {
        perPage.addEventListener('change', function () {
            state.length = parseInt(this.value, 10);
            state.start = 0;
            load();
        });
    }
    var clearBtn = document.getElementById('uxClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (searchEl) { searchEl.value = ''; }
            var r = document.getElementById('uxRole'); if (r) { r.selectedIndex = 0; }
            var s = document.getElementById('uxStatus'); if (s) { s.value = 'active'; }
            state.start = 0;
            load();
        });
    }

    /* Active filter count */
    var activeCountEl = document.getElementById('uxActiveFilterCount');
    function refreshActiveFilterCount() {
        if (!activeCountEl) { return; }
        var f = readFilters();
        var defaults = { search: '', role: '', status: 'active' };
        var active = 0;
        Object.keys(defaults).forEach(function (k) {
            if ((f[k] || '') !== defaults[k]) { active++; }
        });
        activeCountEl.textContent = active;
        activeCountEl.classList.toggle('has-active', active > 0);
    }

    /* Toggle active / Delete — delegated from the rendered action strip.
       Uses SweetAlert2 (already loaded by the layout) + fetch with CSRF. */
    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: body,
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); });
    }

    document.addEventListener('click', function (ev) {
        var toggleBtn = ev.target.closest ? ev.target.closest('.btn-toggle-active') : null;
        if (toggleBtn && rowsEl.contains(toggleBtn)) {
            ev.preventDefault();
            var url = toggleBtn.dataset.url;
            window.Swal && Swal.fire({
                title: 'Confirm change?',
                text: 'Toggle this user\'s active status?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f15a29',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes',
            }).then(function (result) {
                if (!result.isConfirmed) { return; }
                postJson(url, '_token=' + encodeURIComponent(csrfToken)).then(function () { load(); });
            });
            return;
        }

        var deleteBtn = ev.target.closest ? ev.target.closest('.btn-delete-user') : null;
        if (deleteBtn && rowsEl.contains(deleteBtn)) {
            ev.preventDefault();
            var url = deleteBtn.dataset.url;
            window.Swal && Swal.fire({
                title: 'Delete user?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Delete',
            }).then(function (result) {
                if (!result.isConfirmed) { return; }
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: '_token=' + encodeURIComponent(csrfToken) + '&_method=DELETE',
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                  .then(function (resp) {
                      if (resp.ok) {
                          window.Swal && Swal.fire({ icon: 'success', title: (resp.json && resp.json.message) || 'User deleted', timer: 1500, showConfirmButton: false });
                          load();
                      } else {
                          window.Swal && Swal.fire('Error', (resp.json && resp.json.message) || 'Failed to delete user.', 'error');
                      }
                  });
            });
            return;
        }
    });

    load();
})();
