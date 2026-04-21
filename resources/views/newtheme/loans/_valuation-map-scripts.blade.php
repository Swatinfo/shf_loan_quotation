    <script src="{{ asset('newtheme/vendor/leaflet/leaflet.js') }}"></script>
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

            // ---- Leaflet Map ----
            // Fix Leaflet default icon paths for local vendor files
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: '{{ asset('newtheme/vendor/leaflet/images/marker-icon-2x.png') }}',
                iconUrl: '{{ asset('newtheme/vendor/leaflet/images/marker-icon.png') }}',
                shadowUrl: '{{ asset('newtheme/vendor/leaflet/images/marker-shadow.png') }}'
            });

            // Default center: Rajkot, Gujarat
            var defaultLat = 22.3039;
            var defaultLng = 70.8022;
            var defaultZoom = 13;

            var initLat = parseFloat($('#valLatitude').val()) || defaultLat;
            var initLng = parseFloat($('#valLongitude').val()) || defaultLng;
            var hasInitCoords = $('#valLatitude').val() && $('#valLongitude').val();

            var map = L.map('leafletMap').setView([initLat, initLng], hasInitCoords ? 16 : defaultZoom);

            // Tile layers
            var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19
            });
            var satelliteLayer = L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: '&copy; Esri',
                    maxZoom: 19
                });
            var hybridLabels = L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    pane: 'overlayPane'
                });

            // Satellite + labels as default
            var satelliteHybrid = L.layerGroup([satelliteLayer, hybridLabels]);
            satelliteHybrid.addTo(map);

            // Layer switcher
            L.control.layers({
                'Satellite': satelliteHybrid,
                'Street': streetLayer
            }, null, {
                position: 'topright'
            }).addTo(map);

            var marker = null;
            var geocodeTimer = null;

            function reverseGeocode(lat, lng) {
                // Debounce to avoid spamming the API
                clearTimeout(geocodeTimer);
                geocodeTimer = setTimeout(function() {
                    $.getJSON('{{ route('api.reverse-geocode') }}', {
                            lat: lat,
                            lng: lng
                        })
                        .done(function(data) {
                            if (data.landmark) {
                                $('[name="landmark"]').val(data.landmark);
                            }
                            if (data.address) {
                                $('[name="property_address"]').val(data.address);
                            }
                        });
                }, 600);
            }

            function setMarker(lat, lng) {
                lat = parseFloat(lat);
                lng = parseFloat(lng);
                if (isNaN(lat) || isNaN(lng)) return;

                $('#valLatitude').val(lat.toFixed(6));
                $('#valLongitude').val(lng.toFixed(6));
                $('#coordDisplay').text(lat.toFixed(6) + ', ' + lng.toFixed(6));
                $('#btnCopyLocation').removeClass('d-none');

                // Reverse geocode to auto-fill landmark + address
                reverseGeocode(lat, lng);

                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], {
                        draggable: true
                    }).addTo(map);
                    marker.on('dragend', function(e) {
                        var pos = e.target.getLatLng();
                        setMarker(pos.lat, pos.lng);
                    });
                }
                map.setView([lat, lng], 19);
            }

            // Init marker if coordinates exist
            if (hasInitCoords) {
                setMarker(initLat, initLng);
            }

            // Click map to set marker
            map.on('click', function(e) {
                setMarker(e.latlng.lat, e.latlng.lng);
            });

            // Parse coordinates from paste field
            function parseCoordinates(input) {
                input = input.trim();
                // Try "lat, lng" format
                var commaMatch = input.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/);
                if (commaMatch) return {
                    lat: commaMatch[1],
                    lng: commaMatch[2]
                };

                // Try Google Maps URL formats
                // https://www.google.com/maps?q=22.3039,70.8022
                var qMatch = input.match(/[?&]q=(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)/);
                if (qMatch) return {
                    lat: qMatch[1],
                    lng: qMatch[2]
                };

                // https://www.google.com/maps/@22.3039,70.8022,15z
                var atMatch = input.match(/@(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)/);
                if (atMatch) return {
                    lat: atMatch[1],
                    lng: atMatch[2]
                };

                // https://maps.google.com/maps/place/.../@22.3039,70.8022
                var placeMatch = input.match(/place\/.*\/@(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                if (placeMatch) return {
                    lat: placeMatch[1],
                    lng: placeMatch[2]
                };

                // Google Maps short link with coordinates in path
                var pathMatch = input.match(/(-?\d{1,3}\.\d{3,})\s*,\s*(-?\d{1,3}\.\d{3,})/);
                if (pathMatch) return {
                    lat: pathMatch[1],
                    lng: pathMatch[2]
                };

                return null;
            }

            $('#btnParseCoords, #coordPaste').on('click keyup', function(e) {
                if (e.type === 'keyup' && e.which !== 13) return; // Only Enter key
                var val = $('#coordPaste').val();
                if (!val) return;
                var coords = parseCoordinates(val);
                if (coords) {
                    setMarker(coords.lat, coords.lng);
                    $('#coordPaste').val(coords.lat + ', ' + coords.lng).removeClass('is-invalid');
                } else {
                    Swal.fire('Error',
                        'Could not parse coordinates. Try "22.3039, 70.8022" or a Google Maps link.',
                        'error');
                    $('#coordPaste').addClass('is-invalid');
                }
            });

            // Copy Location to clipboard
            $('#btnCopyLocation').on('click', function() {
                var lat = $('#valLatitude').val();
                var lng = $('#valLongitude').val();
                if (!lat || !lng) return;
                var text = lat + ', ' + lng;
                navigator.clipboard.writeText(text).then(function() {
                    var $btn = $('#btnCopyLocation');
                    $btn.html(
                        '<svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copied!'
                        );
                    setTimeout(function() {
                        $btn.html(
                            '<svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg> Copy'
                            );
                    }, 1500);
                });
            });

            // Use My Location (GPS)
            $('#btnMyLocation').on('click', function() {
                var $btn = $(this);
                if (!navigator.geolocation) {
                    Swal.fire('Error', 'GPS is not supported by your browser.', 'error');
                    return;
                }
                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span> Locating...');
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        setMarker(pos.coords.latitude, pos.coords.longitude);
                        $btn.prop('disabled', false).html(
                            '<svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> My Location'
                            );
                    },
                    function(err) {
                        Swal.fire('Error', 'Could not get your location: ' + err.message, 'error');
                        $btn.prop('disabled', false).html(
                            '<svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> My Location'
                            );
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000
                    }
                );
            });

            // ---- Location Search ----
            var searchTimer = null;

            function doSearch() {
                var q = $('#locationSearch').val().trim();
                if (q.length < 3) {
                    $('#searchResults').addClass('d-none').empty();
                    return;
                }
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    $.getJSON('{{ route('api.search-location') }}', {
                        q: q
                    }).fail(function() {
                        $('#searchResults').html(
                            '<div class="p-2 text-muted shf-text-xs">Search failed. Try again.</div>'
                            ).removeClass('d-none');
                    }).done(function(data) {
                        var $results = $('#searchResults').empty();
                        if (!data.results || !data.results.length) {
                            $results.html(
                                '<div class="p-2 text-muted shf-text-xs">No results found</div>'
                                ).removeClass('d-none');
                            return;
                        }
                        data.results.forEach(function(r) {
                            var $item = $(
                                    '<div class="p-2 border-bottom shf-text-xs" style="cursor:pointer;"></div>'
                                    )
                                .text(r.name)
                                .on('click', function() {
                                    setMarker(r.lat, r.lng);
                                    $('#locationSearch').val(r.name);
                                    $results.addClass('d-none');
                                });
                            $item.on('mouseenter', function() {
                                $(this).css('background', '#f0f0f0');
                            });
                            $item.on('mouseleave', function() {
                                $(this).css('background', '');
                            });
                            $results.append($item);
                        });
                        $results.removeClass('d-none');
                    });
                }, 400);
            }

            $('#locationSearch').on('input', doSearch);
            $('#locationSearch').on('keydown', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    doSearch();
                }
            });
            $('#btnSearch').on('click', doSearch);
            // Hide results on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#locationSearch, #searchResults, #btnSearch').length) {
                    $('#searchResults').addClass('d-none');
                }
            });

            // ---- Valuation Calculations ----
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

            // ---- Client-side validation ----
            $('form').on('submit', function(e) {
                var valid = SHF.validateForm($(this), {
                    property_type: {
                        required: true,
                        label: 'Property Type'
                    },
                    valuation_date: {
                        required: true,
                        dateFormat: 'd/m/Y',
                        label: 'Valuation Date'
                    },
                    property_address: {
                        maxlength: 1000,
                        label: 'Property Address'
                    },
                    landmark: {
                        required: true,
                        maxlength: 255,
                        label: 'Landmark'
                    },
                    land_area: {
                        required: true,
                        numeric: true,
                        min: 0,
                        label: 'Land Area'
                    },
                    land_rate: {
                        required: true,
                        numeric: true,
                        min: 0,
                        label: 'Land Rate'
                    },
                    construction_area: {
                        numeric: true,
                        min: 0,
                        label: 'Construction Area'
                    },
                    construction_rate: {
                        numeric: true,
                        min: 0,
                        label: 'Construction Rate'
                    },
                    valuator_name: {
                        required: true,
                        maxlength: 255,
                        label: 'Valuator Name'
                    },
                    valuator_report_number: {
                        maxlength: 100,
                        label: 'Report Number'
                    },
                    notes: {
                        maxlength: 5000,
                        label: 'Notes'
                    }
                });
                if (!valid) e.preventDefault();
            });
        });
    </script>
