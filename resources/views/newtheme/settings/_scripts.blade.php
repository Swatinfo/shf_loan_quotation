        <script>
            $(function() {
                // ============================================================
                //  TAB SWITCHING — works for both legacy (.shf-tab) and
                //  newtheme (.tabs .tab[data-tab]) tab bars. Persists the
                //  selected tab across refresh via URL hash + localStorage.
                // ============================================================
                var SETTINGS_TAB_STORAGE = 'shf_settings_active_tab';

                function activateTab(tab) {
                    if (!tab || !$('#tab-' + tab).length) return false;
                    $('.shf-tab, .tabs .tab[data-tab], .settings-nt-rail-item[data-tab]').removeClass('active');
                    $(
                        '.shf-tab[data-tab="' + tab + '"], ' +
                        '.tabs .tab[data-tab="' + tab + '"], ' +
                        '.settings-nt-rail-item[data-tab="' + tab + '"]'
                    ).addClass('active');
                    $('.settings-tab-pane').hide();
                    $('#tab-' + tab).show();
                    try { localStorage.setItem(SETTINGS_TAB_STORAGE, tab); } catch (e) { /* storage disabled */ }
                    history.replaceState(null, '', '#' + tab);
                    // Notify page-level scripts (e.g. mobile tab strip) so they
                    // can sync their UI (scroll active into view, update label).
                    document.dispatchEvent(new CustomEvent('settings:tab-activated', { detail: { tab: tab } }));
                    return true;
                }

                $(document).on('click', '.shf-tab, .tabs .tab[data-tab], .settings-nt-rail-item[data-tab]', function(e) {
                    e.preventDefault();
                    activateTab($(this).data('tab'));
                });

                // Restore order: URL hash > localStorage > first .active > first tab
                var restored = false;
                var hash = (window.location.hash || '').replace('#', '');
                if (hash) { restored = activateTab(hash); }
                if (!restored) {
                    try {
                        var stored = localStorage.getItem(SETTINGS_TAB_STORAGE);
                        if (stored) { restored = activateTab(stored); }
                    } catch (e) { /* storage disabled */ }
                }
                if (!restored) {
                    var $firstActive = $('.shf-tab.active, .tabs .tab.active').first();
                    if ($firstActive.length) {
                        activateTab($firstActive.data('tab'));
                    } else {
                        var $firstTab = $('.shf-tab, .tabs .tab[data-tab], .settings-nt-rail-item[data-tab]').first();
                        if ($firstTab.length) { activateTab($firstTab.data('tab')); }
                    }
                }

                // ============================================================
                //  FORM VALIDATION
                // ============================================================
                // Company Details
                $('#tab-company form').on('submit', function(e) {
                    if (!SHF.validateForm($(this), {
                        companyName: { required: true, maxlength: 255, label: 'Company Name' },
                        companyEmail: { required: true, email: true, label: 'Email' },
                        companyAddress: { required: true, label: 'Address' },
                        companyPhone: { required: true, maxlength: 20, label: 'Phone' }
                    })) { e.preventDefault(); }
                });
                // IOM Stamp Paper Charges
                $('#tab-charges form').on('submit', function(e) {
                    if (!SHF.validateForm($(this), {
                        'iomCharges[thresholdAmount]': { required: true, numeric: true, min: 0, label: 'Threshold Amount' },
                        'iomCharges[fixedCharge]': { required: true, numeric: true, min: 0, label: 'Fixed Charge' },
                        'iomCharges[percentageAbove]': { required: true, numeric: true, min: 0, label: 'Percentage' }
                    })) { e.preventDefault(); }
                });
                // GST
                $('#tab-gst form').on('submit', function(e) {
                    if (!SHF.validateForm($(this), {
                        gstPercent: { required: true, numeric: true, min: 0, max: 100, label: 'GST Percentage' }
                    })) { e.preventDefault(); }
                });

                // ============================================================
                //  TENURES MANAGER
                // ============================================================
                var tenures = @json($config['tenures'] ?? []);

                function renderTenureTags() {
                    var html = '';
                    $.each(tenures, function(idx, t) {
                        html +=
                            '<span class="shf-tag" style="background:#f0fdf4;border-color:#86efac;color:#16a34a;">' +
                            '<span>' + t + ' Years</span>' +
                            '<input type="hidden" name="tenures[]" value="' + t + '">' +
                            '<button type="button" class="shf-tag-remove removeTenureBtn" data-idx="' + idx +
                            '" style="background:#16a34a;">&times;</button>' +
                            '</span>';
                    });
                    $('#tenureTagsContainer').html(html);
                }
                renderTenureTags();

                $('#addTenureBtn').on('click', function() {
                    var val = parseInt($('#newTenureInput').val());
                    if (val && val > 0 && $.inArray(val, tenures) === -1) {
                        tenures.push(val);
                        tenures.sort(function(a, b) {
                            return a - b;
                        });
                        renderTenureTags();
                        $('#newTenureInput').val('');
                    }
                });
                $('#newTenureInput').on('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $('#addTenureBtn').click();
                    }
                });
                $(document).on('click', '.removeTenureBtn', function() {
                    tenures.splice($(this).data('idx'), 1);
                    renderTenureTags();
                });
                // Auto-add pending tenure on form submit
                $('#tenuresForm').on('submit', function() {
                    var val = parseInt($('#newTenureInput').val());
                    if (val && val > 0 && $.inArray(val, tenures) === -1) {
                        tenures.push(val);
                        tenures.sort(function(a, b) {
                            return a - b;
                        });
                        renderTenureTags();
                    }
                });

                // ============================================================
                //  BANK CHARGES MANAGER
                // ============================================================
                // Bank charges: one row per DB bank, pre-filled from saved charges
                var savedCharges = {};
                @json($bankCharges->map(fn($c) => $c->toArray())->values()).forEach(function(c) {
                    savedCharges[c.bank_name] = c;
                });
                var loanBanks = @json($loanBanks);

                function renderChargeRows() {
                    var html = '';
                    loanBanks.forEach(function(bankName, idx) {
                        var c = savedCharges[bankName] || {};
                        html += '<tr>' +
                            '<td><strong class="shf-text-sm">' + bankName + '</strong>' +
                            '<input type="hidden" name="charges[' + idx + '][bank_name]" value="' + bankName +
                            '">' +
                            '</td>' +
                            '<td><input type="number" name="charges[' + idx + '][pf]" value="' + (c.pf || 0) +
                            '" step="0.01" class="shf-input small" style="width:6rem;"></td>' +
                            '<td><input type="number" name="charges[' + idx + '][admin]" value="' + (c.admin ||
                                0) + '" class="shf-input small" style="width:5rem;"></td>' +
                            '<td><input type="number" name="charges[' + idx + '][stamp_notary]" value="' + (c
                                .stamp_notary || 0) + '" class="shf-input small" style="width:5rem;"></td>' +
                            '<td><input type="number" name="charges[' + idx + '][registration_fee]" value="' + (
                                c.registration_fee || 0) +
                            '" class="shf-input small" style="width:5rem;"></td>' +
                            '<td><input type="number" name="charges[' + idx + '][advocate]" value="' + (c
                                .advocate || 0) + '" class="shf-input small" style="width:5rem;"></td>' +
                            '<td><input type="number" name="charges[' + idx + '][tc]" value="' + (c.tc || 0) +
                            '" class="shf-input small" style="width:5rem;"></td>' +
                            '<td>' +
                            '<input type="hidden" name="charges[' + idx + '][extra1_name]" value="' + (c
                                .extra1_name || '') + '">' +
                            '<input type="hidden" name="charges[' + idx + '][extra1_amt]" value="' + (c
                                .extra1_amt || 0) + '">' +
                            '<input type="hidden" name="charges[' + idx + '][extra2_name]" value="' + (c
                                .extra2_name || '') + '">' +
                            '<input type="hidden" name="charges[' + idx + '][extra2_amt]" value="' + (c
                                .extra2_amt || 0) + '">' +
                            '</td></tr>';
                    });
                    $('#bankChargesBody').html(html);
                }
                renderChargeRows();

                // ============================================================
                //  DOCUMENTS MANAGER
                // ============================================================
                var docs = {
                    proprietor: {
                        en: @json($config['documents_en']['proprietor'] ?? []),
                        gu: @json($config['documents_gu']['proprietor'] ?? [])
                    },
                    partnership_llp: {
                        en: @json($config['documents_en']['partnership_llp'] ?? []),
                        gu: @json($config['documents_gu']['partnership_llp'] ?? [])
                    },
                    pvt_ltd: {
                        en: @json($config['documents_en']['pvt_ltd'] ?? []),
                        gu: @json($config['documents_gu']['pvt_ltd'] ?? [])
                    },
                    salaried: {
                        en: @json($config['documents_en']['salaried'] ?? []),
                        gu: @json($config['documents_gu']['salaried'] ?? [])
                    }
                };
                var currentDocTab = 'proprietor';

                var sortableInstances = {};

                function renderDocList(type) {
                    var esc = function (s) { return $('<span>').text(s == null ? '' : s).html(); };
                    var html = '';
                    $.each(docs[type].en, function(idx, enVal) {
                        var guVal = docs[type].gu[idx] || '';
                        html +=
                            '<div class="doc-sortable-item shf-reason-row row g-2 align-items-center p-2 rounded" style="background:var(--bg);border:1px solid var(--border);margin:0 0 4px 0;">' +
                            '<div class="col-auto d-flex align-items-center gap-2" style="min-width:0;">' +
                                '<span class="doc-drag-handle" style="cursor:grab;color:#9ca3af;font-size:1rem;padding:0 2px;" title="Drag to reorder">⠿</span>' +
                                '<span class="fw-bold shf-text-xs shf-text-gray-light">' + (idx + 1) + '.</span>' +
                            '</div>' +
                            '<div class="col-12 col-md">' +
                                '<input type="text" name="documents_en[' + type + '][]" value="' + esc(enVal) + '" class="shf-input w-100 small" placeholder="English">' +
                            '</div>' +
                            '<div class="col-12 col-md">' +
                                '<input type="text" name="documents_gu[' + type + '][]" value="' + esc(guVal) + '" class="shf-input w-100 small" placeholder="Gujarati">' +
                            '</div>' +
                            '<div class="col-12 col-md-auto">' +
                                '<button type="button" class="btn-accent-sm removeDocBtn shf-btn-danger w-100 w-md-auto justify-content-center" data-type="' + type + '" data-idx="' + idx + '">' +
                                    '<svg class="shf-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> Remove' +
                                '</button>' +
                            '</div>' +
                            '</div>';
                    });
                    $('#docList-' + type).html(html);
                    initSortable(type);
                }

                function initSortable(type) {
                    var el = document.getElementById('docList-' + type);
                    if (!el) return;
                    if (sortableInstances[type]) sortableInstances[type].destroy();
                    sortableInstances[type] = new Sortable(el, {
                        handle: '.doc-drag-handle',
                        animation: 150,
                        ghostClass: 'doc-sortable-ghost',
                        onEnd: function() {
                            // Read new order from DOM inputs
                            var newEn = [],
                                newGu = [];
                            $('#docList-' + type).find('.doc-sortable-item').each(function() {
                                var inputs = $(this).find('input[type="text"]');
                                newEn.push(inputs.eq(0).val());
                                newGu.push(inputs.eq(1).val());
                            });
                            docs[type].en = newEn;
                            docs[type].gu = newGu;
                            renderDocList(type);
                        }
                    });
                }

                function switchDocTab(type) {
                    currentDocTab = type;
                    $('.doc-sub-tab').css({
                        'border-bottom-color': 'transparent',
                        'color': '#6b7280',
                        'background': 'transparent'
                    });
                    $('.doc-sub-tab[data-doc-type="' + type + '"]').css({
                        'border-bottom-color': '#f15a29',
                        'color': '#f15a29',
                        'background': 'rgba(241,90,41,0.05)'
                    });
                    $('.doc-type-pane').hide();
                    $('#docPane-' + type).show();
                    renderDocList(type);
                }
                // Render ALL doc types on load so form always submits all types
                $.each(['proprietor', 'partnership_llp', 'pvt_ltd', 'salaried'], function(_, t) {
                    renderDocList(t);
                });
                switchDocTab('proprietor');

                $(document).on('click', '.doc-sub-tab', function() {
                    switchDocTab($(this).data('doc-type'));
                });

                $(document).on('click', '.addDocBtn', function() {
                    var type = $(this).data('doc-type');
                    var $pane = $('#docPane-' + type);
                    var enVal = $.trim($pane.find('.newDocEn').val());
                    var guVal = $.trim($pane.find('.newDocGu').val());
                    if (enVal) {
                        docs[type].en.push(enVal);
                        docs[type].gu.push(guVal || enVal);
                        $pane.find('.newDocEn').val('');
                        $pane.find('.newDocGu').val('');
                        renderDocList(type);
                    }
                });

                $(document).on('click', '.removeDocBtn', function() {
                    var type = $(this).data('type');
                    var idx = $(this).data('idx');
                    docs[type].en.splice(idx, 1);
                    docs[type].gu.splice(idx, 1);
                    renderDocList(type);
                });

                // Auto-add any pending document input values on form submit
                $('#documentsForm').on('submit', function() {
                    $.each(['proprietor', 'partnership_llp', 'pvt_ltd', 'salaried'], function(_, type) {
                        var $pane = $('#docPane-' + type);
                        var enVal = $.trim($pane.find('.newDocEn').val());
                        if (enVal) {
                            var guVal = $.trim($pane.find('.newDocGu').val());
                            docs[type].en.push(enVal);
                            docs[type].gu.push(guVal || enVal);
                            renderDocList(type);
                        }
                    });
                });

                // ============================================================
                //  DVR CONTACT TYPES & PURPOSES MANAGER
                // ============================================================
                var dvrContactTypes = @json($config['dvrContactTypes'] ?? []);
                var dvrPurposes = @json($config['dvrPurposes'] ?? []);

                function renderDvrList(items, containerId, inputPrefix) {
                    var esc = function (s) { return $('<span>').text(s == null ? '' : s).html(); };
                    var html = '';
                    $.each(items, function(idx, item) {
                        html += '<div class="shf-reason-row row g-2 align-items-center p-2 rounded" style="background:var(--bg);border:1px solid var(--border);margin:0 0 4px 0;">'
                            + '<input type="hidden" name="' + inputPrefix + '[' + idx + '][key]" value="' + esc(item.key) + '">'
                            + '<div class="col-auto d-flex align-items-center gap-2" style="min-width:0;">'
                            +   '<span class="fw-bold shf-text-xs shf-text-gray-light">' + (idx + 1) + '.</span>'
                            +   '<span class="shf-badge shf-badge-gray shf-text-2xs text-truncate" style="max-width:10rem;" title="' + esc(item.key) + '">' + esc(item.key) + '</span>'
                            + '</div>'
                            + '<div class="col-12 col-md">'
                            +   '<input type="text" name="' + inputPrefix + '[' + idx + '][label_en]" value="' + esc(item.label_en) + '" class="shf-input w-100 small" placeholder="English">'
                            + '</div>'
                            + '<div class="col-12 col-md">'
                            +   '<input type="text" name="' + inputPrefix + '[' + idx + '][label_gu]" value="' + esc(item.label_gu) + '" class="shf-input w-100 small" placeholder="Gujarati">'
                            + '</div>'
                            + '<div class="col-12 col-md-auto">'
                            +   '<button type="button" class="btn-accent-sm shf-btn-danger dvr-remove-btn w-100 w-md-auto justify-content-center" data-list="' + containerId + '" data-idx="' + idx + '">'
                            +     '<svg class="shf-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
                            +   '</button>'
                            + '</div>'
                            + '</div>';
                    });
                    $('#' + containerId).html(html);
                }

                renderDvrList(dvrContactTypes, 'dvrContactTypesList', 'dvrContactTypes');
                renderDvrList(dvrPurposes, 'dvrPurposesList', 'dvrPurposes');

                $('#addContactTypeBtn').on('click', function() {
                    var key = $.trim($('#newContactTypeKey').val());
                    var en = $.trim($('#newContactTypeEn').val());
                    var gu = $.trim($('#newContactTypeGu').val());
                    if (key && en) {
                        key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                        dvrContactTypes.push({ key: key, label_en: en, label_gu: gu || en });
                        renderDvrList(dvrContactTypes, 'dvrContactTypesList', 'dvrContactTypes');
                        $('#newContactTypeKey, #newContactTypeEn, #newContactTypeGu').val('');
                    }
                });

                $('#addPurposeBtn').on('click', function() {
                    var key = $.trim($('#newPurposeKey').val());
                    var en = $.trim($('#newPurposeEn').val());
                    var gu = $.trim($('#newPurposeGu').val());
                    if (key && en) {
                        key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                        dvrPurposes.push({ key: key, label_en: en, label_gu: gu || en });
                        renderDvrList(dvrPurposes, 'dvrPurposesList', 'dvrPurposes');
                        $('#newPurposeKey, #newPurposeEn, #newPurposeGu').val('');
                    }
                });

                $(document).on('click', '.dvr-remove-btn', function() {
                    var listId = $(this).data('list');
                    var idx = $(this).data('idx');
                    if (listId === 'dvrContactTypesList') {
                        dvrContactTypes.splice(idx, 1);
                        renderDvrList(dvrContactTypes, 'dvrContactTypesList', 'dvrContactTypes');
                    } else if (listId === 'dvrPurposesList') {
                        dvrPurposes.splice(idx, 1);
                        renderDvrList(dvrPurposes, 'dvrPurposesList', 'dvrPurposes');
                    }
                });

                // ============================================================
                //  QUOTATION HOLD / CANCEL REASONS MANAGER (with `group`)
                // ============================================================
                var quotationHoldReasons = @json($config['quotationHoldReasons'] ?? []);
                var quotationCancelReasons = @json($config['quotationCancelReasons'] ?? []);

                function escapeHtml(s) { return $('<span>').text(s == null ? '' : s).html(); }

                function uniqueGroups(items) {
                    var seen = {}, out = [];
                    items.forEach(function (it) {
                        var g = (it.group || '').trim();
                        if (g && !seen[g]) { seen[g] = true; out.push(g); }
                    });
                    return out.sort();
                }

                function refreshGroupDatalist(datalistId, items) {
                    var html = '';
                    uniqueGroups(items).forEach(function (g) {
                        html += '<option value="' + escapeHtml(g) + '">';
                    });
                    $('#' + datalistId).html(html);
                }

                function renderReasonList(items, containerId, inputPrefix, datalistId) {
                    // Items keep their original index so form POST preserves order + allows targeted remove.
                    // We group by `group` just for visual rendering — server still stores the flat array order.
                    var groups = {}; // label -> [{idx,item}]
                    items.forEach(function (item, idx) {
                        var g = (item.group || 'Other').trim() || 'Other';
                        (groups[g] = groups[g] || []).push({ idx: idx, item: item });
                    });

                    var groupOrder = Object.keys(groups).sort(function (a, b) {
                        if (a === 'Other') return 1;
                        if (b === 'Other') return -1;
                        return a.localeCompare(b);
                    });

                    var html = '';
                    groupOrder.forEach(function (groupName) {
                        html += '<div class="shf-form-label mt-2 mb-1" style="color:var(--accent);">' + escapeHtml(groupName) + '</div>';
                        groups[groupName].forEach(function (entry) {
                            var item = entry.item, idx = entry.idx;
                            html += '<div class="shf-reason-row row g-2 align-items-center p-2 rounded" style="background:var(--bg);border:1px solid var(--border);margin:0 0 4px 0;">'
                                + '<input type="hidden" name="' + inputPrefix + '[' + idx + '][key]" value="' + escapeHtml(item.key) + '">'
                                + '<div class="col-auto d-flex align-items-center gap-2" style="min-width:0;">'
                                +   '<span class="fw-bold shf-text-xs shf-text-gray-light">' + (idx + 1) + '.</span>'
                                +   '<span class="shf-badge shf-badge-gray shf-text-2xs text-truncate" style="max-width:10rem;" title="' + escapeHtml(item.key) + '">' + escapeHtml(item.key) + '</span>'
                                + '</div>'
                                + '<div class="col-12 col-md-6 col-lg">'
                                +   '<input type="text" name="' + inputPrefix + '[' + idx + '][label_en]" value="' + escapeHtml(item.label_en) + '" class="shf-input w-100 small" placeholder="English">'
                                + '</div>'
                                + '<div class="col-12 col-md-6 col-lg">'
                                +   '<input type="text" name="' + inputPrefix + '[' + idx + '][label_gu]" value="' + escapeHtml(item.label_gu) + '" class="shf-input w-100 small" placeholder="Gujarati">'
                                + '</div>'
                                + '<div class="col-12 col-md-8 col-lg-3">'
                                +   '<input type="text" name="' + inputPrefix + '[' + idx + '][group]" value="' + escapeHtml(item.group || 'Other') + '" class="shf-input w-100 small" placeholder="Group" list="' + datalistId + '">'
                                + '</div>'
                                + '<div class="col-12 col-md-4 col-lg-auto text-md-end">'
                                +   '<button type="button" class="btn-accent-sm shf-btn-danger reason-remove-btn w-100 w-md-auto justify-content-center" data-list="' + containerId + '" data-idx="' + idx + '">'
                                +     '<svg class="shf-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
                                +     ' <span class="d-lg-none">Remove</span>'
                                +   '</button>'
                                + '</div>'
                                + '</div>';
                        });
                    });

                    $('#' + containerId).html(html);
                    refreshGroupDatalist(datalistId, items);
                }

                renderReasonList(quotationHoldReasons, 'quotationHoldReasonsList', 'quotationHoldReasons', 'holdGroupOptions');
                renderReasonList(quotationCancelReasons, 'quotationCancelReasonsList', 'quotationCancelReasons', 'cancelGroupOptions');

                function pushReason(items, $keyIn, $enIn, $guIn, $groupIn) {
                    var key = $.trim($keyIn.val());
                    var en = $.trim($enIn.val());
                    if (!key || !en) return false;
                    var gu = $.trim($guIn.val());
                    var group = $.trim($groupIn.val()) || 'Other';
                    key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                    items.push({ key: key, label_en: en, label_gu: gu || en, group: group });
                    $keyIn.val(''); $enIn.val(''); $guIn.val(''); $groupIn.val('');
                    return true;
                }

                $('#addHoldReasonBtn').on('click', function () {
                    if (pushReason(quotationHoldReasons, $('#newHoldReasonKey'), $('#newHoldReasonEn'), $('#newHoldReasonGu'), $('#newHoldReasonGroup'))) {
                        renderReasonList(quotationHoldReasons, 'quotationHoldReasonsList', 'quotationHoldReasons', 'holdGroupOptions');
                    }
                });

                $('#addCancelReasonBtn').on('click', function () {
                    if (pushReason(quotationCancelReasons, $('#newCancelReasonKey'), $('#newCancelReasonEn'), $('#newCancelReasonGu'), $('#newCancelReasonGroup'))) {
                        renderReasonList(quotationCancelReasons, 'quotationCancelReasonsList', 'quotationCancelReasons', 'cancelGroupOptions');
                    }
                });

                $(document).on('click', '.reason-remove-btn', function () {
                    var listId = $(this).data('list');
                    var idx = $(this).data('idx');
                    if (listId === 'quotationHoldReasonsList') {
                        quotationHoldReasons.splice(idx, 1);
                        renderReasonList(quotationHoldReasons, 'quotationHoldReasonsList', 'quotationHoldReasons', 'holdGroupOptions');
                    } else if (listId === 'quotationCancelReasonsList') {
                        quotationCancelReasons.splice(idx, 1);
                        renderReasonList(quotationCancelReasons, 'quotationCancelReasonsList', 'quotationCancelReasons', 'cancelGroupOptions');
                    }
                });

                // Auto-add pending items on form submit
                $('#quotationHoldReasonsForm').on('submit', function () {
                    if (pushReason(quotationHoldReasons, $('#newHoldReasonKey'), $('#newHoldReasonEn'), $('#newHoldReasonGu'), $('#newHoldReasonGroup'))) {
                        renderReasonList(quotationHoldReasons, 'quotationHoldReasonsList', 'quotationHoldReasons', 'holdGroupOptions');
                    }
                });
                $('#quotationCancelReasonsForm').on('submit', function () {
                    if (pushReason(quotationCancelReasons, $('#newCancelReasonKey'), $('#newCancelReasonEn'), $('#newCancelReasonGu'), $('#newCancelReasonGroup'))) {
                        renderReasonList(quotationCancelReasons, 'quotationCancelReasonsList', 'quotationCancelReasons', 'cancelGroupOptions');
                    }
                });

                // Auto-add pending DVR items on form submit
                $('#dvrContactTypesForm').on('submit', function() {
                    var key = $.trim($('#newContactTypeKey').val());
                    var en = $.trim($('#newContactTypeEn').val());
                    if (key && en) {
                        var gu = $.trim($('#newContactTypeGu').val());
                        key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                        dvrContactTypes.push({ key: key, label_en: en, label_gu: gu || en });
                        renderDvrList(dvrContactTypes, 'dvrContactTypesList', 'dvrContactTypes');
                    }
                });
                $('#dvrPurposesForm').on('submit', function() {
                    var key = $.trim($('#newPurposeKey').val());
                    var en = $.trim($('#newPurposeEn').val());
                    if (key && en) {
                        var gu = $.trim($('#newPurposeGu').val());
                        key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                        dvrPurposes.push({ key: key, label_en: en, label_gu: gu || en });
                        renderDvrList(dvrPurposes, 'dvrPurposesList', 'dvrPurposes');
                    }
                });
            });

            // Reset settings confirmation
            $('#formResetSettings').on('submit', function(e) {
                e.preventDefault();
                var form = this;
                Swal.fire({
                    title: 'Reset ALL settings?',
                    text: 'This will reset all settings to their defaults. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, reset all',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        </script>
