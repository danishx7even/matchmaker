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
$nonce    = wp_create_nonce('mm_verify_nonce_' . $user_id);
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

        <form id="mm-email-verify-form" class="mm-email-verify-form" onsubmit="return false;"
              data-ajax-url="<?php echo esc_url($ajax_url); ?>"
              data-nonce="<?php echo esc_attr($nonce); ?>"
              data-context="<?php echo esc_attr($context); ?>"
              data-cooldown="<?php echo (int) $cooldown_remaining; ?>">

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
