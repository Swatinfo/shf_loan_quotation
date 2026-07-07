/* Per-page JS for reset-password.html
   Page-specific behavior layer. Global behavior lives in shf-interactive.js.
   Add form wiring, validation, chart init, etc. here. */
(function () {
  'use strict';

  function init() {
    // reset-password-specific initialization
    document.body.setAttribute('data-page', 'reset-password');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
