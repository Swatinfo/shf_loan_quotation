@extends('layouts.app')
@section('title', 'Edit Role — SHF')

@section('header')
    <div class="d-flex align-items-center justify-content-between">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Role: {{ $role->name }}
        </h2>
        <a href="{{ route('roles.index') }}" class="btn-accent-outline btn-accent-outline-white">
            <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
        </a>
    </div>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            @if ($errors->any())
                <div class="mb-3">
                    @foreach ($errors->all() as $error)
                        <p class="small mb-1" style="color: #c0392b">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('roles.update', $role) }}">
                @csrf
                @method('PUT')

                {{-- Basic Info --}}
                <div class="shf-card p-4 mb-4" style="max-width: 42rem;">
                    <h6 class="font-display fw-semibold mb-3">Role Details</h6>

                    <div class="mb-3">
                        <label class="shf-form-label">Role Name <span style="color:#c0392b">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $role->name) }}" class="shf-input" required>
                    </div>

                    <div class="mb-3">
                        <label class="shf-form-label">Slug <span style="color:#c0392b">*</span></label>
                        <input type="text" name="slug" value="{{ old('slug', $role->slug) }}" class="shf-input"
                            required pattern="[a-z0-9_-]+" {{ $role->is_system ? 'readonly' : '' }}>
                        @if ($role->is_system)
                            <small class="shf-text-gray">System role slugs cannot be changed.</small>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="shf-form-label">Description</label>
                        <input type="text" name="description" value="{{ old('description', $role->description) }}"
                            class="shf-input">
                    </div>

                    <div class="mb-3">
                        <label class="shf-form-label d-flex align-items-center gap-2">
                            <input type="hidden" name="can_be_advisor" value="0">
                            <input type="checkbox" name="can_be_advisor" value="1"
                                {{ old('can_be_advisor', $role->can_be_advisor) ? 'checked' : '' }} class="shf-checkbox"
                                style="width:16px;height:16px;">
                            Can be assigned as Loan Advisor
                        </label>
                        <small class="shf-text-gray">Users with this role will appear in the advisor dropdown.</small>
                    </div>
                </div>

                {{-- Permissions --}}
                <div class="shf-card p-4 mb-4">
                    <h6 class="font-display fw-semibold mb-3">Permissions</h6>
                    <p class="small shf-text-gray mb-3">Select which permissions this role has.</p>

                    @foreach ($permissions as $group => $perms)
                        <div class="mb-3">
                            <strong class="small d-block mb-2" style="color: var(--accent);">{{ $group }}</strong>
                            <div class="row g-2">
                                @foreach ($perms as $perm)
                                    <div class="col-md-6 col-lg-4">
                                        <label class="d-flex align-items-start gap-2 small">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                                {{ in_array($perm->id, $rolePermissionIds) ? 'checked' : '' }}
                                                class="shf-checkbox mt-1" style="width:14px;height:14px;">
                                            <span>
                                                {{ $perm->name }}
                                                @if ($perm->description)
                                                    <span class="d-block shf-text-gray-light" style="font-size:0.75rem;">{{ $perm->description }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Stage Eligibility --}}
                <div class="shf-card p-4 mb-4">
                    <h6 class="font-display fw-semibold mb-3">Stage Eligibility</h6>
                    <p class="small shf-text-gray mb-3">Which loan stages can users with this role be assigned to?</p>

                    <div class="row g-2">
                        @foreach ($stages as $stage)
                            <div class="col-md-6 col-lg-4">
                                <label class="d-flex align-items-center gap-2 small">
                                    <input type="checkbox" name="stage_eligibility[]"
                                        value="{{ $stage->stage_key }}"
                                        {{ ($stageEligibility[$stage->stage_key] ?? false) ? 'checked' : '' }}
                                        class="shf-checkbox" style="width:14px;height:14px;">
                                    {{ $stage->stage_name_en }}
                                    @if ($stage->parent_stage_key)
                                        <span class="shf-text-gray-light">(sub-stage)</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('roles.index') }}" class="btn-accent-outline">Cancel</a>
                    <button type="submit" class="btn-accent">
                        <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function() {
    $('form').on('submit', function(e) {
        var $form = $(this);
        var rules = {
            name: { required: true, maxlength: 255 }
        };
        @unless($role->is_system)
            rules.slug = { required: true, maxlength: 255, pattern: /^[a-z0-9_-]+$/, patternMsg: 'Lowercase letters, numbers, underscores only.' };
        @endunless

        if (!SHF.validateForm($form, rules)) { e.preventDefault(); return false; }

        // Async name uniqueness check
        e.preventDefault();
        var name = $form.find('[name="name"]').val();
        $.get(@json(route('roles.check-name')), { name: name, exclude_id: {{ $role->id }} }, function(data) {
            if (!data.available) {
                var $nameField = $form.find('[name="name"]');
                $nameField.addClass('is-invalid');
                $nameField.after('<div class="text-danger small mt-1 shf-client-error">This role name is already taken.</div>');
                $nameField.focus();
            } else {
                $form.off('submit').submit();
            }
        }).fail(function() {
            $form.off('submit').submit();
        });
    });
});
</script>
@endpush
