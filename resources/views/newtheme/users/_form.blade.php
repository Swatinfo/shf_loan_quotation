{{--
    Shared user form partial for newtheme create + edit pages.

    Expected variables:
    - $mode      : 'create' | 'edit'
    - $user      : \App\Models\User|null  (null in create)
    - $copyFrom  : \App\Models\User|null  (create only, for "Copy" flow)
    - $branches  : Collection<Branch>
    - $permissions : ?Collection (grouped by 'group', edit-only; null in create)
    - $userOverrides : ?array (id => 'grant'|'deny', edit-only)
--}}
@php
    $isEdit = $mode === 'edit';
    // Either variable is only set in its own mode; guard against undefined when
    // the partial is included from the other flow.
    $user = $user ?? null;
    $copyFrom = $copyFrom ?? null;
    $permissions = $permissions ?? null;
    $userOverrides = $userOverrides ?? [];
    $targetUser = $isEdit ? $user : null;

    $allRoles = \App\Models\Role::orderBy('id')->get();
    $allBanks = \App\Models\Bank::active()->with('locations')->orderBy('name')->get();
    $locStates = \App\Models\Location::with('children')->states()->active()->orderBy('name')->get();

    $userBankIds = $targetUser ? $targetUser->employerBanks->pluck('id')->toArray() : [];
    $userLocationIds = $targetUser ? $targetUser->locations->pluck('id')->toArray() : [];
    $userBranchIds = $targetUser ? $targetUser->branches->pluck('id')->toArray() : [];

    $copyBankIds = $copyFrom ? $copyFrom->employerBanks->pluck('id')->toArray() : [];
    $copyLocationIds = $copyFrom ? $copyFrom->locations->pluck('id')->toArray() : [];

    $currentRoleSlug = $targetUser
        ? old('roles.0', $targetUser->roles->first()?->slug)
        : ($copyFrom ? old('roles.0', $copyFrom->roles->first()?->slug) : old('roles.0', 'loan_advisor'));

    $singleCityRoles = ['bank_employee', 'office_employee'];
    $multiLocRoles = ['branch_manager', 'bdh', 'loan_advisor'];
    $bankRoles = ['bank_employee', 'office_employee'];
    $branchRequiredRoles = ['branch_manager', 'bdh', 'loan_advisor', 'office_employee'];
    $psuRoles = ['bank_employee', 'office_employee'];

    $bankCities = [];
    foreach ($allBanks as $b) {
        $bankCities[$b->id] = $b->locations->where('type', 'city');
    }

    $userDefaultCityIds = [];
    if ($isEdit) {
        $userDefaultCityIds = \DB::table('bank_employees')
            ->where('user_id', $targetUser->id)
            ->where('is_default', true)
            ->whereNotNull('location_id')
            ->pluck('location_id')
            ->toArray();
    }

    $userOEBranchIds = [];
    if ($isEdit) {
        $userOEBranchIds = \DB::table('user_branches')
            ->where('user_id', $targetUser->id)
            ->where('is_default_office_employee', true)
            ->pluck('branch_id')
            ->toArray();
    }

    $branchDefaultOEInfo = [];
    foreach ($branches as $br) {
        $q = \DB::table('user_branches')
            ->join('users', 'users.id', '=', 'user_branches.user_id')
            ->where('user_branches.branch_id', $br->id)
            ->where('user_branches.is_default_office_employee', true);
        if ($isEdit) {
            $q->where('user_branches.user_id', '!=', $targetUser->id);
        }
        $branchDefaultOEInfo[$br->id] = $q->value('users.name');
    }

    $formAction = $isEdit ? route('users.update', $targetUser) : route('users.store');
    $submitLabel = $isEdit ? 'Update User' : ($copyFrom ? 'Create Copy' : 'Create User');
    $editUserIdJson = $isEdit ? (int) $targetUser->id : 'null';
@endphp

<form method="POST" action="{{ $formAction }}" id="userForm" data-mode="{{ $mode }}" autocomplete="off">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ========== Basic info ========== --}}
    <div class="uf-grid">
        <div class="uf-field">
            <label class="lbl" for="ufName">Name <span style="color:var(--red);">*</span></label>
            <input type="text" name="name" id="ufName" class="input"
                value="{{ old('name', $targetUser?->name ?? $copyFrom?->name ?? '') }}"
                maxlength="255" autofocus>
            @error('name')<div class="uf-err">{{ $message }}</div>@enderror
        </div>

        <div class="uf-field">
            <label class="lbl" for="ufEmail">Email <span style="color:var(--red);">*</span></label>
            <input type="email" name="email" id="ufEmail" class="input"
                value="{{ old('email', $targetUser?->email ?? '') }}"
                maxlength="255">
            @error('email')<div class="uf-err">{{ $message }}</div>@enderror
        </div>

        <div class="uf-field">
            <label class="lbl" for="ufPhone">Phone</label>
            <input type="text" name="phone" id="ufPhone" class="input"
                value="{{ old('phone', $targetUser?->phone ?? $copyFrom?->phone ?? '') }}"
                maxlength="20">
            @error('phone')<div class="uf-err">{{ $message }}</div>@enderror
        </div>

        <div class="uf-field">
            <label class="lbl" for="ufRole">Role <span style="color:var(--red);">*</span></label>
            <select name="roles[]" id="ufRole" class="input">
                @foreach ($allRoles as $r)
                    @if (! auth()->user()->isSuperAdmin() && $r->slug === 'super_admin') @continue @endif
                    <option value="{{ $r->slug }}" {{ $currentRoleSlug === $r->slug ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
            @error('roles')<div class="uf-err">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- ========== Role-conditional: location ========== --}}
    <div class="uf-field uf-cond" id="ufSingleCityField" data-show-roles="bank_employee,office_employee"
        style="{{ in_array($currentRoleSlug, $singleCityRoles) ? '' : 'display:none;' }}">
        <label class="lbl" for="ufSingleCity">City <span style="color:var(--red);">*</span></label>
        <select name="assigned_locations[]" id="ufSingleCity" class="input">
            <option value="">— Select City —</option>
            @foreach ($locStates as $locState)
                <optgroup label="{{ $locState->name }}">
                    @foreach ($locState->children->where('is_active', true) as $locCity)
                        @php $sel = in_array($locCity->id, old('assigned_locations', $isEdit ? $userLocationIds : $copyLocationIds)); @endphp
                        <option value="{{ $locCity->id }}" {{ $sel ? 'selected' : '' }}>{{ $locCity->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    <div class="uf-field uf-cond" id="ufMultiLocationField" data-show-roles="branch_manager,bdh,loan_advisor"
        style="{{ in_array($currentRoleSlug, $multiLocRoles) ? '' : 'display:none;' }}">
        <label class="lbl">Assigned Locations</label>
        <div class="uf-scroll-box">
            @foreach ($locStates as $locState)
                <div class="uf-loc-block">
                    <label class="uf-check uf-check-strong">
                        <input type="checkbox" name="assigned_locations[]" value="{{ $locState->id }}"
                            {{ in_array($locState->id, old('assigned_locations', $isEdit ? $userLocationIds : $copyLocationIds)) ? 'checked' : '' }}>
                        <span>{{ $locState->name }}</span>
                    </label>
                    @if ($locState->children->where('is_active', true)->isNotEmpty())
                        <div class="uf-loc-cities">
                            @foreach ($locState->children->where('is_active', true) as $locCity)
                                <label class="uf-check">
                                    <input type="checkbox" name="assigned_locations[]" value="{{ $locCity->id }}"
                                        {{ in_array($locCity->id, old('assigned_locations', $isEdit ? $userLocationIds : $copyLocationIds)) ? 'checked' : '' }}>
                                    <span>{{ $locCity->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ========== Role-conditional: bank ========== --}}
    <div class="uf-cond" id="ufSingleBankField" data-show-roles="bank_employee"
        style="{{ $currentRoleSlug === 'bank_employee' ? '' : 'display:none;' }}">
        <div class="uf-field">
            <label class="lbl" for="ufBankSelect">Bank <span style="color:var(--red);">*</span></label>
            <select name="assigned_banks[]" id="ufBankSelect" class="input">
                <option value="">— Select Bank —</option>
                @foreach ($allBanks as $bank)
                    @php $sel = in_array($bank->id, old('assigned_banks', $isEdit ? $userBankIds : $copyBankIds)); @endphp
                    <option value="{{ $bank->id }}" {{ $sel ? 'selected' : '' }}>{{ $bank->name }}</option>
                @endforeach
            </select>
        </div>

        @foreach ($allBanks as $bank)
            @php
                $bankActive = in_array($bank->id, old('assigned_banks', $isEdit ? $userBankIds : $copyBankIds)) && $currentRoleSlug === 'bank_employee';
            @endphp
            <div class="uf-bank-city-defaults" data-bank-id="{{ $bank->id }}" style="{{ $bankActive ? '' : 'display:none;' }}">
                <label class="lbl">Default for cities</label>
                @forelse ($bankCities[$bank->id] ?? [] as $city)
                    @php
                        $isDefault = in_array($city->id, $userDefaultCityIds);
                        $currentDefaultUser = \DB::table('bank_employees')
                            ->join('users', 'users.id', '=', 'bank_employees.user_id')
                            ->where('bank_employees.bank_id', $bank->id)
                            ->where('bank_employees.location_id', $city->id)
                            ->where('bank_employees.is_default', true)
                            ->when($isEdit, fn ($q) => $q->where('bank_employees.user_id', '!=', $targetUser->id))
                            ->value('users.name');
                    @endphp
                    <label class="uf-check">
                        <input type="checkbox" name="default_bank_cities[]" value="{{ $city->id }}" {{ $isDefault ? 'checked' : '' }}>
                        <span>{{ $city->name }}</span>
                        @if ($currentDefaultUser)
                            <span class="uf-muted">(Currently: {{ $currentDefaultUser }})</span>
                        @elseif (!$isDefault)
                            <span class="uf-muted">(No default)</span>
                        @endif
                    </label>
                @empty
                    <span class="uf-muted">No cities configured for this bank.</span>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="uf-field uf-cond" id="ufMultiBankField" data-show-roles="office_employee"
        style="{{ $currentRoleSlug === 'office_employee' ? '' : 'display:none;' }}">
        <label class="lbl">Assigned Banks</label>
        <div class="uf-chip-row">
            @foreach ($allBanks as $bank)
                <label class="uf-check">
                    <input type="checkbox" name="assigned_banks[]" value="{{ $bank->id }}"
                        {{ in_array($bank->id, old('assigned_banks', $isEdit ? $userBankIds : $copyBankIds)) ? 'checked' : '' }}>
                    <span>{{ $bank->name }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- ========== Primary Branch + Assigned Branches ========== --}}
    <div class="uf-grid">
        <div class="uf-field">
            <label class="lbl" for="ufDefaultBranch">Primary Branch
                <span class="uf-branch-required" style="color:var(--red);{{ in_array($currentRoleSlug, $branchRequiredRoles) ? '' : 'display:none;' }}">*</span>
            </label>
            <select name="default_branch_id" id="ufDefaultBranch" class="input"
                {{ in_array($currentRoleSlug, $branchRequiredRoles) ? 'required' : '' }}>
                <option value="">— None —</option>
                @foreach ($branches as $branch)
                    @php
                        $sel = old('default_branch_id', $targetUser?->default_branch_id ?? $copyFrom?->default_branch_id) == $branch->id;
                    @endphp
                    <option value="{{ $branch->id }}" {{ $sel ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="uf-field uf-cond" id="ufMultiBranchField" data-show-roles="branch_manager,bdh,loan_advisor,office_employee"
            style="{{ in_array($currentRoleSlug, $branchRequiredRoles) ? '' : 'display:none;' }}">
            <label class="lbl">Assigned Branches</label>
            <div class="uf-muted uf-oe-default-hint" style="{{ $currentRoleSlug === 'office_employee' ? '' : 'display:none;' }}">
                Default OE is a fallback — bank-specific assignment is in Settings → Workflow → Product Stages
            </div>
            <div class="uf-scroll-box uf-scroll-tall">
                @foreach ($branches as $branch)
                    <div class="uf-branch-row">
                        <label class="uf-check uf-check-flex">
                            <input type="checkbox" name="assigned_branches[]" value="{{ $branch->id }}"
                                class="uf-assigned-branch-cb" data-branch-id="{{ $branch->id }}"
                                {{ in_array($branch->id, old('assigned_branches', $isEdit ? $userBranchIds : [])) ? 'checked' : '' }}>
                            <span>{{ $branch->name }}</span>
                        </label>
                        <label class="uf-check uf-oe-default-toggle" data-branch-id="{{ $branch->id }}"
                            style="{{ $currentRoleSlug === 'office_employee' ? '' : 'display:none;' }}">
                            <input type="checkbox" name="default_oe_branches[]" value="{{ $branch->id }}"
                                {{ in_array($branch->id, old('default_oe_branches', $userOEBranchIds)) ? 'checked' : '' }}>
                            <span class="uf-muted">Default OE</span>
                            @if ($branchDefaultOEInfo[$branch->id])
                                <span class="uf-muted">({{ $branchDefaultOEInfo[$branch->id] }})</span>
                            @endif
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ========== Replace in Product Stages (for bank_employee / office_employee) ========== --}}
    <div class="uf-field uf-cond" id="ufReplacePSU" data-show-roles="bank_employee,office_employee"
        style="{{ in_array($currentRoleSlug, $psuRoles) ? '' : 'display:none;' }}">
        <label class="lbl">Replace in Product Stages</label>
        <div id="ufPSUHolders" class="uf-scroll-box uf-scroll-tall uf-psu">
            <span class="uf-muted">Select bank and city to load current holders.</span>
        </div>
    </div>

    {{-- ========== Password + confirm ========== --}}
    <div class="uf-grid">
        <div class="uf-field">
            <label class="lbl" for="ufPassword">
                {{ $isEdit ? 'New Password (leave blank to keep current)' : 'Password' }}
                @if (! $isEdit) <span style="color:var(--red);">*</span> @endif
            </label>
            <div class="uf-input-wrap">
                <input type="password" name="password" id="ufPassword" class="input" autocomplete="new-password">
                <button type="button" class="uf-eye" data-target="ufPassword" aria-label="Toggle password visibility">
                    <svg class="uf-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg class="uf-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18"/></svg>
                </button>
            </div>
            @error('password')<div class="uf-err">{{ $message }}</div>@enderror
        </div>

        <div class="uf-field">
            <label class="lbl" for="ufPasswordConfirm">{{ $isEdit ? 'Confirm New Password' : 'Confirm Password' }}</label>
            <div class="uf-input-wrap">
                <input type="password" name="password_confirmation" id="ufPasswordConfirm" class="input" autocomplete="new-password">
                <button type="button" class="uf-eye" data-target="ufPasswordConfirm" aria-label="Toggle password visibility">
                    <svg class="uf-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg class="uf-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ========== Status toggle ========== --}}
    <div class="uf-field">
        <label class="lbl">Status</label>
        <label class="uf-toggle">
            <input type="checkbox" name="is_active" value="1"
                {{ $isEdit ? (old('is_active', $targetUser->is_active) ? 'checked' : '') : 'checked' }}>
            <span>Active</span>
        </label>
    </div>

    {{-- ========== Permission overrides (edit only — and only with permission) ========== --}}
    @if (auth()->user()->hasPermission('manage_permissions'))
        @php
            $permsGrouped = $isEdit ? ($permissions ?? collect()) : \App\Models\Permission::all()->groupBy('group');
            $overrides = $userOverrides ?? [];
        @endphp
        @if ($permsGrouped && count($permsGrouped) > 0 && (! $isEdit || ! $targetUser->isSuperAdmin()))
            <div class="uf-section-divider">
                <h3 class="uf-section-title">Permission Overrides</h3>
                <p class="uf-muted">Override role defaults for this user. "Default" uses the role's setting.</p>

                @foreach ($permsGrouped as $group => $perms)
                    <div class="uf-perm-group">
                        <h4 class="uf-perm-group-title">{{ $group }}</h4>
                        <div class="uf-perm-list">
                            @foreach ($perms as $perm)
                                @php $override = $overrides[$perm->id] ?? null; @endphp
                                <div class="uf-perm-row">
                                    <span class="uf-perm-label">{{ $perm->name }}</span>
                                    <select name="permissions[{{ $perm->id }}]" class="input uf-perm-select">
                                        <option value="default" {{ ! $override ? 'selected' : '' }}>Default (role)</option>
                                        <option value="grant" {{ $override === 'grant' ? 'selected' : '' }}>Grant</option>
                                        <option value="deny" {{ $override === 'deny' ? 'selected' : '' }}>Deny</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ========== Actions ========== --}}
    <div class="uf-actions">
        <a href="{{ route('users.index') }}" class="btn">
            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            Cancel
        </a>
        <button type="submit" class="btn primary" id="ufSubmit">
            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
            {{ $submitLabel }}
        </button>
    </div>
</form>

<script>
    window.__UF = {
        mode: @json($mode),
        userId: {!! $editUserIdJson !!},
        checkEmailUrl: @json(route('users.check-email')),
        psuHoldersUrl: @json(route('users.product-stage-holders')),
    };
</script>
