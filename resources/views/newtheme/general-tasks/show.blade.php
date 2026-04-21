@extends('newtheme.layouts.app')

@section('title', 'Task #' . $task->id . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/task-show.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $statusMap = [
        'pending' => 'dark',
        'in_progress' => 'blue',
        'completed' => 'green',
        'cancelled' => 'red',
    ];
    $priorityMap = [
        'low' => 'dark',
        'normal' => 'blue',
        'high' => 'orange',
        'urgent' => 'red',
    ];
    $statusInfo = \App\Models\GeneralTask::STATUS_LABELS[$task->status] ?? ['label' => ucfirst($task->status)];
    $priorityInfo = \App\Models\GeneralTask::PRIORITY_LABELS[$task->priority] ?? ['label' => ucfirst($task->priority)];
    $statusTone = $statusMap[$task->status] ?? 'dark';
    $priorityTone = $priorityMap[$task->priority] ?? 'dark';

    $isActive = in_array($task->status, [\App\Models\GeneralTask::STATUS_PENDING, \App\Models\GeneralTask::STATUS_IN_PROGRESS]);
    $daysUntil = ($task->due_date && $isActive) ? (int) today()->diffInDays($task->due_date, false) : null;
    $dueBadge = null;
    if ($task->due_date && $isActive) {
        if ($daysUntil < 0) {
            $dueBadge = ['red', 'Overdue by ' . abs($daysUntil) . ' ' . (abs($daysUntil) === 1 ? 'day' : 'days')];
        } elseif ($daysUntil === 0) {
            $dueBadge = ['orange', 'Due Today'];
        } elseif ($daysUntil === 1) {
            $dueBadge = ['orange', 'Due Tomorrow'];
        } elseif ($daysUntil === 2) {
            $dueBadge = ['blue', 'Due in 2 days'];
        }
    }

    $assigneeName = (! $task->assigned_to || $task->assigned_to === $task->created_by)
        ? 'Self'
        : ($task->assignee?->name ?? '—');

    $canEdit = $task->isEditableBy(auth()->user());
    $canDelete = $task->isDeletableBy(auth()->user());
    $canChangeStatus = $task->canChangeStatus(auth()->user());
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('general-tasks.index') }}">Tasks</a>
                    <span class="sep">/</span>
                    <span>T-{{ $task->id }}</span>
                </div>
                <h1>{{ $task->title }}</h1>
                <div class="sub">
                    @if ($task->due_date)
                        Due {{ $task->due_date->format('d M Y') }}
                    @endif
                    <span class="badge {{ $priorityTone }}" style="margin-left:8px;vertical-align:middle;">{{ $priorityInfo['label'] }}</span>
                    <span class="badge {{ $statusTone }}" style="margin-left:4px;vertical-align:middle;">{{ $statusInfo['label'] }}</span>
                    @if ($task->loan)
                        <span style="margin-left:8px;">·
                            <a href="{{ route('loans.show', $task->loan_detail_id) }}" style="color:inherit;text-decoration:underline;">
                                #{{ $task->loan->loan_number }}
                            </a>
                        </span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('general-tasks.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                @if ($canEdit)
                    <button type="button" class="btn primary" id="tsEditBtn">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </button>
                @endif
            </div>
        </div>
    </header>

    <main class="content">
        <div class="grid c-main ts-grid">
            {{-- ===== Left column ===== --}}
            <div>
                {{-- Description --}}
                <div class="card">
                    <div class="card-hd"><div class="t">Description</div></div>
                    <div class="card-bd">
                        @if ($task->description)
                            <p class="ts-desc">{{ $task->description }}</p>
                        @else
                            <p class="ts-desc ts-desc-empty">No description provided.</p>
                        @endif

                        @if ($task->loan)
                            <div class="ts-linked">
                                <span class="ts-linked-label">Linked Loan</span>
                                <a href="{{ route('loans.show', $task->loan_detail_id) }}" class="ts-linked-link">
                                    <strong>#{{ $task->loan->loan_number }}</strong>
                                    @if ($task->loan->application_number)
                                        <span class="ts-muted"> / App: {{ $task->loan->application_number }}</span>
                                    @endif
                                    <span class="ts-muted"> — {{ $task->loan->customer_name }}</span>
                                    @if ($task->loan->bank_name)
                                        <span class="ts-muted"> ({{ $task->loan->bank_name }})</span>
                                    @endif
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Status control --}}
                @if ($canChangeStatus)
                    <div class="card mt-4">
                        <div class="card-hd"><div class="t">Change Status</div></div>
                        <div class="card-bd">
                            <div class="ts-status-row">
                                @foreach (\App\Models\GeneralTask::STATUS_LABELS as $statusKey => $info)
                                    @if ($statusKey === 'cancelled' && ! $canEdit)
                                        @continue
                                    @endif
                                    <button type="button"
                                        class="btn sm ts-status-btn ts-status-btn--{{ str_replace('_', '-', $statusKey) }} {{ $task->status === $statusKey ? 'is-current' : '' }}"
                                        data-status="{{ $statusKey }}"
                                        {{ $task->status === $statusKey ? 'disabled' : '' }}>
                                        {{ $info['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Comments --}}
                <div class="card mt-4">
                    <div class="card-hd">
                        <div class="t">Comments <span class="sub">({{ $task->comments->count() }})</span></div>
                    </div>
                    <div class="card-bd">
                        @forelse ($task->comments as $comment)
                            <div class="team-row ts-comment">
                                <div class="avatar xs">{{ strtoupper(substr($comment->user?->name ?? '?', 0, 1)) }}</div>
                                <div class="ts-comment-body">
                                    <div class="ts-comment-meta">
                                        <strong>{{ $comment->user?->name ?? 'Unknown' }}</strong>
                                        <span class="ts-muted">{{ $comment->created_at->diffForHumans() }}</span>
                                        @if ($comment->user_id === auth()->id())
                                            <form method="POST" action="{{ route('general-tasks.comments.destroy', [$task, $comment]) }}" class="ts-comment-del-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ts-icon-btn" title="Delete" aria-label="Delete comment">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <p class="ts-comment-text">{{ $comment->body }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="ts-muted ts-empty">No comments yet.</div>
                        @endforelse

                        @if ($task->isVisibleTo(auth()->user()))
                            <form method="POST" action="{{ route('general-tasks.comments.store', $task) }}" class="ts-comment-form" id="tsCommentForm">
                                @csrf
                                <textarea name="body" class="input" rows="3" placeholder="Add a comment…" maxlength="5000"></textarea>
                                <div class="ts-comment-actions">
                                    <button type="submit" class="btn primary sm">
                                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                        Post comment
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== Right column ===== --}}
            <aside>
                <div class="card">
                    <div class="card-hd"><div class="t">Details</div></div>
                    <div class="card-bd">
                        <div class="ts-kv">
                            <div><span>Created by</span><span>{{ $task->creator?->name ?? '—' }}</span></div>
                            <div><span>Assigned to</span><span>{{ $assigneeName }}</span></div>
                            <div><span>Priority</span><span><span class="badge {{ $priorityTone }}">{{ $priorityInfo['label'] }}</span></span></div>
                            <div><span>Status</span><span id="tsStatusCell"><span class="badge {{ $statusTone }}">{{ $statusInfo['label'] }}</span></span></div>
                            <div><span>Due date</span><span>
                                @if ($task->due_date)
                                    {{ $task->due_date->format('d M Y') }}
                                    @if ($dueBadge)
                                        <span class="badge {{ $dueBadge[0] }}" style="margin-left:6px;">{{ $dueBadge[1] }}</span>
                                    @endif
                                @else
                                    <span class="ts-muted">—</span>
                                @endif
                            </span></div>
                            <div><span>Created</span><span>{{ $task->created_at->format('d M Y, h:i A') }}</span></div>
                            @if ($task->completed_at)
                                <div><span>Completed</span><span>{{ $task->completed_at->format('d M Y, h:i A') }}</span></div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($task->loan)
                    <div class="card mt-4">
                        <div class="card-hd"><div class="t">Linked file</div></div>
                        <div class="card-bd">
                            <a href="{{ route('loans.show', $task->loan_detail_id) }}" class="ts-linked-file">
                                <strong>#{{ $task->loan->loan_number }}</strong>
                                <div class="ts-muted text-xs">
                                    {{ $task->loan->customer_name }}
                                    @if ($task->loan->bank_name)
                                        · {{ $task->loan->bank_name }}
                                    @endif
                                </div>
                            </a>
                        </div>
                    </div>
                @endif

                @if ($canDelete)
                    <form method="POST" action="{{ route('general-tasks.destroy', $task) }}" class="mt-4" id="tsDeleteForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger" style="width:100%;">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete Task
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </main>

    {{-- ===== Edit modal (reuses gt-modal styles from shf-modals.css) ===== --}}
    @if ($canEdit)
        <div id="tsEditBackdrop" class="gt-modal-backdrop" style="display:none;"></div>
        <div id="tsEditModal" class="gt-modal" role="dialog" aria-label="Edit Task" style="display:none;">
            <form id="tsEditForm" method="POST" action="{{ route('general-tasks.update', $task) }}" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="gt-modal-hd">
                    <h3>Edit Task</h3>
                    <button type="button" class="icon-btn" id="tsEditClose" aria-label="Close">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="gt-modal-bd">
                    <div class="gt-field">
                        <label class="lbl" for="tsEditTitle">Title <span style="color:var(--red);">*</span></label>
                        <input type="text" name="title" id="tsEditTitle" class="input shf-input" maxlength="255"
                            value="{{ old('title', $task->title) }}">
                    </div>

                    <div class="gt-field">
                        <label class="lbl" for="tsEditDescription">Description</label>
                        <textarea name="description" id="tsEditDescription" class="input shf-input" rows="3" maxlength="5000"
                            style="height:auto;padding:10px;line-height:1.45;">{{ old('description', $task->description) }}</textarea>
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="tsEditAssignee">Assign To</label>
                            <select name="assigned_to" id="tsEditAssignee" class="input shf-input">
                                <option value="">— Self (no one) —</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" {{ (string) old('assigned_to', $task->assigned_to) === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="tsEditPriority">Priority <span style="color:var(--red);">*</span></label>
                            <select name="priority" id="tsEditPriority" class="input shf-input">
                                @foreach (\App\Models\GeneralTask::PRIORITY_LABELS as $key => $info)
                                    <option value="{{ $key }}" {{ old('priority', $task->priority) === $key ? 'selected' : '' }}>{{ $info['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="tsEditDueDate">Due Date <span style="color:var(--red);">*</span></label>
                            <input type="text" name="due_date" id="tsEditDueDate" class="input shf-input shf-datepicker"
                                placeholder="dd/mm/yyyy" autocomplete="off"
                                value="{{ old('due_date', $task->due_date?->format('d/m/Y')) }}">
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="tsEditLoanSearch">Link to Loan (optional)</label>
                            <input type="text" id="tsEditLoanSearch" class="input shf-input"
                                placeholder="Loan #, customer, bank…" autocomplete="off"
                                value="{{ $taskEditData['loan_label'] ?? '' }}">
                            <input type="hidden" name="loan_detail_id" id="tsEditLoanId" value="{{ $task->loan_detail_id }}">
                            <div class="gt-loan-results" id="tsEditLoanResults"></div>
                        </div>
                    </div>
                </div>

                <div class="gt-modal-ft">
                    <button type="button" class="btn" id="tsEditCancel">Cancel</button>
                    <button type="submit" class="btn primary" id="tsEditSave">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Save
                    </button>
                </div>
            </form>
        </div>
    @endif
@endsection

@push('page-scripts')
    <script>
        window.__TS = {
            statusUrl: @json(route('general-tasks.update-status', $task)),
            indexUrl: @json(route('general-tasks.index')),
            searchLoansUrl: @json(route('general-tasks.search-loans')),
            canEdit: @json($canEdit),
            canDelete: @json($canDelete),
        };
    </script>
    <script src="{{ asset('newtheme/pages/task-show.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
