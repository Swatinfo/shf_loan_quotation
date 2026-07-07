/* Per-page JS for activity-log.html
   Page-specific behavior layer. Global behavior lives in shf-interactive.js.
   Add form wiring, validation, chart init, etc. here. */
(function () {
  'use strict';

  function init() {
    // activity-log-specific initialization
    document.body.setAttribute('data-page', 'activity-log');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
