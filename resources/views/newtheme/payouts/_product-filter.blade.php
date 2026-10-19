{{--
    Product multi-select (Select2) for Payout Runs / Reconciliation. Options depend
    on the selected bank; selecting at least one product is REQUIRED. Needs
    $products (id, name, bank_id) + $input['bank_id'] / $input['product_ids'].
--}}
@php $selectedProductIds = array_map('strval', (array) ($input['product_ids'] ?? [])); @endphp
<div class="field">
    <label class="lbl">Products <span class="ld-req">*</span></label>
    {{-- Wrapper so SHF.validateForm appends the error BELOW the Select2 box
         (it appends after the closest .position-relative; without it the message
         lands after the hidden <select>, i.e. above the box). --}}
    <div class="position-relative">
        <select name="product_ids[]" class="select po-product-select" multiple required
            data-product-select data-placeholder="Select product(s)…">
            @foreach ($products->where('bank_id', (int) ($input['bank_id'] ?? 0)) as $p)
                <option value="{{ $p->id }}" @selected(in_array((string) $p->id, $selectedProductIds, true))>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
</div>

@once
    @push('page-styles')
        <link rel="stylesheet" href="{{ asset('newtheme/vendor/select2/select2.min.css') }}?v={{ config('app.shf_version') }}">
        <style>
            .select2-container { width: 100% !important; }
            .select2-container--default .select2-selection--multiple { border-color: var(--line, #d0d3d6); min-height: 38px; border-radius: 8px; }
            .select2-container--default .select2-selection--multiple .select2-selection__choice { background: var(--accent-dim, rgba(241,90,41,.12)); border-color: var(--accent, #f15a29); color: var(--ink, #1a1a1a); }
            .select2-container--default .select2-results__option--highlighted[aria-selected] { background: var(--accent, #f15a29); }
            /* Invalid state mirrored onto the Select2 box when SHF.validateForm flags the hidden <select>. */
            select.po-product-select.is-invalid + .select2-container .select2-selection { border-color: #dc3545 !important; box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15); }
        </style>
    @endpush
    @push('page-scripts')
        <script src="{{ asset('newtheme/vendor/select2/select2.min.js') }}?v={{ config('app.shf_version') }}"></script>
        <script>
            window.__payoutProducts = @json($products->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name, 'bank_id' => (int) $p->bank_id])->values());
            $(function () {
                function initSelect2($el) {
                    $el.select2({ placeholder: $el.data('placeholder') || 'Select…', width: '100%' });
                }
                function rebuild(bankSel, prodSel) {
                    var $p = $(prodSel);
                    var bankId = parseInt(bankSel.value, 10) || 0;
                    var kept = ($p.val() || []).map(Number); // keep still-valid picks
                    prodSel.innerHTML = '';
                    (window.__payoutProducts || []).filter(function (p) { return p.bank_id === bankId; })
                        .forEach(function (p) {
                            var o = document.createElement('option');
                            o.value = p.id; o.textContent = p.name;
                            if (kept.indexOf(p.id) >= 0) { o.selected = true; }
                            prodSel.appendChild(o);
                        });
                    if ($p.hasClass('select2-hidden-accessible')) { $p.select2('destroy'); }
                    initSelect2($p);
                }
                $('[data-product-select]').each(function () {
                    var prodSel = this;
                    var form = prodSel.closest('form');
                    initSelect2($(prodSel));
                    if (!form) { return; }

                    // Client-side validation (matches the project's SHF.validateForm pattern).
                    $(form).on('submit', function (e) {
                        if (!window.SHF || !SHF.validateForm) { return; }
                        var rules = {
                            bank_id: { required: true, label: 'Bank' },
                            'product_ids[]': { required: true, label: 'Products' },
                            from: { required: true, label: 'From' },
                            to: { required: true, label: 'To' }
                        };
                        if (form.querySelector('input[type="file"]')) {
                            rules.file = { required: true, label: 'Bank statement (.xlsx)' };
                        }
                        if (!SHF.validateForm($(form), rules)) { e.preventDefault(); }
                    });

                    var bankSel = form.querySelector('select[name="bank_id"]');
                    if (bankSel) {
                        $(bankSel).on('change', function () { $(prodSel).val(null); rebuild(bankSel, prodSel); });
                    }
                });
            });
        </script>
    @endpush
@endonce
