@extends('errors.layout')

@section('title', 'Page Not Found')
@section('code', '404 — Not Found')

@section('icon')
    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="7"></circle>
        <path d="M21 21l-4.35-4.35"></path>
        <path d="M11 8v3"></path>
        <path d="M11 14h.01"></path>
    </svg>
@endsection

@section('body')
    <p>The page you're looking for doesn't exist or may have been moved.</p>
    <div class="gu">તમે શોધી રહ્યા છો તે પૃષ્ઠ અસ્તિત્વમાં નથી અથવા ખસેડવામાં આવ્યું છે.</div>
@endsection
