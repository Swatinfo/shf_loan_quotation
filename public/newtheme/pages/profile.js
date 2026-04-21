/*
 * Newtheme profile page behaviour:
 *   - Password reveal toggle
 *   - Delete-account modal open/close + Esc-to-close
 *   - Auto-fade "Saved." messages
 */
(function () {
    'use strict';

    // Password reveal toggles
    document.querySelectorAll('.pwd-wrap .pwd-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) { return; }
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    // Delete-account modal
    var openBtn  = document.getElementById('openDeleteAcct');
    var closeBtn = document.getElementById('closeDelAcct');
    var backdrop = document.getElementById('delAcctBackdrop');
    var modal    = document.getElementById('delAcctModal');
    function openModal() {
        if (!backdrop || !modal) { return; }
        backdrop.style.display = 'block';
        modal.style.display = 'block';
        var firstInput = modal.querySelector('input');
        if (firstInput) { setTimeout(function () { firstInput.focus(); }, 30); }
    }
    function closeModal() {
        if (!backdrop || !modal) { return; }
        backdrop.style.display = 'none';
        modal.style.display = 'none';
    }
    if (openBtn)  { openBtn.addEventListener('click', openModal); }
    if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
    if (backdrop) { backdrop.addEventListener('click', closeModal); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'block') { closeModal(); }
    });

    // Fade "Saved." after 3 seconds
    document.querySelectorAll('.saved-msg').forEach(function (el) {
        setTimeout(function () { el.style.opacity = '0'; }, 3000);
        setTimeout(function () { el.remove(); }, 3500);
    });

    // If page loaded with #password hash, scroll to the password card.
    if (location.hash === '#password') {
        var card = document.getElementById('password');
        if (card) {
            setTimeout(function () { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80);
        }
    }
})();
