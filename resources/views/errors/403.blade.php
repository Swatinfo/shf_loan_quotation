@extends('errors.layout')

@section('title', 'Access Denied')
@section('code', '403 — Forbidden')

@section('icon')
    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
    </svg>
@endsection

@section('body')
    <p>
        You don't have permission to view this page.
        @if (! empty($exception) && method_exists($exception, 'getMessage') && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.')
            <br><span class="detail">{{ $exception->getMessage() }}</span>
        @endif
    </p>
    <p>If you believe this is a mistake, please contact your administrator to request access.</p>
    <div class="gu">તમને આ પૃષ્ઠ જોવાની પરવાનગી નથી.</div>
@endsection
