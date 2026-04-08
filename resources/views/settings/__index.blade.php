@extends('layouts.app')

@section('header')
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">
            <svg style="width:16px;height:16px;display:inline;margin-right:6px;color:#f15a29;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Settings
        </h2>
        <form method="POST" action="{{ route('settings.reset') }}" class="shf-confirm-delete" data-confirm-title="Reset ALL settings?" data-confirm-text="This will reset all settings to their defaults. This cannot be undone.">
            @csrf
            <button type="submit" class="d-inline-flex align-items-center border rounded-pill small fw-semibold" style="padding:4px 16px;border-color:rgba(248,113,113,0.5)!important;color:#fca5a5;background:transparent;">
                <svg style="width:14px;height:14px;margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Reset to Defaults
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            <!-- Tab Navigation (SHF dark tabs) -->
            <div class="shf-tabs">
                @php
                    $tabs = [
                        'company' => ['label' => 'Company', 'perm' => 'edit_company_info'],
                        'banks' => ['label' => 'Banks', 'perm' => 'edit_banks'],
                        'charges' => ['label' => 'IOM Stamp Paper', 'perm' => 'edit_charges'],
                        'bank-charges' => ['label' => 'Bank Charges', 'perm' => 'edit_charges'],
                        'gst' => ['label' => 'GST', 'perm' => 'edit_gst'],
                        'services' => ['label' => 'Services', 'perm' => 'edit_services'],
                        'tenures' => ['label' => 'Tenures', 'perm' => 'edit_tenures'],
                        'documents' => ['label' => 'Documents', 'perm' => 'edit_documents'],
                    ];
                    $activeTab = request('tab', 'company');
                @endphp
                @foreach($tabs as $key => $info)
                    <button class="shf-tab{{ $activeTab === $key ? ' active' : '' }}" data-tab="{{ $key }}">
                        {{ $info['label'] }}
                    </button>
                @endforeach
            </div>

            <!-- Tab Content -->
            <div class="shf-card" style="border-top-left-radius: 0; border-top-right-radius: 0;">

                {{-- Company Details --}}
                <div class="settings-tab-pane p-4" id="tab-company"{!! $activeTab !== 'company' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.company') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label d-block mb-1">Company Name</label>
                                <input type="text" name="companyName" class="shf-input" value="{{ $config['companyName'] }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label d-block mb-1">Email</label>
                                <input type="email" name="companyEmail" class="shf-input" value="{{ $config['companyEmail'] }}" required>
                            </div>
                            <div class="col-12">
                                <label class="shf-form-label d-block mb-1">Address</label>
                                <input type="text" name="companyAddress" class="shf-input" value="{{ $config['companyAddress'] }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label d-block mb-1">Phone</label>
                                <input type="text" name="companyPhone" class="shf-input" value="{{ $config['companyPhone'] }}" required>
                            </div>
                        </div>
                        @if(auth()->user()->hasPermission('edit_company_info'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save Company Details
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Banks --}}
                <div class="settings-tab-pane p-4" id="tab-banks"{!! $activeTab !== 'banks' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.banks') }}" id="banksForm">
                        @csrf
                        <p class="small mb-3" style="color: #6b7280;">Banks available for quotation selection.</p>

                        <div class="d-flex flex-wrap gap-2 mb-3" id="bankTagsContainer"></div>

                        <div class="d-flex gap-2">
                            <input type="text" id="newBankInput" class="shf-input flex-grow-1" placeholder="e.g. Yes Bank">
                            <button type="button" id="addBankBtn" class="btn-accent btn-accent-sm">+ Add</button>
                        </div>

                        @if(auth()->user()->hasPermission('edit_banks'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save Banks
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- IOM Stamp Paper Charges --}}
                <div class="settings-tab-pane p-4" id="tab-charges"{!! $activeTab !== 'charges' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.charges') }}">
                        @csrf
                        <p class="small mb-3" style="color: #6b7280;">IOM Stamp Paper charge structure.</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="shf-form-label d-block mb-1">Threshold Amount</label>
                                <input type="number" name="iomCharges[thresholdAmount]" class="shf-input" value="{{ $config['iomCharges']['thresholdAmount'] }}" required>
                                <p class="mt-1" style="font-size:0.75rem;color:#9ca3af;">Loan amount up to which fixed charge applies</p>
                            </div>
                            <div class="col-md-4">
                                <label class="shf-form-label d-block mb-1">Fixed Charge (up to threshold)</label>
                                <input type="number" name="iomCharges[fixedCharge]" class="shf-input" value="{{ $config['iomCharges']['fixedCharge'] }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="shf-form-label d-block mb-1">Percentage (above threshold)</label>
                                <input type="number" name="iomCharges[percentageAbove]" step="0.01" class="shf-input" value="{{ $config['iomCharges']['percentageAbove'] }}" required>
                                <p class="mt-1" style="font-size:0.75rem;color:#9ca3af;">e.g. 0.35 means 0.35% of loan amount</p>
                            </div>
                        </div>
                        @if(auth()->user()->hasPermission('edit_charges'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save IOM Stamp Paper Charges
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Bank Charges --}}
                <div class="settings-tab-pane p-4" id="tab-bank-charges"{!! $activeTab !== 'bank-charges' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.bank-charges') }}" id="bankChargesForm">
                        @csrf
                        <p class="small mb-3" style="color: #6b7280;">Default charges per bank (used when generating quotations).</p>

                        <div class="table-responsive">
                            <table class="table table-hover small">
                                <thead>
                                    <tr>
                                        <th>Bank</th>
                                        <th>PF Charge (%)</th>
                                        <th>Admin Charges</th>
                                        <th>Stamp Paper & Notary</th>
                                        <th>Registration Fee</th>
                                        <th>Advocate Fees</th>
                                        <th>TC Report Charges</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="bankChargesBody"></tbody>
                            </table>
                        </div>

                        <button type="button" id="addChargeRowBtn" class="mt-3 small fw-semibold" style="background:none;border:none;color:#f15a29;cursor:pointer;">+ Add Bank Row</button>

                        @if(auth()->user()->hasPermission('edit_charges'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save Bank Charges
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- GST --}}
                <div class="settings-tab-pane p-4" id="tab-gst"{!! $activeTab !== 'gst' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.gst') }}">
                        @csrf
                        <div style="max-width: 20rem;">
                            <label class="shf-form-label d-block mb-1">GST Percentage (%)</label>
                            <input type="number" name="gstPercent" step="0.01" class="shf-input" value="{{ $config['gstPercent'] }}" required>
                            <p class="mt-1" style="font-size:0.75rem;color:#9ca3af;">Applied on PF & Admin charges. e.g. 18 for 18%</p>
                        </div>
                        @if(auth()->user()->hasPermission('edit_gst'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save GST
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Services --}}
                <div class="settings-tab-pane p-4" id="tab-services"{!! $activeTab !== 'services' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.services') }}">
                        @csrf
                        <div>
                            <label class="shf-form-label d-block mb-1">Our Services (shown in PDF footer)</label>
                            <textarea name="ourServices" rows="4" class="shf-input" style="resize: vertical; min-height: 80px;">{{ $config['ourServices'] }}</textarea>
                            <p class="mt-1" style="font-size:0.75rem;color:#9ca3af;">Comma-separated list of services displayed in every PDF footer.</p>
                        </div>
                        @if(auth()->user()->hasPermission('edit_services'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save Services
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Tenures --}}
                <div class="settings-tab-pane p-4" id="tab-tenures"{!! $activeTab !== 'tenures' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.tenures') }}" id="tenuresForm">
                        @csrf
                        <p class="small mb-3" style="color: #6b7280;">EMI tenure options (in years) for quotation generation.</p>

                        <div class="d-flex flex-wrap gap-2 mb-3" id="tenureTagsContainer"></div>

                        <div class="d-flex gap-2" style="max-width: 20rem;">
                            <input type="number" id="newTenureInput" min="1" max="50" placeholder="e.g. 25" class="shf-input flex-grow-1">
                            <button type="button" id="addTenureBtn" class="btn-accent btn-accent-sm">+ Add</button>
                        </div>

                        @if(auth()->user()->hasPermission('edit_tenures'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save Tenures
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Documents --}}
                <div class="settings-tab-pane p-4" id="tab-documents"{!! $activeTab !== 'documents' ? ' style="display:none;"' : '' !!}>
                    <form method="POST" action="{{ route('settings.documents') }}" id="documentsForm">
                        @csrf
                        <p class="small mb-3" style="color: #6b7280;">Required documents per business type (bilingual: English + Gujarati).</p>

                        <!-- Sub-tabs for document types -->
                        <div class="d-flex gap-1 mb-3" style="border-bottom: 1px solid var(--border);">
                            @foreach(['proprietor' => 'Proprietor', 'partnership_llp' => 'Partnership / LLP', 'pvt_ltd' => 'PVT LTD'] as $docType => $docLabel)
                                <button type="button" class="doc-sub-tab small fw-semibold" data-doc-type="{{ $docType }}" style="padding:8px 12px;border:none;border-bottom:2px solid transparent;background:transparent;color:#6b7280;border-radius:4px 4px 0 0;cursor:pointer;">
                                    {{ $docLabel }}
                                </button>
                            @endforeach
                        </div>

                        @foreach(['proprietor', 'partnership_llp', 'pvt_ltd'] as $docType)
                            <div class="doc-type-pane" id="docPane-{{ $docType }}" style="display:none;">
                                <div class="d-flex flex-column gap-2 mb-3" id="docList-{{ $docType }}"></div>

                                <div class="d-flex gap-2">
                                    <input type="text" class="shf-input flex-grow-1 newDocEn" placeholder="Document name (English)">
                                    <input type="text" class="shf-input flex-grow-1 newDocGu" placeholder="દસ્તાવેજ નામ (Gujarati)">
                                    <button type="button" class="btn-accent btn-accent-sm addDocBtn" data-doc-type="{{ $docType }}">+ Add</button>
                                </div>
                            </div>
                        @endforeach

                        @if(auth()->user()->hasPermission('edit_documents'))
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn-accent">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Save Documents
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function () {
        // ============================================================
        //  TAB SWITCHING
        // ============================================================
        $('.shf-tabs').on('click', '.shf-tab', function () {
            var tab = $(this).data('tab');
            $('.shf-tab').removeClass('active');
            $(this).addClass('active');
            $('.settings-tab-pane').hide();
            $('#tab-' + tab).show();
        });

        // ============================================================
        //  BANKS MANAGER
        // ============================================================
        var banks = @json($config['banks'] ?? []);

        function renderBankTags() {
            var html = '';
            $.each(banks, function (idx, bank) {
                html += '<span class="shf-tag">' +
                    '<span>' + $('<span>').text(bank).html() + '</span>' +
                    '<input type="hidden" name="banks[]" value="' + $('<span>').text(bank).html() + '">' +
                    '<button type="button" class="shf-tag-remove removeBankBtn" data-idx="' + idx + '">&times;</button>' +
                    '</span>';
            });
            $('#bankTagsContainer').html(html);
        }
        renderBankTags();

        $('#addBankBtn').on('click', function () {
            var val = $.trim($('#newBankInput').val());
            if (val && $.inArray(val, banks) === -1) {
                banks.push(val);
                renderBankTags();
                $('#newBankInput').val('');
            }
        });
        $('#newBankInput').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#addBankBtn').click(); }
        });
        $(document).on('click', '.removeBankBtn', function () {
            banks.splice($(this).data('idx'), 1);
            renderBankTags();
        });

        // ============================================================
        //  TENURES MANAGER
        // ============================================================
        var tenures = @json($config['tenures'] ?? []);

        function renderTenureTags() {
            var html = '';
            $.each(tenures, function (idx, t) {
                html += '<span class="shf-tag" style="background:#f0fdf4;border-color:#86efac;color:#16a34a;">' +
                    '<span>' + t + ' Years</span>' +
                    '<input type="hidden" name="tenures[]" value="' + t + '">' +
                    '<button type="button" class="shf-tag-remove removeTenureBtn" data-idx="' + idx + '" style="background:#16a34a;">&times;</button>' +
                    '</span>';
            });
            $('#tenureTagsContainer').html(html);
        }
        renderTenureTags();

        $('#addTenureBtn').on('click', function () {
            var val = parseInt($('#newTenureInput').val());
            if (val && val > 0 && $.inArray(val, tenures) === -1) {
                tenures.push(val);
                tenures.sort(function (a, b) { return a - b; });
                renderTenureTags();
                $('#newTenureInput').val('');
            }
        });
        $('#newTenureInput').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#addTenureBtn').click(); }
        });
        $(document).on('click', '.removeTenureBtn', function () {
            tenures.splice($(this).data('idx'), 1);
            renderTenureTags();
        });

        // ============================================================
        //  BANK CHARGES MANAGER
        // ============================================================
        var charges = @json($bankCharges->map(fn($c) => $c->toArray())->values());

        function renderChargeRows() {
            var html = '';
            $.each(charges, function (idx, c) {
                html += '<tr>' +
                    '<td><input type="text" name="charges[' + idx + '][bank_name]" value="' + (c.bank_name || '') + '" class="shf-input small" style="width:8rem;" required></td>' +
                    '<td><input type="number" name="charges[' + idx + '][pf]" value="' + (c.pf || 0) + '" step="0.01" class="shf-input small" style="width:6rem;" required></td>' +
                    '<td><input type="number" name="charges[' + idx + '][admin]" value="' + (c.admin || 0) + '" class="shf-input small" style="width:5rem;" required></td>' +
                    '<td><input type="number" name="charges[' + idx + '][stamp_notary]" value="' + (c.stamp_notary || 0) + '" class="shf-input small" style="width:5rem;" required></td>' +
                    '<td><input type="number" name="charges[' + idx + '][registration_fee]" value="' + (c.registration_fee || 0) + '" class="shf-input small" style="width:5rem;" required></td>' +
                    '<td><input type="number" name="charges[' + idx + '][advocate]" value="' + (c.advocate || 0) + '" class="shf-input small" style="width:5rem;" required></td>' +
                    '<td><input type="number" name="charges[' + idx + '][tc]" value="' + (c.tc || 0) + '" class="shf-input small" style="width:5rem;" required></td>' +
                    '<td>' +
                    '<input type="hidden" name="charges[' + idx + '][extra1_name]" value="' + (c.extra1_name || '') + '">' +
                    '<input type="hidden" name="charges[' + idx + '][extra1_amt]" value="' + (c.extra1_amt || 0) + '">' +
                    '<input type="hidden" name="charges[' + idx + '][extra2_name]" value="' + (c.extra2_name || '') + '">' +
                    '<input type="hidden" name="charges[' + idx + '][extra2_amt]" value="' + (c.extra2_amt || 0) + '">' +
                    '<button type="button" class="removeChargeBtn fw-bold" data-idx="' + idx + '" style="background:none;border:none;color:#c0392b;font-size:1.2rem;cursor:pointer;">&times;</button>' +
                    '</td></tr>';
            });
            $('#bankChargesBody').html(html);
        }
        renderChargeRows();

        $('#addChargeRowBtn').on('click', function () {
            charges.push({ bank_name: '', pf: 0, admin: 0, stamp_notary: 0, registration_fee: 0, advocate: 0, tc: 0, extra1_name: '', extra1_amt: 0, extra2_name: '', extra2_amt: 0 });
            renderChargeRows();
        });
        $(document).on('click', '.removeChargeBtn', function () {
            charges.splice($(this).data('idx'), 1);
            renderChargeRows();
        });

        // ============================================================
        //  DOCUMENTS MANAGER
        // ============================================================
        var docs = {
            proprietor: { en: @json($config['documents_en']['proprietor'] ?? []), gu: @json($config['documents_gu']['proprietor'] ?? []) },
            partnership_llp: { en: @json($config['documents_en']['partnership_llp'] ?? []), gu: @json($config['documents_gu']['partnership_llp'] ?? []) },
            pvt_ltd: { en: @json($config['documents_en']['pvt_ltd'] ?? []), gu: @json($config['documents_gu']['pvt_ltd'] ?? []) }
        };
        var currentDocTab = 'proprietor';

        function renderDocList(type) {
            var html = '';
            $.each(docs[type].en, function (idx, enVal) {
                var guVal = docs[type].gu[idx] || '';
                html += '<div class="d-flex align-items-center gap-2 p-3 rounded" style="background:var(--bg);border:1px solid var(--border);">' +
                    '<span class="fw-bold" style="font-size:0.75rem;color:#f15a29;width:1.5rem;">' + (idx + 1) + '.</span>' +
                    '<input type="text" name="documents_en[' + type + '][]" value="' + $('<span>').text(enVal).html() + '" class="shf-input flex-grow-1 small" placeholder="English">' +
                    '<input type="text" name="documents_gu[' + type + '][]" value="' + $('<span>').text(guVal).html() + '" class="shf-input flex-grow-1 small" placeholder="Gujarati">' +
                    '<button type="button" class="removeDocBtn fw-bold" data-type="' + type + '" data-idx="' + idx + '" style="background:none;border:none;color:#c0392b;font-size:1.2rem;cursor:pointer;">&times;</button>' +
                    '</div>';
            });
            $('#docList-' + type).html(html);
        }

        function switchDocTab(type) {
            currentDocTab = type;
            $('.doc-sub-tab').css({ 'border-bottom-color': 'transparent', 'color': '#6b7280', 'background': 'transparent' });
            $('.doc-sub-tab[data-doc-type="' + type + '"]').css({ 'border-bottom-color': '#f15a29', 'color': '#f15a29', 'background': 'rgba(241,90,41,0.05)' });
            $('.doc-type-pane').hide();
            $('#docPane-' + type).show();
            renderDocList(type);
        }
        switchDocTab('proprietor');

        $(document).on('click', '.doc-sub-tab', function () {
            switchDocTab($(this).data('doc-type'));
        });

        $(document).on('click', '.addDocBtn', function () {
            var type = $(this).data('doc-type');
            var $pane = $('#docPane-' + type);
            var enVal = $.trim($pane.find('.newDocEn').val());
            var guVal = $.trim($pane.find('.newDocGu').val());
            if (enVal) {
                docs[type].en.push(enVal);
                docs[type].gu.push(guVal || enVal);
                $pane.find('.newDocEn').val('');
                $pane.find('.newDocGu').val('');
                renderDocList(type);
            }
        });

        $(document).on('click', '.removeDocBtn', function () {
            var type = $(this).data('type');
            var idx = $(this).data('idx');
            docs[type].en.splice(idx, 1);
            docs[type].gu.splice(idx, 1);
            renderDocList(type);
        });
    });
    </script>
    @endpush
@endsection
