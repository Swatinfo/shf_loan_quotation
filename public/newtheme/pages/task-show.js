/*
 * Newtheme Task show page — wires:
 *   - Status change buttons   → PATCH /general-tasks/{id}/status
 *   - Edit modal              → PUT  /general-tasks/{id}
 *   - Edit modal loan search  → GET  /general-tasks/search-loans
 *   - Delete button           → confirm + POST _method=DELETE
 *   - Comment form / delete   → SweetAlert2 confirm on delete
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        var C = window.__TS || {};
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        /* ───────── Status change ───────── */
        $('.ts-status-btn').on('click', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) { return; }
            var status = $btn.data('status');
            $btn.prop('disabled', true).css('opacity', 0.7);
            $.ajax({
                url: C.statusUrl,
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: { _token: csrf, _method: 'PATCH', status: status },
            }).done(function () {
                window.location.reload();
            }).fail(function () {
                $btn.prop('disabled', false).css('opacity', 1);
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Failed to update status', confirmButtonColor: '#f15a29' });
                } else {
                    alert('Failed to update status');
                }
            });
        });

        /* ───────── Edit modal ───────── */
        var $modal    = $('#tsEditModal');
        var $backdrop = $('#tsEditBackdrop');
        var $form     = $('#tsEditForm');

        function openEdit() {
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.shf-client-error').remove();
            $backdrop.show();
            $modal.css('display', 'flex');
            setTimeout(function () { $('#tsEditTitle').trigger('focus'); }, 30);
        }
        function closeEdit() {
            $modal.hide();
            $backdrop.hide();
            $('#tsEditLoanResults').removeClass('open').empty();
        }

        if (C.canEdit && $modal.length) {
            $('#tsEditBtn').on('click', openEdit);
            $('#tsEditClose, #tsEditCancel').on('click', closeEdit);
            $backdrop.on('click', closeEdit);
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) { closeEdit(); }
            });

            /* Datepicker */
            if ($.fn.datepicker) {
                $('#tsEditDueDate').datepicker({
                    format: 'dd/mm/yyyy',
                    autoclose: true,
                    todayHighlight: true,
                    clearBtn: true,
                });
            }

            /* Clear error on change */
            $form.on('input change', '.is-invalid', function () {
                $(this).removeClass('is-invalid').next('.shf-client-error').remove();
            });

            /* Loan autocomplete */
            var loanDebounce;
            $('#tsEditLoanSearch').on('input', function () {
                clearTimeout(loanDebounce);
                var q = $.trim($(this).val());
                var $r = $('#tsEditLoanResults');
                if (q.length < 2) { $r.removeClass('open').empty(); $('#tsEditLoanId').val(''); return; }
                loanDebounce = setTimeout(function () {
                    $.get(C.searchLoansUrl, { q: q }).done(function (loans) {
                        if (!loans || !loans.length) {
                            $r.html('<div class="item" style="color:var(--ink-4);">No loans found</div>').addClass('open');
                            return;
                        }
                        var html = '';
                        loans.slice(0, 8).forEach(function (l) {
                            var label = '#' + esc(l.loan_number || l.id) + ' — ' + esc(l.customer_name || '');
                            if (l.bank_name) { label += ' <span style="color:var(--ink-4);">(' + esc(l.bank_name) + ')</span>'; }
                            html += '<div class="item" data-id="' + esc(l.id) + '" data-label="#' + esc(l.loan_number) + ' - ' + esc(l.customer_name) + '">' + label + '</div>';
                        });
                        $r.html(html).addClass('open');
                    });
                }, 250);
            });
            $(document).on('click', '#tsEditLoanResults .item', function () {
                var $i = $(this);
                if (!$i.data('id')) { return; }
                $('#tsEditLoanId').val($i.data('id'));
                $('#tsEditLoanSearch').val($i.data('label'));
                $('#tsEditLoanResults').removeClass('open');
            });
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#tsEditLoanSearch, #tsEditLoanResults').length) {
                    $('#tsEditLoanResults').removeClass('open');
                }
            });

            /* Submit */
            $form.on('submit', function (e) {
                e.preventDefault();
                $form.find('.shf-client-error').remove();
                $form.find('.is-invalid').removeClass('is-invalid');

                var valid = true;
                if (window.SHF && typeof window.SHF.validateForm === 'function') {
                    valid = window.SHF.validateForm($form, {
                        title:    { required: true, maxlength: 255, label: 'Title' },
                        priority: { required: true,                label: 'Priority' },
                        due_date: { required: true, dateFormat: 'dd/mm/yyyy', label: 'Due Date' },
                    });
                } else {
                    [
                        { sel: '#tsEditTitle',    msg: 'Please enter the task title' },
                        { sel: '#tsEditPriority', msg: 'Please select a priority' },
                        { sel: '#tsEditDueDate',  msg: 'Please select a due date' },
                    ].forEach(function (c) {
                        var $el = $(c.sel);
                        if (!$.trim($el.val())) {
                            $el.addClass('is-invalid').after('<div class="text-danger small mt-1 shf-client-error">' + c.msg + '</div>');
                            valid = false;
                        }
                    });
                }

                if (!valid) { $form.find('.is-invalid').first().trigger('focus'); return false; }

                var $save = $('#tsEditSave');
                $save.prop('disabled', true).css('opacity', 0.65);
                $form[0].submit();
            });
        }

        /* ───────── Delete confirmation ───────── */
        $('#tsDeleteForm').on('submit', function (e) {
            if ($(this).data('confirmed')) { return; }
            e.preventDefault();
            var $f = $(this);
            if (window.Swal) {
                Swal.fire({
                    title: 'Delete this task?',
                    text: 'This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel',
                }).then(function (r) {
                    if (r.isConfirmed) { $f.data('confirmed', true); $f[0].submit(); }
                });
            } else if (confirm('Delete this task? This cannot be undone.')) {
                $f.data('confirmed', true); $f[0].submit();
            }
        });

        /* ───────── Comment delete confirmation ───────── */
        $('.ts-comment-del-form').on('submit', function (e) {
            if ($(this).data('confirmed')) { return; }
            e.preventDefault();
            var $f = $(this);
            if (window.Swal) {
                Swal.fire({
                    title: 'Delete this comment?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                }).then(function (r) {
                    if (r.isConfirmed) { $f.data('confirmed', true); $f[0].submit(); }
                });
            } else if (confirm('Delete this comment?')) {
                $f.data('confirmed', true); $f[0].submit();
            }
        });

        /* ───────── Comment form validation ───────── */
        $('#tsCommentForm').on('submit', function (e) {
            var $ta = $(this).find('textarea[name="body"]');
            if (!$.trim($ta.val())) {
                e.preventDefault();
                $ta.addClass('is-invalid').trigger('focus');
            }
        });

        function esc(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    });
})(window.jQuery);
