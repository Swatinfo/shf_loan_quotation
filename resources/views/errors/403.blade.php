@extends('newtheme.layouts.app')

@section('title', 'Access Denied')

@section('content')
    <div style="max-width:560px;margin:48px auto;padding:0 16px;">
        <div class="card" style="text-align:center;padding:40px 28px;">
            <div style="width:72px;height:72px;margin:0 auto 20px;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                        background:var(--accent-dim,rgba(241,90,41,0.10));color:var(--accent,#f15a29);">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>

            <h1 style="font-family:'Jost',sans-serif;font-size:1.6rem;margin:0 0 8px;color:var(--text,#1a1a1a);">
                Access Denied
            </h1>
            <div style="font-size:0.8rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted,#6b7280);margin-bottom:14px;">
                403 — Forbidden
            </div>

            <p style="color:var(--text-muted,#6b7280);font-size:0.95rem;line-height:1.55;margin:0 auto 8px;max-width:420px;">
                You don't have permission to view this page.
                @if (! empty($exception?->getMessage()) && $exception->getMessage() !== 'This action is unauthorized.')
                    <br><span style="font-size:0.85rem;">{{ $exception->getMessage() }}</span>
                @endif
            </p>
            <p style="color:var(--text-muted,#6b7280);font-size:0.9rem;line-height:1.55;margin:0 auto 24px;max-width:420px;">
                If you believe this is a mistake, please contact your administrator to request access.
            </p>

            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <a href="{{ url()->previous() }}" class="btn-accent-outline btn-accent-sm">Go Back</a>
                <a href="{{ route('dashboard') }}" class="btn-accent btn-accent-sm">Go to Dashboard</a>
            </div>
        </div>
    </div>
@endsection
