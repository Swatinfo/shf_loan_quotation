@extends('layouts.app')
@section('title', 'Users — SHF')

@section('header')
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            User Management
        </h2>
        @if (auth()->user()->hasPermission('create_users'))
            <a href="{{ route('users.create') }}" class="btn-accent btn-accent-sm">
                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New User
            </a>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/css/dataTables.bootstrap5.min.css') }}">
@endpush

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">
            {{-- Filters --}}
            <div id="usersFilterSection" class="shf-section mb-3">
                <div class="shf-section-header shf-collapsible shf-filter-collapse shf-clickable d-flex align-items-center gap-2" data-target="#usersFilterBody">
                    <svg class="shf-collapse-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span class="shf-section-title">Filters</span>
                    <span id="usersFilterCount" class="shf-filter-count shf-collapse-hidden">0</span>
                </div>
                <div id="usersFilterBody" class="shf-section-body shf-filter-body-collapse">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="shf-form-label d-block mb-1">&nbsp;</label>
                            <div class="shf-per-page">
                                <span>Show</span>
                                <select id="userPageLength">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Search</label>
                            <input type="text" id="userSearch" class="shf-input" placeholder="Name, email or phone..." style="min-width:10rem;">
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Status</label>
                            <select id="filterStatus" class="shf-input">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Role</label>
                            <select id="filterRole" class="shf-input">
                                <option value="">All</option>
                                @foreach (\App\Models\Role::orderBy('id')->get() as $r)
                                    <option value="{{ $r->slug }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-auto d-flex gap-2">
                            <label class="shf-form-label d-block mb-1">&nbsp;</label>
                            <button type="button" id="btnUserFilter" class="btn-accent btn-accent-sm">
                                <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Filter
                            </button>
                            <button type="button" id="btnUserClear" class="btn-accent-outline btn-accent-sm"><svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table + Mobile Cards --}}
            <div id="usersTableSection" class="shf-section shf-dt-section">
                <div id="usersMobileCards" class="d-md-none p-3"></div>
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- Empty State --}}
            <div id="usersEmptyState" class="shf-collapse-hidden">
                <div class="shf-section">
                    <div class="p-5 text-center">
                        <div class="shf-stat-icon mx-auto mb-3 shf-empty-icon-blue">
                            <svg class="shf-icon-xl" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <h3 class="font-display fw-semibold shf-text-lg shf-text-dark-alt">No users found</h3>
                        <p class="mt-1 small shf-text-gray">Try adjusting your search filters.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/datatables/js/dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    var table = $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: @json(route('users.data')),
            data: function(d) {
                d.role = $('#filterRole').val();
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'name_html' },
            { data: 'email' },
            { data: 'role_html', orderable: false },
            { data: 'branch_html', orderable: false },
            { data: 'status_html' },
            { data: 'created_html' },
            { data: 'actions_html', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[5, 'desc']],
        pageLength: 50,
        dom: 'rt<"shf-dt-bottom"ip>',
        language: {
            info: 'Showing _START_ to _END_ of _TOTAL_ users',
            infoEmpty: '',
            infoFiltered: '(filtered from _MAX_ total)',
            emptyTable: ' ',
            zeroRecords: ' ',
            paginate: { previous: '&laquo;', next: '&raquo;' }
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal;
            var filtered = settings._iRecordsDisplay;
            var $bottom = $('#usersTable_wrapper .shf-dt-bottom');

            if (filtered === 0) {
                $bottom.hide();
                $('#usersTableSection').hide();
                $('#usersEmptyState').show();
                $('#usersMobileCards').html('');
                if (total === 0) {
                    $('#usersFilterSection').hide();
                }
                return;
            }
            $bottom.show();
            $('#usersFilterSection').show();
            $('#usersTableSection').show();
            $('#usersEmptyState').hide();

            // Build mobile cards
            var data = this.api().rows({ page: 'current' }).data();
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var d = data[i];
                var statusBadge = d.is_active ? 'shf-badge-green' : 'shf-badge-red';
                var statusLabel = d.is_active ? 'Active' : 'Inactive';
                html += '<div class="shf-card mb-3 p-3">'
                    + '<div class="d-flex align-items-start justify-content-between mb-2">'
                    + '<div>'
                    + '<div class="fw-semibold shf-text-base">' + $('<span>').text(d.name).html() + '</div>'
                    + '<div class="shf-text-gray shf-text-sm">' + $('<span>').text(d.email).html() + '</div>'
                    + (d.phone ? '<div class="shf-text-gray" style="font-size:0.72rem">' + $('<span>').text(d.phone).html() + '</div>' : '')
                    + '</div>'
                    + '<span class="shf-badge ' + statusBadge + '">' + statusLabel + '</span>'
                    + '</div>'
                    + '<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">'
                    + d.role_html
                    + (d.branches ? ' <small class="shf-text-xs shf-text-gray">' + $('<span>').text(d.branches).html() + '</small>' : '')
                    + ' <span class="shf-text-xs shf-text-gray-light">' + d.created_at + '</span>'
                    + '</div>'
                    + '<div class="pt-2 shf-border-top-light">' + d.actions_html + '</div>'
                    + '</div>';
            }
            $('#usersMobileCards').html(html);
        }
    });

    // Per-page
    $('#userPageLength').on('change', function() { table.page.len(parseInt(this.value)).draw(); });

    // Search with debounce
    var searchTimer = null;
    $('#userSearch').on('keyup', function() {
        clearTimeout(searchTimer);
        var val = this.value;
        searchTimer = setTimeout(function() { table.search(val).draw(); }, 300);
    });

    // Filter / Clear buttons
    $('#btnUserFilter').on('click', function() { table.ajax.reload(); });
    $('#btnUserClear').on('click', function() {
        $('#filterRole, #filterStatus').val('');
        $('#userSearch').val('');
        table.search('').ajax.reload();
    });

    // Toggle active via AJAX
    $(document).on('click', '.btn-toggle-active', function() {
        var $btn = $(this);
        Swal.fire({
            title: 'Are you sure?',
            text: 'This will change the user\'s active status.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f15a29',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post($btn.data('url'), { _token: csrfToken }, function() {
                    table.ajax.reload(null, false);
                });
            }
        });
    });

    // Delete via AJAX
    $(document).on('click', '.btn-delete-user', function() {
        var $btn = $(this);
        Swal.fire({
            title: 'Delete this user?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: $btn.data('url'),
                    type: 'DELETE',
                    data: { _token: csrfToken },
                    success: function(r) {
                        Swal.fire({ icon: 'success', title: r.message || 'User deleted', timer: 1500, showConfirmButton: false });
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete user.', 'error');
                    }
                });
            }
        });
    });

    // Filter count badge
    function updateUserFilterCount() {
        var count = 0;
        if ($('#filterStatus').val()) count++;
        if ($('#filterRole').val()) count++;
        if ($('#userSearch').val()) count++;
        var $badge = $('#usersFilterCount');
        if (count > 0) { $badge.text(count).removeClass('shf-collapse-hidden'); }
        else { $badge.addClass('shf-collapse-hidden'); }
    }
    $(document).on('change', '#filterStatus, #filterRole', updateUserFilterCount);
    $('#userSearch').on('keyup', updateUserFilterCount);
    $('#btnUserClear').on('click', function() { setTimeout(updateUserFilterCount, 50); });
});
</script>
@endpush
