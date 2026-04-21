{{--
    Site-wide Create Task modal. Lives in the newtheme layout so the FAB
    (or any `data-shf-open="create-task"` button / `shf:open-create-task`
    dispatchEvent) can trigger it from any page.

    Validation is jQuery-driven — no HTML5 `required` attrs. Mirrors the
    legacy dashCreateTaskModal pattern: $.trim() checks, .is-invalid class,
    .shf-client-error inline messages, focus-first-invalid on submit.
--}}
@php
    $activeUsers = \App\Models\User::where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name']);
    $currentUserId = auth()->id();
@endphp

<div id="shfCreateTaskBackdrop" class="gt-modal-backdrop" style="display:none;"></div>
<div id="shfCreateTaskModal" class="gt-modal" role="dialog" aria-label="Create Task" style="display:none;">
    <form id="shfCreateTaskForm" method="POST" action="{{ route('general-tasks.store') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="_method" id="shfCreateTaskMethod" value="POST">

        <div class="gt-modal-hd">
            <h3 id="shfCreateTaskTitle">Create New Task</h3>
            <button type="button" class="icon-btn" id="shfCreateTaskClose" aria-label="Close">
                <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="gt-modal-bd">
            <div class="gt-field">
                <label class="lbl" for="shfCreateTaskTitleInput">Title <span style="color:var(--red);">*</span></label>
                <input type="text" name="title" id="shfCreateTaskTitleInput" class="input shf-input" maxlength="255">
            </div>

            <div class="gt-field">
                <label class="lbl" for="shfCreateTaskDesc">Description</label>
                <textarea name="description" id="shfCreateTaskDesc" class="input shf-input" rows="3" style="height:auto;padding:10px;line-height:1.45;" maxlength="5000"></textarea>
            </div>

            <div class="gt-row-2">
                <div class="gt-field">
                    <label class="lbl" for="shfCreateTaskAssignee">Assign To</label>
                    <select name="assigned_to" id="shfCreateTaskAssignee" class="input shf-input">
                        <option value="">— Self (me) —</option>
                        @foreach ($activeUsers as $u)
                            <option value="{{ $u->id }}" {{ $u->id === $currentUserId ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gt-field">
                    <label class="lbl" for="shfCreateTaskPriority">Priority <span style="color:var(--red);">*</span></label>
                    <select name="priority" id="shfCreateTaskPriority" class="input shf-input">
                        <option value="">— Select —</option>
                        @foreach (\App\Models\GeneralTask::PRIORITY_LABELS as $key => $info)
                            <option value="{{ $key }}" {{ $key === 'normal' ? 'selected' : '' }}>{{ $info['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="gt-row-2">
                <div class="gt-field">
                    <label class="lbl" for="shfCreateTaskDueDate">Due Date <span style="color:var(--red);">*</span></label>
                    <input type="text" name="due_date" id="shfCreateTaskDueDate" class="input shf-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                </div>
                <div class="gt-field">
                    <label class="lbl" for="shfCreateTaskLoanSearch">Link to Loan (optional)</label>
                    <input type="text" id="shfCreateTaskLoanSearch" class="input shf-input" placeholder="Loan #, customer, bank…" autocomplete="off">
                    <input type="hidden" name="loan_detail_id" id="shfCreateTaskLoanId" value="">
                    <div class="gt-loan-results" id="shfCreateTaskLoanResults"></div>
                </div>
            </div>
        </div>

        <div class="gt-modal-ft">
            <button type="button" class="btn" id="shfCreateTaskCancel">Cancel</button>
            <button type="submit" class="btn primary" id="shfCreateTaskSave">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                Save
            </button>
        </div>
    </form>
</div>
