@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Valuation Map — Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/vendor/leaflet/leaflet.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-valuation-map.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php $v = $valuations->first(); @endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('loans.index') }}">Loans</a>
                    <span class="sep">/</span>
                    <a href="{{ route('loans.show', $loan) }}">#{{ $loan->loan_number }}</a>
                    <span class="sep">/</span>
                    <span>Valuation Map</span>
                </div>
                <h1>Valuation Map</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($loan->product?->name) / {{ $loan->product->name }}@endif
                    @if ($v && $v->final_valuation)
                        <span class="vm-chip">Final ₹ {{ number_format($v->final_valuation) }}</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.stages', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Stages
                </a>
                <a href="{{ route('loans.show', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Loan
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        @if ($errors->any())
            <div class="card vm-alert">
                <div class="card-bd">
                    <strong>Please fix the following:</strong>
                    <ul>
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('loans.valuation.store', $loan) }}" id="vmForm" autocomplete="off">
            @csrf
            <input type="hidden" name="valuation_type" value="property">

            {{-- ===== Property Details ===== --}}
            <div class="card vm-card">
                <div class="card-hd"><div class="t"><span class="num">1</span>Property Details</div></div>
                <div class="card-bd">
                    <div class="vm-grid">
                        <div class="vm-field">
                            <label class="lbl" for="vmPropertyType">Property Type <span class="vm-req">*</span></label>
                            <select name="property_type" id="vmPropertyType" class="input">
                                <option value="">— Select —</option>
                                @foreach (\App\Models\ValuationDetail::PROPERTY_TYPES as $key => $label)
                                    <option value="{{ $key }}" {{ old('property_type', $v?->property_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="vmDate">Valuation Date <span class="vm-req">*</span></label>
                            <input type="text" name="valuation_date" id="vmDate" class="input shf-datepicker-custom"
                                data-min-date="{{ $v?->created_at ? $v->created_at->format('d/m/Y') : now()->subDays(3)->format('d/m/Y') }}"
                                data-max-date="{{ $v?->created_at ? now()->format('d/m/Y') : now()->addDay()->format('d/m/Y') }}"
                                value="{{ old('valuation_date', $v?->valuation_date?->format('d/m/Y') ?? now()->format('d/m/Y')) }}"
                                placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>
                    </div>

                    <div class="vm-field vm-field-full">
                        <label class="lbl" for="vmAddress">Property Address</label>
                        <textarea name="property_address" id="vmAddress" class="input vm-textarea" rows="2" maxlength="1000">{{ old('property_address', $v?->property_address) }}</textarea>
                    </div>

                    <div class="vm-grid">
                        <div class="vm-field">
                            <label class="lbl" for="vmLandmark">Landmark <span class="vm-req">*</span></label>
                            <input type="text" name="landmark" id="vmLandmark" class="input"
                                value="{{ old('landmark', $v?->landmark) }}" placeholder="e.g. Near SBI Bank, Ring Road" maxlength="255">
                        </div>
                        <div class="vm-field vm-field-search">
                            <label class="lbl" for="locationSearch">Search Location</label>
                            <div class="vm-input-action">
                                <input type="text" id="locationSearch" class="input" placeholder="Search place, area, city…" autocomplete="off">
                                <button type="button" id="btnSearch" class="btn sm" title="Search">
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </button>
                            </div>
                            <div id="searchResults" class="vm-search-results d-none"></div>
                        </div>
                    </div>

                    <div class="vm-grid vm-grid-3">
                        <div class="vm-field">
                            <label class="lbl" for="coordPaste">Paste Coordinates / Google Maps Link</label>
                            <div class="vm-input-action">
                                <input type="text" id="coordPaste" class="input" placeholder="e.g. 22.3039, 70.8022 or Google Maps URL">
                                <button type="button" id="btnParseCoords" class="btn sm" title="Parse">
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="valLatitude">Latitude</label>
                            <input type="text" name="latitude" id="valLatitude" class="input vm-readonly" value="{{ old('latitude', $v?->latitude) }}" placeholder="Auto-filled" readonly>
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="valLongitude">Longitude</label>
                            <input type="text" name="longitude" id="valLongitude" class="input vm-readonly" value="{{ old('longitude', $v?->longitude) }}" placeholder="Auto-filled" readonly>
                        </div>
                    </div>

                    <div class="vm-map-wrap">
                        <div class="vm-map-tools">
                            <span class="vm-map-hint">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Click map to set location
                            </span>
                            <div class="vm-map-actions">
                                <span id="coordDisplay" class="vm-coord-display"></span>
                                <button type="button" id="btnCopyLocation" class="btn sm" title="Copy coordinates" style="display:none;">
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                    Copy
                                </button>
                                <button type="button" id="btnMyLocation" class="btn sm" title="Use my GPS location">
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    My Location
                                </button>
                            </div>
                        </div>
                        <div id="leafletMap" class="vm-map"></div>
                    </div>
                </div>
            </div>

            {{-- ===== Land Valuation ===== --}}
            <div class="card vm-card">
                <div class="card-hd"><div class="t"><span class="num">2</span>Land Valuation</div></div>
                <div class="card-bd">
                    <div class="vm-grid vm-grid-3">
                        <div class="vm-field">
                            <label class="lbl" for="landArea">Land Area (sq ft) <span class="vm-req">*</span></label>
                            <input type="number" name="land_area" id="landArea" class="input" value="{{ old('land_area', $v?->land_area) }}" placeholder="e.g. 1200" step="0.01" min="0">
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="landRate">Land Rate (sq ft) <span class="vm-req">*</span></label>
                            <div class="vm-amount">
                                <span class="vm-rupee">₹</span>
                                <input type="number" name="land_rate" id="landRate" class="input vm-amount-input" value="{{ old('land_rate', $v?->land_rate) }}" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="landValuation">Land Valuation</label>
                            <div class="vm-amount">
                                <span class="vm-rupee">₹</span>
                                <input type="text" id="landValuation" class="input vm-amount-input vm-readonly" value="{{ $v?->land_valuation ? number_format($v->land_valuation) : '' }}" readonly>
                            </div>
                            <div class="vm-words" id="landValWords"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Construction Valuation ===== --}}
            <div class="card vm-card">
                <div class="card-hd"><div class="t"><span class="num">3</span>Construction Valuation</div></div>
                <div class="card-bd">
                    <div class="vm-grid vm-grid-3">
                        <div class="vm-field">
                            <label class="lbl" for="constructionArea">Construction Area (sq ft)</label>
                            <input type="number" name="construction_area" id="constructionArea" class="input" value="{{ old('construction_area', $v?->construction_area) }}" placeholder="e.g. 800" step="0.01" min="0">
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="constructionRate">Construction Rate (sq ft)</label>
                            <div class="vm-amount">
                                <span class="vm-rupee">₹</span>
                                <input type="number" name="construction_rate" id="constructionRate" class="input vm-amount-input" value="{{ old('construction_rate', $v?->construction_rate) }}" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="constructionValuation">Construction Valuation</label>
                            <div class="vm-amount">
                                <span class="vm-rupee">₹</span>
                                <input type="text" id="constructionValuation" class="input vm-amount-input vm-readonly" value="{{ $v?->construction_valuation ? number_format($v->construction_valuation) : '' }}" readonly>
                            </div>
                            <div class="vm-words" id="constValWords"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Final Valuation ===== --}}
            <div class="card vm-card">
                <div class="card-hd"><div class="t"><span class="num">4</span>Final Valuation</div></div>
                <div class="card-bd">
                    <div class="vm-grid">
                        <div class="vm-field vm-field-wide">
                            <label class="lbl" for="finalValuation">Total Valuation Amount</label>
                            <div class="vm-amount vm-amount-hero">
                                <span class="vm-rupee">₹</span>
                                <input type="text" id="finalValuation" class="input vm-amount-input vm-amount-hero-input" value="{{ $v?->final_valuation ? number_format($v->final_valuation) : '' }}" readonly>
                            </div>
                            <div class="vm-words" id="finalValWords"></div>
                        </div>
                    </div>
                    <div class="vm-grid vm-grid-3">
                        <div class="vm-field">
                            <label class="lbl" for="vmValuator">Valuator Name <span class="vm-req">*</span></label>
                            <input type="text" name="valuator_name" id="vmValuator" class="input" value="{{ old('valuator_name', $v?->valuator_name) }}" maxlength="255">
                        </div>
                        <div class="vm-field">
                            <label class="lbl" for="vmReportNumber">Report Number</label>
                            <input type="text" name="valuator_report_number" id="vmReportNumber" class="input" value="{{ old('valuator_report_number', $v?->valuator_report_number) }}" maxlength="100">
                        </div>
                        <div class="vm-field">{{-- spacer --}}</div>
                    </div>
                    <div class="vm-field vm-field-full">
                        <label class="lbl" for="vmNotes">Notes</label>
                        <textarea name="notes" id="vmNotes" class="input vm-textarea" rows="3" maxlength="5000">{{ old('notes', $v?->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="vm-actions">
                <a href="{{ route('loans.stages', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancel
                </a>
                <button type="submit" class="btn primary" id="vmSave">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                    Save Valuation
                </button>
            </div>
        </form>
    </main>
@endsection

@push('page-scripts')
    {{-- The Leaflet + coordinate / geocoding / search / GPS / live-calc / validation
         script targets the same DOM IDs (#locationSearch, #searchResults, #coordPaste,
         #btnParseCoords, #btnSearch, #btnMyLocation, #btnCopyLocation, #valLatitude,
         #valLongitude, #coordDisplay, #leafletMap, #landArea, #landRate, #landValuation,
         #landValWords, #constructionArea, #constructionRate, #constructionValuation,
         #constValWords, #finalValuation, #finalValWords) which are preserved in this
         rewrite, so the same partial drives both the legacy and newtheme pages. --}}
    @include('newtheme.loans._valuation-map-scripts')
@endpush
