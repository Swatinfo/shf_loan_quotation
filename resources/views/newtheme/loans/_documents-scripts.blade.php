<script>
$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    function updateProgress(progress) {
        if (!progress) return;
        $('.shf-doc-progress-bar').css('width', progress.percentage + '%');
        $('.shf-doc-progress-text').text(progress.resolved + '/' + progress.total + ' (' + progress.percentage + '%)');
    }

    function updateDocRow($row, doc) {
        if (!$row.length || !doc) return;
        var status = doc.status;
        var isResolved = (status === 'received' || status === 'waived');

        // Update data attribute
        $row.data('current-status', status).attr('data-current-status', status);

        // Swap row CSS classes
        $row.removeClass('shf-doc-received shf-doc-pending shf-doc-rejected');
        if (isResolved) {
            $row.addClass('shf-doc-received');
        } else if (status === 'rejected') {
            $row.addClass('shf-doc-rejected');
        } else {
            $row.addClass('shf-doc-pending');
        }

        // Update checkbox
        var $cb = $row.find('.shf-doc-toggle');
        $cb.prop('checked', status === 'received').prop('disabled', false);

        // Update document name strikethrough
        var $nameContainer = $row.find('.flex-grow-1').first();
        var $name = $nameContainer.children('span').first();
        if (status === 'received') {
            $name.addClass('text-decoration-line-through text-muted');
        } else {
            $name.removeClass('text-decoration-line-through text-muted');
        }

        // Update badge
        $nameContainer.find('.shf-badge').remove();
        if (status === 'rejected') {
            $name.after('<span class="shf-badge shf-badge-red shf-text-2xs">Rejected</span>');
        } else if (status === 'waived') {
            $name.after('<span class="shf-badge shf-badge-orange shf-text-2xs">Waived</span>');
        } else if (status === 'received') {
            $name.after('<span class="shf-badge shf-badge-green shf-text-2xs">Collected</span>');
        }

        // Update received date info line
        $nameContainer.find('small.text-success').remove();
        $nameContainer.find('small.text-danger').not('.shf-badge').filter(function() {
            return !$(this).closest('.shf-doc-actions').length;
        }).remove();
        if (status === 'received' && doc.received_date) {
            var dateHtml = '<small class="text-success shf-text-2xs d-block">' +
                '<svg class="shf-icon-2xs" style="vertical-align:-1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' +
                doc.received_date + (doc.received_by ? ' by ' + doc.received_by : '') +
                '</small>';
            var $gu = $nameContainer.find('small.text-muted.shf-text-2xs').first();
            if ($gu.length) {
                $gu.after(dateHtml);
            } else {
                $nameContainer.append(dateHtml);
            }
        }

        // Show/hide waive button (hide when received or waived)
        var $waiveBtn = $row.find('.shf-doc-action[data-status="waived"]');
        if (isResolved) {
            $waiveBtn.hide();
        } else {
            $waiveBtn.show();
        }
    }

    function scrollToNextPending($afterRow) {
        var $pending;
        if ($afterRow && $afterRow.length) {
            $pending = $afterRow.closest('.col-xl-6').nextAll().find('.shf-doc-pending').first();
        }
        if (!$pending || !$pending.length) {
            $pending = $('.shf-doc-pending').first();
        }
        if ($pending.length) {
            setTimeout(function() {
                $pending[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $pending.addClass('shf-doc-highlight');
                setTimeout(function() { $pending.removeClass('shf-doc-highlight'); }, 1500);
            }, 300);
        }
    }

    function handleDocResponse(r, $row) {
        if (r.success && r.stage_advanced) {
            Swal.fire({
                title: 'All documents collected!',
                text: 'Document Collection stage completed. Moving to next stage.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(function() {
                window.location.href = '{{ route("loans.stages", $loan) }}';
            });
            return;
        }
        if (r.success && r.document && $row) {
            updateDocRow($row, r.document);
            updateProgress(r.progress);
            if (r.document.status === 'received' || r.document.status === 'waived') {
                scrollToNextPending($row);
            }
        } else if (r.success) {
            location.reload();
        }
    }

    // Whole row clickable — toggles received/pending
    $(document).on('click', '.shf-doc-row', function() {
        var $cb = $(this).find('.shf-doc-toggle');
        if (!$cb.length) return;
        var $row = $(this);
        var url = $row.data('toggle-url');
        var currentStatus = $row.data('current-status');
        var newStatus = (currentStatus === 'received') ? 'pending' : 'received';
        $cb.prop('disabled', true);
        $.post(url, { _token: csrfToken, status: newStatus })
            .done(function(r) { handleDocResponse(r, $row); })
            .fail(function() { $cb.prop('disabled', false); });
    });

    // Checkbox direct change (when clicked directly via stopPropagation)
    $(document).on('change', '.shf-doc-toggle', function() {
        var $cb = $(this);
        var $row = $cb.closest('.shf-doc-item');
        var url = $cb.data('url');
        var status = $cb.is(':checked') ? 'received' : 'pending';
        $cb.prop('disabled', true);
        $.post(url, { _token: csrfToken, status: status })
            .done(function(r) { handleDocResponse(r, $row); })
            .fail(function() { $cb.prop('disabled', false); });
    });

    // Waive button
    $(document).on('click', '.shf-doc-action', function() {
        var url = $(this).data('url');
        var status = $(this).data('status');
        var $btn = $(this);
        var $row = $btn.closest('.shf-doc-item');
        $btn.prop('disabled', true);
        $.post(url, { _token: csrfToken, status: status })
            .done(function(r) { handleDocResponse(r, $row); })
            .fail(function() { $btn.prop('disabled', false); });
    });

    // Remove document
    $(document).on('click', '.shf-doc-remove', function() {
        var url = $(this).data('url');
        Swal.fire({
            title: 'Remove document?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Remove'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({ url: url, method: 'DELETE', data: { _token: csrfToken } })
                    .done(function() { location.reload(); });
            }
        });
    });

    // Upload file
    $(document).on('change', '.shf-doc-upload-input', function() {
        var $input = $(this);
        var file = $input[0].files[0];
        if (!file) return;

        // 10MB limit
        if (file.size > 10 * 1024 * 1024) {
            Swal.fire('File too large', 'Maximum file size is 10 MB.', 'error');
            $input.val('');
            return;
        }

        var url = $input.data('url');
        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', csrfToken);

        $input.closest('.shf-doc-actions').find('label').addClass('opacity-50');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(r) {
            if (r.success) location.reload();
        }).fail(function(xhr) {
            var msg = 'Upload failed.';
            if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.file) {
                msg = xhr.responseJSON.errors.file[0];
            }
            Swal.fire('Upload Error', msg, 'error');
            $input.closest('.shf-doc-actions').find('label').removeClass('opacity-50');
            $input.val('');
        });
    });

    // Delete file (keeps document record)
    $(document).on('click', '.shf-doc-delete-file', function() {
        var url = $(this).data('url');
        Swal.fire({
            title: 'Delete uploaded file?',
            text: 'The document record will remain, only the file will be removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete File'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({ url: url, method: 'DELETE', data: { _token: csrfToken } })
                    .done(function(r) { if (r.success) location.reload(); });
            }
        });
    });

    // On load: scroll to first pending document
    scrollToNextPending(null);

    // Add document
    $('#addDocForm').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        if (!SHF.validateForm($form, {
            document_name_en: { required: true, maxlength: 255, label: 'Document Name (English)' },
            document_name_gu: { maxlength: 255, label: 'Document Name (Gujarati)' }
        })) return;
        $.post($form.attr('action'), $form.serialize() + '&_token=' + csrfToken)
            .done(function(r) { if (r.success) location.reload(); })
            .fail(function(xhr) { Swal.fire('Error', xhr.responseJSON?.error || 'Failed to add document', 'error'); });
    });
});
</script>
