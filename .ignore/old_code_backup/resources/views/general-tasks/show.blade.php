@extends('layouts.app')
@section('title', 'Task: ' . $task->title . ' — SHF')

@section('header')
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            Task #{{ $task->id }}
        </h2>
        <div class="d-flex gap-2">
            <a href="{{ route('general-tasks.index') }}" class="btn-accent-outline-white btn-accent-sm">
                <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            @if ($task->isEditableBy(auth()->user()))
                <button class="btn-accent btn-accent-sm" onclick='editTask(@json($taskEditData))'>
                    <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </button>
            @endif
        </div>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5">

        <div class="row g-4">
            {{-- Task Detail --}}
            <div class="col-lg-8">
                <div class="shf-section">
                    <div class="shf-section-header d-flex align-items-center justify-content-between">
                        <span class="shf-section-title">{{ $task->title }}</span>
                        <div class="d-flex gap-2">
                            {!! $task->priority_badge_html !!}
                            {!! $task->status_badge_html !!}
                        </div>
                    </div>
                    <div class="shf-section-body">
                        @if ($task->description)
                            <div class="mb-3">
                                <p class="mb-0" style="white-space: pre-line;">{{ $task->description }}</p>
                            </div>
                        @endif

                        @if ($task->loan)
                            <div class="mb-3 p-2 rounded" style="background: var(--light);">
                                <small class="text-muted d-block mb-1">Linked Loan</small>
                                <a href="{{ route('loans.show', $task->loan_detail_id) }}" class="text-decoration-none fw-medium">
                                    #{{ $task->loan->loan_number }}
                                    @if ($task->loan->application_number)
                                        / App: {{ $task->loan->application_number }}
                                    @endif
                                    — {{ $task->loan->customer_name }}
                                    @if ($task->loan->bank_name)
                                        ({{ $task->loan->bank_name }})
                                    @endif
                                </a>
                            </div>
                        @endif

                        {{-- Status Change --}}
                        @if ($task->canChangeStatus(auth()->user()))
                            <div class="mb-3 p-2 rounded border">
                                <small class="text-muted d-block mb-1">Change Status</small>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach (\App\Models\GeneralTask::STATUS_LABELS as $statusKey => $info)
                                        @if ($statusKey === 'cancelled' && !$task->isEditableBy(auth()->user()))
                                            @continue
                                        @endif
                                        <button class="btn-accent-sm {{ $task->status === $statusKey ? 'btn-accent' : 'btn-accent-outline' }} shf-status-btn"
                                                data-status="{{ $statusKey }}"
                                                {{ $task->status === $statusKey ? 'disabled' : '' }}>
                                            {{ $info['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Comments Section --}}
                <div class="shf-section mt-3">
                    <div class="shf-section-header">
                        <span class="shf-section-title">Comments ({{ $task->comments->count() }})</span>
                    </div>
                    <div class="shf-section-body">
                        @forelse ($task->comments as $comment)
                            <div class="d-flex gap-2 mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px; background:var(--accent); color:#fff; font-size:0.75rem; font-weight:600;">
                                        {{ strtoupper(substr($comment->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="small">{{ $comment->user?->name ?? 'Unknown' }}</strong>
                                            <span class="text-muted small ms-2">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if ($comment->user_id === auth()->id())
                                            <form method="POST" action="{{ route('general-tasks.comments.destroy', [$task, $comment]) }}" onsubmit="return confirm('Delete this comment?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
                                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <p class="mb-0 small" style="white-space: pre-line;">{{ $comment->body }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No comments yet.</p>
                        @endforelse

                        {{-- Add Comment --}}
                        @if ($task->isVisibleTo(auth()->user()))
                            <form method="POST" action="{{ route('general-tasks.comments.store', $task) }}" class="mt-3 pt-3 border-top">
                                @csrf
                                <div class="mb-2">
                                    <textarea name="body" class="shf-input" rows="2" placeholder="Add a comment..." required maxlength="5000">{{ old('body') }}</textarea>
                                    @error('body')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn-accent btn-accent-sm">
                                    <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    Comment
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sidebar Info --}}
            <div class="col-lg-4">
                <div class="shf-section">
                    <div class="shf-section-header">
                        <span class="shf-section-title">Details</span>
                    </div>
                    <div class="shf-section-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td class="text-muted" style="width:110px;">Created by</td>
                                <td class="fw-medium">{{ $task->creator?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Assigned to</td>
                                <td class="fw-medium">
                                    @if (!$task->assigned_to || $task->assigned_to === $task->created_by)
                                        <span class="text-muted">Self</span>
                                    @else
                                        {{ $task->assignee?->name ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td id="statusBadge">{!! $task->status_badge_html !!}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Priority</td>
                                <td>{!! $task->priority_badge_html !!}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Due date</td>
                                <td>
                                    @if ($task->due_date)
                                        @php
                                            $isActive = in_array($task->status, [\App\Models\GeneralTask::STATUS_PENDING, \App\Models\GeneralTask::STATUS_IN_PROGRESS]);
                                            $daysUntil = $isActive ? (int) today()->diffInDays($task->due_date, false) : null;
                                        @endphp
                                        {{ $task->due_date->format('d M Y') }}
                                        @if ($isActive && $daysUntil < 0)
                                            <br><span class="shf-badge shf-badge-red shf-text-2xs">Overdue by {{ abs($daysUntil) }} {{ abs($daysUntil) === 1 ? 'day' : 'days' }}</span>
                                        @elseif ($isActive && $daysUntil === 0)
                                            <br><span class="shf-badge shf-badge-orange shf-text-2xs">Due Today</span>
                                        @elseif ($isActive && $daysUntil === 1)
                                            <br><span class="shf-badge shf-badge-orange shf-text-2xs">Due Tomorrow</span>
                                        @elseif ($isActive && $daysUntil === 2)
                                            <br><span class="shf-badge shf-badge-blue shf-text-2xs">Due in 2 days</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Created</td>
                                <td>{{ $task->created_at->format('d M Y, h:i A') }}</td>
                            </tr>
                            @if ($task->completed_at)
                                <tr>
                                    <td class="text-muted">Completed</td>
                                    <td>{{ $task->completed_at->format('d M Y, h:i A') }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>

                @if ($task->isDeletableBy(auth()->user()))
                    <div class="mt-3">
                        <form method="POST" action="{{ route('general-tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete Task
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Edit Task Modal (reuse from index pattern) --}}
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px;">
            <form id="taskForm" method="POST" action="{{ route('general-tasks.update', $task) }}">
                @csrf
                @method('PUT')
                <div class="modal-header" style="background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title font-display">Edit Task / ટાસ્ક સુધારો</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    @if ($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="shf-form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="taskTitle" class="shf-input" required maxlength="255"
                            value="{{ old('title', $task->title) }}">
                    </div>
                    <div class="mb-3">
                        <label class="shf-form-label">Description</label>
                        <textarea name="description" id="taskDescription" class="shf-input" rows="3" maxlength="5000">{{ old('description', $task->description) }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="shf-form-label">Assign To</label>
                            <select name="assigned_to" id="taskAssignedTo" class="shf-input">
                                <option value="">Self (no one)</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" {{ (string) old('assigned_to', $task->assigned_to) === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="shf-form-label">Priority</label>
                            <select name="priority" id="taskPriority" class="shf-input">
                                @foreach (\App\Models\GeneralTask::PRIORITY_LABELS as $key => $info)
                                    <option value="{{ $key }}" {{ old('priority', $task->priority) === $key ? 'selected' : '' }}>{{ $info['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="shf-form-label">Due Date</label>
                            <input type="text" name="due_date" id="taskDueDate" class="shf-input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy"
                                value="{{ old('due_date', $task->due_date?->format('d/m/Y')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="shf-form-label">Link to Loan (optional)</label>
                            <input type="text" id="taskLoanSearch" class="shf-input" placeholder="Search loan #, app # or customer..." autocomplete="off">
                            <input type="hidden" name="loan_detail_id" id="taskLoanId">
                            <div id="taskLoanResults" class="position-relative">
                                <div id="taskLoanDropdown" class="dropdown-menu w-100 shadow" style="max-height:200px; overflow-y:auto;"></div>
                            </div>
                            <div id="taskLoanChip" class="d-none mt-2">
                                <span class="shf-badge shf-badge-blue shf-text-xs" id="taskLoanChipText"></span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="clearLoanLink()">&times; Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-accent-outline btn-accent-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent btn-accent-sm">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showToast(message, type) {
    type = type || 'info';
    var bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-primary');
    var $toast = $('<div class="alert alert-dismissible fade show position-fixed bottom-0 end-0 m-3 text-white ' + bgClass + '" style="z-index:9999;max-width:350px;">'
        + message
        + '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button></div>');
    $('body').append($toast);
    setTimeout(function() { $toast.alert('close'); }, 4000);
}

$(function() {
    @if ($errors->any())
        $('#taskModal').modal('show');
    @endif

    $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });

    // ── Edit Task form validation ──
    $('#taskForm').on('submit', function(e) {
        var valid = SHF.validateForm($(this), {
            title: { required: true, maxlength: 255 },
            description: { maxlength: 5000 }
        });
        if (!valid) { e.preventDefault(); return false; }
    });

    // ── Comment form validation ──
    $('form[action*="comments"]').not('[method=""]').on('submit', function(e) {
        if (!$(this).find('[name="_method"]').length || $(this).find('[name="_method"]').val() !== 'DELETE') {
            var valid = SHF.validateForm($(this), {
                body: { required: true, maxlength: 5000, label: 'Comment' }
            });
            if (!valid) { e.preventDefault(); return false; }
        }
    });

    // ── Status change buttons ──
    $('.shf-status-btn').on('click', function() {
        var btn = $(this);
        var newStatus = btn.data('status');
        $.ajax({
            url: '{{ route("general-tasks.update-status", $task) }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'PATCH', status: newStatus },
            success: function(resp) {
                window.location.href = '{{ route("general-tasks.index") }}';
            },
            error: function() { showToast('Failed to update status / સ્થિતિ અપડેટ કરવામાં નિષ્ફળ', 'error'); }
        });
    });

    // ── Loan Search Autocomplete ──
    var loanTimer;
    $('#taskLoanSearch').on('input', function() {
        clearTimeout(loanTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $('#taskLoanDropdown').removeClass('show').empty(); return; }
        loanTimer = setTimeout(function() {
            $.get('{{ route("general-tasks.search-loans") }}', { q: q }, function(loans) {
                var $dd = $('#taskLoanDropdown');
                if (!loans.length) {
                    $dd.html('<span class="dropdown-item text-muted">No loans found</span>').addClass('show');
                    return;
                }
                var html = '';
                loans.forEach(function(loan) {
                    var label = '#' + loan.loan_number;
                    if (loan.application_number) label += ' / App: ' + loan.application_number;
                    label += ' — ' + loan.customer_name;
                    if (loan.bank_name) label += ' (' + loan.bank_name + ')';
                    html += '<a class="dropdown-item shf-loan-pick" href="#" data-id="' + loan.id + '" data-label="' + label.replace(/"/g, '&quot;') + '">' + label + '</a>';
                });
                $dd.html(html).addClass('show');
            });
        }, 300);
    });

    $(document).on('click', '.shf-loan-pick', function(e) {
        e.preventDefault();
        $('#taskLoanId').val($(this).data('id'));
        $('#taskLoanSearch').val('').hide();
        $('#taskLoanChipText').text($(this).data('label'));
        $('#taskLoanChip').removeClass('d-none');
        $('#taskLoanDropdown').removeClass('show').empty();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#taskLoanResults, #taskLoanSearch').length) {
            $('#taskLoanDropdown').removeClass('show');
        }
    });
});

function editTask(taskData) {
    $('#taskTitle').val(taskData.title);
    $('#taskDescription').val(taskData.description || '');
    $('#taskAssignedTo').val(taskData.assigned_to || '');
    $('#taskPriority').val(taskData.priority);
    $('#taskDueDate').val(taskData.due_date_formatted || '');
    if (taskData.loan_detail_id) {
        $('#taskLoanId').val(taskData.loan_detail_id);
        $('#taskLoanSearch').hide();
        $('#taskLoanChipText').text(taskData.loan_label);
        $('#taskLoanChip').removeClass('d-none');
    } else {
        clearLoanLink();
    }
    var modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}

function clearLoanLink() {
    $('#taskLoanId').val('');
    $('#taskLoanSearch').val('').show();
    $('#taskLoanChip').addClass('d-none');
}
</script>
@endpush
