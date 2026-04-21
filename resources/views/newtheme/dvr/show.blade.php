@extends('newtheme.layouts.app', ['pageKey' => 'dvr'])

@section('title', 'Visit #' . $dvr->id . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/dvr-show.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $canEdit = $dvr->isEditableBy(auth()->user());
    $canDelete = $dvr->isDeletableBy(auth()->user());
    $canCreate = auth()->user()->hasPermission('create_dvr');
    $contactLabel = $contactTypeLabels[$dvr->contact_type] ?? ucfirst(str_replace('_', ' ', $dvr->contact_type));
    $purposeLabel = $purposeLabels[$dvr->purpose] ?? ucfirst(str_replace('_', ' ', $dvr->purpose));

    $followUpTone = null;
    $followUpLabel = null;
    if ($dvr->follow_up_needed) {
        if ($dvr->is_follow_up_done) {
            $followUpTone = 'green';
            $followUpLabel = 'Follow-up Done';
        } elseif ($dvr->is_overdue_follow_up) {
            $followUpTone = 'red';
            $followUpLabel = 'Follow-up Overdue';
        } else {
            $followUpTone = 'amber';
            $followUpLabel = 'Follow-up Pending';
        }
    } elseif ($dvr->is_follow_up_done) {
        // No pending follow-up — visit is closed / completed.
        $followUpTone = 'green';
        $followUpLabel = 'Completed';
    }
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('dvr.index') }}">DVR</a>
                    <span class="sep">/</span>
                    <span>V-{{ $dvr->id }}</span>
                </div>
                <h1>{{ $dvr->contact_name }}</h1>
                <div class="sub">
                    Visited {{ $dvr->visit_date->format('d M Y') }}
                    <span class="badge blue" style="margin-left:8px;vertical-align:middle;">{{ $contactLabel }}</span>
                    <span class="badge" style="margin-left:4px;vertical-align:middle;">{{ $purposeLabel }}</span>
                    @if ($followUpTone)
                        <span class="badge {{ $followUpTone }}" style="margin-left:4px;vertical-align:middle;">{{ $followUpLabel }}</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('dvr.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                @if ($canEdit)
                    <button type="button" class="btn" id="dsEditBtn">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </button>
                @endif
                @if ($dvr->follow_up_needed && ! $dvr->is_follow_up_done && $canCreate)
                    <button type="button" class="btn primary" id="dsLogFollowUpBtn">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                        Log Follow-up
                    </button>
                @endif
            </div>
        </div>
    </header>

    <main class="content">
        <div class="grid c-main ds-grid">
            {{-- ===== Left column ===== --}}
            <div>
                {{-- Visit card --}}
                <div class="card">
                    <div class="card-hd"><div class="t">Visit</div></div>
                    <div class="card-bd">
                        <div class="ds-grid-3">
                            <div class="ds-kv-item">
                                <span class="ds-k">Contact Name</span>
                                <span class="ds-v">{{ $dvr->contact_name }}</span>
                            </div>
                            @if ($dvr->contact_phone)
                                <div class="ds-kv-item">
                                    <span class="ds-k">Phone</span>
                                    <span class="ds-v"><a href="tel:{{ $dvr->contact_phone }}" class="ds-link">{{ $dvr->contact_phone }}</a></span>
                                </div>
                            @endif
                            <div class="ds-kv-item">
                                <span class="ds-k">Visit Date</span>
                                <span class="ds-v">{{ $dvr->visit_date->format('d M Y') }}</span>
                            </div>
                            <div class="ds-kv-item">
                                <span class="ds-k">Contact Type</span>
                                <span class="ds-v"><span class="badge blue">{{ $contactLabel }}</span></span>
                            </div>
                            <div class="ds-kv-item">
                                <span class="ds-k">Purpose</span>
                                <span class="ds-v"><span class="badge">{{ $purposeLabel }}</span></span>
                            </div>
                            <div class="ds-kv-item">
                                <span class="ds-k">Branch</span>
                                <span class="ds-v">{{ $dvr->branch?->name ?? '—' }}</span>
                            </div>
                        </div>

                        @if ($dvr->notes)
                            <div class="ds-block">
                                <span class="ds-block-label">Notes</span>
                                <p class="ds-prose">{{ $dvr->notes }}</p>
                            </div>
                        @endif

                        @if ($dvr->outcome)
                            <div class="ds-block">
                                <span class="ds-block-label">Outcome</span>
                                <p class="ds-prose">{{ $dvr->outcome }}</p>
                            </div>
                        @endif

                        @if ($dvr->loan)
                            <div class="ds-linked">
                                <span class="ds-linked-label">Linked Loan</span>
                                <a href="{{ route('loans.show', $dvr->loan_id) }}" class="ds-linked-link">
                                    <strong>#{{ $dvr->loan->loan_number }}</strong>
                                    @if ($dvr->loan->application_number)
                                        <span class="ds-muted"> / App: {{ $dvr->loan->application_number }}</span>
                                    @endif
                                    <span class="ds-muted"> — {{ $dvr->loan->customer_name }}</span>
                                    @if ($dvr->loan->bank_name)
                                        <span class="ds-muted"> ({{ $dvr->loan->bank_name }})</span>
                                    @endif
                                </a>
                            </div>
                        @endif
                        @if ($dvr->quotation)
                            <div class="ds-linked">
                                <span class="ds-linked-label">Linked Quotation</span>
                                <a href="{{ route('quotations.show', $dvr->quotation_id) }}" class="ds-linked-link">
                                    <strong>Q#{{ $dvr->quotation_id }}</strong>
                                    <span class="ds-muted"> — {{ $dvr->quotation->customer_name }}</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Follow-up panel --}}
                @if ($dvr->follow_up_needed)
                    <div class="card mt-4 ds-follow ds-follow-{{ $followUpTone }}">
                        <div class="card-hd ds-follow-hd">
                            <div class="t">
                                <span class="ds-follow-dot"></span>
                                {{ $followUpLabel }}
                            </div>
                            @if (! $dvr->is_follow_up_done && $canEdit)
                                <button type="button" class="btn success sm" id="dsMarkDoneBtn" data-id="{{ $dvr->id }}">
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                    Mark Done
                                </button>
                            @endif
                        </div>
                        <div class="card-bd">
                            @if ($dvr->follow_up_date)
                                <div class="ds-follow-row">
                                    <span class="ds-k">Follow-up Date</span>
                                    <span class="ds-v">
                                        {{ $dvr->follow_up_date->format('d M Y') }}
                                        @if ($dvr->is_overdue_follow_up)
                                            <span class="badge red" style="margin-left:6px;">{{ (int) $dvr->follow_up_date->diffInDays(today()) }} days overdue</span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                            @if ($dvr->follow_up_notes)
                                <div class="ds-follow-row">
                                    <span class="ds-k">Notes</span>
                                    <span class="ds-v">{{ $dvr->follow_up_notes }}</span>
                                </div>
                            @endif
                            @if ($dvr->followUpVisit)
                                <div class="ds-follow-row" style="margin-top:8px;">
                                    <a href="{{ route('dvr.show', $dvr->follow_up_visit_id) }}" class="btn sm">
                                        View Follow-up Visit #{{ $dvr->follow_up_visit_id }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Visit chain timeline --}}
                @if ($visitChain->count() > 1)
                    <div class="card mt-4">
                        <div class="card-hd"><div class="t">Visit Timeline</div></div>
                        <div class="card-bd ds-timeline">
                            @foreach ($visitChain as $chainVisit)
                                @php
                                    $isCurrent = $chainVisit->id === $dvr->id;
                                    $chainPurposeLabel = $purposeLabels[$chainVisit->purpose] ?? $chainVisit->purpose;
                                @endphp
                                <div class="ds-tl-row {{ $isCurrent ? 'ds-tl-current' : '' }}">
                                    <div class="ds-tl-dot">{{ $loop->iteration }}</div>
                                    <div class="ds-tl-body">
                                        <div class="ds-tl-head">
                                            @if ($isCurrent)
                                                <strong>{{ $chainVisit->visit_date->format('d M Y') }}</strong>
                                                <span class="badge orange">Current</span>
                                            @else
                                                <a href="{{ route('dvr.show', $chainVisit) }}" class="ds-link">
                                                    <strong>{{ $chainVisit->visit_date->format('d M Y') }}</strong>
                                                </a>
                                            @endif
                                            <span class="badge">{{ $chainPurposeLabel }}</span>
                                        </div>
                                        @if ($chainVisit->notes)
                                            <div class="ds-muted ds-tl-notes">{{ \Illuminate\Support\Str::limit($chainVisit->notes, 120) }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ===== Right column ===== --}}
            <aside>
                <div class="card">
                    <div class="card-hd"><div class="t">Details</div></div>
                    <div class="card-bd">
                        <div class="ds-kv">
                            <div><span>Created by</span><span>{{ $dvr->user?->name ?? '—' }}</span></div>
                            <div><span>Created</span><span>{{ $dvr->created_at->format('d M Y, h:i A') }}</span></div>
                            @if ($dvr->updated_at->ne($dvr->created_at))
                                <div><span>Updated</span><span>{{ $dvr->updated_at->format('d M Y, h:i A') }}</span></div>
                            @endif
                            @if ($dvr->parentVisit)
                                <div>
                                    <span>Follow-up of</span>
                                    <span>
                                        <a href="{{ route('dvr.show', $dvr->parent_visit_id) }}" class="ds-link">
                                            Visit #{{ $dvr->parent_visit_id }}
                                        </a>
                                        <div class="ds-muted" style="font-size:11.5px;">{{ $dvr->parentVisit->visit_date->format('d M Y') }}</div>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($canDelete)
                    <form method="POST" action="{{ route('dvr.destroy', $dvr) }}" class="mt-4" id="dsDeleteForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger" style="width:100%;">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete Visit
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </main>

    {{-- ===== Edit DVR modal (reuses gt-modal shell from shf-modals.css) ===== --}}
    @if ($canEdit)
        <div id="dsEditBackdrop" class="gt-modal-backdrop" style="display:none;"></div>
        <div id="dsEditModal" class="gt-modal ds-modal-wide" role="dialog" aria-label="Edit Visit" style="display:none;">
            <form id="dsEditForm" method="POST" action="{{ route('dvr.update', $dvr) }}" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="gt-modal-hd">
                    <h3>Edit Visit</h3>
                    <button type="button" class="icon-btn" id="dsEditClose" aria-label="Close">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="gt-modal-bd">
                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="dsEditName">Contact Name <span style="color:var(--red);">*</span></label>
                            <input type="text" name="contact_name" id="dsEditName" class="input shf-input" maxlength="255"
                                value="{{ $dvr->contact_name }}">
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="dsEditPhone">Contact Phone</label>
                            <input type="text" name="contact_phone" id="dsEditPhone" class="input shf-input" maxlength="20"
                                value="{{ $dvr->contact_phone }}">
                        </div>
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="dsEditType">Contact Type <span style="color:var(--red);">*</span></label>
                            <select name="contact_type" id="dsEditType" class="input shf-input">
                                @foreach ($contactTypes as $ct)
                                    <option value="{{ $ct['key'] }}" {{ $dvr->contact_type === $ct['key'] ? 'selected' : '' }}>
                                        {{ $ct['label_en'] }} / {{ $ct['label_gu'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="dsEditPurpose">Purpose <span style="color:var(--red);">*</span></label>
                            <select name="purpose" id="dsEditPurpose" class="input shf-input">
                                @foreach ($purposes as $p)
                                    <option value="{{ $p['key'] }}" {{ $dvr->purpose === $p['key'] ? 'selected' : '' }}>
                                        {{ $p['label_en'] }} / {{ $p['label_gu'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="gt-field">
                        <label class="lbl" for="dsEditDate">Visit Date <span style="color:var(--red);">*</span></label>
                        <input type="text" name="visit_date" id="dsEditDate" class="input shf-input shf-datepicker-past"
                            autocomplete="off" placeholder="dd/mm/yyyy" value="{{ $dvr->visit_date->format('d/m/Y') }}">
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="dsEditNotes">Notes</label>
                            <textarea name="notes" id="dsEditNotes" class="input shf-input" rows="3" maxlength="5000"
                                style="height:auto;padding:10px;line-height:1.45;">{{ $dvr->notes }}</textarea>
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="dsEditOutcome">Outcome</label>
                            <textarea name="outcome" id="dsEditOutcome" class="input shf-input" rows="3" maxlength="5000"
                                style="height:auto;padding:10px;line-height:1.45;">{{ $dvr->outcome }}</textarea>
                        </div>
                    </div>

                    <div class="ds-follow-box">
                        <label class="ds-check">
                            <input type="checkbox" name="follow_up_needed" id="dsEditFollowNeeded" value="1"
                                {{ $dvr->follow_up_needed ? 'checked' : '' }}>
                            <span>Follow-up Needed</span>
                        </label>
                        <div id="dsEditFollowFields" class="gt-row-2" style="{{ $dvr->follow_up_needed ? '' : 'display:none;' }}margin-top:10px;">
                            <div class="gt-field">
                                <label class="lbl" for="dsEditFollowDate">Follow-up Date</label>
                                <input type="text" name="follow_up_date" id="dsEditFollowDate" class="input shf-input shf-datepicker-future"
                                    autocomplete="off" placeholder="dd/mm/yyyy" value="{{ $dvr->follow_up_date?->format('d/m/Y') }}">
                            </div>
                            <div class="gt-field">
                                <label class="lbl" for="dsEditFollowNotes">Follow-up Notes</label>
                                <input type="text" name="follow_up_notes" id="dsEditFollowNotes" class="input shf-input"
                                    maxlength="5000" value="{{ $dvr->follow_up_notes }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gt-modal-ft">
                    <button type="button" class="btn" id="dsEditCancel">Cancel</button>
                    <button type="submit" class="btn primary" id="dsEditSave">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ===== Log Follow-up modal ===== --}}
    @if ($dvr->follow_up_needed && ! $dvr->is_follow_up_done && $canCreate)
        <div id="dsFuBackdrop" class="gt-modal-backdrop" style="display:none;"></div>
        <div id="dsFuModal" class="gt-modal ds-modal-wide" role="dialog" aria-label="Log Follow-up Visit" style="display:none;">
            <form id="dsFuForm" method="POST" action="{{ route('dvr.store') }}" autocomplete="off">
                @csrf
                <input type="hidden" name="parent_visit_id" value="{{ $dvr->id }}">
                <input type="hidden" name="loan_id" value="{{ $dvr->loan_id }}">
                <input type="hidden" name="quotation_id" value="{{ $dvr->quotation_id }}">

                <div class="gt-modal-hd">
                    <h3>Log Follow-up Visit</h3>
                    <button type="button" class="icon-btn" id="dsFuClose" aria-label="Close">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="gt-modal-bd">
                    <div class="ds-info-line">
                        Follow-up for visit on <strong>{{ $dvr->visit_date->format('d M Y') }}</strong> with <strong>{{ $dvr->contact_name }}</strong>. This will mark the original visit's follow-up as done.
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="dsFuName">Contact Name <span style="color:var(--red);">*</span></label>
                            <input type="text" name="contact_name" id="dsFuName" class="input shf-input" maxlength="255" value="{{ $dvr->contact_name }}">
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="dsFuPhone">Contact Phone</label>
                            <input type="text" name="contact_phone" id="dsFuPhone" class="input shf-input" maxlength="20" value="{{ $dvr->contact_phone }}">
                        </div>
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="dsFuType">Contact Type <span style="color:var(--red);">*</span></label>
                            <select name="contact_type" id="dsFuType" class="input shf-input">
                                @foreach ($contactTypes as $ct)
                                    <option value="{{ $ct['key'] }}" {{ $dvr->contact_type === $ct['key'] ? 'selected' : '' }}>{{ $ct['label_en'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="dsFuPurpose">Purpose <span style="color:var(--red);">*</span></label>
                            <select name="purpose" id="dsFuPurpose" class="input shf-input">
                                @foreach ($purposes as $p)
                                    <option value="{{ $p['key'] }}" {{ $p['key'] === 'follow_up' ? 'selected' : '' }}>{{ $p['label_en'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="gt-field">
                        <label class="lbl" for="dsFuDate">Visit Date <span style="color:var(--red);">*</span></label>
                        <input type="text" name="visit_date" id="dsFuDate" class="input shf-input shf-datepicker-past"
                            autocomplete="off" placeholder="dd/mm/yyyy" value="{{ now()->format('d/m/Y') }}">
                    </div>

                    <div class="gt-row-2">
                        <div class="gt-field">
                            <label class="lbl" for="dsFuNotes">Notes</label>
                            <textarea name="notes" id="dsFuNotes" class="input shf-input" rows="3" maxlength="5000"
                                style="height:auto;padding:10px;line-height:1.45;"></textarea>
                        </div>
                        <div class="gt-field">
                            <label class="lbl" for="dsFuOutcome">Outcome</label>
                            <textarea name="outcome" id="dsFuOutcome" class="input shf-input" rows="3" maxlength="5000"
                                style="height:auto;padding:10px;line-height:1.45;"></textarea>
                        </div>
                    </div>

                    <div class="ds-follow-box">
                        <label class="ds-check">
                            <input type="checkbox" name="follow_up_needed" id="dsFuFollowNeeded" value="1">
                            <span>Another Follow-up Needed</span>
                        </label>
                        <div id="dsFuFollowFields" class="gt-row-2" style="display:none;margin-top:10px;">
                            <div class="gt-field">
                                <label class="lbl" for="dsFuFollowDate">Follow-up Date</label>
                                <input type="text" name="follow_up_date" id="dsFuFollowDate" class="input shf-input shf-datepicker-future"
                                    autocomplete="off" placeholder="dd/mm/yyyy">
                            </div>
                            <div class="gt-field">
                                <label class="lbl" for="dsFuFollowNotes">Follow-up Notes</label>
                                <input type="text" name="follow_up_notes" id="dsFuFollowNotes" class="input shf-input" maxlength="5000">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gt-modal-ft">
                    <button type="button" class="btn" id="dsFuCancel">Cancel</button>
                    <button type="submit" class="btn primary" id="dsFuSave">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Save Follow-up Visit
                    </button>
                </div>
            </form>
        </div>
    @endif
@endsection

@push('page-scripts')
    <script>
        window.__DS = {
            markDoneUrl: @json(url('/dvr/' . $dvr->id . '/follow-up-done')),
            csrf: @json(csrf_token()),
        };
    </script>
    <script src="{{ asset('newtheme/pages/dvr-show.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
