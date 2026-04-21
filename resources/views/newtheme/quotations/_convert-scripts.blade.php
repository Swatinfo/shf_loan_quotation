<script>
$(function() {
    // Init datepicker for DOB
    $('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, endDate: new Date() });

    var $bankRadios = $('input[name="bank_index"]');
    var $product = $('#productSelect');
    var allOptions = $product.find('option').clone();

    // Highlight selected bank card + filter products by matched bank_id
    $bankRadios.on('change', function() {
        $('.form-check.border').removeClass('border-primary');
        $(this).closest('.form-check').addClass('border-primary');

        var bankId = $(this).data('bank-id');

        $product.empty().append('<option value="">-- Select Product --</option>');
        if (bankId) {
            allOptions.each(function() {
                if ($(this).data('bank-id') == bankId) {
                    $product.append($(this).clone());
                }
            });
        }
    });

    // Trigger on page load
    $bankRadios.filter(':checked').trigger('change');

    // Client-side validation before submit
    $('form').on('submit', function(e) {
        var errors = [];

        // Clear previous errors
        $(this).find('.shf-client-error').remove();
        $(this).find('.is-invalid').removeClass('is-invalid');

        var fields = [
            { sel: '[name="bank_index"]:checked', msg: 'Please select a bank', type: 'radio' },
            { sel: '[name="branch_id"]', msg: 'Branch is required' },
            { sel: '#productSelect', msg: 'Product is required' },
            { sel: '[name="customer_phone"]', msg: 'Customer phone is required' },
            { sel: '[name="date_of_birth"]', msg: 'Date of birth is required' },
            { sel: '[name="pan_number"]', msg: 'PAN number is required' },
            { sel: '[name="assigned_advisor"]', msg: 'Assigned advisor is required' },
        ];

        $.each(fields, function(_, f) {
            if (f.type === 'radio') {
                if ($(f.sel).length === 0) {
                    errors.push(f.msg);
                }
                return;
            }
            var $el = $(f.sel);
            var val = $.trim($el.val());
            if (!val) {
                errors.push(f.msg);
                $el.addClass('is-invalid');
                $el.after('<div class="text-danger small mt-1 shf-client-error">' + f.msg + '</div>');
            }
        });

        // PAN format validation
        var pan = $.trim($('[name="pan_number"]').val()).toUpperCase();
        if (pan && !/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(pan)) {
            errors.push('PAN number must be in format ABCDE1234F');
            $('[name="pan_number"]').addClass('is-invalid')
                .after('<div class="text-danger small mt-1 shf-client-error">PAN number must be in format ABCDE1234F</div>');
        }

        if (errors.length) {
            e.preventDefault();
            // Scroll to first error
            var $first = $('.is-invalid').first();
            if ($first.length) {
                $('html, body').animate({ scrollTop: $first.offset().top - 100 }, 300);
            }
        }
    });

    // Clear error on input change
    $(document).on('change input', '.is-invalid', function() {
        $(this).removeClass('is-invalid').next('.shf-client-error').remove();
    });
});
</script>
