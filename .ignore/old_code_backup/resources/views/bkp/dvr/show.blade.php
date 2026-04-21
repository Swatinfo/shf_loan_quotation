@extends('layouts.app')
@section('title', 'Visit: ' . $dvr->contact_name . ' — SHF')

@section('header')
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Visit #{{ $dvr->id }}
        </h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('dvr.index') }}" class="btn-accent-outline-white btn-accent-sm">
                <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            @if ($dvr->isEditableBy(auth()->user()))
                <button class="btn-accent btn-accent-sm" data-bs-toggle="modal" data-bs-target="#editDvrModal">
                    <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </button>
            @endif
            @if ($dvr->follow_up_needed && !$dvr->is_follow_up_done && auth()->user()->hasPermission('create_dvr'))
                <a href="{{ route('dvr.index') }}?follow_up_from={{ $dvr->id }}" class="btn-accent btn-accent-sm"
                    onclick="event.preventDefault(); logFollowUpVisit();">
                    <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Log Follow-up Visit
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5">

        <div class="row g-4">
            {{-- Visit Detail --}}
            <div class="col-lg-8">
                <div class="shf-section">
                    <div class="shf-section-header d-flex align-items-center justify-content-between">
                        <span class="shf-section-title">{{ $dvr->contact_name }}</span>
                        <span class="shf-badge shf-badge-blue">{{ $contactTypeLabels[$dvr->contact_type] ?? $dvr->contact_type }}</span>
                    </div>
                    <div class="shf-section-body">
                        @if ($dvr->contact_phone)
                            <div class="mb-2">
                                <small class="text-muted">Phone:</small>
                                <span>{{ $dvr->contact_phone }}</span>
                            </div>
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-sm-4">
                                <small class="text-muted d-block">Visit Date</small>
                                <strong>{{ $dvr->visit_date->format('d M Y') }}</strong>
                            </div>
                            <div class="col-sm-4">
                                <small class="text-muted d-block">Purpose</small>
                                <span class="shf-badge shf-badge-gray">{{ $purposeLabels[$dvr->purpose] ?? $dvr->purpose }}</span>
                            </div>
                            <div class="col-sm-4">
                                <small class="text-muted d-block">Branch</small>
                                {{ $dvr->branch?->name ?? '—' }}
                            </div>
                        </div>

                        @if ($dvr->notes)
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Notes</small>
                                <p class="mb-0" style="white-space: pre-line;">{{ $dvr->notes }}</p>
                            </div>
                        @endif

                        @if ($dvr->outcome)
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Outcome</small>
                                <p class="mb-0" style="white-space: pre-line;">{{ $dvr->outcome }}</p>
                            </div>
                        @endif

                        {{-- Linked Loan/Quotation --}}
                        @if ($dvr->loan)
                            <div class="mb-3 p-2 rounded" style="background: var(--bg);">
                                <small class="text-muted d-block mb-1">Linked Loan</small>
                                <a href="{{ route('loans.show', $dvr->loan_id) }}" class="text-decoration-none fw-medium">
                                    #{{ $dvr->loan->loan_number }}
                                    @if ($dvr->loan->application_number)
                                        / App: {{ $dvr->loan->application_number }}
                                    @endif
                                    — {{ $dvr->loan->customer_name }}
                                    @if ($dvr->loan->bank_name)
                                        ({{ $dvr->loan->bank_name }})
                                    @endif
                                </a>
                            </div>
                        @endif
                        @if ($dvr->quotation)
                            <div class="mb-3 p-2 rounded" style="background: var(--bg);">
                                <small class="text-muted d-block mb-1">Linked Quotation</small>
                                <a href="{{ route('quotations.show', $dvr->quotation_id) }}" class="text-decoration-none fw-medium">
                                    Q#{{ $dvr->quotation_id }} — {{ $dvr->quotation->customer_name }}
                                </a>
                            </div>
                        @endif

                        {{-- Follow-up Section --}}
                        @if ($dvr->follow_up_needed)
                            <div class="p-3 rounded mt-3" style="background: {{ $dvr->is_follow_up_done ? 'rgba(39,174,96,0.08)' : ($dvr->is_overdue_follow_up ? 'rgba(220,53,69,0.08)' : 'rgba(245,158,11,0.08)') }}; border: 1px solid {{ $dvr->is_follow_up_done ? '#27ae60' : ($dvr->is_overdue_follow_up ? '#dc3545' : '#f59e0b') }};">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <strong>
                                        @if ($dvr->is_follow_up_done)
                                            <span style="color:#27ae60;">Follow-up Done</span>
                                        @elseif ($dvr->is_overdue_follow_up)
                                            <span style="color:#dc3545;">Follow-up Overdue</span>
                                        @else
                                            <span style="color:#f59e0b;">Follow-up Pending</span>
                                        @endif
                                    </strong>
                                    @if (!$dvr->is_follow_up_done && $dvr->isEditableBy(auth()->user()))
                                        <button class="btn-accent-sm shf-btn-success" onclick="markFollowUpDone({{ $dvr->id }})">
                                            <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Mark Done
                                        </button>
                                    @endif
                                </div>
                                @if ($dvr->follow_up_date)
                                    <small class="text-muted">Follow-up Date:</small>
                                    <span class="fw-medium">{{ $dvr->follow_up_date->format('d M Y') }}</span>
                                    @if ($dvr->is_overdue_follow_up)
                                        <span class="shf-badge shf-badge-red shf-text-2xs ms-1">{{ $dvr->follow_up_date->diffInDays(today()) }} days overdue</span>
                                    @endif
                                    <br>
                                @endif
                                @if ($dvr->follow_up_notes)
                                    <small class="text-muted">Notes:</small> {{ $dvr->follow_up_notes }}
                                @endif
                                @if ($dvr->followUpVisit)
                                    <div class="mt-2">
                                        <a href="{{ route('dvr.show', $dvr->follow_up_visit_id) }}" class="btn-accent-sm btn-accent-outline shf-text-xs">
                                            View Follow-up Visit #{{ $dvr->follow_up_visit_id }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Visit Chain Timeline --}}
                @if ($visitChain->count() > 1)
                    <div class="shf-section mt-4">
                        <div class="shf-section-header">
                            <span class="shf-section-title">Visit Timeline</span>
                        </div>
                        <div class="shf-section-body">
                            @foreach ($visitChain as $chainVisit)
                                <div class="d-flex gap-3 mb-3 {{ $chainVisit->id === $dvr->id ? 'p-2 rounded' : '' }}"
                                    style="{{ $chainVisit->id === $dvr->id ? 'background:rgba(241,90,41,0.08);border:1px solid var(--accent);' : '' }}">
                                    <div class="text-center" style="min-width:3rem;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                            style="width:2rem;height:2rem;background:{{ $chainVisit->id === $dvr->id ? 'var(--accent)' : 'var(--bg-alt)' }};color:{{ $chainVisit->id === $dvr->id ? '#fff' : 'var(--text-muted)' }};font-size:0.7rem;font-weight:600;">
                                            {{ $loop->iteration }}
                                        </div>
                                        @if (!$loop->last)
                                            <div style="width:2px;height:1.5rem;background:var(--border);margin:4px auto;"></div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2">
                                            @if ($chainVisit->id === $dvr->id)
                                                <strong class="shf-text-sm">{{ $chainVisit->visit_date->format('d M Y') }}</strong>
                                                <span class="shf-badge shf-badge-orange shf-text-2xs">Current</span>
                                            @else
                                                <a href="{{ route('dvr.show', $chainVisit) }}" class="fw-medium shf-text-sm text-decoration-none">
                                                    {{ $chainVisit->visit_date->format('d M Y') }}
                                                </a>
                                            @endif
                                            <span class="shf-badge shf-badge-gray shf-text-2xs">{{ $purposeLabels[$chainVisit->purpose] ?? $chainVisit->purpose }}</span>
                                        </div>
                                        @if ($chainVisit->notes)
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($chainVisit->notes, 100) }}</small>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                <div class="shf-section">
                    <div class="shf-section-header">
                        <span class="shf-section-title">Details</span>
                    </div>
                    <div class="shf-section-body">
                        <div class="mb-2">
                            <small class="text-muted d-block">Created By</small>
                            {{ $dvr->user?->name ?? '—' }}
                        </div>
                        <div class="mb-2">
                            <small class="text-muted d-block">Created</small>
                            {{ $dvr->created_at->format('d M Y, h:i A') }}
                        </div>
                        @if ($dvr->updated_at->ne($dvr->created_at))
                            <div class="mb-2">
                                <small class="text-muted d-block">Updated</small>
                                {{ $dvr->updated_at->format('d M Y, h:i A') }}
                            </div>
                        @endif
                        @if ($dvr->parentVisit)
                            <div class="mb-2">
                                <small class="text-muted d-block">Follow-up of</small>
                                <a href="{{ route('dvr.show', $dvr->parent_visit_id) }}" class="text-decoration-none">
                                    Visit #{{ $dvr->parent_visit_id }} ({{ $dvr->parentVisit->visit_date->format('d M Y') }})
                                </a>
                            </div>
                        @endif

                        @if ($dvr->isDeletableBy(auth()->user()))
                            <hr style="border-color:var(--border);">
                            <form method="POST" action="{{ route('dvr.destroy', $dvr) }}" class="shf-confirm-delete"
                                data-confirm-title="Delete Visit Report?"
                                data-confirm-text="This action cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-accent-sm shf-btn-danger-alt w-100">
                                    <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete Visit
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Edit DVR Modal --}}
@if ($dvr->isEditableBy(auth()->user()))
    <div class="modal fade" id="editDvrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border: none; border-radius: 12px;">
                <form id="editDvrForm" method="POST" action="{{ route('dvr.update', $dvr) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header" style="background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title font-display">Edit Visit / મુલાકાત સુધારો</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Contact Name <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="shf-input" required value="{{ $dvr->contact_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Contact Phone</label>
                                <input type="text" name="contact_phone" class="shf-input" value="{{ $dvr->contact_phone }}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="shf-form-label">Contact Type <span class="text-danger">*</span></label>
                                <select name="contact_type" class="shf-input" required>
                                    @foreach ($contactTypes as $ct)
                                        <option value="{{ $ct['key'] }}" {{ $dvr->contact_type === $ct['key'] ? 'selected' : '' }}>
                                            {{ $ct['label_en'] }} / {{ $ct['label_gu'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="shf-form-label">Purpose <span class="text-danger">*</span></label>
                                <select name="purpose" class="shf-input" required>
                                    @foreach ($purposes as $p)
                                        <option value="{{ $p['key'] }}" {{ $dvr->purpose === $p['key'] ? 'selected' : '' }}>
                                            {{ $p['label_en'] }} / {{ $p['label_gu'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="shf-form-label">Visit Date <span class="text-danger">*</span></label>
                                <input type="text" name="visit_date" class="shf-input shf-datepicker-past"
                                    autocomplete="off" required value="{{ $dvr->visit_date->format('d/m/Y') }}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="shf-form-label">Notes</label>
                                <textarea name="notes" class="shf-input" rows="3">{{ $dvr->notes }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Outcome</label>
                                <textarea name="outcome" class="shf-input" rows="3">{{ $dvr->outcome }}</textarea>
                            </div>
                        </div>
                        <div class="mt-3 p-3 rounded" style="background:var(--bg);border:1px solid var(--border);">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="follow_up_needed" id="editFollowUpNeeded" value="1"
                                    {{ $dvr->follow_up_needed ? 'checked' : '' }}>
                                <label class="form-check-label shf-form-label" for="editFollowUpNeeded">Follow-up Needed</label>
                            </div>
                            <div id="editFollowUpFields" class="row g-3" style="{{ $dvr->follow_up_needed ? '' : 'display:none;' }}">
                                <div class="col-md-4">
                                    <label class="shf-form-label">Follow-up Date</label>
                                    <input type="text" name="follow_up_date" class="shf-input shf-datepicker-future"
                                        autocomplete="off" value="{{ $dvr->follow_up_date?->format('d/m/Y') }}">
                                </div>
                                <div class="col-md-8">
                                    <label class="shf-form-label">Follow-up Notes</label>
                                    <input type="text" name="follow_up_notes" class="shf-input" value="{{ $dvr->follow_up_notes }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-accent-outline btn-accent-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-accent btn-accent-sm">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

{{-- Follow-up Visit Modal (reuses index create form pattern) --}}
@if ($dvr->follow_up_needed && !$dvr->is_follow_up_done && auth()->user()->hasPermission('create_dvr'))
    <div class="modal fade" id="followUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border: none; border-radius: 12px;">
                <form id="followUpDvrForm" method="POST" action="{{ route('dvr.store') }}">
                    @csrf
                    <input type="hidden" name="parent_visit_id" value="{{ $dvr->id }}">
                    <input type="hidden" name="loan_id" value="{{ $dvr->loan_id }}">
                    <input type="hidden" name="quotation_id" value="{{ $dvr->quotation_id }}">
                    <div class="modal-header" style="background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title font-display">Log Follow-up Visit / ફોલો-અપ મુલાકાત</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info small mb-3">
                            Follow-up for visit on <strong>{{ $dvr->visit_date->format('d M Y') }}</strong> with <strong>{{ $dvr->contact_name }}</strong>.
                            This will mark the original visit's follow-up as done.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="shf-form-label">Contact Name <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="shf-input" required value="{{ $dvr->contact_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Contact Phone</label>
                                <input type="text" name="contact_phone" class="shf-input" value="{{ $dvr->contact_phone }}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="shf-form-label">Contact Type <span class="text-danger">*</span></label>
                                <select name="contact_type" class="shf-input" required>
                                    @foreach ($contactTypes as $ct)
                                        <option value="{{ $ct['key'] }}" {{ $dvr->contact_type === $ct['key'] ? 'selected' : '' }}>
                                            {{ $ct['label_en'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="shf-form-label">Purpose <span class="text-danger">*</span></label>
                                <select name="purpose" class="shf-input" required>
                                    @foreach ($purposes as $p)
                                        <option value="{{ $p['key'] }}" {{ $p['key'] === 'follow_up' ? 'selected' : '' }}>
                                            {{ $p['label_en'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="shf-form-label">Visit Date <span class="text-danger">*</span></label>
                                <input type="text" name="visit_date" class="shf-input shf-datepicker-past"
                                    autocomplete="off" required value="{{ now()->format('d/m/Y') }}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="shf-form-label">Notes</label>
                                <textarea name="notes" class="shf-input" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="shf-form-label">Outcome</label>
                                <textarea name="outcome" class="shf-input" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="mt-3 p-3 rounded" style="background:var(--bg);border:1px solid var(--border);">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="follow_up_needed" id="fuFollowUpNeeded" value="1">
                                <label class="form-check-label shf-form-label" for="fuFollowUpNeeded">Another Follow-up Needed</label>
                            </div>
                            <div id="fuFollowUpFields" class="row g-3" style="display:none;">
                                <div class="col-md-4">
                                    <label class="shf-form-label">Follow-up Date</label>
                                    <input type="text" name="follow_up_date" class="shf-input shf-datepicker-future" autocomplete="off">
                                </div>
                                <div class="col-md-8">
                                    <label class="shf-form-label">Follow-up Notes</label>
                                    <input type="text" name="follow_up_notes" class="shf-input">
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
<script>
    $(function() {
        // ── Datepicker init ──
        $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });
        $('.shf-datepicker-past').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, endDate: '+0d' });
        $('.shf-datepicker-future').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, startDate: '+1d' });

        // Edit follow-up toggle
        // Edit follow-up toggle with +7 day default
        $('#editFollowUpNeeded').on('change', function() {
            $('#editFollowUpFields').toggle(this.checked);
            if (this.checked && !$('#editDvrForm [name="follow_up_date"]').val()) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                var dd = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                $('#editDvrForm [name="follow_up_date"]').datepicker('update', dd);
            }
        });
        // Follow-up modal toggle with +7 day default
        $('#fuFollowUpNeeded').on('change', function() {
            $('#fuFollowUpFields').toggle(this.checked);
            if (this.checked && !$('#followUpDvrForm [name="follow_up_date"]').val()) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                var dd = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                $('#followUpDvrForm [name="follow_up_date"]').datepicker('update', dd);
            }
        });
    });

    // ── Edit form validation ──
    var dvrValidationRules = {
        contact_name: { required: true, maxlength: 255, label: 'Contact Name / સંપર્ક નામ' },
        contact_type: { required: true, label: 'Contact Type / સંપર્ક પ્રકાર' },
        purpose: { required: true, label: 'Purpose / હેતુ' },
        visit_date: { required: true, dateFormat: 'd/m/Y', label: 'Visit Date / મુલાકાત તારીખ' },
        contact_phone: { maxlength: 20, label: 'Contact Phone / ફોન' },
        notes: { maxlength: 5000, label: 'Notes / નોંધ' },
        outcome: { maxlength: 5000, label: 'Outcome / પરિણામ' }
    };

    $('#editDvrForm').on('submit', function(e) {
        var rules = $.extend(true, {}, dvrValidationRules);
        rules.follow_up_date = {
            label: 'Follow-up Date / ફોલો-અપ તારીખ',
            custom: function() {
                if ($('#editFollowUpNeeded').is(':checked')) {
                    var val = $('#editDvrForm [name="follow_up_date"]').val();
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
        };
        if (!SHF.validateForm($(this), rules)) e.preventDefault();
    });

    $('#followUpDvrForm').on('submit', function(e) {
        var rules = $.extend(true, {}, dvrValidationRules);
        rules.follow_up_date = {
            label: 'Follow-up Date / ફોલો-અપ તારીખ',
            custom: function() {
                if ($('#fuFollowUpNeeded').is(':checked')) {
                    var val = $('#followUpDvrForm [name="follow_up_date"]').val();
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
        };
        if (!SHF.validateForm($(this), rules)) e.preventDefault();
    });

    function markFollowUpDone(id) {
        Swal.fire({
            title: 'Mark Follow-up Done?',
            text: 'This will mark the follow-up as completed without logging a new visit.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f15a29',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, mark done',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/dvr/' + id + '/follow-up-done',
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function() { location.reload(); },
                    error: function() { Swal.fire('Error', 'Could not update.', 'error'); }
                });
            }
        });
    }

    function logFollowUpVisit() {
        $('#followUpModal').modal('show');
    }
</script>
@endpush
