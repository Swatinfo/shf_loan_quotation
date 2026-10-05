@extends('errors.layout')

@section('title', 'Page Expired')
@section('code', '419 — Session Expired')

@section('icon')
    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M23 4v6h-6"></path>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
    </svg>
@endsection

@section('body')
    <p>Your session expired for security. Please refresh the page and try again — you may need to sign in.</p>
    <div class="gu">સુરક્ષા માટે તમારું સત્ર સમાપ્ત થયું. પૃષ્ઠ રિફ્રેશ કરીને ફરી પ્રયાસ કરો.</div>
@endsection

@section('actions')
    <a class="btn btn-outline" href="" onclick="location.reload(); return false;">Refresh</a>
    <a class="btn" href="/login">Sign In</a>
@endsection
