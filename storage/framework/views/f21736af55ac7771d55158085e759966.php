<?php $__env->startSection('title', 'Turnaround Time Report — SHF'); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('vendor/datatables/css/dataTables.bootstrap5.min.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('header'); ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Turnaround Time Report / ટર્નઅરાઉન્ડ ટાઇમ રિપોર્ટ
        </h2>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="py-4">
    <div class="px-3 px-sm-4 px-lg-5">

        
        <div class="shf-section mb-4">
            <div class="shf-section-header d-flex align-items-center justify-content-between">
                <span class="shf-section-title">Filters</span>
            </div>
            <div class="shf-section-body">
                <div class="row g-2">
                    <div class="col-6 col-md-auto">
                        <label class="shf-form-label">Period</label>
                        <select id="filterPeriod" class="shf-input shf-input-sm shf-report-filter">
                            <option value="current_month" selected>Current Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="current_quarter">Current Quarter</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="current_year">Current Year</option>
                            <option value="last_year">Last Year</option>
                            <option value="all_time">All Time</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-auto shf-custom-dates" style="display:none;">
                        <label class="shf-form-label">From Date</label>
                        <input type="text" id="filterDateFrom" class="shf-input shf-input-sm shf-datepicker shf-report-filter" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-auto shf-custom-dates" style="display:none;">
                        <label class="shf-form-label">To Date</label>
                        <input type="text" id="filterDateTo" class="shf-input shf-input-sm shf-datepicker shf-report-filter" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-auto">
                        <label class="shf-form-label">Bank</label>
                        <select id="filterBank" class="shf-input shf-input-sm shf-report-filter">
                            <option value="">All Banks</option>
                            <?php $__currentLoopData = $banks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($bank->id); ?>"><?php echo e($bank->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-auto">
                        <label class="shf-form-label">Product</label>
                        <select id="filterProduct" class="shf-input shf-input-sm shf-report-filter">
                            <option value="">All Products</option>
                            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($product->id); ?>"><?php echo e($product->bank?->name); ?> / <?php echo e($product->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <?php if($scope['type'] !== 'self'): ?>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label">Branch</label>
                            <select id="filterBranch" class="shf-input shf-input-sm shf-report-filter">
                                <option value="">All Branches</option>
                                <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($branch->id); ?>"><?php echo e($branch->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label">User</label>
                            <select id="filterUser" class="shf-input shf-input-sm shf-report-filter">
                                <option value="">All Users</option>
                                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" id="filterBranch" value="">
                        <input type="hidden" id="filterUser" value="">
                    <?php endif; ?>
                    <div class="col-6 col-md-auto d-none" id="stageFilterWrap">
                        <label class="shf-form-label">Stage</label>
                        <select id="filterStage" class="shf-input shf-input-sm shf-report-filter">
                            <option value="">All Stages</option>
                            <?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($stage->stage_key); ?>"><?php echo e($stage->stage_name_en); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex align-items-end gap-2">
                        <button id="applyFilters" class="btn-accent btn-accent-sm">
                            <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            Filter
                        </button>
                        <button id="clearFilters" class="btn-accent-outline btn-accent-sm">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="shf-tabs mb-3">
            <button class="shf-tab active" data-tab="overall">Overall Loan TAT</button>
            <button class="shf-tab" data-tab="stagewise">Stage-wise TAT</button>
        </div>

        
        <div id="tab-overall">
            <div class="shf-section shf-dt-section">
                <div class="shf-section-header">
                    <span class="shf-section-title">Overall Turnaround Time (Completed Loans by Advisor)</span>
                </div>
                <div class="shf-section-body p-0">
                    <table id="overallTable" class="table table-hover mb-0 w-100">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Bank</th>
                                <th class="text-center">Loans</th>
                                <th>Fastest</th>
                                <th>Average</th>
                                <th>Slowest</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div id="tab-stagewise" class="d-none">
            <div class="shf-section shf-dt-section">
                <div class="shf-section-header">
                    <span class="shf-section-title">Stage-wise Turnaround Time (Per User Per Bank)</span>
                </div>
                <div class="shf-section-body p-0">
                    <table id="stageTable" class="table table-hover mb-0 w-100">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Bank</th>
                                <th>Stage</th>
                                <th class="text-center">Count</th>
                                <th>Fastest</th>
                                <th>Average</th>
                                <th>Slowest</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="<?php echo e(asset('vendor/datatables/js/dataTables.min.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/datatables/js/dataTables.bootstrap5.min.js')); ?>"></script>
<script>
$(function() {
    $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });

    var activeTab = 'overall';

    // Period presets → {from, to} in dd/mm/yyyy
    function getPeriodDates(period) {
        var now = new Date();
        var y = now.getFullYear(), m = now.getMonth(); // 0-indexed month
        var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
        var fmt = function(d) { return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear(); };

        switch (period) {
            case 'current_month':
                return { from: fmt(new Date(y, m, 1)), to: fmt(now) };
            case 'last_month':
                return { from: fmt(new Date(y, m - 1, 1)), to: fmt(new Date(y, m, 0)) };
            case 'current_quarter':
                var qStart = m - (m % 3);
                return { from: fmt(new Date(y, qStart, 1)), to: fmt(now) };
            case 'last_quarter':
                var qStart = m - (m % 3) - 3;
                var qy = qStart < 0 ? y - 1 : y;
                if (qStart < 0) qStart += 12;
                return { from: fmt(new Date(qy, qStart, 1)), to: fmt(new Date(qy, qStart + 3, 0)) };
            case 'current_year':
                return { from: fmt(new Date(y, 0, 1)), to: fmt(now) };
            case 'last_year':
                return { from: fmt(new Date(y - 1, 0, 1)), to: fmt(new Date(y - 1, 11, 31)) };
            case 'all_time':
                return { from: '', to: '' };
            default:
                return { from: $('#filterDateFrom').val(), to: $('#filterDateTo').val() };
        }
    }

    // Set initial dates to current month
    var initDates = getPeriodDates('current_month');
    $('#filterDateFrom').val(initDates.from);
    $('#filterDateTo').val(initDates.to);

    // Loading state
    function setLoading(loading) {
        var $filters = $('.shf-section-body select, .shf-section-body input, .shf-section-body button');
        if (loading) {
            $filters.prop('disabled', true);
            $('.shf-dt-section').css('opacity', '0.5');
        } else {
            $filters.prop('disabled', false);
            $('.shf-dt-section').css('opacity', '1');
        }
    }

    function getFilters() {
        var period = $('#filterPeriod').val();
        var dates = (period === 'custom') ? { from: $('#filterDateFrom').val(), to: $('#filterDateTo').val() } : getPeriodDates(period);
        var dateFrom = dates.from;
        var dateTo = dates.to;
        // Convert dd/mm/yyyy to yyyy-mm-dd for server
        if (dateFrom) {
            var p = dateFrom.split('/');
            dateFrom = p[2] + '-' + p[1] + '-' + p[0];
        }
        if (dateTo) {
            var p = dateTo.split('/');
            dateTo = p[2] + '-' + p[1] + '-' + p[0];
        }
        return {
            tab: activeTab,
            date_from: dateFrom || '',
            date_to: dateTo || '',
            bank_id: $('#filterBank').val() || '',
            product_id: $('#filterProduct').val() || '',
            branch_id: $('#filterBranch').val() || '',
            user_id: $('#filterUser').val() || '',
            stage_key: activeTab === 'stagewise' ? ($('#filterStage').val() || '') : ''
        };
    }

    function colorDays(val, raw) {
        if (raw === undefined || raw === null) return val;
        var cls = raw <= 30 ? 'shf-text-success-alt' : (raw <= 60 ? 'text-warning' : 'text-danger');
        return '<span class="fw-semibold ' + cls + '">' + val + '</span>';
    }

    function colorHours(val, raw) {
        if (raw === undefined || raw === null) return val;
        var days = raw / 24;
        var cls = days <= 3 ? 'shf-text-success-alt' : (days <= 7 ? 'text-warning' : 'text-danger');
        return '<span class="fw-semibold ' + cls + '">' + val + '</span>';
    }

    // Overall DataTable
    var overallDt = $('#overallTable').DataTable({
        processing: true,
        ajax: {
            url: '<?php echo e(route("reports.turnaround.data")); ?>',
            data: function(d) { return $.extend({}, d, getFilters()); },
            dataSrc: 'data',
            beforeSend: function() { setLoading(true); },
            complete: function() { setLoading(false); }
        },
        columns: [
            { data: 'user_name' },
            { data: 'bank_name' },
            { data: 'total_loans', className: 'text-center' },
            { data: 'min_days', render: function(d, t, r) { return colorDays(d, r.min_days_raw); } },
            { data: 'avg_days', render: function(d, t, r) { return colorDays(d, r.avg_days_raw); } },
            { data: 'max_days', render: function(d, t, r) { return colorDays(d, r.max_days_raw); } }
        ],
        dom: 'rt<"shf-dt-bottom"ip>',
        pageLength: 50,
        order: [[4, 'asc']],
        language: { emptyTable: 'No completed loans found for the selected filters.' },
        drawCallback: function(settings) {
            var api = this.api();
            if (api.rows().count() === 0) {
                $(api.table().container()).find('tbody').html(
                    '<tr><td colspan="6" class="text-center py-4"><div class="shf-empty-state-icon shf-empty-icon-blue mb-2"><svg style="width:32px;height:32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div><p class="text-muted mb-0">No completed loans found for the selected filters.</p></td></tr>'
                );
            }
        }
    });

    // Stage-wise DataTable (lazy-loaded on first tab switch)
    var stageInited = false;
    var stageDt = null;

    // Tab switching
    $(document).on('click', '.shf-tab', function() {
        var tab = $(this).data('tab');
        activeTab = tab;
        $('.shf-tab').removeClass('active');
        $(this).addClass('active');
        $('#tab-overall, #tab-stagewise').addClass('d-none');
        $('#tab-' + tab).removeClass('d-none');
        // Show/hide stage filter
        if (tab === 'stagewise') {
            $('#stageFilterWrap').removeClass('d-none');
            if (!stageInited) {
                stageDt = $('#stageTable').DataTable({
                    processing: true,
                    ajax: {
                        url: '<?php echo e(route("reports.turnaround.data")); ?>',
                        data: function(d) { return $.extend({}, d, getFilters()); },
                        dataSrc: 'data',
                        beforeSend: function() { setLoading(true); },
                        complete: function() { setLoading(false); }
                    },
                    columns: [
                        { data: 'user_name' },
                        { data: 'bank_name' },
                        { data: 'stage_name' },
                        { data: 'times_handled', className: 'text-center' },
                        { data: 'min_time', render: function(d, t, r) { return colorHours(d, r.min_hours_raw); } },
                        { data: 'avg_time', render: function(d, t, r) { return colorHours(d, r.avg_hours_raw); } },
                        { data: 'max_time', render: function(d, t, r) { return colorHours(d, r.max_hours_raw); } }
                    ],
                    dom: 'rt<"shf-dt-bottom"ip>',
                    pageLength: 50,
                    order: [[0, 'asc'], [2, 'asc']],
                    language: { emptyTable: 'No stage data found for the selected filters.' }
                });
                stageInited = true;
            } else {
                stageDt.ajax.reload();
            }
        } else {
            $('#stageFilterWrap').addClass('d-none');
        }
    });

    // Refresh active table with loading state
    function refreshData() {
        setLoading(true);
        var dt = activeTab === 'overall' ? overallDt : stageDt;
        if (dt) {
            dt.ajax.reload(function() { setLoading(false); }, false);
        } else {
            setLoading(false);
        }
    }

    // Period dropdown: toggle custom date fields + update dates + refresh
    $('#filterPeriod').on('change', function() {
        var period = $(this).val();
        if (period === 'custom') {
            $('.shf-custom-dates').show();
            return; // Don't auto-refresh — wait for user to pick dates
        }
        $('.shf-custom-dates').hide();
        var dates = getPeriodDates(period);
        $('#filterDateFrom').val(dates.from);
        $('#filterDateTo').val(dates.to);
        refreshData();
    });

    // Auto-refresh when any filter changes (except custom date inputs handled by Apply)
    $(document).on('change', '.shf-report-filter', function() {
        if ($(this).attr('id') === 'filterPeriod') return; // handled above
        if ($(this).attr('id') === 'filterDateFrom' || $(this).attr('id') === 'filterDateTo') return; // custom dates use Apply
        refreshData();
    });

    // Filter buttons
    $('#applyFilters').on('click', function() {
        refreshData();
    });

    $('#clearFilters').on('click', function() {
        $('#filterPeriod').val('current_month');
        $('.shf-custom-dates').hide();
        var dates = getPeriodDates('current_month');
        $('#filterDateFrom').val(dates.from);
        $('#filterDateTo').val(dates.to);
        $('#filterBank, #filterProduct, #filterBranch, #filterUser, #filterStage').val('');
        refreshData();
    });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH F:\G Drive\Projects\quotationshf\resources\views/reports/turnaround.blade.php ENDPATH**/ ?>