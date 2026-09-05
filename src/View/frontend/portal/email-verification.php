<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Verification Screen Template
 *
 * @var int    $user_id
 * @var string $user_email
 * @var int    $cooldown_remaining
 * @var string $context
 */
$nonce = wp_create_nonce('mm_verify_nonce_' . $user_id);
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="mm-email-verify-wrapper">
    <div class="mm-email-verify-card">
        <div class="mm-email-verify-icon-wrap">
            <svg class="mm-email-verify-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="20" height="16" x="2" y="4" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
        </div>

        <h2 class="mm-email-verify-title"><?php esc_html_e('Verify Your Email Address', 'matchmaker'); ?></h2>
        <p class="mm-email-verify-desc">
            <?php esc_html_e('We sent a 6-digit verification code to:', 'matchmaker'); ?><br>
            <strong class="mm-email-highlight"><?php echo esc_html($user_email); ?></strong>
        </p>

        <div id="mm-verify-alert" class="mm-verify-alert" style="display:none;" role="alert"></div>

        <form id="mm-email-verify-form" class="mm-email-verify-form" onsubmit="return false;">
            <input type="hidden" id="mm_verify_user_id" value="<?php echo (int) $user_id; ?>">
            <input type="hidden" id="mm_verify_nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" id="mm_verify_context" value="<?php echo esc_attr($context); ?>">

            <div class="mm-otp-group">
                <label for="mm-otp-input" class="mm-otp-label"><?php esc_html_e('Enter 6-Digit Code', 'matchmaker'); ?></label>
                <input type="text"
                       id="mm-otp-input"
                       name="verification_code"
                       class="mm-otp-input"
                       maxlength="6"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       autocomplete="one-time-code"
                       placeholder="••••••"
                       autofocus
                       required>
            </div>

            <button type="button" id="mm-verify-submit-btn" class="mm-verify-btn">
                <span class="mm-verify-btn-text"><?php esc_html_e('Verify & Continue', 'matchmaker'); ?></span>
                <span class="mm-verify-btn-spinner" style="display:none;"></span>
            </button>
        </form>

        <div class="mm-resend-wrap">
            <p class="mm-resend-text">
                <?php esc_html_e('Didn\'t receive the code?', 'matchmaker'); ?>
                <button type="button" id="mm-resend-code-btn" class="mm-resend-btn" <?php echo ($cooldown_remaining > 0) ? 'disabled' : ''; ?>>
                    <?php esc_html_e('Resend Code', 'matchmaker'); ?>
                    <span id="mm-resend-timer-box" <?php echo ($cooldown_remaining > 0) ? '' : 'style="display:none;"'; ?>>
                        (<span id="mm-resend-countdown"><?php echo (int) $cooldown_remaining; ?></span>s)
                    </span>
                </button>
            </p>
        </div>
    </div>
</div>

<style>
.mm-email-verify-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 50vh;
    padding: 40px 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}
.mm-email-verify-card {
    background: #ffffff;
    max-width: 480px;
    width: 100%;
    border-radius: 20px;
    padding: 40px 32px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(29, 30, 32, 0.08);
    border: 1px solid rgba(204, 114, 63, 0.18);
}
.mm-email-verify-icon-wrap {
    width: 68px;
    height: 68px;
    margin: 0 auto 20px;
    background: #F8F2ED;
    border: 2px solid #CC723F;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #CC723F;
}
.mm-email-verify-icon {
    width: 32px;
    height: 32px;
}
.mm-email-verify-title {
    font-family: "Cormorant SC", Georgia, serif;
    font-size: 24px;
    font-weight: 700;
    color: #1D1E20;
    margin: 0 0 10px;
    letter-spacing: 0.02em;
}
.mm-email-verify-desc {
    font-size: 15px;
    line-height: 1.6;
    color: #64748b;
    margin: 0 0 24px;
}
.mm-email-highlight {
    color: #1D1E20;
    font-weight: 600;
}
.mm-verify-alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    text-align: center;
}
.mm-verify-alert.error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.mm-verify-alert.success {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}
.mm-otp-group {
    margin-bottom: 24px;
}
.mm-otp-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.mm-otp-input {
    width: 100%;
    max-width: 280px;
    height: 54px;
    margin: 0 auto;
    text-align: center;
    font-family: "Courier New", Courier, monospace, monospace;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: 0.35em;
    padding-left: 0.35em;
    color: #1D1E20;
    background: #F8F2ED;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.mm-otp-input:focus {
    border-color: #CC723F;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(204, 114, 63, 0.15);
}
.mm-verify-btn {
    width: 100%;
    height: 48px;
    background: #1D1E20;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.1s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.mm-verify-btn:hover {
    background: #CC723F;
}
.mm-verify-btn:active {
    transform: scale(0.99);
}
.mm-verify-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.mm-resend-wrap {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}
.mm-resend-text {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}
.mm-resend-btn {
    background: none;
    border: none;
    padding: 0;
    margin-left: 6px;
    color: #CC723F;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    text-decoration: underline;
}
.mm-resend-btn:disabled {
    color: #94a3b8;
    text-decoration: none;
    cursor: not-allowed;
}
.mm-verify-btn-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-top-color: transparent;
    border-radius: 50%;
    animation: mm-spin 0.6s linear infinite;
    display: inline-block;
}
@keyframes mm-spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
(function() {
    var ajaxUrl = "<?php echo esc_js($ajax_url); ?>";
    var form = document.getElementById('mm-email-verify-form');
    var input = document.getElementById('mm-otp-input');
    var submitBtn = document.getElementById('mm-verify-submit-btn');
    var resendBtn = document.getElementById('mm-resend-code-btn');
    var timerBox = document.getElementById('mm-resend-timer-box');
    var countdownEl = document.getElementById('mm-resend-countdown');
    var alertBox = document.getElementById('mm-verify-alert');
    var nonce = document.getElementById('mm_verify_nonce') ? document.getElementById('mm_verify_nonce').value : '';
    var userId = document.getElementById('mm_verify_user_id') ? document.getElementById('mm_verify_user_id').value : '<?php echo (int) $user_id; ?>';

    var cooldownSeconds = <?php echo (int) $cooldown_remaining; ?>;
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

        timerInterval = setInterval(function() {
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
        input.addEventListener('input', function() {
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
        bodyData.append('user_id', userId);
        bodyData.append('code', code);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: bodyData.toString()
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.success) {
                showAlert(res.data && res.data.message ? res.data.message : 'Verified! Reloading...', 'success');
                setTimeout(function() {
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
        .catch(function(err) {
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
        resendBtn.addEventListener('click', function() {
            if (resendBtn.disabled) return;
            resendBtn.disabled = true;
            showAlert('Sending a new code...', 'success');

            var bodyData = new URLSearchParams();
            bodyData.append('action', 'mm_resend_verification_code');
            bodyData.append('nonce', nonce);
            bodyData.append('user_id', userId);

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyData.toString()
            })
            .then(function(res) { return res.json(); })
            .then(function(res) {
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
            .catch(function(err) {
                showAlert('Network error. Please try again.', 'error');
                resendBtn.disabled = false;
            });
        });
    }
})();
</script>
