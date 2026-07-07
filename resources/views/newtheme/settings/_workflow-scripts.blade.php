<script>
$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Validate Add Bank/Product/Branch forms
    $('form').on('submit', function(e) {
        var $form = $(this);
        var action = $form.attr('action') || '';
        var rules = {};

        if (action.indexOf('banks') !== -1) {
            rules = { name: { required: true, maxlength: 255, label: 'Bank Name' } };
        } else if (action.indexOf('products') !== -1) {
            rules = { bank_id: { required: true, label: 'Bank' }, name: { required: true, maxlength: 255, label: 'Product Name' } };
        } else if (action.indexOf('branches') !== -1) {
            rules = { name: { required: true, maxlength: 255, label: 'Branch Name' } };
        }

        if (Object.keys(rules).length && !SHF.validateForm($form, rules)) {
            e.preventDefault();
        }
    });

    $('.shf-delete-bank').on('click', function() {
        var bankId = $(this).data('id');
        Swal.fire({
            title: 'Delete this bank?',
            text: 'This will delete the bank and all its products. This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({ url: '/settings/workflow/banks/' + bankId, method: 'DELETE', data: { _token: csrfToken } })
                    .done(function() { location.reload(); });
            }
        });
    });
    $('.shf-delete-branch').on('click', function() {
        var branchId = $(this).data('id');
        Swal.fire({
            title: 'Delete this branch?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({ url: '/settings/workflow/branches/' + branchId, method: 'DELETE', data: { _token: csrfToken } })
                    .done(function() { location.reload(); });
            }
        });
    });
});
</script>
