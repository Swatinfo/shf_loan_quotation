@extends('newtheme.layouts.app')

@section('title', 'Notifications · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/notifications.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    @php
        $unreadCount = $notifications->where('is_read', false)->count();
        $totalCount = $notifications->count();
    @endphp

    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Notifications</span></div>
                <h1>Notifications</h1>
                <div class="sub">
                    <strong>{{ $unreadCount }}</strong> unread · {{ $totalCount }} shown
                </div>
            </div>
            <div class="head-actions">
                <button type="button" class="btn primary" id="markAllReadBtn" {{ $unreadCount === 0 ? 'disabled' : '' }}>
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Mark all read
                </button>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="grid c-form mt-4" style="max-width: 760px;">

            {{-- ===== Push notification toggle ===== --}}
            <div class="card" id="pushPrompt" style="display:none;">
                <div class="card-bd">
                    <div class="row-flex">
                        <div class="row-info">
                            <div class="row-title" id="pushPromptTitle">Enable desktop &amp; mobile notifications</div>
                            <div class="row-sub" id="pushPromptSubtitle">Get alerted on your phone or desktop, even when SHF is closed.</div>
                        </div>
                        <div class="row-actions">
                            <button type="button" class="btn primary" id="enablePushBtn">Enable</button>
                            <button type="button" class="btn" id="disablePushBtn" style="display:none;">Disable</button>
                        </div>
                    </div>

                    <div id="pushPromptBlockedHelp" class="blocked-help" style="display:none;">
                        <p class="hint-h"><strong>How to re-enable</strong></p>
                        <ol>
                            <li>Tap the <strong>lock</strong> icon in the address bar</li>
                            <li>Open <strong>Permissions</strong> → <strong>Notifications</strong></li>
                            <li>Select <strong>Allow</strong></li>
                            <li>Reload this page</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- ===== Sound settings ===== --}}
            <div class="card mt-4">
                <div class="card-bd">
                    <div class="row-flex">
                        <div class="row-info">
                            <div class="row-title">Notification sound</div>
                            <div class="row-sub">Chime when a live notification arrives.</div>
                        </div>
                        <div class="row-actions sound-actions">
                            {{-- Custom newtheme dropdown — replaces the native <select> so
                                 the open list inherits theme tokens (the JS still calls
                                 SHFPush.setChimePreset(value), no API change). --}}
                            <div class="shf-dd-wrap" id="chimePresetDD">
                                <button type="button" class="btn shf-dd-trigger" id="chimePresetBtn" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="shf-dd-value" id="chimePresetValue">Loading…</span>
                                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;opacity:0.55;"><path d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <ul class="shf-dd-menu" role="listbox" id="chimePresetMenu"></ul>
                            </div>
                            <button type="button" class="btn primary" id="testSoundBtn">Test</button>
                            <button type="button" class="btn" id="muteBtn" style="display:none;">Mute</button>
                            <button type="button" class="btn primary" id="unmuteBtn" style="display:none;">Unmute</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Notification list ===== --}}
            <div class="notif-list mt-4">
                @forelse($notifications as $n)
                    @php
                        $color = match($n->type) {
                            'success' => 'green',
                            'warning', 'error' => 'orange',
                            'assignment' => 'blue',
                            default => 'gray',
                        };
                    @endphp
                    <div class="notif-item card {{ $n->is_read ? 'is-read' : '' }}" data-notif-id="{{ $n->id }}">
                        <div class="card-bd">
                            <div class="notif-head">
                                <div class="notif-title">
                                    <span class="badge sq {{ $color }}">{{ ucfirst(str_replace('_', ' ', $n->type)) }}</span>
                                    <strong>{{ $n->title }}</strong>
                                </div>
                                <span class="notif-age" title="{{ $n->created_at->format('d M Y, h:i A') }}">{{ $n->created_at->diffForHumans() }}</span>
                            </div>
                            @if ($n->message)
                                <p class="notif-msg">{{ $n->message }}</p>
                            @endif
                            <div class="notif-actions">
                                @if ($n->link)
                                    <a href="{{ $n->link }}" class="btn primary sm">
                                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                        View
                                    </a>
                                @endif
                                @if (! $n->is_read)
                                    <button type="button" class="btn sm js-mark-read" data-id="{{ $n->id }}">
                                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Mark read
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card">
                        <div class="card-bd notif-empty">
                            <div class="notif-empty-icon">🔕</div>
                            <div class="notif-empty-title">All clear</div>
                            <div class="notif-empty-sub">You have no notifications.</div>
                        </div>
                    </div>
                @endforelse
            </div>

        </div>
    </main>
@endsection

@push('page-scripts')
    <script>
        window.__NOTIF = {
            markReadUrlBase: @json(url('/notifications')),
            markAllReadUrl: @json(route('notifications.read-all')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/notifications.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
