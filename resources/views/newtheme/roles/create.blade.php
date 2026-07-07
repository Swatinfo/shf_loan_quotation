@extends('newtheme.layouts.app', ['pageKey' => 'roles'])

@section('title', 'New Role · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/roles.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('roles.index') }}">Roles</a>
                    <span class="sep">/</span>
                    <span>New</span>
                </div>
                <h1>Create Role</h1>
                <div class="sub">Define a role and optionally copy permissions from an existing one.</div>
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
        <div class="grid c-form mt-4" style="max-width: 720px;">

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

            <form method="POST" action="{{ route('roles.store') }}" id="roleCreateForm" autocomplete="off">
                @csrf

                {{-- Role Details --}}
                <div class="card">
                    <div class="card-hd">
                        <div class="t"><span class="num">1</span>Role Details</div>
                    </div>
                    <div class="card-bd">

                        <div class="rlf-row">
                            <label for="roleNameInput" class="rlf-lbl">Role Name <span class="rlf-req">*</span></label>
                            <input type="text" id="roleNameInput" name="name"
                                class="input" value="{{ old('name') }}" required
                                placeholder="e.g. Senior Advisor" maxlength="255" autofocus>
                        </div>

                        <div class="rlf-row">
                            <label for="roleSlugInput" class="rlf-lbl">Slug <span class="rlf-req">*</span></label>
                            <input type="text" id="roleSlugInput" name="slug"
                                class="input rlf-mono" value="{{ old('slug') }}" required
                                placeholder="e.g. senior_advisor" pattern="[a-z0-9_-]+" maxlength="255">
                            <div class="rlf-hint">Lowercase letters, numbers, underscores only. Must be unique.</div>
                        </div>

                        <div class="rlf-row">
                            <label for="roleDescInput" class="rlf-lbl">Description</label>
                            <input type="text" id="roleDescInput" name="description"
                                class="input" value="{{ old('description') }}"
                                placeholder="Brief description of this role">
                        </div>

                        <div class="rlf-row">
                            <input type="hidden" name="can_be_advisor" value="0">
                            <label class="rlf-check">
                                <input type="checkbox" name="can_be_advisor" value="1"
                                    {{ old('can_be_advisor') ? 'checked' : '' }}>
                                <span>Can be assigned as Loan Advisor</span>
                            </label>
                            <div class="rlf-hint">Users with this role will appear in the advisor dropdown when creating/editing loans.</div>
                        </div>

                    </div>
                </div>

                {{-- Copy From --}}
                <div class="card mt-4">
                    <div class="card-hd">
                        <div class="t"><span class="num">2</span>Copy From Existing Role <span class="sub">optional</span></div>
                    </div>
                    <div class="card-bd">
                        <p class="rlf-intro">Optionally copy permissions and stage eligibility from an existing role.</p>

                        <div class="rlf-row">
                            <label for="copyFromSelect" class="rlf-lbl">Source Role</label>
                            <select id="copyFromSelect" name="copy_from" class="input">
                                <option value="">— None (start fresh) —</option>
                                @foreach ($existingRoles as $existingRole)
                                    <option value="{{ $existingRole->id }}"
                                        {{ old('copy_from') == $existingRole->id ? 'selected' : '' }}>
                                        {{ $existingRole->name }} ({{ $existingRole->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="copyOptions" class="rlf-copy-opts" {{ old('copy_from') ? '' : 'style=display:none;' }}>
                            <div class="rlf-row rlf-row-tight">
                                <input type="hidden" name="copy_permissions" value="0">
                                <label class="rlf-check">
                                    <input type="checkbox" name="copy_permissions" value="1"
                                        {{ old('copy_permissions', '1') == '1' ? 'checked' : '' }}>
                                    <span>Copy permissions</span>
                                </label>
                            </div>
                            <div class="rlf-row rlf-row-tight">
                                <input type="hidden" name="copy_stage_eligibility" value="0">
                                <label class="rlf-check">
                                    <input type="checkbox" name="copy_stage_eligibility" value="1"
                                        {{ old('copy_stage_eligibility', '1') == '1' ? 'checked' : '' }}>
                                    <span>Copy stage eligibility</span>
                                </label>
                                <div class="rlf-hint">Adds this role to all stages where the source role is eligible.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rlf-actions">
                    <a href="{{ route('roles.index') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn primary">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Create Role
                    </button>
                </div>
            </form>
        </div>
    </main>
@endsection

@push('page-scripts')
    <script>
        (function ($) {
            $(function () {
                // Auto-generate slug from name
                $('#roleNameInput').on('input', function () {
                    var v = $(this).val();
                    var slug = v.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                    $('#roleSlugInput').val(slug);
                });

                // Toggle copy options
                $('#copyFromSelect').on('change', function () {
                    if ($(this).val()) { $('#copyOptions').show(); }
                    else { $('#copyOptions').hide(); }
                });

                // Submit: client validation → async name check → real submit
                $('#roleCreateForm').on('submit', function (e) {
                    var $form = $(this);
                    if (window.SHF && SHF.validateForm) {
                        var valid = SHF.validateForm($form, {
                            name: { required: true, maxlength: 255 },
                            slug: { required: true, maxlength: 255, pattern: /^[a-z0-9_-]+$/, patternMsg: 'Lowercase letters, numbers, underscores only.' },
                        });
                        if (!valid) { e.preventDefault(); return false; }
                    }

                    e.preventDefault();
                    var name = $('#roleNameInput').val();
                    $.get(@json(route('roles.check-name')), { name: name }, function (data) {
                        if (!data.available) {
                            var $n = $('#roleNameInput');
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
