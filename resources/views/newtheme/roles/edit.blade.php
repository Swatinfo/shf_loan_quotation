@extends('newtheme.layouts.app', ['pageKey' => 'roles'])

@section('title', 'Edit ' . $role->name . ' · Role · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/roles.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $totalPermCount = $permissions->flatten()->count();
    $checkedPermCount = count($rolePermissionIds);
    $checkedStageCount = collect($stageEligibility)->filter()->count();
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('roles.index') }}">Roles</a>
                    <span class="sep">/</span>
                    <span>{{ $role->name }}</span>
                </div>
                <h1>Edit Role: {{ $role->name }}</h1>
                <div class="sub">
                    <code class="rl-slug">{{ $role->slug }}</code>
                    @if ($role->is_system)
                        <span class="badge orange" style="margin-left:6px;vertical-align:middle;">System</span>
                    @else
                        <span class="badge blue" style="margin-left:6px;vertical-align:middle;">Workflow</span>
                    @endif
                    <span style="margin-left:10px;">{{ $checkedPermCount }} / {{ $totalPermCount }} permissions · {{ $checkedStageCount }} stages</span>
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('roles.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">

        @if ($errors->any())
            <div class="rl-errors">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('roles.update', $role) }}" id="roleEditForm" autocomplete="off">
            @csrf
            @method('PUT')

            {{-- Role Details --}}
            <div class="card rle-card" style="max-width: 720px;">
                <div class="card-hd">
                    <div class="t"><span class="num">1</span>Role Details</div>
                </div>
                <div class="card-bd">
                    <div class="rlf-row">
                        <label for="roleNameInput" class="rlf-lbl">Role Name <span class="rlf-req">*</span></label>
                        <input type="text" id="roleNameInput" name="name"
                            class="input" value="{{ old('name', $role->name) }}" required maxlength="255">
                    </div>

                    <div class="rlf-row">
                        <label for="roleSlugInput" class="rlf-lbl">Slug <span class="rlf-req">*</span></label>
                        <input type="text" id="roleSlugInput" name="slug"
                            class="input rlf-mono"
                            value="{{ old('slug', $role->slug) }}" required
                            pattern="[a-z0-9_-]+" maxlength="255"
                            {{ $role->is_system ? 'readonly' : '' }}>
                        @if ($role->is_system)
                            <div class="rlf-hint">System role slugs cannot be changed.</div>
                        @else
                            <div class="rlf-hint">Lowercase letters, numbers, underscores only.</div>
                        @endif
                    </div>

                    <div class="rlf-row">
                        <label for="roleDescInput" class="rlf-lbl">Description</label>
                        <input type="text" id="roleDescInput" name="description"
                            class="input" value="{{ old('description', $role->description) }}">
                    </div>

                    <div class="rlf-row">
                        <input type="hidden" name="can_be_advisor" value="0">
                        <label class="rlf-check">
                            <input type="checkbox" name="can_be_advisor" value="1"
                                {{ old('can_be_advisor', $role->can_be_advisor) ? 'checked' : '' }}>
                            <span>Can be assigned as Loan Advisor</span>
                        </label>
                        <div class="rlf-hint">Users with this role will appear in the advisor dropdown.</div>
                    </div>
                </div>
            </div>

            {{-- Permissions --}}
            <div class="card mt-4 rle-card">
                <div class="card-hd">
                    <div class="t"><span class="num">2</span>Permissions <span class="sub" id="permCountSub">{{ $checkedPermCount }} / {{ $totalPermCount }} selected</span></div>
                    <div class="actions">
                        <button type="button" class="btn sm" id="permSelectAll">Select all</button>
                        <button type="button" class="btn sm" id="permSelectNone">Select none</button>
                    </div>
                </div>
                <div class="card-bd">
                    <p class="rlf-intro">Select which permissions this role has. Resolution order is super_admin bypass → user override → role default (5&nbsp;min cache).</p>

                    @foreach ($permissions as $group => $perms)
                        @php $groupId = 'permGroup-' . \Illuminate\Support\Str::slug($group); @endphp
                        <div class="rle-group" id="{{ $groupId }}">
                            <div class="rle-group-hd">
                                <strong class="rle-group-name">{{ $group }}</strong>
                                <button type="button" class="rle-group-toggle" data-group="{{ $groupId }}">Toggle</button>
                            </div>
                            <div class="rle-perm-grid">
                                @foreach ($perms as $perm)
                                    <label class="rle-perm">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                            class="rle-perm-input"
                                            {{ in_array($perm->id, $rolePermissionIds) ? 'checked' : '' }}>
                                        <span class="rle-perm-body">
                                            <span class="rle-perm-name">{{ $perm->name }}</span>
                                            @if ($perm->description)
                                                <span class="rle-perm-desc">{{ $perm->description }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Stage Eligibility --}}
            <div class="card mt-4 rle-card">
                <div class="card-hd">
                    <div class="t"><span class="num">3</span>Stage Eligibility <span class="sub" id="stageCountSub">{{ $checkedStageCount }} selected</span></div>
                    <div class="actions">
                        <button type="button" class="btn sm" id="stageSelectAll">Select all</button>
                        <button type="button" class="btn sm" id="stageSelectNone">Select none</button>
                    </div>
                </div>
                <div class="card-bd">
                    <p class="rlf-intro">Which loan stages can users with this role be assigned to?</p>

                    <div class="rle-stage-grid">
                        @foreach ($stages as $stage)
                            <label class="rle-perm rle-stage {{ $stage->parent_stage_key ? 'is-sub' : '' }}">
                                <input type="checkbox" name="stage_eligibility[]" value="{{ $stage->stage_key }}"
                                    class="rle-stage-input"
                                    {{ ($stageEligibility[$stage->stage_key] ?? false) ? 'checked' : '' }}>
                                <span class="rle-perm-body">
                                    <span class="rle-perm-name">{{ $stage->stage_name_en }}</span>
                                    @if ($stage->parent_stage_key)
                                        <span class="rle-perm-desc">sub-stage of {{ $stage->parent_stage_key }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rlf-actions">
                <a href="{{ route('roles.index') }}" class="btn">Cancel</a>
                <button type="submit" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </main>
@endsection

@push('page-scripts')
    <script>
        (function ($) {
            $(function () {
                var $form = $('#roleEditForm');

                // Counters
                function updatePermCount() {
                    var total = $form.find('.rle-perm-input').length;
                    var checked = $form.find('.rle-perm-input:checked').length;
                    $('#permCountSub').text(checked + ' / ' + total + ' selected');
                }
                function updateStageCount() {
                    var checked = $form.find('.rle-stage-input:checked').length;
                    $('#stageCountSub').text(checked + ' selected');
                }
                $form.on('change', '.rle-perm-input', updatePermCount);
                $form.on('change', '.rle-stage-input', updateStageCount);

                // Bulk — permissions
                $('#permSelectAll').on('click', function () { $form.find('.rle-perm-input').prop('checked', true); updatePermCount(); });
                $('#permSelectNone').on('click', function () { $form.find('.rle-perm-input').prop('checked', false); updatePermCount(); });

                // Bulk — stages
                $('#stageSelectAll').on('click', function () { $form.find('.rle-stage-input').prop('checked', true); updateStageCount(); });
                $('#stageSelectNone').on('click', function () { $form.find('.rle-stage-input').prop('checked', false); updateStageCount(); });

                // Per-group toggle
                $('.rle-group-toggle').on('click', function () {
                    var gid = $(this).data('group');
                    var $inputs = $('#' + gid).find('.rle-perm-input');
                    var anyUnchecked = $inputs.filter(':not(:checked)').length > 0;
                    $inputs.prop('checked', anyUnchecked);
                    updatePermCount();
                });

                // Submit: client validation → async name check → real submit
                $form.on('submit', function (e) {
                    var rules = { name: { required: true, maxlength: 255 } };
                    @unless ($role->is_system)
                        rules.slug = { required: true, maxlength: 255, pattern: /^[a-z0-9_-]+$/, patternMsg: 'Lowercase letters, numbers, underscores only.' };
                    @endunless

                    if (window.SHF && SHF.validateForm) {
                        if (!SHF.validateForm($form, rules)) { e.preventDefault(); return false; }
                    }

                    e.preventDefault();
                    var name = $form.find('[name="name"]').val();
                    $.get(@json(route('roles.check-name')), { name: name, exclude_id: {{ $role->id }} }, function (data) {
                        if (!data.available) {
                            var $n = $form.find('[name="name"]');
                            $n.addClass('is-invalid');
                            $form.find('.rlf-client-err').remove();
                            $n.after('<div class="rlf-err rlf-client-err">This role name is already taken.</div>');
                            $n.focus();
                        } else {
                            $form.off('submit').trigger('submit');
                        }
                    }).fail(function () {
                        $form.off('submit').trigger('submit');
                    });
                });
            });
        })(window.jQuery);
    </script>
@endpush
