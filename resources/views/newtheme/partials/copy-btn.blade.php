{{--
    Reusable copy-to-clipboard button. Usage:
        @include('newtheme.partials.copy-btn', ['value' => $loan->loan_number])
    Behaviour + styling are global (SHF.copyBtn handler in shf-newtheme.js,
    .shf-copy-btn in shf-extras.css). Renders nothing for empty values.
--}}
@php($copyValue = trim((string) ($value ?? '')))
@if ($copyValue !== '' && $copyValue !== '—')
    <button type="button" class="shf-copy-btn" data-copy="{{ $copyValue }}" title="Copy" aria-label="Copy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="9" y="9" width="11" height="11" rx="2"></rect>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
        </svg>
    </button>
@endif
