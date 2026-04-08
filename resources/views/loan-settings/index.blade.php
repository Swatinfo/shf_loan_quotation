@extends('layouts.app')

@section('header')
    <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">
        <svg style="width:16px;height:16px;display:inline;margin-right:6px;color:#f15a29;" fill="none" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
        </svg>
        Loan Settings
    </h2>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            {{-- SHF-style tabs (matching quotation settings) --}}
            <div class="shf-tabs">
                @php $activeTab = request('tab', 'banks'); @endphp
                <button class="shf-tab{{ $activeTab === 'locations' ? ' active' : '' }}"
                    data-tab="locations">Locations</button>
                <button class="shf-tab{{ $activeTab === 'banks' ? ' active' : '' }}" data-tab="banks">Banks</button>
                <button class="shf-tab{{ $activeTab === 'branches' ? ' active' : '' }}"
                    data-tab="branches">Branches</button>
                <button class="shf-tab{{ $activeTab === 'master-stages' ? ' active' : '' }}" data-tab="master-stages">Stage
                    Master</button>
                <button class="shf-tab{{ $activeTab === 'products' ? ' active' : '' }}" data-tab="products">Products &
                    Stages</button>
                <button class="shf-tab{{ $activeTab === 'user-roles' ? ' active' : '' }}" data-tab="user-roles">User
                    Roles</button>
            </div>

            <div class="shf-card" style="border-top-left-radius: 0; border-top-right-radius: 0;">

                {{-- Locations Tab --}}
                <div class="settings-tab-pane p-4" id="tab-locations"{!! $activeTab !== 'locations' ? ' style="display:none;"' : '' !!}>
                    <p class="small mb-3" style="color: #6b7280;">Manage states and cities. Branches, users, and products
                        can be assigned to locations.</p>

                    @php $locations = \App\Models\Location::with('children')->states()->orderBy('name')->get(); @endphp

                    @foreach ($locations as $state)
                        <div class="mb-3 border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $state->name }}</strong>
                                    @if ($state->code)
                                        <small class="text-muted">({{ $state->code }})</small>
                                    @endif
                                    <span class="shf-badge shf-badge-blue ms-1"
                                        style="font-size:0.55rem;">{{ $state->children->count() }} cities</span>
                                    @if (!$state->is_active)
                                        <span class="shf-badge shf-badge-gray ms-1"
                                            style="font-size:0.55rem;">Inactive</span>
                                    @endif
                                </div>
                                @if (auth()->user()->hasPermission('manage_workflow_config'))
                                    <div class="d-flex gap-1">
                                        <button class="btn-accent-sm shf-edit-location" data-id="{{ $state->id }}"
                                            data-name="{{ $state->name }}" data-code="{{ $state->code }}"
                                            data-type="state" data-parent-id="">
                                            Edit
                                        </button>
                                        <button class="btn-accent-sm shf-delete-item"
                                            style="background:linear-gradient(135deg,#dc2626,#ef4444);font-size:0.65rem;"
                                            data-url="{{ route('loan-settings.locations.destroy', $state) }}">
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </div>
                            @if ($state->children->isNotEmpty())
                                <div class="mt-2 ps-3">
                                    @foreach ($state->children as $city)
                                        <div
                                            class="d-flex justify-content-between align-items-center py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                                            <div>
                                                <span class="text-muted" style="font-size:0.8rem;">↳</span>
                                                <span style="font-size:0.85rem;">{{ $city->name }}</span>
                                                @if ($city->code)
                                                    <small class="text-muted">({{ $city->code }})</small>
                                                @endif
                                                @if (!$city->is_active)
                                                    <span class="shf-badge shf-badge-gray ms-1"
                                                        style="font-size:0.5rem;">Inactive</span>
                                                @endif
                                            </div>
                                            @if (auth()->user()->hasPermission('manage_workflow_config'))
                                                <div class="d-flex gap-1">
                                                    <button class="btn-accent-sm shf-edit-location"
                                                        style="font-size:0.65rem;" data-id="{{ $city->id }}"
                                                        data-name="{{ $city->name }}" data-code="{{ $city->code }}"
                                                        data-type="city" data-parent-id="{{ $city->parent_id }}">
                                                        Edit
                                                    </button>
                                                    <button class="btn-accent-sm shf-delete-item"
                                                        style="background:linear-gradient(135deg,#dc2626,#ef4444);font-size:0.6rem;"
                                                        data-url="{{ route('loan-settings.locations.destroy', $city) }}">
                                                        Delete
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <form method="POST" action="{{ route('loan-settings.locations.store') }}" class="mt-4"
                            id="locationForm">
                            @csrf
                            <input type="hidden" name="id" id="locationEditId" value="">
                            <h6 class="mb-3" id="locationFormTitle">Add Location</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="shf-form-label d-block mb-1">Type <span
                                            class="text-danger">*</span></label>
                                    <select name="type" id="locationTypeInput" class="shf-input">
                                        <option value="state">State</option>
                                        <option value="city">City</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="locationParentWrapper" style="display:none;">
                                    <label class="shf-form-label d-block mb-1">State <span
                                            class="text-danger">*</span></label>
                                    <select name="parent_id" id="locationParentInput" class="shf-input">
                                        <option value="">— Select State —</option>
                                        @foreach ($locations as $state)
                                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="shf-form-label d-block mb-1">Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="name" id="locationNameInput" class="shf-input"
                                        placeholder="e.g. Gujarat or Rajkot">
                                </div>
                                <div class="col-md-2">
                                    <label class="shf-form-label d-block mb-1">Code</label>
                                    <input type="text" name="code" id="locationCodeInput" class="shf-input"
                                        placeholder="e.g. GJ">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn-accent" id="locationSubmitBtn">
                                        <span id="locationSubmitText">Add</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Banks Tab --}}
                <div class="settings-tab-pane p-4" id="tab-banks"{!! $activeTab !== 'banks' ? ' style="display:none;"' : '' !!}>
                    @foreach ($banks as $bank)
                        <div class="py-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $bank->name }}</strong>
                                    @if ($bank->code)
                                        <small class="text-muted">({{ $bank->code }})</small>
                                    @endif
                                    <small class="text-muted ms-2">{{ $bank->products->count() }} products</small>

                                    {{-- Assigned locations --}}
                                    @if ($bank->locations->isNotEmpty())
                                        <div class="mt-1">
                                            @foreach ($bank->locations as $bankLoc)
                                                <span class="shf-badge shf-badge-blue" style="font-size:0.6rem;">
                                                    📍
                                                    {{ $bankLoc->parent?->name ? $bankLoc->parent->name . '/' : '' }}{{ $bankLoc->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="mt-1"><small class="text-muted fst-italic">All locations</small>
                                        </div>
                                    @endif
                                </div>
                                @if (auth()->user()->hasPermission('manage_workflow_config'))
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        <button class="btn-accent-sm shf-edit-bank" data-id="{{ $bank->id }}"
                                            data-name="{{ $bank->name }}" data-code="{{ $bank->code }}"
                                            data-location-ids="{{ $bank->locations->pluck('id')->toJson() }}">
                                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button class="btn-accent-sm shf-delete-item"
                                            style="background:linear-gradient(135deg,#dc2626,#ef4444);"
                                            data-url="{{ route('loan-settings.banks.destroy', $bank) }}">
                                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <form method="POST" action="{{ route('loan-settings.banks.store') }}" class="mt-4"
                            id="bankForm">
                            @csrf
                            <input type="hidden" name="id" id="bankEditId" value="">
                            <h6 class="mb-3" id="bankFormTitle">Add Bank</h6>

                            @if ($errors->any())
                                <div class="alert alert-danger py-2 mb-3" style="font-size:0.8rem;">
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="shf-form-label d-block mb-1">Bank Name</label>
                                    <input type="text" name="name" id="bankNameInput"
                                        class="shf-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                        placeholder="e.g. State Bank of India" value="{{ old('name') }}">
                                    @if ($errors->has('name'))
                                        <small class="text-danger"
                                            style="font-size:0.75rem;">{{ $errors->first('name') }}</small>
                                    @endif
                                </div>
                                <div class="col-md-2">
                                    <label class="shf-form-label d-block mb-1">Code</label>
                                    <input type="text" name="code" id="bankCodeInput"
                                        class="shf-input {{ $errors->has('code') ? 'is-invalid' : '' }}"
                                        placeholder="e.g. SBI" value="{{ old('code') }}">
                                    @if ($errors->has('code'))
                                        <small class="text-danger"
                                            style="font-size:0.75rem;">{{ $errors->first('code') }}</small>
                                    @endif
                                </div>
                                <div class="col-md-4" id="bankLocationSection" style="display:none;">
                                    <label class="shf-form-label d-block mb-1">Available Locations</label>
                                    <div
                                        style="max-height: 220px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px;">
                                        @php $bankLocStates = \App\Models\Location::with('children')->states()->active()->orderBy('name')->get(); @endphp
                                        @foreach ($bankLocStates as $bls)
                                            <div class="mb-2">
                                                <label class="d-flex align-items-center gap-1 fw-semibold"
                                                    style="font-size:0.8rem;cursor:pointer;">
                                                    <input type="checkbox" name="bank_locations[]"
                                                        value="{{ $bls->id }}" class="shf-checkbox bank-loc-check"
                                                        style="width:13px;height:13px;">
                                                    {{ $bls->name }}
                                                </label>
                                                @foreach ($bls->children->where('is_active', true) as $blc)
                                                    <label class="d-flex align-items-center gap-1 ps-3"
                                                        style="font-size:0.75rem;cursor:pointer;">
                                                        <input type="checkbox" name="bank_locations[]"
                                                            value="{{ $blc->id }}"
                                                            class="shf-checkbox bank-loc-check"
                                                            style="width:12px;height:12px;">
                                                        {{ $blc->name }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn-accent" id="bankSubmitBtn">
                                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span id="bankSubmitText">Add Bank</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="bankCancelEdit"
                                        style="display:none;" onclick="resetBankForm()">Cancel</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Branches Tab --}}
                <div class="settings-tab-pane p-4" id="tab-branches"{!! $activeTab !== 'branches' ? ' style="display:none;"' : '' !!}>
                    @foreach ($branches as $branch)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <strong>{{ $branch->name }}</strong>
                                @if ($branch->code)
                                    <small class="text-muted">({{ $branch->code }})</small>
                                @endif
                                @if ($branch->location)
                                    <span class="shf-badge shf-badge-blue ms-2"
                                        style="font-size:0.6rem;">{{ $branch->location->parent?->name }} /
                                        {{ $branch->location->name }}</span>
                                @elseif($branch->city)
                                    <small class="text-muted ms-2">{{ $branch->city }}</small>
                                @endif
                                @if ($branch->phone)
                                    <small class="text-muted ms-2">{{ $branch->phone }}</small>
                                @endif
                                @if ($branch->manager)
                                    <br><small class="text-muted">Manager:
                                        <strong>{{ $branch->manager->name }}</strong></small>
                                @endif
                            </div>
                            @if (auth()->user()->hasPermission('manage_workflow_config'))
                                <div class="d-flex gap-2">
                                    <button class="btn-accent-sm shf-edit-branch" data-id="{{ $branch->id }}"
                                        data-name="{{ $branch->name }}" data-code="{{ $branch->code }}"
                                        data-city="{{ $branch->city }}" data-phone="{{ $branch->phone }}"
                                        data-manager-id="{{ $branch->manager_id }}"
                                        data-location-id="{{ $branch->location_id }}">
                                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit
                                    </button>
                                    <button class="btn-accent-sm shf-delete-item"
                                        style="background:linear-gradient(135deg,#dc2626,#ef4444);"
                                        data-url="{{ route('loan-settings.branches.destroy', $branch) }}">
                                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <form method="POST" action="{{ route('loan-settings.branches.store') }}" class="mt-4"
                            id="branchForm">
                            @csrf
                            <input type="hidden" name="id" id="branchEditId" value="">
                            <h6 class="mb-3" id="branchFormTitle">Add Branch</h6>

                            @if ($errors->any() && old('_token') && !old('charges'))
                                <div class="alert alert-danger py-2 mb-3" style="font-size:0.8rem;">
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="shf-form-label d-block mb-1">Branch Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="name" id="branchNameInput"
                                        class="shf-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                        placeholder="e.g. Ahmedabad Office" value="{{ old('name') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="shf-form-label d-block mb-1">Code</label>
                                    <input type="text" name="code" id="branchCodeInput"
                                        class="shf-input {{ $errors->has('code') ? 'is-invalid' : '' }}"
                                        placeholder="e.g. AHM" value="{{ old('code') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="shf-form-label d-block mb-1">City</label>
                                    <input type="text" name="city" id="branchCityInput" class="shf-input"
                                        placeholder="City" value="{{ old('city') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="shf-form-label d-block mb-1">Phone</label>
                                    <input type="text" name="phone" id="branchPhoneInput" class="shf-input"
                                        placeholder="Phone" value="{{ old('phone') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="shf-form-label d-block mb-1">City / Location</label>
                                    <select name="location_id" id="branchLocationInput" class="shf-input">
                                        <option value="">— Select City —</option>
                                        @php $locStates = \App\Models\Location::with('children')->states()->active()->orderBy('name')->get(); @endphp
                                        @foreach ($locStates as $locState)
                                            <optgroup label="{{ $locState->name }}">
                                                @foreach ($locState->children->where('is_active', true) as $locCity)
                                                    <option value="{{ $locCity->id }}"
                                                        {{ old('location_id') == $locCity->id ? 'selected' : '' }}>
                                                        {{ $locCity->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="shf-form-label d-block mb-1">Branch Manager <span
                                            class="text-danger">*</span></label>
                                    <select name="manager_id" id="branchManagerInput"
                                        class="shf-input {{ $errors->has('manager_id') ? 'is-invalid' : '' }}">
                                        <option value="">— Select Manager —</option>
                                        @foreach ($allActiveUsers->whereIn('task_role', ['branch_manager', 'loan_advisor']) as $mgr)
                                            <option value="{{ $mgr->id }}"
                                                {{ old('manager_id') == $mgr->id ? 'selected' : '' }}>
                                                {{ $mgr->name }} ({{ $mgr->task_role_label }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn-accent">
                                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Stage Master Tab --}}
                <div class="settings-tab-pane p-4" id="tab-master-stages"{!! $activeTab !== 'master-stages' ? ' style="display:none;"' : '' !!}>
                    <p class="text-muted mb-3">Global stage configuration. Set the default responsible role for each stage.
                        Product-specific stages inherit from these defaults.</p>

                    <form method="POST" action="{{ route('loan-settings.master-stages.save') }}" id="masterStagesForm">
                        @csrf
                        @php
                            $mainStages = $stages->whereNull('parent_stage_key');
                            $childStages = $stages->whereNotNull('parent_stage_key');
                        @endphp

                        {{-- Column header --}}
                        <div class="d-none d-md-flex align-items-center py-2 border-bottom mb-1"
                            style="font-size:0.7rem;font-weight:600;color:var(--primary-dark-light);text-transform:uppercase;letter-spacing:0.5px;">
                            <div style="flex:1 1 0;min-width:0;">Stage</div>
                            <div style="min-width:220px;flex-shrink:0;">Eligible Roles</div>
                            <div class="text-center" style="width:70px;flex-shrink:0;">Active</div>
                        </div>

                        @foreach ($stages as $stage)
                            @php
                                $isParallelHeader = $stage->is_parallel && !$stage->parent_stage_key;
                                $hasSubActions = !empty($stage->sub_actions) && is_array($stage->sub_actions);
                            @endphp
                            <div class="shf-master-stage d-flex align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }} {{ $stage->parent_stage_key ? 'ps-4' : '' }}"
                                style="{{ $stage->parent_stage_key ? 'background:#fafafa;' : '' }}">
                                <input type="hidden" name="stages[{{ $loop->index }}][id]"
                                    value="{{ $stage->id }}">

                                <div style="flex:1 1 0;min-width:0;">
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($stage->parent_stage_key)
                                            <span class="text-muted" style="font-size:0.8rem;">↳</span>
                                        @endif
                                        <span
                                            class="{{ $stage->parent_stage_key ? 'small' : 'fw-medium' }}">{{ $stage->stage_name_en }}</span>
                                        @if ($stage->stage_name_gu)
                                            <small
                                                class="text-muted d-none d-sm-inline">({{ $stage->stage_name_gu }})</small>
                                        @endif
                                        @if ($stage->is_parallel)
                                            <span class="shf-badge shf-badge-blue"
                                                style="font-size: 0.55rem;">Parallel</span>
                                        @endif
                                        @if ($stage->stage_type === 'decision')
                                            <span class="shf-badge shf-badge-orange"
                                                style="font-size: 0.55rem;">Decision</span>
                                        @endif
                                        @if ($hasSubActions)
                                            <span class="shf-badge shf-badge-orange"
                                                style="font-size: 0.5rem;">{{ count($stage->sub_actions) }}
                                                sub-stages</span>
                                        @endif
                                    </div>
                                    @if ($stage->description_en)
                                        <small class="text-muted d-block"
                                            style="font-size:0.7rem;">{{ $stage->description_en }}</small>
                                    @endif
                                </div>

                                <div style="min-width:220px;flex-shrink:0;">
                                    @if ($isParallelHeader)
                                        <small class="text-muted">— Group label —</small>
                                    @elseif($hasSubActions)
                                        <small class="text-muted">— See sub-stages —</small>
                                    @else
                                        @php $stageRoles = is_array($stage->default_role) ? $stage->default_role : ($stage->default_role ? [$stage->default_role] : []); @endphp
                                        <small class="text-muted d-block" style="font-size:0.6rem;">Stage Eligible
                                            Roles:</small>
                                        <div class="d-flex flex-wrap gap-1 shf-role-checkboxes">
                                            @foreach (\App\Models\User::TASK_ROLE_LABELS as $role => $label)
                                                <label class="d-inline-flex align-items-center gap-1 me-1"
                                                    style="font-size:0.72rem;cursor:pointer;">
                                                    <input type="checkbox"
                                                        name="stages[{{ $loop->parent->index }}][default_role][]"
                                                        value="{{ $role }}" class="shf-checkbox"
                                                        style="width:13px;height:13px;"
                                                        {{ in_array($role, $stageRoles) ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- Enable/Disable toggle (RIGHT side) --}}
                                <div class="text-center" style="width:70px;flex-shrink:0;">
                                    @if ($isParallelHeader || $hasSubActions)
                                        <input type="hidden" name="stages[{{ $loop->index }}][is_enabled]"
                                            value="1">
                                        <small class="text-muted">—</small>
                                    @else
                                        <input type="hidden" name="stages[{{ $loop->index }}][is_enabled]"
                                            value="0">
                                        <input type="checkbox" name="stages[{{ $loop->index }}][is_enabled]"
                                            value="1" class="shf-toggle shf-master-stage-toggle"
                                            {{ $stage->is_enabled ? 'checked' : '' }}>
                                    @endif
                                </div>
                            </div>

                            {{-- Sub-actions with role assignment + enable/disable --}}
                            @if ($hasSubActions)
                                @foreach ($stage->sub_actions as $saIdx => $subAction)
                                    @php $saEnabled = $subAction['is_enabled'] ?? true; @endphp
                                    <div class="shf-master-substage d-flex align-items-center py-1 ps-5 border-bottom"
                                        style="background:#f5f0eb;">
                                        <div style="flex:1 1 0;min-width:0;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-muted" style="font-size:0.75rem;">⤷</span>
                                                <span
                                                    style="font-size:0.8rem;">{{ $subAction['name'] ?? $subAction['key'] }}</span>
                                                <span
                                                    class="shf-badge shf-badge-{{ ($subAction['type'] ?? '') === 'action_button' ? 'orange' : 'blue' }}"
                                                    style="font-size:0.5rem;">
                                                    {{ ($subAction['type'] ?? '') === 'action_button' ? 'Action' : 'Form' }}
                                                </span>
                                                @if (!empty($subAction['transfer_to_role']))
                                                    <span class="text-muted" style="font-size:0.65rem;">→ transfers to
                                                        {{ $subAction['transfer_to_role'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div style="min-width:220px;flex-shrink:0;">
                                            @php $saRoles = $subAction['roles'] ?? []; @endphp
                                            <small class="text-muted d-block" style="font-size:0.55rem;">Sub-action
                                                Roles:</small>
                                            <div class="d-flex flex-wrap gap-1 shf-role-checkboxes">
                                                @foreach (\App\Models\User::TASK_ROLE_LABELS as $role => $label)
                                                    <label class="d-inline-flex align-items-center gap-1 me-1"
                                                        style="font-size:0.68rem;cursor:pointer;">
                                                        <input type="checkbox"
                                                            name="stages[{{ $loop->parent->parent->index }}][sub_actions][{{ $saIdx }}][roles][]"
                                                            value="{{ $role }}" class="shf-checkbox"
                                                            style="width:12px;height:12px;"
                                                            {{ in_array($role, $saRoles) ? 'checked' : '' }}>
                                                        {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Enable/Disable toggle (RIGHT side) --}}
                                        <div class="text-center" style="width:70px;flex-shrink:0;">
                                            <input type="hidden"
                                                name="stages[{{ $loop->parent->index }}][sub_actions][{{ $saIdx }}][is_enabled]"
                                                value="0">
                                            <input type="checkbox"
                                                name="stages[{{ $loop->parent->index }}][sub_actions][{{ $saIdx }}][is_enabled]"
                                                value="1" class="shf-toggle shf-master-substage-toggle"
                                                {{ $saEnabled ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        @endforeach

                        @if (auth()->user()->hasPermission('manage_workflow_config'))
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    Save Stage Defaults
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Products & Stages Tab --}}
                <div class="settings-tab-pane p-4" id="tab-products"{!! $activeTab !== 'products' ? ' style="display:none;"' : '' !!}>
                    @foreach ($banks as $bank)
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">
                                <svg style="width:14px;height:14px;display:inline;margin-right:4px;" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                {{ $bank->name }}
                            </h6>
                            @foreach ($bank->products as $product)
                                <div class="border-bottom ps-3">
                                    <div class="d-flex justify-content-between align-items-center py-2">
                                        <div>
                                            <span>{{ $product->name }}</span>
                                            @if ($product->code)
                                                <small class="text-muted">({{ $product->code }})</small>
                                            @endif
                                            @if ($product->productStages->count() > 0)
                                                <span class="shf-badge shf-badge-green ms-2"
                                                    style="font-size: 0.6rem;">{{ $product->productStages->where('is_enabled', true)->count() }}
                                                    stages</span>
                                            @endif
                                            @if ($product->locations->isNotEmpty())
                                                <span class="shf-badge shf-badge-blue ms-1"
                                                    style="font-size: 0.6rem;">{{ $product->locations->count() }}
                                                    locations</span>
                                            @else
                                                <span class="shf-badge shf-badge-gray ms-1" style="font-size: 0.6rem;">All
                                                    locations</span>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('loan-settings.product-stages', $product) }}"
                                                class="btn-accent-sm" style="font-size:0.7rem;">
                                                <svg style="width:11px;height:11px;" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                Stages
                                            </a>
                                            <button type="button" class="btn-accent-sm shf-toggle-product-locations"
                                                style="font-size:0.7rem;background:linear-gradient(135deg,#2563eb,#3b82f6);"
                                                data-target="#product-locs-{{ $product->id }}">
                                                <svg style="width:11px;height:11px;" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                Locations
                                            </button>
                                            @if (auth()->user()->hasPermission('manage_workflow_config'))
                                                <button class="btn-accent-sm shf-delete-item"
                                                    style="background:linear-gradient(135deg,#dc2626,#ef4444);font-size:0.7rem;"
                                                    data-url="{{ route('loan-settings.products.destroy', $product) }}">
                                                    <svg style="width:11px;height:11px;" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    {{-- Inline Location Config (collapsed) --}}
                                    <div id="product-locs-{{ $product->id }}" style="display:none;" class="pb-3">
                                        <form method="POST"
                                            action="{{ route('loan-settings.product-locations.save', $product) }}">
                                            @csrf
                                            @php
                                                $productLocIds = $product->locations->pluck('id')->toArray();
                                                $bankLocIds = $bank->locations->pluck('id')->toArray();
                                                $bankLocParentIds = $bank->locations
                                                    ->whereNotNull('parent_id')
                                                    ->pluck('parent_id')
                                                    ->unique()
                                                    ->toArray();
                                            @endphp
                                            <div class="p-2 border rounded" style="background:#f8fafc;">
                                                @if ($bank->locations->isEmpty())
                                                    <small class="text-muted">Bank has no locations assigned. Configure
                                                        bank locations first.</small>
                                                @else
                                                    <small class="text-muted d-block mb-2"
                                                        style="font-size:0.7rem;">Select cities where this product is
                                                        available (from {{ $bank->name }}'s locations).</small>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @foreach (\App\Models\Location::with('children')->states()->active()->orderBy('name')->get() as $ls)
                                                            @php
                                                                // Only show states/cities that belong to this bank
                                                                $stateInBank = in_array($ls->id, $bankLocIds);
                                                                $bankCities = $ls->children
                                                                    ->where('is_active', true)
                                                                    ->whereIn('id', $bankLocIds);
                                                            @endphp
                                                            @if ($stateInBank || $bankCities->isNotEmpty())
                                                                <div style="min-width:130px;">
                                                                    @if ($stateInBank)
                                                                        <label
                                                                            class="d-flex align-items-center gap-1 fw-semibold"
                                                                            style="font-size:0.75rem;cursor:pointer;">
                                                                            <input type="checkbox" name="locations[]"
                                                                                value="{{ $ls->id }}"
                                                                                class="shf-checkbox"
                                                                                style="width:12px;height:12px;"
                                                                                {{ in_array($ls->id, $productLocIds) ? 'checked' : '' }}>
                                                                            {{ $ls->name }}
                                                                        </label>
                                                                    @else
                                                                        <small class="fw-semibold d-block"
                                                                            style="font-size:0.75rem;">{{ $ls->name }}</small>
                                                                    @endif
                                                                    @foreach ($bankCities as $lc)
                                                                        <label class="d-flex align-items-center gap-1 ps-3"
                                                                            style="font-size:0.7rem;cursor:pointer;">
                                                                            <input type="checkbox" name="locations[]"
                                                                                value="{{ $lc->id }}"
                                                                                class="shf-checkbox"
                                                                                style="width:11px;height:11px;"
                                                                                {{ in_array($lc->id, $productLocIds) ? 'checked' : '' }}>
                                                                            {{ $lc->name }}
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="mt-2 d-flex justify-content-end">
                                                    <button type="submit" class="btn-accent-sm"
                                                        style="font-size:0.7rem;">
                                                        <svg style="width:10px;height:10px;" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        Save Locations
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                            @if ($bank->products->isEmpty())
                                <p class="text-muted small ps-3">No products yet</p>
                            @endif
                        </div>
                    @endforeach

                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <form method="POST" action="{{ route('loan-settings.products.store') }}"
                            class="mt-4 border-top pt-4">
                            @csrf
                            <h6 class="mb-3">Add Product</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="shf-form-label d-block mb-1">Bank</label>
                                    <select name="bank_id" class="shf-input" required>
                                        <option value="">Select bank...</option>
                                        @foreach ($banks as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="shf-form-label d-block mb-1">Product Name</label>
                                    <input type="text" name="name" class="shf-input" placeholder="e.g. Home Loan"
                                        required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn-accent">
                                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Product
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- User Roles Tab --}}
                <div class="settings-tab-pane p-4" id="tab-user-roles"{!! $activeTab !== 'user-roles' ? ' style="display:none;"' : '' !!}>
                    <p class="text-muted mb-3">Assign workflow roles to users for loan processing.</p>

                    {{-- Desktop table --}}
                    <div class="d-none d-md-block">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>System Role</th>
                                    <th>Task Role</th>
                                    <th>Bank</th>
                                    <th>Branches</th>
                                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                                        <th style="width:80px;"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $u)
                                    <tr>
                                        <td>
                                            <strong>{{ $u->name }}</strong>
                                            <br><small class="text-muted">{{ $u->email }}</small>
                                        </td>
                                        <td>
                                            <span
                                                class="shf-badge {{ $u->isSuperAdmin() ? 'shf-badge-orange' : ($u->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray') }}"
                                                style="font-size: 0.7rem;">
                                                {{ $u->role_label }}
                                            </span>
                                        </td>
                                        <td>{{ $u->task_role_label ?: '—' }}</td>
                                        <td>{{ $u->taskBank?->name ?? '—' }}</td>
                                        <td>{{ $u->branches->pluck('name')->implode(', ') ?: '—' }}</td>
                                        @if (auth()->user()->hasPermission('manage_workflow_config'))
                                            <td>
                                                <button class="btn-accent-sm shf-edit-role"
                                                    data-user-id="{{ $u->id }}"
                                                    data-task-role="{{ $u->task_role ?? '' }}"
                                                    data-task-bank-id="{{ $u->task_bank_id ?? '' }}"
                                                    data-employee-id="{{ $u->employee_id ?? '' }}"
                                                    data-branches="{{ $u->branches->pluck('id')->toJson() }}">
                                                    <svg style="width:12px;height:12px;" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="d-md-none">
                        @foreach ($users as $u)
                            <div class="border-bottom py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>{{ $u->name }}</strong>
                                        <span
                                            class="shf-badge {{ $u->isSuperAdmin() ? 'shf-badge-orange' : ($u->isAdmin() ? 'shf-badge-blue' : 'shf-badge-gray') }} ms-1"
                                            style="font-size: 0.65rem;">{{ $u->role_label }}</span>
                                        <br><small class="text-muted">{{ $u->task_role_label ?: 'No task role' }}</small>
                                    </div>
                                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                                        <button class="btn-accent-sm shf-edit-role" data-user-id="{{ $u->id }}"
                                            data-task-role="{{ $u->task_role ?? '' }}"
                                            data-task-bank-id="{{ $u->task_bank_id ?? '' }}"
                                            data-employee-id="{{ $u->employee_id ?? '' }}"
                                            data-branches="{{ $u->branches->pluck('id')->toJson() }}">
                                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Edit User Role Modal --}}
    @if (auth()->user()->hasPermission('manage_workflow_config'))
        <div class="modal fade" id="editRoleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editRoleUserId">
                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Task Role</label>
                            <select id="editTaskRole" class="shf-input">
                                <option value="">— None (quotation only) —</option>
                                @foreach (\App\Models\User::TASK_ROLE_LABELS as $role => $label)
                                    <option value="{{ $role }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3" id="bankFieldWrapper" style="display:none;">
                            <label class="shf-form-label d-block mb-1">Bank <span class="text-danger">*</span></label>
                            <select id="editTaskBankId" class="shf-input">
                                <option value="">— Select Bank —</option>
                                @foreach ($banks as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Employee ID</label>
                            <input type="text" id="editEmployeeId" class="shf-input" placeholder="Optional">
                        </div>
                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Branch Assignments</label>
                            <div id="branchCheckboxes" style="max-height: 200px; overflow-y: auto;">
                                @foreach ($allBranches as $branch)
                                    <div class="form-check py-1">
                                        <input class="shf-checkbox shf-branch-check" type="checkbox"
                                            value="{{ $branch->id }}" id="branch_{{ $branch->id }}">
                                        <label class="form-check-label"
                                            for="branch_{{ $branch->id }}">{{ $branch->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn-accent" id="saveRoleBtn">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Save Role
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(function() {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            // SHF tab switching (same pattern as quotation settings)
            $('.shf-tab').on('click', function() {
                var tab = $(this).data('tab');
                $('.shf-tab').removeClass('active');
                $(this).addClass('active');
                $('.settings-tab-pane').hide();
                $('#tab-' + tab).show();
                history.replaceState(null, '', '#' + tab);
            });
            // Restore tab from URL hash on page load, or show banks tab if there are validation errors
            var hash = window.location.hash.substring(1);
            @if ($errors->any() && old('manager_id') !== null)
                hash = 'branches';
            @elseif ($errors->any())
                hash = 'banks';
            @endif
            if (hash && $('#tab-' + hash).length) {
                $('.shf-tab').removeClass('active');
                $('.shf-tab[data-tab="' + hash + '"]').addClass('active');
                $('.settings-tab-pane').hide();
                $('#tab-' + hash).show();
            }

            // Location form — type toggle
            $('#locationTypeInput').on('change', function() {
                $('#locationParentWrapper').toggle($(this).val() === 'city');
            });
            $(document).on('click', '.shf-edit-location', function() {
                $('#locationEditId').val($(this).data('id'));
                $('#locationNameInput').val($(this).data('name'));
                $('#locationCodeInput').val($(this).data('code'));
                $('#locationTypeInput').val($(this).data('type')).trigger('change');
                $('#locationParentInput').val($(this).data('parent-id'));
                $('#locationFormTitle').text('Edit Location');
                $('#locationSubmitText').text('Update');
            });

            // Edit bank — populate form with locations
            $(document).on('click', '.shf-edit-bank', function() {
                $('#bankEditId').val($(this).data('id'));
                $('#bankNameInput').val($(this).data('name'));
                $('#bankCodeInput').val($(this).data('code'));

                // Reset location checkboxes
                $('.bank-loc-check').prop('checked', false);

                // Check assigned locations
                var locationIds = $(this).data('location-ids') || [];
                locationIds.forEach(function(id) {
                    $('.bank-loc-check[value="' + id + '"]').prop('checked', true);
                });

                $('#bankLocationSection').show();
                $('#bankFormTitle').text('Edit Bank');
                $('#bankSubmitText').text('Update Bank');
                $('#bankCancelEdit').show();
                $('#bankNameInput').focus();
            });

            window.resetBankForm = function() {
                $('#bankEditId').val('');
                $('#bankNameInput').val('');
                $('#bankCodeInput').val('');
                $('.bank-loc-check').prop('checked', false);
                $('#bankLocationSection').hide();
                $('#bankFormTitle').text('Add Bank');
                $('#bankSubmitText').text('Add Bank');
                $('#bankCancelEdit').hide();
            };

            // Toggle inline product stage config
            $(document).on('click', '.shf-toggle-product-locations', function() {
                var $panel = $($(this).data('target'));
                $panel.is(':visible') ? $panel.slideUp(200) : $panel.slideDown(200);
            });

            $(document).on('click', '.shf-toggle-stages', function() {
                var target = $(this).data('target');
                var $panel = $(target);
                if ($panel.is(':visible')) {
                    $panel.slideUp(200);
                } else {
                    // Close any other open panels first
                    $('.shf-product-stages-panel:visible').slideUp(200);
                    $panel.slideDown(200);
                }
            });

            // Edit branch — populate form
            $(document).on('click', '.shf-edit-branch', function() {
                $('#branchEditId').val($(this).data('id'));
                $('#branchNameInput').val($(this).data('name'));
                $('#branchCodeInput').val($(this).data('code'));
                $('#branchCityInput').val($(this).data('city'));
                $('#branchPhoneInput').val($(this).data('phone'));
                $('#branchManagerInput').val($(this).data('manager-id') || '');
                $('#branchLocationInput').val($(this).data('location-id') || '');
                $('#branchFormTitle').text('Edit Branch');
                $('#branchNameInput').focus();
            });

            // Delete bank/branch
            $(document).on('click', '.shf-delete-item', function() {
                var url = $(this).data('url');
                Swal.fire({
                    title: 'Delete?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Delete'
                }).then(function(r) {
                    if (r.isConfirmed) {
                        $.ajax({
                                url: url,
                                method: 'DELETE',
                                data: {
                                    _token: csrfToken
                                }
                            })
                            .done(function() {
                                location.reload();
                            })
                            .fail(function(xhr) {
                                Swal.fire('Error', xhr.responseJSON?.error || 'Cannot delete',
                                    'error');
                            });
                    }
                });
            });

            // Edit role modal
            $(document).on('click', '.shf-edit-role', function() {
                var $btn = $(this);
                $('#editRoleUserId').val($btn.data('user-id'));
                $('#editTaskRole').val($btn.data('task-role'));
                $('#editTaskBankId').val($btn.data('task-bank-id'));
                $('#editEmployeeId').val($btn.data('employee-id'));

                var userBranches = $btn.data('branches') || [];
                $('.shf-branch-check').prop('checked', false);
                userBranches.forEach(function(id) {
                    $('#branch_' + id).prop('checked', true);
                });

                $('#bankFieldWrapper').toggle($btn.data('task-role') === 'bank_employee');
                $('#editRoleModal').modal('show');
            });

            $('#editTaskRole').on('change', function() {
                $('#bankFieldWrapper').toggle($(this).val() === 'bank_employee');
            });

            // Save role
            $('#saveRoleBtn').on('click', function() {
                var userId = $('#editRoleUserId').val();
                var branches = [];
                $('.shf-branch-check:checked').each(function() {
                    branches.push($(this).val());
                });

                $.ajax({
                    url: '/loan-settings/users/' + userId + '/role',
                    method: 'POST',
                    data: {
                        _token: csrfToken,
                        task_role: $('#editTaskRole').val() || null,
                        task_bank_id: $('#editTaskBankId').val() || null,
                        employee_id: $('#editEmployeeId').val() || null,
                        branches: branches
                    },
                    success: function(r) {
                        if (r.success) {
                            $('#editRoleModal').modal('hide');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'Failed', 'error');
                    }
                });
            });

            // --- Stage Master form validation ---
            $('#masterStagesForm').on('submit', function(e) {
                var hasError = false;
                var firstErrorEl = null;

                // Clear previous
                $('.shf-master-stage, .shf-master-substage').css({'outline': '', 'background': ''});
                $('.shf-inline-error').remove();

                function addInlineError($row, msg) {
                    hasError = true;
                    if (!firstErrorEl) firstErrorEl = $row;
                    $row.css({'outline': '2px solid #dc3545', 'background': '#fff5f5'});
                    $row.append('<div class="shf-inline-error text-danger mt-1" style="font-size:0.75rem;">* ' + msg + '</div>');
                }

                // Validate enabled stages
                $('.shf-master-stage').each(function() {
                    var $row = $(this);
                    var $toggle = $row.find('.shf-master-stage-toggle');
                    if (!$toggle.length || !$toggle.is(':checked')) return;
                    var $roleBoxes = $row.find('.shf-role-checkboxes');
                    if (!$roleBoxes.length) return;
                    if ($roleBoxes.find('input[type="checkbox"]:checked').length === 0) {
                        addInlineError($row, 'Select at least one role');
                    }
                });

                // Validate enabled sub-stages
                $('.shf-master-substage').each(function() {
                    var $row = $(this);
                    var $toggle = $row.find('.shf-master-substage-toggle');
                    if (!$toggle.length || !$toggle.is(':checked')) return;
                    var $roleBoxes = $row.find('.shf-role-checkboxes');
                    if (!$roleBoxes.length) return;
                    if ($roleBoxes.find('input[type="checkbox"]:checked').length === 0) {
                        addInlineError($row, 'Select at least one role');
                    }
                });

                if (hasError) {
                    e.preventDefault();
                    if (firstErrorEl) {
                        $('html, body').animate({ scrollTop: $(firstErrorEl).offset().top - 100 }, 300);
                    }
                    return false;
                }
            });
        });
    </script>
@endpush
