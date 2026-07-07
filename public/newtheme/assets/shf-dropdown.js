/*
 * SHFDropdown — themed dropdown component shared across newtheme pages.
 *
 * Markup contract:
 *
 *   <div class="shf-dd-wrap" id="myDD">
 *     <button type="button" class="btn shf-dd-trigger">
 *       <span class="shf-dd-value">Loading…</span>
 *       <svg ... chevron .../>
 *     </button>
 *     <ul class="shf-dd-menu">
 *       <li data-key="a">Option A</li>
 *       <li data-key="b" class="active">Option B</li>
 *     </ul>
 *   </div>
 *
 *  - All `.shf-dd-wrap` elements on the page get auto-initialised on
 *    DOMContentLoaded. To init one programmatically: `SHFDropdown.init(el)`.
 *  - The menu is portaled to `<body>` and positioned with `position:fixed` so
 *    it can escape any ancestor `overflow:hidden` (every newtheme `.card` has
 *    that, which would otherwise clip the dropdown).
 *  - Position picks "drop down" or "drop up" depending on available space and
 *    re-runs on resize/scroll, so it adapts to every viewport.
 *
 * Selection API:
 *   wrap.shfDD.setValue('key', { silent: false })
 *   wrap.shfDD.getValue()
 *   wrap.addEventListener('shf-dd-change', function (e) { e.detail.key, ... })
 *
 * Multiple dropdowns on the same page are isolated — each wrap owns its own
 * menu and only one menu is open at a time (opening one closes the others).
 */
(function () {
    'use strict';

    var openWrap = null; // currently-open dropdown (only one at a time)

    function init(wrap) {
        if (!wrap || wrap.dataset.shfDdReady) { return; }
        wrap.dataset.shfDdReady = '1';

        var trigger = wrap.querySelector('.shf-dd-trigger');
        var valueEl = wrap.querySelector('.shf-dd-value');
        var menu    = wrap.querySelector('.shf-dd-menu');
        if (!trigger || !valueEl || !menu) { return; }

        // Portal the menu so a parent .card { overflow: hidden } can't clip it.
        document.body.appendChild(menu);

        function positionMenu() {
            var r = trigger.getBoundingClientRect();
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var minW = Math.max(r.width, 180);
            menu.style.minWidth = minW + 'px';

            // Measure menu height (visible while we measure, since open() set it).
            var menuH = menu.offsetHeight || 200;
            var below = vh - r.bottom;
            var above = r.top;

            // Drop down by default. If not enough room and there's more above, drop up.
            if (below < menuH + 12 && above > below) {
                menu.style.top = Math.max(8, r.top - menuH - 6) + 'px';
            } else {
                menu.style.top = (r.bottom + 6) + 'px';
            }

            // Horizontal: prefer aligning to trigger's left edge, but right-align
            // if the menu would overflow past the viewport's right edge. Final
            // clamp keeps it within the viewport on phones.
            var idealLeft = r.left;
            if (idealLeft + minW > vw - 8) {
                idealLeft = r.right - minW;
            }
            var maxLeft = vw - minW - 8;
            menu.style.left = Math.max(8, Math.min(idealLeft, maxLeft)) + 'px';
        }

        function open() {
            if (openWrap && openWrap !== wrap && openWrap.shfDD) {
                openWrap.shfDD.close();
            }
            openWrap = wrap;
            wrap.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.style.display = 'block';
            positionMenu();
        }
        function close() {
            wrap.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
            menu.style.display = 'none';
            if (openWrap === wrap) { openWrap = null; }
        }
        function isOpen() { return wrap.classList.contains('open'); }
        function toggle() { isOpen() ? close() : open(); }

        function getValue() {
            var active = menu.querySelector('li.active');
            return active ? active.dataset.key : null;
        }
        function setValue(key, opts) {
            opts = opts || {};
            var li = menu.querySelector('li[data-key="' + key + '"]');
            if (!li) { return; }
            Array.prototype.forEach.call(menu.querySelectorAll('li'), function (other) {
                other.classList.toggle('active', other === li);
            });
            valueEl.textContent = li.textContent.trim();
            if (!opts.silent) {
                wrap.dispatchEvent(new CustomEvent('shf-dd-change', {
                    detail: { key: key, label: valueEl.textContent },
                    bubbles: true,
                }));
            }
        }
        // Reflect any pre-set `.active` row into the trigger label on init.
        var initialActive = menu.querySelector('li.active');
        if (initialActive) { valueEl.textContent = initialActive.textContent.trim(); }

        // Wire interactions
        trigger.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
        menu.addEventListener('click', function (e) {
            var li = e.target.closest('li');
            if (!li || !li.dataset.key) { return; }
            setValue(li.dataset.key);
            close();
        });

        // Expose API on the wrap (so callers can drive it programmatically).
        wrap.shfDD = { open: open, close: close, toggle: toggle, getValue: getValue, setValue: setValue, position: positionMenu };
    }

    function initAll(root) {
        var scope = root || document;
        scope.querySelectorAll('.shf-dd-wrap').forEach(init);
    }

    // Global single-listener guards (registered once, drive every dropdown).
    document.addEventListener('click', function (e) {
        if (!openWrap) { return; }
        var insideMenu    = openWrap.shfDD && openWrap.querySelector('.shf-dd-trigger') && (
            openWrap.contains(e.target)                                 // trigger lives in wrap
            || (function () {                                            // portaled menu
                var m = document.body.querySelectorAll('.shf-dd-menu');
                for (var i = 0; i < m.length; i++) {
                    if (m[i].contains(e.target)) { return true; }
                }
                return false;
            })()
        );
        if (!insideMenu) { openWrap.shfDD.close(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openWrap && openWrap.shfDD) { openWrap.shfDD.close(); }
    });
    window.addEventListener('resize',  function () { if (openWrap && openWrap.shfDD) { openWrap.shfDD.position(); } });
    window.addEventListener('scroll',  function () { if (openWrap && openWrap.shfDD) { openWrap.shfDD.position(); } }, true);

    // Auto-init on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }

    window.SHFDropdown = { init: init, initAll: initAll };
})();
