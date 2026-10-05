@extends('errors.layout')

@section('title', 'Too Many Requests')
@section('code', '429 — Slow Down')

@section('icon')
    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 22h14"></path>
        <path d="M5 2h14"></path>
        <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path>
        <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path>
    </svg>
@endsection

@section('body')
    <p>You've made too many requests in a short time. Please wait a moment and try again.</p>
    <div class="gu">થોડા સમયમાં ઘણી વિનંતીઓ થઈ. કૃપા કરીને થોડીવાર રાહ જોઈને ફરી પ્રયાસ કરો.</div>
@endsection

@section('actions')
    <a class="btn" href="" onclick="location.reload(); return false;">Retry</a>
@endsection
