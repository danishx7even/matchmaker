<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Notification_Manager
 *
 * Manages the WordPress Heartbeat API notification polling (15s interval),
 * persistent database notifications table (wp_matchmaker_notifications),
 * unread badge clearing on view, and automated email alerts upon admin match approval.
 */
class Notification_Manager
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Heartbeat API hooks
        add_filter('heartbeat_received', [$this, 'handle_heartbeat_pulse'], 10, 2);
        add_filter('heartbeat_settings', [$this, 'configure_heartbeat_frequency']);

        // AJAX handler for clearing unread notifications
        add_action('wp_ajax_mm_mark_notifications_read', [$this, 'handle_ajax_mark_read']);
    }

    /**
     * Set Heartbeat polling interval to 15 seconds on the Dashboard.
     */
    public function configure_heartbeat_frequency(array $settings): array
    {
        $settings['interval'] = 15;
        return $settings;
    }

    /**
     * Insert a persistent notification row into wp_matchmaker_notifications.
     */
    public function create_notification(int $user_id, int $match_id, string $type, string $title, string $message): void
    {
        if ($user_id <= 0 || $match_id <= 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_notifications';

        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'match_id'   => $match_id,
                'type'       => sanitize_key($type),
                'title'      => sanitize_text_field($title),
                'message'    => sanitize_textarea_field($message),
                'is_read'    => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        $this->flush_user_unread_transient($user_id);
    }

    /**
     * Process Heartbeat pulse from client.
     */
    public function handle_heartbeat_pulse(array $response, array $data): array
    {
        if (empty($data['mm_poll_notifications']) || !is_user_logged_in()) {
            return $response;
        }

        $user_id = get_current_user_id();

        // Tier check: skip free and event users
        $user_type = class_exists('\Matchmaker\PMPro_Sync')
            ? PMPro_Sync::instance()->get_current_user_type($user_id)
            : (string) get_user_meta($user_id, 'user_type', true);

        if (in_array($user_type, ['free', 'event'], true)) {
            $response['matchmaker_unread_count'] = 0;
            return $response;
        }

        $unread_count = $this->get_user_unread_count($user_id);
        $response['matchmaker_unread_count'] = $unread_count;

        return $response;
    }

    /**
     * Retrieve or cache unread notification count for a user.
     *
     * Strictly reads from wp_matchmaker_notifications (is_read = 0).
     * No fallback to wp_matches — prevents stale approved-match counts
     * from re-triggering badges after the user has viewed their notifications.
     */
    public function get_user_unread_count(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $transient_key = "mm_unread_count_{$user_id}";
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_notifications';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );

        set_transient($transient_key, $count, 15);

        return $count;
    }

    /**
     * Flush transient unread cache for a given user.
     */
    public function flush_user_unread_transient(int $user_id): void
    {
        if ($user_id > 0) {
            delete_transient("mm_unread_count_{$user_id}");
        }
    }

    /**
     * AJAX handler to mark all unread notifications for a user as read (is_read = 1).
     */
    public function handle_ajax_mark_read(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mm_portal_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => 'User not logged in.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_notifications';

        $wpdb->update(
            $table,
            ['is_read' => 1],
            ['user_id' => $user_id, 'is_read' => 0],
            ['%d'],
            ['%d', '%d']
        );

        $this->flush_user_unread_transient($user_id);

        wp_send_json_success(['message' => 'Notifications marked as read.']);
    }

    /**
     * Send email notifications to both members when an admin approves a match,
     * and log persistent notification records into wp_matchmaker_notifications.
     */
    public function send_approval_emails(int $match_id): void
    {
        if ($match_id <= 0) {
            return;
        }

        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';

        $match = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$matches_table} WHERE id = %d", $match_id),
            ARRAY_A
        );

        if (!$match) {
            return;
        }

        $u1_id = (int) $match['user_one_id'];
        $u2_id = (int) $match['user_two_id'];

        $u1_obj  = get_userdata($u1_id);
        $u2_obj  = get_userdata($u2_id);
        $u1_pool = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $u1_id), ARRAY_A);
        $u2_pool = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $u2_id), ARRAY_A);

        if (!$u1_obj || !$u2_obj) {
            return;
        }

        // Insert persistent notification records into database
        $this->create_notification(
            $u1_id,
            $match_id,
            'match_approved',
            __('You Have a New Match!', 'matchmaker'),
            sprintf(__('A new match candidate (%s) has been approved for your review.', 'matchmaker'), $u2_obj->display_name)
        );

        $this->create_notification(
            $u2_id,
            $match_id,
            'match_approved',
            __('You Have a New Match!', 'matchmaker'),
            sprintf(__('A new match candidate (%s) has been approved for your review.', 'matchmaker'), $u1_obj->display_name)
        );

        $default_subject = "You Have a New Match Available on Arab Zawaj!";
        $default_template = "
            <p>Dear {user_name},</p>
            <p>We are excited to inform you that our matchmaking team has approved a new match for you!</p>
            <p><strong>Candidate Details:</strong></p>
            <ul>
                <li><strong>Name:</strong> {candidate_name}</li>
                <li><strong>Age:</strong> {candidate_age} Years Old</li>
                <li><strong>Location:</strong> {candidate_location}</li>
            </ul>
            <p>Please log in to your dashboard to review this candidate's full profile and submit your response within 7 days.</p>
            <p><a href='{dashboard_url}' style='background-color: #CC723F; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>View Your Match Now →</a></p>
            <p>Warm regards,<br>The Arab Zawaj Matchmaking Team</p>
        ";

        $subject_setting  = (string) get_option('mm_email_approval_subject', $default_subject);
        $template_setting = (string) get_option('mm_email_approval_template', $default_template);

        $dashboard_url = home_url('/dashboard/');

        $calc_age = static function (?string $birth_date): string {
            if (empty($birth_date) || $birth_date === '0000-00-00') {
                return '—';
            }
            try {
                return (string) (new \DateTime($birth_date))->diff(new \DateTime())->y;
            } catch (\Exception $e) {
                return '—';
            }
        };

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // 1. Send to User One (about User Two)
        if (!empty($u1_obj->user_email)) {
            $replacements_u1 = [
                '{user_name}'          => esc_html($u1_obj->display_name),
                '{candidate_name}'     => esc_html($u2_obj->display_name),
                '{candidate_age}'      => esc_html($calc_age($u2_pool['birth_date'] ?? '')),
                '{candidate_location}' => esc_html($u2_pool['location'] ?? '—'),
                '{dashboard_url}'      => esc_url($dashboard_url),
            ];

            $subject1 = str_replace(array_keys($replacements_u1), array_values($replacements_u1), $subject_setting);
            $body1    = str_replace(array_keys($replacements_u1), array_values($replacements_u1), $template_setting);

            wp_mail($u1_obj->user_email, $subject1, wp_kses_post($body1), $headers);
        }

        // 2. Send to User Two (about User One)
        if (!empty($u2_obj->user_email)) {
            $replacements_u2 = [
                '{user_name}'          => esc_html($u2_obj->display_name),
                '{candidate_name}'     => esc_html($u1_obj->display_name),
                '{candidate_age}'      => esc_html($calc_age($u1_pool['birth_date'] ?? '')),
                '{candidate_location}' => esc_html($u1_pool['location'] ?? '—'),
                '{dashboard_url}'      => esc_url($dashboard_url),
            ];

            $subject2 = str_replace(array_keys($replacements_u2), array_values($replacements_u2), $subject_setting);
            $body2    = str_replace(array_keys($replacements_u2), array_values($replacements_u2), $template_setting);

            wp_mail($u2_obj->user_email, $subject2, wp_kses_post($body2), $headers);
        }
    }
}
