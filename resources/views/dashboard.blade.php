@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="/vendor/datatables/css/dataTables.bootstrap5.min.css">
@endpush

@section('header')
    <div class="d-flex align-items-center justify-content-between">
        <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; margin: 0;">Dashboard</h2>
        @if(auth()->user()->hasPermission('create_quotation'))
            <a href="{{ route('quotations.create') }}" class="btn-accent btn-accent-sm">
                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Quotation
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="shf-stat-card">
                        <div class="shf-stat-icon">
                            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value">{{ number_format($stats['total']) }}</div>
                            <div class="shf-stat-label">Total Quotations</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-4">
                    <div class="shf-stat-card">
                        <div class="shf-stat-icon">
                            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value">{{ number_format($stats['today']) }}</div>
                            <div class="shf-stat-label">Today</div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-4">
                    <div class="shf-stat-card">
                        <div class="shf-stat-icon">
                            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value">{{ number_format($stats['this_month']) }}</div>
                            <div class="shf-stat-label">This Month</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quotation History Section -->
            <div class="shf-section">
                <div class="shf-section-header">
                    <div class="shf-section-number">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="shf-section-title">Quotation History</span>
                </div>

                <!-- Filters -->
                <div class="shf-section-body" style="border-bottom: 1px solid #f0f0f0;">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md">
                            <label class="shf-form-label d-block mb-1">Search</label>
                            <input type="text" id="filter-search"
                                   placeholder="Customer name or filename..."
                                   class="shf-input">
                        </div>

                        <div class="col-6 col-md-auto" style="min-width: 10rem;">
                            <label class="shf-form-label d-block mb-1">Type</label>
                            <select id="filter-type" class="shf-input">
                                <option value="">All Types</option>
                                <option value="proprietor">Proprietor</option>
                                <option value="partnership_llp">Partnership/LLP</option>
                                <option value="pvt_ltd">PVT LTD</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-auto" style="min-width: 9rem;">
                            <label class="shf-form-label d-block mb-1">From</label>
                            <input type="text" id="filter-date-from" class="shf-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>

                        <div class="col-6 col-md-auto" style="min-width: 9rem;">
                            <label class="shf-form-label d-block mb-1">To</label>
                            <input type="text" id="filter-date-to" class="shf-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>

                        @if($permissions['view_all'] && count($users) > 0)
                            <div class="col-6 col-md-auto" style="min-width: 10rem;">
                                <label class="shf-form-label d-block mb-1">Created By</label>
                                <select id="filter-created-by" class="shf-input">
                                    <option value="">All Users</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-12 col-md-auto d-flex gap-2">
                            <button type="button" id="btn-filter" class="btn-accent btn-accent-sm">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Filter
                            </button>
                            <button type="button" id="btn-clear" class="btn-accent-outline btn-accent-sm">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop DataTable (hidden on mobile) -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table id="quotations-table" class="table table-hover w-100">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Loan Amount</th>
                                    <th>Banks</th>
                                    @if($permissions['view_all'])
                                        <th>Created By</th>
                                    @endif
                                    <th>Date</th>
                                    <th class="text-end no-sort">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile card layout (visible on mobile only) -->
                <div class="d-md-none">
                    <div id="mobile-cards-container" class="p-3"></div>
                    <div id="mobile-load-more" class="text-center pb-3" style="display: none;">
                        <button type="button" id="btn-load-more" class="btn-accent-outline btn-accent-sm">
                            Load More
                        </button>
                    </div>
                </div>

                <!-- Empty state (shown by JS when no records) -->
                <div id="empty-state" class="p-5 text-center" style="display: none;">
                    <div class="shf-stat-icon mx-auto mb-3" style="width: 64px; height: 64px;">
                        <svg style="width:32px;height:32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-display fw-semibold" style="font-size: 1.125rem; color: #111827;">No quotations found</h3>
                    <p id="empty-state-text" class="mt-1 small" style="color: #6b7280;">
                        Get started by creating your first quotation.
                    </p>
                    @if(auth()->user()->hasPermission('create_quotation'))
                        <div id="empty-state-cta" class="mt-4">
                            <a href="{{ route('quotations.create') }}" class="btn-accent">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                New Quotation
                            </a>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: var(--radius); border: 1px solid var(--border);">
                <div class="modal-body text-center p-4">
                    <div class="shf-stat-icon mx-auto mb-3" style="width: 48px; height: 48px; background: #fef2f2; color: #dc2626;">
                        <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <h5 class="font-display fw-semibold mb-2" style="font-size: 1rem;">Delete Quotation?</h5>
                    <p class="small mb-0" style="color: #6b7280;">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center gap-2 border-0 pt-0 pb-4">
                    <button type="button" class="btn-accent-outline btn-accent-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-confirm-delete" class="btn-accent btn-accent-sm" style="background: linear-gradient(135deg, #c0392b, #e74c3c);">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="/vendor/datatables/js/dataTables.min.js"></script>
<script src="/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
    var canViewAll = @json($permissions['view_all']);
    var canDownload = @json($permissions['download_pdf']);
    var canDelete = @json($permissions['delete_quotations']);
    var hasFilters = false;
    var deleteUrl = null;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    var dataUrl = @json(route('dashboard.quotation-data'));
    var showingCached = false;

    // --- Suppress DataTables error popup (always) ---
    $.fn.dataTable.ext.errMode = 'none';

    // --- Auto-reload when back online ---
    window.addEventListener('online', function() {
        if (showingCached) {
            showingCached = false;
            $('#offline-indicator').remove();
            try { table.ajax.reload(); } catch(e) {}
            loadMobileCards(true);
        }
    });

    function showCachedIndicator() {
        if ($('#offline-indicator').length) return;
        showingCached = true;
        $('<div id="offline-indicator" class="text-center py-2 small" style="background:#fff8e1;color:#8a6d00;border-bottom:1px solid #ffe082;">' +
            '<svg style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M5.636 18.364a9 9 0 010-12.728"/>' +
            '</svg>' +
            'Offline — showing cached data / ઑફલાઇન — કેશ ડેટા દર્શાવે છે' +
        '</div>').insertBefore('#quotations-table_wrapper, #mobile-cards-container').first();
    }

    // --- LocalStorage cache helpers ---
    function cacheResponse(data) {
        try { localStorage.setItem('shf_dt_cache', JSON.stringify(data)); } catch(e) {}
    }
    function getCachedResponse() {
        try { var s = localStorage.getItem('shf_dt_cache'); return s ? JSON.parse(s) : null; } catch(e) { return null; }
    }
    function cacheMobileData(data) {
        try { localStorage.setItem('shf_mobile_cache', JSON.stringify(data)); } catch(e) {}
    }
    function getCachedMobileData() {
        try { var s = localStorage.getItem('shf_mobile_cache'); return s ? JSON.parse(s) : null; } catch(e) { return null; }
    }

    // --- Bootstrap Datepicker Init ---
    $('.shf-datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        clearBtn: true
    });

    // Convert dd/mm/yyyy → yyyy-mm-dd for server
    function getDateValue(selector) {
        var val = $(selector).val();
        if (!val) return '';
        var parts = val.split('/');
        if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
        return val;
    }

    // --- Desktop DataTable ---
    var table = $('#quotations-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: function(data, callback, settings) {
            data.customer_type = $('#filter-type').val();
            data.date_from = getDateValue('#filter-date-from');
            data.date_to = getDateValue('#filter-date-to');
            data.created_by = $('#filter-created-by').val() || '';

            $.ajax({
                url: dataUrl,
                data: data,
                success: function(res) {
                    cacheResponse(res);
                    $('#offline-indicator').remove();
                    showingCached = false;
                    callback(res);
                },
                error: function() {
                    var cached = getCachedResponse();
                    if (cached) {
                        cached.draw = data.draw;
                        showCachedIndicator();
                        callback(cached);
                    } else {
                        callback({ draw: data.draw, data: [], recordsTotal: 0, recordsFiltered: 0 });
                    }
                }
            });
        },
        columns: buildColumns(),
        order: [[getDateColumnIndex(), 'desc']],
        pageLength: 20,
        lengthMenu: [10, 20, 50, 100],
        dom: '<"shf-dt-top"l>rt<"shf-dt-bottom"ip>',
        language: {
            processing: '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary" role="status"></div></div>',
            emptyTable: ' ',
            zeroRecords: ' ',
            info: 'Showing _START_ to _END_ of _TOTAL_',
            infoEmpty: '',
            infoFiltered: '(filtered from _MAX_)',
            lengthMenu: 'Show _MENU_',
            paginate: { previous: '&laquo;', next: '&raquo;' }
        },
        searching: true,
        drawCallback: function(settings) {
            var total = settings._iRecordsDisplay;
            if (total === 0) {
                updateEmptyState();
                $('#empty-state').show();
            } else {
                $('#empty-state').hide();
            }
        }
    });

    function buildColumns() {
        var cols = [
            { data: 'id', className: 'text-muted', width: '50px' },
            { data: 'customer_name', className: 'fw-medium' },
            {
                data: null,
                render: function(data) {
                    return '<span class="shf-badge ' + data.type_badge_class + '">' + data.type_label + '</span>';
                }
            },
            { data: 'formatted_amount', className: 'fw-medium' },
            {
                data: 'banks',
                orderable: false,
                render: function(data) {
                    if (!data || data.length === 0) return '<span style="color:#9ca3af;">—</span>';
                    var html = '<div class="d-flex flex-wrap gap-1">';
                    var show = data.slice(0, 2);
                    for (var i = 0; i < show.length; i++) {
                        html += '<span class="shf-tag" style="padding:2px 8px;font-size:0.7rem;">' + $('<span>').text(show[i]).html() + '</span>';
                    }
                    if (data.length > 2) {
                        html += '<span class="shf-badge shf-badge-gray" style="font-size:0.7rem;">+' + (data.length - 2) + '</span>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ];

        if (canViewAll) {
            cols.push({ data: 'created_by', className: 'text-muted' });
        }

        cols.push({ data: 'date', className: 'text-muted text-nowrap' });

        cols.push({
            data: null,
            orderable: false,
            className: 'text-end',
            render: function(data) {
                var html = '<div class="d-flex align-items-center justify-content-end gap-2">';
                // View
                html += '<a href="' + data.show_url + '" style="color:#f15a29;" title="View Details">' +
                    '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>' +
                    '</svg></a>';
                // Download
                if (data.download_url) {
                    html += '<a href="' + data.download_url + '" style="color:#27ae60;" title="Download PDF">' +
                        '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' +
                        '</svg></a>';
                }
                // Delete
                if (data.delete_url) {
                    html += '<button type="button" class="btn btn-link p-0 btn-delete" data-url="' + data.delete_url + '" style="color:#c0392b;" title="Delete">' +
                        '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
                        '</svg></button>';
                }
                html += '</div>';
                return html;
            }
        });

        return cols;
    }

    function getDateColumnIndex() {
        return canViewAll ? 6 : 5;
    }

    function updateEmptyState() {
        hasFilters = $('#filter-search').val() || $('#filter-type').val() ||
                     $('#filter-date-from').val() || $('#filter-date-to').val() ||
                     ($('#filter-created-by').length && $('#filter-created-by').val());

        if (hasFilters) {
            $('#empty-state-text').text('Try adjusting your search filters.');
            $('#empty-state-cta').hide();
        } else {
            $('#empty-state-text').text('Get started by creating your first quotation.');
            $('#empty-state-cta').show();
        }
    }

    // --- Filter Buttons ---
    var searchTimer = null;
    $('#filter-search').on('keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            table.search($('#filter-search').val()).draw();
            loadMobileCards(true);
        }, 400);
    });

    // Re-draw on datepicker change
    $('.shf-datepicker').on('changeDate clearDate', function() {
        table.draw();
        loadMobileCards(true);
    });

    $('#btn-filter').on('click', function() {
        table.search($('#filter-search').val()).draw();
        loadMobileCards(true);
    });

    $('#btn-clear').on('click', function() {
        $('#filter-search').val('');
        $('#filter-type').val('');
        $('#filter-date-from').datepicker('clearDates');
        $('#filter-date-to').datepicker('clearDates');
        $('#filter-created-by').val('');
        table.search('').draw();
        loadMobileCards(true);
    });

    // --- AJAX Delete ---
    $(document).on('click', '.btn-delete', function() {
        deleteUrl = $(this).data('url');
        deleteModal.show();
    });

    $('#btn-confirm-delete').on('click', function() {
        if (!deleteUrl) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: deleteUrl,
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            success: function(res) {
                deleteModal.hide();
                showToast(res.message || 'Quotation deleted.', 'success');
                table.draw(false);
                loadMobileCards(true);
                // Update stats (reload page stats section)
                updateStats();
            },
            error: function(xhr) {
                deleteModal.hide();
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to delete quotation.';
                showToast(msg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Delete');
                deleteUrl = null;
            }
        });
    });

    // --- Mobile Cards ---
    var mobileStart = 0;
    var mobileLength = 10;
    var mobileTotal = 0;
    var mobileLoading = false;

    function loadMobileCards(reset) {
        if (mobileLoading) return;
        if (reset) {
            mobileStart = 0;
            $('#mobile-cards-container').empty();
        }
        mobileLoading = true;

        $.ajax({
            url: dataUrl,
            data: {
                draw: 1,
                start: mobileStart,
                length: mobileLength,
                'search[value]': $('#filter-search').val() || '',
                customer_type: $('#filter-type').val() || '',
                date_from: getDateValue('#filter-date-from'),
                date_to: getDateValue('#filter-date-to'),
                created_by: $('#filter-created-by').val() || ''
            },
            success: function(res) {
                cacheMobileData(res);
                $('#offline-indicator').remove();
                showingCached = false;
                renderMobileCards(res);
            },
            error: function() {
                var cached = getCachedMobileData();
                if (cached) {
                    showCachedIndicator();
                    renderMobileCards(cached);
                }
            },
            complete: function() {
                mobileLoading = false;
            }
        });
    }

    function renderMobileCards(res) {
        mobileTotal = res.recordsFiltered;
        var container = $('#mobile-cards-container');

        if (res.data.length === 0 && mobileStart === 0) {
            container.html('');
            return;
        }

        for (var i = 0; i < res.data.length; i++) {
            container.append(buildMobileCard(res.data[i]));
        }

        mobileStart += res.data.length;

        if (mobileStart < mobileTotal) {
            $('#mobile-load-more').show();
        } else {
            $('#mobile-load-more').hide();
        }
    }

    function buildMobileCard(q) {
        var banksHtml = '';
        if (q.banks && q.banks.length > 0) {
            banksHtml = '<div class="d-flex flex-wrap gap-1 justify-content-end">';
            var show = q.banks.slice(0, 2);
            for (var i = 0; i < show.length; i++) {
                banksHtml += '<span class="shf-tag" style="padding:2px 6px;font-size:0.65rem;">' + $('<span>').text(show[i]).html() + '</span>';
            }
            if (q.banks.length > 2) {
                banksHtml += '<span class="shf-badge shf-badge-gray" style="font-size:0.65rem;">+' + (q.banks.length - 2) + '</span>';
            }
            banksHtml += '</div>';
        }

        var createdByHtml = '';
        if (canViewAll && q.created_by) {
            createdByHtml = '<div style="color:#6b7280;font-size:0.72rem;">By ' + $('<span>').text(q.created_by).html() + '</div>';
        }

        var actionsHtml = '<div class="d-flex align-items-center gap-3 pt-2 mt-1" style="border-top:1px solid #f0f0f0;">';
        actionsHtml += '<a href="' + q.show_url + '" class="d-flex align-items-center gap-1" style="color:#f15a29;font-size:0.78rem;text-decoration:none;">' +
            '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>' +
            '</svg>View</a>';

        if (q.download_url) {
            actionsHtml += '<a href="' + q.download_url + '" class="d-flex align-items-center gap-1" style="color:#27ae60;font-size:0.78rem;text-decoration:none;">' +
                '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' +
                '</svg>PDF</a>';
        }

        if (q.delete_url) {
            actionsHtml += '<button type="button" class="btn btn-link p-0 d-flex align-items-center gap-1 ms-auto btn-delete" data-url="' + q.delete_url + '" style="color:#c0392b;font-size:0.78rem;text-decoration:none;">' +
                '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
                '</svg>Delete</button>';
        }
        actionsHtml += '</div>';

        return '<div class="shf-card mb-3 p-3">' +
            '<div class="d-flex align-items-start justify-content-between mb-2">' +
                '<div>' +
                    '<div class="fw-semibold" style="font-size:0.9rem;">' + $('<span>').text(q.customer_name).html() + '</div>' +
                    '<div class="mt-1"><span class="shf-badge ' + q.type_badge_class + '">' + q.type_label + '</span></div>' +
                '</div>' +
                '<span style="color:#6b7280;font-size:0.72rem;">#' + q.id + '</span>' +
            '</div>' +
            '<div class="d-flex align-items-center justify-content-between py-2" style="border-top:1px solid #f0f0f0;">' +
                '<div>' +
                    '<div class="fw-semibold" style="font-size:0.85rem;">' + $('<span>').text(q.formatted_amount).html() + '</div>' +
                    '<div style="color:#6b7280;font-size:0.72rem;">' + q.date + '</div>' +
                '</div>' +
                '<div class="d-flex align-items-center gap-2">' + banksHtml + '</div>' +
            '</div>' +
            createdByHtml +
            actionsHtml +
        '</div>';
    }

    $('#btn-load-more').on('click', function() {
        loadMobileCards(false);
    });

    // Initial mobile load (cache handles offline)
    loadMobileCards(true);

    // --- Stats Update After Delete ---
    function updateStats() {
        $.get(window.location.href, function(html) {
            var $html = $(html);
            var newStats = $html.find('.shf-stat-value');
            $('.shf-stat-value').each(function(i) {
                $(this).text($(newStats[i]).text());
            });
        });
    }

    // --- Toast Helper ---
    function showToast(message, type) {
        type = type || 'success';
        var iconColor = type === 'success' ? '#4ade80' : (type === 'error' ? '#f87171' : '#facc15');
        var iconPath = type === 'success'
            ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
            : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';

        var $toast = $(
            '<div class="shf-toast-wrapper">' +
                '<div class="shf-toast ' + type + '">' +
                    '<svg style="width:16px;height:16px;color:' + iconColor + ';flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + iconPath + '"/>' +
                    '</svg>' +
                    '<span>' + $('<span>').text(message).html() + '</span>' +
                    '<button type="button" class="shf-toast-close" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;margin-left:8px;">' +
                        '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
            '</div>'
        );

        $('body').append($toast);
        $toast.find('.shf-toast-close').on('click', function() {
            $toast.find('.shf-toast').css('animation', 'toastOut 0.3s ease forwards');
            setTimeout(function() { $toast.remove(); }, 300);
        });
        setTimeout(function() {
            $toast.find('.shf-toast').css('animation', 'toastOut 0.3s ease forwards');
            setTimeout(function() { $toast.remove(); }, 300);
        }, 5000);
    }
});
</script>
@endpush
