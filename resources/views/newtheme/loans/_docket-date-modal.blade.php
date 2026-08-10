{{--
    Shared "Change Docket Date" modal (permission-gated docket-date override).

    Included by both the loan view (show) page and the loan stages page so the
    markup, theming and behaviour stay in one place. Each page just renders a
    trigger element carrying:
        data-docket-change-btn
        data-url="<loans.docket-date.update route>"
        data-current="<dd/mm/yyyy or empty>"

    Requires the globally-loaded SweetAlert2, jQuery + Bootstrap Datepicker, and
    (optionally) SHF.loader. Styling is inlined here (not a separate .css file)
    so it is never served stale by the service-worker asset cache.
--}}
<style>
    .shf-swal-field { text-align: left; margin: 0 0 14px; }
    .shf-swal-field:last-child { margin-bottom: 0; }
    .shf-swal-label {
        display: block;
        font-family: 'Jost', sans-serif;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--text-muted, #6b7280);
        margin-bottom: 5px;
    }
    .shf-swal-input {
        width: 100%;
        box-sizing: border-box;
        padding: 8px 12px;
        border: 1.5px solid var(--border, #bcbec0);
        border-radius: 8px;
        font-family: 'Archivo', sans-serif;
        font-size: 0.88rem;
        color: var(--text, #1a1a1a);
        background: var(--white, #fff);
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .shf-swal-input:focus {
        border-color: var(--accent, #f15a29);
        box-shadow: 0 0 0 3px var(--accent-dim, rgba(241, 90, 41, 0.10));
    }
    textarea.shf-swal-input { min-height: 4.5rem; resize: vertical; line-height: 1.5; }
</style>
<script>
    (function () {
        var btns = document.querySelectorAll('[data-docket-change-btn]');
        if (!btns.length) { return; }
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        function isFudgeDate(s) { return /^\d{2}\/\d{2}\/\d{4}$/.test(s); }
        function parseDmy(s) { var p = s.split('/'); return new Date(+p[2], +p[1] - 1, +p[0]); }

        btns.forEach(function (btn) {
            var url = btn.getAttribute('data-url');
            if (!url) { return; }
            btn.addEventListener('click', function () {
                var current = btn.getAttribute('data-current') || '';
                Swal.fire({
                    title: 'Change Docket Date',
                    html:
                        '<div class="shf-swal-field">' +
                            '<label class="shf-swal-label">New docket date</label>' +
                            '<input type="text" id="swalDocketDate" class="shf-swal-input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy" value="' + current + '">' +
                        '</div>' +
                        '<div class="shf-swal-field">' +
                            '<label class="shf-swal-label">Reason for change (required)</label>' +
                            '<textarea id="swalDocketReason" class="shf-swal-input" placeholder="Why is the docket date being changed?"></textarea>' +
                        '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    confirmButtonColor: '#f15a29',
                    allowOutsideClick: false,
                    didOpen: function () {
                        if (window.jQuery && $.fn.datepicker) {
                            $('#swalDocketDate').datepicker({
                                format: 'dd/mm/yyyy',
                                autoclose: true,
                                todayHighlight: true,
                                startDate: '0d', // today or later only
                                container: Swal.getPopup()
                            });
                        }
                    },
                    preConfirm: function () {
                        var d = (document.getElementById('swalDocketDate').value || '').trim();
                        var reason = (document.getElementById('swalDocketReason').value || '').trim();
                        if (!isFudgeDate(d)) { Swal.showValidationMessage('Please pick a valid date.'); return false; }
                        var today = new Date(); today.setHours(0, 0, 0, 0);
                        if (parseDmy(d) < today) { Swal.showValidationMessage('Docket date must be today or later.'); return false; }
                        if (reason.length < 3) { Swal.showValidationMessage('A reason (min 3 characters) is required.'); return false; }
                        return { docket_date: d, reason: reason };
                    }
                }).then(function (res) {
                    if (!res.isConfirmed) { return; }
                    if (window.SHF && SHF.loader) { SHF.loader.begin(); }
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify(res.value)
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (resp) {
                            if (resp.ok && resp.j.success) {
                                Swal.fire({ icon: 'success', title: 'Docket date updated', timer: 1200, showConfirmButton: false })
                                    .then(function () { window.location.reload(); });
                            } else {
                                Swal.fire('Error', resp.j.message || (resp.j.errors ? Object.values(resp.j.errors)[0][0] : 'Failed to update docket date.'), 'error');
                            }
                        }).catch(function () { Swal.fire('Error', 'Request failed.', 'error'); })
                        .finally(function () { if (window.SHF && SHF.loader) { SHF.loader.end(); } });
                });
            });
        });
    })();
</script>
