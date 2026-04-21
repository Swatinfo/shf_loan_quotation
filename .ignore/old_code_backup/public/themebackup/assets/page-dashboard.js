// Dashboard — section-based tab filtering.
// "overview" shows ALL sections. Any other tab shows only cards whose
// data-section attribute contains that key (space-separated).

(function () {
  'use strict';

  const TAB_IDS = {
    'overview':  'dash-tab-overview',
    'my-queue':  'dash-tab-my-queue',
    'branch':    'dash-tab-branch',
    'pipeline':  'dash-tab-pipeline',
    'kpis':      'dash-tab-kpis',
  };

  function activate(key) {
    Object.entries(TAB_IDS).forEach(([k, id]) => {
      const t = document.getElementById(id);
      if (t) t.classList.toggle('active', k === key);
    });

    const sections = document.querySelectorAll('[data-section]');
    sections.forEach(sec => {
      const keys = sec.getAttribute('data-section').split(/\s+/).filter(Boolean);
      const show = key === 'overview' || keys.includes(key);
      sec.style.display = show ? '' : 'none';
    });
  }

  function init() {
    Object.entries(TAB_IDS).forEach(([key, id]) => {
      const t = document.getElementById(id);
      if (!t) return;
      t.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        activate(key);
      });
    });
    activate('overview');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
