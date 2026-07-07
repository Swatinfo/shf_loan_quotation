{{--
    Site-wide Create DVR (Daily Visit Report) modal. Lives in the newtheme
    layout alongside create-task-modal, gated by create_dvr permission.

    Trigger: any element with data-shf-open="create-dvr", or
    document.dispatchEvent(new CustomEvent('shf:open-create-dvr')).

    Validation is jQuery / SHF.validateForm driven — no HTML5 `required` attrs.
--}}
@php
    $dvrConfigSvc = app(\App\Services\ConfigService::class)->load();
    $contactTypes = $dvrConfigSvc['dvrContactTypes'] ?? [];
    $purposes     = $dvrConfigSvc['dvrPurposes'] ?? [];
@endphp

<div id="shfCreateDvrBackdrop" class="gt-modal-backdrop" style="display:none;"></div>
<div id="shfCreateDvrModal" class="gt-modal" role="dialog" aria-label="New Visit" style="display:none;max-width:680px;">
    <form id="shfCreateDvrForm" method="POST" action="{{ route('dvr.store') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="_from_dashboard" value="1">

        <div class="gt-modal-hd">
            <h3>New Visit</h3>
            <button type="button" class="icon-btn" id="shfCreateDvrClose" aria-label="Close">
                <svg class="i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="gt-modal-bd">
            <div class="gt-row-2">
                <div class="gt-field" style="position:relative;">
                    <label class="lbl" for="shfDvrContactName">Contact Name <span style="color:var(--red);">*</span></label>
                    <input type="text" name="contact_name" id="shfDvrContactName" class="input shf-input" maxlength="255" autocomplete="off">
                </div>
                <div class="gt-field" style="position:relative;">
                    <label class="lbl" for="shfDvrContactPhone">Contact Phone</label>
                    <input type="text" name="contact_phone" id="shfDvrContactPhone" class="input shf-input" maxlength="20" placeholder="Search by phone or name…" autocomplete="off">
                </div>
            </div>
            {{-- Shared results dropdown — driven by typing in EITHER contact
                 field. Populates Name + Phone + Type when the user picks a hit. --}}
            <div class="gt-loan-results" id="shfDvrContactResults" style="margin-top:-8px;"></div>

            <div class="gt-row-2">
                <div class="gt-field">
                    <label class="lbl" for="shfDvrContactType">Contact Type <span style="color:var(--red);">*</span></label>
                    <select name="contact_type" id="shfDvrContactType" class="input shf-input">
                        <option value="">— Select —</option>
                        @foreach ($contactTypes as $ct)
                            <option value="{{ $ct['key'] }}">{{ $ct['label_en'] ?? $ct['key'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gt-field">
                    <label class="lbl" for="shfDvrPurpose">Purpose <span style="color:var(--red);">*</span></label>
                    <select name="purpose" id="shfDvrPurpose" class="input shf-input">
                        <option value="">— Select —</option>
                        @foreach ($purposes as $p)
                            <option value="{{ $p['key'] }}">{{ $p['label_en'] ?? $p['key'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="gt-field">
                <label class="lbl" for="shfDvrVisitDate">Visit Date <span style="color:var(--red);">*</span></label>
                <input type="text" name="visit_date" id="shfDvrVisitDate" class="input shf-input shf-datepicker-past" autocomplete="off" placeholder="dd/mm/yyyy">
            </div>

            <div class="gt-row-2">
                <div class="gt-field">
                    <label class="lbl" for="shfDvrNotes">Notes</label>
                    <textarea name="notes" id="shfDvrNotes" class="input shf-input" rows="3" style="height:auto;padding:10px;line-height:1.45;" maxlength="5000"></textarea>
                </div>
                <div class="gt-field">
                    <label class="lbl" for="shfDvrOutcome">Outcome</label>
                    <textarea name="outcome" id="shfDvrOutcome" class="input shf-input" rows="3" style="height:auto;padding:10px;line-height:1.45;" maxlength="5000"></textarea>
                </div>
            </div>

            <div style="padding:12px;border:1px solid var(--line);border-radius:10px;background:var(--paper-2,#faf9f7);">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--ink);cursor:pointer;">
                    <input type="checkbox" name="follow_up_needed" id="shfDvrFollowUpNeeded" value="1">
                    Follow-up Needed
                </label>
                <div id="shfDvrFollowUpFields" class="gt-row-2" style="display:none;margin-top:10px;">
                    <div class="gt-field">
                        <label class="lbl" for="shfDvrFollowUpDate">Follow-up Date</label>
                        <input type="text" name="follow_up_date" id="shfDvrFollowUpDate" class="input shf-input shf-datepicker-future" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="gt-field">
                        <label class="lbl" for="shfDvrFollowUpNotes">Follow-up Notes</label>
                        <input type="text" name="follow_up_notes" id="shfDvrFollowUpNotes" class="input shf-input" maxlength="5000" placeholder="What to do on follow-up">
                    </div>
                </div>
            </div>
        </div>

        <div class="gt-modal-ft">
            <button type="button" class="btn" id="shfCreateDvrCancel">Cancel</button>
            <button type="submit" class="btn primary" id="shfCreateDvrSave">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                Save Visit
            </button>
        </div>
    </form>
</div>
