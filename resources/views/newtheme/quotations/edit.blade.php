{{--
    Quotation edit page.

    Reuses the create form body verbatim. The differences:
      - Page header text + button label.
      - `window.SHF_QUOTATION_PREFILL` is injected ahead of _create-script,
        which detects it during init and populates every field.
      - `window.SHF_QUOTATION_UPDATE_URL` switches the submit handler to PUT.

    Authorisation is enforced by `Quotation::isEditableBy()` in the controller
    (creator + branch_manager / bdh of the quotation's branch + super_admin /
    view_all_quotations). Conversion-locked quotations 403 here.
--}}
@extends('newtheme.layouts.app')

@section('title', 'Edit Quotation #' . $quotation->id . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/quotation-create.css') }}?v={{ config('app.shf_version') }}">
    <style>
        .shf-doc-row {
            transition: opacity 0.15s ease, color 0.15s ease;
        }

        .shf-doc-row.shf-doc-struck>span {
            text-decoration: line-through;
            color: #9ca3af;
        }

        .shf-doc-row.shf-doc-struck {
            opacity: 0.65;
        }
    </style>
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a> ·
                    <a href="{{ route('quotations.index') }}">Quotations</a> ·
                    <a href="{{ route('quotations.show', $quotation) }}">#{{ $quotation->id }}</a> ·
                    <span>Edit</span>
                </div>
                <h1>Edit Quotation #{{ $quotation->id }}</h1>
                <div class="sub">Update customer + loan + bank details. Saving regenerates the cached PDF.</div>
            </div>
            <div class="head-actions">
                <a href="{{ route('quotations.show', $quotation) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="qc-legacy-wrap py-4">
            <div class="px-3 px-sm-4 px-lg-5">

                {{-- Location Selector --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Location / સ્થાન <span class="text-danger">*</span></label>
                                <select id="quotationLocation" class="shf-input w-100"
                                    onchange="onLocationChange(); clearFieldError('quotationLocation')">
                                    <option value="">-- Select Location / સ્થાન પસંદ કરો --</option>
                                    @php
                                        $isAdminUser = auth()
                                            ->user()
                                            ->hasAnyRole(['super_admin', 'admin']);
                                        $userLocIds = $isAdminUser
                                            ? []
                                            : auth()->user()->locations->pluck('id')->toArray();
                                    @endphp
                                    @foreach ($locStates as $locState)
                                        @php
                                            $stateCities = $locState->children->where('is_active', true);
                                            if (!$isAdminUser) {
                                                $hasState = in_array($locState->id, $userLocIds);
                                                $stateCities = $hasState
                                                    ? $stateCities
                                                    : $stateCities->whereIn('id', $userLocIds);
                                            }
                                        @endphp
                                        @if ($stateCities->isNotEmpty())
                                            <optgroup label="{{ $locState->name }}">
                                                @foreach ($stateCities as $locCity)
                                                    <option value="{{ $locCity->id }}"
                                                        data-parent-id="{{ $locState->id }}"
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
                                            {{ $branch->name }}@if ($branch->location)
                                                ({{ $branch->location->name }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Customer Info --}}
                <div class="shf-section mb-4">
                    <div class="shf-section-header">
                        <div class="shf-section-number">1</div>
                        <span class="shf-section-title">Customer Information / <span
                                style="font-weight:400;opacity:0.8;">ગ્રાહક માહિતી</span></span>
                    </div>
                    <div class="shf-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Customer Name / ગ્રાહક નામ</label>
                                <input type="text" id="customerName" class="shf-input w-100"
                                    placeholder="Enter full name" oninput="clearFieldError('customerName')" />
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
                            <div class="col-md-6">
                                <label class="shf-form-label">Referred By (Name) / સંદર્ભ આપનાર (નામ)</label>
                                <input type="text" id="referralName" class="shf-input w-100"
                                    placeholder="Who referred this customer? (optional)" />
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Referral Type / સંદર્ભ પ્રકાર</label>
                                <select id="referralType" class="shf-input w-100">
                                    <option value="">-- Select Type / પ્રકાર પસંદ કરો --</option>
                                    @foreach ($config['quotationReferralTypes'] ?? [] as $rt)
                                        <option value="{{ $rt['key'] }}">
                                            {{ $rt['label_en'] }}{{ !empty($rt['label_gu']) ? ' / ' . $rt['label_gu'] : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Documents --}}
                <div class="shf-section mb-4 shf-collapse-hidden" id="docSection">
                    <div class="shf-section-header">
                        <div class="shf-section-number">2</div>
                        <span class="shf-section-title">Required Documents / જરૂરી દસ્તાવેજો</span>
                    </div>
                    <div class="shf-section-body">
                        <div class="small mb-2 shf-text-gray">
                            Uncheck a document to strike it through — struck rows are saved but excluded from the PDF.
                        </div>
                        <div class="row g-2" id="docGrid"></div>
                    </div>
                </div>

                {{-- Loan Details --}}
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
                                <input type="text" id="preparedByName" class="shf-input w-100" />
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Prepared By (Mobile) / તૈયાર કરનાર (મોબાઇલ)</label>
                                <input type="text" id="preparedByMobile" class="shf-input w-100" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bank Selection & EMI --}}
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

                {{-- Additional Notes --}}
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

                <div class="qc-submit-row d-flex justify-content-center gap-3 py-4">
                    <a href="{{ route('quotations.show', $quotation) }}" class="btn-accent-outline"
                        style="padding:10px 28px;font-size:0.95rem;">
                        <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </a>
                    <button id="btnGenerate" onclick="handleGenerate()" class="btn-accent"
                        style="padding:10px 28px;font-size:0.95rem;">
                        <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        UPDATE QUOTATION
                    </button>
                </div>

            </div>
        </div>
    </main>

    <div id="toastContainer"
        style="position:fixed;bottom:16px;right:16px;z-index:1050;display:flex;flex-direction:column;gap:8px;"></div>
@endsection

@push('page-scripts')
    @php
        // Build the prefill payload in a regular PHP block so Blade's @json
// argument parser doesn't have to navigate nested arrow-fn closures
        // and array literals (it stops at the first `)` and chokes on the
        // unmatched `[`).
        $prefillPayload = [
            'customer_name' => $quotation->customer_name,
            'customer_type' => $quotation->customer_type,
            'referral_name' => $quotation->referral_name,
            'referral_type' => $quotation->referral_type,
            'loan_amount' => $quotation->loan_amount,
            'additional_notes' => $quotation->additional_notes,
            'prepared_by_name' => $quotation->prepared_by_name,
            'prepared_by_mobile' => $quotation->prepared_by_mobile,
            'location_id' => $quotation->location_id,
            'branch_id' => $quotation->branch_id,
            'selected_tenures' => $quotation->selected_tenures ?? [],
            'documents' => $quotation->documents
                ->map(
                    fn($d) => [
                        'en' => $d->document_name_en,
                        'gu' => $d->document_name_gu ?? '',
                        'excluded' => (bool) $d->is_excluded,
                    ],
                )
                ->values(),
            'banks' => $quotation->banks
                ->map(
                    fn($b) => [
                        'bank_name' => $b->bank_name,
                        'roi_min' => $b->roi_min,
                        'roi_max' => $b->roi_max,
                        'pf_charge' => $b->pf_charge,
                        'admin_charge' => $b->admin_charge,
                        'stamp_notary' => $b->stamp_notary,
                        'registration_fee' => $b->registration_fee,
                        'advocate_fees' => $b->advocate_fees,
                        'iom_charge' => $b->iom_charge,
                        'tc_report' => $b->tc_report,
                        'extra1_name' => $b->extra1_name,
                        'extra1_amount' => $b->extra1_amount,
                        'extra2_name' => $b->extra2_name,
                        'extra2_amount' => $b->extra2_amount,
                    ],
                )
                ->values(),
        ];
        $updateUrl = route('quotations.update', $quotation);
    @endphp
    <script>
        // Hand the create script everything it needs to reconstruct the form.
        // _create-script.blade.php detects these and applies them after its
        // standard init pass, then routes the submit handler to PUT.
        window.SHF_QUOTATION_UPDATE_URL = @json($updateUrl);
        window.SHF_QUOTATION_PREFILL = @json($prefillPayload);
    </script>
    <script src="{{ asset('newtheme/js/shf-app.js') }}?v={{ config('app.shf_version') }}"></script>
    <script src="{{ asset('newtheme/js/offline-manager.js') }}?v={{ config('app.shf_version') }}"
        onerror="/* optional */"></script>
    @include('newtheme.quotations._create-script')
@endpush
