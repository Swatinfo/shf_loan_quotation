            <div class="shf-card shf-section-no-top-radius">

                {{-- Locations Tab --}}
                <div class="settings-tab-pane p-4 shf-collapse-hidden" id="tab-locations"{!! $activeTab !== 'locations' ? '' : '' !!}>
                    <p class="small mb-3 shf-text-gray">Manage states and cities. Branches, users, and products
                        can be assigned to locations.</p>

                    @php $locations = \App\Models\Location::with('children')->states()->orderBy('name')->get(); @endphp

                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <div class="shf-add-form-wrapper mb-3">
                            <button class="shf-add-form-toggle collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#locationFormCollapse" aria-expanded="false" id="locationFormToggle">
                                <span id="locationFormTitle">+ Add Location</span>
                                <svg class="shf-chevron shf-icon-md" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="collapse" id="locationFormCollapse">
                                <div class="shf-add-form-body">
                                    <form method="POST" action="{{ route('loan-settings.locations.store') }}"
                                        id="locationForm">
                                        @csrf
                                        <input type="hidden" name="id" id="locationEditId" value="">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="shf-form-label d-block mb-1">Type <span
                                                        class="text-danger">*</span></label>
                                                <select name="type" id="locationTypeInput" class="shf-input">
                                                    <option value="state">State</option>
                                                    <option value="city">City</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 shf-collapse-hidden" id="locationParentWrapper">
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
                                                <input type="text" name="name" id="locationNameInput"
                                                    class="shf-input" placeholder="e.g. Gujarat or Rajkot">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="shf-form-label d-block mb-1">Code</label>
                                                <input type="text" name="code" id="locationCodeInput"
                                                    class="shf-input" placeholder="e.g. GJ">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end gap-2">
                                                <button type="submit" class="btn-accent" id="locationSubmitBtn">
                                                    <svg class="shf-icon-sm" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <span id="locationSubmitText">Add</span>
                                                </button>
                                                <button type="button" class="btn-accent-outline shf-form-cancel"
                                                    data-collapse="#locationFormCollapse" data-reset="locationForm"><svg
                                                        class="shf-icon-sm" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg> Cancel</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif

                    @foreach ($locations as $state)
                        <div class="mb-3 border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $state->name }}</strong>
                                    @if ($state->code)
                                        <small class="text-muted">({{ $state->code }})</small>
                                    @endif
                                    <span
                                        class="shf-badge shf-badge-blue ms-1 shf-text-2xs">{{ $state->children->count() }}
                                        cities</span>
                                    @if (!$state->is_active)
                                        <span class="shf-badge shf-badge-gray ms-1 shf-text-2xs">Inactive</span>
                                    @endif
                                </div>
                                @if (auth()->user()->hasPermission('manage_workflow_config'))
                                    <div class="d-flex gap-1">
                                        <button class="btn-accent-sm shf-edit-location" data-id="{{ $state->id }}"
                                            data-name="{{ $state->name }}" data-code="{{ $state->code }}"
                                            data-type="state" data-parent-id="">
                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg> Edit
                                        </button>
                                        <button class="btn-accent-sm shf-delete-item shf-text-xs shf-btn-danger"
                                            data-url="{{ route('loan-settings.locations.destroy', $state) }}">
                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg> Delete
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
                                                <span class="text-muted shf-text-sm">↳</span>
                                                <span class="shf-text-sm">{{ $city->name }}</span>
                                                @if ($city->code)
                                                    <small class="text-muted">({{ $city->code }})</small>
                                                @endif
                                                @if (!$city->is_active)
                                                    <span
                                                        class="shf-badge shf-badge-gray ms-1 shf-text-2xs">Inactive</span>
                                                @endif
                                            </div>
                                            @if (auth()->user()->hasPermission('manage_workflow_config'))
                                                <div class="d-flex gap-1">
                                                    <button class="btn-accent-sm shf-edit-location shf-text-xs"
                                                        data-id="{{ $city->id }}"
                                                        data-name="{{ $city->name }}"
                                                        data-code="{{ $city->code }}" data-type="city"
                                                        data-parent-id="{{ $city->parent_id }}">
                                                        <svg class="shf-icon-xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg> Edit
                                                    </button>
                                                    <button
                                                        class="btn-accent-sm shf-delete-item shf-text-xs shf-btn-danger"
                                                        data-url="{{ route('loan-settings.locations.destroy', $city) }}">
                                                        <svg class="shf-icon-xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg> Delete
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                </div>

                {{-- Banks Tab --}}
                <div class="settings-tab-pane p-4 shf-collapse-hidden" id="tab-banks"{!! $activeTab !== 'banks' ? '' : '' !!}>
                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <div class="shf-add-form-wrapper mb-3">
                            <button class="shf-add-form-toggle collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#bankFormCollapse" aria-expanded="false" id="bankFormToggle">
                                <span id="bankFormTitle">+ Add Bank</span>
                                <svg class="shf-chevron shf-icon-md" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="collapse" id="bankFormCollapse">
                                <div class="shf-add-form-body">
                                    <form method="POST" action="{{ route('loan-settings.banks.store') }}"
                                        id="bankForm">
                                        @csrf
                                        <input type="hidden" name="id" id="bankEditId" value="">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="shf-form-label d-block mb-1">Bank Name</label>
                                                <input type="text" name="name" id="bankNameInput"
                                                    class="shf-input" placeholder="e.g. State Bank of India"
                                                    value="{{ old('name') }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="shf-form-label d-block mb-1">Code</label>
                                                <input type="text" name="code" id="bankCodeInput"
                                                    class="shf-input" placeholder="e.g. SBI"
                                                    value="{{ old('code') }}">
                                            </div>
                                            <div class="col-md-4 shf-collapse-hidden" id="bankLocationSection">
                                                <label class="shf-form-label d-block mb-1">Available Locations</label>
                                                <div
                                                    style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;padding:8px;">
                                                    @php $bankLocStates = \App\Models\Location::with('children')->states()->active()->orderBy('name')->get(); @endphp
                                                    @foreach ($bankLocStates as $bls)
                                                        <div class="mb-2">
                                                            <label
                                                                class="d-flex align-items-center gap-1 fw-semibold shf-text-sm shf-clickable">
                                                                <input type="checkbox" name="bank_locations[]"
                                                                    value="{{ $bls->id }}"
                                                                    class="shf-checkbox bank-loc-check">
                                                                {{ $bls->name }}
                                                            </label>
                                                            @foreach ($bls->children->where('is_active', true) as $blc)
                                                                <label
                                                                    class="d-flex align-items-center gap-1 ps-3 shf-text-xs shf-clickable">
                                                                    <input type="checkbox" name="bank_locations[]"
                                                                        value="{{ $blc->id }}"
                                                                        class="shf-checkbox bank-loc-check">
                                                                    {{ $blc->name }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end gap-2">
                                                <button type="submit" class="btn-accent" id="bankSubmitBtn">
                                                    <svg class="shf-icon-sm" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <span id="bankSubmitText">Add Bank</span>
                                                </button>
                                                <button type="button" class="btn-accent-outline shf-form-cancel"
                                                    data-collapse="#bankFormCollapse" data-reset="bankForm"
                                                    onclick="resetBankForm()"><svg class="shf-icon-sm" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg> Cancel</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
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
                                                <span class="shf-badge shf-badge-blue shf-text-xs">
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
                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button class="btn-accent-sm shf-delete-item shf-btn-danger"
                                            data-url="{{ route('loan-settings.banks.destroy', $bank) }}">
                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
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
                </div>

                {{-- Branches Tab --}}
                <div class="settings-tab-pane p-4 shf-collapse-hidden" id="tab-branches"{!! $activeTab !== 'branches' ? '' : '' !!}>
                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <div class="shf-add-form-wrapper mb-3">
                            <button class="shf-add-form-toggle collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#branchFormCollapse" aria-expanded="false" id="branchFormToggle">
                                <span id="branchFormTitle">+ Add Branch</span>
                                <svg class="shf-chevron shf-icon-md" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="collapse" id="branchFormCollapse">
                                <div class="shf-add-form-body">
                                    <form method="POST" action="{{ route('loan-settings.branches.store') }}"
                                        id="branchForm">
                                        @csrf
                                        <input type="hidden" name="id" id="branchEditId" value="">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="shf-form-label d-block mb-1">Branch Name <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="name" id="branchNameInput"
                                                    class="shf-input" placeholder="e.g. Ahmedabad Office"
                                                    value="{{ old('name') }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="shf-form-label d-block mb-1">Code</label>
                                                <input type="text" name="code" id="branchCodeInput"
                                                    class="shf-input" placeholder="e.g. AHM"
                                                    value="{{ old('code') }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="shf-form-label d-block mb-1">City</label>
                                                <input type="text" name="city" id="branchCityInput"
                                                    class="shf-input" placeholder="City"
                                                    value="{{ old('city') }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="shf-form-label d-block mb-1">Phone</label>
                                                <input type="text" name="phone" id="branchPhoneInput"
                                                    class="shf-input" placeholder="Phone"
                                                    value="{{ old('phone') }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="shf-form-label d-block mb-1">City / Location</label>
                                                <select name="location_id" id="branchLocationInput"
                                                    class="shf-input">
                                                    <option value="">— Select City —</option>
                                                    @php $locStates = \App\Models\Location::with('children')->states()->active()->orderBy('name')->get(); @endphp
                                                    @foreach ($locStates as $locState)
                                                        <optgroup label="{{ $locState->name }}">
                                                            @foreach ($locState->children->where('is_active', true) as $locCity)
                                                                <option value="{{ $locCity->id }}">
                                                                    {{ $locCity->name }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="shf-form-label d-block mb-1">Branch Manager</label>
                                                <select name="manager_id" id="branchManagerInput" class="shf-input">
                                                    <option value="">— Select Manager —</option>
                                                    @foreach ($allActiveUsers->filter(fn($u) => $u->hasAnyRole(['branch_manager', 'bdh', 'loan_advisor'])) as $mgr)
                                                        <option value="{{ $mgr->id }}">{{ $mgr->name }}
                                                            ({{ $mgr->workflow_role_label }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end gap-2">
                                                <button type="submit" class="btn-accent"><svg class="shf-icon-sm"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg> Add</button>
                                                <button type="button" class="btn-accent-outline shf-form-cancel"
                                                    data-collapse="#branchFormCollapse" data-reset="branchForm"><svg
                                                        class="shf-icon-sm" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg> Cancel</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                    @foreach ($branches as $branch)
                        <div class="py-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong>{{ $branch->name }}</strong>
                                    @if ($branch->code)
                                        <small class="text-muted">({{ $branch->code }})</small>
                                    @endif
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                        @if ($branch->location)
                                            <span
                                                class="shf-badge shf-badge-gray shf-text-2xs">{{ $branch->location->parent?->name ? $branch->location->parent->name . ' / ' : '' }}{{ $branch->location->name }}</span>
                                        @elseif($branch->city)
                                            <small class="text-muted">{{ $branch->city }}</small>
                                        @endif
                                        @if ($branch->phone)
                                            <small class="text-muted">{{ $branch->phone }}</small>
                                        @endif
                                    </div>
                                    @if ($branch->manager)
                                        <small class="text-muted d-block mt-1">Manager:
                                            <strong>{{ $branch->manager->name }}</strong></small>
                                    @endif
                                </div>
                                @if (auth()->user()->hasPermission('manage_workflow_config'))
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <button class="btn-accent-sm shf-text-xs shf-edit-branch"
                                            data-id="{{ $branch->id }}" data-name="{{ $branch->name }}"
                                            data-code="{{ $branch->code }}" data-city="{{ $branch->city }}"
                                            data-phone="{{ $branch->phone }}"
                                            data-manager-id="{{ $branch->manager_id }}"
                                            data-location-id="{{ $branch->location_id }}"><svg class="shf-icon-xs"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg> Edit</button>
                                        <button class="btn-accent-sm shf-text-xs shf-delete-item shf-btn-danger"
                                            data-url="{{ route('loan-settings.branches.destroy', $branch) }}">
                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
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
                </div>

                {{-- Stage Master Tab --}}
                <div class="settings-tab-pane p-4 shf-collapse-hidden" id="tab-master-stages"{!! $activeTab !== 'master-stages' ? '' : '' !!}>
                    <p class="text-muted mb-3">Configure which role handles each stage. Set global defaults and
                        override per bank.</p>

                    @php
                        $roleOptions = [
                            'task_owner' => 'Task Owner',
                            'bank_employee' => 'Bank Employee',
                            'office_employee' => 'Office Employee',
                        ];
                        $activeBanksForConfig = $banks->where('is_active', true);
                    @endphp

                    <form method="POST" action="{{ route('loan-settings.master-stages.save') }}"
                        id="masterStagesForm">
                        @csrf

                        @foreach ($stages as $stage)
                            @php
                                $si = $loop->index;
                                $isParallelHeader = $stage->is_parallel && !$stage->parent_stage_key;
                                $hasSubActions = !empty($stage->sub_actions) && is_array($stage->sub_actions);
                                $isHeaderOnly = $isParallelHeader;
                            @endphp

                            <div class="shf-stage-card {{ $stage->parent_stage_key ? 'shf-stage-card--child' : '' }}">
                                <input type="hidden" name="stages[{{ $si }}][id]"
                                    value="{{ $stage->id }}">

                                <div class="shf-stage-header">
                                    <div class="shf-stage-header-title">
                                        @if ($stage->parent_stage_key)
                                            <span class="text-muted">↳</span>
                                        @endif
                                        <strong
                                            class="{{ $stage->parent_stage_key ? 'fw-medium' : '' }}">{{ $stage->stage_name_en }}</strong>
                                        @if ($stage->stage_name_gu)
                                            <small
                                                class="text-muted d-none d-sm-inline">({{ $stage->stage_name_gu }})</small>
                                        @endif
                                        @if ($stage->is_parallel)
                                            <span class="shf-badge shf-badge-blue shf-text-2xs">Parallel</span>
                                        @endif
                                        @if ($stage->stage_type === 'decision')
                                            <span class="shf-badge shf-badge-orange shf-text-2xs">Decision</span>
                                        @endif
                                        @if ($hasSubActions)
                                            <span
                                                class="shf-badge shf-badge-orange shf-text-2xs">{{ count($stage->sub_actions) }}
                                                phases</span>
                                        @endif
                                    </div>
                                    @if (!$isHeaderOnly)
                                        <input type="hidden" name="stages[{{ $si }}][is_enabled]"
                                            value="0">
                                        <input type="checkbox" name="stages[{{ $si }}][is_enabled]"
                                            value="1" class="shf-toggle"
                                            {{ $stage->is_enabled ? 'checked' : '' }}>
                                    @else
                                        <input type="hidden" name="stages[{{ $si }}][is_enabled]"
                                            value="1">
                                    @endif
                                </div>

                                <div class="shf-stage-body">
                                    @if ($stage->description_en)
                                        <small
                                            class="text-muted d-block shf-text-xs mb-2">{{ $stage->description_en }}</small>
                                    @endif

                                    @if (!$isHeaderOnly)
                                        @if ($hasSubActions)
                                            {{-- Multi-phase: one dropdown per phase --}}
                                            <div class="mb-2">
                                                <div class="shf-form-label d-block mb-1">Default Phase Roles</div>
                                                @foreach ($stage->sub_actions as $phaseIdx => $phase)
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="shf-phase-num shf-role-bg-task-owner"
                                                            style="width:22px;height:22px;font-size:0.65rem;">{{ $phaseIdx + 1 }}</span>
                                                        <span class="shf-text-xs"
                                                            style="min-width:140px;">{{ $phase['name'] ?? $phase['key'] }}</span>
                                                        <select
                                                            name="stages[{{ $si }}][phase_roles][{{ $phaseIdx }}]"
                                                            class="shf-input-sm" style="width:160px;">
                                                            @foreach ($roleOptions as $rv => $rl)
                                                                <option value="{{ $rv }}"
                                                                    {{ ($phase['role'] ?? 'task_owner') === $rv ? 'selected' : '' }}>
                                                                    {{ $rl }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            {{-- Single-phase: one dropdown --}}
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="shf-form-label mb-0">Default Role</span>
                                                <select name="stages[{{ $si }}][assigned_role]"
                                                    class="shf-input-sm" style="width:180px;">
                                                    @foreach ($roleOptions as $rv => $rl)
                                                        <option value="{{ $rv }}"
                                                            {{ ($stage->assigned_role ?? 'task_owner') === $rv ? 'selected' : '' }}>
                                                            {{ $rl }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        {{-- Bank Configuration --}}
                                        @if ($activeBanksForConfig->isNotEmpty())
                                            <div class="shf-collapsible shf-filter-open mt-2"
                                                data-target="#bank-config-{{ $stage->id }}">
                                                <span class="shf-form-label mb-0 shf-clickable">Bank
                                                    Configuration</span>
                                                <svg class="shf-collapse-arrow shf-icon-xs" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                                @php
                                                    $overrideCount = $activeBanksForConfig
                                                        ->filter(function ($b) use ($stage, $bankStageConfigs) {
                                                            return isset($bankStageConfigs[$b->id . '_' . $stage->id]);
                                                        })
                                                        ->count();
                                                @endphp
                                                @if ($overrideCount > 0)
                                                    <span
                                                        class="shf-badge shf-badge-orange shf-text-2xs ms-1">{{ $overrideCount }}
                                                        custom</span>
                                                @endif
                                            </div>
                                            <div id="bank-config-{{ $stage->id }}" class="mt-2"
                                                style="border-left:3px solid var(--accent-dim);padding-left:12px;">
                                                @foreach ($activeBanksForConfig as $bank)
                                                    @php
                                                        $bsc = $bankStageConfigs[$bank->id . '_' . $stage->id] ?? null;
                                                        $bankHasOverride = $bsc !== null;
                                                    @endphp
                                                    <div
                                                        class="d-flex align-items-start gap-2 mb-2 py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                                                        <div style="min-width:120px;flex-shrink:0;"
                                                            class="d-flex align-items-center gap-1">
                                                            <strong class="shf-text-xs">{{ $bank->name }}</strong>
                                                        </div>
                                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                                            @if ($hasSubActions)
                                                                {{-- Per-phase dropdowns for this bank --}}
                                                                @foreach ($stage->sub_actions as $phaseIdx => $phase)
                                                                    @php
                                                                        $phaseDefault = $phase['role'] ?? 'task_owner';
                                                                        $bankPhaseRole =
                                                                            $bsc?->phase_roles[(string) $phaseIdx] ??
                                                                            $phaseDefault;
                                                                        $phaseIsOverride =
                                                                            $bankPhaseRole !== $phaseDefault;
                                                                    @endphp
                                                                    <div
                                                                        class="d-inline-flex align-items-center gap-1 me-2 mb-1">
                                                                        <span
                                                                            class="shf-text-2xs text-muted">P{{ $phaseIdx + 1 }}:</span>
                                                                        <select
                                                                            name="bank_configs[{{ $bank->id }}][{{ $stage->id }}][phase_roles][{{ $phaseIdx }}]"
                                                                            class="shf-input-sm shf-text-xs {{ $phaseIsOverride ? 'shf-input-override' : '' }}"
                                                                            style="width:140px;padding:2px 4px;">
                                                                            @foreach ($roleOptions as $rv => $rl)
                                                                                <option value="{{ $rv }}"
                                                                                    {{ $bankPhaseRole === $rv ? 'selected' : '' }}>
                                                                                    {{ $rl }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                @endforeach
                                                            @else
                                                                {{-- Single dropdown for this bank --}}
                                                                @php
                                                                    $stageDefault =
                                                                        $stage->assigned_role ?? 'task_owner';
                                                                    $bankRole = $bsc?->assigned_role ?? $stageDefault;
                                                                    $singleIsOverride = $bankRole !== $stageDefault;
                                                                @endphp
                                                                <select
                                                                    name="bank_configs[{{ $bank->id }}][{{ $stage->id }}][assigned_role]"
                                                                    class="shf-input-sm shf-text-xs {{ $singleIsOverride ? 'shf-input-override' : '' }}"
                                                                    style="width:160px;padding:2px 4px;">
                                                                    @foreach ($roleOptions as $rv => $rl)
                                                                        <option value="{{ $rv }}"
                                                                            {{ $bankRole === $rv ? 'selected' : '' }}>
                                                                            {{ $rl }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    @else
                                        <small class="text-muted">— Group label —</small>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @if (auth()->user()->hasPermission('manage_workflow_config'))
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn-accent">
                                    <svg class="shf-icon-sm" fill="none" stroke="currentColor"
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
                <div class="settings-tab-pane p-4 shf-collapse-hidden" id="tab-products"{!! $activeTab !== 'products' ? '' : '' !!}>
                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                        <div class="shf-card p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="shf-text-sm shf-text-gray">
                                <strong>Sync Settings</strong> — re-apply the current stage &amp; task-owner
                                configuration to all active loans (not completed or disbursed). Manually
                                transferred stages are preserved.
                            </div>
                            <form method="POST" action="{{ route('loan-settings.sync-stage-config') }}"
                                class="m-0"
                                onsubmit="return confirm('Re-apply the current stage configuration to all active loans? Manually transferred stages will be kept.');">
                                @csrf
                                <button type="submit" class="btn-accent btn-accent-sm shf-text-nowrap">
                                    Sync Settings
                                </button>
                            </form>
                        </div>
                        <div class="shf-add-form-wrapper mb-3">
                            <button class="shf-add-form-toggle collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#productFormCollapse" aria-expanded="false">
                                <span id="productFormTitle">+ Add Product</span>
                                <svg class="shf-chevron shf-icon-md" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="collapse" id="productFormCollapse">
                                <div class="shf-add-form-body">
                                    <form method="POST" action="{{ route('loan-settings.products.store') }}"
                                        id="productForm">
                                        @csrf
                                        <input type="hidden" name="id" id="productEditId">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="shf-form-label d-block mb-1">Bank</label>
                                                <select name="bank_id" id="productBankInput" class="shf-input"
                                                    required>
                                                    <option value="">Select bank...</option>
                                                    @foreach ($banks as $b)
                                                        <option value="{{ $b->id }}">{{ $b->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="shf-form-label d-block mb-1">Product Name</label>
                                                <input type="text" name="name" id="productNameInput"
                                                    class="shf-input" placeholder="e.g. Home Loan" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="shf-form-label d-block mb-1">Code (optional)</label>
                                                <input type="text" name="code" id="productCodeInput"
                                                    class="shf-input" placeholder="e.g. HL" maxlength="20">
                                            </div>
                                        </div>

                                        <div class="shf-text-xs text-muted mt-2">
                                            Payout slabs, PF flag, cap and cycle for this product are configured on the
                                            <strong>Payout Config</strong> tab.
                                        </div>

                                        <div class="d-flex align-items-center gap-2 mt-3">
                                            <button type="submit" class="btn-accent"><svg class="shf-icon-sm"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg> <span id="productSubmitText">Add Product</span></button>
                                            <button type="button" class="btn-accent-outline shf-form-cancel"
                                                data-collapse="#productFormCollapse" data-reset="productForm"><svg
                                                    class="shf-icon-sm" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg> Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                    @foreach ($banks as $bank)
                        <div class="shf-stage-card">
                            <div class="shf-stage-header">
                                <div class="shf-stage-header-title">
                                    <svg class="shf-icon-md" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <strong>{{ $bank->name }}</strong>
                                    <span
                                        class="shf-badge shf-badge-gray shf-text-2xs">{{ $bank->products->count() }}
                                        products</span>
                                </div>
                            </div>
                            <div class="shf-stage-body" style="padding:0;">
                                @foreach ($bank->products as $product)
                                    <div class="px-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div class="d-flex align-items-center flex-wrap gap-1">
                                                <strong>{{ $product->name }}</strong>
                                                @if ($product->code)
                                                    <small class="text-muted">({{ $product->code }})</small>
                                                @endif
                                                @if ($product->productStages->count() > 0)
                                                    <span
                                                        class="shf-badge shf-badge-green shf-text-2xs">{{ $product->productStages->where('is_enabled', true)->count() }}
                                                        stages</span>
                                                @endif
                                                @if ($product->locations->isNotEmpty())
                                                    <span
                                                        class="shf-badge shf-badge-gray shf-text-2xs">{{ $product->locations->pluck('name')->implode(', ') }}</span>
                                                @else
                                                    <span class="shf-badge shf-badge-gray shf-text-2xs">All
                                                        locations</span>
                                                @endif
                                            </div>
                                            <div class="d-flex gap-1 flex-shrink-0">
                                                <a href="{{ route('loan-settings.product-stages', $product) }}"
                                                    class="btn-accent-sm shf-text-xs"><svg class="shf-icon-xs"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg> Stages</a>
                                                <button type="button"
                                                    class="btn-accent-sm shf-toggle-product-locations shf-text-xs shf-btn-gray"
                                                    data-target="#product-locs-{{ $product->id }}"><svg
                                                        class="shf-icon-xs" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg> Locations</button>
                                                @if (auth()->user()->hasPermission('manage_workflow_config'))
                                                    <button type="button"
                                                        class="btn-accent-sm shf-edit-product shf-text-xs"
                                                        data-id="{{ $product->id }}"
                                                        data-bank-id="{{ $product->bank_id }}"
                                                        data-name="{{ $product->name }}"
                                                        data-code="{{ $product->code }}">
                                                        <svg class="shf-icon-2xs" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Edit
                                                    </button>
                                                    <button
                                                        class="btn-accent-sm shf-delete-item shf-text-xs shf-btn-danger"
                                                        data-url="{{ route('loan-settings.products.destroy', $product) }}">
                                                        <svg class="shf-icon-2xs" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        {{-- Inline Location Config (collapsed) --}}
                                        <div id="product-locs-{{ $product->id }}"
                                            class="shf-collapse-hidden mt-2 pb-2">
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
                                                        <small class="text-muted">Bank has no locations assigned.
                                                            Configure
                                                            bank locations first.</small>
                                                    @else
                                                        <small class="text-muted d-block mb-2 shf-text-xs">Select
                                                            cities
                                                            where this product is
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
                                                                                <input type="checkbox"
                                                                                    name="locations[]"
                                                                                    value="{{ $ls->id }}"
                                                                                    class="shf-checkbox shf-icon-2xs"
                                                                                    {{ in_array($ls->id, $productLocIds) ? 'checked' : '' }}>
                                                                                {{ $ls->name }}
                                                                            </label>
                                                                        @else
                                                                            <small
                                                                                class="fw-semibold d-block shf-text-xs">{{ $ls->name }}</small>
                                                                        @endif
                                                                        @foreach ($bankCities as $lc)
                                                                            <label
                                                                                class="d-flex align-items-center gap-1 ps-3 shf-clickable shf-text-xs">
                                                                                <input type="checkbox"
                                                                                    name="locations[]"
                                                                                    value="{{ $lc->id }}"
                                                                                    class="shf-checkbox shf-icon-2xs"
                                                                                    {{ in_array($lc->id, $productLocIds) ? 'checked' : '' }}>
                                                                                {{ $lc->name }}
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    <div class="mt-2 d-flex justify-content-end gap-2">
                                                        <button type="submit" class="btn-accent-sm shf-text-xs">
                                                            <svg class="shf-icon-2xs" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Save Locations
                                                        </button>
                                                        <button type="button"
                                                            class="btn-accent-outline shf-close-product-locs"
                                                            data-target="#product-locs-{{ $product->id }}"><svg
                                                                class="shf-icon-sm" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg> Cancel</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                                @if ($bank->products->isEmpty())
                                    <p class="text-muted small px-3 py-3">No products yet</p>
                                @endif
                            </div>{{-- close shf-stage-body --}}
                        </div>{{-- close shf-stage-card --}}
                    @endforeach


                </div>

                {{-- ═══ Role Permissions Tab ═══ --}}
                <div class="settings-tab-pane p-4 shf-collapse-hidden"
                    id="tab-role-permissions"{!! $activeTab !== 'role-permissions' ? '' : '' !!}>
                    <div class="shf-section">
                        <div class="shf-section-header">
                            <div class="shf-section-number">
                                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <span class="shf-section-title">Task Role × Permission Matrix</span>
                        </div>
                        <div class="shf-section-body">
                            <p class="small mb-4 shf-text-gray">
                                Configure which loan permissions each task role has. These are additive to system role
                                permissions.
                            </p>

                            @php $groupedLoanPerms = $loanPermissions->groupBy('group'); @endphp

                            @if (auth()->user()->hasPermission('manage_workflow_config'))
                                <form action="{{ route('loan-settings.task-role-permissions.save') }}"
                                    method="POST">
                                    @csrf
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Permission</th>
                                                    @foreach ($workflowRoles as $wfRole)
                                                        <th class="text-center">{{ $wfRole->name }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($groupedLoanPerms as $group => $perms)
                                                    <tr class="bg-accent-dim">
                                                        <td colspan="{{ $workflowRoles->count() + 1 }}"
                                                            class="font-display fw-semibold small shf-settings-tab-header">
                                                            {{ $group }}
                                                        </td>
                                                    </tr>
                                                    @foreach ($perms as $perm)
                                                        <tr>
                                                            <td>
                                                                <span class="fw-medium">{{ $perm->name }}</span>
                                                                @if ($perm->description)
                                                                    <span
                                                                        class="d-block small shf-text-gray-light">{{ $perm->description }}</span>
                                                                @endif
                                                            </td>
                                                            @foreach ($workflowRoles as $wfRole)
                                                                <td class="text-center">
                                                                    <input type="checkbox"
                                                                        name="task_role[{{ $wfRole->slug }}][]"
                                                                        value="{{ $perm->id }}"
                                                                        {{ in_array($perm->id, $rolePermissions[$wfRole->slug] ?? []) ? 'checked' : '' }}
                                                                        {{ $wfRole->slug === 'super_admin' ? 'checked disabled' : '' }}
                                                                        class="shf-checkbox shf-icon-md">
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn-accent">
                                            <svg class="shf-icon-md" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            Save Permissions
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Permission</th>
                                                @foreach ($workflowRoles as $wfRole)
                                                    <th class="text-center">{{ $wfRole->name }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($groupedLoanPerms as $group => $perms)
                                                <tr class="bg-accent-dim">
                                                    <td colspan="{{ $workflowRoles->count() + 1 }}"
                                                        class="font-display fw-semibold small shf-settings-tab-header">
                                                        {{ $group }}
                                                    </td>
                                                </tr>
                                                @foreach ($perms as $perm)
                                                    <tr>
                                                        <td>
                                                            <span class="fw-medium">{{ $perm->name }}</span>
                                                            @if ($perm->description)
                                                                <span
                                                                    class="d-block small shf-text-gray-light">{{ $perm->description }}</span>
                                                            @endif
                                                        </td>
                                                        @foreach ($workflowRoles as $wfRole)
                                                            <td class="text-center">
                                                                <input type="checkbox"
                                                                    {{ in_array($perm->id, $rolePermissions[$wfRole->slug] ?? []) ? 'checked' : '' }}
                                                                    disabled class="shf-checkbox"
                                                                    style="width:16px;height:16px;opacity:1;cursor:not-allowed;">
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ============================ Payout Config ============================ --}}
                @php
                    $payoutRates = [
                        'admin_gst' => 'Admin GST',
                        'pf_gst' => 'PF GST',
                        'user_tds' => 'User TDS',
                        'user_insurance' => 'User Insurance',
                    ];
                    $canEditPayout = auth()->user()->hasPermission('manage_workflow_config');
                @endphp
                <div class="settings-tab-pane p-4 shf-collapse-hidden" id="tab-payout-config">
                    <div class="shf-section">
                        <div class="shf-section-header">
                            <div class="shf-section-number">
                                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                </svg>
                            </div>
                            <span class="shf-section-title">Payout Config</span>
                        </div>
                        <div class="shf-section-body">
                            <p class="small mb-4 shf-text-gray">
                                Enter each rate as a percentage. The <strong>Calc (×)</strong> multiplier is computed
                                automatically as value ÷ 100 and is read-only. Set an <strong>Effective From</strong>
                                date per rate — the calc inherits it from the rate.
                            </p>

                            <form method="POST" action="{{ route('loan-settings.payout-config.save') }}" id="payoutConfigForm">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rate</th>
                                                <th style="width:150px;">Value (%)</th>
                                                <th style="width:140px;">Calc (×)</th>
                                                <th style="width:180px;">Effective From</th>
                                                <th style="width:120px;">History</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($payoutRates as $key => $label)
                                                @php
                                                    $row = $payoutConfig[$key] ?? ['value' => 0, 'calc' => 0, 'effective_from' => null];
                                                    $history = $payoutRateHistory[$key] ?? collect();
                                                @endphp
                                                <tr>
                                                    <td><span class="fw-medium">{{ $label }}</span></td>
                                                    <td>
                                                        <input type="number" step="0.01" min="0" max="100"
                                                            name="payout[{{ $key }}][value]"
                                                            class="shf-input payout-value" data-key="{{ $key }}"
                                                            value="{{ old('payout.'.$key.'.value', $row['value']) }}"
                                                            @disabled(!$canEditPayout)>
                                                    </td>
                                                    <td>
                                                        <input type="text" readonly tabindex="-1"
                                                            class="shf-input shf-input-readonly payout-calc"
                                                            id="payoutCalc_{{ $key }}"
                                                            value="{{ old('payout.'.$key.'.value') !== null ? number_format((float) old('payout.'.$key.'.value') / 100, 4) : number_format((float) $row['calc'], 4) }}">
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                            name="payout[{{ $key }}][effective_from]"
                                                            class="shf-input payout-date" placeholder="yyyy-mm-dd"
                                                            autocomplete="off"
                                                            value="{{ old('payout.'.$key.'.effective_from', $row['effective_from']) }}"
                                                            @disabled(!$canEditPayout)>
                                                    </td>
                                                    <td>
                                                        @if ($history->count())
                                                            <details class="shf-text-xs">
                                                                <summary class="shf-clickable">{{ $history->count() }} version{{ $history->count() === 1 ? '' : 's' }}</summary>
                                                                <div class="mt-1">
                                                                    @foreach ($history as $h)
                                                                        <div class="shf-text-2xs text-muted">
                                                                            {{ optional($h->effective_from)->format('d/m/Y') }} →
                                                                            {{ rtrim(rtrim(number_format($h->value, 2), '0'), '.') }}%
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </details>
                                                        @else
                                                            <span class="shf-text-2xs text-muted">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="shf-text-xs text-muted mt-2">
                                    Changing a rate with a new <strong>Effective From</strong> keeps the old value as a
                                    version — a payout uses the rate in force on each tranche's disbursement date.
                                </div>

                                @if ($canEditPayout)
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn-accent">
                                            <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            Save Payout Config
                                        </button>
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>

                    {{-- ===== Product Payout (moved from Products & Stages) ===== --}}
                    <div class="shf-section" style="margin-top:16px;">
                        <div class="shf-section-header">
                            <div class="shf-section-number">
                                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="shf-section-title">Product Payout Slabs</span>
                        </div>
                        <div class="shf-section-body">
                            <p class="small mb-3 shf-text-gray">
                                Per-product payout: PF flag, max cap, bank payout cycle (day 1–31) and slab rates
                                (internal + connector). Ranges apply to the disbursed amount, or to the PF amount for
                                PF-based products.
                            </p>

                            @if (auth()->user()->hasPermission('manage_workflow_config'))
                                <div class="shf-add-form-wrapper mb-3">
                                    <button class="shf-add-form-toggle collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#payoutProductFormCollapse"
                                        aria-expanded="false" id="payoutProductFormToggle">
                                        <span id="payoutProductFormTitle">Pick a product below to edit its payout</span>
                                        <svg class="shf-chevron shf-icon-md" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div class="collapse" id="payoutProductFormCollapse">
                                        <div class="shf-add-form-body">
                                            <form method="POST"
                                                action="{{ route('loan-settings.payout-product.save') }}"
                                                id="payoutProductForm">
                                                @csrf
                                                <input type="hidden" name="product_id" id="payoutProductId">
                                                <div class="row g-3 mb-1 align-items-end">
                                                    <div class="col-md-8">
                                                        <span class="shf-form-label d-block mb-1">Product</span>
                                                        <strong id="payoutProductName" class="shf-text-accent">— use an
                                                            Edit button below —</strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="shf-form-label d-block mb-1">Effective From</label>
                                                        <input type="text" name="effective_from"
                                                            id="payoutProductEffectiveInput"
                                                            class="shf-input payout-date" placeholder="yyyy-mm-dd"
                                                            autocomplete="off">
                                                        <div class="shf-text-2xs text-muted mt-1">Blank = today. A new
                                                            date keeps the old slabs as a version.</div>
                                                    </div>
                                                </div>

                                                <div class="row g-3 align-items-end">
                                                    <div class="col-md-4">
                                                        <label class="shf-form-label d-block mb-1">Is PF Based? / પીએફ
                                                            આધારિત?</label>
                                                        <label
                                                            class="d-inline-flex align-items-center gap-2 shf-text-sm">
                                                            <input type="checkbox" name="is_pf_based" value="1"
                                                                id="productPfInput" class="shf-checkbox">
                                                            <span>Yes — payout based on PF</span>
                                                        </label>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="shf-form-label d-block mb-1">Max Payout Amount (₹,
                                                            optional)</label>
                                                        <input type="number" name="max_payout_amount"
                                                            id="productMaxPayoutInput" class="shf-input" min="0"
                                                            step="0.01" placeholder="e.g. 50000.50">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="shf-form-label d-block mb-1">Payout Cycle (day
                                                            1–31)</label>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input type="number" name="payout_cycle_start_day"
                                                                id="productCycleStartInput" class="shf-input" min="1"
                                                                max="31" placeholder="Start (1)">
                                                            <span class="text-muted">to</span>
                                                            <input type="number" name="payout_cycle_end_day"
                                                                id="productCycleEndInput" class="shf-input" min="1"
                                                                max="31" placeholder="End (31)">
                                                        </div>
                                                        <div class="shf-text-xs text-muted mt-1">Bank payout cycle (e.g.
                                                            16 to 16). Defaults 1–31 (whole month).</div>
                                                    </div>
                                                </div>

                                                <div class="mt-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <label class="shf-form-label mb-0">Payout Slabs / પેઆઉટ
                                                            સ્લેબ</label>
                                                        <button type="button" id="productSlabAdd"
                                                            class="btn-accent-outline btn-accent-sm">
                                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            Add Slab
                                                        </button>
                                                    </div>
                                                    <div class="shf-text-xs text-muted mb-2">
                                                        Ranges apply to the disbursed amount. Payout per slab is a fixed
                                                        ₹ amount or a percentage. Ranges must not overlap.
                                                    </div>
                                                    <div id="productSlabList"></div>
                                                    <div id="productSlabError" class="shf-text-error shf-text-sm mt-1"
                                                        style="display:none;"></div>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 mt-3">
                                                    <button type="submit" class="btn-accent"><svg class="shf-icon-sm"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg> Save Product Payout</button>
                                                    <button type="button" class="btn-accent-outline shf-form-cancel"
                                                        data-collapse="#payoutProductFormCollapse"
                                                        data-reset="payoutProductForm"><svg class="shf-icon-sm"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg> Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @foreach ($banks as $bank)
                                @if ($bank->products->isNotEmpty())
                                    <div class="shf-stage-card">
                                        <div class="shf-stage-header">
                                            <div class="shf-stage-header-title">
                                                <strong>{{ $bank->name }}</strong>
                                                <span
                                                    class="shf-badge shf-badge-gray shf-text-2xs">{{ $bank->products->count() }}
                                                    products</span>
                                            </div>
                                        </div>
                                        <div class="shf-stage-body" style="padding:0;">
                                            @foreach ($bank->products as $product)
                                                @php
                                                    // The payout version currently in force (for display + edit prefill).
                                                    $currentPv = $product->payoutVersions->firstWhere('id', $product->current_payout_version_id);
                                                @endphp
                                                <div
                                                    class="px-3 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                                        <strong>{{ $product->name }}</strong>
                                                        @if ($product->is_pf_based)
                                                            <span class="shf-badge shf-badge-orange shf-text-2xs">PF
                                                                Based</span>
                                                        @endif
                                                        @if ($product->max_payout_amount)
                                                            <span class="shf-badge shf-badge-blue shf-text-2xs">Cap ₹
                                                                {{ inr($product->max_payout_amount) }}</span>
                                                        @endif
                                                        <span class="shf-badge shf-badge-gray shf-text-2xs">Cycle
                                                            {{ $product->payout_cycle_start_day }}–{{ $product->payout_cycle_end_day }}</span>
                                                        @if ($currentPv)
                                                            <span class="shf-badge shf-badge-green shf-text-2xs">Effective
                                                                {{ optional($currentPv->effective_from)->format('d/m/Y') }}</span>
                                                        @endif
                                                        @if ($product->payoutSlabs->isNotEmpty())
                                                            <span
                                                                class="shf-badge shf-badge-purple shf-text-2xs">{{ $product->payoutSlabs->count() }}
                                                                {{ $product->payoutSlabs->count() === 1 ? 'slab' : 'slabs' }}
                                                                ·
                                                                {{ \App\Services\NumberToWordsService::formatCurrency($product->payoutSlabs->first()->low_amount) }}
                                                                –
                                                                {{ \App\Services\NumberToWordsService::formatCurrency($product->payoutSlabs->last()->high_amount) }}</span>
                                                        @else
                                                            <span class="shf-badge shf-badge-gray shf-text-2xs">No
                                                                slabs</span>
                                                        @endif
                                                        @if ($product->payoutVersions->count() > 1)
                                                            <details class="shf-text-2xs">
                                                                <summary class="shf-clickable shf-text-accent">{{ $product->payoutVersions->count() }}
                                                                    versions</summary>
                                                                <div class="mt-1">
                                                                    @foreach ($product->payoutVersions as $pv)
                                                                        <div class="text-muted">
                                                                            {{ optional($pv->effective_from)->format('d/m/Y') }}
                                                                            — {{ $pv->payoutSlabs->count() }} slab{{ $pv->payoutSlabs->count() === 1 ? '' : 's' }}{{ $pv->is_pf_based ? ', PF' : '' }}{{ $pv->max_payout_amount ? ', cap ₹ '.inr($pv->max_payout_amount) : '' }}
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </details>
                                                        @endif
                                                    </div>
                                                    @if (auth()->user()->hasPermission('manage_workflow_config'))
                                                        @php
                                                            /* Built in @php first — @json() chokes on closures inside its argument (see lessons.md). */
                                                            $payoutSlabsPayload = $product->payoutSlabs
                                                                ->map(
                                                                    fn($s) => [
                                                                        'low_amount' => $s->low_amount,
                                                                        'high_amount' => $s->high_amount,
                                                                        'payout_type' => $s->payout_type,
                                                                        'payout_value' => $s->payout_value,
                                                                        'connector_payout_type' => $s->connector_payout_type,
                                                                        'connector_payout_value' => $s->connector_payout_value,
                                                                    ],
                                                                )
                                                                ->values();
                                                        @endphp
                                                        <button type="button"
                                                            class="btn-accent-sm shf-edit-payout-product shf-text-xs"
                                                            data-id="{{ $product->id }}"
                                                            data-name="{{ $bank->name }} — {{ $product->name }}"
                                                            data-pf-based="{{ $product->is_pf_based ? 1 : 0 }}"
                                                            data-max-payout="{{ $product->max_payout_amount }}"
                                                            data-cycle-start="{{ $product->payout_cycle_start_day }}"
                                                            data-cycle-end="{{ $product->payout_cycle_end_day }}"
                                                            data-effective="{{ optional($currentPv?->effective_from)->format('Y-m-d') }}"
                                                            data-slabs='@json($payoutSlabsPayload)'>
                                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                            Edit Payout
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
