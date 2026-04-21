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
});
