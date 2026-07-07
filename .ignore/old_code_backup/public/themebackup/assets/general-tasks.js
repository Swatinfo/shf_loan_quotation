/* Per-page JS for general-tasks.html
   Page-specific behavior layer. Global behavior lives in shf-interactive.js.
   Add form wiring, validation, chart init, etc. here. */
(function () {
  'use strict';

  function init() {
    // general-tasks-specific initialization
    document.body.setAttribute('data-page', 'general-tasks');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
