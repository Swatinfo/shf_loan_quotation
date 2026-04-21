// ============================================================
//  PASSWORD UI — Strength meter, toggles, change password
// ============================================================
var PasswordManager = {

    init: function() {
        this.setupToggles();
        var newPwInput = document.getElementById('newPassword');
        var confirmInput = document.getElementById('confirmPassword');
        if (newPwInput) {
            newPwInput.addEventListener('input', function() {
                PasswordManager.updateStrength();
                PasswordManager.checkConfirmMatch();
            });
        }
        if (confirmInput) {
            confirmInput.addEventListener('input', function() {
                PasswordManager.checkConfirmMatch();
            });
        }
    },

    setupToggles: function() {
        document.querySelectorAll('.toggle-pw').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var input = btn.parentElement.querySelector('input');
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = '\u{1F648}';
                    btn.title = 'Hide password';
                } else {
                    input.type = 'password';
                    btn.textContent = '\u{1F441}';
                    btn.title = 'Show password';
                }
            });
        });
    },

    calculateStrength: function(password) {
        if (!password) return 0;
        var score = 0;
        // Base rules (+1 each)
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        // Bonus for length
        if (password.length >= 12) score += 0.5;
        if (password.length >= 16) score += 0.5;

        // Penalties
        if (/^[a-zA-Z]+$/.test(password)) score -= 1;
        if (/^[0-9]+$/.test(password)) score -= 1;
        if (/(.)\1{2,}/.test(password)) score -= 0.5;
        if (/^(password|123456|qwerty|admin)/i.test(password)) score -= 1;

        return Math.max(0, Math.min(4, Math.round(score)));
    },

    updateStrength: function() {
        var password = document.getElementById('newPassword').value;
        var score = this.calculateStrength(password);

        var levels = [
            { label: 'Weak / નબળો', color: '#c0392b', width: '25%' },
            { label: 'Weak / નબળો', color: '#c0392b', width: '25%' },
            { label: 'Fair / સાધારણ', color: '#f39c12', width: '50%' },
            { label: 'Good / સારો', color: '#3498db', width: '75%' },
            { label: 'Strong / મજબૂત', color: '#27ae60', width: '100%' }
        ];

        var level = levels[score];
        var bar = document.getElementById('strengthBar');
        var label = document.getElementById('strengthLabel');
        if (bar) {
            bar.style.width = password ? level.width : '0';
            bar.style.background = level.color;
        }
        if (label) {
            label.textContent = password ? level.label : '';
            label.style.color = level.color;
        }

        // Update policy checklist
        var checks = {
            'pol-length': password.length >= 8,
            'pol-upper': /[A-Z]/.test(password),
            'pol-lower': /[a-z]/.test(password),
            'pol-digit': /[0-9]/.test(password),
            'pol-special': /[^A-Za-z0-9]/.test(password)
        };
        for (var id in checks) {
            var el = document.getElementById(id);
            if (el) {
                el.classList.toggle('met', checks[id]);
            }
        }
    },

    checkConfirmMatch: function() {
        var newPw = document.getElementById('newPassword').value;
        var confirm = document.getElementById('confirmPassword').value;
        var msg = document.getElementById('confirmMsg');
        if (!msg) return;
        if (!confirm) {
            msg.textContent = '';
            msg.className = 'confirm-msg';
            return;
        }
        if (newPw === confirm) {
            msg.textContent = '✓ Passwords match / પાસવર્ડ મેળ ખાય છે';
            msg.className = 'confirm-msg match';
        } else {
            msg.textContent = '✗ Passwords do not match / પાસવર્ડ મેળ ખાતા નથી';
            msg.className = 'confirm-msg mismatch';
        }
    },

    changePassword: async function() {
        var oldPw = document.getElementById('oldPassword').value;
        var newPw = document.getElementById('newPassword').value;
        var confirmPw = document.getElementById('confirmPassword').value;

        if (!oldPw) {
            showToast('Please enter old password / જૂનો પાસવર્ડ દાખલ કરો', 'error');
            return;
        }
        if (!newPw) {
            showToast('Please enter new password / નવો પાસવર્ડ દાખલ કરો', 'error');
            return;
        }
        if (newPw !== confirmPw) {
            showToast('Passwords do not match / પાસવર્ડ મેળ ખાતા નથી', 'error');
            return;
        }

        var score = this.calculateStrength(newPw);
        if (score < 2) {
            showToast('Password is too weak. Please use a stronger password.', 'error');
            return;
        }

        var btn = document.getElementById('btnChangePassword');
        btn.disabled = true;
        btn.textContent = 'Changing...';

        try {
            var resp = await fetch('api/change-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ oldPassword: oldPw, newPassword: newPw })
            });
            var result = await resp.json();

            if (resp.ok && result.success) {
                showToast('Password changed successfully! / પાસવર્ડ સફળતાપૂર્વક બદલાયો!');
                document.getElementById('oldPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
                this.updateStrength();
                this.checkConfirmMatch();
            } else {
                showToast(result.error || 'Failed to change password', 'error');
            }
        } catch(e) {
            showToast('Failed to connect to server', 'error');
        }

        btn.disabled = false;
        btn.textContent = 'Change Password / પાસવર્ડ બદલો';
    }
};
