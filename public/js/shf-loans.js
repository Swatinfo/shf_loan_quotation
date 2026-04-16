/**
 * SHF Loans — Client-side interactions for loan workflow system.
 * Loaded on loan pages via @push('scripts')
 */

const SHFLoans = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,

    /**
     * Product dependent dropdown (when bank changes, filter products)
     */
    initProductDropdown() {
        var $bank = $('#bankSelect'), $product = $('#productSelect');
        if (!$bank.length || !$product.length) return;

        var allOptions = $product.find('option').clone();
        $bank.on('change', function() {
            var bankId = $(this).val();
            $product.empty().append('<option value="">-- Select Product --</option>');
            if (bankId) {
                allOptions.each(function() {
                    if ($(this).data('bank-id') == bankId) $product.append($(this).clone());
                });
            } else {
                $product.append(allOptions.clone());
            }
        });
        if ($bank.val()) $bank.trigger('change');
    },

    /**
     * Show toast notification
     */
    showToast(message, type) {
        type = type || 'info';
        var bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-primary');
        var $toast = $('<div class="alert alert-dismissible fade show position-fixed bottom-0 end-0 m-3 text-white ' + bgClass + '" style="z-index:9999;max-width:350px;">'
            + message
            + '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button></div>');
        $('body').append($toast);
        setTimeout(function() { $toast.alert('close'); }, 4000);
    },

    init() {
        this.initProductDropdown();
    }
};

$(document).ready(function() { SHFLoans.init(); });
