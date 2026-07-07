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

    // PAN autofill — when a complete PAN is entered, look up the latest known
    // details for that PAN and prefill phone / email / DOB (customer name comes
    // from the quotation). Operator can still override.
    var lookupUrl = @json(route('customers.lookup'));
    var $pan = $('#convertPan');
    var $hint = $('.shf-pan-lookup-hint');
    var $loans = $('.shf-pan-loans');
    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function renderLoans(loans) {
        if (!loans || !loans.length) { $loans.hide().empty(); return; }
        var links = loans.map(function(l) {
            return '<a href="' + l.url + '" target="_blank" rel="noopener" class="shf-text-accent fw-semibold">#' + esc(l.loan_number) + '</a>';
        }).join(', ');
        $loans.html('<span class="text-warning">&#9888; This customer has ' + loans.length +
            ' open loan(s): </span>' + links).show();
    }
    $pan.on('blur change', function() {
        var pan = $.trim($pan.val()).toUpperCase();
        $pan.val(pan);
        if (!/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(pan)) { $hint.hide().text(''); $loans.hide().empty(); return; }
        $.getJSON(lookupUrl, { pan: pan }).done(function(r) {
            if (!r.found || !r.customer) { $hint.hide().text(''); $loans.hide().empty(); return; }
            var c = r.customer;
            if (c.mobile && !$.trim($('[name="customer_phone"]').val())) { $('[name="customer_phone"]').val(c.mobile); }
            if (c.email && !$.trim($('[name="customer_email"]').val())) { $('[name="customer_email"]').val(c.email); }
            if (c.date_of_birth && !$.trim($('[name="date_of_birth"]').val())) { $('[name="date_of_birth"]').val(c.date_of_birth); }
            $hint.text('Existing customer found — details auto-filled (' + (c.customer_name || '') + ').').show();
            renderLoans(r.loans);
        });
    });

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
