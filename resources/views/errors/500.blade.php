@extends('errors.layout')

@section('title', 'Something Went Wrong')
@section('code', '500 — Server Error')

@section('icon')
    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
        <path d="M12 9v4"></path>
        <path d="M12 17h.01"></path>
    </svg>
@endsection

@section('body')
    <p>Something went wrong on our end. The team has been notified — please try again in a moment.</p>
    <div class="gu">અમારી બાજુથી કંઈક ખોટું થયું. થોડીવાર પછી ફરી પ્રયાસ કરો.</div>
@endsection

@section('actions')
    <a class="btn btn-outline" href="" onclick="location.reload(); return false;">Retry</a>
    <a class="btn" href="/dashboard">Go to Dashboard</a>
@endsection
