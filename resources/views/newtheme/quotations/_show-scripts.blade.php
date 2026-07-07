        <script>
            $(function () {
                // Datepicker: future dates only for hold follow-up
                $('#holdModal .shf-datepicker').datepicker({
                    format: 'dd/mm/yyyy',
                    autoclose: true,
                    todayHighlight: true,
                    startDate: new Date(Date.now() + 86400000) // tomorrow
                });

                // Client-side validation
                $('#holdForm').on('submit', function (e) {
                    if (!SHF.validateForm($(this), {
                        reason_key: { required: true, label: 'Reason' },
                        follow_up_date: { required: true, dateFormat: 'dd/mm/yyyy', label: 'Follow-up Date' }
                    })) { e.preventDefault(); }
                });
                $('#cancelForm').on('submit', function (e) {
                    if (!SHF.validateForm($(this), {
                        reason_key: { required: true, label: 'Reason' }
                    })) { e.preventDefault(); }
                });

                // Auto-open modal when navigated from dashboard with ?action=hold or ?action=cancel
                var params = new URLSearchParams(window.location.search);
                var action = params.get('action');
                if (action === 'hold' && $('#holdModal').length) {
                    new bootstrap.Modal(document.getElementById('holdModal')).show();
                } else if (action === 'cancel' && $('#cancelModal').length) {
                    new bootstrap.Modal(document.getElementById('cancelModal')).show();
                }
            });
        </script>
