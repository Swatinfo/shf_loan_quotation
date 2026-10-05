@extends('errors.layout')

@section('head')
    <meta http-equiv="refresh" content="30">
@endsection

@section('title', "We'll be right back")
@section('code', '503 — Service Temporarily Unavailable')

@section('icon')
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"></circle>
        <path d="M12 7v5l3 2"></path>
    </svg>
@endsection

@section('body')
    <p>
        SHF World is undergoing brief maintenance and will be back shortly.
        This page refreshes automatically — thank you for your patience.
    </p>
    <div class="gu">અમે થોડીવારમાં પાછા આવીએ છીએ — SHF World પર જાળવણીનું કામ ચાલુ છે.</div>
@endsection

@section('actions')
    <a class="btn" href="" onclick="location.reload(); return false;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 4v6h-6"></path>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
        </svg>
        Retry now
    </a>
@endsection
