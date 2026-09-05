<?php
declare(strict_types=1);

namespace Matchmaker\Service;

use Matchmaker\Repository\MatchRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NotificationService
 * @package Matchmaker\Service
 */
class NotificationService {

    /**
     * @var NotificationService|null
     */
    private static ?NotificationService $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return NotificationService
     */
    public static function instance(): NotificationService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * NotificationService constructor.
     */
    private function __construct() {
        $this->boot();
    }

    /**
     * Boot the service and register hooks.
     *
     * @return void
     */
    public function boot(): void {
        add_filter('heartbeat_received', [$this, 'handle_heartbeat_pulse'], 10, 2);
        add_filter('heartbeat_settings', [$this, 'configure_heartbeat_frequency']);
        add_action('wp_ajax_mm_mark_notifications_read', [$this, 'handle_ajax_mark_read']);
    }

    /**
     * Configure the heartbeat frequency.
     *
     * @param array $settings The heartbeat settings.
     * @return array
     */
    public function configure_heartbeat_frequency(array $settings): array {
        $settings['interval'] = 15;
        return $settings;
    }

    /**
     * Handle the heartbeat pulse.
     *
     * @param array $response The heartbeat response.
     * @param array $data The heartbeat data.
     * @return array
     */
    public function handle_heartbeat_pulse(array $response, array $data): array {
        if (!is_user_logged_in()) {
            return $response;
        }

        $user_id = get_current_user_id();
        $user_type = 'free';
        
        if (class_exists('\Matchmaker\Core\PMProSync')) {
            $user_type = \Matchmaker\Core\PMProSync::instance()->get_current_user_type($user_id);
        } else {
            $meta_type = (string) get_user_meta($user_id, 'user_type', true);
            if (!empty($meta_type)) {
                $user_type = $meta_type;
            }
        }

        if ($user_type === 'event') {
            return $response;
        }

        $repo = MatchRepository::instance();
        $unread_count = $repo->get_unread_count($user_id);

        $response['mm_unread_count']         = $unread_count;
        $response['matchmaker_unread_count'] = $unread_count;

        return $response;
    }

    /**
     * Create a notification.
     *
     * @param int $user_id The user ID.
     * @param int $match_id The match ID.
     * @param string $type The notification type.
     * @param string $title The notification title.
     * @param string $message The notification message.
     * @return void
     */
    public function create_notification(int $user_id, int $match_id, string $type, string $title, string $message): void {
        MatchRepository::instance()->create_notification($user_id, $match_id, $type, $title, $message);

        MatchRepository::instance()->log_event(
            'notification',
            $type,
            sprintf(__('In-App Notification: %s (User #%d)', 'matchmaker'), $title, $user_id),
            $message,
            [
                'user_id'  => $user_id,
                'match_id' => $match_id,
                'type'     => $type,
                'title'    => $title,
            ],
            $match_id,
            $user_id,
            null,
            'info'
        );
    }

    /**
     * Send rejection in-app notification to the candidate whose match was declined.
     *
     * @param int $match_id The match ID.
     * @param int $declined_by_user_id The user ID who declined the match.
     * @return void
     */
    public function send_rejection_notification(int $match_id, int $declined_by_user_id): void
    {
        $repo  = MatchRepository::instance();
        $match = $repo->find_match_by_id($match_id);

        if (!$match) {
            return;
        }

        $other_user_id = ((int) ($match['user_one_id'] ?? 0) === $declined_by_user_id)
            ? (int) ($match['user_two_id'] ?? 0)
            : (int) ($match['user_one_id'] ?? 0);

        if ($other_user_id <= 0) {
            return;
        }

        // 1. Invalidate/Dismiss any stale 'match_approved' notification for this match so the candidate doesn't see a new match alert
        $repo->dismiss_notifications_for_match($match_id, 'match_approved');

        // 2. Dispatch polite conclusion notification to the other user
        $this->create_notification(
            $other_user_id,
            $match_id,
            'match_rejected',
            __('Match Recommendation Concluded', 'matchmaker'),
            __('Your recent match recommendation has concluded as the candidate declined to proceed. Our matchmakers will continue curating fresh matches for you.', 'matchmaker')
        );

        $this->flush_user_unread_transient($other_user_id);
        $this->flush_user_unread_transient($declined_by_user_id);
    }

    /**
     * Get user unread count.
     *
     * @param int $user_id The user ID.
     * @return int
     */
    public function get_user_unread_count(int $user_id): int {
        return MatchRepository::instance()->get_unread_count($user_id);
    }

    /**
     * Flush user unread transient.
     *
     * @param int $user_id The user ID.
     * @return void
     */
    public function flush_user_unread_transient(int $user_id): void {
        MatchRepository::instance()->flush_unread_transient($user_id);
    }

    /**
     * Handle AJAX mark read.
     *
     * @return void
     */
    public function handle_ajax_mark_read(): void {
        check_ajax_referer('mm_portal_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        MatchRepository::instance()->mark_notifications_read($user_id);
        wp_send_json_success();
    }

    /**
     * Send approval emails and in-app notifications.
     *
     * @param int $match_id The match ID.
     * @return void
     */
    public function send_approval_emails(int $match_id): void {
        $repo = MatchRepository::instance();
        $match = $repo->find_match_by_id($match_id);

        if (!$match) {
            return;
        }

        $user_a = get_userdata((int) ($match['user_one_id'] ?? 0));
        $user_b = get_userdata((int) ($match['user_two_id'] ?? 0));

        if (!$user_a || !$user_b) {
            return;
        }
        
        $pool_a = $repo->get_user_pool((int) ($match['user_one_id'] ?? 0));
        $pool_b = $repo->get_user_pool((int) ($match['user_two_id'] ?? 0));
        
        $age_a = isset($pool_a['birth_date']) ? $repo->calc_age($pool_a['birth_date']) : '';
        $age_b = isset($pool_b['birth_date']) ? $repo->calc_age($pool_b['birth_date']) : '';

        $loc_a = trim(($pool_a['city'] ?? '') . ', ' . ($pool_a['country'] ?? ($pool_a['location'] ?? '')), ', ') ?: '—';
        $loc_b = trim(($pool_b['city'] ?? '') . ', ' . ($pool_b['country'] ?? ($pool_b['location'] ?? '')), ', ') ?: '—';

        $dashboard_url = \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();

        $default_subject = __('Congratulations! You have a new approved match on Arab Zawaj', 'matchmaker');
        $default_template = "<p>Dear {user_name},</p>\n"
            . "<p>Great news! Our matchmakers have reviewed and approved a new profile match for you.</p>\n"
            . "<p><strong>Candidate Details:</strong><br>\n"
            . "Name: {candidate_name}<br>\n"
            . "Age: {candidate_age}<br>\n"
            . "Location: {candidate_location}</p>\n"
            . "<p>Please log in to your portal to review their full profile and respond:</p>\n"
            . "<p><a href=\"{dashboard_url}\" style=\"background:#CC723F;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">View Match Details &rarr;</a></p>\n"
            . "<p>Warm regards,<br>Arab Zawaj Matchmaking Team</p>";

        $subject  = (string) get_option('mm_email_approval_subject', $default_subject);
        $template = (string) get_option('mm_email_approval_template', $default_template);

        $body_a = str_replace(
            ['{user_name}', '{candidate_name}', '{candidate_age}', '{candidate_location}', '{dashboard_url}'],
            [$user_a->display_name, $user_b->display_name, (string)$age_b, $loc_b, $dashboard_url],
            $template
        );

        $body_b = str_replace(
            ['{user_name}', '{candidate_name}', '{candidate_age}', '{candidate_location}', '{dashboard_url}'],
            [$user_b->display_name, $user_a->display_name, (string)$age_a, $loc_a, $dashboard_url],
            $template
        );
        
        $this->create_notification((int) $user_a->ID, $match_id, 'match_approved', __('New Match Available!', 'matchmaker'), __('You have a new approved match awaiting your review.', 'matchmaker'));
        $this->create_notification((int) $user_b->ID, $match_id, 'match_approved', __('New Match Available!', 'matchmaker'), __('You have a new approved match awaiting your review.', 'matchmaker'));

        $html_filter = static fn() => 'text/html';
        add_filter('wp_mail_content_type', $html_filter);
        $mail_a_sent = wp_mail($user_a->user_email, $subject, wpautop($body_a));
        $mail_b_sent = wp_mail($user_b->user_email, $subject, wpautop($body_b));
        remove_filter('wp_mail_content_type', $html_filter);

        $repo->log_event(
            'email',
            'email_sent',
            sprintf(__('Approval Email Sent: %s (Match #%d)', 'matchmaker'), $user_a->user_email, $match_id),
            $subject,
            [
                'match_id'      => $match_id,
                'user_id'       => (int) $user_a->ID,
                'recipient'     => $user_a->user_email,
                'subject'       => $subject,
                'body_html'     => wpautop($body_a),
                'delivery_stat' => $mail_a_sent ? 'delivered' : 'failed',
            ],
            $match_id,
            (int) $user_a->ID,
            $user_a->user_email,
            $mail_a_sent ? 'success' : 'error'
        );

        $repo->log_event(
            'email',
            'email_sent',
            sprintf(__('Approval Email Sent: %s (Match #%d)', 'matchmaker'), $user_b->user_email, $match_id),
            $subject,
            [
                'match_id'      => $match_id,
                'user_id'       => (int) $user_b->ID,
                'recipient'     => $user_b->user_email,
                'subject'       => $subject,
                'body_html'     => wpautop($body_b),
                'delivery_stat' => $mail_b_sent ? 'delivered' : 'failed',
            ],
            $match_id,
            (int) $user_b->ID,
            $user_b->user_email,
            $mail_b_sent ? 'success' : 'error'
        );
    }

    /**
     * Send mutual match emails and in-app notifications.
     *
     * @param int $match_id The match ID.
     * @return void
     */
    public function send_mutual_match_notifications(int $match_id): void
    {
        $repo  = MatchRepository::instance();
        $match = $repo->find_match_by_id($match_id);

        if (!$match) {
            return;
        }

        $user_a = get_userdata((int) ($match['user_one_id'] ?? 0));
        $user_b = get_userdata((int) ($match['user_two_id'] ?? 0));

        if (!$user_a || !$user_b) {
            return;
        }

        $dashboard_url = \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();

        // 1. In-App Notifications
        $this->create_notification((int) $user_a->ID, $match_id, 'mutual_match', __('It\'s a Mutual Match!', 'matchmaker'), sprintf(__('Congratulations! You and %s have both accepted the match. Contact information is now unlocked!', 'matchmaker'), $user_b->display_name));
        $this->create_notification((int) $user_b->ID, $match_id, 'mutual_match', __('It\'s a Mutual Match!', 'matchmaker'), sprintf(__('Congratulations! You and %s have both accepted the match. Contact information is now unlocked!', 'matchmaker'), $user_a->display_name));

        // 2. Transactional HTML Emails
        $subject = __('Congratulations! It\'s a Mutual Match on Arab Zawaj', 'matchmaker');
        
        $body_template = "<p>Dear {user_name},</p>\n"
            . "<p>Exciting news! <strong>{candidate_name}</strong> has also accepted your profile match. It's a mutual match!</p>\n"
            . "<p>You can now log in to your Member Portal to view their verified contact details and begin communications.</p>\n"
            . "<p><a href=\"{dashboard_url}\" style=\"background:#CC723F;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">View Contact Details &rarr;</a></p>\n"
            . "<p>Warm regards,<br>Arab Zawaj Matchmaking Team</p>";

        $body_a = str_replace(
            ['{user_name}', '{candidate_name}', '{dashboard_url}'],
            [$user_a->display_name, $user_b->display_name, $dashboard_url],
            $body_template
        );

        $body_b = str_replace(
            ['{user_name}', '{candidate_name}', '{dashboard_url}'],
            [$user_b->display_name, $user_a->display_name, $dashboard_url],
            $body_template
        );

        $html_filter = static fn() => 'text/html';
        add_filter('wp_mail_content_type', $html_filter);
        $mail_a_sent = wp_mail($user_a->user_email, $subject, wpautop($body_a));
        $mail_b_sent = wp_mail($user_b->user_email, $subject, wpautop($body_b));
        remove_filter('wp_mail_content_type', $html_filter);

        $repo->log_event(
            'email',
            'email_sent',
            sprintf(__('Mutual Match Email Sent: %s (Match #%d)', 'matchmaker'), $user_a->user_email, $match_id),
            $subject,
            [
                'match_id'      => $match_id,
                'user_id'       => (int) $user_a->ID,
                'recipient'     => $user_a->user_email,
                'subject'       => $subject,
                'body_html'     => wpautop($body_a),
                'delivery_stat' => $mail_a_sent ? 'delivered' : 'failed',
            ],
            $match_id,
            (int) $user_a->ID,
            $user_a->user_email,
            $mail_a_sent ? 'success' : 'error'
        );

        $repo->log_event(
            'email',
            'email_sent',
            sprintf(__('Mutual Match Email Sent: %s (Match #%d)', 'matchmaker'), $user_b->user_email, $match_id),
            $subject,
            [
                'match_id'      => $match_id,
                'user_id'       => (int) $user_b->ID,
                'recipient'     => $user_b->user_email,
                'subject'       => $subject,
                'body_html'     => wpautop($body_b),
                'delivery_stat' => $mail_b_sent ? 'delivered' : 'failed',
            ],
            $match_id,
            (int) $user_b->ID,
            $user_b->user_email,
            $mail_b_sent ? 'success' : 'error'
        );
    }

    /**
     * Send email notification to admin when a match pair expires.
     *
     * @param int      $match_id          The match ID.
     * @param string   $reason            Reason for expiry ('declined_by_user', '7_day_idle_timeout').
     * @param int|null $declining_user_id Optional user ID who declined the match.
     * @return void
     */
    public function send_match_expired_admin_email(int $match_id, string $reason, ?int $declining_user_id = null): void
    {
        $repo  = MatchRepository::instance();
        $match = $repo->find_match_by_id($match_id);

        if (!$match) {
            return;
        }

        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }

        $u1_id = (int) ($match['user_one_id'] ?? 0);
        $u2_id = (int) ($match['user_two_id'] ?? 0);
        $u1    = get_userdata($u1_id);
        $u2    = get_userdata($u2_id);

        $p1 = $repo->get_user_pool($u1_id);
        $p2 = $repo->get_user_pool($u2_id);

        $reason_label = __('7-Day Idle Response Timeout', 'matchmaker');
        if ($reason === 'declined_by_user' || $reason === 'rejected') {
            $declining_user = $declining_user_id ? get_userdata($declining_user_id) : null;
            $declining_name = $declining_user ? $declining_user->display_name : ($declining_user_id ? "User #{$declining_user_id}" : __('Member', 'matchmaker'));
            $reason_label   = sprintf(__('Declined by Member (%s)', 'matchmaker'), $declining_name);
        }

        $subject = sprintf(__('[Arab Zawaj Matchmaker] Match Pair #%d Expired: %s', 'matchmaker'), $match_id, $reason_label);

        $admin_pool_url = admin_url('admin.php?page=matchmaking-pool');
        $body = "<h2>" . esc_html__('Match Pair Expiry Alert', 'matchmaker') . "</h2>"
            . "<p><strong>" . esc_html__('Match ID:', 'matchmaker') . "</strong> #" . (int)$match_id . "<br>"
            . "<strong>" . esc_html__('Status:', 'matchmaker') . "</strong> Expired<br>"
            . "<strong>" . esc_html__('Expiry Reason:', 'matchmaker') . "</strong> " . esc_html($reason_label) . "<br>"
            . "<strong>" . esc_html__('Compatibility Score:', 'matchmaker') . "</strong> " . (int)($match['score'] ?? 0) . " / 6</p>"
            . "<h3>" . esc_html__('Member 1 Details', 'matchmaker') . "</h3>"
            . "<p>Name: " . esc_html($u1 ? $u1->display_name : "User #{$u1_id}") . "<br>"
            . "Email: " . esc_html($u1 ? $u1->user_email : 'N/A') . "<br>"
            . "Tier: " . esc_html($repo->format_tier_label($p1['user_type'] ?? 'free')) . "<br>"
            . "Location: " . esc_html($p1['location'] ?? 'N/A') . "</p>"
            . "<h3>" . esc_html__('Member 2 Details', 'matchmaker') . "</h3>"
            . "<p>Name: " . esc_html($u2 ? $u2->display_name : "User #{$u2_id}") . "<br>"
            . "Email: " . esc_html($u2 ? $u2->user_email : 'N/A') . "<br>"
            . "Tier: " . esc_html($repo->format_tier_label($p2['user_type'] ?? 'free')) . "<br>"
            . "Location: " . esc_html($p2['location'] ?? 'N/A') . "</p>"
            . "<p><em>" . esc_html__('The active approved match slot for both members is now free. You can review and approve a new match for either member in the admin portal.', 'matchmaker') . "</em></p>"
            . "<p><a href=\"" . esc_url($admin_pool_url) . "\" style=\"background:#CC723F;color:#fff;padding:10px 18px;text-decoration:none;border-radius:5px;display:inline-block;\">" . esc_html__('Open Candidate Pool &rarr;', 'matchmaker') . "</a></p>";

        add_filter('wp_mail_content_type', static fn() => 'text/html');
        $admin_sent = wp_mail($admin_email, $subject, $body);
        remove_filter('wp_mail_content_type', static fn() => 'text/html');

        $repo->log_event(
            'email',
            'admin_alert_email',
            sprintf(__('Admin Match Expiry Alert Sent: Match #%d', 'matchmaker'), $match_id),
            $subject,
            [
                'match_id'      => $match_id,
                'recipient'     => $admin_email,
                'reason'        => $reason,
                'subject'       => $subject,
                'body_html'     => $body,
                'delivery_stat' => $admin_sent ? 'delivered' : 'failed',
            ],
            $match_id,
            null,
            $admin_email,
            $admin_sent ? 'success' : 'error'
        );
    }
}
