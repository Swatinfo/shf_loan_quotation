/*
 * Newtheme loan show page — resources/views/newtheme/loans/show.blade.php
 *
 * - Collapsible card toggles (chevron + body)
 * - Status change (SweetAlert2 + POST to loans.update-status)
 * - Remarks: AJAX load + add
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        var C = window.__LS || {};
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        /* ───────── Collapsible sections ───────── */
        $(document).on('click keydown', '.ls-toggle', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
            if (e.type === 'keydown') { e.preventDefault(); }
            var $card = $(this).closest('.ls-collapsible');
            $card.toggleClass('collapsed');
        });

        /* ───────── Status change ───────── */
        $('.ls-status-change').on('click', function (e) {
            e.preventDefault();
            var status = $(this).data('status');
            var needsReason = (status === 'on_hold' || status === 'cancelled');
            var title = status === 'on_hold'
                ? 'Put Loan On Hold?'
                : (status === 'cancelled' ? 'Cancel Loan?' : 'Reactivate Loan?');

            if (needsReason) {
                SHF.prompt({
                    title: title,
                    input: 'textarea',
                    inputLabel: 'Reason (required)',
                    placeholder: 'Reason for ' + status.replace('_', ' ') + '…',
                    required: true,
                    requiredMsg: 'Please provide a reason',
                    icon: 'question',
                    danger: status === 'cancelled',
                    confirmText: 'Yes, confirm',
                }).then(function (reason) {
                    if (reason !== null) { postStatus(status, reason); }
                });
            } else {
                SHF.confirm({
                    title: title,
                    text: 'The loan will be reactivated.',
                    icon: 'question',
                    confirmText: 'Yes, confirm',
                }).then(function (ok) {
                    if (ok) { postStatus(status, ''); }
                });
            }
        });

        function postStatus(status, reason) {
            $.post(C.updateStatusUrl, {
                _token: csrf,
                status: status,
                reason: reason,
            }).done(function () {
                location.reload();
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update status.';
                SHF.alert({ icon: 'error', title: msg });
            });
        }

        /* ───────── Remarks ───────── */
        function loadRemarks() {
            var $list = $('#lsRemarksList');
            if (!$list.length || !C.remarksIndexUrl) { return; }

            $.get(C.remarksIndexUrl).done(function (r) {
                var remarks = (r && r.remarks) || [];
                var $count = $('#lsRemarksCount');
                if (!remarks.length) {
                    $list.html('<span class="ls-muted">No remarks yet.</span>');
                    $count.hide();
                    return;
                }
                $count.text(remarks.length).show();
                var html = remarks.map(function (rm) {
                    return '<div class="ls-remark-item">'
                        + '<div class="ls-remark-head">'
                        + '<strong>' + esc(rm.user_name) + '</strong>'
                        + '<span class="ls-remark-time">' + esc(rm.created_at) + '</span>'
                        + '</div>'
                        + '<p class="ls-remark-body">' + esc(rm.remark) + '</p>'
                        + '</div>';
                }).join('');
                $list.html(html);
            }).fail(function () {
                $list.html('<span class="ls-muted">Could not load remarks.</span>');
            });
        }
        loadRemarks();

        $('#lsRemarkForm').on('submit', function (e) {
            e.preventDefault();
            var $input = $('#lsRemarkInput');
            var text = ($input.val() || '').trim();
            if (!text) {
                $input.addClass('is-invalid').trigger('focus');
                return;
            }
            $input.removeClass('is-invalid');
            $.post(C.remarksStoreUrl, { _token: csrf, remark: text }).done(function (r) {
                if (r && r.success) {
                    $input.val('');
                    loadRemarks();
                }
            }).fail(function () {
                SHF.alert({ icon: 'error', title: 'Could not post remark' });
            });
        });

        function esc(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    });
})(window.jQuery);
