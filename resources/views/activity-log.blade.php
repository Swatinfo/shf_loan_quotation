@extends('layouts.app')

@section('header')
    <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">
        <svg style="width:16px;height:16px;display:inline;margin-right:6px;color:#f15a29;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Activity Log
    </h2>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            <!-- Filters -->
            <div class="shf-section mb-4">
                <div class="shf-section-body">
                    <form method="GET" action="{{ route('activity-log') }}" class="row g-3 align-items-end">
                        <div class="col-6 col-md-auto" style="min-width: 10rem;">
                            <label class="shf-form-label d-block mb-1">User</label>
                            <select name="user_id" class="shf-input">
                                <option value="">All Users</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-auto" style="min-width: 11rem;">
                            <label class="shf-form-label d-block mb-1">Action</label>
                            <select name="action" class="shf-input">
                                <option value="">All Actions</option>
                                @foreach($actionTypes as $type)
                                    <option value="{{ $type }}" {{ request('action') === $type ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $type)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-auto" style="min-width: 9rem;">
                            <label class="shf-form-label d-block mb-1">From</label>
                            <input type="text" id="dp-date-from" value="{{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d/m/Y') : '' }}" class="shf-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                            <input type="hidden" name="date_from" id="date_from_hidden" value="{{ request('date_from') }}">
                        </div>

                        <div class="col-6 col-md-auto" style="min-width: 9rem;">
                            <label class="shf-form-label d-block mb-1">To</label>
                            <input type="text" id="dp-date-to" value="{{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d/m/Y') : '' }}" class="shf-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                            <input type="hidden" name="date_to" id="date_to_hidden" value="{{ request('date_to') }}">
                        </div>

                        <div class="col-12 col-md-auto d-flex gap-2">
                            <button type="submit" class="btn-accent btn-accent-sm">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Filter
                            </button>
                            <a href="{{ route('activity-log') }}" class="btn-accent-outline btn-accent-sm">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Log Table -->
            <div class="shf-section">
                <div class="shf-section-header">
                    <div class="shf-section-number">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <span class="shf-section-title">Activity Records</span>
                </div>

                @if($logs->count() > 0)
                    <!-- Desktop table -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Subject</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr>
                                        <td style="white-space: nowrap; color: #6b7280;">
                                            {{ $log->created_at->format('d M Y') }}
                                            <span class="d-block small" style="color: #9ca3af;">{{ $log->created_at->format('h:i A') }}</span>
                                        </td>
                                        <td class="fw-medium">{{ $log->user?->name ?? 'System' }}</td>
                                        <td>
                                            @php
                                                $actionBadges = [
                                                    'login' => 'shf-badge-green',
                                                    'logout' => 'shf-badge-gray',
                                                    'create_quotation' => 'shf-badge-blue',
                                                    'delete_quotation' => 'shf-badge-red',
                                                    'update_settings' => 'shf-badge-orange',
                                                    'create_user' => 'shf-badge-blue',
                                                    'update_user' => 'shf-badge-blue',
                                                    'delete_user' => 'shf-badge-red',
                                                    'update_permissions' => 'shf-badge-orange',
                                                ];
                                                $badgeClass = $actionBadges[$log->action] ?? 'shf-badge-gray';
                                            @endphp
                                            <span class="shf-badge {{ $badgeClass }}">
                                                {{ ucwords(str_replace('_', ' ', $log->action)) }}
                                            </span>
                                        </td>
                                        <td style="color: #6b7280;">
                                            @if($log->subject_type)
                                                {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td style="color: #6b7280; max-width: 20rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            @if($log->properties)
                                                @php $props = $log->properties; @endphp
                                                @if(isset($props['customer_name']))
                                                    {{ $props['customer_name'] }}
                                                    @if(isset($props['loan_amount']))
                                                        — ₹ {{ number_format($props['loan_amount']) }}
                                                    @endif
                                                @elseif(isset($props['name']))
                                                    {{ $props['name'] }}
                                                @elseif(isset($props['section']))
                                                    Section: {{ $props['section'] }}
                                                @else
                                                    <span class="small" style="color: #9ca3af;">{{ Str::limit(json_encode($props), 80) }}</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td style="white-space: nowrap; font-size: 0.75rem; color: #9ca3af;">
                                            {{ $log->ip_address ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile card layout -->
                    <div class="d-md-none p-3">
                        @foreach($logs as $log)
                            @php
                                $actionBadges = [
                                    'login' => 'shf-badge-green', 'logout' => 'shf-badge-gray',
                                    'create_quotation' => 'shf-badge-blue', 'delete_quotation' => 'shf-badge-red',
                                    'update_settings' => 'shf-badge-orange', 'create_user' => 'shf-badge-blue',
                                    'update_user' => 'shf-badge-blue', 'delete_user' => 'shf-badge-red',
                                    'update_permissions' => 'shf-badge-orange',
                                ];
                                $badgeClass = $actionBadges[$log->action] ?? 'shf-badge-gray';
                            @endphp
                            <div class="shf-card mb-3 p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="shf-badge {{ $badgeClass }}">{{ ucwords(str_replace('_', ' ', $log->action)) }}</span>
                                    <span style="color: #9ca3af; font-size: 0.7rem;">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                </div>
                                <div class="fw-medium" style="font-size: 0.85rem;">{{ $log->user?->name ?? 'System' }}</div>
                                @if($log->subject_type)
                                    <div style="color: #6b7280; font-size: 0.78rem;">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                                @endif
                                @if($log->properties)
                                    @php $props = $log->properties; @endphp
                                    <div style="color: #6b7280; font-size: 0.78rem; margin-top: 4px;">
                                        @if(isset($props['customer_name']))
                                            {{ $props['customer_name'] }}
                                            @if(isset($props['loan_amount']))
                                                — ₹ {{ number_format($props['loan_amount']) }}
                                            @endif
                                        @elseif(isset($props['name']))
                                            {{ $props['name'] }}
                                        @elseif(isset($props['section']))
                                            Section: {{ $props['section'] }}
                                        @endif
                                    </div>
                                @endif
                                @if($log->ip_address)
                                    <div style="color: #9ca3af; font-size: 0.68rem; margin-top: 4px;">IP: {{ $log->ip_address }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="px-4 py-3 shf-pagination" style="border-top: 1px solid #f0f0f0;">
                        {{ $logs->links() }}
                    </div>
                @else
                    <div class="p-5 text-center">
                        <div class="shf-stat-icon mx-auto mb-3" style="width: 64px; height: 64px;">
                            <svg style="width:32px;height:32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="font-display fw-semibold" style="font-size: 1.125rem; color: #111827;">No activity recorded</h3>
                        <p class="mt-1 small" style="color: #6b7280;">
                            @if(request()->hasAny(['user_id', 'action', 'date_from', 'date_to']))
                                Try adjusting your filters.
                            @else
                                Activity will appear here as users perform actions.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    $(function() {
        $('.shf-datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true,
            clearBtn: true
        });

        // Sync visible datepicker → hidden input (yyyy-mm-dd)
        function syncDateHidden(dpId, hiddenId) {
            $(dpId).on('changeDate', function(e) {
                if (e.date) {
                    var d = e.date;
                    var yyyy = d.getFullYear();
                    var mm = String(d.getMonth() + 1).padStart(2, '0');
                    var dd = String(d.getDate()).padStart(2, '0');
                    $(hiddenId).val(yyyy + '-' + mm + '-' + dd);
                }
            }).on('clearDate', function() {
                $(hiddenId).val('');
            });
        }
        syncDateHidden('#dp-date-from', '#date_from_hidden');
        syncDateHidden('#dp-date-to', '#date_to_hidden');
    });
    </script>
    @endpush
@endsection
