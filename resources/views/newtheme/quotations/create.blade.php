@extends('newtheme.layouts.app')

@section('title', 'New Quotation · SHF World')

@push('page-styles')
    {{-- No legacy shf.css or Bootstrap — we restyle every `.shf-*` class the
         form + script use with newtheme tokens. See the comprehensive shim
         in public/newtheme/pages/quotation-create.css. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/pages/quotation-create.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a> ·
                    <a href="{{ route('quotations.index') }}">Quotations</a> ·
                    <span>New</span>
                </div>
                <h1>Create Quotation</h1>
                <div class="sub">Fill in customer + loan + bank details, then generate the comparison PDF.</div>
            </div>
            <div class="head-actions">
                <a href="{{ route('quotations.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="qc-legacy-wrap py-4">
            <div class="px-3 px-sm-4 px-lg-5">

                {{-- Location Selector (always visible, mandatory) --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Location / સ્થાન <span class="text-danger">*</span></label>
                                <select id="quotationLocation" class="shf-input w-100"
                                    onchange="onLocationChange(); clearFieldError('quotationLocation')">
                                    <option value="">-- Select Location / સ્થાન પસંદ કરો --</option>
                                    @php
                                        $isAdminUser = auth()->user()->hasAnyRole(['super_admin', 'admin']);
                                        $userLocIds = $isAdminUser ? [] : auth()->user()->locations->pluck('id')->toArray();
                                    @endphp
                                    @foreach ($locStates as $locState)
                                        @php
                                            $stateCities = $locState->children->where('is_active', true);
                                            if (!$isAdminUser) {
                                                $hasState = in_array($locState->id, $userLocIds);
                                                $stateCities = $hasState ? $stateCities : $stateCities->whereIn('id', $userLocIds);
                                            }
                                        @endphp
                                        @if ($stateCities->isNotEmpty())
                                            <optgroup label="{{ $locState->name }}">
                                                @foreach ($stateCities as $locCity)
                                                    <option value="{{ $locCity->id }}" data-parent-id="{{ $locState->id }}"
                                                        {{ $defaultLocationId == $locCity->id ? 'selected' : '' }}>
                                                        {{ $locCity->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Branch / શાખા <span class="text-danger">*</span></label>
                                <select id="quotationBranch" class="shf-input w-100"
                                    onchange="clearFieldError('quotationBranch')">
                                    <option value="">-- Select Branch / શાખા પસંદ કરો --</option>
                                    @foreach ($userBranches as $branch)
                                        <option value="{{ $branch->id }}" data-location-id="{{ $branch->location_id }}"
                                            {{ $defaultBranchId == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}@if($branch->location) ({{ $branch->location->name }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 1: Customer Info --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-header">
                        <div class="shf-section-number">1</div>
                        <span class="shf-section-title">Customer Information / <span style="font-weight:400;opacity:0.8;">ગ્રાહક માહિતી</span></span>
                    </div>
                    <div class="shf-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Customer Name / ગ્રાહક નામ</label>
                                <input type="text" id="customerName" class="shf-input w-100" placeholder="Enter full name"
                                    oninput="clearFieldError('customerName')" />
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Customer Type / ગ્રાહક પ્રકાર</label>
                                <select id="customerType" onchange="onCustomerTypeChange(); clearFieldError('customerType')"
                                    class="shf-input w-100">
                                    <option value="">-- Select Type / પ્રકાર પસંદ કરો --</option>
                                    <option value="proprietor">Proprietor / માલિકી</option>
                                    <option value="partnership_llp">Partnership / LLP / ભાગીદારી / LLP</option>
                                    <option value="pvt_ltd">Private Limited / પ્રાઇવેટ લિમિટેડ</option>
                                    <option value="salaried">Salaried / પગારદાર</option>
                                    <option value="all">All Types / બધા પ્રકાર</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 2: Documents --}}
                <div class="shf-section mb-4 shf-collapse-hidden" id="docSection">
                    <div class="shf-section-header">
                        <div class="shf-section-number">2</div>
                        <span class="shf-section-title">Required Documents / જરૂરી દસ્તાવેજો</span>
                    </div>
                    <div class="shf-section-body">
                        <div class="row g-2" id="docGrid"></div>
                    </div>
                </div>

                {{-- Section 3: Loan Details --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-header">
                        <div class="shf-section-number">3</div>
                        <span class="shf-section-title">Loan Details / લોન વિગતો</span>
                    </div>
                    <div class="shf-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Loan Amount / લોન રકમ</label>
                                <input type="text" id="loanAmount" class="shf-input w-100" placeholder="e.g. 50,00,000"
                                    oninput="formatLoanAmount(this); updateAllBanks(); updateAllPfAmounts(); clearFieldError('loanAmount');" />
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Amount in Words / રકમ શબ્દોમાં</label>
                                <input type="text" id="loanWords" class="shf-input w-100 small"
                                    style="background:#f9fafb;color:#6b7280;" readonly />
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Prepared By (Name) / તૈયાર કરનાર (નામ)</label>
                                <input type="text" id="preparedByName" class="shf-input w-100" value="{{ $user->name }}" />
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Prepared By (Mobile) / તૈયાર કરનાર (મોબાઇલ)</label>
                                <input type="text" id="preparedByMobile" class="shf-input w-100" value="{{ $user->phone ?? '' }}" />
                            </div>
                        </div>
                        @if (! empty($canAssignCreator) && $canAssignCreator)
                            {{-- Admin / super_admin / bdh — can attribute this quotation to
                                 a different user. Defaults to the currently-auth'd user. --}}
                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <label class="shf-form-label">Created By / બનાવનાર</label>
                                    <select id="quotationCreatedBy" class="shf-input w-100">
                                        @foreach ($assignableUsers as $au)
                                            <option value="{{ $au->id }}" {{ $au->id === $user->id ? 'selected' : '' }}>{{ $au->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Section 4: Bank Selection & EMI --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-header">
                        <div class="shf-section-number">4</div>
                        <span class="shf-section-title">Bank Selection & EMI / બેંક પસંદગી અને EMI</span>
                    </div>
                    <div class="shf-section-body">
                        <div class="d-flex flex-wrap gap-2 mb-4" id="bankChips"></div>
                        <div id="tenureSelection" class="shf-collapse-hidden mb-4">
                            <div class="shf-form-label mb-2">Select Tenures for PDF / PDF માટે સમયગાળો પસંદ કરો</div>
                            <div id="tenureChips" class="d-flex flex-wrap gap-2"></div>
                        </div>
                        <div id="bankCards" class="d-flex flex-column gap-3"></div>
                    </div>
                </div>

                {{-- Section 5: Additional Notes --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-header">
                        <div class="shf-section-number">5</div>
                        <span class="shf-section-title">Additional Notes / વધારાની નોંધ</span>
                    </div>
                    <div class="shf-section-body">
                        <label class="shf-form-label">Notes (will appear highlighted in red on PDF)</label>
                        <textarea id="additionalNotes" rows="3" class="shf-input w-100"
                            placeholder="Enter any additional notes for the proposal..."></textarea>
                    </div>
                </div>

                {{-- Pending Offline Quotations Badge (clickable Sync Now) --}}
                <div id="pendingSyncBadge" class="shf-collapse-hidden text-center mb-2">
                    <button type="button" onclick="syncNow()" id="syncNowBtn"
                        style="display:inline-flex;align-items:center;gap:6px;background:#f39c12;color:#fff;padding:6px 16px;border-radius:20px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;">
                        <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span id="pendingSyncCount">0</span> quotation(s) pending sync / ક્વોટેશન સિંક બાકી
                        <span id="syncNowLabel" style="margin-left:4px;font-size:0.75rem;opacity:0.9;">— Tap to sync / સિંક કરવા ટેપ કરો</span>
                    </button>
                </div>

                {{-- Generate Button — on phones this becomes a sticky bar
                     pinned above the bottom nav so the action is always
                     reachable while scrolling through the form. --}}
                <div class="qc-submit-row d-flex justify-content-center gap-3 py-4">
                    <a href="{{ route('quotations.index') }}" class="btn-accent-outline" style="padding:10px 28px;font-size:0.95rem;">
                        <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </a>
                    <button id="btnGenerate" onclick="handleGenerate()" class="btn-accent" style="padding:10px 28px;font-size:0.95rem;">
                        <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        GENERATE PDF PROPOSAL
                    </button>
                </div>

            </div>
        </div>
    </main>

    {{-- Toast notifications --}}
    <div id="toastContainer" style="position:fixed;bottom:16px;right:16px;z-index:1050;display:flex;flex-direction:column;gap:8px;"></div>
@endsection

@push('page-scripts')
    {{-- Core SHF helpers (Indian number formatting, etc.) + offline manager +
         the verbatim create-script partial. Loading order matters:
           1. shf-app.js     — defines window.SHF
           2. offline-manager.js — defines window.OfflineManager (tried but non-blocking)
           3. _create-script.blade.php — uses both of the above plus the form IDs. --}}
    <script src="{{ asset('newtheme/js/shf-app.js') }}?v={{ config('app.shf_version') }}"></script>
    <script src="{{ asset('newtheme/js/offline-manager.js') }}?v={{ config('app.shf_version') }}" onerror="/* optional */"></script>
    @include('newtheme.quotations._create-script')
@endpush
