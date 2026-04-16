<?php $__env->startSection('title', 'Loans — SHF'); ?>

<?php $__env->startSection('header'); ?>
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
        <h2 class="font-display fw-semibold text-white shf-page-title">
            <svg class="shf-header-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            Loans
        </h2>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('vendor/datatables/css/dataTables.bootstrap5.min.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="py-4">
        <div class="px-3 px-sm-4 px-lg-5">

            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="shf-stat-card">
                        <div class="shf-stat-icon">
                            <svg class="shf-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value"><?php echo e(number_format($stats['total'])); ?></div>
                            <div class="shf-stat-label">Total Loans</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="shf-stat-card shf-stat-card-blue">
                        <div class="shf-stat-icon shf-stat-icon-blue">
                            <svg class="shf-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value"><?php echo e(number_format($stats['active'])); ?></div>
                            <div class="shf-stat-label">Active</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="shf-stat-card shf-stat-card-green">
                        <div class="shf-stat-icon shf-stat-icon-green">
                            <svg class="shf-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value"><?php echo e(number_format($stats['completed'])); ?></div>
                            <div class="shf-stat-label">Completed</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="shf-stat-card shf-stat-card-accent">
                        <div class="shf-stat-icon shf-stat-icon-accent">
                            <svg class="shf-icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <div class="shf-stat-value"><?php echo e(number_format($stats['this_month'])); ?></div>
                            <div class="shf-stat-label">This Month</div>
                        </div>
                    </div>
                </div>
            </div>

            
            <div id="loansFilterSection" class="shf-section mb-3">
                <div class="shf-section-header shf-collapsible shf-filter-collapse shf-clickable d-flex align-items-center gap-2"
                    data-target="#loansFilterBody">
                    <svg class="shf-collapse-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="shf-section-title">Filters</span>
                    <span id="loansFilterCount" class="shf-filter-count shf-collapse-hidden">0</span>
                </div>
                <div id="loansFilterBody" class="shf-section-body shf-filter-body-collapse">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="shf-form-label d-block mb-1">&nbsp;</label>
                            <div class="shf-per-page">
                                <span>Show</span>
                                <select id="loanPageLength">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Status</label>
                            <select id="filterStatus" class="shf-input">
                                <option value="">All Status</option>
                                <?php $__currentLoopData = \App\Models\LoanDetail::STATUS_LABELS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($key); ?>" <?php echo e($key === 'active' ? 'selected' : ''); ?>>
                                        <?php echo e($label['label']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Type</label>
                            <select id="filterType" class="shf-input">
                                <option value="">All Types</option>
                                <?php $__currentLoopData = \App\Models\LoanDetail::CUSTOMER_TYPE_LABELS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <?php
                            $loanUser = auth()->user();
                            $loanIsBankEmp = $loanUser->hasRole('bank_employee');
                            $loanIsAdminOrMgr = $loanUser->hasAnyRole([
                                'super_admin',
                                'admin',
                                'branch_manager',
                                'bdh',
                            ]);
                        ?>
                        <?php if(!$loanIsBankEmp): ?>
                            <div class="col-6 col-md-auto">
                                <label class="shf-form-label d-block mb-1">Bank</label>
                                <select id="filterBank" class="shf-input">
                                    <option value="">All Banks</option>
                                    <?php $__currentLoopData = $banks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($bank->id); ?>"><?php echo e($bank->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if($loanIsAdminOrMgr): ?>
                            <div class="col-6 col-md-auto">
                                <label class="shf-form-label d-block mb-1">Branch</label>
                                <select id="filterBranch" class="shf-input">
                                    <option value="">All Branches</option>
                                    <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($branch->id); ?>"><?php echo e($branch->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">Stage</label>
                            <select id="filterStage" class="shf-input">
                                <option value="">All Stages</option>
                                <?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($stage->stage_key); ?>"><?php echo e($stage->stage_name_en); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <?php if($loanIsAdminOrMgr): ?>
                            <div class="col-6 col-md-auto">
                                <label class="shf-form-label d-block mb-1">Owner Role</label>
                                <select id="filterRole" class="shf-input">
                                    <option value="">All Roles</option>
                                    <?php $__currentLoopData = \App\Models\Role::orderBy('id')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($r->slug); ?>"><?php echo e($r->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if(!$loanIsBankEmp): ?>
                            <div class="col-6 col-md-auto">
                                <label class="shf-form-label d-block mb-1">Docket</label>
                                <select id="filterDocket" class="shf-input">
                                    <option value="">All</option>
                                    <option value="overdue">Overdue</option>
                                    <option value="due_today">Due Today</option>
                                    <option value="due_soon">Due Soon (7 days)</option>
                                    <option value="due_15">Due in 15 days</option>
                                    <option value="due_month">Due in 1 month</option>
                                    <option value="custom">Custom Date</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-auto shf-collapse-hidden" id="docketCustomDate">
                                <label class="shf-form-label d-block mb-1">Docket By</label>
                                <input type="text" id="filterDocketDate" class="shf-input shf-datepicker"
                                    placeholder="dd/mm/yyyy" autocomplete="off">
                            </div>
                        <?php endif; ?>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">From</label>
                            <input type="text" id="filterDateFrom" class="shf-input shf-datepicker"
                                placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>
                        <div class="col-6 col-md-auto">
                            <label class="shf-form-label d-block mb-1">To</label>
                            <input type="text" id="filterDateTo" class="shf-input shf-datepicker"
                                placeholder="dd/mm/yyyy" autocomplete="off">
                        </div>
                        <div class="col-12 col-md-auto d-flex gap-2">
                            <label class="shf-form-label d-block mb-1">&nbsp;</label>
                            <button type="button" id="btnLoanFilter" class="btn-accent btn-accent-sm">
                                <svg class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Filter
                            </button>
                            <button type="button" id="btnLoanClear" class="btn-accent-outline btn-accent-sm"><svg
                                    class="shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg> Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            
            <div id="loansTableSection" class="shf-section shf-dt-section">
                <div id="loansMobileCards" class="d-md-none"></div>
                <div class="table-responsive">
                    <table id="loansTable" class="table table-hover mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Loan #</th>
                                <th>Customer</th>
                                <th>Bank / Product</th>
                                <th class="text-end">Amount</th>
                                <th>Stage</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            
            <div id="loansEmptyState" class="shf-collapse-hidden">
                <div class="shf-section">
                    <div class="p-5 text-center">
                        <div class="shf-stat-icon mx-auto mb-3 shf-empty-icon-blue">
                            <svg class="shf-icon-xl" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h3 class="font-display fw-semibold shf-text-lg shf-text-dark-alt">No loans found</h3>
                        <p class="mt-1 small shf-text-gray">Try adjusting your filters or convert a quotation to create a
                            loan.</p>
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
            function convertDate(val) {
                if (!val) return '';
                var parts = val.split('/');
                return parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : val;
            }

            var table = $('#loansTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '<?php echo e(route('loans.data')); ?>',
                    data: function(d) {
                        d.status = $('#filterStatus').val();
                        d.customer_type = $('#filterType').val();
                        d.bank_id = $('#filterBank').val();
                        d.branch_id = $('#filterBranch').val();
                        d.date_from = convertDate($('#filterDateFrom').val());
                        d.date_to = convertDate($('#filterDateTo').val());
                        d.stage = $('#filterStage').val();
                        d.role = $('#filterRole').val();
                        d.docket = $('#filterDocket').val();
                        d.docket_date = convertDate($('#filterDocketDate').val());
                    }
                },
                columns: [{
                        data: 'loan_number'
                    },
                    {
                        data: 'customer_name'
                    },
                    {
                        data: 'bank_product'
                    },
                    {
                        data: 'amount_info',
                        className: 'text-end'
                    },
                    {
                        data: 'current_stage_name'
                    },
                    {
                        data: 'owner_info',
                        orderable: false
                    },
                    {
                        data: 'status_label'
                    },
                    {
                        data: 'created_at'
                    },
                    {
                        data: 'actions_html',
                        orderable: false,
                        searchable: false
                    }
                ],
                order: [
                    [0, 'desc']
                ],
                pageLength: 50,
                lengthMenu: [10, 25, 50, 100],
                dom: 'rt<"shf-dt-bottom"ip>',
                language: {
                    search: '',
                    searchPlaceholder: 'Search loans...',
                    info: 'Showing _START_ to _END_ of _TOTAL_ loans',
                    infoEmpty: '',
                    infoFiltered: '(filtered from _MAX_ total)',
                    emptyTable: ' ',
                    zeroRecords: ' ',
                    paginate: {
                        previous: '‹',
                        next: '›'
                    }
                },
                drawCallback: function(settings) {
                    var total = settings._iRecordsTotal;
                    var filtered = settings._iRecordsDisplay;
                    var $bottom = $('#loansTable_wrapper .shf-dt-bottom');

                    if (filtered === 0) {
                        $bottom.hide();
                        $('#loansTableSection').hide();
                        $('#loansEmptyState').show();
                        $('#loansMobileCards').html('');
                        // Keep filters visible if total > 0 (user filtered to zero)
                        if (total === 0) {
                            $('#loansFilterSection').hide();
                        }
                        return;
                    }
                    $bottom.show();
                    $('#loansFilterSection').show();
                    $('#loansTableSection').show();
                    $('#loansEmptyState').hide();

                    // Build mobile cards
                    var data = this.api().rows({
                        page: 'current'
                    }).data();
                    var html = '';
                    for (var i = 0; i < data.length; i++) {
                        var d = data[i];
                        var locHtml = d.location_name ? '<small class="location-info shf-text-2xs">' + d
                            .location_name + '</small>' : '';
                        var ownerPlain = (d.owner_info || '—').replace(/<br\s*\/?>/gi, ' · ').replace(
                            /<[^>]+>/g, '');
                        html += '<div class="shf-card mb-2 p-3">' +
                            '<div class="d-flex justify-content-between align-items-start mb-2">' +
                            '<div style="min-width:0;flex:1;"><strong>' + d.customer_name +
                            '</strong><br><small class="text-muted">' + d.loan_number +
                            '</small></div>' +
                            '<div class="ms-2 flex-shrink-0">' + d.status_label + '</div></div>' +
                            '<div class="d-flex justify-content-between align-items-center mb-1">' +
                            '<span>' + d.formatted_amount + '</span>' +
                            '<small class="text-muted">' + d.bank_name + '</small></div>' +
                            (locHtml ? '<div class="mb-1">' + locHtml + '</div>' : '') +
                            '<div class="mb-1"><small class="text-muted">Owner: ' + ownerPlain +
                            '</small></div>' +
                            '<div class="d-flex flex-wrap gap-1 mb-2"><small class="text-muted me-1">Stage:</small>' +
                            d.current_stage_name + '</div>' +
                            '<div>' + d.actions_html + '</div></div>';
                    }
                    $('#loansMobileCards').html(html ||
                        '<p class="text-muted text-center py-4">No matching loans</p>');
                }
            });

            // Custom per-page selector
            $('#loanPageLength').on('change', function() {
                table.page.len(parseInt(this.value)).draw();
            });

            // Status filter — auto-reload on change
            $('#filterStatus').on('change', function() {
                table.ajax.reload();
            });

            // Docket filter — show/hide custom date, auto-reload
            $('#filterDocket').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#docketCustomDate').show();
                } else {
                    $('#docketCustomDate').hide();
                    $('#filterDocketDate').val('');
                    table.ajax.reload();
                }
            });
            $('#filterDocketDate').on('change', function() {
                table.ajax.reload();
            });

            // Filter buttons (for other filters)
            $('#btnLoanFilter').on('click', function() {
                table.ajax.reload();
            });
            $('#btnLoanClear').on('click', function() {
                $('#filterStatus, #filterType, #filterBank, #filterBranch, #filterStage, #filterRole, #filterDocket')
                    .val('');
                $('#filterDocketDate').val('').datepicker('update', '');
                $('#docketCustomDate').hide();
                $('#filterDateFrom, #filterDateTo').val('').datepicker('update', '');
                table.ajax.reload();
            });

            // Datepicker
            $('.shf-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                clearBtn: true
            });

            // Filter count badge
            function updateLoanFilterCount() {
                var count = 0;
                $('#filterStatus, #filterType, #filterBank, #filterBranch, #filterStage, #filterRole, #filterDocket')
                    .each(function() {
                        if ($(this).val()) count++;
                    });
                if ($('#filterDateFrom').val()) count++;
                if ($('#filterDateTo').val()) count++;
                var $badge = $('#loansFilterCount');
                if (count > 0) {
                    $badge.text(count).removeClass('shf-collapse-hidden');
                } else {
                    $badge.addClass('shf-collapse-hidden');
                }
            }
            $(document).on('change',
                '#filterStatus, #filterType, #filterBank, #filterBranch, #filterStage, #filterRole, #filterDocket, #filterDateFrom, #filterDateTo',
                updateLoanFilterCount);
            $('#btnLoanClear').on('click', function() {
                setTimeout(updateLoanFilterCount, 50);
            });
        });
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH F:\G Drive\Projects\quotationshf\resources\views/loans/index.blade.php ENDPATH**/ ?>