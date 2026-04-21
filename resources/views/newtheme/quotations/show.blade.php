{{--
    Newtheme quotation show — shell-swap.

    The quotation show view has 9+ action buttons (Convert / PDF Branded /
    PDF Plain / Preview HTML / Hold / Resume / Cancel / View Loan / Back),
    two modals (Hold + Cancel) and a 350-line body with comparison cards,
    customer info, hold/cancel banners. We keep the body + scripts in shared
    partials so the legacy view stays byte-identical and just swap the outer
    chrome to the newtheme shell.
--}}
@extends('newtheme.layouts.app', ['pageKey' => 'quotations'])

@section('title', 'Quotation #' . $quotation->id . ' · SHF World')

@push('page-styles')
    {{-- Load Bootstrap (for .row/.col-*/.input-group/.modal used in body + modals)
         and the legacy shf.css (shf-* vocabulary). Then the page stylesheet
         layers the newtheme chrome overrides on top. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/vendor/bootstrap/css/bootstrap.min.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/quotation-show.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('quotations.index') }}">Quotations</a>
                    <span class="sep">/</span>
                    <span>#{{ $quotation->id }}</span>
                </div>
                <h1>Quotation #{{ $quotation->id }}</h1>
                <div class="sub">
                    <strong>{{ $quotation->customer_name }}</strong>
                    @if ($quotation->customer_type)
                        · {{ ucwords(str_replace('_', ' ', $quotation->customer_type)) }}
                    @endif
                    · {{ $quotation->created_at?->format('d M Y') }}
                    @if ($quotation->is_converted)
                        <span class="badge green" style="margin-left:6px;vertical-align:middle;">Converted</span>
                    @elseif ($quotation->is_cancelled)
                        <span class="badge red" style="margin-left:6px;vertical-align:middle;">Cancelled</span>
                    @elseif ($quotation->is_on_hold)
                        <span class="badge amber" style="margin-left:6px;vertical-align:middle;">On Hold</span>
                    @else
                        <span class="badge blue" style="margin-left:6px;vertical-align:middle;">Active</span>
                    @endif
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('quotations.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                @if (!$quotation->is_converted && !$quotation->is_cancelled && auth()->user()->hasPermission('convert_to_loan'))
                    <a href="{{ route('quotations.convert', $quotation) }}" class="btn primary">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        Convert to Loan
                    </a>
                @elseif ($quotation->is_converted)
                    <a href="{{ route('loans.show', $quotation->loan_id) }}" class="btn primary">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        View Loan #{{ $quotation->loan->loan_number }}
                    </a>
                @endif
                @if (auth()->user()->hasPermission('download_pdf_branded'))
                    <a href="{{ route('quotations.download', [$quotation, 'branded' => 1]) }}" class="btn">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10v6m0 0l-3-3m3 3l3-3"/></svg>
                        Branded PDF
                    </a>
                @endif
                @if (auth()->user()->hasPermission('download_pdf_plain'))
                    <a href="{{ route('quotations.download', [$quotation, 'branded' => 0]) }}" class="btn">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10v6m0 0l-3-3m3 3l3-3"/></svg>
                        Plain PDF
                    </a>
                @endif
                @if ($quotation->status === \App\Models\Quotation::STATUS_ACTIVE && !$quotation->is_converted && auth()->user()->hasPermission('hold_quotation'))
                    <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#holdModal">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Hold
                    </button>
                @endif
                @if ($quotation->is_on_hold && auth()->user()->hasPermission('resume_quotation'))
                    <form method="POST" action="{{ route('quotations.resume', $quotation) }}" class="shf-confirm-delete" data-confirm-title="Resume this quotation?" data-confirm-text="Move it back to active." data-confirm-button="Yes, resume" data-confirm-icon="question" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn primary">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Resume
                        </button>
                    </form>
                @endif
                @if (!$quotation->is_cancelled && !$quotation->is_converted && auth()->user()->hasPermission('cancel_quotation'))
                    <button type="button" class="btn danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancel
                    </button>
                @endif
            </div>
        </div>
    </header>

    @include('newtheme.quotations._show-body')
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/vendor/bootstrap/js/bootstrap.bundle.min.js') }}?v={{ config('app.shf_version') }}"></script>
    @include('newtheme.quotations._show-scripts')
@endpush
