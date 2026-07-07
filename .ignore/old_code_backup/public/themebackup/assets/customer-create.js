/* Per-page JS for customer-create.html
   Page-specific behavior layer. Global behavior lives in shf-interactive.js.
   Add form wiring, validation, chart init, etc. here. */
(function () {
  'use strict';

  function init() {
    // customer-create-specific initialization
    document.body.setAttribute('data-page', 'customer-create');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
