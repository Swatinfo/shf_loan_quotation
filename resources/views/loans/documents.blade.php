@extends('layouts.app')

@section('header')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('loans.show', $loan) }}" style="color: rgba(255,255,255,0.4); text-decoration: none;">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">
            Documents — {{ $loan->loan_number }}
        </h2>
    </div>
@endsection

@section('content')
@php
    $docsLocked = $loan->stageAssignments()
        ->where('parent_stage_key', 'parallel_processing')
        ->where('status', 'completed')
        ->exists();
@endphp
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5" style="max-width: 48rem;">

        @if($docsLocked)
            <div class="alert alert-warning mb-3" style="font-size:0.85rem;">
                <strong>Documents are locked.</strong> Verification stages have started — documents can no longer be modified.
            </div>
        @endif

        {{-- Progress Bar --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Collection Progress</strong>
                    <span class="shf-doc-progress-text">{{ $progress['resolved'] }}/{{ $progress['total'] }} ({{ $progress['percentage'] }}%)</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success shf-doc-progress-bar" style="width: {{ $progress['percentage'] }}%"></div>
                </div>
                @if($progress['rejected'] > 0)
                    <small class="text-danger mt-1 d-block">{{ $progress['rejected'] }} document(s) rejected</small>
                @endif
            </div>
        </div>

        {{-- Document List --}}
        <div class="shf-card mb-4">
            <div class="p-4">
                @forelse($documents as $doc)
                    <div class="shf-doc-item d-flex align-items-center gap-3 {{ !$docsLocked ? 'shf-doc-row' : '' }} {{ $doc->isResolved() ? 'shf-doc-received' : ($doc->status === 'rejected' ? 'shf-doc-rejected' : 'shf-doc-pending') }}"
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
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="{{ $doc->isReceived() ? 'text-decoration-line-through text-muted' : '' }}">{{ $doc->document_name_en }}</span>
                                @if($doc->is_required) <small class="text-danger fw-bold">*</small> @endif
                                @if($doc->status === 'rejected')
                                    <span class="shf-badge shf-badge-red" style="font-size:0.6rem;">Rejected</span>
                                @elseif($doc->status === 'waived')
                                    <span class="shf-badge shf-badge-orange" style="font-size:0.6rem;">Waived</span>
                                @elseif($doc->isReceived())
                                    <span class="shf-badge shf-badge-green" style="font-size:0.6rem;">Collected</span>
                                @endif
                            </div>
                            @if($doc->document_name_gu)
                                <small class="text-muted" style="font-size:0.75rem;">{{ $doc->document_name_gu }}</small>
                            @endif
                            @if($doc->status === 'received')
                                <small class="text-success d-block" style="font-size:0.7rem;">
                                    <svg style="width:10px;height:10px;display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $doc->received_date?->format('d M Y') }}{{ $doc->receivedByUser ? ' by ' . $doc->receivedByUser->name : '' }}
                                </small>
                            @elseif($doc->status === 'rejected' && $doc->rejected_reason)
                                <small class="text-danger d-block" style="font-size:0.7rem;">{{ $doc->rejected_reason }}</small>
                            @endif
                            {{-- File attachment info --}}
                            @if($doc->hasFile())
                                <small class="d-flex align-items-center gap-1 mt-1" style="font-size:0.7rem;">
                                    <svg style="width:10px;height:10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span class="text-muted">{{ $doc->file_name }} ({{ $doc->formattedFileSize() }})</span>
                                    @if(auth()->user()->hasPermission('download_loan_documents'))
                                        <a href="{{ route('loans.documents.download', [$loan, $doc]) }}" class="text-primary" style="font-size:0.65rem;" onclick="event.stopPropagation();">Download</a>
                                    @endif
                                    @if(auth()->user()->hasPermission('delete_loan_files'))
                                        <button class="btn p-0 text-danger shf-doc-delete-file" style="font-size:0.65rem; border:none; background:none;" data-url="{{ route('loans.documents.deleteFile', [$loan, $doc]) }}" onclick="event.stopPropagation();">Delete File</button>
                                    @endif
                                </small>
                            @endif
                        </div>

                        {{-- Action buttons --}}
                        @if(!$docsLocked)
                        <div class="d-flex gap-1 flex-shrink-0 shf-doc-actions">
                            @if(auth()->user()->hasPermission('upload_loan_documents'))
                                <label class="btn-accent-sm" style="font-size:0.65rem; cursor:pointer; margin:0;" title="Upload file" onclick="event.stopPropagation();">
                                    <svg style="width:10px;height:10px;display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    {{ $doc->hasFile() ? 'Replace' : 'Upload' }}
                                    <input type="file" class="d-none shf-doc-upload-input" data-url="{{ route('loans.documents.upload', [$loan, $doc]) }}" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                                </label>
                            @endif
                            @if(auth()->user()->hasPermission('manage_loan_documents'))
                                @if(!in_array($doc->status, ['received', 'waived']))
                                    <button class="btn-accent-sm shf-doc-action" style="background:linear-gradient(135deg,#d97706,#f59e0b);font-size:0.65rem;" data-url="{{ route('loans.documents.status', [$loan, $doc]) }}" data-status="waived" title="Waive this document" onclick="event.stopPropagation();">
                                        Waive
                                    </button>
                                @endif
                                <button class="btn-accent-sm shf-doc-remove" style="background:linear-gradient(135deg,#dc2626,#ef4444);font-size:0.65rem;" data-url="{{ route('loans.documents.destroy', [$loan, $doc]) }}" title="Remove this document" onclick="event.stopPropagation();">
                                    Remove
                                </button>
                            @endif
                        </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No documents yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Add Document --}}
        @if(auth()->user()->hasPermission('manage_loan_documents'))
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
                                <button type="submit" class="btn-accent-sm w-100">+ Add</button>
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

    function handleDocResponse(r) {
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
        } else if (r.success) {
            location.reload();
        }
    }

    // Whole row clickable — toggles received/pending
    $(document).on('click', '.shf-doc-row', function() {
        var $cb = $(this).find('.shf-doc-toggle');
        if (!$cb.length) return;
        var url = $(this).data('toggle-url');
        var currentStatus = $(this).data('current-status');
        var newStatus = (currentStatus === 'received') ? 'pending' : 'received';
        $cb.prop('disabled', true);
        $.post(url, { _token: csrfToken, status: newStatus })
            .done(handleDocResponse)
            .fail(function() { $cb.prop('disabled', false); });
    });

    // Checkbox direct change (when clicked directly via stopPropagation)
    $(document).on('change', '.shf-doc-toggle', function() {
        var url = $(this).data('url');
        var status = $(this).is(':checked') ? 'received' : 'pending';
        $(this).prop('disabled', true);
        $.post(url, { _token: csrfToken, status: status })
            .done(handleDocResponse)
            .fail(function() { $(this).prop('disabled', false); }.bind(this));
    });

    // Waive button
    $(document).on('click', '.shf-doc-action', function() {
        var url = $(this).data('url');
        var status = $(this).data('status');
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(url, { _token: csrfToken, status: status }).done(function(r) {
            if (r.success) location.reload();
        }).fail(function() { $btn.prop('disabled', false); });
    });

    // Remove document
    $(document).on('click', '.shf-doc-remove', function() {
        var url = $(this).data('url');
        Swal.fire({
            title: 'Remove document?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
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
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Delete File'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({ url: url, method: 'DELETE', data: { _token: csrfToken } })
                    .done(function(r) { if (r.success) location.reload(); });
            }
        });
    });

    // Add document
    $('#addDocForm').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize() + '&_token=' + csrfToken)
            .done(function(r) { if (r.success) location.reload(); });
    });
});
</script>
@endpush
