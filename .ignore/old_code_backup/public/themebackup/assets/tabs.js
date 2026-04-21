// Tab wiring helper — ONE unique prefix per page.
// Usage in a per-page script:
//   wireTabs('loan', ['workflow','documents','rate','queries','timeline','notes']);
// This expects:
//   <a id="loan-tab-workflow" ...>
//   <div id="loan-panel-workflow" ...>
//
// First key with .active on its tab, or first key overall, is shown by default.

(function () {
  'use strict';

  window.wireTabs = function (prefix, keys, opts) {
    opts = opts || {};
    const tabIds = keys.map(k => `${prefix}-tab-${k}`);
    const panelIds = keys.map(k => `${prefix}-panel-${k}`);

    function activate(key) {
      keys.forEach((k, i) => {
        const t = document.getElementById(tabIds[i]);
        if (t) t.classList.toggle('active', k === key);
        const p = document.getElementById(panelIds[i]);
        if (p) p.style.display = k === key ? '' : 'none';
      });
      if (opts.onChange) opts.onChange(key);
    }

    function init() {
      keys.forEach((k, i) => {
        const t = document.getElementById(tabIds[i]);
        if (!t) return;
        t.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          activate(k);
        });
      });
      // Initial
      let initialKey = keys[0];
      keys.forEach((k, i) => {
        const t = document.getElementById(tabIds[i]);
        if (t && t.classList.contains('active')) initialKey = k;
      });
      activate(initialKey);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  };
})();
