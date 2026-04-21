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
                    })) { e.preventDefault(); }
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
