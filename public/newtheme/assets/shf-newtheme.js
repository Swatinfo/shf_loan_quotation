/*
 * shf-newtheme.js — minimal jQuery helpers needed by newtheme pages.
 *
 * This is a focused subset of public/js/shf-app.js:
 *   - SHF.validateForm            (site-wide form validation)
 *   - SHF.validateBeforeAjax      (validate then POST)
 *   - SHF.formatIndianNumber      (error messages inside validateForm)
 *   - Auto-clear .is-invalid / .shf-validation-error on input/change
 *   - Auto-expand textareas (fallback for browsers lacking field-sizing: content)
 *   - Password reveal toggle (.shf-password-toggle)
 *   - Toast auto-dismiss + manual close (.shf-toast-wrapper)
 *   - "Saved." message fade (.shf-saved-msg)
 *   - SweetAlert confirm-delete (.shf-confirm-delete)
 *
 * Deliberately OMITTED vs shf-app.js:
 *   - Mobile FAB expand/collapse — the newtheme layout has its own FAB
 *     handler registered by menu-shell code; including shf-app.js's FAB
 *     block caused a double-bind that cancelled every click.
 *   - Filter auto-collapse on mobile, Bootstrap modal auto-show,
 *     radio-adjacent-checkbox auto-check, SHF.initAmountFields, etc.
 *     Each page-specific bit lives in its own page JS file.
 */
$(function () {
    'use strict';

    // 0. Disable HTML5 browser validation bubbles — we use SHF.validateForm instead
    $('form').attr('novalidate', true);

    window.SHF = window.SHF || {};

    // ─────────────────────────────────────────────────────────────
    // SHF.formatIndianNumber — declared first because validateForm
    // references it inside error messages.
    // ─────────────────────────────────────────────────────────────
    SHF.formatIndianNumber = function (num) {
        if (num == null || num === '') { return ''; }
        var n = parseFloat(num);
        if (isNaN(n)) { return ''; }
        var s = Math.round(n).toString();
        var lastThree = s.slice(-3);
        var otherNumbers = s.slice(0, -3);
        if (otherNumbers !== '') { lastThree = ',' + lastThree; }
        return otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + lastThree;
    };

    // ─────────────────────────────────────────────────────────────
    // Amount-in-words helpers — verbatim copies from shf-app.js so
    // pages that don't load shf-app.js (e.g. /loan-settings payout
    // inputs) can show bilingual amount words. Guarded so shf-app.js
    // stays authoritative on pages that load both.
    // ─────────────────────────────────────────────────────────────
    SHF.numberToWordsEn = SHF.numberToWordsEn || function (num) {
        if (num === 0) { return 'Zero'; }
        var ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        var tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        function tw(n) {
            if (n < 20) { return ones[n]; }
            return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '');
        }
        function th(n) {
            if (n >= 100) { return ones[Math.floor(n / 100)] + ' Hundred' + (n % 100 ? ' ' + tw(n % 100) : ''); }
            return tw(n);
        }
        // Recursive segment converter — mirrors NumberToWordsService::innerDigitsEn
        // so crore counts >= 100 don't index past the ones[] table (e.g. 2000 crore).
        function seg(n) {
            var r = '';
            if (n >= 10000000) { r += seg(Math.floor(n / 10000000)) + ' Crore '; n %= 10000000; }
            if (n >= 100000) { r += tw(Math.floor(n / 100000)) + ' Lakh '; n %= 100000; }
            if (n >= 1000) { r += tw(Math.floor(n / 1000)) + ' Thousand '; n %= 1000; }
            if (n > 0) { r += th(n); }
            return r.trim();
        }
        return seg(num) + ' Rupees';
    };

    SHF.numberToWordsGu = SHF.numberToWordsGu || function (num) {
        if (num === 0) { return 'શૂન્ય'; }
        var gu = ['', 'એક', 'બે', 'ત્રણ', 'ચાર', 'પાંચ', 'છ', 'સાત', 'આઠ', 'નવ', 'દસ',
            'અગિયાર', 'બાર', 'તેર', 'ચૌદ', 'પંદર', 'સોળ', 'સત્તર', 'અઢાર', 'ઓગણીસ', 'વીસ',
            'એકવીસ', 'બાવીસ', 'ત્રેવીસ', 'ચોવીસ', 'પચ્ચીસ', 'છવ્વીસ', 'સત્તાવીસ', 'અઠ્ઠાવીસ', 'ઓગણત્રીસ', 'ત્રીસ',
            'એકત્રીસ', 'બત્રીસ', 'તેંત્રીસ', 'ચોંત્રીસ', 'પાંત્રીસ', 'છત્રીસ', 'સાડત્રીસ', 'આડત્રીસ', 'ઓગણચાલીસ', 'ચાલીસ',
            'એકતાલીસ', 'બેતાલીસ', 'તેતાલીસ', 'ચુંમ્માલીસ', 'પિસ્તાલીસ', 'છેંતાલીસ', 'સુડતાલીસ', 'અડતાલીસ', 'ઓગણપચાસ', 'પચાસ',
            'એકાવન', 'બાવન', 'ત્રેપન', 'ચોપન', 'પંચાવન', 'છપ્પન', 'સત્તાવન', 'અઠ્ઠાવન', 'ઓગણસાઈઠ', 'સાઈઠ',
            'એકસઠ', 'બાસઠ', 'ત્રેસઠ', 'ચોસઠ', 'પાંસઠ', 'છાસઠ', 'સડસઠ', 'અડસઠ', 'ઓગણોસિત્તેર', 'સિત્તેર',
            'એકોતેર', 'બોંતેર', 'તોંતેર', 'ચુંમોતેર', 'પંચોતેર', 'છોંતેર', 'સીતોતેર', 'ઇઠોતેર', 'ઓગણએંસી', 'એંસી',
            'એક્યાસી', 'બ્યાસી', 'ત્યાસી', 'ચોરાસી', 'પંચાસી', 'છયાસી', 'સત્યાસી', 'અઠયાસી', 'નેવ્યાસી', 'નેવું',
            'એકણું', 'બાણું', 'ત્રાણું', 'ચોરાણું', 'પંચાણું', 'છન્નું', 'સતાણું', 'અઠ્ઠાણું', 'નવ્વાણું'];
        function tw(n) { return gu[n] || ''; }
        function th(n) {
            if (n >= 100) { return gu[Math.floor(n / 100)] + ' સો' + (n % 100 ? ' ' + tw(n % 100) : ''); }
            return tw(n);
        }
        // Recursive segment converter — mirrors NumberToWordsService::innerDigitsGu.
        function seg(n) {
            var r = '';
            if (n >= 10000000) { r += seg(Math.floor(n / 10000000)) + ' કરોડ '; n %= 10000000; }
            if (n >= 100000) { r += tw(Math.floor(n / 100000)) + ' લાખ '; n %= 100000; }
            if (n >= 1000) { r += tw(Math.floor(n / 1000)) + ' હજાર '; n %= 1000; }
            if (n > 0) { r += th(n); }
            return r.trim();
        }
        return seg(num) + ' રૂપિયા';
    };

    SHF.bilingualAmountWords = SHF.bilingualAmountWords || function (num) {
        return SHF.numberToWordsEn(num) + ' / ' + SHF.numberToWordsGu(num);
    };

    // ─────────────────────────────────────────────────────────────
    // SHF.validateForm — verbatim copy from shf-app.js (keep
    // behaviour identical so forms validate the same everywhere).
    // ─────────────────────────────────────────────────────────────
    SHF.validateForm = function ($form, rules) {
        // Clear previous errors
        $form.find('.shf-validation-error').remove();
        $form.find('.is-invalid').removeClass('is-invalid');

        var errors = [];

        $.each(rules, function (fieldName, rule) {
            var $field = $form.find('[name="' + fieldName + '"]');
            if (!$field.length) { return; }

            var val;
            if ($field.is(':radio')) {
                val = $form.find('[name="' + fieldName + '"]:checked').val() || '';
            } else if ($field.is(':checkbox') && !$field.is('[type="hidden"]')) {
                val = $field.is(':checked') ? $field.val() : '';
            } else {
                val = ($field.val() || '').toString().trim();
            }

            var label = rule.label || fieldName.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            var err = null;

            if (rule.required && !val) {
                err = label + ' is required.';
            } else if (val) {
                if (rule.maxlength && val.length > rule.maxlength) {
                    err = label + ' must not exceed ' + rule.maxlength + ' characters.';
                }
                if (!err && rule.minlength && val.length < rule.minlength) {
                    err = label + ' must be at least ' + rule.minlength + ' characters.';
                }
                if (!err && rule.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                    err = label + ' must be a valid email address.';
                }
                if (!err && rule.numeric) {
                    var numVal = parseFloat(val.replace(/,/g, ''));
                    if (isNaN(numVal)) {
                        err = label + ' must be a number.';
                    } else {
                        if (rule.min !== undefined && numVal < rule.min) {
                            err = label + ' must be at least ' + rule.min + '.';
                        }
                        if (!err && rule.max !== undefined && numVal > rule.max) {
                            err = label + ' must not exceed ' + SHF.formatIndianNumber(rule.max) + '.';
                        }
                    }
                }
                if (!err && rule.pattern && !rule.pattern.test(val)) {
                    err = rule.patternMsg || (label + ' format is invalid.');
                }
                if (!err && (rule.dateFormat === 'd/m/Y' || rule.dateFormat === 'dd/mm/yyyy') && !/^\d{2}\/\d{2}\/\d{4}$/.test(val)) {
                    err = label + ' must be in dd/mm/yyyy format.';
                }
            }
            if (!err && rule.custom) {
                err = rule.custom(val, $field, $form);
            }

            if (err) {
                errors.push({ field: fieldName, message: err, $field: $field });
            }
        });

        if (errors.length) {
            var errStyle = 'display:block;width:100%;margin-top:4px;font-size:0.8rem;color:#dc3545;font-weight:500;';

            $.each(errors, function (_, e) {
                var $field = e.$field;
                var $target = $field;

                if ($field.is(':radio')) {
                    $target = $form.find('[name="' + e.field + '"]').first().closest('.form-check, [class*="col-"]');
                }
                if ($field.is(':hidden') && $field.siblings('.shf-amount-input').length) {
                    $target = $field.siblings('.shf-amount-input');
                }

                $target.addClass('is-invalid');
                if (!$target.is(':radio') && !$target.is(':hidden')) {
                    $target.css({ 'border-color': '#dc3545', 'box-shadow': '0 0 0 3px rgba(220,53,69,0.15)' });
                }

                var $feedback = $('<div class="shf-validation-error" style="' + errStyle + '">' + e.message + '</div>');

                var $col = $target.closest('[class*="col-"], .gt-field, .qc-field');
                if ($col.length) {
                    $col.append($feedback);
                } else {
                    var $wrapper = $target.closest('.position-relative, .input-group');
                    if ($wrapper.length) {
                        $wrapper.after($feedback);
                    } else {
                        $target.after($feedback);
                    }
                }
            });

            var $first = errors[0].$field;
            if ($first.is(':hidden') && $first.siblings('.shf-amount-input').length) {
                $first = $first.siblings('.shf-amount-input');
            }
            var $scrollTo = $first.closest('[class*="col-"], .gt-field, .qc-field');
            if (!$scrollTo.length) { $scrollTo = $first; }
            if ($scrollTo.length && $scrollTo.is(':visible')) {
                $('html, body').animate({ scrollTop: $scrollTo.offset().top - 120 }, 300);
                $first.focus();
            }
            return false;
        }
        return true;
    };

    SHF.validateBeforeAjax = function ($container, rules, url, data) {
        if (!SHF.validateForm($container, rules)) { return false; }
        return $.post(url, data);
    };

    // Clear validation errors on field input/change
    $(document).on('input change', '.is-invalid', function () {
        $(this).removeClass('is-invalid').css({ 'border-color': '', 'box-shadow': '' });
        var $col = $(this).closest('[class*="col-"], .gt-field, .qc-field');
        if ($col.length) {
            $col.find('.shf-validation-error').remove();
        } else {
            $(this).nextAll('.shf-validation-error').first().remove();
        }
    });

    // Auto-expand textareas (fallback for browsers without field-sizing: content)
    if (!('CSS' in window) || !CSS.supports('field-sizing', 'content')) {
        function autoExpand(el) {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        }
        $(document).on('input', 'textarea.shf-input, textarea.shf-input-sm', function () {
            autoExpand(this);
        });
        $('textarea.shf-input, textarea.shf-input-sm').each(function () { autoExpand(this); });
    }

    // Toast wrapper — auto-dismiss + manual close
    $('.shf-toast-wrapper [data-auto-dismiss]').each(function () {
        var $toast = $(this);
        var delay = parseInt($toast.attr('data-auto-dismiss'), 10) || 3000;
        $toast.css('opacity', 0).animate({ opacity: 1 }, 200);
        setTimeout(function () {
            $toast.animate({ opacity: 0 }, 300, function () { $toast.remove(); });
        }, delay);
    });
    $(document).on('click', '.shf-toast-close', function () {
        var $toast = $(this).closest('.shf-toast');
        $toast.animate({ opacity: 0 }, 200, function () { $toast.remove(); });
    });

    // Password reveal toggle
    $(document).on('click', '.shf-password-toggle', function () {
        var $btn = $(this);
        var $input = $('#' + $btn.attr('data-target'));
        if (!$input.length) { return; }
        var showing = $input.attr('type') === 'text';
        $input.attr('type', showing ? 'password' : 'text');
        $btn.find('.shf-eye-open').toggleClass('shf-eye-hidden', !showing);
        $btn.find('.shf-eye-closed').toggleClass('shf-eye-hidden', showing);
    });

    // Saved message fade
    $('.shf-saved-msg').each(function () {
        var $msg = $(this);
        setTimeout(function () {
            $msg.fadeOut(400, function () { $msg.remove(); });
        }, 3000);
    });

    // SweetAlert-based delete confirm
    $(document).on('submit', '.shf-confirm-delete', function (e) {
        if (this.__shfConfirmed) { return; }
        e.preventDefault();
        var form = this;
        if (!window.Swal) { if (confirm('Delete this item? This cannot be undone.')) { form.__shfConfirmed = true; form.submit(); } return; }
        Swal.fire({
            title: 'Delete?',
            text: $(form).data('confirmText') || 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
        }).then(function (result) {
            if (result.isConfirmed) { form.__shfConfirmed = true; form.submit(); }
        });
    });

    // ─────────────────────────────────────────────────────────────
    // Force-uppercase data path. The CSS rule on `.shf-input`
    // (`text-transform: uppercase !important`) only flips the
    // *displayed* glyphs — the underlying value submitted to the
    // server stays in whatever case the user typed. This handler
    // uppercases the actual input value on every keystroke / paste
    // / change so what gets persisted matches what the user sees.
    //
    // Exclusions:
    //   - Inputs explicitly opted out via `data-no-uppercase`
    //   - Inputs inside a form/container with `data-no-uppercase`
    //   - Live-formatted amount inputs (the SHF Indian-comma formatter
    //     drives `.value` itself; uppercasing would race with it)
    //   - Datepicker-managed inputs (the plugin sets values programmatically)
    //   - Input types where uppercase makes no sense or would break
    //     things: password, hidden, file, number, date, datetime-local,
    //     month, time, week, tel, color, range, checkbox, radio, etc.
    //
    // Email is INCLUDED. Server side lowercases the email at every auth
    // entry point (LoginRequest, password-reset, NewPasswordController)
    // and `User::setEmailAttribute` stores emails lowercase regardless,
    // so login works for any typed case.
    // ─────────────────────────────────────────────────────────────
    // Some pages load BOTH shf-newtheme.js (global) and shf-app.js
    // (per-page, e.g. quotation create). Both install identical
    // uppercase handlers — this guard ensures only the first wins so
    // the `input` event doesn't fire two value swaps and bounce the
    // caret.
    if (window.__shfUppercaseInstalled) { return; }
    window.__shfUppercaseInstalled = true;

    var SKIP_INPUT_TYPES = [
        'password', 'hidden', 'file', 'number',
        'date', 'datetime-local', 'month', 'time', 'week', 'tel',
        'color', 'range', 'checkbox', 'radio', 'button',
        'submit', 'reset', 'image',
    ];
    var SKIP_CLASSES = [
        'shf-amount-input', 'shf-amount-raw',
        'shf-datepicker', 'shf-datepicker-past', 'shf-datepicker-custom',
    ];

    function shfShouldSkipUppercase(el) {
        if (!el) { return true; }
        if (el.hasAttribute('data-no-uppercase')) { return true; }
        // Walk ancestors for a form/container that opted the whole subtree out.
        var node = el.parentNode;
        while (node && node.nodeType === 1) {
            if (node.hasAttribute && node.hasAttribute('data-no-uppercase')) { return true; }
            node = node.parentNode;
        }
        if (el.tagName === 'INPUT') {
            var t = (el.getAttribute('type') || 'text').toLowerCase();
            if (SKIP_INPUT_TYPES.indexOf(t) !== -1) { return true; }
            // A show/hide toggle flips a password field to type=text — still
            // skip it. Detect by semantics that survive the type swap.
            var ac = (el.getAttribute('autocomplete') || '').toLowerCase();
            var nameId = ((el.getAttribute('name') || '') + ' ' + (el.id || '')).toLowerCase();
            if (ac.indexOf('password') !== -1 || nameId.indexOf('password') !== -1) { return true; }
        }
        for (var i = 0; i < SKIP_CLASSES.length; i++) {
            if (el.classList && el.classList.contains(SKIP_CLASSES[i])) { return true; }
        }
        return false;
    }

    function shfApplyUppercase(el) {
        if (shfShouldSkipUppercase(el)) { return; }
        var v = el.value;
        if (v == null || v === '') { return; }
        var upper = v.toUpperCase();
        if (upper === v) { return; }
        // Preserve caret position across the value swap so the user
        // doesn't get bumped to the end of the field mid-typing.
        var start = null, end = null;
        try {
            start = el.selectionStart;
            end = el.selectionEnd;
        } catch (e) { /* some inputs (number/date) throw on selectionStart */ }
        el.value = upper;
        if (start !== null && end !== null) {
            try { el.setSelectionRange(start, end); } catch (e) { /* same caveat */ }
        }
    }

    // Delegated handlers so dynamically-added inputs (bank cards,
    // doc grid, etc.) are covered without per-render rebinding.
    $(document).on('input change blur', 'input, textarea', function () {
        shfApplyUppercase(this);
    });

    // Defensive sweep on submit — if anything bypassed the input
    // handler (programmatic .value = "...", autofill races) it gets
    // uppercased one more time before the form leaves the page.
    $(document).on('submit', 'form', function () {
        var $form = $(this);
        $form.find('input, textarea').each(function () {
            shfApplyUppercase(this);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // SHF.loader — global AJAX loading overlay.
    //
    // Reference-counted: overlapping requests share one overlay that
    // hides only when the last one finishes. A 250ms show-delay keeps
    // fast requests from flashing it; once visible it stays >= 300ms
    // so it never blinks. A 30s watchdog force-hides if an end() is
    // ever lost, so the UI can't freeze behind the overlay.
    //
    // jQuery AJAX is hooked automatically via ajaxSend/ajaxComplete —
    // that covers every $.post/$.ajax user action site-wide. Opt a
    // background request out with jQuery's `global: false` option.
    // fetch() is NOT hooked (badge poll, DataTables, offline sync stay
    // silent by design) — fetch-based user actions call
    // SHF.loader.begin()/end() explicitly.
    // ─────────────────────────────────────────────────────────────
    if (!window.__shfLoaderInstalled) {
        window.__shfLoaderInstalled = true;

        var LOADER_SHOW_DELAY = 250;
        var LOADER_MIN_VISIBLE = 300;
        var LOADER_WATCHDOG_MS = 30000;

        var loaderCount = 0;
        var loaderShowTimer = null;
        var loaderHideTimer = null;
        var loaderWatchdog = null;
        var loaderShownAt = 0;

        // Created on demand — static markup can be stripped by page
        // init sweeps, so never rely on it existing in the blade.
        function loaderEl() {
            var el = document.getElementById('shfAjaxLoader');
            if (!el) {
                el = document.createElement('div');
                el.id = 'shfAjaxLoader';
                el.className = 'shf-ajax-loader';
                el.setAttribute('role', 'status');
                el.setAttribute('aria-live', 'polite');
                el.innerHTML =
                    '<div class="shf-ajax-loader-box">' +
                    '<div class="shf-ajax-loader-spinner"></div>' +
                    '<div class="shf-ajax-loader-text">Processing…<br>પ્રક્રિયા ચાલુ છે…</div>' +
                    '</div>';
                document.body.appendChild(el);
            }
            return el;
        }

        function loaderVisible() {
            var el = document.getElementById('shfAjaxLoader');
            return !!(el && el.classList.contains('shf-ajax-loader--visible'));
        }

        function loaderShowNow() {
            loaderShowTimer = null;
            if (loaderCount <= 0) { return; }
            loaderEl().classList.add('shf-ajax-loader--visible');
            loaderShownAt = Date.now();
        }

        function loaderHideNow() {
            loaderHideTimer = null;
            var el = document.getElementById('shfAjaxLoader');
            if (el) { el.classList.remove('shf-ajax-loader--visible'); }
            loaderShownAt = 0;
        }

        SHF.loader = {
            begin: function () {
                loaderCount++;
                if (loaderHideTimer) {
                    // New request while the previous batch's hide is
                    // pending — keep the overlay up, no re-show flicker.
                    clearTimeout(loaderHideTimer);
                    loaderHideTimer = null;
                }
                if (!loaderVisible() && !loaderShowTimer) {
                    loaderShowTimer = setTimeout(loaderShowNow, LOADER_SHOW_DELAY);
                }
                if (loaderWatchdog) { clearTimeout(loaderWatchdog); }
                loaderWatchdog = setTimeout(function () {
                    loaderCount = 0;
                    if (loaderShowTimer) { clearTimeout(loaderShowTimer); loaderShowTimer = null; }
                    loaderHideNow();
                    if (window.console && console.warn) {
                        console.warn('SHF.loader: watchdog fired — an end() call was lost or a request ran > ' + (LOADER_WATCHDOG_MS / 1000) + 's.');
                    }
                }, LOADER_WATCHDOG_MS);
            },
            end: function () {
                if (loaderCount > 0) { loaderCount--; }
                if (loaderCount > 0) { return; }
                if (loaderWatchdog) { clearTimeout(loaderWatchdog); loaderWatchdog = null; }
                if (loaderShowTimer) {
                    // Finished before the show-delay elapsed — never show.
                    clearTimeout(loaderShowTimer);
                    loaderShowTimer = null;
                    return;
                }
                if (!loaderVisible()) { return; }
                var remaining = LOADER_MIN_VISIBLE - (Date.now() - loaderShownAt);
                if (remaining > 0) {
                    loaderHideTimer = setTimeout(loaderHideNow, remaining);
                } else {
                    loaderHideNow();
                }
            },
            // Force-reset (bfcache restores, tests).
            reset: function () {
                loaderCount = 0;
                if (loaderShowTimer) { clearTimeout(loaderShowTimer); loaderShowTimer = null; }
                if (loaderHideTimer) { clearTimeout(loaderHideTimer); loaderHideTimer = null; }
                if (loaderWatchdog) { clearTimeout(loaderWatchdog); loaderWatchdog = null; }
                loaderHideNow();
            }
        };

        // Every jQuery AJAX request site-wide (unless `global: false`).
        $(document).ajaxSend(function () { SHF.loader.begin(); });
        $(document).ajaxComplete(function () { SHF.loader.end(); });

        // Back/forward-cache restore can resurrect a visible overlay
        // from before navigation — clear it.
        $(window).on('pageshow', function (e) {
            if (e.originalEvent && e.originalEvent.persisted) { SHF.loader.reset(); }
        });
    }
});
