@extends('layouts.app')

@section('header')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('loans.stages', $loan) }}" style="color: rgba(255,255,255,0.4); text-decoration: none;">
            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">Transfer History — {{ $loan->loan_number }}</h2>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5" style="max-width: 48rem;">
        @forelse($transfers as $transfer)
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>{{ $transfer->stageAssignment?->stage?->stage_name_en ?? $transfer->stage_key }}</strong>
                            <span class="shf-badge shf-badge-{{ $transfer->transfer_type === 'auto' ? 'blue' : 'gray' }} ms-1" style="font-size: 0.65rem;">
                                {{ $transfer->transfer_type === 'auto' ? 'Auto' : 'Manual' }}
                            </span>
                        </div>
                        <small class="text-muted">{{ $transfer->created_at?->diffForHumans() }}</small>
                    </div>
                    <div class="mt-1">
                        {{ $transfer->fromUser?->name ?? '—' }} &rarr; <strong>{{ $transfer->toUser?->name ?? '—' }}</strong>
                    </div>
                    @if($transfer->reason)
                        <small class="text-muted fst-italic">"{{ $transfer->reason }}"</small>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted text-center py-4">No transfers yet.</p>
        @endforelse
    </div>
</div>
@endsection
