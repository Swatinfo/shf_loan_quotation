        <script>
            $(function() {
                var csrfToken = $('meta[name="csrf-token"]').attr('content');

                // ============================================================
                //  TAB SWITCHING — unified for legacy (.shf-tab), newtheme
                //  mobile (.tabs .tab[data-tab]) and newtheme desktop rail
                //  (.loan-settings-nt-rail-item[data-tab]). Persists the
                //  selected tab across refresh via URL hash + localStorage.
                // ============================================================
                var LOAN_SETTINGS_TAB_STORAGE = 'shf_loan_settings_active_tab';

                function activateTab(tab) {
                    if (!tab || !$('#tab-' + tab).length) return false;
                    $('.shf-tab, .tabs .tab[data-tab], .loan-settings-nt-rail-item[data-tab]').removeClass('active');
                    $(
                        '.shf-tab[data-tab="' + tab + '"], ' +
                        '.tabs .tab[data-tab="' + tab + '"], ' +
                        '.loan-settings-nt-rail-item[data-tab="' + tab + '"]'
                    ).addClass('active');
                    $('.settings-tab-pane').hide();
                    $('#tab-' + tab).show();
                    try { localStorage.setItem(LOAN_SETTINGS_TAB_STORAGE, tab); } catch (e) { /* storage disabled */ }
                    history.replaceState(null, '', '#' + tab);
                    document.dispatchEvent(new CustomEvent('loan-settings:tab-activated', { detail: { tab: tab } }));
                    return true;
                }

                $(document).on('click', '.shf-tab, .tabs .tab[data-tab], .loan-settings-nt-rail-item[data-tab]', function(e) {
                    e.preventDefault();
                    activateTab($(this).data('tab'));
                });

                // Restore order:
                //   (1) validation-error hint (banks / branches),
                //   (2) URL hash,
                //   (3) localStorage,
                //   (4) first .active tab,
                //   (5) first tab.
                var restored = false;
                @if ($errors->any() && old('manager_id') !== null)
                    restored = activateTab('branches');
                @elseif (($errors->any() || session('error')) && old('bank_id') !== null)
                    {{-- product form posts bank_id; land back on the Products tab --}}
                    restored = activateTab('products');
                @elseif ($errors->any())
                    restored = activateTab('banks');
                @endif
                if (!restored) {
                    var hash = (window.location.hash || '').replace('#', '');
                    if (hash) { restored = activateTab(hash); }
                }
                if (!restored) {
                    try {
                        var stored = localStorage.getItem(LOAN_SETTINGS_TAB_STORAGE);
                        if (stored) { restored = activateTab(stored); }
                    } catch (e) { /* storage disabled */ }
                }
                if (!restored) {
                    var $firstActive = $('.shf-tab.active, .tabs .tab.active').first();
                    if ($firstActive.length) {
                        activateTab($firstActive.data('tab'));
                    } else {
                        var $firstTab = $('.shf-tab, .tabs .tab[data-tab]').first();
                        if ($firstTab.length) { activateTab($firstTab.data('tab')); }
                    }
                }

                // Cancel button — reset form + close collapse
                $(document).on('click', '.shf-form-cancel', function() {
                    var collapseId = $(this).data('collapse');
                    var formId = $(this).data('reset');

                    // Reset form fields
                    if (formId) {
                        var $form = $('#' + formId);
                        $form[0].reset();
                        $form.find('input[type="hidden"]').not('input[name="_token"]').val('');
                        $form.find('.is-invalid').removeClass('is-invalid');
                    }

                    // Reset titles back to Add
                    if (formId === 'locationForm') {
                        $('#locationFormTitle').text('+ Add Location');
                        $('#locationSubmitText').text('Add');
                    } else if (formId === 'bankForm') {
                        resetBankForm();
                    } else if (formId === 'branchForm') {
                        $('#branchFormTitle').text('+ Add Branch');
                    } else if (formId === 'productForm') {
                        resetProductForm();
                    }

                    // Close collapse
                    if (collapseId) {
                        var $collapse = $(collapseId);
                        if ($collapse.hasClass('show')) {
                            bootstrap.Collapse.getOrCreateInstance($collapse[0]).hide();
                        }
                    }
                });

                // ============================================================
                //  FORM VALIDATION
                // ============================================================
                $('#locationForm').on('submit', function(e) {
                    var rules = { name: { required: true, maxlength: 255, label: 'Name' }, type: { required: true, label: 'Type' } };
                    if ($('#locationTypeInput').val() === 'city') {
                        rules['parent_id'] = { required: true, label: 'State' };
                    }
                    if (!SHF.validateForm($(this), rules)) { e.preventDefault(); }
                });
                $('#bankForm').on('submit', function(e) {
                    if (!SHF.validateForm($(this), { name: { required: true, maxlength: 255, label: 'Bank Name' } })) { e.preventDefault(); }
                });
                $('#branchForm').on('submit', function(e) {
                    if (!SHF.validateForm($(this), {
                        name: { required: true, maxlength: 255, label: 'Branch Name' },
                        location_id: { required: true, label: 'Location' }
                    })) { e.preventDefault(); }
                });
                $('#productForm').on('submit', function(e) {
                    if (!SHF.validateForm($(this), {
                        name: { required: true, maxlength: 255, label: 'Product Name' },
                        bank_id: { required: true, label: 'Bank' }
                    })) { e.preventDefault(); return; }

                    // Payout slab checks: complete rows, high > low, % ≤ 100, no overlaps.
                    var slabsOk = true;
                    var $slabErr = productSlabErrorEl().hide();
                    var ranges = [];
                    $('#productSlabList .product-slab-row').each(function() {
                        var low = parseFloat($(this).find('.slab-low').val());
                        var high = parseFloat($(this).find('.slab-high').val());
                        var val = parseFloat($(this).find('.slab-value').val());
                        var type = $(this).find('.slab-type').val();
                        if (isNaN(low) || isNaN(high) || isNaN(val)) {
                            slabsOk = false; $slabErr.text('Please fill all fields in every payout slab row.');
                            return false;
                        }
                        if (high <= low) {
                            slabsOk = false; $slabErr.text('Each slab\'s high range must be greater than its low range.');
                            return false;
                        }
                        if (type === 'percent' && val > 100) {
                            slabsOk = false; $slabErr.text('Percentage payout cannot exceed 100%.');
                            return false;
                        }
                        ranges.push([low, high]);
                    });
                    if (slabsOk) {
                        ranges.sort(function(a, b) { return a[0] - b[0]; });
                        for (var i = 1; i < ranges.length; i++) {
                            if (ranges[i][0] <= ranges[i - 1][1]) {
                                slabsOk = false; $slabErr.text('Payout slab ranges overlap — adjust the low/high values.');
                                break;
                            }
                        }
                    }
                    if (!slabsOk) { e.preventDefault(); $slabErr.show(); }
                });

                // ============================================================
                //  PRODUCT PAYOUT SLABS — repeater + edit-product populate
                // ============================================================
                var productSlabIndex = 0;

                // The statically-rendered #productSlabError div is stripped from the
                // DOM at page load (global cleanup sweeps it), so (re)create it on
                // demand right after the slab list.
                function productSlabErrorEl() {
                    var $e = $('#productSlabError');
                    if (!$e.length) {
                        $e = $('<div id="productSlabError" class="shf-text-error shf-text-sm mt-1" style="display:none;"></div>')
                            .insertAfter('#productSlabList');
                    }
                    return $e;
                }

                function productSlabRowHtml(idx, data) {
                    data = data || {};
                    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
                    return '<div class="row g-2 mb-2 product-slab-row">' +
                        '<div class="col-md-3"><label class="shf-form-label d-block mb-1">Low Range (₹)</label>' +
                            '<input type="number" name="slabs[' + idx + '][low_amount]" class="shf-input slab-low" min="0" step="1" value="' + esc(data.low_amount) + '"></div>' +
                        '<div class="col-md-3"><label class="shf-form-label d-block mb-1">High Range (₹)</label>' +
                            '<input type="number" name="slabs[' + idx + '][high_amount]" class="shf-input slab-high" min="1" step="1" value="' + esc(data.high_amount) + '"></div>' +
                        '<div class="col-md-2"><label class="shf-form-label d-block mb-1">Payout Type</label>' +
                            '<select name="slabs[' + idx + '][payout_type]" class="shf-input slab-type">' +
                                '<option value="amount"' + (data.payout_type !== 'percent' ? ' selected' : '') + '>Fixed ₹</option>' +
                                '<option value="percent"' + (data.payout_type === 'percent' ? ' selected' : '') + '>%</option>' +
                            '</select></div>' +
                        '<div class="col-md-2"><label class="shf-form-label d-block mb-1">Payout</label>' +
                            '<input type="number" name="slabs[' + idx + '][payout_value]" class="shf-input slab-value" min="0" step="0.01" value="' + esc(data.payout_value) + '"></div>' +
                        '<div class="col-md-2 d-flex align-items-end"><button type="button" class="btn-accent-sm shf-btn-danger product-slab-remove">' +
                            '<svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                            ' Remove</button></div>' +
                    '</div>';
                }

                function addProductSlabRow(data) {
                    $('#productSlabList').append(productSlabRowHtml(productSlabIndex++, data));
                    // Prefilled rows (edit-product) should show their words hints immediately.
                    $('#productSlabList .product-slab-row').last().find('.slab-low, .slab-high')
                        .filter(function() { return $(this).val() !== ''; })
                        .trigger('input');
                }

                $(document).on('click', '#productSlabAdd', function() { addProductSlabRow(); });
                $(document).on('click', '.product-slab-remove', function() {
                    $(this).closest('.product-slab-row').remove();
                });

                // Live amount-in-words hint under Max Payout + slab Low/High inputs.
                // Hint divs are created on demand (statically-rendered helper divs get
                // stripped on this page — see productSlabErrorEl above). Guarded: if a
                // stale-cached shf-newtheme.js lacks the words helpers, hints are
                // silently skipped instead of breaking the rest of this script.
                $(document).on('input', '#productMaxPayoutInput, .slab-low, .slab-high', function() {
                    if (!window.SHF || !SHF.bilingualAmountWords) { return; }
                    var $input = $(this);
                    var $hint = $input.next('.shf-amount-words-hint');
                    if (!$hint.length) {
                        $hint = $('<div class="shf-amount-words-hint shf-text-2xs shf-text-gray mt-1"></div>').insertAfter($input);
                    }
                    var n = Math.floor(parseFloat($input.val()));
                    $hint.text(isFinite(n) && n > 0 ? SHF.bilingualAmountWords(n) : '');
                });

                window.resetProductForm = function() {
                    $('#productEditId').val('');
                    $('#productBankInput').val('');
                    $('#productNameInput').val('');
                    $('#productCodeInput').val('');
                    $('#productMaxPayoutInput').val('').trigger('input');
                    $('#productPfInput').prop('checked', false);
                    $('#productSlabList').empty();
                    productSlabIndex = 0;
                    productSlabErrorEl().hide();
                    $('#productFormTitle').text('+ Add Product');
                    $('#productSubmitText').text('Add Product');
                };

                // Edit product — populate the Add form (same pattern as banks/branches)
                $(document).on('click', '.shf-edit-product', function() {
                    resetProductForm();
                    $('#productEditId').val($(this).data('id'));
                    $('#productBankInput').val($(this).data('bank-id'));
                    $('#productNameInput').val($(this).data('name'));
                    $('#productCodeInput').val($(this).data('code'));
                    $('#productPfInput').prop('checked', String($(this).data('pf-based')) === '1');
                    $('#productMaxPayoutInput').val($(this).data('max-payout')).trigger('input');
                    ($(this).data('slabs') || []).forEach(function(s) { addProductSlabRow(s); });
                    $('#productFormTitle').text('Edit Product');
                    $('#productSubmitText').text('Update Product');
                    var $collapse = $('#productFormCollapse');
                    if (!$collapse.hasClass('show')) {
                        new bootstrap.Collapse($collapse[0], { toggle: true });
                    }
                    $collapse[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    $('#productNameInput').focus();
                });

                // Location form — type toggle
                $('#locationTypeInput').on('change', function() {
                    $('#locationParentWrapper').toggle($(this).val() === 'city');
                });
                $(document).on('click', '.shf-edit-location', function() {
                    $('#locationEditId').val($(this).data('id'));
                    $('#locationNameInput').val($(this).data('name'));
                    $('#locationCodeInput').val($(this).data('code'));
                    $('#locationTypeInput').val($(this).data('type')).trigger('change');
                    $('#locationParentInput').val($(this).data('parent-id'));
                    $('#locationFormTitle').text('Edit Location');
                    $('#locationSubmitText').text('Update');
                    var $collapse = $('#locationFormCollapse');
                    if (!$collapse.hasClass('show')) {
                        new bootstrap.Collapse($collapse[0], {
                            toggle: true
                        });
                    }
                    $collapse[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                });

                // Edit bank — populate form with locations
                $(document).on('click', '.shf-edit-bank', function() {
                    $('#bankEditId').val($(this).data('id'));
                    $('#bankNameInput').val($(this).data('name'));
                    $('#bankCodeInput').val($(this).data('code'));

                    // Reset location checkboxes
                    $('.bank-loc-check').prop('checked', false);

                    // Check assigned locations
                    var locationIds = $(this).data('location-ids') || [];
                    locationIds.forEach(function(id) {
                        $('.bank-loc-check[value="' + id + '"]').prop('checked', true);
                    });

                    $('#bankLocationSection').show();
                    $('#bankFormTitle').text('Edit Bank');
                    $('#bankSubmitText').text('Update Bank');
                    var $collapse = $('#bankFormCollapse');
                    if (!$collapse.hasClass('show')) {
                        new bootstrap.Collapse($collapse[0], {
                            toggle: true
                        });
                    }
                    $collapse[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    $('#bankNameInput').focus();
                });

                window.resetBankForm = function() {
                    $('#bankEditId').val('');
                    $('#bankNameInput').val('');
                    $('#bankCodeInput').val('');
                    $('.bank-loc-check').prop('checked', false);
                    $('#bankLocationSection').hide();
                    $('#bankFormTitle').text('+ Add Bank');
                    $('#bankSubmitText').text('Add Bank');
                };

                // Toggle inline product stage config
                $(document).on('click', '.shf-toggle-product-locations', function() {
                    var $panel = $($(this).data('target'));
                    $panel.is(':visible') ? $panel.slideUp(200) : $panel.slideDown(200);
                });

                // Close product location panel
                $(document).on('click', '.shf-close-product-locs', function() {
                    $($(this).data('target')).slideUp(200);
                });

                $(document).on('click', '.shf-toggle-stages', function() {
                    var target = $(this).data('target');
                    var $panel = $(target);
                    if ($panel.is(':visible')) {
                        $panel.slideUp(200);
                    } else {
                        // Close any other open panels first
                        $('.shf-product-stages-panel:visible').slideUp(200);
                        $panel.slideDown(200);
                    }
                });

                // Edit branch — populate form
                $(document).on('click', '.shf-edit-branch', function() {
                    $('#branchEditId').val($(this).data('id'));
                    $('#branchNameInput').val($(this).data('name'));
                    $('#branchCodeInput').val($(this).data('code'));
                    $('#branchCityInput').val($(this).data('city'));
                    $('#branchPhoneInput').val($(this).data('phone'));
                    $('#branchManagerInput').val($(this).data('manager-id') || '');
                    $('#branchLocationInput').val($(this).data('location-id') || '');
                    $('#branchFormTitle').text('Edit Branch');
                    var $collapse = $('#branchFormCollapse');
                    if (!$collapse.hasClass('show')) {
                        new bootstrap.Collapse($collapse[0], {
                            toggle: true
                        });
                    }
                    $collapse[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    $('#branchNameInput').focus();
                });

                // Delete bank/branch
                $(document).on('click', '.shf-delete-item', function() {
                    var url = $(this).data('url');
                    Swal.fire({
                        title: 'Delete?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Delete'
                    }).then(function(r) {
                        if (r.isConfirmed) {
                            $.ajax({
                                    url: url,
                                    method: 'DELETE',
                                    data: {
                                        _token: csrfToken
                                    }
                                })
                                .done(function() {
                                    location.reload();
                                })
                                .fail(function(xhr) {
                                    Swal.fire('Error', xhr.responseJSON?.error || 'Cannot delete',
                                        'error');
                                });
                        }
                    });
                });

                // Stage Master form — no special validation needed (dropdowns always have a value)
            });
        </script>
