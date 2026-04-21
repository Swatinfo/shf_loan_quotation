@extends('layouts.app')
@section('title', 'New Role — SHF')

@section('header')
    <div class="d-flex align-items-center justify-content-between">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Role
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
            <div class="shf-card p-4" style="max-width: 42rem;">

                @if ($errors->any())
                    <div class="mb-3">
                        @foreach ($errors->all() as $error)
                            <p class="small mb-1" style="color: #c0392b">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('roles.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="shf-form-label">Role Name <span style="color:#c0392b">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" class="shf-input" required
                            placeholder="e.g. Senior Advisor" id="roleNameInput">
                    </div>

                    <div class="mb-3">
                        <label class="shf-form-label">Slug <span style="color:#c0392b">*</span></label>
                        <input type="text" name="slug" value="{{ old('slug') }}" class="shf-input" required
                            placeholder="e.g. senior_advisor" id="roleSlugInput" pattern="[a-z0-9_-]+">
                        <small class="shf-text-gray">Lowercase letters, numbers, underscores only. Must be unique.</small>
                    </div>

                    <div class="mb-3">
                        <label class="shf-form-label">Description</label>
                        <input type="text" name="description" value="{{ old('description') }}" class="shf-input"
                            placeholder="Brief description of this role">
                    </div>

                    <div class="mb-4">
                        <label class="shf-form-label d-flex align-items-center gap-2">
                            <input type="hidden" name="can_be_advisor" value="0">
                            <input type="checkbox" name="can_be_advisor" value="1"
                                {{ old('can_be_advisor') ? 'checked' : '' }} class="shf-checkbox"
                                style="width:16px;height:16px;">
                            Can be assigned as Loan Advisor
                        </label>
                        <small class="shf-text-gray">Users with this role will appear in the advisor dropdown when creating/editing loans.</small>
                    </div>

                    <hr class="my-4">

                    <h6 class="font-display fw-semibold mb-3">Copy From Existing Role</h6>
                    <p class="small shf-text-gray mb-3">Optionally copy permissions and stage eligibility from an existing role.</p>

                    <div class="mb-3">
                        <label class="shf-form-label">Copy from</label>
                        <select name="copy_from" class="shf-input" id="copyFromSelect">
                            <option value="">— None (start fresh) —</option>
                            @foreach ($existingRoles as $existingRole)
                                <option value="{{ $existingRole->id }}"
                                    {{ old('copy_from') == $existingRole->id ? 'selected' : '' }}>
                                    {{ $existingRole->name }} ({{ $existingRole->slug }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="copyOptions" class="{{ old('copy_from') ? '' : 'd-none' }}">
                        <div class="mb-2">
                            <label class="shf-form-label d-flex align-items-center gap-2">
                                <input type="hidden" name="copy_permissions" value="0">
                                <input type="checkbox" name="copy_permissions" value="1"
                                    {{ old('copy_permissions', '1') == '1' ? 'checked' : '' }} class="shf-checkbox"
                                    style="width:16px;height:16px;">
                                Copy permissions
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="shf-form-label d-flex align-items-center gap-2">
                                <input type="hidden" name="copy_stage_eligibility" value="0">
                                <input type="checkbox" name="copy_stage_eligibility" value="1"
                                    {{ old('copy_stage_eligibility', '1') == '1' ? 'checked' : '' }} class="shf-checkbox"
                                    style="width:16px;height:16px;">
                                Copy stage eligibility
                            </label>
                            <small class="shf-text-gray">Adds this role to all stages where the source role is eligible.</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="{{ route('roles.index') }}" class="btn-accent-outline">Cancel</a>
                        <button type="submit" class="btn-accent">
                            <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // Auto-generate slug from name
            $('#roleNameInput').on('input', function () {
                var name = $(this).val();
                var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                $('#roleSlugInput').val(slug);
            });

            // Toggle copy options
            $('#copyFromSelect').on('change', function () {
                if ($(this).val()) {
                    $('#copyOptions').removeClass('d-none');
                } else {
                    $('#copyOptions').addClass('d-none');
                }
            });

            // ── Form validation ──
            $('form').on('submit', function(e) {
                var $form = $(this);
                var valid = SHF.validateForm($form, {
                    name: { required: true, maxlength: 255 },
                    slug: { required: true, maxlength: 255, pattern: /^[a-z0-9_-]+$/, patternMsg: 'Lowercase letters, numbers, underscores only.' }
                });
                if (!valid) { e.preventDefault(); return false; }

                // Async name uniqueness check
                e.preventDefault();
                var name = $('#roleNameInput').val();
                $.get(@json(route('roles.check-name')), { name: name }, function(data) {
                    if (!data.available) {
                        $('#roleNameInput').addClass('is-invalid');
                        $('#roleNameInput').after('<div class="text-danger small mt-1 shf-client-error">This role name is already taken.</div>');
                        $('#roleNameInput').focus();
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
