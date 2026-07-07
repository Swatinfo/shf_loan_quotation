@extends('layouts.app')
@section('title', 'DVR — SHF')

@section('header')
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Daily Visit Reports
        </h2>
        @if (auth()->user()->hasPermission('create_dvr'))
            <button class="btn-accent btn-accent-sm" data-bs-toggle="modal" data-bs-target="#dvrModal"
                onclick="resetDvrForm()">
                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Visit
            </button>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/css/dataTables.bootstrap5.min.css') }}">
@endpush

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            {{-- Filters --}}
            <div class="shf-section mb-3">
                <div class="shf-section-header shf-collapsible shf-filter-collapse shf-clickable d-flex align-items-center gap-2"
                    data-target="#dvrFilterBody">
                    <svg class="shf-collapse-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="shf-section-title">Filters</span>
                    <span id="dvrFilterCount" class="shf-filter-count shf-collapse-hidden">0</span>
                </div>
                <div id="dvrFilterBody" class="shf-section-body shf-filter-body-collapse">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">View</label>
                            <select id="filterView" class="shf-input">
                                <option value="my_visits">My Visits</option>
                                @if ($isBdh || $isBranchManager)
                                    <option value="my_branch">My Branch</option>
                                @endif
                                @if ($canViewAll)
                                    <option value="all">All Visits</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Contact Type</label>
                            <select id="filterContactType" class="shf-input">
                                <option value="">All</option>
                                @foreach ($contactTypes as $ct)
                                    <option value="{{ $ct['key'] }}">{{ $ct['label_en'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Purpose</label>
                            <select id="filterPurpose" class="shf-input">
                                <option value="">All</option>
                                @foreach ($purposes as $p)
                                    <option value="{{ $p['key'] }}">{{ $p['label_en'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Follow-up</label>
                            <select id="filterFollowUp" class="shf-input">
                                <option value="active" selected>Active</option>
                                <option value="pending">Pending Follow-ups</option>
                                <option value="overdue">Overdue Follow-ups</option>
                                <option value="done">Completed Follow-ups</option>
                                <option value="all">All (incl. completed)</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">From</label>
                            <input type="text" id="filterDateFrom" class="shf-input shf-datepicker-past" autocomplete="off" placeholder="dd/mm/yyyy">
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">To</label>
                            <input type="text" id="filterDateTo" class="shf-input shf-datepicker-past" autocomplete="off" placeholder="dd/mm/yyyy">
                        </div>
                        @if ($canViewAll || $isBdh || $isBranchManager)
                            <div class="col-6 col-md-auto">
                                <label class="shf-form-label d-block mb-1">User</label>
                                <select id="filterUser" class="shf-input">
                                    <option value="">All Users</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">&nbsp;</label>
                            <input type="text" id="filterSearch" placeholder="Search..." class="shf-input">
                        </div>
                        <div class="col-12 col-md-auto d-flex gap-2">
                            <label class="shf-form-label d-block mb-1">&nbsp;</label>
                            <button type="button" id="btnFilter" class="btn-accent btn-accent-sm">
                                <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Filter
                            </button>
                            <button type="button" id="btnClear" class="btn-accent-outline btn-accent-sm">
                                <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table + Mobile Cards --}}
            <div id="dvrTableSection" class="shf-section shf-dt-section">
                <div id="dvrMobileCards" class="d-md-none"></div>
                <div class="table-responsive d-none d-md-block">
                    <table id="dvrTable" class="table table-hover mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Purpose</th>
                                <th>Follow-up</th>
                                <th>Created</th>
                                <th style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- Empty State --}}
            <div id="dvrEmptyState" class="shf-collapse-hidden">
                <div class="shf-section">
                    <div class="p-5 text-center">
                        <div class="shf-stat-icon mx-auto mb-3">
                            <svg class="shf-icon-xl" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="font-display fw-semibold shf-text-lg shf-text-dark-alt">No visit reports found</h3>
                        <p class="mt-1 small shf-text-gray">Log your first daily visit to get started.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Create/Edit DVR Modal --}}
    @if (auth()->user()->hasPermission('create_dvr'))
        <div class="modal fade" id="dvrModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border: none; border-radius: 12px;">
                    <form id="dvrForm" method="POST" action="{{ route('dvr.store') }}">
                        @csrf
                        <input type="hidden" name="_method" id="dvrFormMethod" value="POST">
                        <input type="hidden" name="parent_visit_id" id="dvrParentVisitId" value="">
                        <div class="modal-header"
                            style="background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;">
                            <h5 class="modal-title font-display" id="dvrModalTitle">New Visit / નવી મુલાકાત</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            @if ($errors->any())
                                <div class="alert alert-danger mb-3">
                                    <ul class="mb-0 small">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="shf-form-label">Contact Phone</label>
                                    <input type="text" name="contact_phone" id="dvrContactPhone" class="shf-input"
                                        maxlength="20" value="{{ old('contact_phone') }}" placeholder="Search by phone or name..."
                                        autocomplete="off">
                                    <div id="dvrContactResults" class="position-relative">
                                        <div id="dvrContactDropdown" class="dropdown-menu w-100 shadow"
                                            style="max-height:220px; overflow-y:auto;"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="shf-form-label">Contact Name <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_name" id="dvrContactName" class="shf-input" required
                                        maxlength="255" value="{{ old('contact_name') }}" placeholder="Search by name..." autocomplete="off">
                                    <div id="dvrContactNameResults" class="position-relative">
                                        <div id="dvrContactNameDropdown" class="dropdown-menu w-100 shadow"
                                            style="max-height:220px; overflow-y:auto;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="shf-form-label">Contact Type <span class="text-danger">*</span></label>
                                    <select name="contact_type" id="dvrContactType" class="shf-input" required>
                                        <option value="">Select...</option>
                                        @foreach ($contactTypes as $ct)
                                            <option value="{{ $ct['key'] }}" {{ old('contact_type') === $ct['key'] ? 'selected' : '' }}>
                                                {{ $ct['label_en'] }} / {{ $ct['label_gu'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="shf-form-label">Purpose <span class="text-danger">*</span></label>
                                    <select name="purpose" id="dvrPurpose" class="shf-input" required>
                                        <option value="">Select...</option>
                                        @foreach ($purposes as $p)
                                            <option value="{{ $p['key'] }}" {{ old('purpose') === $p['key'] ? 'selected' : '' }}>
                                                {{ $p['label_en'] }} / {{ $p['label_gu'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="shf-form-label">Visit Date <span class="text-danger">*</span></label>
                                    <input type="text" name="visit_date" id="dvrVisitDate" class="shf-input shf-datepicker-past"
                                        autocomplete="off" placeholder="dd/mm/yyyy" required value="{{ old('visit_date') }}">
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="shf-form-label">Notes</label>
                                    <textarea name="notes" id="dvrNotes" class="shf-input" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="shf-form-label">Outcome</label>
                                    <textarea name="outcome" id="dvrOutcome" class="shf-input" rows="3" maxlength="5000">{{ old('outcome') }}</textarea>
                                </div>
                            </div>

                            {{-- Follow-up section --}}
                            <div class="mt-3 p-3 rounded" style="background:var(--bg);border:1px solid var(--border);">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="follow_up_needed" id="dvrFollowUpNeeded" value="1"
                                        {{ old('follow_up_needed') ? 'checked' : '' }}>
                                    <label class="form-check-label shf-form-label" for="dvrFollowUpNeeded">
                                        Follow-up Needed / ફોલો-અપ જરૂરી
                                    </label>
                                </div>
                                <div id="dvrFollowUpFields" class="row g-3" style="{{ old('follow_up_needed') ? '' : 'display:none;' }}">
                                    <div class="col-md-4">
                                        <label class="shf-form-label">Follow-up Date</label>
                                        <input type="text" name="follow_up_date" id="dvrFollowUpDate" class="shf-input shf-datepicker-future"
                                            autocomplete="off" placeholder="dd/mm/yyyy" value="{{ old('follow_up_date') }}">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="shf-form-label">Follow-up Notes</label>
                                        <input type="text" name="follow_up_notes" id="dvrFollowUpNotes" class="shf-input"
                                            maxlength="5000" value="{{ old('follow_up_notes') }}" placeholder="What to do on follow-up...">
                                    </div>
                                </div>
                            </div>

                            {{-- Link to Loan/Quotation --}}
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="shf-form-label">Link to Loan (optional)</label>
                                    <input type="text" id="dvrLoanSearch" class="shf-input"
                                        placeholder="Search loan #, app # or customer..." autocomplete="off">
                                    <input type="hidden" name="loan_id" id="dvrLoanId">
                                    <div id="dvrLoanResults" class="position-relative">
                                        <div id="dvrLoanDropdown" class="dropdown-menu w-100 shadow"
                                            style="max-height:200px; overflow-y:auto;"></div>
                                    </div>
                                    <div id="dvrLoanChip" class="d-none mt-2">
                                        <span class="shf-badge shf-badge-blue shf-text-xs" id="dvrLoanChipText"></span>
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1"
                                            onclick="clearDvrLoanLink()">&times; Remove</button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="shf-form-label">Link to Quotation (optional)</label>
                                    <input type="text" id="dvrQuotationSearch" class="shf-input"
                                        placeholder="Search customer name..." autocomplete="off">
                                    <input type="hidden" name="quotation_id" id="dvrQuotationId">
                                    <div id="dvrQuotationResults" class="position-relative">
                                        <div id="dvrQuotationDropdown" class="dropdown-menu w-100 shadow"
                                            style="max-height:200px; overflow-y:auto;"></div>
                                    </div>
                                    <div id="dvrQuotationChip" class="d-none mt-2">
                                        <span class="shf-badge shf-badge-gray shf-text-xs" id="dvrQuotationChipText"></span>
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1"
                                            onclick="clearDvrQuotationLink()">&times; Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-accent-outline btn-accent-sm"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-accent btn-accent-sm">Save Visit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation --}}
    <form id="deleteDvrForm" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    {{-- Follow-up Visit Modal (from listing) --}}
    @if (auth()->user()->hasPermission('create_dvr'))
        <div class="modal fade" id="listFollowUpModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border: none; border-radius: 12px;">
                    <form id="listFollowUpForm" method="POST" action="{{ route('dvr.store') }}">
                        @csrf
                        <input type="hidden" name="parent_visit_id" id="listFuParentId" value="">
                        <div class="modal-header" style="background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;">
                            <h5 class="modal-title font-display">Log Follow-up Visit / ફોલો-અપ મુલાકાત</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="shf-form-label">Contact Name <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_name" id="listFuContactName" class="shf-input" required maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="shf-form-label">Contact Phone</label>
                                    <input type="text" name="contact_phone" id="listFuContactPhone" class="shf-input" maxlength="20">
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="shf-form-label">Contact Type <span class="text-danger">*</span></label>
                                    <select name="contact_type" id="listFuContactType" class="shf-input" required>
                                        @foreach ($contactTypes as $ct)
                                            <option value="{{ $ct['key'] }}">{{ $ct['label_en'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="shf-form-label">Purpose <span class="text-danger">*</span></label>
                                    <select name="purpose" class="shf-input" required>
                                        @foreach ($purposes as $p)
                                            <option value="{{ $p['key'] }}" {{ $p['key'] === 'follow_up' ? 'selected' : '' }}>{{ $p['label_en'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="shf-form-label">Visit Date <span class="text-danger">*</span></label>
                                    <input type="text" name="visit_date" class="shf-input shf-datepicker-past" autocomplete="off" required>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="shf-form-label">Notes</label>
                                    <textarea name="notes" class="shf-input" rows="3" maxlength="5000"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="shf-form-label">Outcome</label>
                                    <textarea name="outcome" class="shf-input" rows="3" maxlength="5000"></textarea>
                                </div>
                            </div>
                            <div class="mt-3 p-3 rounded" style="background:var(--bg);border:1px solid var(--border);">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="follow_up_needed" id="listFuFollowUpNeeded" value="1">
                                    <label class="form-check-label shf-form-label" for="listFuFollowUpNeeded">Another Follow-up Needed</label>
                                </div>
                                <div id="listFuFollowUpFields" class="row g-3" style="display:none;">
                                    <div class="col-md-4">
                                        <label class="shf-form-label">Follow-up Date</label>
                                        <input type="text" name="follow_up_date" id="listFuFollowUpDate" class="shf-input shf-datepicker-future" autocomplete="off">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="shf-form-label">Follow-up Notes</label>
                                        <input type="text" name="follow_up_notes" class="shf-input" maxlength="5000">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-accent-outline btn-accent-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-accent btn-accent-sm">Save Follow-up Visit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script src="{{ asset('vendor/datatables/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
    <script>
        $(function() {
            // ── Datepicker init ──
            $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });
            $('.shf-datepicker-past').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, endDate: '+0d' });
            $('.shf-datepicker-future').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, startDate: '+1d' });

            @if ($errors->any())
                $('#dvrModal').modal('show');
            @endif

            // Auto-open create modal when ?create=1
            if (new URLSearchParams(window.location.search).get('create') === '1') {
                resetDvrForm();
                $('#dvrModal').modal('show');
                // Clean URL
                history.replaceState(null, '', window.location.pathname);
            }

            // Follow-up toggle with +7 day default
            $('#dvrFollowUpNeeded').on('change', function() {
                $('#dvrFollowUpFields').toggle(this.checked);
                if (this.checked && !$('#dvrFollowUpDate').val()) {
                    var d = new Date(); d.setDate(d.getDate() + 7);
                    var dd = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                    $('#dvrFollowUpDate').datepicker('update', dd);
                }
            });

            // DataTable
            var viewIcon = '<svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
            var followUpIcon = '<svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            var deleteIcon = '<svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';

            var table = $('#dvrTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('dvr.data')),
                    data: function(d) {
                        d.view = $('#filterView').val();
                        d.contact_type = $('#filterContactType').val();
                        d.purpose = $('#filterPurpose').val();
                        d.follow_up = $('#filterFollowUp').val();
                        d.date_from = $('#filterDateFrom').val();
                        d.date_to = $('#filterDateTo').val();
                        d.user_id = $('#filterUser').val() || '';
                    }
                },
                columns: [
                    { data: 'visit_date', render: function(data, type, row) {
                        return '<a href="' + row.show_url + '" class="fw-medium text-decoration-none" style="color:var(--primary-dark-solid);">' + data + '</a>';
                    }},
                    { data: 'contact_name', render: function(data, type, row) {
                        var html = '<strong>' + data + '</strong>';
                        if (row.contact_phone) html += '<br><small class="text-muted">' + row.contact_phone + '</small>';
                        if (row.user_name && $('#filterView').val() !== 'my_visits') html += '<br><small class="shf-text-xs text-muted">by ' + row.user_name + '</small>';
                        return html;
                    }},
                    { data: 'contact_type' },
                    { data: 'purpose' },
                    { data: 'follow_up_html' },
                    { data: 'created_at' },
                    { data: null, orderable: false, searchable: false, className: 'text-end', render: function(data, type, row) {
                        var html = '<div class="d-flex gap-1 justify-content-end flex-wrap">';
                        html += '<a href="' + row.show_url + '" class="btn-accent-sm shf-text-xs">' + viewIcon + ' View</a>';
                        if (row.follow_up_needed && !row.is_follow_up_done && row.can_edit) {
                            html += '<button class="btn-accent-sm shf-text-xs btn-accent-outline shf-dvr-followup-btn" data-id="' + row.id + '" data-name="' + row.contact_name.replace(/"/g, '&quot;') + '" data-phone="' + row.contact_phone.replace(/"/g, '&quot;') + '" data-type="' + row.contact_type_key + '">' + followUpIcon + ' Follow-up</button>';
                        }
                        if (row.can_delete) {
                            html += '<button class="btn-accent-sm shf-text-xs shf-btn-danger-alt" onclick="deleteDvr(' + row.id + ')">' + deleteIcon + ' Del</button>';
                        }
                        html += '</div>';
                        return html;
                    }}
                ],
                order: [[4, 'asc']],
                pageLength: 50,
                dom: 'rt<"shf-dt-bottom"ip>',
                language: {
                    processing: '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>',
                    emptyTable: ' ',
                    zeroRecords: ' ',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    infoEmpty: '',
                    infoFiltered: '(filtered from _MAX_)',
                    paginate: { previous: '&laquo;', next: '&raquo;' }
                },
                createdRow: function(row, data) {
                    if (data.follow_up_urgency === 'overdue') {
                        $(row).css('background-color', 'rgba(220, 53, 69, 0.08)').css('border-left', '3px solid #dc3545');
                    } else if (data.follow_up_urgency === 'due_today') {
                        $(row).css('background-color', 'rgba(255, 193, 7, 0.10)').css('border-left', '3px solid #ffc107');
                    } else if (data.follow_up_urgency === 'due_tomorrow') {
                        $(row).css('background-color', 'rgba(255, 193, 7, 0.05)').css('border-left', '3px solid #ffe082');
                    } else if (data.follow_up_urgency === 'due_soon') {
                        $(row).css('border-left', '3px solid #3b82f6');
                    }
                },
                drawCallback: function(settings) {
                    var total = settings._iRecordsDisplay;
                    var hasData = total > 0;
                    $('#dvrTableSection').toggle(hasData);
                    $('#dvrTable_wrapper .shf-dt-bottom').toggle(hasData);
                    if (!hasData) {
                        $('#dvrEmptyState').show();
                        $('#dvrMobileCards').html('');
                    } else {
                        $('#dvrEmptyState').hide();
                        var data = this.api().rows({ page: 'current' }).data();
                        var html = '';
                        for (var i = 0; i < data.length; i++) {
                            var d = data[i];
                            var cardStyle = '';
                            if (d.follow_up_urgency === 'overdue') {
                                cardStyle = 'border-left:3px solid #dc3545;background:rgba(220,53,69,0.08);';
                            } else if (d.follow_up_urgency === 'due_today') {
                                cardStyle = 'border-left:3px solid #ffc107;background:rgba(255,193,7,0.10);';
                            } else if (d.follow_up_urgency === 'due_tomorrow') {
                                cardStyle = 'border-left:3px solid #ffe082;background:rgba(255,193,7,0.05);';
                            } else if (d.follow_up_urgency === 'due_soon') {
                                cardStyle = 'border-left:3px solid #3b82f6;';
                            }
                            html += '<div class="shf-card mb-2 p-3" style="' + cardStyle + '">'
                                + '<div class="d-flex justify-content-between align-items-start mb-1">'
                                + '<a href="' + d.show_url + '" class="fw-medium text-decoration-none" style="color:var(--primary-dark-solid);">' + d.contact_name + '</a>'
                                + '<small class="text-muted">' + d.visit_date + '</small>'
                                + '</div>';
                            if (d.contact_phone) html += '<small class="text-muted d-block mb-1">' + d.contact_phone + '</small>';
                            html += '<div class="d-flex flex-wrap gap-2 mt-1 align-items-center">'
                                + '<span class="shf-badge shf-badge-blue shf-text-2xs">' + d.contact_type + '</span>'
                                + '<span class="shf-badge shf-badge-gray shf-text-2xs">' + d.purpose + '</span>';
                            if (d.notes) html += '<small class="text-muted">' + d.notes + '</small>';
                            html += '</div>';
                            if (d.follow_up_needed) html += '<div class="mt-1">' + d.follow_up_html + '</div>';
                            if (d.loan_info) html += '<div class="mt-1">' + d.loan_info + '</div>';
                            html += '<div class="d-flex gap-1 mt-2 flex-wrap">'
                                + '<a href="' + d.show_url + '" class="btn-accent-sm shf-text-xs">' + viewIcon + ' View</a>';
                            if (d.follow_up_needed && !d.is_follow_up_done && d.can_edit) {
                                html += '<button class="btn-accent-sm shf-text-xs btn-accent-outline shf-dvr-followup-btn" data-id="' + d.id + '" data-name="' + d.contact_name.replace(/"/g, '&quot;') + '" data-phone="' + d.contact_phone.replace(/"/g, '&quot;') + '" data-type="' + d.contact_type_key + '">' + followUpIcon + ' Follow-up</button>';
                            }
                            if (d.can_delete) {
                                html += '<button class="btn-accent-sm shf-text-xs shf-btn-danger-alt" onclick="deleteDvr(' + d.id + ')">' + deleteIcon + ' Del</button>';
                            }
                            html += '</div></div>';
                        }
                        $('#dvrMobileCards').html(html);
                    }
                }
            });

            // Filters
            $('#btnFilter').on('click', function() { table.draw(); updateFilterCount(); });
            $('#btnClear').on('click', function() {
                $('#filterView').val('my_visits');
                $('#filterContactType, #filterPurpose, #filterUser').val('');
                $('#filterFollowUp').val('active');
                $('#filterDateFrom, #filterDateTo, #filterSearch').val('');
                table.search('').draw();
                updateFilterCount();
            });
            $('#filterSearch').on('keyup', function(e) {
                if (e.key === 'Enter') table.search(this.value).draw();
            });

            function updateFilterCount() {
                var count = 0;
                if ($('#filterView').val() !== 'my_visits') count++;
                if ($('#filterContactType').val()) count++;
                if ($('#filterPurpose').val()) count++;
                if ($('#filterFollowUp').val() && $('#filterFollowUp').val() !== 'active') count++;
                if ($('#filterDateFrom').val()) count++;
                if ($('#filterDateTo').val()) count++;
                if ($('#filterUser').val()) count++;
                var badge = $('#dvrFilterCount');
                badge.text(count);
                count > 0 ? badge.removeClass('shf-collapse-hidden') : badge.addClass('shf-collapse-hidden');
            }

            // Loan Search
            var loanTimer;
            $('#dvrLoanSearch').on('input', function() {
                clearTimeout(loanTimer);
                var q = $(this).val().trim();
                if (q.length < 2) { $('#dvrLoanDropdown').removeClass('show').empty(); return; }
                loanTimer = setTimeout(function() {
                    $.get(@json(route('dvr.search-loans')), { q: q }, function(loans) {
                        var $dd = $('#dvrLoanDropdown');
                        if (!loans.length) { $dd.html('<span class="dropdown-item text-muted">No loans found</span>').addClass('show'); return; }
                        var html = '';
                        loans.forEach(function(loan) {
                            var label = '#' + loan.loan_number;
                            if (loan.application_number) label += ' / App: ' + loan.application_number;
                            label += ' — ' + loan.customer_name;
                            if (loan.bank_name) label += ' (' + loan.bank_name + ')';
                            html += '<a class="dropdown-item shf-dvr-loan-pick" href="#" data-id="' + loan.id + '" data-label="' + label.replace(/"/g, '&quot;') + '">' + label + '</a>';
                        });
                        $dd.html(html).addClass('show');
                    });
                }, 300);
            });
            $(document).on('click', '.shf-dvr-loan-pick', function(e) {
                e.preventDefault();
                $('#dvrLoanId').val($(this).data('id'));
                $('#dvrLoanSearch').val('').hide();
                $('#dvrLoanChipText').text($(this).data('label'));
                $('#dvrLoanChip').removeClass('d-none');
                $('#dvrLoanDropdown').removeClass('show').empty();
            });

            // Quotation Search
            var quotTimer;
            $('#dvrQuotationSearch').on('input', function() {
                clearTimeout(quotTimer);
                var q = $(this).val().trim();
                if (q.length < 2) { $('#dvrQuotationDropdown').removeClass('show').empty(); return; }
                quotTimer = setTimeout(function() {
                    $.get(@json(route('dvr.search-quotations')), { q: q }, function(quotations) {
                        var $dd = $('#dvrQuotationDropdown');
                        if (!quotations.length) { $dd.html('<span class="dropdown-item text-muted">No quotations found</span>').addClass('show'); return; }
                        var html = '';
                        quotations.forEach(function(q) {
                            var label = 'Q#' + q.id + ' — ' + q.customer_name;
                            html += '<a class="dropdown-item shf-dvr-quotation-pick" href="#" data-id="' + q.id + '" data-label="' + label.replace(/"/g, '&quot;') + '">' + label + '</a>';
                        });
                        $dd.html(html).addClass('show');
                    });
                }, 300);
            });
            $(document).on('click', '.shf-dvr-quotation-pick', function(e) {
                e.preventDefault();
                $('#dvrQuotationId').val($(this).data('id'));
                $('#dvrQuotationSearch').val('').hide();
                $('#dvrQuotationChipText').text($(this).data('label'));
                $('#dvrQuotationChip').removeClass('d-none');
                $('#dvrQuotationDropdown').removeClass('show').empty();
            });

            // Contact Search (phone or name) — dropdown shown below the input being typed in
            var contactTimer;
            $('#dvrContactPhone, #dvrContactName').on('input', function() {
                clearTimeout(contactTimer);
                var $input = $(this);
                var dropdownSel = $input.is('#dvrContactPhone') ? '#dvrContactDropdown' : '#dvrContactNameDropdown';
                var otherSel = $input.is('#dvrContactPhone') ? '#dvrContactNameDropdown' : '#dvrContactDropdown';
                var q = $input.val().trim();
                var $dd = $(dropdownSel);
                $(otherSel).removeClass('show').empty();
                if (q.length < 2) { $dd.removeClass('show').empty(); return; }
                contactTimer = setTimeout(function() {
                    $.get(@json(route('dvr.search-contacts')), { q: q }, function(contacts) {
                        if (!contacts.length) { $dd.removeClass('show').empty(); return; }
                        var html = '';
                        contacts.forEach(function(c) {
                            var label = '<strong>' + $('<span>').text(c.name).html() + '</strong>';
                            if (c.phone) label += ' <span class="text-muted">' + $('<span>').text(c.phone).html() + '</span>';
                            label += ' <span class="shf-badge shf-badge-gray shf-text-2xs">' + c.source + '</span>';
                            html += '<a class="dropdown-item shf-dvr-contact-pick py-2" href="#" data-name="' + $('<span>').text(c.name).html().replace(/"/g, '&quot;')
                                + '" data-phone="' + $('<span>').text(c.phone || '').html().replace(/"/g, '&quot;')
                                + '" data-type="' + (c.type || '') + '">'
                                + label + '</a>';
                        });
                        $dd.html(html).addClass('show');
                    });
                }, 300);
            });
            $(document).on('click', '.shf-dvr-contact-pick', function(e) {
                e.preventDefault();
                $('#dvrContactName').val($(this).data('name'));
                $('#dvrContactPhone').val($(this).data('phone'));
                var contactType = $(this).data('type');
                if (contactType && $('#dvrContactType option[value="' + contactType + '"]').length) {
                    $('#dvrContactType').val(contactType);
                }
                $('#dvrContactDropdown, #dvrContactNameDropdown').removeClass('show').empty();
            });

            // Close dropdowns on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#dvrContactResults, #dvrContactNameResults, #dvrContactPhone, #dvrContactName').length) {
                    $('#dvrContactDropdown, #dvrContactNameDropdown').removeClass('show');
                }
                if (!$(e.target).closest('#dvrLoanResults, #dvrLoanSearch').length) $('#dvrLoanDropdown').removeClass('show');
                if (!$(e.target).closest('#dvrQuotationResults, #dvrQuotationSearch').length) $('#dvrQuotationDropdown').removeClass('show');
            });
        });

        function resetDvrForm() {
            $('#dvrForm').attr('action', @json(route('dvr.store')));
            $('#dvrFormMethod').val('POST');
            $('#dvrParentVisitId').val('');
            $('#dvrModalTitle').text('New Visit / નવી મુલાકાત');
            $('#dvrContactName, #dvrContactPhone, #dvrNotes, #dvrOutcome, #dvrFollowUpNotes').val('');
            $('#dvrContactType, #dvrPurpose').val('');
            $('#dvrFollowUpNeeded').prop('checked', false);
            $('#dvrFollowUpFields').hide();
            $('#dvrFollowUpDate').val('');
            var today = new Date();
            var dd = ('0' + today.getDate()).slice(-2) + '/' + ('0' + (today.getMonth() + 1)).slice(-2) + '/' + today.getFullYear();
            $('#dvrVisitDate').val(dd);
            clearDvrLoanLink();
            clearDvrQuotationLink();
        }

        function clearDvrLoanLink() {
            $('#dvrLoanId').val('');
            $('#dvrLoanSearch').val('').show();
            $('#dvrLoanChip').addClass('d-none');
        }
        function clearDvrQuotationLink() {
            $('#dvrQuotationId').val('');
            $('#dvrQuotationSearch').val('').show();
            $('#dvrQuotationChip').addClass('d-none');
        }

        // ── Follow-up button from listing ──
        $(document).on('click', '.shf-dvr-followup-btn', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var phone = $(this).data('phone');
            var type = $(this).data('type');
            $('#listFuParentId').val(id);
            $('#listFuContactName').val(name);
            $('#listFuContactPhone').val(phone);
            if (type) $('#listFuContactType').val(type);
            // Set today's date
            var today = new Date();
            var dd = ('0' + today.getDate()).slice(-2) + '/' + ('0' + (today.getMonth() + 1)).slice(-2) + '/' + today.getFullYear();
            $('#listFollowUpModal [name="visit_date"]').val(dd);
            // Init datepickers inside modal
            $('#listFollowUpModal .shf-datepicker-past').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, endDate: '+0d' });
            $('#listFollowUpModal .shf-datepicker-future').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, startDate: '+1d' });
            $('#listFollowUpModal').modal('show');
        });

        // Follow-up toggle with +7 day default (listing modal)
        $('#listFuFollowUpNeeded').on('change', function() {
            $('#listFuFollowUpFields').toggle(this.checked);
            if (this.checked && !$('#listFuFollowUpDate').val()) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                var dd = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                $('#listFuFollowUpDate').datepicker('update', dd);
            }
        });

        // Follow-up form validation (listing modal)
        $('#listFollowUpForm').on('submit', function(e) {
            var valid = SHF.validateForm($(this), {
                contact_name: { required: true, maxlength: 255, label: 'Contact Name / સંપર્ક નામ' },
                contact_type: { required: true, label: 'Contact Type / સંપર્ક પ્રકાર' },
                purpose: { required: true, label: 'Purpose / હેતુ' },
                visit_date: { required: true, dateFormat: 'd/m/Y', label: 'Visit Date / મુલાકાત તારીખ' },
                follow_up_date: {
                    label: 'Follow-up Date / ફોલો-અપ તારીખ',
                    custom: function() {
                        if ($('#listFuFollowUpNeeded').is(':checked')) {
                            var val = $('#listFuFollowUpDate').val();
                            if (!val) return 'Follow-up Date is required when follow-up is needed / ફોલો-અપ તારીખ જરૂરી છે';
                            var parts = val.split('/');
                            if (parts.length === 3) {
                                var inputDate = new Date(parts[2], parts[1] - 1, parts[0]);
                                var today = new Date(); today.setHours(0,0,0,0);
                                if (inputDate <= today) return 'Follow-up Date must be a future date / ફોલો-અપ તારીખ ભવિષ્યની હોવી જોઈએ';
                            }
                        }
                        return null;
                    }
                }
            });
            if (!valid) e.preventDefault();
        });

        // ── Form Validation ──
        $('#dvrForm').on('submit', function(e) {
            var valid = SHF.validateForm($(this), {
                contact_name: { required: true, maxlength: 255, label: 'Contact Name / સંપર્ક નામ' },
                contact_type: { required: true, label: 'Contact Type / સંપર્ક પ્રકાર' },
                purpose: { required: true, label: 'Purpose / હેતુ' },
                visit_date: { required: true, dateFormat: 'd/m/Y', label: 'Visit Date / મુલાકાત તારીખ' },
                contact_phone: { maxlength: 20, label: 'Contact Phone / ફોન' },
                notes: { maxlength: 5000, label: 'Notes / નોંધ' },
                outcome: { maxlength: 5000, label: 'Outcome / પરિણામ' },
                follow_up_date: {
                    label: 'Follow-up Date / ફોલો-અપ તારીખ',
                    custom: function(val, $field, $form) {
                        if ($('#dvrFollowUpNeeded').is(':checked')) {
                            if (!val) {
                                return 'Follow-up Date is required when follow-up is needed / ફોલો-અપ તારીખ જરૂરી છે';
                            }
                            // Validate future date
                            var parts = val.split('/');
                            if (parts.length === 3) {
                                var inputDate = new Date(parts[2], parts[1] - 1, parts[0]);
                                var today = new Date(); today.setHours(0,0,0,0);
                                if (inputDate <= today) {
                                    return 'Follow-up Date must be a future date / ફોલો-અપ તારીખ ભવિષ્યની હોવી જોઈએ';
                                }
                            }
                        }
                        return null;
                    }
                }
            });
            if (!valid) e.preventDefault();
        });

        function deleteDvr(id) {
            Swal.fire({
                title: 'Delete Visit Report?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var form = document.getElementById('deleteDvrForm');
                    form.action = '/dvr/' + id;
                    form.submit();
                }
            });
        }
    </script>
@endpush
