/*
 * Newtheme user create/edit form — shared JS.
 *
 * Drives the role-dependent conditional fields (location / bank / branch /
 * PSU-holder autocomplete), password eye toggle, and SHF.validateForm-based
 * client validation with async email-uniqueness check.
 *
 * Unified DOM IDs keep a single script working for both create and edit pages.
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        var CFG = window.__UF || {};
        var mode = CFG.mode || 'create';
        var userId = CFG.userId; // null on create, int on edit

        var SINGLE_CITY_ROLES = ['bank_employee', 'office_employee'];
        var MULTI_LOC_ROLES = ['branch_manager', 'bdh', 'loan_advisor'];
        var BANK_ROLES = ['bank_employee', 'office_employee'];
        var BRANCH_REQUIRED_ROLES = ['branch_manager', 'bdh', 'loan_advisor', 'office_employee'];
        var PSU_ROLES = ['bank_employee', 'office_employee'];

        var $form = $('#userForm');
        if (!$form.length) { return; }

        /* ───────── Password eye toggles ───────── */
        $form.on('click', '.uf-eye', function () {
            var target = $(this).data('target');
            var $input = $('#' + target);
            if (!$input.length) { return; }
            var isPw = $input.attr('type') === 'password';
            $input.attr('type', isPw ? 'text' : 'password');
            $(this).find('.uf-eye-open').toggle(!isPw);
            $(this).find('.uf-eye-closed').toggle(isPw);
        });

        /* ───────── Clear per-field error on change ───────── */
        $form.on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid').next('.uf-client-error').remove();
        });

        /* ───────── Role-conditional field visibility ───────── */
        function currentRole() { return $('#ufRole').val() || ''; }
        function inList(arr, v) { return arr.indexOf(v) !== -1; }

        function applyRoleVisibility() {
            var role = currentRole();

            $('#ufSingleCityField').toggle(inList(SINGLE_CITY_ROLES, role));
            $('#ufMultiLocationField').toggle(inList(MULTI_LOC_ROLES, role));
            $('#ufSingleBankField').toggle(role === 'bank_employee');
            $('#ufMultiBankField').toggle(role === 'office_employee');
            $('#ufMultiBranchField').toggle(inList(BRANCH_REQUIRED_ROLES, role));
            $('#ufReplacePSU').toggle(inList(PSU_ROLES, role));

            var requireBranch = inList(BRANCH_REQUIRED_ROLES, role);
            $('#ufDefaultBranch').prop('required', requireBranch);
            $('.uf-branch-required').toggle(requireBranch);

            // Bank city defaults: only for bank_employee with a bank selected
            showBankCityDefaults();

            // OE-specific toggles
            if (role === 'office_employee') {
                $('.uf-oe-default-toggle, .uf-oe-default-hint').show();
            } else {
                $('.uf-oe-default-toggle, .uf-oe-default-hint').hide();
            }

            loadPSUHolders();
        }

        function showBankCityDefaults() {
            var role = currentRole();
            var bankId = $('#ufBankSelect').val();
            $('.uf-bank-city-defaults').hide();
            if (role === 'bank_employee' && bankId) {
                $('.uf-bank-city-defaults[data-bank-id="' + bankId + '"]').show();
            }
        }

        function syncPrimaryBranch() {
            var primaryId = $('#ufDefaultBranch').val();
            $('.uf-assigned-branch-cb').each(function () {
                var branchId = ($(this).data('branch-id') || '').toString();
                if (branchId === primaryId) {
                    $(this).prop('checked', true).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
        }

        /* ───────── PSU holder autocomplete ───────── */
        function loadPSUHolders() {
            var role = currentRole();
            if (!inList(PSU_ROLES, role)) { return; }

            var $holders = $('#ufPSUHolders');
            var cityId = $('#ufSingleCity').val();
            var bankIds = [];

            if (role === 'bank_employee') {
                var bankId = $('#ufBankSelect').val();
                if (!bankId || !cityId) {
                    var msg = !bankId && !cityId
                        ? 'Select bank and city first'
                        : (!bankId ? 'Select bank first' : 'Select city first');
                    $holders.html('<span class="uf-muted">' + msg + '</span>');
                    return;
                }
                bankIds = [bankId];
            } else {
                $('#ufMultiBankField input[name="assigned_banks[]"]:checked').each(function () {
                    bankIds.push($(this).val());
                });
                if (!cityId) {
                    $holders.html('<span class="uf-muted">Select city to load current holders.</span>');
                    return;
                }
                if (!bankIds.length) {
                    $holders.html('<span class="uf-muted">Select assigned banks to load current holders.</span>');
                    return;
                }
            }

            $holders.html('<span class="uf-muted">Loading…</span>');
            $.get(CFG.psuHoldersUrl, {
                'bank_ids[]': bankIds,
                location_id: cityId,
                role: role,
            }).done(function (data) {
                if (!data || !data.length) {
                    $holders.html('<span class="uf-muted">No current holders found for this role.</span>');
                    return;
                }
                var byProduct = {};
                data.forEach(function (h) {
                    var key = h.bank_name + ' — ' + h.product_name;
                    if (!byProduct[key]) { byProduct[key] = []; }
                    byProduct[key].push(h);
                });
                var html = '';
                Object.keys(byProduct).forEach(function (productLabel) {
                    var holders = byProduct[productLabel];
                    html += '<div class="mb-2" style="margin-bottom:10px;"><strong>' + esc(productLabel) + '</strong>';
                    holders.forEach(function (h) {
                        if (mode === 'edit' && userId && h.user_id === userId) {
                            html += '<div class="uf-muted" style="margin-left:12px;padding:2px 0;">'
                                + '<em>You</em> (' + esc(h.stage_list) + ')</div>';
                        } else {
                            html += '<label class="uf-check" style="margin-left:12px;padding:2px 0;display:flex;">'
                                + '<input type="checkbox" name="replace_psu[]" value="' + esc(h.user_id + '_' + h.product_id) + '">'
                                + '<span>' + esc(h.user_name) + ' (' + esc(h.stage_list) + ')</span>'
                                + '</label>';
                        }
                    });
                    html += '</div>';
                });
                $holders.html(html);
            }).fail(function () {
                $holders.html('<span class="uf-err">Failed to load holders.</span>');
            });
        }

        function esc(s) { return $('<div/>').text(s == null ? '' : s).html(); }

        /* ───────── Wiring ───────── */
        $('#ufRole').on('change', applyRoleVisibility);
        $('#ufBankSelect').on('change', function () {
            showBankCityDefaults();
            loadPSUHolders();
        });
        $('#ufSingleCity').on('change', loadPSUHolders);
        $form.on('change', '#ufMultiBankField input[name="assigned_banks[]"]', loadPSUHolders);
        $('#ufDefaultBranch').on('change', syncPrimaryBranch);

        // Initial pass — respects the server-rendered visibility
        syncPrimaryBranch();
        loadPSUHolders();

        /* ───────── Submit: validation + async email uniqueness ───────── */
        $form.on('submit', function (e) {
            var role = currentRole();

            var rules = {
                name:  { required: true, maxlength: 255, label: 'Name' },
                email: { required: true, email: true, maxlength: 255, label: 'Email' },
            };

            if (mode === 'create') {
                rules.password = { required: true, minlength: 8, label: 'Password' };
                rules.password_confirmation = {
                    required: true,
                    label: 'Confirm Password',
                    custom: function () {
                        var pw = $('#ufPassword').val();
                        var cf = $('#ufPasswordConfirm').val();
                        if (cf && pw !== cf) { return 'Passwords do not match.'; }
                        return null;
                    },
                };
            } else {
                var pw = $('#ufPassword').val();
                if (pw) {
                    rules.password = { minlength: 8, label: 'New Password' };
                    rules.password_confirmation = {
                        label: 'Confirm Password',
                        custom: function () {
                            var cf = $('#ufPasswordConfirm').val();
                            if (pw !== cf) { return 'Passwords do not match.'; }
                            return null;
                        },
                    };
                }
            }

            if (inList(SINGLE_CITY_ROLES, role)) {
                rules['assigned_locations[]'] = { required: true, label: 'City' };
            }
            if (role === 'bank_employee') {
                rules['assigned_banks[]'] = { required: true, label: 'Bank' };
            }
            if (inList(BRANCH_REQUIRED_ROLES, role)) {
                rules.default_branch_id = { required: true, label: 'Primary Branch' };
            }

            if (window.SHF && typeof window.SHF.validateForm === 'function') {
                if (!window.SHF.validateForm($form, rules)) {
                    e.preventDefault();
                    $form.find('.is-invalid').first().trigger('focus');
                    return false;
                }
            }

            // Async email uniqueness check
            e.preventDefault();
            var email = $form.find('[name="email"]').val();
            var payload = { email: email };
            if (userId) { payload.exclude_id = userId; }
            $.get(CFG.checkEmailUrl, payload).done(function (data) {
                if (!data || !data.available) {
                    var $emailField = $form.find('[name="email"]');
                    $emailField.addClass('is-invalid');
                    if (!$emailField.next('.uf-client-error').length) {
                        $emailField.after('<div class="uf-client-error">This email is already taken.</div>');
                    }
                    $emailField.trigger('focus');
                } else {
                    $form.off('submit');
                    $form[0].submit();
                }
            }).fail(function () {
                // Network failure — let the server validate
                $form.off('submit');
                $form[0].submit();
            });
        });
    });
})(window.jQuery);
