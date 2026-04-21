    <script>
        $(function() {
            $('#productStagesForm').on('submit', function(e) {
                var hasError = false;
                var firstErrorEl = null;

                // Clear previous
                $('.shf-loc-row').css('outline', '');
                $('.shf-inline-error').remove();

                function addError($row, msg) {
                    hasError = true;
                    if (!firstErrorEl) firstErrorEl = $row;
                    $row.css('outline', '2px solid #dc3545');
                    $row.append('<div class="shf-inline-error text-danger mt-1 shf-text-2xs">* ' + msg + '</div>');
                }

                // Validate enabled stages
                $('.shf-stage-block').each(function() {
                    var $block = $(this);
                    var $toggle = $block.find('.shf-stage-toggle');
                    if (!$toggle.length || !$toggle.is(':checked')) return;
                    $block.find('.shf-loc-row').each(function() { validateLocRow($(this)); });
                });

                // Validate enabled sub-stages
                $('.shf-substage-block').each(function() {
                    var $block = $(this);
                    var $toggle = $block.find('.shf-substage-toggle');
                    if (!$toggle.length || !$toggle.is(':checked')) return;
                    $block.find('.shf-loc-row').each(function() { validateLocRow($(this)); });
                });

                function validateLocRow($row) {
                    var $checkboxes = $row.find('input[type="checkbox"]');
                    var $radios = $row.find('input[type="radio"]');

                    if (!$checkboxes.length) {
                        addError($row, 'No eligible employees');
                        return;
                    }
                    if ($checkboxes.filter(':checked').length === 0) {
                        addError($row, 'Select at least one employee');
                        return;
                    }
                    var checkedRadio = $radios.filter(':checked');
                    if (checkedRadio.length === 0) {
                        $row.css('outline', '2px solid #f15a29');
                        $row.append('<div class="shf-inline-error" style="font-size:0.7rem;color:#f15a29;">* Select a default employee</div>');
                        hasError = true;
                        if (!firstErrorEl) firstErrorEl = $row;
                        return;
                    }
                    var checkedVals = $checkboxes.filter(':checked').map(function() { return $(this).val(); }).get();
                    if (checkedVals.indexOf(checkedRadio.val()) === -1) {
                        $row.css('outline', '2px solid #f15a29');
                        $row.append('<div class="shf-inline-error" style="font-size:0.7rem;color:#f15a29;">* Default must be a selected employee</div>');
                        hasError = true;
                        if (!firstErrorEl) firstErrorEl = $row;
                    }
                }

                if (hasError) {
                    e.preventDefault();
                    if (firstErrorEl) {
                        $('html, body').animate({ scrollTop: $(firstErrorEl).offset().top - 100 }, 300);
                    }
                    return false;
                }
            });

            // Auto-select default when only one checkbox checked
            $(document).on('change', '.shf-loc-row input[type="checkbox"]', function() {
                var $row = $(this).closest('.shf-loc-row');
                var $checked = $row.find('input[type="checkbox"]:checked');
                if ($checked.length === 1) {
                    $row.find('input[type="radio"][value="' + $checked.val() + '"]').prop('checked', true);
                }
                if (!$(this).is(':checked')) {
                    var val = $(this).val();
                    var $radio = $row.find('input[type="radio"][value="' + val + '"]:checked');
                    if ($radio.length) {
                        $radio.prop('checked', false);
                        var $first = $row.find('input[type="checkbox"]:checked').first();
                        if ($first.length) {
                            $row.find('input[type="radio"][value="' + $first.val() + '"]').prop('checked',
                                true);
                        }
                    }
                }
            });
        });
    </script>
