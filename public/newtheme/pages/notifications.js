/*
 * Newtheme notifications page behaviour:
 *   - Mark single read (POST /notifications/{id}/read)
 *   - Mark all read (POST /notifications/read-all)
 *   - Push opt-in toggle via window.SHFPush
 *   - Sound preset / mute / unmute / test chime
 */
(function () {
    'use strict';

    var URLS = window.__NOTIF || {};
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function post(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
    }

    /* Mark single notification as read */
    document.querySelectorAll('.js-mark-read').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            btn.disabled = true;
            post(URLS.markReadUrlBase + '/' + encodeURIComponent(id) + '/read').then(function () {
                var card = btn.closest('.notif-item');
                if (card) { card.classList.add('is-read'); }
                btn.remove();
                bumpUnreadBadge(-1);
            }).catch(function () { btn.disabled = false; });
        });
    });

    /* Mark all read → reload to refresh server-rendered counts */
    var allBtn = document.getElementById('markAllReadBtn');
    if (allBtn) {
        allBtn.addEventListener('click', function () {
            allBtn.disabled = true;
            post(URLS.markAllReadUrl).then(function () { location.reload(); })
                .catch(function () { allBtn.disabled = false; });
        });
    }

    function bumpUnreadBadge(delta) {
        document.querySelectorAll('.js-notif-badge').forEach(function (b) {
            var n = parseInt(b.textContent || '0', 10);
            if (isNaN(n)) { n = 0; }
            n = Math.max(0, n + delta);
            if (n === 0) { b.classList.add('d-none'); b.textContent = ''; }
            else { b.classList.remove('d-none'); b.textContent = n > 99 ? '99+' : String(n); }
        });
        var subEl = document.querySelector('.page-header .sub strong');
        if (subEl) {
            var cur = parseInt(subEl.textContent || '0', 10);
            if (!isNaN(cur)) {
                cur = Math.max(0, cur + delta);
                subEl.textContent = cur;
                if (allBtn && cur === 0) { allBtn.disabled = true; }
            }
        }
    }

    /* ==================== Push toggle ==================== */
    if (window.SHFPush && window.SHFPush.supported()) {
        var prompt = document.getElementById('pushPrompt');
        var enable = document.getElementById('enablePushBtn');
        var disable = document.getElementById('disablePushBtn');
        var blocked = document.getElementById('pushPromptBlockedHelp');
        var subtitle = document.getElementById('pushPromptSubtitle');
        var titleEl = document.getElementById('pushPromptTitle');
        var defaultTitle = 'Enable desktop & mobile notifications';

        function refresh() {
            window.SHFPush.status().then(function (s) {
                prompt.style.display = '';
                if (s.permission === 'denied') {
                    titleEl.textContent = 'Notifications blocked — enable them in browser settings.';
                    enable.style.display = 'none';
                    disable.style.display = 'none';
                    subtitle.style.display = 'none';
                    blocked.style.display = 'block';
                    return;
                }
                titleEl.textContent = defaultTitle;
                subtitle.style.display = '';
                blocked.style.display = 'none';
                enable.style.display = s.subscribed ? 'none' : '';
                disable.style.display = s.subscribed ? '' : 'none';
            }).catch(function () {
                prompt.style.display = '';
                titleEl.textContent = defaultTitle;
                subtitle.style.display = '';
                blocked.style.display = 'none';
                enable.style.display = '';
                disable.style.display = 'none';
            });
        }

        enable.addEventListener('click', function () {
            enable.disabled = true; enable.textContent = 'Enabling…';
            window.SHFPush.enable().then(refresh).catch(function (err) {
                alert((err && err.message) || 'Failed to enable notifications.');
                refresh();
            }).finally(function () { enable.disabled = false; enable.textContent = 'Enable'; });
        });
        disable.addEventListener('click', function () {
            disable.disabled = true; disable.textContent = 'Disabling…';
            window.SHFPush.disable().then(refresh, refresh)
                .finally(function () { disable.disabled = false; disable.textContent = 'Disable'; });
        });
        refresh();
    }

    /* ==================== Sound mute/unmute + test ==================== */
    if (window.SHFPush && typeof window.SHFPush.playChime === 'function') {
        var mute = document.getElementById('muteBtn');
        var unmute = document.getElementById('unmuteBtn');
        var test = document.getElementById('testSoundBtn');

        function refreshSound() {
            var muted = window.SHFPush.isMuted();
            mute.style.display = muted ? 'none' : '';
            unmute.style.display = muted ? '' : 'none';
        }
        test.addEventListener('click', function () { window.SHFPush.playChime(); });
        mute.addEventListener('click', function () { window.SHFPush.mute(); refreshSound(); });
        unmute.addEventListener('click', function () { window.SHFPush.unmute(); refreshSound(); window.SHFPush.playChime(); });
        refreshSound();

    }

    /* ==================== Custom themed dropdown for chime preset ====================
       Markup is rendered by the page; we just inject the menu items, then let
       the shared SHFDropdown component handle open/close/positioning/clicks. */
    if (window.SHFPush && typeof window.SHFPush.getChimePresets === 'function') {
        var dd = document.getElementById('chimePresetDD');
        var menu = document.getElementById('chimePresetMenu');
        if (dd && menu) {
            var presets = window.SHFPush.getChimePresets();
            var current = window.SHFPush.getChimePreset();

            menu.innerHTML = presets.map(function (p) {
                var cls = p.key === current ? ' class="active"' : '';
                return '<li role="option" data-key="' + p.key + '"' + cls + '>' + p.label + '</li>';
            }).join('');

            // (Re-)initialise — handles the case where SHFDropdown.initAll()
            // already ran before the menu items existed.
            if (window.SHFDropdown && typeof window.SHFDropdown.init === 'function') {
                window.SHFDropdown.init(dd);
            }

            // Reflect the current preset on the trigger label.
            if (dd.shfDD) { dd.shfDD.setValue(current, { silent: true }); }

            // React to user selections
            dd.addEventListener('shf-dd-change', function (e) {
                window.SHFPush.setChimePreset(e.detail.key);
                if (!window.SHFPush.isMuted()) { window.SHFPush.playChime(); }
            });
        }
    }
})();
