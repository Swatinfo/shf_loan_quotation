/*
 * Newtheme loan edit page — resources/views/newtheme/loans/edit.blade.php
 *
 * - bootstrap-datepicker on #leDob (max = today)
 * - Bank → Product filtering (products carry data-bank-id)
 * - Client-side validation via SHF.validateForm
 *
 * Indian-comma amount formatting + amount-words are auto-wired by
 * newtheme/assets/shf-newtheme.js (via .shf-amount-input / .shf-amount-raw
 * / [data-amount-words]).
 */
(function ($) {
    if (!$ || !$.fn) { return; }

    $(function () {
        /* ───────── Datepicker (DOB — must be in the past) ───────── */
        if ($.fn.datepicker) {
            $('#leDob').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true,
                endDate: '0d',
                clearBtn: true,
            });
        }

        /* ───────── Clear error on change ───────── */
        $('#leForm').on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid').next('.le-client-error').remove();
        });

        /* ───────── Bank → Product filter ───────── */
        var $bank = $('#leBank');
        var $product = $('#leProduct');
        var allOptions = $product.find('option').clone();
        var currentProduct = $product.val();

        function filterProducts() {
            var bankId = String($bank.val() || '');
            $product.empty().append('<option value="">— Select —</option>');
            if (bankId) {
                allOptions.each(function () {
                    if (String($(this).data('bank-id') || '') === bankId) {
                        $product.append($(this).clone());
                    }
                });
            } else {
                $product.append(allOptions.clone());
            }
            if (currentProduct) {
                $product.val(currentProduct);
                currentProduct = null; // only restore once on first paint
            }
        }
        $bank.on('change', filterProducts);
        if ($bank.val()) { filterProducts(); }

        /* ───────── PAN uppercase enforcement ───────── */
        $('#lePan').on('input', function () {
            var pos = this.selectionStart;
            var up = this.value.toUpperCase();
            if (up !== this.value) {
                this.value = up;
                try { this.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
            }
        });

        /* ───────── Submit: validate via SHF.validateForm ───────── */
        $('#leForm').on('submit', function (e) {
            if (!window.SHF || typeof window.SHF.validateForm !== 'function') { return; }
            var valid = window.SHF.validateForm($(this), {
                customer_name:  { required: true, maxlength: 255, label: 'Customer Name' },
                customer_type:  { required: true, label: 'Customer Type' },
                customer_phone: { required: true, maxlength: 20, label: 'Phone' },
                date_of_birth:  { required: true, dateFormat: 'd/m/Y', label: 'Date of Birth' },
                pan_number:     {
                    required: true,
                    pattern: /^[A-Z]{5}[0-9]{4}[A-Z]$/i,
                    patternMsg: 'PAN must be in format ABCDE1234F.',
                    label: 'PAN Number',
                },
                loan_amount:    { required: true, numeric: true, min: 1, label: 'Loan Amount' },
                bank_id:        { required: true, label: 'Bank' },
                product_id:     { required: true, label: 'Product' },
                branch_id:      { required: true, label: 'Branch' },
                customer_email: { email: true, maxlength: 255, label: 'Email' },
                notes:          { maxlength: 5000, label: 'Notes' },
            });
            if (!valid) {
                e.preventDefault();
                $(this).find('.is-invalid').first().trigger('focus');
            }
        });
    });
})(window.jQuery);
