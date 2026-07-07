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

            if (!window.Swal) {
                if (needsReason) {
                    var reason = prompt(title + '\nPlease enter a reason:');
                    if (!reason) { return; }
                    postStatus(status, reason);
                } else if (confirm(title)) {
                    postStatus(status, '');
                }
                return;
            }

            Swal.fire({
                title: title,
                input: needsReason ? 'textarea' : undefined,
                inputLabel: needsReason ? 'Reason (required)' : undefined,
                inputPlaceholder: needsReason ? 'Reason for ' + status.replace('_', ' ') + '…' : undefined,
                inputValidator: needsReason ? function (v) { if (!v) { return 'Please provide a reason'; } } : undefined,
                text: !needsReason ? 'The loan will be reactivated.' : undefined,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: status === 'cancelled' ? '#dc2626' : '#f15a29',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, confirm',
            }).then(function (result) {
                if (result.isConfirmed) {
                    postStatus(status, result.value || '');
                }
            });
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
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: msg, confirmButtonColor: '#f15a29' });
                } else {
                    alert(msg);
                }
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
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Could not post remark', confirmButtonColor: '#f15a29' });
                } else {
                    alert('Could not post remark.');
                }
            });
        });

        function esc(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    });
})(window.jQuery);
