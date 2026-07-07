/*
 * Newtheme permissions matrix — resources/views/newtheme/permissions/index.blade.php
 *
 * - Live-filter rows by permission name / description / group.
 * - Per-role "All" / "None" bulk toggles that respect the current filter (so
 *   you can grant a group of permissions to a role in one click).
 */
(function () {
    'use strict';

    var searchEl = document.getElementById('pmSearch');
    var noResultsEl = document.getElementById('pmNoResults');
    var tableRows = Array.prototype.slice.call(document.querySelectorAll('.pm-row'));
    var groupRows = Array.prototype.slice.call(document.querySelectorAll('.pm-group-row'));

    /* ====================== Filter ====================== */
    function applyFilter() {
        var q = (searchEl && searchEl.value || '').trim().toLowerCase();
        var anyVisible = false;

        // Permission rows — show if search hits the concatenated search blob.
        tableRows.forEach(function (row) {
            var hay = row.getAttribute('data-search') || '';
            var show = !q || hay.indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) { anyVisible = true; }
        });

        // Group header — only show when at least one child row is visible.
        groupRows.forEach(function (groupRow) {
            var next = groupRow.nextElementSibling;
            var hasVisibleChild = false;
            while (next && !next.classList.contains('pm-group-row')) {
                if (next.classList.contains('pm-row') && next.style.display !== 'none') {
                    hasVisibleChild = true;
                    break;
                }
                next = next.nextElementSibling;
            }
            groupRow.style.display = hasVisibleChild ? '' : 'none';
        });

        if (noResultsEl) {
            noResultsEl.style.display = anyVisible ? 'none' : '';
        }
    }

    if (searchEl) {
        searchEl.addEventListener('input', applyFilter);
    }

    /* ====================== Bulk toggles ====================== */
    // Per-column "All" / "None" — only affects currently-visible (filtered) rows.
    document.querySelectorAll('.pm-bulk').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var role = btn.getAttribute('data-role');
            var setTo = btn.getAttribute('data-action') === 'all';
            tableRows.forEach(function (row) {
                if (row.style.display === 'none') { return; }
                var cb = row.querySelector('input[type="checkbox"][data-role="' + role + '"]');
                if (cb) { cb.checked = setTo; }
            });
        });
    });
})();
