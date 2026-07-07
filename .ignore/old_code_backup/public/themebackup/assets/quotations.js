/* Per-page JS for quotations.html
   Page-specific behavior layer. Global behavior lives in shf-interactive.js.
   Add form wiring, validation, chart init, etc. here. */
(function () {
  'use strict';

  function init() {
    // quotations-specific initialization
    document.body.setAttribute('data-page', 'quotations');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
