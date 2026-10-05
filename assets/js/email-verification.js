/**
 * Matchmaker Email Verification — email-verification.js
 * Handles 6-digit OTP entry, automatic submission, fetch AJAX verification, and resend cooldown countdown.
 */
(function () {
    'use strict';

    function initEmailVerification() {
        var form = document.getElementById('mm-email-verify-form');
        if (!form) return;

        var ajaxUrl = form.getAttribute('data-ajax-url') || '/wp-admin/admin-ajax.php';
        var nonce = form.getAttribute('data-nonce') || '';
        var cooldownSeconds = parseInt(form.getAttribute('data-cooldown') || '0', 10);

        var input = document.getElementById('mm-otp-input');
        var submitBtn = document.getElementById('mm-verify-submit-btn');
        var resendBtn = document.getElementById('mm-resend-code-btn');
        var timerBox = document.getElementById('mm-resend-timer-box');
        var countdownEl = document.getElementById('mm-resend-countdown');
        var alertBox = document.getElementById('mm-verify-alert');
        var timerInterval = null;

        function showAlert(msg, type) {
            if (!alertBox) return;
            if (!msg) {
                alertBox.style.display = 'none';
                alertBox.textContent = '';
                alertBox.className = 'mm-verify-alert';
                return;
            }
            alertBox.textContent = msg;
            alertBox.className = 'mm-verify-alert ' + type;
            alertBox.style.display = 'block';
        }

        function startCooldownTimer(seconds) {
            cooldownSeconds = seconds;
            if (cooldownSeconds <= 0) {
                if (resendBtn) resendBtn.disabled = false;
                if (timerBox) timerBox.style.display = 'none';
                return;
            }

            if (resendBtn) resendBtn.disabled = true;
            if (timerBox) timerBox.style.display = 'inline';
            if (countdownEl) countdownEl.textContent = cooldownSeconds;

            if (timerInterval) clearInterval(timerInterval);

            timerInterval = setInterval(function () {
                cooldownSeconds--;
                if (countdownEl) countdownEl.textContent = cooldownSeconds;

                if (cooldownSeconds <= 0) {
                    clearInterval(timerInterval);
                    if (resendBtn) resendBtn.disabled = false;
                    if (timerBox) timerBox.style.display = 'none';
                }
            }, 1000);
        }

        if (cooldownSeconds > 0) {
            startCooldownTimer(cooldownSeconds);
        }

        // Auto submit when 6 digits are typed
        if (input) {
            input.addEventListener('input', function () {
                var val = input.value.replace(/\D/g, '');
                input.value = val;
                if (val.length === 6) {
                    doVerify();
                }
            });
        }

        function doVerify() {
            if (!input || !submitBtn) return;
            var code = input.value.trim();
            if (code.length !== 6) {
                showAlert('Please enter the full 6-digit code.', 'error');
                return;
            }

            showAlert('', '');
            submitBtn.disabled = true;
            var btnText = submitBtn.querySelector('.mm-verify-btn-text');
            var spinner = submitBtn.querySelector('.mm-verify-btn-spinner');
            if (btnText) btnText.textContent = 'Verifying...';
            if (spinner) spinner.style.display = 'inline-block';

            var bodyData = new URLSearchParams();
            bodyData.append('action', 'mm_verify_email_code');
            bodyData.append('nonce', nonce);
            bodyData.append('code', code);

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyData.toString()
            })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res.success) {
                    showAlert(res.data && res.data.message ? res.data.message : 'Verified! Reloading...', 'success');
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                } else {
                    var verifyError = (res.data && res.data.message) ? res.data.message : (typeof res.data === 'string' ? res.data : 'Invalid code. Please try again.');
                    showAlert(verifyError, 'error');
                    submitBtn.disabled = false;
                    if (btnText) btnText.textContent = 'Verify & Continue';
                    if (spinner) spinner.style.display = 'none';
                    input.focus();
                }
            })
            .catch(function (err) {
                showAlert('Network error or server timeout. Please try again.', 'error');
                submitBtn.disabled = false;
                if (btnText) btnText.textContent = 'Verify & Continue';
                if (spinner) spinner.style.display = 'none';
            });
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', doVerify);
        }

        if (resendBtn) {
            resendBtn.addEventListener('click', function () {
                if (resendBtn.disabled) return;
                resendBtn.disabled = true;
                showAlert('Sending a new code...', 'success');

                var bodyData = new URLSearchParams();
                bodyData.append('action', 'mm_resend_verification_code');
                bodyData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: bodyData.toString()
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res.success) {
                        showAlert(res.data && res.data.message ? res.data.message : 'A new code has been sent.', 'success');
                        startCooldownTimer(res.data && res.data.cooldown_remaining ? res.data.cooldown_remaining : 60);
                    } else {
                        var errorMsg = (res.data && res.data.message) ? res.data.message : (typeof res.data === 'string' ? res.data : 'Could not resend code.');
                        showAlert(errorMsg, 'error');
                        if (res.data && res.data.cooldown_remaining && res.data.cooldown_remaining > 0) {
                            startCooldownTimer(res.data.cooldown_remaining);
                        } else {
                            resendBtn.disabled = false;
                        }
                    }
                })
                .catch(function (err) {
                    showAlert('Network error. Please try again.', 'error');
                    resendBtn.disabled = false;
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmailVerification);
    } else {
        initEmailVerification();
    }
}());
