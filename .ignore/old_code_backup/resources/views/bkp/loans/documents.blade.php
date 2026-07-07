@extends('layouts.app')
@section('title', 'Loan Documents — SHF')

@section('header')
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h2 class="font-display fw-semibold text-white shf-page-title"><svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg> Documents — {{ $loan->loan_number }}</h2>
        <a href="{{ route('loans.show', $loan) }}" class="btn-accent-outline btn-accent-sm btn-accent-outline-white"><svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg> Back</a>
    </div>
@endsection

@section('content')
@php
    $docsLocked = !in_array($loan->status, ['active', 'on_hold']) || $loan->stageAssignments()
        ->where('parent_stage_key', 'parallel_processing')
        ->where('status', 'completed')
        ->exists();
@endphp
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5">

        @if($docsLocked)
            <div class="alert alert-warning mb-3 shf-text-sm">
                <strong>Documents are locked.</strong> Verification stages have started — documents can no longer be modified.
            </div>
        @endif

        {{-- Progress Bar (sticky) --}}
        <div class="card border-0 shadow-sm mb-4" style="position:sticky; top:0; z-index:100;">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Collection Progress</strong>
                    <span class="shf-doc-progress-text">{{ $progress['resolved'] }}/{{ $progress['total'] }} ({{ $progress['percentage'] }}%)</span>
                </div>
                <div class="progress shf-progress-md">
                    <div class="progress-bar bg-success shf-doc-progress-bar" style="width: {{ $progress['percentage'] }}%"></div>
                </div>
                @if($progress['rejected'] > 0)
                    <small class="text-danger mt-1 d-block">{{ $progress['rejected'] }} document(s) rejected</small>
                @endif
            </div>
        </div>

        {{-- Document List --}}
        <div class="shf-card mb-4">
            <div class="p-2 p-sm-4 row g-3">
                @forelse($documents as $doc)
                    <div class="col-xl-6">
                    <div class="shf-doc-item d-flex align-items-center gap-2 {{ !$docsLocked ? 'shf-doc-row' : '' }} {{ $doc->isResolved() ? 'shf-doc-received' : ($doc->status === 'rejected' ? 'shf-doc-rejected' : 'shf-doc-pending') }}"
                         data-doc-id="{{ $doc->id }}"
                         @if(auth()->user()->hasPermission('manage_loan_documents') && !$docsLocked)
                             data-toggle-url="{{ route('loans.documents.status', [$loan, $doc]) }}"
                             data-current-status="{{ $doc->status }}"
                         @endif>
                        {{-- Checkbox --}}
                        @if(auth()->user()->hasPermission('manage_loan_documents'))
                            <div class="flex-shrink-0" onclick="event.stopPropagation();">
                                <input type="checkbox" class="shf-checkbox shf-doc-toggle"
                                       {{ $doc->isReceived() ? 'checked' : '' }}
                                       data-url="{{ route('loans.documents.status', [$loan, $doc]) }}">
                            </div>
                        @endif

                        {{-- Document info --}}
                        <div class="flex-grow-1" style="min-width:0;">
                            <span class="{{ $doc->isReceived() ? 'text-decoration-line-through text-muted' : '' }}">{{ $doc->document_name_en }}</span>
                            @if($doc->is_required) <small class="text-danger fw-bold">*</small> @endif
                            @if($doc->status === 'rejected')
                                <span class="shf-badge shf-badge-red shf-text-2xs">Rejected</span>
                            @elseif($doc->status === 'waived')
                                <span class="shf-badge shf-badge-orange shf-text-2xs">Waived</span>
                            @elseif($doc->isReceived())
                                <span class="shf-badge shf-badge-green shf-text-2xs">Collected</span>
                            @endif
                            @if($doc->document_name_gu)
                                <small class="text-muted shf-text-2xs d-block">{{ $doc->document_name_gu }}</small>
                            @endif
                            @if($doc->status === 'received')
                                <small class="text-success shf-text-2xs d-block">
                                    <svg class="shf-icon-2xs" style="vertical-align:-1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $doc->received_date?->format('d M Y') }}{{ $doc->receivedByUser ? ' by ' . $doc->receivedByUser->name : '' }}
                                </small>
                            @elseif($doc->status === 'rejected' && $doc->rejected_reason)
                                <small class="text-danger shf-text-2xs d-block">{{ $doc->rejected_reason }}</small>
                            @endif
                            @if($doc->hasFile())
                                <small class="d-flex align-items-center gap-1 shf-text-2xs flex-wrap">
                                    <svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span class="text-muted">{{ $doc->file_name }} ({{ $doc->formattedFileSize() }})</span>
                                    @if(auth()->user()->hasPermission('download_loan_documents'))
                                        <a href="{{ route('loans.documents.download', [$loan, $doc]) }}" class="text-primary shf-text-2xs" onclick="event.stopPropagation();">Download</a>
                                    @endif
                                    @if(auth()->user()->hasPermission('delete_loan_files'))
                                        <button class="btn p-0 text-danger shf-doc-delete-file shf-text-2xs shf-btn-minimal" data-url="{{ route('loans.documents.deleteFile', [$loan, $doc]) }}" onclick="event.stopPropagation();">Delete</button>
                                    @endif
                                </small>
                            @endif
                        </div>

                        {{-- Action buttons (right-aligned, compact) --}}
                        @if(!$docsLocked)
                        <div class="flex-shrink-0 d-flex gap-1 shf-doc-actions">
                            @if(auth()->user()->hasPermission('upload_loan_documents'))
                                <label class="btn-accent-sm shf-text-2xs shf-upload-label" title="Upload file" onclick="event.stopPropagation();">
                                    <svg class="shf-icon-xs shf-icon-inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> {{ $doc->hasFile() ? 'Replace' : 'Upload' }}
                                    <input type="file" class="d-none shf-doc-upload-input" data-url="{{ route('loans.documents.upload', [$loan, $doc]) }}" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                                </label>
                            @endif
                            @if(auth()->user()->hasPermission('manage_loan_documents'))
                                @if(!in_array($doc->status, ['received', 'waived']))
                                    <button class="btn-accent-sm shf-doc-action shf-text-2xs shf-btn-warning" data-url="{{ route('loans.documents.status', [$loan, $doc]) }}" data-status="waived" onclick="event.stopPropagation();">
                                        <svg class="shf-icon-xs shf-icon-inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg> Waive
                                    </button>
                                @endif
                            @endif
                            @if(auth()->user()->hasPermission('delete_loan_files'))
                                <button class="btn-accent-sm shf-doc-remove shf-text-2xs shf-btn-danger" data-url="{{ route('loans.documents.destroy', [$loan, $doc]) }}" onclick="event.stopPropagation();">
                                    <svg class="shf-icon-xs shf-icon-inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> Remove
                                </button>
                            @endif
                        </div>
                        @endif
                    </div>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted py-4">No documents yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Add Document --}}
        @if(!$docsLocked && auth()->user()->hasPermission('manage_loan_documents'))
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Add Document</h6>
                    <form id="addDocForm" action="{{ route('loans.documents.store', $loan) }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-5">
                                <input type="text" name="document_name_en" class="shf-input shf-input-sm" placeholder="Document name (English)" required>
                            </div>
                            <div class="col-sm-4">
                                <input type="text" name="document_name_gu" class="shf-input shf-input-sm" placeholder="Name (Gujarati) — optional">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn-accent-sm w-100"><svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
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
@endpush
