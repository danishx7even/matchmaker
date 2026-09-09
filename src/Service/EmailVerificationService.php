<?php
declare(strict_types=1);

namespace Matchmaker\Service;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailVerificationService
 *
 * Manages 6-digit email verification codes, 24-hour expiration,
 * 60-second resend cooldown, transactional email dispatch, and gating.
 *
 * @package Matchmaker\Service
 * @since   2.4.0
 */
class EmailVerificationService
{
    private static ?self $instance = null;

    /**
     * In-memory tracking of users who already had a code generated & sent in the current PHP request lifecycle.
     * Prevents duplicate emails when multiple WordPress/PMPro hooks fire simultaneously on registration.
     *
     * @var array<int, int> [user_id => timestamp]
     */
    private static array $sent_in_request = [];

    public const CODE_EXPIRY_SECONDS     = 3600; // 60 minutes (default fallback)
    public const RESEND_COOLDOWN_SECONDS = 60;   // 60 seconds (default fallback)

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // AJAX endpoints (authenticated and non-authenticated with user-bound token)
        add_action('wp_ajax_mm_verify_email_code',        [$this, 'handle_ajax_verify']);
        add_action('wp_ajax_nopriv_mm_verify_email_code', [$this, 'handle_ajax_verify']);
        add_action('wp_ajax_mm_resend_verification_code',        [$this, 'handle_ajax_resend']);
        add_action('wp_ajax_nopriv_mm_resend_verification_code', [$this, 'handle_ajax_resend']);

        // Pending email update AJAX endpoints
        add_action('wp_ajax_mm_verify_pending_email_code', [$this, 'handle_ajax_verify_pending_email']);
        add_action('wp_ajax_mm_resend_pending_email_code', [$this, 'handle_ajax_resend_pending_email']);

        // Auto-send verification code on user registration
        add_action('user_register', [$this, 'on_user_register'], 20, 1);
        add_action('pmpro_after_checkout', [$this, 'on_pmpro_checkout'], 20, 2);

        // Intercept profile email updates on PMPro / WordPress edit profile
        add_action('pmpro_user_profile_update_errors', [$this, 'intercept_pmpro_profile_update'], 10, 3);
        add_action('pmpro_personal_options_update',    [$this, 'intercept_pmpro_personal_options_update'], 5, 1);
        add_filter('wp_pre_insert_user_data',          [$this, 'intercept_wp_pre_insert_user_data'], 10, 4);
        add_action('user_profile_update_errors',       [$this, 'intercept_profile_email_update'], 10, 3);
        add_action('personal_options_update',          [$this, 'intercept_personal_options_update'], 5, 1);

        // PMPro Account Page & Content Notice Hooks
        add_filter('the_content',                             [$this, 'filter_the_content_for_pending_email_notice'], 5, 1);
        add_filter('pmpro_shortcode_account',                 [$this, 'filter_pmpro_shortcode_notice'], 5, 1);
        add_filter('pmpro_shortcode_member_profile_edit',     [$this, 'filter_pmpro_shortcode_notice'], 5, 1);
        add_action('pmpro_account_bullets_top',               [$this, 'render_pending_email_notice_on_pmpro_account']);
        add_action('pmpro_account_preheader',                 [$this, 'render_pending_email_notice_on_pmpro_account']);
        add_action('pmpro_member_profile_edit_after_panel',   [$this, 'render_pending_email_notice_on_pmpro_account']);

        // 48-Hour Unverified Users Purge Cron Worker
        add_action('mm_purge_unverified_users_job', [$this, 'purge_unverified_users']);
        $this->schedule_purge_job();

        // One-time grandfathering migration for existing users
        $this->maybe_grandfather_existing_users();
    }

    /**
     * Schedule daily recurring Action Scheduler action for unverified user purge.
     *
     * @return void
     */
    public function schedule_purge_job(): void
    {
        if (function_exists('as_has_scheduled_action') && function_exists('as_schedule_recurring_action')) {
            if (!as_has_scheduled_action('mm_purge_unverified_users_job')) {
                $interval = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
                as_schedule_recurring_action(time() + 3600, $interval, 'mm_purge_unverified_users_job', [], 'matchmaker');
            }
        }
    }

    /**
     * Get configured verification code expiration in seconds.
     *
     * @return int
     */
    public function get_expiry_seconds(): int
    {
        $hours = (int) get_option('mm_email_verify_expiry_hours', 1);
        if ($hours <= 0) {
            $hours = 1;
        }
        return $hours * 3600;
    }

    /**
     * Get configured resend cooldown in seconds.
     *
     * @return int
     */
    public function get_cooldown_seconds(): int
    {
        return max(5, (int) get_option('mm_email_verify_cooldown_seconds', 60));
    }

    /**
     * Get configured sender email address.
     *
     * @return string
     */
    public function get_sender_email(): string
    {
        $custom = trim((string) get_option('mm_email_verify_from_email', ''));
        if (!empty($custom) && is_email($custom)) {
            return $custom;
        }

        $admin_email = (string) get_option('admin_email');
        $site_url    = function_exists('home_url') ? (string) home_url() : '';
        $host        = (string) (function_exists('wp_parse_url') ? wp_parse_url($site_url, PHP_URL_HOST) : parse_url($site_url, PHP_URL_HOST));
        $host        = preg_replace('/:\d+$/', '', $host);
        $host        = preg_replace('/^www\./', '', $host);

        if (!empty($host) && strpos($host, '.') !== false && !filter_var($host, FILTER_VALIDATE_IP)) {
            return 'no-reply@' . $host;
        } elseif (!empty($admin_email) && is_email($admin_email)) {
            return $admin_email;
        }
        return 'no-reply@arabzawaj.com';
    }

    /**
     * Get configured sender display name.
     *
     * @return string
     */
    public function get_sender_name(): string
    {
        $custom = trim((string) get_option('mm_email_verify_from_name', ''));
        if (!empty($custom)) {
            return $custom;
        }
        $sitename = function_exists('get_bloginfo') ? (string) wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) : 'Arab Zawaj';
        return !empty($sitename) ? $sitename : 'Arab Zawaj Matrimony';
    }

    /**
     * Get configured verification email subject line.
     *
     * @param string $code
     * @return string
     */
    public function get_email_subject(string $code): string
    {
        $default = __('Your Arab Zawaj Verification Code: {code}', 'matchmaker');
        $tpl     = (string) get_option('mm_email_verify_subject', $default);
        if (empty(trim($tpl))) {
            $tpl = $default;
        }
        return str_replace('{code}', $code, $tpl);
    }

    /**
     * Grandfather all existing members registered before this feature into verified status.
     *
     * @return void
     */
    public function maybe_grandfather_existing_users(): void
    {
        if ((int) get_option('mm_email_verification_grandfathered_v1', 0) === 1) {
            return;
        }

        global $wpdb;

        if (empty(get_option('mm_email_verification_enabled_at', 0))) {
            update_option('mm_email_verification_enabled_at', time());
        }

        if (isset($wpdb->users)) {
            // Find all existing user IDs
            $existing_user_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->users}");
            if (is_array($existing_user_ids) && !empty($existing_user_ids)) {
                foreach ($existing_user_ids as $uid) {
                    $user_id = (int) $uid;
                    if ($user_id > 0) {
                        $current_val = get_user_meta($user_id, 'mm_email_verified', true);
                        if ($current_val === '') {
                            update_user_meta($user_id, 'mm_email_verified', 1);
                        }
                    }
                }
            }
        }

        update_option('mm_email_verification_grandfathered_v1', 1);
    }

    /**
     * Check if a user is email-verified.
     * Administrators and pre-existing grandfathered members bypass verification gating.
     *
     * @param int $user_id
     * @return bool
     */
    public function is_user_verified(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (function_exists('user_can') && (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_matchmaker'))) {
            return true;
        }

        $verified = get_user_meta($user_id, 'mm_email_verified', true);

        if ($verified !== '') {
            return (int) $verified === 1;
        }

        // Lazy grandfathering check: if user has an existing pool profile, grandfather them
        $pool = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);
        if (!empty($pool)) {
            update_user_meta($user_id, 'mm_email_verified', 1);
            return true;
        }

        // Check registration date against cutoff
        $user_data = get_userdata($user_id);
        $cutoff    = (int) get_option('mm_email_verification_enabled_at', 0);
        if ($cutoff > 0 && $user_data && !empty($user_data->user_registered) && strtotime((string) $user_data->user_registered) <= $cutoff) {
            update_user_meta($user_id, 'mm_email_verified', 1);
            return true;
        }

        return false;
    }

    /**
     * Mark a user as verified directly (admin/seed utility).
     *
     * @param int $user_id
     * @param bool $verified
     * @return bool
     */
    public function set_user_verified(int $user_id, bool $verified = true): bool
    {
        if ($user_id <= 0) {
            return false;
        }
        update_user_meta($user_id, 'mm_email_verified', $verified ? 1 : 0);
        if ($verified) {
            delete_user_meta($user_id, 'mm_verification_code');
            delete_user_meta($user_id, 'mm_verification_expires_at');
            delete_user_meta($user_id, 'mm_verification_last_sent_at');
        }
        return true;
    }

    /**
     * Reset in-memory request cache (useful for testing & long-running processes).
     *
     * @return void
     */
    public static function reset_in_memory_state(): void
    {
        self::$sent_in_request = [];
    }

    /**
     * Generate and dispatch a 6-digit numeric verification code to user email.
     *
     * @param int  $user_id
     * @param bool $force Bypass cooldown if true.
     * @return array{success: bool, message: string, cooldown_remaining: int}
     */
    public function generate_and_send_code(int $user_id, bool $force = false): array
    {
        if ($user_id <= 0) {
            return [
                'success'            => false,
                'message'            => __('Invalid user account.', 'matchmaker'),
                'cooldown_remaining' => 0,
            ];
        }

        $now            = time();
        $cooldown_limit = $this->get_cooldown_seconds();
        $expiry_limit   = $this->get_expiry_seconds();

        $last_sent = (int) get_user_meta($user_id, 'mm_verification_last_sent_at', true);
        $time_diff = $now - $last_sent;

        // 1. Cooldown check: if not forced (e.g. user clicking Resend) AND within cooldown, reject
        if (!$force && $last_sent > 0 && $time_diff < $cooldown_limit) {
            $remaining = $cooldown_limit - $time_diff;
            return [
                'success'            => false,
                'message'            => sprintf(__('Please wait %d seconds before requesting another code.', 'matchmaker'), $remaining),
                'cooldown_remaining' => $remaining,
            ];
        }

        // 2. Forced hook deduplication: prevent duplicate emails if multiple hooks fire in the same request or < 15s apart
        if ($force && (isset(self::$sent_in_request[$user_id]) || ($last_sent > 0 && $time_diff < 15))) {
            $stored_code = (string) get_user_meta($user_id, 'mm_verification_code', true);
            if (!empty($stored_code)) {
                self::$sent_in_request[$user_id] = $now;
                $user = get_userdata($user_id);
                $user_email = $user ? $user->user_email : '';
                return [
                    'success'            => true,
                    'message'            => sprintf(__('Verification code already sent to %s.', 'matchmaker'), esc_html($user_email)),
                    'cooldown_remaining' => max(0, $cooldown_limit - $time_diff),
                ];
            }
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            \Matchmaker\Repository\MatchRepository::instance()->log_event(
                'email',
                'verification_code_failed',
                sprintf(__('Email Verification Failed: User #%d', 'matchmaker'), $user_id),
                __('User account or email address not found in database.', 'matchmaker'),
                ['user_id' => $user_id],
                null,
                $user_id,
                null,
                'error'
            );

            return [
                'success'            => false,
                'message'            => __('User email not found.', 'matchmaker'),
                'cooldown_remaining' => 0,
            ];
        }

        // Generate 6-digit cryptographically secure random number
        $code = sprintf('%06d', random_int(100000, 999999));
        $now  = time();

        update_user_meta($user_id, 'mm_verification_code', $code);
        update_user_meta($user_id, 'mm_verification_expires_at', $now + $expiry_limit);

        // Send HTML email
        $mail_error = null;
        $sent = $this->send_verification_email($user->user_email, $user->display_name ?: 'Member', $code, $mail_error);

        $repo = \Matchmaker\Repository\MatchRepository::instance();

        if (!$sent) {
            $is_test_mode = $repo->is_test_mode();
            $error_detail = $mail_error ?: __('Mail server rejected dispatch (check SMTP or mail configuration).', 'matchmaker');

            if ($is_test_mode) {
                // In Test Mode, simulate dispatch so developer/admin can test the entire OTP flow locally
                $repo->log_event(
                    'email',
                    'verification_code_sent',
                    sprintf(__('Email Verification Code (Test Mode Simulation): %s', 'matchmaker'), $user->user_email),
                    sprintf(__('Test Mode: Verification code %s generated for %s (local mail sending failed: %s).', 'matchmaker'), $code, $user->user_email, $error_detail),
                    [
                        'user_id'       => $user_id,
                        'recipient'     => $user->user_email,
                        'code'          => $code,
                        'body_html'     => $this->get_email_html($user->display_name ?: 'Member', $code, $user->user_email),
                        'delivery_stat' => 'simulated',
                        'note'          => 'Test Mode active: Simulated delivery because local mail server is offline.',
                        'expires_at'    => gmdate('Y-m-d H:i:s', $now + $expiry_limit),
                    ],
                    null,
                    $user_id,
                    $user->user_email,
                    'warning'
                );

                update_user_meta($user_id, 'mm_verification_last_sent_at', $now);
                self::$sent_in_request[$user_id] = $now;

                return [
                    'success'            => true,
                    'message'            => sprintf(__('Test Mode: Your verification code is %s (Mail server offline: %s).', 'matchmaker'), $code, esc_html($error_detail)),
                    'cooldown_remaining' => $cooldown_limit,
                ];
            }

            $repo->log_event(
                'email',
                'verification_code_failed',
                sprintf(__('Email Verification Code Failed: %s', 'matchmaker'), $user->user_email),
                sprintf(__('Failed to dispatch verification email to %s. Reason: %s', 'matchmaker'), $user->user_email, $error_detail),
                [
                    'user_id'       => $user_id,
                    'recipient'     => $user->user_email,
                    'code'          => $code,
                    'body_html'     => $this->get_email_html($user->display_name ?: 'Member', $code, $user->user_email),
                    'delivery_stat' => 'failed',
                    'error_detail'  => $error_detail,
                ],
                null,
                $user_id,
                $user->user_email,
                'error'
            );

            $user_friendly_err = ($error_detail === 'Could not instantiate mail function.')
                ? __('Could not instantiate mail function. Please configure an SMTP plugin (e.g. WP Mail SMTP or FluentSMTP) or switch the plugin to Test Mode in Settings.', 'matchmaker')
                : $error_detail;

            return [
                'success'            => false,
                'message'            => sprintf(__('Failed to send verification email: %s', 'matchmaker'), esc_html($user_friendly_err)),
                'cooldown_remaining' => 0,
            ];
        }

        // Only enforce resend cooldown upon confirmed dispatch
        update_user_meta($user_id, 'mm_verification_last_sent_at', $now);
        self::$sent_in_request[$user_id] = $now;

        $repo->log_event(
            'email',
            'verification_code_sent',
            sprintf(__('Email Verification Code Sent: %s', 'matchmaker'), $user->user_email),
            sprintf(__('Verification code %s dispatched successfully to %s.', 'matchmaker'), $code, $user->user_email),
            [
                'user_id'       => $user_id,
                'recipient'     => $user->user_email,
                'code'          => $code,
                'body_html'     => $this->get_email_html($user->display_name ?: 'Member', $code, $user->user_email),
                'delivery_stat' => 'delivered',
                'expires_at'    => gmdate('Y-m-d H:i:s', $now + $expiry_limit),
            ],
            null,
            $user_id,
            $user->user_email,
            'success'
        );

        return [
            'success'            => true,
            'message'            => sprintf(__('Verification code sent to %s.', 'matchmaker'), esc_html($user->user_email)),
            'cooldown_remaining' => $cooldown_limit,
        ];
    }

    /**
     * Verify the 6-digit code submitted by user.
     *
     * @param int    $user_id
     * @param string $code
     * @return array{success: bool, message: string}
     */
    public function verify_code(int $user_id, string $code): array
    {
        if ($user_id <= 0) {
            return ['success' => false, 'message' => __('Invalid user session.', 'matchmaker')];
        }

        $user = get_userdata($user_id);
        $user_email = $user ? $user->user_email : '';

        $clean_code = preg_replace('/\D/', '', trim($code));
        if (strlen((string) $clean_code) !== 6) {
            return ['success' => false, 'message' => __('Please enter a valid 6-digit verification code.', 'matchmaker')];
        }

        $stored_code = (string) get_user_meta($user_id, 'mm_verification_code', true);
        $expires_at  = (int) get_user_meta($user_id, 'mm_verification_expires_at', true);
        $repo        = \Matchmaker\Repository\MatchRepository::instance();

        if (empty($stored_code) || time() > $expires_at) {
            $repo->log_event(
                'email',
                'email_verify_failed',
                sprintf(__('Verification Code Expired: %s', 'matchmaker'), $user_email ?: "User #{$user_id}"),
                __('User attempted verification with an expired or non-existent code.', 'matchmaker'),
                [
                    'user_id'   => $user_id,
                    'recipient' => $user_email,
                    'attempted' => $clean_code,
                    'expired'   => true,
                ],
                null,
                $user_id,
                $user_email,
                'warning'
            );

            return [
                'success' => false,
                'message' => __('Your verification code has expired. Please request a new code.', 'matchmaker'),
            ];
        }

        if (!hash_equals($stored_code, (string) $clean_code)) {
            $repo->log_event(
                'email',
                'email_verify_failed',
                sprintf(__('Invalid Verification Code Attempt: %s', 'matchmaker'), $user_email ?: "User #{$user_id}"),
                sprintf(__('User submitted invalid code "%s".', 'matchmaker'), $clean_code),
                [
                    'user_id'   => $user_id,
                    'recipient' => $user_email,
                    'attempted' => $clean_code,
                ],
                null,
                $user_id,
                $user_email,
                'warning'
            );

            return [
                'success' => false,
                'message' => __('Invalid verification code. Please check your email and try again.', 'matchmaker'),
            ];
        }

        // Code matches and is within expiration window!
        update_user_meta($user_id, 'mm_email_verified', 1);
        delete_user_meta($user_id, 'mm_verification_code');
        delete_user_meta($user_id, 'mm_verification_expires_at');
        delete_user_meta($user_id, 'mm_verification_last_sent_at');

        $repo->log_event(
            'email',
            'email_verified',
            sprintf(__('Email Address Verified: %s', 'matchmaker'), $user_email ?: "User #{$user_id}"),
            sprintf(__('User #%d successfully verified email address %s.', 'matchmaker'), $user_id, $user_email),
            [
                'user_id'   => $user_id,
                'recipient' => $user_email,
                'verified'  => true,
            ],
            null,
            $user_id,
            $user_email,
            'success'
        );

        return [
            'success' => true,
            'message' => __('Email address verified successfully!', 'matchmaker'),
        ];
    }

    /**
     * Wrap custom HTML body content inside standard responsive email outer shell.
     * Uses 100% inline CSS and nested tables for universal rendering across Gmail, Outlook, Apple Mail, etc.
     *
     * @param string $body_inner
     * @return string
     */
    private function wrap_email_layout(string $body_inner): string
    {
        return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Arab Zawaj Verification Code</title>
<!--[if mso]>
<style>
body,table,td { font-family: Arial, Helvetica, sans-serif !important; }
</style>
<![endif]-->
<style>
body { margin: 0 !important; padding: 0 !important; -webkit-text-size-adjust: 100% !important; -ms-text-size-adjust: 100% !important; background-color: #F5EFEB !important; }
table { border-collapse: collapse !important; mso-table-lspace: 0pt !important; mso-table-rspace: 0pt !important; }
td { padding: 0; }
img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
@media only screen and (max-width: 600px) {
    .email-container-table { width: 100% !important; max-width: 100% !important; border-radius: 0 !important; }
    .email-body-td { padding: 28px 20px !important; }
    .email-otp-box { font-size: 30px !important; letter-spacing: 8px !important; padding: 18px 12px !important; }
    .email-header-td { padding: 28px 20px 22px !important; }
}
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #F5EFEB; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1D1E20;">
<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#F5EFEB" style="background-color: #F5EFEB; width: 100%; margin: 0; padding: 35px 10px;">
    <tr>
        <td align="center">
            <!-- Email Container (Max Width 580px) -->
            <table role="presentation" class="email-container-table" width="580" border="0" cellspacing="0" cellpadding="0" bgcolor="#FFFFFF" style="max-width: 580px; width: 100%; background-color: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(29,30,32,0.08); border: 1px solid rgba(204,114,63,0.2);">
                
                <!-- Premium Brand Header -->
                <tr>
                    <td class="email-header-td" bgcolor="#1D1E20" align="center" style="background-color: #1D1E20; padding: 34px 30px 26px; border-bottom: 4px solid #CC723F; text-align: center;">
                        <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="margin: 0 auto;">
                            <tr>
                                <td align="center" style="padding-bottom: 6px;">
                                    <span style="display: inline-block; font-size: 18px; color: #CC723F; line-height: 1;">✦</span>
                                </td>
                            </tr>
                            <tr>
                                <td align="center">
                                    <h1 style="font-family: \'Marcellus\', Georgia, \'Times New Roman\', serif; font-size: 24px; font-weight: 700; color: #FFFFFF; margin: 0; letter-spacing: 0.15em; text-transform: uppercase; line-height: 1.2;">ARAB ZAWAJ</h1>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top: 6px;">
                                    <span style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 11px; font-weight: 600; color: #CC723F; letter-spacing: 0.22em; text-transform: uppercase;">PREMIUM ARAB MATCHMAKING</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Email Body Content -->
                <tr>
                    <td class="email-body-td" style="padding: 38px 34px 30px; font-size: 15px; line-height: 1.65; color: #4B5563; text-align: left;">
                        ' . $body_inner . '
                    </td>
                </tr>

                <!-- Email Footer -->
                <tr>
                    <td bgcolor="#FDFBF9" align="center" style="background-color: #FDFBF9; padding: 24px 30px; border-top: 1px solid #F1ECE6; text-align: center;">
                        <p style="font-family: Georgia, serif; font-style: italic; font-size: 13px; color: #8C532B; margin: 0 0 8px; line-height: 1.4;">Thank you for being part of Arab Zawaj</p>
                        <p style="font-size: 12px; color: #9CA3AF; margin: 0; line-height: 1.5;">&copy; ' . gmdate('Y') . ' Arab Zawaj Matrimony. All rights reserved.</p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>';
    }

    /**
     * Get branded HTML email template for verification code.
     *
     * @param string $display_name
     * @param string $code
     * @param string $user_email
     * @return string
     */
    public function get_email_html(string $display_name, string $code, string $user_email = ''): string
    {
        $custom_template = (string) get_option('mm_email_verify_template', '');
        $expiry_hours    = (string) max(1, (int) get_option('mm_email_verify_expiry_hours', 1));
        $sitename        = $this->get_sender_name();

        if (!empty(trim($custom_template))) {
            $body_content = str_replace(
                ['{code}', '{user_name}', '{user_email}', '{site_name}', '{expiry_hours}'],
                [$code, $display_name ?: 'Member', $user_email, $sitename, $expiry_hours],
                $custom_template
            );
            if (str_contains($body_content, '<html') || str_contains($body_content, '<body')) {
                return $body_content;
            }
            return $this->wrap_email_layout($body_content);
        }

        $default_inner = '
            <div style="margin-bottom: 22px;">
                <h2 style="font-family: \'Marcellus\', Georgia, serif; font-size: 21px; font-weight: 700; color: #1D1E20; margin: 0 0 10px; line-height: 1.3;">Hello, ' . esc_html($display_name ?: 'Member') . '!</h2>
                <p style="margin: 0; font-size: 15px; color: #4B5563; line-height: 1.6;">Thank you for joining Arab Zawaj. To protect the integrity and security of our matrimony community, please enter the one-time verification code below to confirm your email address:</p>
            </div>

            <!-- OTP Code Card -->
            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 28px 0 24px;">
                <tr>
                    <td align="center" bgcolor="#FAF5F0" style="background-color: #FAF5F0; border: 2px dashed #CC723F; border-radius: 12px; padding: 24px 16px; text-align: center;">
                        <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #8C532B; margin-bottom: 8px;">YOUR VERIFICATION CODE</div>
                        <div class="email-otp-box" style="font-family: \'Courier New\', Courier, monospace; font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #1D1E20; text-indent: 12px; margin: 4px 0 8px; line-height: 1;">' . esc_html($code) . '</div>
                        <div style="font-size: 12px; color: #78716C; font-weight: 500;">⏱ Valid for <strong>' . (((int) $expiry_hours === 1) ? '60 minutes' : ($expiry_hours . ' hours')) . '</strong></div>
                    </td>
                </tr>
            </table>

            <!-- Security Notice Box -->
            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 0 0 24px;">
                <tr>
                    <td bgcolor="#FFFDFB" style="background-color: #FFFDFB; border-left: 3px solid #CC723F; border-radius: 0 8px 8px 0; padding: 14px 18px;">
                        <p style="margin: 0; font-size: 13px; color: #6B7280; line-height: 1.5;"><strong>Security Reminder:</strong> Never share this 6-digit code with anyone. Arab Zawaj representatives will never ask for your verification code.</p>
                    </td>
                </tr>
            </table>

            <p style="margin: 0 0 24px; font-size: 14px; color: #6B7280; line-height: 1.6;">If you did not create an account on Arab Zawaj, you can safely disregard this email.</p>

            <p style="margin: 0; font-size: 14px; color: #4B5563; line-height: 1.5;">Warm regards,<br><strong style="color: #1D1E20;">Arab Zawaj Matchmaking Team</strong></p>
        ';

        return $this->wrap_email_layout($default_inner);
    }

    /**
     * Send branded HTML verification email.
     *
     * @param string      $to_email
     * @param string      $display_name
     * @param string      $code
     * @param string|null $mail_error Captured error message if wp_mail fails.
     * @return bool
     */
    public function send_verification_email(string $to_email, string $display_name, string $code, ?string &$mail_error = null): bool
    {
        $subject = $this->get_email_subject($code);
        $html    = $this->get_email_html($display_name, $code, $to_email);

        $from_email = $this->get_sender_email();
        $from_name  = $this->get_sender_name();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $from_email_filter   = static fn(): string => $from_email;
        $from_name_filter    = static fn(): string => $from_name;
        $content_type_filter = static fn(): string => 'text/html';

        add_filter('wp_mail_from', $from_email_filter, 999);
        add_filter('wp_mail_from_name', $from_name_filter, 999);
        add_filter('wp_mail_content_type', $content_type_filter, 999);

        $mail_error = null;
        $error_listener = static function ($wp_error) use (&$mail_error): void {
            if (is_wp_error($wp_error)) {
                $mail_error = $wp_error->get_error_message();
            } elseif (is_string($wp_error)) {
                $mail_error = $wp_error;
            }
        };

        add_action('wp_mail_failed', $error_listener, 10, 1);

        $sent = (bool) wp_mail($to_email, $subject, $html, $headers);

        remove_action('wp_mail_failed', $error_listener, 10);
        remove_filter('wp_mail_content_type', $content_type_filter, 999);
        remove_filter('wp_mail_from_name', $from_name_filter, 999);
        remove_filter('wp_mail_from', $from_email_filter, 999);

        return $sent;
    }

    /**
     * Render the standalone email verification view.
     *
     * @param int    $user_id
     * @param string $context 'portal' | 'form'
     * @return string
     */
    public function render_verification_screen(int $user_id, string $context = 'portal'): string
    {
        $user = get_userdata($user_id);
        $user_email = $user ? $user->user_email : '';

        // Auto-send code if none currently exists or expired
        $stored_code = (string) get_user_meta($user_id, 'mm_verification_code', true);
        $expires_at  = (int) get_user_meta($user_id, 'mm_verification_expires_at', true);
        if (empty($stored_code) || time() > $expires_at) {
            $this->generate_and_send_code($user_id, true);
        }

        $cooldown_limit     = $this->get_cooldown_seconds();
        $last_sent          = (int) get_user_meta($user_id, 'mm_verification_last_sent_at', true);
        $time_diff          = time() - $last_sent;
        $cooldown_remaining = ($time_diff < $cooldown_limit) ? ($cooldown_limit - $time_diff) : 0;

        $view_path = (defined('MM_SRC_PATH') ? MM_SRC_PATH : dirname(__DIR__) . '/') . 'View/frontend/portal/email-verification.php';

        ob_start();
        include $view_path;
        return (string) ob_get_clean();
    }

    /**
     * AJAX handler for code verification.
     *
     * @return void
     */
    public function handle_ajax_verify(): void
    {
        $nonce   = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $user_id = get_current_user_id();

        if ($user_id <= 0 && !empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        }

        $valid_nonce = wp_verify_nonce($nonce, 'mm_verify_nonce')
            || ($user_id > 0 && wp_verify_nonce($nonce, 'mm_verify_nonce_' . $user_id));

        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'matchmaker')]);
        }

        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('User session not found. Please log in again.', 'matchmaker')]);
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash((string) $_POST['code'])) : '';
        $res  = $this->verify_code($user_id, $code);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * AJAX handler for resending verification code.
     *
     * @return void
     */
    public function handle_ajax_resend(): void
    {
        $nonce   = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $user_id = get_current_user_id();

        if ($user_id <= 0 && !empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        }

        $valid_nonce = wp_verify_nonce($nonce, 'mm_verify_nonce')
            || ($user_id > 0 && wp_verify_nonce($nonce, 'mm_verify_nonce_' . $user_id));

        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'matchmaker')]);
        }

        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('User session not found. Please log in again.', 'matchmaker')]);
        }

        $res = $this->generate_and_send_code($user_id, false);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * Hook on user registration.
     *
     * @param int $user_id
     * @return void
     */
    public function on_user_register(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }
        $this->generate_and_send_code($user_id, true);
    }

    /**
     * Hook on PMPro checkout.
     *
     * @param int   $user_id
     * @param mixed $morder
     * @return void
     */
    public function on_pmpro_checkout(int $user_id, mixed $morder = null): void
    {
        if ($user_id <= 0 || $this->is_user_verified($user_id)) {
            return;
        }
        $this->generate_and_send_code($user_id, true);
    }

    /**
     * Purge unverified user accounts older than 48 hours and cancel any associated PMPro membership.
     * Triggered daily by Action Scheduler recurring action `mm_purge_unverified_users_job`.
     *
     * @return int Number of purged unverified accounts.
     */
    public function purge_unverified_users(): int
    {
        global $wpdb;
        $cutoff_time = time() - (48 * 3600);
        $cutoff_date = gmdate('Y-m-d H:i:s', $cutoff_time);

        if (!isset($wpdb->users)) {
            return 0;
        }

        // Find users registered <= 48 hours ago who are not verified
        $sql = "
            SELECT u.ID, u.user_email, u.user_registered 
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON (u.ID = um.user_id AND um.meta_key = 'mm_email_verified')
            WHERE u.user_registered <= %s
              AND (um.meta_value IS NULL OR um.meta_value = '' OR um.meta_value = '0')
        ";

        $users_to_purge = $wpdb->get_results($wpdb->prepare($sql, $cutoff_date));
        if (empty($users_to_purge) || !is_array($users_to_purge)) {
            return 0;
        }

        if (!function_exists('wp_delete_user')) {
            $user_admin_file = (defined('ABSPATH') ? ABSPATH : '') . 'wp-admin/includes/user.php';
            if (file_exists($user_admin_file)) {
                require_once $user_admin_file;
            }
        }

        $purged_count = 0;
        $repo = \Matchmaker\Repository\MatchRepository::instance();

        foreach ($users_to_purge as $u) {
            $uid = (int) $u->ID;
            if ($uid <= 0) {
                continue;
            }

            // Safety check: Never delete administrators or matchmaker admins
            if (function_exists('user_can') && (user_can($uid, 'manage_options') || user_can($uid, 'manage_matchmaker'))) {
                continue;
            }

            // Cancel PMPro membership if active
            if (function_exists('pmpro_changeMembershipLevel')) {
                pmpro_changeMembershipLevel(0, $uid);
            }

            // Remove from matchmaking pool
            $repo->delete_pool_user($uid);

            // Log purge event
            $repo->log_event(
                'system',
                'user_purged_unverified_48h',
                sprintf(__('Unverified User Auto-Purged (48h): %s', 'matchmaker'), $u->user_email),
                sprintf(__('User #%d (%s) registered at %s was automatically deleted after 48 hours without email verification.', 'matchmaker'), $uid, $u->user_email, (string) $u->user_registered),
                ['user_id' => $uid, 'user_registered' => (string) $u->user_registered],
                null,
                $uid,
                $u->user_email,
                'warning'
            );

            // Delete user account
            if (function_exists('wp_delete_user')) {
                wp_delete_user($uid);
                $purged_count++;
            }
        }

        return $purged_count;
    }

    /**
     * Intercept PMPro frontend member profile edit errors and email change.
     * PMPro passes ($errors, $update, $user) where $errors is an array or WP_Error.
     *
     * @param mixed     $errors
     * @param bool      $update
     * @param \stdClass $user
     * @return void
     */
    public function intercept_pmpro_profile_update(mixed &$errors, bool $update, \stdClass &$user): void
    {
        if (!$update || empty($user->ID)) {
            return;
        }

        $user_id = (int) $user->ID;
        $current_user = get_userdata($user_id);
        if (!$current_user || empty($current_user->user_email)) {
            return;
        }

        // Allow administrators editing other users in wp-admin to update directly
        if (function_exists('is_admin') && is_admin() && function_exists('current_user_can') && current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            return;
        }

        $submitted_email = '';
        if (isset($user->user_email)) {
            $submitted_email = sanitize_email((string) $user->user_email);
        } elseif (isset($_POST['user_email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['user_email']));
        } elseif (isset($_POST['email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['email']));
        }

        if (empty($submitted_email) || !is_email($submitted_email)) {
            return;
        }

        $current_email = (string) $current_user->user_email;

        // If email has not changed, do nothing
        if (strtolower(trim($submitted_email)) === strtolower(trim($current_email))) {
            return;
        }

        // Check if email already belongs to another user
        $existing_user_id = email_exists($submitted_email);
        if ($existing_user_id && (int) $existing_user_id !== $user_id) {
            $msg = __('This email is already registered, please choose another one.', 'paid-memberships-pro');
            if (is_array($errors)) {
                $errors[] = $msg;
            } elseif (is_object($errors) && method_exists($errors, 'add')) {
                $errors->add('email_exists', $msg);
            }
            return;
        }

        // Save new email as unverified in usermeta
        update_user_meta($user_id, 'mm_pending_new_email', $submitted_email);

        // Send 6-digit verification code to the NEW email address
        $this->generate_and_send_pending_code($user_id, $submitted_email, true);

        // Keep current email intact in $user object and $_POST so PMPro and WP do not overwrite wp_users.user_email
        $user->user_email = $current_email;
        if (isset($_POST['user_email'])) {
            $_POST['user_email'] = $current_email;
        }
        if (isset($_POST['email'])) {
            $_POST['email'] = $current_email;
        }
    }

    /**
     * Intercept PMPro frontend personal_options_update.
     *
     * @param int $user_id
     * @return void
     */
    public function intercept_pmpro_personal_options_update(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        if (function_exists('is_admin') && is_admin() && function_exists('current_user_can') && current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            return;
        }

        $current_user = get_userdata($user_id);
        if (!$current_user || empty($current_user->user_email)) {
            return;
        }

        $submitted_email = '';
        if (isset($_POST['user_email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['user_email']));
        } elseif (isset($_POST['email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['email']));
        }

        if (empty($submitted_email) || !is_email($submitted_email)) {
            return;
        }

        $current_email = (string) $current_user->user_email;
        if (strtolower(trim($submitted_email)) !== strtolower(trim($current_email))) {
            update_user_meta($user_id, 'mm_pending_new_email', $submitted_email);
            $this->generate_and_send_pending_code($user_id, $submitted_email, true);
            $_POST['user_email'] = $current_email;
            $_POST['email'] = $current_email;
        }
    }

    /**
     * Universal WordPress core filter: intercept any user update before inserting into wp_users.
     *
     * @param array    $data     Array of data to be updated/inserted in wp_users.
     * @param bool     $update   Whether this is an existing user update.
     * @param int|null $user_id  User ID being updated.
     * @param array    $userdata Raw user data passed to wp_insert_user.
     * @return array Modified data array.
     */
    public function intercept_wp_pre_insert_user_data(array $data, bool $update, ?int $user_id, array $userdata): array
    {
        if (!$update || empty($user_id) || $user_id <= 0) {
            return $data;
        }

        // If this update is coming from our own verify_pending_email_code(), allow it through!
        if (!empty($GLOBALS['__mm_updating_verified_email'])) {
            return $data;
        }

        // Allow administrators editing other users in wp-admin
        if (function_exists('is_admin') && is_admin() && function_exists('current_user_can') && current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            return $data;
        }

        $current_user = get_userdata($user_id);
        if (!$current_user || empty($current_user->user_email)) {
            return $data;
        }

        $current_email = (string) $current_user->user_email;
        $new_email     = isset($data['user_email']) ? sanitize_email((string) $data['user_email']) : '';

        if (!empty($new_email) && is_email($new_email) && strtolower(trim($new_email)) !== strtolower(trim($current_email))) {
            // Check if email already belongs to another user
            $existing_user_id = email_exists($new_email);
            if ($existing_user_id && (int) $existing_user_id !== $user_id) {
                return $data;
            }

            // Intercept! Save new email as pending and dispatch verification code
            update_user_meta($user_id, 'mm_pending_new_email', $new_email);
            $this->generate_and_send_pending_code($user_id, $new_email, true);

            // Revert email in $data so wp_users is NOT updated yet
            $data['user_email'] = $current_email;
        }

        return $data;
    }

    /**
     * Filter the_content to prepend the pending email notice banner strictly on PMPro Account and Edit Profile pages.
     *
     * @param string $content
     * @return string
     */
    public function filter_the_content_for_pending_email_notice(string $content): string
    {
        if (!is_user_logged_in()) {
            return $content;
        }

        $user_id = get_current_user_id();
        $pending_email = (string) get_user_meta($user_id, 'mm_pending_new_email', true);
        if (empty($pending_email)) {
            return $content;
        }

        // Never render notice on dashboard or questionnaire/form wizard pages
        $is_dashboard_or_form = str_contains($content, '[az_profile')
            || str_contains($content, '[matchmaker_member_portal')
            || str_contains($content, '[matchmaking_form')
            || str_contains($content, '[matchmaking_field');

        if ($is_dashboard_or_form) {
            return $content;
        }

        // Check if page contains PMPro account or profile edit shortcodes, or is a PMPro account/edit profile page
        $is_pmpro_account_page = function_exists('pmpro_is_pmpro_page') && (pmpro_is_pmpro_page('account') || pmpro_is_pmpro_page('member_profile_edit'));
        $has_pmpro_shortcode   = str_contains($content, '[pmpro_account')
            || str_contains($content, '[pmpro_member_profile_edit');

        if ($is_pmpro_account_page || $has_pmpro_shortcode) {
            // Avoid duplicate rendering
            if (!str_contains($content, 'mm-pending-email-notice')) {
                $notice_html = $this->render_pending_email_notice($user_id);
                return $notice_html . $content;
            }
        }

        return $content;
    }

    /**
     * Filter PMPro account / member profile edit shortcode output.
     *
     * @param string $content
     * @return string
     */
    public function filter_pmpro_shortcode_notice(string $content): string
    {
        if (!is_user_logged_in()) {
            return $content;
        }

        $user_id = get_current_user_id();
        $pending_email = (string) get_user_meta($user_id, 'mm_pending_new_email', true);
        if (empty($pending_email) || str_contains($content, 'mm-pending-email-notice')) {
            return $content;
        }

        $notice_html = $this->render_pending_email_notice($user_id);
        return $notice_html . $content;
    }

    /**
     * Intercept profile email updates on WordPress edit profile.
     * Prevents immediate overwrite in wp_users, storing pending new email in usermeta and sending OTP.
     *
     * @param \WP_Error $errors
     * @param bool      $update
     * @param \stdClass $user
     * @return void
     */
    public function intercept_profile_email_update(\WP_Error &$errors, bool $update, \stdClass $user): void
    {
        if (!$update || empty($user->ID)) {
            return;
        }

        $user_id = (int) $user->ID;
        $current_user = get_userdata($user_id);
        if (!$current_user || empty($current_user->user_email)) {
            return;
        }

        // Allow administrators editing other users in wp-admin to update directly
        if (function_exists('is_admin') && is_admin() && function_exists('current_user_can') && current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            return;
        }

        $submitted_email = '';
        if (isset($_POST['email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['email']));
        } elseif (isset($_POST['user_email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['user_email']));
        } elseif (isset($user->user_email)) {
            $submitted_email = sanitize_email((string) $user->user_email);
        }

        if (empty($submitted_email) || !is_email($submitted_email)) {
            return;
        }

        $current_email = (string) $current_user->user_email;

        // If email hasn't changed, do nothing
        if (strtolower(trim($submitted_email)) === strtolower(trim($current_email))) {
            return;
        }

        // Check if email already belongs to another user
        $existing_user_id = email_exists($submitted_email);
        if ($existing_user_id && (int) $existing_user_id !== $user_id) {
            $errors->add('email_exists', __('That email address is already registered. Please enter a different one.', 'matchmaker'));
            return;
        }

        // Save new email as unverified in usermeta
        update_user_meta($user_id, 'mm_pending_new_email', $submitted_email);

        // Send 6-digit verification code to the NEW email address
        $this->generate_and_send_pending_code($user_id, $submitted_email, true);

        // Keep current email intact in $user object and $_POST so WordPress doesn't overwrite wp_users.user_email
        $user->user_email = $current_email;
        if (isset($_POST['email'])) {
            $_POST['email'] = $current_email;
        }
        if (isset($_POST['user_email'])) {
            $_POST['user_email'] = $current_email;
        }
    }

    /**
     * Intercept personal_options_update as a secondary guard for profile saves.
     *
     * @param int $user_id
     * @return void
     */
    public function intercept_personal_options_update(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        if (function_exists('is_admin') && is_admin() && function_exists('current_user_can') && current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            return;
        }

        $current_user = get_userdata($user_id);
        if (!$current_user || empty($current_user->user_email)) {
            return;
        }

        $submitted_email = '';
        if (isset($_POST['email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['email']));
        } elseif (isset($_POST['user_email'])) {
            $submitted_email = sanitize_email((string) wp_unslash($_POST['user_email']));
        }

        if (empty($submitted_email) || !is_email($submitted_email)) {
            return;
        }

        $current_email = (string) $current_user->user_email;
        if (strtolower(trim($submitted_email)) !== strtolower(trim($current_email))) {
            update_user_meta($user_id, 'mm_pending_new_email', $submitted_email);
            $this->generate_and_send_pending_code($user_id, $submitted_email, true);
            $_POST['email'] = $current_email;
            $_POST['user_email'] = $current_email;
        }
    }

    /**
     * Generate and dispatch a 6-digit verification code to the pending new email address.
     *
     * @param int    $user_id
     * @param string $new_email
     * @param bool   $force Bypass cooldown if true.
     * @return array{success: bool, message: string, cooldown_remaining: int}
     */
    public function generate_and_send_pending_code(int $user_id, string $new_email, bool $force = false): array
    {
        if ($user_id <= 0 || empty($new_email) || !is_email($new_email)) {
            return [
                'success'            => false,
                'message'            => __('Invalid email address.', 'matchmaker'),
                'cooldown_remaining' => 0,
            ];
        }

        $now            = time();
        $cooldown_limit = $this->get_cooldown_seconds();
        $expiry_limit   = $this->get_expiry_seconds();

        $last_sent = (int) get_user_meta($user_id, 'mm_pending_verification_last_sent_at', true);
        $time_diff = $now - $last_sent;

        if (!$force && $last_sent > 0 && $time_diff < $cooldown_limit) {
            $remaining = $cooldown_limit - $time_diff;
            return [
                'success'            => false,
                'message'            => sprintf(__('Please wait %d seconds before requesting another code.', 'matchmaker'), $remaining),
                'cooldown_remaining' => $remaining,
            ];
        }

        // Generate 6-digit cryptographically secure random number
        $code = sprintf('%06d', random_int(100000, 999999));

        update_user_meta($user_id, 'mm_pending_new_email', $new_email);
        update_user_meta($user_id, 'mm_pending_verification_code', $code);
        update_user_meta($user_id, 'mm_pending_verification_expires_at', $now + $expiry_limit);
        update_user_meta($user_id, 'mm_pending_verification_last_sent_at', $now);

        $user = get_userdata($user_id);
        $display_name = $user ? ($user->display_name ?: 'Member') : 'Member';

        // Send HTML email to new email address
        $mail_error = null;
        $sent = $this->send_pending_email_verification($new_email, $display_name, $code, $mail_error);

        $repo = \Matchmaker\Repository\MatchRepository::instance();

        if (!$sent) {
            $is_test_mode = $repo->is_test_mode();
            $error_detail = $mail_error ?: __('Mail server rejected dispatch.', 'matchmaker');

            if ($is_test_mode) {
                $repo->log_event(
                    'email',
                    'pending_email_code_sent',
                    sprintf(__('Pending Email Verification Code (Test Mode): %s', 'matchmaker'), $new_email),
                    sprintf(__('Test Mode: Verification code %s generated for pending email change to %s.', 'matchmaker'), $code, $new_email),
                    [
                        'user_id'       => $user_id,
                        'new_email'     => $new_email,
                        'code'          => $code,
                        'delivery_stat' => 'simulated',
                        'expires_at'    => gmdate('Y-m-d H:i:s', $now + $expiry_limit),
                    ],
                    null,
                    $user_id,
                    $new_email,
                    'warning'
                );

                return [
                    'success'            => true,
                    'message'            => sprintf(__('Test Mode: Verification code is %s.', 'matchmaker'), $code),
                    'cooldown_remaining' => $cooldown_limit,
                ];
            }

            $repo->log_event(
                'email',
                'pending_email_code_failed',
                sprintf(__('Pending Email Verification Code Failed: %s', 'matchmaker'), $new_email),
                sprintf(__('Failed to dispatch verification email to %s: %s', 'matchmaker'), $new_email, $error_detail),
                ['user_id' => $user_id, 'new_email' => $new_email, 'error_detail' => $error_detail],
                null,
                $user_id,
                $new_email,
                'error'
            );

            return [
                'success'            => false,
                'message'            => sprintf(__('Failed to send verification email: %s', 'matchmaker'), esc_html($error_detail)),
                'cooldown_remaining' => 0,
            ];
        }

        $repo->log_event(
            'email',
            'pending_email_code_sent',
            sprintf(__('Pending Email Verification Code Sent: %s', 'matchmaker'), $new_email),
            sprintf(__('Verification code dispatched successfully to new email %s.', 'matchmaker'), $new_email),
            [
                'user_id'       => $user_id,
                'new_email'     => $new_email,
                'code'          => $code,
                'delivery_stat' => 'delivered',
                'expires_at'    => gmdate('Y-m-d H:i:s', $now + $expiry_limit),
            ],
            null,
            $user_id,
            $new_email,
            'success'
        );

        return [
            'success'            => true,
            'message'            => sprintf(__('Verification code sent to %s.', 'matchmaker'), esc_html($new_email)),
            'cooldown_remaining' => $cooldown_limit,
        ];
    }

    /**
     * Send branded verification email for pending email update.
     *
     * @param string      $to_email
     * @param string      $display_name
     * @param string      $code
     * @param string|null $mail_error
     * @return bool
     */
    public function send_pending_email_verification(string $to_email, string $display_name, string $code, ?string &$mail_error = null): bool
    {
        $custom_subject = (string) get_option('mm_email_verify_update_subject', '');
        $subject = !empty(trim($custom_subject))
            ? str_replace('{code}', $code, $custom_subject)
            : sprintf(__('Verify Your New Arab Zawaj Email: %s', 'matchmaker'), $code);
        
        $custom_template = (string) get_option('mm_email_verify_update_template', '');
        $sitename        = $this->get_sender_name();
        $expiry_hours    = (string) max(1, (int) get_option('mm_email_verify_expiry_hours', 1));

        if (!empty(trim($custom_template))) {
            $body_content = str_replace(
                ['{code}', '{user_name}', '{new_email}', '{user_email}', '{site_name}', '{expiry_hours}'],
                [$code, $display_name ?: 'Member', $to_email, $to_email, $sitename, $expiry_hours],
                $custom_template
            );
            if (str_contains($body_content, '<html') || str_contains($body_content, '<body')) {
                $html = $body_content;
            } else {
                $html = $this->wrap_email_layout($body_content);
            }
        } else {
            $inner = '
                <div style="margin-bottom: 22px;">
                    <h2 style="font-family: \'Marcellus\', Georgia, serif; font-size: 21px; font-weight: 700; color: #1D1E20; margin: 0 0 10px; line-height: 1.3;">Hello, ' . esc_html($display_name ?: 'Member') . '!</h2>
                    <p style="margin: 0; font-size: 15px; color: #4B5563; line-height: 1.6;">You recently requested to update your account email address on Arab Zawaj to <strong>' . esc_html($to_email) . '</strong>. Please enter the verification code below to confirm this change:</p>
                </div>

                <!-- OTP Code Card -->
                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 28px 0 24px;">
                    <tr>
                        <td align="center" bgcolor="#FAF5F0" style="background-color: #FAF5F0; border: 2px dashed #CC723F; border-radius: 12px; padding: 24px 16px; text-align: center;">
                            <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #8C532B; margin-bottom: 8px;">EMAIL UPDATE VERIFICATION CODE</div>
                            <div class="email-otp-box" style="font-family: \'Courier New\', Courier, monospace; font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #1D1E20; text-indent: 12px; margin: 4px 0 8px; line-height: 1;">' . esc_html($code) . '</div>
                            <div style="font-size: 12px; color: #78716C; font-weight: 500;">⏱ Valid for <strong>' . (((int) $expiry_hours === 1) ? '60 minutes' : ($expiry_hours . ' hours')) . '</strong></div>
                        </td>
                    </tr>
                </table>

                <!-- Security Notice Box -->
                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 0 0 24px;">
                    <tr>
                        <td bgcolor="#FFFDFB" style="background-color: #FFFDFB; border-left: 3px solid #CC723F; border-radius: 0 8px 8px 0; padding: 14px 18px;">
                            <p style="margin: 0; font-size: 13px; color: #6B7280; line-height: 1.5;"><strong>Security Reminder:</strong> If you did not request this email change, please log into your account immediately to review your settings.</p>
                        </td>
                    </tr>
                </table>

                <p style="margin: 0; font-size: 14px; color: #4B5563; line-height: 1.5;">Warm regards,<br><strong style="color: #1D1E20;">Arab Zawaj Matchmaking Team</strong></p>
            ';
            $html = $this->wrap_email_layout($inner);
        }

        $from_email = $this->get_sender_email();
        $from_name  = $this->get_sender_name();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $from_email_filter   = static fn(): string => $from_email;
        $from_name_filter    = static fn(): string => $from_name;
        $content_type_filter = static fn(): string => 'text/html';

        add_filter('wp_mail_from', $from_email_filter, 999);
        add_filter('wp_mail_from_name', $from_name_filter, 999);
        add_filter('wp_mail_content_type', $content_type_filter, 999);

        $mail_error = null;
        $error_listener = static function ($wp_error) use (&$mail_error): void {
            if (is_wp_error($wp_error)) {
                $mail_error = $wp_error->get_error_message();
            } elseif (is_string($wp_error)) {
                $mail_error = $wp_error;
            }
        };

        add_action('wp_mail_failed', $error_listener, 10, 1);

        $sent = (bool) wp_mail($to_email, $subject, $html, $headers);

        remove_action('wp_mail_failed', $error_listener, 10);
        remove_filter('wp_mail_content_type', $content_type_filter, 999);
        remove_filter('wp_mail_from_name', $from_name_filter, 999);
        remove_filter('wp_mail_from', $from_email_filter, 999);

        return $sent;
    }

    /**
     * Verify the code submitted for pending email update.
     * Updates wp_users.user_email upon successful verification.
     *
     * @param int    $user_id
     * @param string $code
     * @return array{success: bool, message: string, new_email?: string}
     */
    public function verify_pending_email_code(int $user_id, string $code): array
    {
        if ($user_id <= 0) {
            return ['success' => false, 'message' => __('Invalid user session.', 'matchmaker')];
        }

        $pending_email = (string) get_user_meta($user_id, 'mm_pending_new_email', true);
        if (empty($pending_email) || !is_email($pending_email)) {
            return ['success' => false, 'message' => __('No pending email update found.', 'matchmaker')];
        }

        $clean_code = preg_replace('/\D/', '', trim($code));
        if (strlen((string) $clean_code) !== 6) {
            return ['success' => false, 'message' => __('Please enter a valid 6-digit verification code.', 'matchmaker')];
        }

        $stored_code = (string) get_user_meta($user_id, 'mm_pending_verification_code', true);
        $expires_at  = (int) get_user_meta($user_id, 'mm_pending_verification_expires_at', true);
        $repo        = \Matchmaker\Repository\MatchRepository::instance();

        if (empty($stored_code) || time() > $expires_at) {
            $repo->log_event(
                'email',
                'pending_email_verify_failed',
                sprintf(__('Pending Email Code Expired: %s', 'matchmaker'), $pending_email),
                __('User attempted verification with an expired or non-existent code.', 'matchmaker'),
                ['user_id' => $user_id, 'pending_email' => $pending_email, 'expired' => true],
                null,
                $user_id,
                $pending_email,
                'warning'
            );

            return ['success' => false, 'message' => __('Your verification code has expired. Please request a new code.', 'matchmaker')];
        }

        if (!hash_equals($stored_code, (string) $clean_code)) {
            $repo->log_event(
                'email',
                'pending_email_verify_failed',
                sprintf(__('Invalid Pending Verification Code Attempt: %s', 'matchmaker'), $pending_email),
                sprintf(__('User submitted invalid code "%s".', 'matchmaker'), $clean_code),
                ['user_id' => $user_id, 'pending_email' => $pending_email, 'attempted' => $clean_code],
                null,
                $user_id,
                $pending_email,
                'warning'
            );

            return ['success' => false, 'message' => __('Invalid verification code. Please check your email and try again.', 'matchmaker')];
        }

        // Check if email was claimed by another user
        $email_holder = email_exists($pending_email);
        if ($email_holder && (int) $email_holder !== $user_id) {
            return ['success' => false, 'message' => __('That email address is already registered to another user.', 'matchmaker')];
        }

        // Update user_email in wp_users
        $GLOBALS['__mm_updating_verified_email'] = true;
        $update_res = wp_update_user([
            'ID'         => $user_id,
            'user_email' => $pending_email,
        ]);
        unset($GLOBALS['__mm_updating_verified_email']);

        if (\is_wp_error($update_res)) {
            return ['success' => false, 'message' => $update_res->get_error_message()];
        }

        // Clean up pending meta
        delete_user_meta($user_id, 'mm_pending_new_email');
        delete_user_meta($user_id, 'mm_pending_verification_code');
        delete_user_meta($user_id, 'mm_pending_verification_expires_at');
        delete_user_meta($user_id, 'mm_pending_verification_last_sent_at');
        update_user_meta($user_id, 'mm_email_verified', 1);

        $repo->log_event(
            'email',
            'pending_email_verified',
            sprintf(__('Email Address Updated & Verified: %s', 'matchmaker'), $pending_email),
            sprintf(__('User #%d successfully verified and updated email address to %s.', 'matchmaker'), $user_id, $pending_email),
            ['user_id' => $user_id, 'new_email' => $pending_email],
            null,
            $user_id,
            $pending_email,
            'success'
        );

        return [
            'success'   => true,
            'message'   => __('Your email address has been successfully verified and updated!', 'matchmaker'),
            'new_email' => $pending_email,
        ];
    }

    /**
     * AJAX handler for verifying pending email change code.
     *
     * @return void
     */
    public function handle_ajax_verify_pending_email(): void
    {
        $nonce   = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $user_id = get_current_user_id();

        if ($user_id <= 0 && !empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        }

        $valid_nonce = wp_verify_nonce($nonce, 'mm_pending_verify_nonce')
            || wp_verify_nonce($nonce, 'mm_verify_nonce')
            || ($user_id > 0 && wp_verify_nonce($nonce, 'mm_verify_nonce_' . $user_id));

        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'matchmaker')]);
        }

        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('User session not found. Please log in again.', 'matchmaker')]);
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash((string) $_POST['code'])) : '';
        $res  = $this->verify_pending_email_code($user_id, $code);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * AJAX handler for resending verification code to pending email.
     *
     * @return void
     */
    public function handle_ajax_resend_pending_email(): void
    {
        $nonce   = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        $user_id = get_current_user_id();

        if ($user_id <= 0 && !empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        }

        $valid_nonce = wp_verify_nonce($nonce, 'mm_pending_verify_nonce')
            || wp_verify_nonce($nonce, 'mm_verify_nonce')
            || ($user_id > 0 && wp_verify_nonce($nonce, 'mm_verify_nonce_' . $user_id));

        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'matchmaker')]);
        }

        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('User session not found. Please log in again.', 'matchmaker')]);
        }

        $pending_email = (string) get_user_meta($user_id, 'mm_pending_new_email', true);
        if (empty($pending_email)) {
            wp_send_json_error(['message' => __('No pending email update found.', 'matchmaker')]);
        }

        $res = $this->generate_and_send_pending_code($user_id, $pending_email, false);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * PMPro Account Page hook callback to render pending email banner.
     *
     * @return void
     */
    public function render_pending_email_notice_on_pmpro_account(): void
    {
        echo $this->render_pending_email_notice();
    }

    /**
     * Render the pending email notification banner and OTP modal for Dashboard & PMPro account.
     *
     * @param int $user_id
     * @return string
     */
    public function render_pending_email_notice(int $user_id = 0): string
    {
        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0) {
            return '';
        }

        $pending_email = (string) get_user_meta($user_id, 'mm_pending_new_email', true);
        if (empty($pending_email)) {
            return '';
        }

        $nonce    = wp_create_nonce('mm_pending_verify_nonce');
        $ajax_url = admin_url('admin-ajax.php');

        $cooldown_limit     = $this->get_cooldown_seconds();
        $last_sent          = (int) get_user_meta($user_id, 'mm_pending_verification_last_sent_at', true);
        $time_diff          = time() - $last_sent;
        $cooldown_remaining = ($time_diff < $cooldown_limit) ? ($cooldown_limit - $time_diff) : 0;

        ob_start();
        ?>
        <div class="mm-pending-email-notice-wrap" id="mm-pending-email-notice-wrap" style="margin: 0 0 24px;">
            <!-- Notice Banner -->
            <div class="mm-pending-email-notice" style="background: linear-gradient(135deg, #FFFDFB 0%, #FAF5F0 100%); border: 1px solid rgba(204,114,63,0.3); border-left: 4px solid #CC723F; padding: 16px 20px; border-radius: 12px; box-shadow: 0 4px 14px rgba(204,114,63,0.08); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 260px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(204,114,63,0.12); color: #CC723F; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#CC723F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #CC723F; margin-bottom: 2px;"><?php esc_html_e('Email Verification Pending', 'matchmaker'); ?></div>
                        <div style="color: #374151; font-size: 14px; line-height: 1.4;">
                            <?php printf(esc_html__('Please verify %s to complete your email update.', 'matchmaker'), '<strong style="color:#1D1E20;">' . esc_html($pending_email) . '</strong>'); ?>
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" id="mm-open-pending-verify-modal-btn" style="background: #CC723F; color: #ffffff; border: none; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(204,114,63,0.25); display: inline-flex; align-items: center; gap: 6px;">
                        <span><?php esc_html_e('Verify Email', 'matchmaker'); ?></span>
                        <span style="font-size: 14px;">&rarr;</span>
                    </button>
                </div>
            </div>

            <!-- Pending Email OTP Verification Modal -->
            <div id="mm-pending-verify-modal" class="mm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(29,30,32,0.65); backdrop-filter:blur(4px); z-index:99999; justify-content:center; align-items:center; padding:16px; box-sizing:border-box;">
                <div class="mm-modal-card" style="background:#ffffff; max-width:440px; width:100%; border-radius:18px; padding:32px 26px; box-shadow:0 24px 48px rgba(29,30,32,0.22); border:1px solid rgba(204,114,63,0.18); position:relative; text-align:center; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                    <button type="button" id="mm-close-pending-modal-btn" style="position:absolute; top:16px; right:18px; background:none; border:none; font-size:24px; color:#9ca3af; cursor:pointer; line-height:1; transition:color 0.15s ease;" title="<?php esc_attr_e('Close', 'matchmaker'); ?>">&times;</button>
                    
                    <div style="width:52px; height:52px; background:#FAF5F0; border:1px solid rgba(204,114,63,0.25); border-radius:50%; color:#CC723F; display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#CC723F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                    <h3 style="font-family:'Marcellus', Georgia, serif; font-size:22px; font-weight:700; color:#1D1E20; margin:0 0 8px; letter-spacing:0.02em;">
                        <?php esc_html_e('Verify New Email', 'matchmaker'); ?>
                    </h3>
                    <p style="font-size:14px; color:#6b7280; margin:0 0 20px; line-height:1.5;">
                        <?php printf(esc_html__('We sent a 6-digit verification code to %s. Enter it below to update your email.', 'matchmaker'), '<br><span style="display:inline-block; margin-top:4px; padding:3px 10px; background:#FAF5F0; border-radius:6px; font-weight:600; color:#CC723F; font-size:13px;">' . esc_html($pending_email) . '</span>'); ?>
                    </p>

                    <div id="mm-pending-verify-alert" style="display:none; padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; text-align:left; line-height:1.4;"></div>

                    <form id="mm-pending-verify-form" style="margin:0 0 16px;">
                        <input type="hidden" name="action" value="mm_verify_pending_email_code">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                        <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">

                        <div style="margin-bottom:18px;">
                            <input type="text" id="mm-pending-otp-input" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="· · · · · ·" style="font-family:'Courier New', monospace; font-size:30px; font-weight:700; letter-spacing:10px; text-align:center; width:220px; padding:12px 8px; border:2px solid #E5E7EB; border-radius:10px; outline:none; transition:border-color 0.2s, box-shadow 0.2s; background:#FAFAFA;" autocomplete="one-time-code" required>
                        </div>

                        <button type="submit" id="mm-pending-verify-submit-btn" style="width:100%; background:#CC723F; color:#ffffff; border:none; padding:13px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; transition:background 0.15s ease; box-shadow: 0 2px 10px rgba(204,114,63,0.3);">
                            <?php esc_html_e('Confirm &amp; Update Email', 'matchmaker'); ?>
                        </button>
                    </form>

                    <div style="font-size:13px; color:#6b7280;">
                        <?php esc_html_e("Didn't receive the code?", 'matchmaker'); ?>
                        <button type="button" id="mm-pending-resend-btn" style="background:none; border:none; color:#CC723F; font-weight:600; cursor:pointer; padding:0 0 0 4px; text-decoration:underline; font-size:13px;" <?php echo ($cooldown_remaining > 0) ? 'disabled' : ''; ?>>
                            <?php echo ($cooldown_remaining > 0) ? sprintf(esc_html__('Resend in %ds', 'matchmaker'), $cooldown_remaining) : esc_html__('Resend Code', 'matchmaker'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                var modal = document.getElementById('mm-pending-verify-modal');
                var openBtn = document.getElementById('mm-open-pending-verify-modal-btn');
                var closeBtn = document.getElementById('mm-close-pending-modal-btn');
                var form = document.getElementById('mm-pending-verify-form');
                var otpInput = document.getElementById('mm-pending-otp-input');
                var submitBtn = document.getElementById('mm-pending-verify-submit-btn');
                var resendBtn = document.getElementById('mm-pending-resend-btn');
                var alertBox = document.getElementById('mm-pending-verify-alert');
                var cooldown = <?php echo (int) $cooldown_remaining; ?>;
                var timerInterval = null;

                function showAlert(msg, isSuccess) {
                    if (!alertBox) return;
                    alertBox.style.display = 'block';
                    alertBox.style.background = isSuccess ? '#ecfdf5' : '#fef2f2';
                    alertBox.style.border = isSuccess ? '1px solid #a7f3d0' : '1px solid #fecaca';
                    alertBox.style.color = isSuccess ? '#065f46' : '#991b1b';
                    alertBox.innerHTML = msg;
                }

                function startCooldown(seconds) {
                    cooldown = seconds;
                    if (timerInterval) clearInterval(timerInterval);
                    if (!resendBtn) return;
                    resendBtn.disabled = true;
                    resendBtn.innerText = 'Resend in ' + cooldown + 's';

                    timerInterval = setInterval(function() {
                        cooldown--;
                        if (cooldown <= 0) {
                            clearInterval(timerInterval);
                            resendBtn.disabled = false;
                            resendBtn.innerText = 'Resend Code';
                        } else {
                            resendBtn.innerText = 'Resend in ' + cooldown + 's';
                        }
                    }, 1000);
                }

                if (cooldown > 0) {
                    startCooldown(cooldown);
                }

                if (openBtn && modal) {
                    openBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        modal.style.display = 'flex';
                        if (otpInput) otpInput.focus();
                    });
                }

                if (closeBtn && modal) {
                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }

                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var code = (otpInput ? otpInput.value : '').replace(/\D/g, '');
                        if (code.length !== 6) {
                            showAlert('Please enter the complete 6-digit code.', false);
                            return;
                        }

                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.innerText = 'Verifying...';
                        }

                        var fd = new FormData();
                        fd.append('action', 'mm_verify_pending_email_code');
                        fd.append('nonce', '<?php echo esc_js($nonce); ?>');
                        fd.append('user_id', '<?php echo (int) $user_id; ?>');
                        fd.append('code', code);

                        fetch('<?php echo esc_url($ajax_url); ?>', {
                            method: 'POST',
                            body: fd
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                showAlert(res.data && res.data.message ? res.data.message : 'Email updated successfully!', true);
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1200);
                            } else {
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.innerText = 'Confirm & Update Email';
                                }
                                showAlert(res.data && res.data.message ? res.data.message : 'Verification failed.', false);
                            }
                        })
                        .catch(function() {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerText = 'Confirm & Update Email';
                            }
                            showAlert('Network error. Please try again.', false);
                        });
                    });
                }

                if (resendBtn) {
                    resendBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (resendBtn.disabled) return;
                        resendBtn.disabled = true;
                        resendBtn.innerText = 'Sending...';

                        var fd = new FormData();
                        fd.append('action', 'mm_resend_pending_email_code');
                        fd.append('nonce', '<?php echo esc_js($nonce); ?>');
                        fd.append('user_id', '<?php echo (int) $user_id; ?>');

                        fetch('<?php echo esc_url($ajax_url); ?>', {
                            method: 'POST',
                            body: fd
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                showAlert(res.data && res.data.message ? res.data.message : 'New code sent!', true);
                                var cd = (res.data && res.data.cooldown_remaining) ? res.data.cooldown_remaining : 60;
                                startCooldown(cd);
                            } else {
                                resendBtn.disabled = false;
                                resendBtn.innerText = 'Resend Code';
                                showAlert(res.data && res.data.message ? res.data.message : 'Could not send code.', false);
                            }
                        })
                        .catch(function() {
                            resendBtn.disabled = false;
                            resendBtn.innerText = 'Resend Code';
                            showAlert('Network error. Please try again.', false);
                        });
                    });
                }
            })();
            </script>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

