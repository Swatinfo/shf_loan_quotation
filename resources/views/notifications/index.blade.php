@extends('layouts.app')

@section('header')
    <div class="d-flex align-items-center justify-content-between">
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">Notifications</h2>
        <button class="btn btn-sm btn-outline-light" id="markAllReadBtn">Mark All Read</button>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5" style="max-width: 48rem;">
        @forelse($notifications as $notification)
            <div class="card border-0 shadow-sm mb-2 {{ $notification->is_read ? 'opacity-75' : '' }}">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center gap-2">
                            <span class="shf-badge shf-badge-{{ match($notification->type) { 'success' => 'green', 'warning' => 'orange', 'error' => 'orange', 'assignment' => 'blue', default => 'gray' } }}" style="font-size: 0.65rem;">
                                {{ ucfirst(str_replace('_', ' ', $notification->type)) }}
                            </span>
                            <strong>{{ $notification->title }}</strong>
                        </div>
                        <small class="text-muted text-nowrap ms-2">{{ $notification->created_at->diffForHumans() }}</small>
                    </div>
                    <p class="mb-1 mt-1 small">{{ $notification->message }}</p>
                    <div class="d-flex gap-2">
                        @if($notification->link)
                            <a href="{{ $notification->link }}" class="btn-accent-sm">View</a>
                        @endif
                        @if(!$notification->is_read)
                            <button class="btn btn-sm btn-outline-secondary shf-mark-read" data-id="{{ $notification->id }}">Mark Read</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted text-center py-4">No notifications.</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('.shf-mark-read').on('click', function() {
        var $btn = $(this), id = $btn.data('id');
        $.post('/notifications/' + id + '/read', { _token: csrfToken }).done(function() {
            $btn.closest('.card').addClass('opacity-75');
            $btn.remove();
        });
    });

    $('#markAllReadBtn').on('click', function() {
        $.post('/notifications/read-all', { _token: csrfToken }).done(function() { location.reload(); });
    });
});
</script>
@endpush
