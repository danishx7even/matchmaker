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

    public const CODE_EXPIRY_SECONDS     = 86400; // 24 hours (default fallback)
    public const RESEND_COOLDOWN_SECONDS = 60;    // 60 seconds (default fallback)

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

        // Auto-send verification code on user registration
        add_action('user_register', [$this, 'on_user_register'], 20, 1);
        add_action('pmpro_after_checkout', [$this, 'on_pmpro_checkout'], 20, 2);

        // One-time grandfathering migration for existing users
        $this->maybe_grandfather_existing_users();
    }

    /**
     * Get configured verification code expiration in seconds.
     *
     * @return int
     */
    public function get_expiry_seconds(): int
    {
        $hours = max(1, (int) get_option('mm_email_verify_expiry_hours', 24));
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

        if (function_exists('user_can') && user_can($user_id, 'manage_options')) {
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

        $cooldown_limit = $this->get_cooldown_seconds();
        $expiry_limit   = $this->get_expiry_seconds();

        $last_sent = (int) get_user_meta($user_id, 'mm_verification_last_sent_at', true);
        $time_diff = time() - $last_sent;

        if (!$force && $last_sent > 0 && $time_diff < $cooldown_limit) {
            $remaining = $cooldown_limit - $time_diff;
            return [
                'success'            => false,
                'message'            => sprintf(__('Please wait %d seconds before requesting another code.', 'matchmaker'), $remaining),
                'cooldown_remaining' => $remaining,
            ];
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
     *
     * @param string $body_inner
     * @return string
     */
    private function wrap_email_layout(string $body_inner): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #F8F2ED; margin: 0; padding: 30px 10px; color: #1D1E20; }
.email-container { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid rgba(204,114,63,0.15); }
.email-header { background: #1D1E20; padding: 28px 24px; text-align: center; border-bottom: 3px solid #CC723F; }
.email-header h1 { font-family: Georgia, serif; font-size: 22px; color: #ffffff; margin: 0; letter-spacing: 0.05em; }
.email-body { padding: 36px 28px; text-align: center; font-size: 15px; line-height: 1.6; color: #4b5563; }
.email-footer { background: #fdfbf9; padding: 18px 24px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
</style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>ARAB ZAWAJ</h1>
    </div>
    <div class="email-body">
        ' . $body_inner . '
    </div>
    <div class="email-footer">
        &copy; ' . gmdate('Y') . ' Arab Zawaj. All rights reserved.
    </div>
</div>
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
        $expiry_hours    = (string) max(1, (int) get_option('mm_email_verify_expiry_hours', 24));
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

        $default_inner = '<p style="font-size: 17px; font-weight: 600; color: #1D1E20;">Assalamu Alaikum, ' . esc_html($display_name ?: 'Member') . '!</p>'
            . '<p>Please use the 6-digit verification code below to verify your email address and access your Arab Zawaj matchmaking portal:</p>'
            . '<div style="display: inline-block; background: #F8F2ED; border: 2px dashed #CC723F; border-radius: 12px; padding: 16px 36px; font-size: 32px; font-weight: 800; letter-spacing: 0.25em; color: #1D1E20; margin: 10px 0 24px;">' . esc_html($code) . '</div>'
            . '<p style="font-size: 14px; color: #6b7280;">This code is valid for <strong>' . $expiry_hours . ' hours</strong>. If you did not request this email, please ignore it.</p>'
            . '<p style="font-size: 13px; color: #9ca3af; margin-top: 20px;">Need assistance? Contact our support team directly.</p>';

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
}
