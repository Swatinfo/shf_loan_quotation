    <script>
        $(function() {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            // Show/hide custom docket date based on docket_days_offset selection
            function toggleCustomDocketDate($select) {
                var $form = $select.closest('form');
                var $customField = $form.find('[name="custom_docket_date"]').closest('.col-sm-6');
                if ($select.val() === '0') {
                    $customField.show();
                } else {
                    $customField.hide();
                    $form.find('[name="custom_docket_date"]').val('');
                }
            }
            $(document).on('change', '[name="docket_days_offset"]', function() {
                toggleCustomDocketDate($(this));
            });
            // Init on page load for any existing forms
            $('[name="docket_days_offset"]').each(function() {
                toggleCustomDocketDate($(this));
            });

            // Init Bootstrap Datepicker — future-allowed fields
            $('.shf-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true
            });
            // Past-only datepicker (no future dates) for most loan stage date fields
            $('.shf-datepicker-past').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                endDate: '+0d'
            });
            // Custom datepicker with data-min-date / data-max-date attributes
            $('.shf-datepicker-custom').each(function() {
                var opts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true };
                var minDate = $(this).data('min-date');
                var maxDate = $(this).data('max-date');
                if (minDate) opts.startDate = minDate;
                if (maxDate) opts.endDate = maxDate;
                $(this).datepicker(opts);
            });

            // Reusable: validate required fields in a stage notes form, returns true if valid
            // Show inline field errors below form controls
            function showInlineErrors($form, fieldErrors) {
                $form.find('.shf-field-error').remove();
                $form.find('.is-invalid').removeClass('is-invalid');
                var firstErrorField = null;
                Object.keys(fieldErrors).forEach(function(fieldName) {
                    var $input = $form.find('[name="' + fieldName + '"]');
                    if ($input.length) {
                        $input.addClass('is-invalid');
                        var $visible = $input.closest('.shf-amount-wrap').find('.shf-amount-input');
                        if ($visible.length) $visible.addClass('is-invalid');
                        var $parent = $input.closest('.col-sm-6, .col-sm-12, [class^="col-sm-"]');
                        if (!$parent.length) $parent = $input.parent();
                        $parent.append('<div class="shf-field-error text-danger shf-text-xs mt-1">' +
                            fieldErrors[fieldName] + '</div>');
                        if (!firstErrorField) firstErrorField = $visible.length ? $visible : $input;
                    }
                });
                if (firstErrorField) {
                    firstErrorField[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstErrorField.focus();
                }
            }

            function validateStageForm($form) {
                $form.find('.shf-field-error').remove();
                $form.find('.is-invalid').removeClass('is-invalid');
                // Re-run repo vs interest rate check to surface any existing error
                var $repoInput = $form.find('input[name="repo_rate"]');
                if ($repoInput.length) recalcBankRate($repoInput[0]);
                var hasErrors = false,
                    firstErrorField = null;
                // Check if recalcBankRate already flagged an error
                if ($repoInput.hasClass('is-invalid')) {
                    hasErrors = true;
                    firstErrorField = $repoInput;
                }
                $form.find('.col-sm-6, .col-sm-12, [class*="col-sm-"]').each(function() {
                    var $col = $(this);
                    var $label = $col.find('.form-label');
                    if ($label.find('.text-danger').length === 0) return;
                    var $input = $col.find('input[name], select[name], textarea[name]');
                    if (!$input.length) $input = $col.find('.shf-amount-raw');
                    if ($input.length && $input.prop('readonly')) return; // skip readonly (auto-calculated)
                    if ($input.length && (!$input.val() || !$input.val().trim())) {
                        $input.addClass('is-invalid');
                        var $visible = $col.find('.shf-amount-input');
                        if ($visible.length) $visible.addClass('is-invalid');
                        var labelText = $label.text().replace('*', '').trim();
                        $col.append('<div class="shf-field-error text-danger shf-text-xs mt-1">' +
                            labelText + ' is required</div>');
                        if (!firstErrorField) firstErrorField = $visible.length ? $visible : $input;
                        hasErrors = true;
                    }
                });
                if (hasErrors) {
                    firstErrorField[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstErrorField.focus();
                }
                return !hasErrors;
            }

            // Stage status change — save form first (with inline validation), then update status
            $(document).on('click', '.shf-stage-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id'),
                    stage = $btn.data('stage'),
                    action = $btn.data('action');
                var $form = $btn.closest('.card-body').find('.shf-stage-notes-form');

                function doStatusUpdate() {
                    $btn.prop('disabled', true);
                    $.post('/loans/' + loanId + '/stages/' + stage + '/status', {
                            _token: csrfToken,
                            status: action
                        })
                        .done(function(r) {
                            if (r.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Stage completed!',
                                    text: r.message || 'Stage updated successfully.',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    location.reload();
                                });
                            }
                        })
                        .fail(function(xhr) {
                            $btn.prop('disabled', false);
                            var fieldErrors = xhr.responseJSON?.field_errors || {};
                            if ($form.length && Object.keys(fieldErrors).length) {
                                showInlineErrors($form, fieldErrors);
                            } else {
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed', 'error');
                            }
                        });
                }

                if ($form.length && action === 'completed') {
                    if (!validateStageForm($form)) return;
                    var formData = {};
                    $form.serializeArray().forEach(function(item) {
                        formData[item.name] = item.value;
                    });
                    $btn.prop('disabled', true);
                    $.post($form.data('notes-url'), {
                            _token: csrfToken,
                            notes_data: formData
                        })
                        .done(function(r) {
                            if (r.stage_advanced) {
                                Swal.fire({
                                        icon: 'success',
                                        title: 'Stage completed!',
                                        text: 'Moving to next stage...',
                                        timer: 1500,
                                        showConfirmButton: false
                                    })
                                    .then(function() {
                                        location.reload();
                                    });
                            } else {
                                doStatusUpdate();
                            }
                        })
                        .fail(function(xhr) {
                            $btn.prop('disabled', false);
                            var fieldErrors = xhr.responseJSON?.field_errors || {};
                            if (Object.keys(fieldErrors).length) {
                                showInlineErrors($form, fieldErrors);
                            } else {
                                Swal.fire('Error', xhr.responseJSON?.error || 'Save failed', 'error');
                            }
                        });
                } else {
                    doStatusUpdate();
                }
            });

            // Assign
            $(document).on('change', '.shf-stage-assign', function() {
                var userId = $(this).val(),
                    loanId = $(this).data('loan-id'),
                    stage = $(this).data('stage');
                if (!userId) return;
                $.post('/loans/' + loanId + '/stages/' + stage + '/assign', {
                        _token: csrfToken,
                        user_id: userId
                    })
                    .done(function(r) {
                        if (r.success) Swal.fire({
                            icon: 'success',
                            title: 'Assigned to ' + r.assigned_to,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    });
            });

            // Reject
            $(document).on('click', '.shf-reject-btn', function() {
                var loanId = $(this).data('loan-id'),
                    stage = $(this).data('stage');
                Swal.fire({
                    title: 'Reject Loan?',
                    html: 'This will reject the <strong>entire loan</strong>. This cannot be undone.',
                    icon: 'warning',
                    input: 'textarea',
                    inputLabel: 'Rejection reason (required)',
                    inputPlaceholder: 'Why is this loan being rejected?',
                    inputValidator: function(v) {
                        if (!v) return 'Reason is required';
                    },
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Reject Loan'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.post('/loans/' + loanId + '/stages/' + stage + '/reject', {
                                _token: csrfToken,
                                reason: result.value
                            })
                            .done(function() {
                                location.reload();
                            });
                    }
                });
            });

            // Stage notes form — inline errors + focus first error field
            $(document).on('submit', '.shf-stage-notes-form', function(e) {
                e.preventDefault();
                var $form = $(this),
                    url = $form.data('notes-url');
                if (!validateStageForm($form)) return;
                var formData = {};
                $form.serializeArray().forEach(function(item) {
                    formData[item.name] = item.value;
                });
                $.post(url, {
                        _token: csrfToken,
                        notes_data: formData
                    })
                    .done(function(r) {
                        if (r.success && r.stage_advanced) {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'Stage completed!',
                                    text: 'Moving to next stage...',
                                    timer: 1500,
                                    showConfirmButton: false
                                })
                                .then(function() {
                                    location.reload();
                                });
                        } else if (r.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Details saved',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    })
                    .fail(function(xhr) {
                        var fieldErrors = xhr.responseJSON?.field_errors || {};
                        if (Object.keys(fieldErrors).length) {
                            showInlineErrors($form, fieldErrors);
                        } else {
                            Swal.fire('Error', xhr.responseJSON?.error || 'Failed', 'error');
                        }
                    });
            });

            // Quick role-based transfer
            // Stage transfer button (select user from dropdown, then click Transfer)
            $(document).on('click', '.shf-stage-transfer-btn', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var stage = $btn.data('stage');
                var $select = $btn.siblings('.shf-stage-transfer-select');
                var userId = $select.val();
                var userName = $select.find('option:selected').text();

                if (!userId) {
                    $select.addClass('is-invalid');
                    $select.next('.shf-field-error').remove();
                    $select.after('<div class="shf-field-error text-danger shf-text-xs mt-1">Please select a user to transfer to</div>');
                    return;
                }
                $select.removeClass('is-invalid').next('.shf-field-error').remove();

                Swal.fire({
                    title: 'Transfer to ' + userName.trim() + '?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Transfer'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $btn.prop('disabled', true);
                        $.post('/loans/' + loanId + '/stages/' + stage + '/transfer', {
                                _token: csrfToken,
                                user_id: userId,
                                reason: 'Manual transfer'
                            })
                            .done(function(r) {
                                if (r.success) {
                                    Swal.fire({
                                            icon: 'success',
                                            title: 'Transferred to ' + r.assigned_to,
                                            timer: 1500,
                                            showConfirmButton: false
                                        })
                                        .then(function() {
                                            location.reload();
                                        });
                                }
                            })
                            .fail(function(xhr) {
                                $btn.prop('disabled', false);
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                    'error');
                            });
                    }
                });
            });

            // E-Sign phase actions
            $(document).on('click', '.shf-esign-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var action = $btn.data('action');
                var transferTo = $btn.closest('.card-body').find('.shf-transfer-user[data-stage="esign"]')
                    .val() || '';
                var confirmMsg = {
                    send_for_esign: 'Send for E-Sign?',
                    esign_generated: 'Confirm E-Sign generated?',
                    esign_customer_done: 'Confirm customer E-Sign done?',
                    esign_complete: 'Mark E-Sign as complete?'
                };
                Swal.fire({
                    title: 'Confirm',
                    text: confirmMsg[action] || 'Proceed with this action?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $btn.prop('disabled', true);
                        $.post('/loans/' + loanId + '/stages/esign/action', {
                                _token: csrfToken,
                                action: action,
                                transfer_to: transferTo || null
                            })
                            .done(function(r) {
                                if (r.success) {
                                    Swal.fire({
                                            icon: 'success',
                                            title: r.message,
                                            timer: 1500,
                                            showConfirmButton: false
                                        })
                                        .then(function() {
                                            location.reload();
                                        });
                                }
                            })
                            .fail(function(xhr) {
                                $btn.prop('disabled', false);
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                    'error');
                            });
                    }
                });
            });

            // Docket phase actions
            // Technical Valuation: send to office employee
            $(document).on('click', '.shf-tv-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var transferTo = $btn.closest('.card-body, .card').find(
                    '.shf-transfer-user[data-stage="technical_valuation"]').val() || '';
                Swal.fire({
                    title: 'Confirm',
                    text: 'Send for technical valuation?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Send',
                    confirmButtonColor: '#f15a29',
                    cancelButtonColor: '#6c757d'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $btn.prop('disabled', true);
                        $.post('/loans/' + loanId + '/stages/technical_valuation/action', {
                                _token: csrfToken,
                                action: 'send_to_office',
                                transfer_to: transferTo || null
                            })
                            .done(function(r) {
                                if (r.success) {
                                    Swal.fire({
                                            icon: 'success',
                                            title: r.message,
                                            timer: 1500,
                                            showConfirmButton: false
                                        })
                                        .then(function() {
                                            location.reload();
                                        });
                                }
                            })
                            .fail(function(xhr) {
                                $btn.prop('disabled', false);
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                    'error');
                            });
                    }
                });
            });

            $(document).on('click', '.shf-docket-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                // Save form data first, then send action
                var $form = $btn.closest('.card-body').find('.shf-stage-notes-form');
                if ($form.length) {
                    if (!validateStageForm($form)) return;
                    Swal.fire({
                        title: 'Confirm',
                        text: 'Send docket for processing?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Send'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            var formData = {};
                            $form.serializeArray().forEach(function(item) {
                                formData[item.name] = item.value;
                            });
                            $btn.prop('disabled', true);
                            $.post($form.data('notes-url'), {
                                    _token: csrfToken,
                                    notes_data: formData
                                })
                                .done(function() {
                                    var docketTransferTo = $btn.closest('.card-body').find(
                                            '.shf-transfer-user[data-stage="docket"]').val() ||
                                        '';
                                    $.post('/loans/' + loanId + '/stages/docket/action', {
                                            _token: csrfToken,
                                            action: 'send_to_office',
                                            transfer_to: docketTransferTo || null
                                        })
                                        .done(function(r) {
                                            if (r.success) {
                                                Swal.fire({
                                                        icon: 'success',
                                                        title: r.message,
                                                        timer: 1500,
                                                        showConfirmButton: false
                                                    })
                                                    .then(function() {
                                                        location.reload();
                                                    });
                                            }
                                        })
                                        .fail(function(xhr) {
                                            $btn.prop('disabled', false);
                                            Swal.fire('Error', xhr.responseJSON?.error ||
                                                'Failed', 'error');
                                        });
                                })
                                .fail(function(xhr) {
                                    $btn.prop('disabled', false);
                                    var fieldErrors = xhr.responseJSON?.field_errors || {};
                                    if (Object.keys(fieldErrors).length) {
                                        showInlineErrors($form, fieldErrors);
                                    } else {
                                        Swal.fire('Error', xhr.responseJSON?.error ||
                                            'Save failed', 'error');
                                    }
                                });
                        }
                    });
                }
            });

            // Rate & PF phase actions
            $(document).on('click', '.shf-rate-pf-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var action = $btn.data('action');
                var ratePfTransferTo = $btn.closest('.card-body').find(
                    '.shf-transfer-user[data-stage="rate_pf"]').val() || '';
                var confirmMsg = action === 'send_to_bank' ? 'Send rate & PF details for review?' :
                    action === 'complete' ? 'Complete Rate & PF stage?' :
                    'Return this stage to the task owner?';

                var $form = $btn.closest('.card-body').find('.shf-stage-notes-form');
                if ($form.length && !validateStageForm($form)) return;

                function doAction() {
                    $btn.prop('disabled', true);
                    $.post('/loans/' + loanId + '/stages/rate_pf/action', {
                            _token: csrfToken,
                            action: action,
                            transfer_to: ratePfTransferTo || null
                        })
                        .done(function(r) {
                            if (r.success) {
                                Swal.fire({
                                        icon: 'success',
                                        title: r.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    })
                                    .then(function() {
                                        location.reload();
                                    });
                            }
                        })
                        .fail(function(xhr) {
                            $btn.prop('disabled', false);
                            Swal.fire('Error', xhr.responseJSON?.error || 'Failed', 'error');
                        });
                }

                function saveAndAction() {
                    if ($form.length) {
                        var formData = {};
                        $form.serializeArray().forEach(function(item) {
                            formData[item.name] = item.value;
                        });
                        $btn.prop('disabled', true);
                        $.post($form.data('notes-url'), {
                                _token: csrfToken,
                                notes_data: formData
                            })
                            .done(function() {
                                doAction();
                            })
                            .fail(function(xhr) {
                                $btn.prop('disabled', false);
                                var fieldErrors = xhr.responseJSON?.field_errors || {};
                                if (Object.keys(fieldErrors).length) {
                                    showInlineErrors($form, fieldErrors);
                                } else {
                                    Swal.fire('Error', xhr.responseJSON?.error || 'Save failed',
                                        'error');
                                }
                            });
                    } else {
                        doAction();
                    }
                }

                Swal.fire({
                    title: 'Confirm',
                    text: confirmMsg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                }).then(function(result) {
                    if (result.isConfirmed) saveAndAction();
                });
            });

            // Legal verification phase actions
            $(document).on('click', '.shf-legal-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var action = $btn.data('action');
                var advisorName = $('#legalAdvisorName').val();

                if (!advisorName || !advisorName.trim()) {
                    var $input = $('#legalAdvisorName');
                    $input.addClass('is-invalid');
                    $input.next('.shf-field-error').remove();
                    $input.after('<div class="shf-field-error text-danger shf-text-xs mt-1">Legal Advisor name is required</div>');
                    $input[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    $input.focus();
                    return;
                }

                var legalTransferTo = $btn.closest('.card-body').find(
                    '.shf-transfer-user[data-stage="legal_verification"]').val() || '';
                var postData = {
                    _token: csrfToken,
                    action: action,
                    suggested_legal_advisor: advisorName.trim(),
                    transfer_to: legalTransferTo || null
                };
                var confirmMsg = action === 'send_to_bank' ? 'Send for legal verification?' :
                    'Initiate legal verification?';

                Swal.fire({
                    title: 'Confirm',
                    text: confirmMsg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $btn.prop('disabled', true);
                        $.post('/loans/' + loanId + '/stages/legal_verification/action', postData)
                            .done(function(r) {
                                if (r.success) {
                                    Swal.fire({
                                            icon: 'success',
                                            title: r.message,
                                            timer: 1500,
                                            showConfirmButton: false
                                        })
                                        .then(function() {
                                            location.reload();
                                        });
                                }
                            })
                            .fail(function(xhr) {
                                $btn.prop('disabled', false);
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                    'error');
                            });
                    }
                });
            });

            // Sanction stage phase actions (send for sanction / sanction generated)
            $(document).on('click', '.shf-sanction-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var action = $btn.data('action');
                var confirmMsg = action === 'send_for_sanction' ?
                    'Send this loan for sanction letter generation?' :
                    'Confirm that the sanction letter has been generated?';
                Swal.fire({
                    title: 'Confirm',
                    text: confirmMsg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        var sanctionTransferTo = $btn.closest('.card-body').find(
                            '.shf-transfer-user[data-stage="sanction"]').val() || '';
                        $btn.prop('disabled', true);
                        $.post('/loans/' + loanId + '/stages/sanction/action', {
                                _token: csrfToken,
                                action: action,
                                transfer_to: sanctionTransferTo || null
                            })
                            .done(function(r) {
                                if (r.success) {
                                    Swal.fire({
                                            icon: 'success',
                                            title: r.message,
                                            timer: 1500,
                                            showConfirmButton: false
                                        })
                                        .then(function() {
                                            location.reload();
                                        });
                                }
                            })
                            .fail(function(xhr) {
                                $btn.prop('disabled', false);
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                    'error');
                            });
                    }
                });
            });

            // Bank rate auto-calculation: interest_rate - repo_rate = bank_rate
            function recalcBankRate(el) {
                var $card = $(el).closest('.card');
                var interestRate = parseFloat($card.find('input[name="interest_rate"]').val()) || 0;
                var $repoInput = $card.find('input[name="repo_rate"]');
                var repoRate = parseFloat($repoInput.val()) || 0;
                var $repoCol = $repoInput.closest('.col-sm-6, .col-sm-12, [class*="col-sm-"]');
                $repoCol.find('.shf-field-error').remove();
                $repoInput.removeClass('is-invalid');
                if (repoRate > interestRate && interestRate > 0) {
                    $repoInput.addClass('is-invalid');
                    $repoCol.append(
                        '<div class="shf-field-error text-danger shf-text-xs mt-1">Repo Rate cannot exceed Interest Rate (' +
                        interestRate + '%)</div>');
                    $card.find('input[name="bank_rate"]').val('');
                    return;
                }
                var bankRate = Math.max(0, (interestRate - repoRate)).toFixed(2);
                $card.find('input[name="bank_rate"]').val(bankRate);
            }
            $(document).on('input', 'input[name="repo_rate"], input[name="interest_rate"]', function() {
                recalcBankRate(this);
            });

            // PF & Admin auto-calculation
            var loanAmount = parseFloat($('.shf-stages-wrap').data('loan-amount')) || 0;

            function recalcPfTotals(context) {
                var $card = $(context).closest('.card');
                if (!$card.length) $card = $(context).closest('form').closest('div');

                var pfType = $card.find('select[name="processing_fee_type"]').val() || 'percent';
                var pfValue = parseFloat($card.find('input[name="processing_fee"]').val()) || 0;
                var gstPercent = parseFloat($card.find('input[name="gst_percent"]').val()) || 0;

                // Calculate PF amount
                var pfAmount = pfType === 'percent' ? (loanAmount * pfValue / 100) : pfValue;
                pfAmount = Math.round(pfAmount * 100) / 100;

                // Calculate GST on PF
                var pfGst = Math.round(pfAmount * gstPercent / 100 * 100) / 100;

                // Total PF = PF Amount + GST
                var totalPf = Math.round((pfAmount + pfGst) * 100) / 100;

                // Set values — update both display and hidden raw inputs for currency fields
                setAmountField($card, 'processing_fee_amount', pfAmount);
                setAmountField($card, 'pf_gst_amount', pfGst);
                setAmountField($card, 'total_pf', totalPf);

                // Admin charges
                var adminCharges = parseFloat($card.find(
                    'input[name="admin_charges"].shf-amount-raw, input.shf-amount-raw').filter(function() {
                    return $(this).attr('name') === 'admin_charges';
                }).val()) || 0;
                var adminGstPercent = parseFloat($card.find('input[name="admin_charges_gst_percent"]').val()) || 0;
                var adminGst = Math.round(adminCharges * adminGstPercent / 100 * 100) / 100;
                var totalAdmin = Math.round((adminCharges + adminGst) * 100) / 100;

                setAmountField($card, 'admin_charges_gst_amount', adminGst);
                setAmountField($card, 'total_admin_charges', totalAdmin);
            }

            function setAmountField($card, fieldName, value) {
                // Find the hidden raw input and visible display input for currency fields
                var $raw = $card.find('input[name="' + fieldName + '"].shf-amount-raw');
                if ($raw.length) {
                    $raw.val(value);
                    $raw.closest('.shf-amount-wrap').find('.shf-amount-input').val(value ? Number(value)
                        .toLocaleString('en-IN') : '0');
                } else {
                    // Fallback for non-currency fields
                    $card.find('input[name="' + fieldName + '"]').val(value);
                }
            }

            // Trigger recalculation on relevant field changes
            $(document).on('input',
                'input[name="processing_fee"], input[name="gst_percent"], input[name="admin_charges_gst_percent"]',
                function() {
                    recalcPfTotals(this);
                });
            $(document).on('change', 'select[name="processing_fee_type"]', function() {
                recalcPfTotals(this);
            });
            // Also recalc when admin_charges amount changes (uses the shf-amount-input class)
            $(document).on('input', '.shf-amount-input', function() {
                var $raw = $(this).closest('.shf-amount-wrap').find('.shf-amount-raw');
                if ($raw.attr('name') === 'admin_charges') {
                    recalcPfTotals(this);
                }
            });

            // Init PF calculation on page load for any existing rate_pf forms
            $('select[name="processing_fee_type"]').each(function() {
                recalcPfTotals(this);
            });

            // Sanction EMI auto-calculation from sanctioned_amount + sanctioned_rate + tenure_months
            function calcSanctionEmi($container) {
                var $amount = $container.find('[name="sanctioned_amount"]');
                var $rate = $container.find('[name="sanctioned_rate"]');
                var $months = $container.find('[name="tenure_months"]');
                var $emiDisplay = $container.find('[name="emi_amount"]').closest('.shf-amount-wrap').find('.shf-amount-input');
                var $emiRaw = $container.find('[name="emi_amount"]');
                if (!$amount.length || !$rate.length || !$months.length) return;

                var P = parseFloat($amount.val()) || 0;
                var annualRate = parseFloat($rate.val()) || 0;
                var N = parseInt($months.val()) || 0;
                if (P <= 0 || annualRate <= 0 || N <= 0) return;

                var r = annualRate / 12 / 100;
                var emi = Math.round(P * r * Math.pow(1 + r, N) / (Math.pow(1 + r, N) - 1));
                $emiRaw.val(emi);
                if ($emiDisplay.length) {
                    $emiDisplay.val(SHF.formatIndianNumber(emi));
                    var $words = $emiDisplay.closest('.shf-amount-wrap').find('[data-amount-words]');
                    if ($words.length) $words.text(SHF.bilingualAmountWords(emi));
                }
                validateSanctionEmi($container);
            }

            function validateSanctionEmi($container) {
                var sanctionedAmount = parseFloat($container.find('[name="sanctioned_amount"]').val()) || 0;
                var emiAmount = parseFloat($container.find('[name="emi_amount"]').val()) || 0;
                var $emiDisplay = $container.find('[name="emi_amount"]').closest('.shf-amount-wrap').find('.shf-amount-input');
                var $col = $emiDisplay.closest('.col-sm-6, .col-sm-3, .col-sm-2, .shf-amount-wrap');
                $col.find('.shf-emi-error').remove();
                $emiDisplay.removeClass('is-invalid');
                if (sanctionedAmount > 0 && emiAmount > sanctionedAmount) {
                    $emiDisplay.addClass('is-invalid');
                    $col.append('<div class="shf-emi-error text-danger shf-text-xs mt-1">EMI cannot exceed sanctioned amount (₹ ' + SHF.formatIndianNumber(sanctionedAmount) + ')</div>');
                    return false;
                }
                return true;
            }

            // Auto-calculate on tenure/amount/rate change
            $(document).on('input change', '[name="tenure_months"]', function() {
                calcSanctionEmi($(this).closest('form'));
            });
            $(document).on('input change', '.shf-amount-input', function() {
                var $form = $(this).closest('form');
                if ($form.find('[name="tenure_months"]').length) {
                    var fieldName = $(this).closest('.shf-amount-wrap').find('.shf-amount-raw').attr('name');
                    if (fieldName === 'sanctioned_amount') {
                        calcSanctionEmi($form);
                    } else if (fieldName === 'emi_amount') {
                        validateSanctionEmi($form);
                    }
                }
            });

            // Block form submit if EMI > sanctioned amount (sanction stage)
            $(document).on('submit', '.shf-stage-notes-form', function(e) {
                var $form = $(this);
                if ($form.find('[name="tenure_months"]').length && $form.find('[name="sanctioned_amount"]').length) {
                    if (!validateSanctionEmi($form)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        var $emiDisplay = $form.find('[name="emi_amount"]').closest('.shf-amount-wrap').find('.shf-amount-input');
                        if ($emiDisplay.length) $emiDisplay[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                }
            });

            // Init sanction EMI on page load
            $('[name="tenure_months"]').each(function() {
                var $form = $(this).closest('form');
                var emiVal = parseFloat($form.find('[name="emi_amount"]').val()) || 0;
                if (emiVal > 0) validateSanctionEmi($form);
            });

            // Edit saved sub-stage data
            $(document).on('click', '.shf-edit-saved', function() {
                var target = $(this).data('target');
                $(this).closest('.shf-stage-saved-data').hide();
                $(target).slideDown(200, function() {
                    // Re-initialize datepickers in revealed form
                    $(target).find('.shf-datepicker').datepicker({
                        format: 'dd/mm/yyyy',
                        autoclose: true,
                        todayHighlight: true
                    });
                    $(target).find('.shf-datepicker-past').datepicker({
                        format: 'dd/mm/yyyy',
                        autoclose: true,
                        todayHighlight: true,
                        endDate: '+0d'
                    });
                    $(target).find('.shf-datepicker-custom').each(function() {
                        var opts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true };
                        var minDate = $(this).data('min-date');
                        var maxDate = $(this).data('max-date');
                        if (minDate) opts.startDate = minDate;
                        if (maxDate) opts.endDate = maxDate;
                        $(this).datepicker(opts);
                    });
                    // Re-initialize currency formatting
                    if (typeof SHF !== 'undefined' && SHF.initAmountFields) {
                        SHF.initAmountFields();
                    }
                    // Re-evaluate docket date toggle if present
                    $(target).find('[name="docket_days_offset"]').each(function() {
                        toggleCustomDocketDate($(this));
                    });
                });
            });

            // Raise query modal
            $(document).on('click', '.shf-raise-query-btn', function() {
                $('#queryLoanId').val($(this).data('loan-id'));
                $('#queryStageKey').val($(this).data('stage'));
                $('#queryText').val('');
                $('#raiseQueryModal').modal('show');
            });

            $('#submitQueryBtn').on('click', function() {
                var queryText = $('#queryText').val();
                if (!queryText || !queryText.trim()) {
                    var $qt = $('#queryText');
                    $qt.addClass('is-invalid');
                    $qt.next('.shf-field-error').remove();
                    $qt.after('<div class="shf-field-error text-danger shf-text-xs mt-1">Query text is required</div>');
                    $qt.focus();
                    return;
                }
                $('#queryText').removeClass('is-invalid').next('.shf-field-error').remove();
                var loanId = $('#queryLoanId').val();
                var stageKey = $('#queryStageKey').val();
                var $btn = $(this);
                $btn.prop('disabled', true).text('Submitting...');
                $.post('/loans/' + loanId + '/stages/' + stageKey + '/query', {
                    _token: csrfToken,
                    query_text: queryText
                }).done(function(r) {
                    if (r.success) {
                        $('#raiseQueryModal').modal('hide');
                        location.reload();
                    }
                }).fail(function(xhr) {
                    Swal.fire('Error', xhr.responseJSON?.error || 'Failed to raise query', 'error');
                }).always(function() {
                    $btn.prop('disabled', false).text('Raise Query');
                });
            });

            // Respond to query
            $(document).on('submit', '.shf-query-respond', function(e) {
                e.preventDefault();
                var $form = $(this),
                    url = $form.data('url');
                var responseText = $form.find('[name="response_text"]').val();
                var $rt = $form.find('[name="response_text"]');
                if (!responseText || !responseText.trim()) {
                    $rt.addClass('is-invalid');
                    $rt.next('.shf-field-error').remove();
                    $rt.after('<div class="shf-field-error text-danger shf-text-xs mt-1">Response text is required</div>');
                    $rt.focus();
                    return;
                }
                $rt.removeClass('is-invalid').next('.shf-field-error').remove();
                var $btn = $form.find('button[type="submit"]');
                $btn.prop('disabled', true);
                $.post(url, {
                        _token: csrfToken,
                        response_text: responseText
                    })
                    .done(function(r) {
                        if (r.success) location.reload();
                    })
                    .fail(function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.error || 'Failed to respond', 'error');
                    })
                    .always(function() {
                        $btn.prop('disabled', false);
                    });
            });

            // Resolve query
            $(document).on('click', '.shf-query-resolve', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                $.post($btn.data('url'), {
                        _token: csrfToken
                    })
                    .done(function(r) {
                        if (r.success) location.reload();
                    })
                    .fail(function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.error || 'Failed to resolve', 'error');
                    })
                    .always(function() {
                        $btn.prop('disabled', false);
                    });
            });

            // Load eligible users for transfer dropdowns
            $('.shf-transfer-user').each(function() {
                var $select = $(this);
                var loanId = $select.data('loan-id');
                var stage = $select.data('stage');
                var role = $select.data('role');
                if (!loanId || !stage) return;
                $.get('/loans/' + loanId + '/stages/' + stage + '/eligible-users', {
                        role: role
                    })
                    .done(function(r) {
                        if (r.users && r.users.length) {
                            r.users.forEach(function(u) {
                                $select.append('<option value="' + u.id + '">' + u.name +
                                    '</option>');
                            });
                            if (r.default_user_id) {
                                $select.val(r.default_user_id);
                            }
                        }
                    });
            });

            // Clear inline errors on input/change
            $(document).on('input', '.shf-sd-remarks', function() {
                $(this).removeClass('is-invalid').next('.shf-client-error').remove();
            });
            $(document).on('input', '#legalAdvisorName, #queryText, [name="response_text"]', function() {
                $(this).removeClass('is-invalid').next('.shf-field-error').remove();
            });
            $(document).on('change', '.shf-transfer-user, .shf-stage-transfer-select', function() {
                $(this).removeClass('is-invalid').next('.shf-field-error').remove();
            });

            // Sanction Decision actions
            $(document).on('click', '.shf-sd-action', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var action = $btn.data('action');
                var $card = $btn.closest('.card-body, .card');
                var remarks = $card.find('.shf-sd-remarks').val() || '';
                var transferTo = '';

                if (action === 'reject') {
                    if (!remarks || remarks.trim().length < 10) {
                        var $textarea = $card.find('.shf-sd-remarks');
                        $textarea.addClass('is-invalid');
                        $textarea.next('.shf-client-error').remove();
                        $textarea.after(
                            '<div class="text-danger small mt-1 shf-client-error">Rejection reason is required (minimum 10 characters)</div>'
                        );
                        $textarea.focus();
                        return;
                    }
                    Swal.fire({
                        title: 'Reject Loan?',
                        text: 'This will reject the entire loan and lock all stages. This cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Reject'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            $btn.prop('disabled', true);
                            $.post('/loans/' + loanId + '/stages/sanction_decision/action', {
                                _token: csrfToken,
                                action: 'reject',
                                rejection_reason: remarks.trim()
                            }).done(function(r) {
                                if (r.success) {
                                    Swal.fire({
                                            icon: 'success',
                                            title: 'Loan rejected',
                                            timer: 1500,
                                            showConfirmButton: false
                                        })
                                        .then(function() {
                                            location.reload();
                                        });
                                }
                            }).fail(function(xhr) {
                                $btn.prop('disabled', false);
                                Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                    'error');
                            });
                        }
                    });
                    return;
                }

                if (action.startsWith('escalate_') && (!remarks || !remarks.trim())) {
                    var $textarea = $card.find('.shf-sd-remarks');
                    $textarea.addClass('is-invalid');
                    $textarea.next('.shf-client-error').remove();
                    $textarea.after(
                        '<div class="text-danger small mt-1 shf-client-error">Remarks are required for escalation</div>'
                    );
                    $textarea.focus();
                    return;
                }

                var postData = {
                    _token: csrfToken,
                    action: action,
                    decision_remarks: remarks.trim()
                };
                if (transferTo) postData.transfer_to = transferTo;

                // Approve action — confirm before proceeding
                if (action === 'approve') {
                    Swal.fire({
                        title: 'Approve Loan?',
                        text: 'This will mark the loan as sanctioned and advance to the next stage.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Approve'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            $btn.prop('disabled', true);
                            $.post('/loans/' + loanId + '/stages/sanction_decision/action',
                                    postData)
                                .done(function(r) {
                                    if (r.success) {
                                        Swal.fire({
                                                icon: 'success',
                                                title: r.message,
                                                timer: 1500,
                                                showConfirmButton: false
                                            })
                                            .then(function() {
                                                location.reload();
                                            });
                                    }
                                })
                                .fail(function(xhr) {
                                    $btn.prop('disabled', false);
                                    Swal.fire('Error', xhr.responseJSON?.error || 'Failed',
                                        'error');
                                });
                        }
                    });
                    return;
                }

                $btn.prop('disabled', true);
                $.post('/loans/' + loanId + '/stages/sanction_decision/action', postData)
                    .done(function(r) {
                        if (r.success) {
                            Swal.fire({
                                    icon: 'success',
                                    title: r.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                })
                                .then(function() {
                                    location.reload();
                                });
                        }
                    })
                    .fail(function(xhr) {
                        $btn.prop('disabled', false);
                        Swal.fire('Error', xhr.responseJSON?.error || 'Failed', 'error');
                    });
            });

            // OTC: Transfer to Office Employee
            $(document).on('click', '.shf-otc-transfer', function() {
                var $btn = $(this);
                var loanId = $btn.data('loan-id');
                var userId = $btn.closest('.card-body').find(
                    '.shf-transfer-user[data-stage="otc_clearance"]').val();
                var $otcSelect = $btn.closest('.card-body').find('.shf-transfer-user[data-stage="otc_clearance"]');
                if (!userId) {
                    $otcSelect.addClass('is-invalid');
                    $otcSelect.next('.shf-field-error').remove();
                    $otcSelect.after('<div class="shf-field-error text-danger shf-text-xs mt-1">Please select an employee</div>');
                    return;
                }
                $otcSelect.removeClass('is-invalid').next('.shf-field-error').remove();
                $btn.prop('disabled', true);
                $.post('/loans/' + loanId + '/stages/otc_clearance/transfer', {
                        _token: csrfToken,
                        user_id: userId,
                        reason: 'OTC transfer to Office Employee'
                    })
                    .done(function(r) {
                        if (r.success) {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'Transferred to ' + r.assigned_to,
                                    timer: 1500,
                                    showConfirmButton: false
                                })
                                .then(function() {
                                    location.reload();
                                });
                        }
                    })
                    .fail(function(xhr) {
                        $btn.prop('disabled', false);
                        Swal.fire('Error', xhr.responseJSON?.error || 'Failed', 'error');
                    });
            });

            // Progress border: SVG rect stroke fills around the perimeter
            // Progress border: SVG path fills left → top → right → bottom
            (function() {
                var $pipe = $('.shf-pipeline');
                var path = $pipe.find('.shf-pipeline-border-path')[0];
                if (!path) return;
                // outerWidth/Height includes the 3px border on each side
                var ow = $pipe.outerWidth();
                var oh = $pipe.outerHeight();
                var svg = $pipe.find('.shf-pipeline-border')[0];
                // SVG covers entire outer box (positioned at -3px offset)
                svg.setAttribute('width', ow);
                svg.setAttribute('height', oh);
                // Path traces the center of the 3px border (1.5px inset from outer edge)
                var r = 10; // border radius
                var x1 = 1.5,
                    y1 = 1.5,
                    x2 = ow - 1.5,
                    y2 = oh - 1.5;
                var d = 'M ' + x1 + ' ' + (y2 - r) +
                    ' L ' + x1 + ' ' + (y1 + r) +
                    ' Q ' + x1 + ' ' + y1 + ' ' + (x1 + r) + ' ' + y1 +
                    ' L ' + (x2 - r) + ' ' + y1 +
                    ' Q ' + x2 + ' ' + y1 + ' ' + x2 + ' ' + (y1 + r) +
                    ' L ' + x2 + ' ' + (y2 - r) +
                    ' Q ' + x2 + ' ' + y2 + ' ' + (x2 - r) + ' ' + y2 +
                    ' L ' + (x1 + r) + ' ' + y2 +
                    ' Q ' + x1 + ' ' + y2 + ' ' + x1 + ' ' + (y2 - r);
                path.setAttribute('d', d);
                var perimeter = path.getTotalLength();
                var progress = parseInt($pipe.data('progress')) || 0;
                var filled = perimeter * (progress / 100);
                path.style.strokeDasharray = perimeter;
                path.style.strokeDashoffset = perimeter;
                // Force paint of empty state
                path.getBoundingClientRect();
                // Animate alongside stage icons
                var animDuration = parseInt($pipe.data('anim-duration')) || 800;
                path.style.transition = 'stroke-dashoffset ' + (animDuration / 1000) + 's ease-out';
                path.style.strokeDashoffset = perimeter - filled;
            })();

            // Auto-scroll to first actionable stage — don't wait for full animation
            var $target = $('[data-actionable="true"]').first();
            if ($target.length) {
                setTimeout(function() {
                    var offset = $target.offset().top - 80;
                    $('html, body').animate({
                        scrollTop: offset
                    }, 400);
                    $target.addClass('shf-stage-highlight');
                    setTimeout(function() {
                        $target.removeClass('shf-stage-highlight');
                    }, 2000);
                }, 600);
            }

            // Fixed bottom action bar for mobile/tablet
            (function() {
                var $actionCard = $('[data-actionable="true"]').first();
                if (!$actionCard.length) return;

                // Find all primary action buttons (stage complete, phase advance, form save)
                var actionSelectors = [
                    '.shf-stage-action',
                    '.shf-sd-action',
                    '.shf-legal-action',
                    '.shf-tv-action',
                    '.shf-rate-pf-action',
                    '.shf-sanction-action',
                    '.shf-docket-action',
                    '.shf-esign-action',
                    '.shf-primary-action',
                    '.shf-stage-notes-form button[type="submit"]'
                ].join(', ');
                var $buttons = $actionCard.find(actionSelectors);
                if (!$buttons.length) return;

                var $bar = $('#stageBottomBar');
                $buttons.each(function() {
                    var $orig = $(this);
                    var isLink = $orig.is('a');
                    var $clone = isLink ?
                        $('<a class="shf-bar-btn" href="' + $orig.attr('href') + '"></a>') :
                        $('<button class="shf-bar-btn"></button>');
                    $clone.html($orig.html());
                    if ($orig.hasClass('shf-btn-warning')) $clone.addClass('shf-bar-btn--warning');
                    if ($orig.hasClass('shf-btn-danger-alt') || $orig.hasClass('shf-btn-danger')) $clone
                        .addClass('shf-bar-btn--danger');
                    if ($orig.hasClass('shf-btn-success')) $clone.addClass('shf-bar-btn--success');
                    if (!isLink) {
                        $clone.on('click', function() {
                            var offset = $orig.offset().top - 100;
                            $('html, body').animate({
                                scrollTop: offset
                            }, 300, function() {
                                $orig.trigger('click');
                            });
                        });
                    }
                    $bar.append($clone);
                });

                $bar.addClass('shf-bar-visible');
            })();

        });
    </script>
