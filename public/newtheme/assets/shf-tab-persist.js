/*
 * Shared tab-persistence layer for newtheme pages.
 *
 * Remembers the last-selected tab per page (keyed by group name + pathname)
 * and restores it on reload by simulating a click on the stored tab.
 *
 * Works with any markup that uses:
 *     <div class="tabs" data-tab-panel-group="{group}">
 *        <a class="tab" data-panel="{panelId}">…</a>
 *        …
 *     </div>
 *
 * No coupling to wireTabs() or the dashboard's custom activatePanel — we just
 * fire the same click event the user would have, so whatever handler the page
 * installed does its own thing (show panel, lazy-load data, etc.).
 *
 * Runs LAST, after all page scripts + DOMContentLoaded handlers, so page code
 * has already wired its listeners by the time we restore.
 */
(function () {
    'use strict';

    function storageKey(group) {
        return 'shf-tab:' + group + ':' + (location.pathname || '/');
    }

    /* Save on click — delegated so dynamically rendered tabs also stick. */
    document.addEventListener('click', function (ev) {
        var tab = ev.target.closest ? ev.target.closest('.tabs .tab') : null;
        if (!tab) { return; }
        var group = tab.closest('[data-tab-panel-group]');
        if (!group) { return; }
        var panel = tab.dataset.panel || tab.getAttribute('data-panel');
        var groupName = group.dataset.tabPanelGroup || group.getAttribute('data-tab-panel-group');
        if (!panel || !groupName) { return; }
        try { localStorage.setItem(storageKey(groupName), panel); } catch (e) { /* ignore quota errors */ }
    }, false);

    /* Restore after page scripts wire their listeners. A macrotask delay
       (setTimeout 0) is enough — DOMContentLoaded listeners run synchronously
       during parse, and our script loads at end-of-body. */
    function restore() {
        document.querySelectorAll('[data-tab-panel-group]').forEach(function (group) {
            var groupName = group.dataset.tabPanelGroup || group.getAttribute('data-tab-panel-group');
            if (!groupName) { return; }
            var stored = null;
            try { stored = localStorage.getItem(storageKey(groupName)); } catch (e) { return; }
            if (!stored) { return; }
            // Find the tab inside this SAME group (not all tabs on the page — many
            // pages share the same group name on both the tab-bar and its sibling
            // panels container, so we scope to the first group that carries .tab).
            var tab = document.querySelector('.tabs[data-tab-panel-group="' + groupName + '"] .tab[data-panel="' + stored + '"]');
            if (!tab) { return; }
            // Skip if already active — avoids re-firing lazy-loaders unnecessarily.
            if (tab.classList.contains('active')) { return; }
            tab.click();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(restore, 0); });
    } else {
        setTimeout(restore, 0);
    }
})();
