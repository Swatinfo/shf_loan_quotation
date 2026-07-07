    <script>
        $(function() {
            $('.shf-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true
            });
            $('.shf-datepicker-custom').each(function() {
                var opts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true };
                if ($(this).data('min-date')) opts.startDate = $(this).data('min-date');
                if ($(this).data('max-date')) opts.endDate = $(this).data('max-date');
                $(this).datepicker(opts);
            });

            // Auto-calculate valuations
            function parseNum(val) {
                return parseFloat(String(val).replace(/[^0-9.]/g, '')) || 0;
            }

            function calculateValuations() {
                var landArea = parseNum($('#landArea').val());
                var landRate = parseNum($('#landRate').val());
                var landVal = Math.round(landArea * landRate);

                var constArea = parseNum($('#constructionArea').val());
                var constRate = parseNum($('#constructionRate').val());
                var constVal = Math.round(constArea * constRate);

                var finalVal = landVal + constVal;

                $('#landValuation').val(landVal ? '₹ ' + SHF.formatIndianNumber(landVal) : '');
                $('#constructionValuation').val(constVal ? '₹ ' + SHF.formatIndianNumber(constVal) : '');
                $('#finalValuation').val(finalVal ? '₹ ' + SHF.formatIndianNumber(finalVal) : '');

                $('#landValWords').text(landVal ? SHF.bilingualAmountWords(landVal) : '');
                $('#constValWords').text(constVal ? SHF.bilingualAmountWords(constVal) : '');
                $('#finalValWords').text(finalVal ? SHF.bilingualAmountWords(finalVal) : '');
            }

            $('#landArea, #landRate, #constructionArea, #constructionRate').on('input change', calculateValuations);
            calculateValuations(); // Init on load

            // Map preview from lat/lng
            function updateMap() {
                var lat = $('#valLatitude').val();
                var lng = $('#valLongitude').val();
                if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                    $('#mapFrame').attr('src', 'https://maps.google.com/maps?q=' + lat + ',' + lng +
                        '&z=15&output=embed');
                    $('#mapPreview').show();
                } else {
                    $('#mapPreview').hide();
                }
            }

            $('#valLatitude, #valLongitude').on('change blur', updateMap);

            // Client-side validation
            $('form').on('submit', function(e) {
                var valid = SHF.validateForm($(this), {
                    property_type:          { required: true, label: 'Property Type' },
                    valuation_date:         { required: true, dateFormat: 'd/m/Y', label: 'Valuation Date' },
                    property_address:       { maxlength: 1000, label: 'Property Address' },
                    landmark:               { required: true, maxlength: 255, label: 'Landmark' },
                    latitude:               { maxlength: 50, label: 'Latitude' },
                    longitude:              { maxlength: 50, label: 'Longitude' },
                    land_area:              { required: true, numeric: true, min: 0, label: 'Land Area' },
                    land_rate:              { required: true, numeric: true, min: 0, label: 'Land Rate' },
                    construction_area:      { numeric: true, min: 0, label: 'Construction Area' },
                    construction_rate:      { numeric: true, min: 0, label: 'Construction Rate' },
                    valuator_name:          { required: true, maxlength: 255, label: 'Valuator Name' },
                    valuator_report_number: { maxlength: 100, label: 'Report Number' },
                    notes:                  { maxlength: 5000, label: 'Notes' }
                });
                if (!valid) e.preventDefault();
            });
        });
    </script>
