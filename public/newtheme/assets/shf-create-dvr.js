/*
 * Shared site-wide Create-DVR modal — mirrors the create-task modal pattern.
 * Trigger: any element with [data-shf-open="create-dvr"] or
 *          document.dispatchEvent(new CustomEvent('shf:open-create-dvr')).
 *
 * Validation uses window.SHF.validateForm (jQuery-based) — same legacy pattern
 * as the dashCreateDvrModal. No HTML5 required attributes.
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        var $modal    = $('#shfCreateDvrModal');
        var $backdrop = $('#shfCreateDvrBackdrop');
        var $form     = $('#shfCreateDvrForm');
        if (!$modal.length || !$form.length) { return; }

        /* ───────── Open / close ───────── */
        function openModal() {
            document.body.classList.remove('shf-fab-open');
            document.body.classList.remove('shf-more-open');
            resetForm();
            $backdrop.show();
            $modal.css('display', 'flex');
            setTimeout(function () { $('#shfDvrContactName').trigger('focus'); }, 30);
        }
        function closeModal() {
            $modal.hide();
            $backdrop.hide();
        }
        function resetForm() {
            $form[0].reset();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.shf-client-error').remove();
            $('#shfDvrFollowUpFields').hide();
            // Default visit_date = today
            var today = new Date();
            var dd = ('0' + today.getDate()).slice(-2);
            var mm = ('0' + (today.getMonth() + 1)).slice(-2);
            $('#shfDvrVisitDate').val(dd + '/' + mm + '/' + today.getFullYear());
        }

        $(document).on('click', '[data-shf-open="create-dvr"]', function (e) {
            e.preventDefault();
            openModal();
        });
        document.addEventListener('shf:open-create-dvr', openModal);

        $('#shfCreateDvrClose, #shfCreateDvrCancel').on('click', closeModal);
        $backdrop.on('click', closeModal);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $modal.is(':visible')) { closeModal(); }
        });

        /* Clear per-field error when the user changes it (legacy pattern) */
        $(document).on('input change', '#shfCreateDvrForm .is-invalid', function () {
            $(this).removeClass('is-invalid').next('.shf-client-error').remove();
        });

        /* ───────── Datepickers ───────── */
        if ($.fn.datepicker) {
            $('#shfDvrVisitDate').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                endDate: '+0d',                  // visits can only be today-or-past
            });
            $('#shfDvrFollowUpDate').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                startDate: '+1d',                // follow-ups are future-only
            });
        }

        /* ───────── Contact search (name or phone) ───────── */
        var $contactName  = $('#shfDvrContactName');
        var $contactPhone = $('#shfDvrContactPhone');
        var $contactType  = $('#shfDvrContactType');
        var $contactResults = $('#shfDvrContactResults');
        // Matches Route::get('/dvr/search-contacts', ...) — relative so it
        // inherits the current host (sidesteps cross-origin cookie issues).
        var searchUrl = '/dvr/search-contacts';
        var contactDebounce;

        function esc(s) { return $('<div/>').text(s == null ? '' : s).html(); }

        function runContactSearch() {
            var q = $.trim(($contactName.val() || '') + ' ' + ($contactPhone.val() || ''));
            // Use whichever field was typed last — but fall through to combined
            // text if we only have one field populated.
            var qFinal = $.trim($contactName.val()) || $.trim($contactPhone.val());
            if (qFinal.length < 2) { $contactResults.removeClass('open').empty(); return; }

            clearTimeout(contactDebounce);
            contactDebounce = setTimeout(function () {
                $.get(searchUrl, { q: qFinal }).done(function (contacts) {
                    if (!contacts || !contacts.length) { $contactResults.removeClass('open').empty(); return; }
                    var html = '';
                    contacts.slice(0, 10).forEach(function (c) {
                        var label = '<strong>' + esc(c.name) + '</strong>';
                        if (c.phone)  { label += ' <span style="color:var(--ink-4);">' + esc(c.phone) + '</span>'; }
                        if (c.source) { label += ' <span style="background:var(--paper-2,#f4f2f0);color:var(--ink-3);font-size:10px;padding:1px 6px;border-radius:999px;margin-left:4px;">' + esc(c.source) + '</span>'; }
                        html += '<div class="item" data-name="' + esc(c.name) + '" data-phone="' + esc(c.phone || '') + '" data-type="' + esc(c.type || '') + '">' + label + '</div>';
                    });
                    $contactResults.html(html).addClass('open');
                });
            }, 300);
        }

        $contactName.on('input', runContactSearch);
        $contactPhone.on('input', runContactSearch);

        $contactResults.on('click', '.item', function () {
            var $item = $(this);
            $contactName.val($item.data('name'));
            $contactPhone.val($item.data('phone'));
            var t = $item.data('type');
            if (t && $contactType.find('option[value="' + t + '"]').length) {
                $contactType.val(t);
            }
            $contactResults.removeClass('open').empty();
        });

        // Close dropdown on outside-click
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#shfDvrContactName, #shfDvrContactPhone, #shfDvrContactResults').length) {
                $contactResults.removeClass('open');
            }
        });

        /* ───────── Follow-up toggle — show/hide + +7-day default ───────── */
        $('#shfDvrFollowUpNeeded').on('change', function () {
            var checked = $(this).is(':checked');
            $('#shfDvrFollowUpFields').toggle(checked);
            if (checked && !$('#shfDvrFollowUpDate').val()) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                var dd = ('0' + d.getDate()).slice(-2);
                var mm = ('0' + (d.getMonth() + 1)).slice(-2);
                if ($.fn.datepicker) {
                    $('#shfDvrFollowUpDate').datepicker('update', dd + '/' + mm + '/' + d.getFullYear());
                } else {
                    $('#shfDvrFollowUpDate').val(dd + '/' + mm + '/' + d.getFullYear());
                }
            }
        });

        /* ───────── Submit with SHF.validateForm ───────── */
        $form.on('submit', function (e) {
            e.preventDefault();

            $form.find('.shf-client-error').remove();
            $form.find('.is-invalid').removeClass('is-invalid');

            var valid = true;
            var rules = {
                contact_name: { required: true, maxlength: 255,                 label: 'Contact Name' },
                contact_type: { required: true,                                   label: 'Contact Type' },
                purpose:      { required: true,                                   label: 'Purpose' },
                visit_date:   { required: true, dateFormat: 'dd/mm/yyyy',        label: 'Visit Date' },
            };
            if ($('#shfDvrFollowUpNeeded').is(':checked')) {
                rules.follow_up_date = { required: true, dateFormat: 'dd/mm/yyyy', label: 'Follow-up Date' };
            }

            if (window.SHF && typeof window.SHF.validateForm === 'function') {
                valid = window.SHF.validateForm($form, rules);
            } else {
                // Plain-jQuery fallback
                Object.keys(rules).forEach(function (name) {
                    var r = rules[name];
                    var $el = $form.find('[name="' + name + '"]');
                    if (r.required && !$.trim($el.val())) {
                        $el.addClass('is-invalid').after('<div class="text-danger small mt-1 shf-client-error">' + (r.label || name) + ' is required</div>');
                        valid = false;
                    }
                });
            }

            if (!valid) {
                $form.find('.is-invalid').first().trigger('focus');
                return false;
            }

            var $save = $('#shfCreateDvrSave');
            $save.prop('disabled', true).css('opacity', 0.65);

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).done(function () {
                closeModal();
                if (window.shfToast) { window.shfToast('Visit saved', 'success'); }
                document.dispatchEvent(new CustomEvent('shf:dvr-created'));
                // On non-DVR pages, reload so dashboard counts stay in sync.
                var onDvrIndex = /\/dvr(?:\/|\?|$)/.test(location.pathname + location.search);
                if (!onDvrIndex) { setTimeout(function () { location.reload(); }, 600); }
            }).fail(function (xhr) {
                var json = xhr.responseJSON;
                if (json && json.errors) {
                    Object.keys(json.errors).forEach(function (field) {
                        var $el = $form.find('[name="' + field + '"]');
                        if ($el.length) {
                            $el.addClass('is-invalid').after('<div class="text-danger small mt-1 shf-client-error">' + $('<div/>').text(json.errors[field][0]).html() + '</div>');
                        }
                    });
                    $form.find('.is-invalid').first().trigger('focus');
                } else {
                    alert('Failed to save visit. Please try again.');
                }
            }).always(function () {
                $save.prop('disabled', false).css('opacity', 1);
            });
        });
    });
})(window.jQuery);
