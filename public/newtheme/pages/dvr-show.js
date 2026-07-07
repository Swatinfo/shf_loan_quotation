/*
 * Newtheme DVR show page — wires:
 *   - Edit modal (jQuery, SHF.validateForm, bootstrap-datepicker)
 *   - Log Follow-up modal (native form POST to dvr.store)
 *   - Mark Follow-up Done   → PATCH /dvr/{id}/follow-up-done (AJAX)
 *   - Delete confirmation   → SweetAlert2 + submit
 *   - Follow-up toggle (+7-day default)
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        var C = window.__DS || {};

        /* ───────── Datepickers inside modals ───────── */
        if ($.fn.datepicker) {
            $('.shf-datepicker-past').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, endDate: '+0d', clearBtn: true });
            $('.shf-datepicker-future').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, startDate: '+1d', clearBtn: true });
        }

        /* ───────── Shared modal helpers ───────── */
        function bindModal(cfg) {
            var $modal = $(cfg.modal);
            var $backdrop = $(cfg.backdrop);
            if (!$modal.length) { return; }
            $(cfg.open).on('click', function () {
                $backdrop.show();
                $modal.css('display', 'flex');
                if (cfg.focus) { setTimeout(function () { $(cfg.focus).trigger('focus'); }, 30); }
            });
            $(cfg.close.join(',')).on('click', function () {
                $modal.hide();
                $backdrop.hide();
            });
            $backdrop.on('click', function () { $modal.hide(); $backdrop.hide(); });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    $modal.hide();
                    $backdrop.hide();
                }
            });
        }

        bindModal({
            modal: '#dsEditModal',
            backdrop: '#dsEditBackdrop',
            open: '#dsEditBtn',
            close: ['#dsEditClose', '#dsEditCancel'],
            focus: '#dsEditName',
        });

        bindModal({
            modal: '#dsFuModal',
            backdrop: '#dsFuBackdrop',
            open: '#dsLogFollowUpBtn',
            close: ['#dsFuClose', '#dsFuCancel'],
            focus: '#dsFuName',
        });

        /* ───────── Clear field error on change ───────── */
        $(document).on('input change', '#dsEditForm .is-invalid, #dsFuForm .is-invalid', function () {
            $(this).removeClass('is-invalid').next('.shf-client-error').remove();
        });

        /* ───────── Follow-up toggle (+7 day default) ───────── */
        $('#dsEditFollowNeeded').on('change', function () {
            $('#dsEditFollowFields').toggle(this.checked);
            if (this.checked && !$('#dsEditFollowDate').val()) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                var dd = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                if ($.fn.datepicker) {
                    $('#dsEditFollowDate').datepicker('update', dd);
                } else {
                    $('#dsEditFollowDate').val(dd);
                }
            }
        });

        $('#dsFuFollowNeeded').on('change', function () {
            $('#dsFuFollowFields').toggle(this.checked);
            if (this.checked && !$('#dsFuFollowDate').val()) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                var dd = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                if ($.fn.datepicker) {
                    $('#dsFuFollowDate').datepicker('update', dd);
                } else {
                    $('#dsFuFollowDate').val(dd);
                }
            }
        });

        /* ───────── Shared validation rules ───────── */
        var dvrRules = {
            contact_name: { required: true, maxlength: 255, label: 'Contact Name / સંપર્ક નામ' },
            contact_type: { required: true, label: 'Contact Type / સંપર્ક પ્રકાર' },
            purpose:      { required: true, label: 'Purpose / હેતુ' },
            visit_date:   { required: true, dateFormat: 'd/m/Y', label: 'Visit Date / મુલાકાત તારીખ' },
            contact_phone:{ maxlength: 20, label: 'Contact Phone / ફોન' },
            notes:        { maxlength: 5000, label: 'Notes / નોંધ' },
            outcome:      { maxlength: 5000, label: 'Outcome / પરિણામ' },
        };

        function followUpRule(checkboxSel, fieldSel) {
            return {
                label: 'Follow-up Date / ફોલો-અપ તારીખ',
                custom: function () {
                    if (!$(checkboxSel).is(':checked')) { return null; }
                    var val = $(fieldSel).val();
                    if (!val) { return 'Follow-up Date is required when follow-up is needed / ફોલો-અપ તારીખ જરૂરી છે'; }
                    var parts = val.split('/');
                    if (parts.length === 3) {
                        var inputDate = new Date(parts[2], parts[1] - 1, parts[0]);
                        var today = new Date(); today.setHours(0, 0, 0, 0);
                        if (inputDate <= today) {
                            return 'Follow-up Date must be a future date / ફોલો-અપ તારીખ ભવિષ્યની હોવી જોઈએ';
                        }
                    }
                    return null;
                },
            };
        }

        /* ───────── Edit form submit ───────── */
        $('#dsEditForm').on('submit', function (e) {
            var rules = $.extend(true, {}, dvrRules, {
                follow_up_date: followUpRule('#dsEditFollowNeeded', '#dsEditFollowDate'),
            });
            if (window.SHF && typeof window.SHF.validateForm === 'function') {
                if (!window.SHF.validateForm($(this), rules)) {
                    e.preventDefault();
                    $(this).find('.is-invalid').first().trigger('focus');
                }
            }
        });

        /* ───────── Follow-up form submit ───────── */
        $('#dsFuForm').on('submit', function (e) {
            var rules = $.extend(true, {}, dvrRules, {
                follow_up_date: followUpRule('#dsFuFollowNeeded', '#dsFuFollowDate'),
            });
            if (window.SHF && typeof window.SHF.validateForm === 'function') {
                if (!window.SHF.validateForm($(this), rules)) {
                    e.preventDefault();
                    $(this).find('.is-invalid').first().trigger('focus');
                }
            }
        });

        /* ───────── Mark follow-up done ───────── */
        $('#dsMarkDoneBtn').on('click', function () {
            var runMark = function () {
                $.ajax({
                    url: C.markDoneUrl,
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': C.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                }).done(function () {
                    location.reload();
                }).fail(function () {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Could not update', confirmButtonColor: '#f15a29' });
                    } else {
                        alert('Could not update.');
                    }
                });
            };

            if (window.Swal) {
                Swal.fire({
                    title: 'Mark Follow-up Done?',
                    text: 'This marks the follow-up as completed without logging a new visit.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#f15a29',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, mark done',
                    cancelButtonText: 'Cancel',
                }).then(function (r) { if (r.isConfirmed) { runMark(); } });
            } else if (confirm('Mark follow-up done?')) {
                runMark();
            }
        });

        /* ───────── Delete confirmation ───────── */
        $('#dsDeleteForm').on('submit', function (e) {
            if ($(this).data('confirmed')) { return; }
            e.preventDefault();
            var $f = $(this);
            if (window.Swal) {
                Swal.fire({
                    title: 'Delete this visit report?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel',
                }).then(function (r) {
                    if (r.isConfirmed) { $f.data('confirmed', true); $f[0].submit(); }
                });
            } else if (confirm('Delete this visit report?')) {
                $f.data('confirmed', true); $f[0].submit();
            }
        });
    });
})(window.jQuery);
