/*
 * Shared site-wide Create-Task modal — triggered from the FAB (or any
 * element with [data-shf-open="create-task"], or via
 * document.dispatchEvent(new CustomEvent('shf:open-create-task'))).
 *
 * Validation uses window.SHF.validateForm (jQuery-based) — same pattern as
 * the legacy dashCreateTaskModal. No HTML5 required attributes.
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        var $modal    = $('#shfCreateTaskModal');
        var $backdrop = $('#shfCreateTaskBackdrop');
        var $form     = $('#shfCreateTaskForm');
        if (!$modal.length || !$form.length) { return; }

        /* ───────── Open / close ───────── */
        function openModal() {
            // Dismiss the FAB menu + more sheet if either is still open
            document.body.classList.remove('shf-fab-open');
            document.body.classList.remove('shf-more-open');
            resetForm();
            $backdrop.show();
            $modal.css('display', 'flex');
            setTimeout(function () { $('#shfCreateTaskTitleInput').trigger('focus'); }, 30);
        }
        function closeModal() {
            $modal.hide();
            $backdrop.hide();
            $('#shfCreateTaskLoanResults').removeClass('open').empty();
        }
        function resetForm() {
            $form[0].reset();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.shf-client-error').remove();
            $('#shfCreateTaskLoanId').val('');
            $('#shfCreateTaskAssignee').val($('#shfCreateTaskAssignee option[selected]').val() || '');
            // Default due date = today + 7 days (matches legacy UX)
            var d = new Date(); d.setDate(d.getDate() + 7);
            var dd = ('0' + d.getDate()).slice(-2);
            var mm = ('0' + (d.getMonth() + 1)).slice(-2);
            $('#shfCreateTaskDueDate').val(dd + '/' + mm + '/' + d.getFullYear());
            $('#shfCreateTaskPriority').val('normal');
            $('#shfCreateTaskTitle').text('Create New Task');
        }

        /* Trigger hooks — any of:
             <button data-shf-open="create-task">
             document.dispatchEvent(new CustomEvent('shf:open-create-task'))
         */
        $(document).on('click', '[data-shf-open="create-task"]', function (e) {
            e.preventDefault();
            openModal();
        });
        document.addEventListener('shf:open-create-task', openModal);

        $('#shfCreateTaskClose, #shfCreateTaskCancel').on('click', closeModal);
        $backdrop.on('click', closeModal);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $modal.is(':visible')) { closeModal(); }
        });

        /* Clear per-field error when user changes it (legacy pattern) */
        $(document).on('input change', '#shfCreateTaskForm .is-invalid', function () {
            $(this).removeClass('is-invalid').next('.shf-client-error').remove();
        });

        /* ───────── Datepicker on modal open ───────── */
        if ($.fn.datepicker) {
            $('#shfCreateTaskDueDate').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                clearBtn: true,
                startDate: new Date(),
            });
        }

        /* ───────── Loan search autocomplete ───────── */
        var loanDebounce;
        var searchUrl = $form.data('loanSearchUrl') || (window.__SHF_TASK_ROUTES && window.__SHF_TASK_ROUTES.searchLoans) || '/general-tasks/search-loans';
        $('#shfCreateTaskLoanSearch').on('input', function () {
            clearTimeout(loanDebounce);
            var q = $.trim($(this).val());
            var $results = $('#shfCreateTaskLoanResults');
            if (q.length < 2) { $results.removeClass('open').empty(); $('#shfCreateTaskLoanId').val(''); return; }
            loanDebounce = setTimeout(function () {
                $.get(searchUrl, { q: q }).done(function (loans) {
                    if (!loans || !loans.length) {
                        $results.html('<div class="item" style="color:var(--ink-4);">No loans found</div>').addClass('open');
                        return;
                    }
                    var html = '';
                    loans.slice(0, 8).forEach(function (l) {
                        var label = '#' + esc(l.loan_number || l.id) + ' — ' + esc(l.customer_name || '');
                        if (l.bank_name) { label += ' <span style="color:var(--ink-4);">(' + esc(l.bank_name) + ')</span>'; }
                        html += '<div class="item" data-id="' + esc(l.id) + '" data-label="#' + esc(l.loan_number) + ' - ' + esc(l.customer_name) + '">' + label + '</div>';
                    });
                    $results.html(html).addClass('open');
                });
            }, 250);
        });
        $(document).on('click', '#shfCreateTaskLoanResults .item', function () {
            var $item = $(this);
            if (!$item.data('id')) { return; }
            $('#shfCreateTaskLoanId').val($item.data('id'));
            $('#shfCreateTaskLoanSearch').val($item.data('label'));
            $('#shfCreateTaskLoanResults').removeClass('open');
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#shfCreateTaskLoanSearch, #shfCreateTaskLoanResults').length) {
                $('#shfCreateTaskLoanResults').removeClass('open');
            }
        });
        function esc(s) { return $('<div/>').text(s == null ? '' : s).html(); }

        /* ───────── Submit with SHF.validateForm (jQuery-based) ───────── */
        $form.on('submit', function (e) {
            e.preventDefault();

            // Clear any prior inline errors before re-validating (legacy pattern)
            $form.find('.shf-client-error').remove();
            $form.find('.is-invalid').removeClass('is-invalid');

            var valid = true;

            // Prefer SHF.validateForm if available — matches quotation-page
            // conventions and gives field-label aware messages.
            if (window.SHF && typeof window.SHF.validateForm === 'function') {
                valid = window.SHF.validateForm($form, {
                    title:    { required: true, maxlength: 255, label: 'Title' },
                    priority: { required: true,                label: 'Priority' },
                    due_date: { required: true, dateFormat: 'dd/mm/yyyy', label: 'Due Date' },
                });
            } else {
                // Graceful fallback — same checks written by hand.
                var checks = [
                    { sel: '#shfCreateTaskTitleInput', msg: 'Please enter the task title' },
                    { sel: '#shfCreateTaskPriority',   msg: 'Please select a priority' },
                    { sel: '#shfCreateTaskDueDate',    msg: 'Please select a due date' },
                ];
                checks.forEach(function (c) {
                    var $el = $(c.sel);
                    if (!$.trim($el.val())) {
                        $el.addClass('is-invalid').after('<div class="text-danger small mt-1 shf-client-error">' + c.msg + '</div>');
                        valid = false;
                    }
                });
            }

            if (!valid) {
                $form.find('.is-invalid').first().trigger('focus');
                return false;
            }

            var $save = $('#shfCreateTaskSave');
            $save.prop('disabled', true).css('opacity', 0.65);

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).done(function () {
                closeModal();
                if (window.shfToast) { window.shfToast('Task created', 'success'); }
                // Any listing page that cares can listen and refresh itself.
                document.dispatchEvent(new CustomEvent('shf:task-created'));
                // If we're on a non-task-listing page, reloading keeps
                // dashboard counts up to date; on tasks page the listener
                // handles refresh without reload.
                var onTasksIndex = /\/general-tasks(?:\?|$)/.test(location.pathname + location.search);
                if (!onTasksIndex) { setTimeout(function () { location.reload(); }, 600); }
            }).fail(function (xhr) {
                var json = xhr.responseJSON;
                if (json && json.errors) {
                    Object.keys(json.errors).forEach(function (field) {
                        var $el = $form.find('[name="' + field + '"]');
                        if ($el.length) {
                            $el.addClass('is-invalid').after('<div class="text-danger small mt-1 shf-client-error">' + esc(json.errors[field][0]) + '</div>');
                        }
                    });
                    $form.find('.is-invalid').first().trigger('focus');
                } else {
                    alert('Failed to save task. Please try again.');
                }
            }).always(function () {
                $save.prop('disabled', false).css('opacity', 1);
            });
        });
    });
})(window.jQuery);
