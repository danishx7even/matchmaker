<?php
declare(strict_types=1);

namespace Matchmaker\Service;

use Matchmaker\Repository\MatchRepository;
use Matchmaker\Core\PMProSync;
use Matchmaker\Service\ProfileService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BulkEmailService
 *
 * Handles targeted recipient resolution, dynamic template placeholder interpolation,
 * and asynchronous batch queueing via Action Scheduler with audit logging.
 *
 * @package Matchmaker\Service
 * @since   2.8.0
 */
class BulkEmailService
{
    private static ?self $instance = null;

    public const HOOK_PROCESS_BATCH = 'mm_process_bulk_email_batch';
    public const BATCH_SIZE = 20;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action(self::HOOK_PROCESS_BATCH, [$this, 'process_batch'], 10, 1);
    }

    /**
     * Get list of supported dynamic placeholders and descriptions.
     *
     * @return array<string, string>
     */
    public function get_placeholders_guide(): array
    {
        return [
            '{name}'          => __('Member\'s full display name', 'matchmaker'),
            '{first_name}'    => __('Member\'s first name', 'matchmaker'),
            '{email}'         => __('Member\'s registered email address', 'matchmaker'),
            '{user_type}'     => __('Formatted base membership tier (e.g., Monthly Member, Free Member)', 'matchmaker'),
            '{services}'      => __('List of active purchased services (e.g., Private Matchmaking, or None)', 'matchmaker'),
            '{dashboard_url}' => __('Direct link to Member Portal Dashboard', 'matchmaker'),
            '{login_url}'     => __('Login page URL', 'matchmaker'),
            '{site_name}'     => __('Website title', 'matchmaker'),
            '{site_url}'      => __('Website home URL', 'matchmaker'),
        ];
    }

    /**
     * Get persistent default subject line.
     *
     * @return string
     */
    public function get_default_subject(): string
    {
        $default = 'Important Update from {site_name} regarding your Matchmaking Profile';
        return (string) get_option('mm_bulk_email_default_subject', $default);
    }

    /**
     * Get persistent default HTML email template.
     *
     * @return string
     */
    public function get_default_template(): string
    {
        $default = "<p>Dear {first_name},</p>\n\n"
            . "<p>We are reaching out with an important update regarding your matchmaking profile on <strong>{site_name}</strong>.</p>\n\n"
            . "<p>Your current membership status: <strong>{user_type}</strong><br>\n"
            . "Active services: <strong>{services}</strong></p>\n\n"
            . "<p>Please log in to your dashboard to review your recommendations and updates:</p>\n"
            . "<p><a href=\"{dashboard_url}\" style=\"background:#CC723F; color:#ffffff; padding:10px 22px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;\">View Your Dashboard &rarr;</a></p>\n\n"
            . "<p>Warm regards,<br>\n"
            . "The {site_name} Team</p>";

        return (string) get_option('mm_bulk_email_default_template', $default);
    }

    /**
     * Save default subject and template.
     *
     * @param string $subject
     * @param string $template
     * @return bool
     */
    public function save_default_template(string $subject, string $template): bool
    {
        update_option('mm_bulk_email_default_subject', sanitize_text_field($subject));
        update_option('mm_bulk_email_default_template', wp_kses_post($template));
        return true;
    }

    /**
     * Resolve list of recipient user IDs based on criteria or explicit member IDs.
     *
     * @param array<string, mixed> $args
     * @return array<int, int> Unique user IDs
     */
    public function resolve_recipients(array $args): array
    {
        $mode = (string) ($args['mode'] ?? 'criteria');

        if ($mode === 'members') {
            $raw_ids = $args['member_ids'] ?? [];
            if (is_string($raw_ids)) {
                $raw_ids = array_filter(array_map('intval', explode(',', $raw_ids)));
            }
            if (!is_array($raw_ids)) {
                return [];
            }
            $clean_ids = [];
            foreach ($raw_ids as $uid) {
                $uid = (int) $uid;
                if ($uid > 0 && get_userdata($uid)) {
                    $clean_ids[] = $uid;
                }
            }
            return array_values(array_unique($clean_ids));
        }

        // Segment by Criteria
        $filters = [];

        // Tier filter
        $tier = (string) ($args['tier'] ?? '');
        if (!empty($tier) && in_array($tier, ['monthly', 'event', 'free', 'one_on_one'], true)) {
            $filters['user_type'] = $tier;
        }

        // Parent applying filter
        if (isset($args['parent_applying']) && $args['parent_applying'] !== '' && $args['parent_applying'] !== 'all') {
            $filters['is_parent_applying'] = (int) $args['parent_applying'];
        }

        // Service filter
        $service_filter = (string) ($args['service'] ?? '');
        if ($service_filter === 'has_service' || $service_filter === '1') {
            $filters['has_one_on_one'] = 1;
        } elseif ($service_filter === 'no_service' || $service_filter === '0') {
            $filters['has_one_on_one'] = 0;
        }

        $repo = MatchRepository::instance();
        $candidates = $repo->search_pool($filters);
        $user_ids = [];

        foreach ($candidates as $c) {
            $uid = (int) ($c['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            // If specific PMPro service level ID filter requested
            if (is_numeric($service_filter) && (int) $service_filter > 0) {
                $target_level_id = (int) $service_filter;
                $active_srv = class_exists(PMProSync::class) ? PMProSync::instance()->get_user_active_services($uid) : [];
                $has_level = false;
                foreach ($active_srv as $srv) {
                    if ((int) ($srv['id'] ?? 0) === $target_level_id) {
                        $has_level = true;
                        break;
                    }
                }
                if (!$has_level) {
                    continue;
                }
            }

            $user_ids[] = $uid;
        }

        return array_values(array_unique($user_ids));
    }

    /**
     * Interpolate dynamic placeholders into content string for a specific member.
     *
     * @param string $content HTML or plain text
     * @param int    $user_id WordPress user ID
     * @return string
     */
    public function interpolate(string $content, int $user_id): string
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return $content;
        }

        $repo = MatchRepository::instance();
        $user_type = ProfileService::instance()->get_user_type($user_id);
        $tier_label = $repo->format_tier_label($user_type);

        $active_srv = class_exists(PMProSync::class) ? PMProSync::instance()->get_user_active_services($user_id) : [];
        $services_names = array_column($active_srv, 'name');
        $services_str = !empty($services_names) ? implode(', ', $services_names) : 'None';

        $dashboard_url = ProfileService::instance()->get_dashboard_url();
        $login_url     = function_exists('pmpro_url') ? pmpro_url('login') : wp_login_url();
        $site_name     = get_bloginfo('name') ?: 'Arab Zawaj';
        $site_url      = home_url('/');

        $first_name = $user->first_name ?: ($user->display_name ?: 'Member');
        $full_name  = $user->display_name ?: $first_name;

        $replacements = [
            '{name}'          => esc_html($full_name),
            '{first_name}'    => esc_html($first_name),
            '{email}'         => esc_html($user->user_email),
            '{user_type}'     => esc_html($tier_label),
            '{services}'      => esc_html($services_str),
            '{dashboard_url}' => esc_url($dashboard_url),
            '{login_url}'     => esc_url($login_url),
            '{site_name}'     => esc_html($site_name),
            '{site_url}'      => esc_url($site_url),
        ];

        return strtr($content, $replacements);
    }

    /**
     * Queue a bulk email campaign into Action Scheduler chunked batches.
     *
     * @param array<int, int> $recipient_ids
     * @param string          $subject
     * @param string          $template
     * @return array<string, mixed>
     */
    public function queue_bulk_email(array $recipient_ids, string $subject, string $template): array
    {
        $clean_recipients = array_values(array_unique(array_filter(array_map('intval', $recipient_ids))));
        if (empty($clean_recipients)) {
            return ['success' => false, 'message' => __('No valid recipients found for this selection.', 'matchmaker')];
        }

        if (empty(trim($subject))) {
            return ['success' => false, 'message' => __('Email subject line cannot be empty.', 'matchmaker')];
        }

        if (empty(trim($template))) {
            return ['success' => false, 'message' => __('Email body template cannot be empty.', 'matchmaker')];
        }

        $rand_suffix = function_exists('wp_generate_password') ? wp_generate_password(6, false) : substr(md5((string) mt_rand()), 0, 6);
        $campaign_id = 'campaign_' . time() . '_' . $rand_suffix;
        $chunks = array_chunk($clean_recipients, self::BATCH_SIZE);
        $total_batches = count($chunks);

        $repo = MatchRepository::instance();

        // Initial Campaign Log
        $repo->log_event(
            'email',
            'bulk_email_started',
            sprintf(__('Bulk email campaign "%s" started', 'matchmaker'), $campaign_id),
            sprintf(
                __('Queued bulk email to %d recipients across %d batches. Subject: %s', 'matchmaker'),
                count($clean_recipients),
                $total_batches,
                $subject
            ),
            [
                'campaign_id'      => $campaign_id,
                'total_recipients' => count($clean_recipients),
                'total_batches'    => $total_batches,
                'subject'          => $subject,
            ],
            null,
            null,
            null,
            'info'
        );

        foreach ($chunks as $index => $chunk) {
            $batch_args = [
                'campaign_id'   => $campaign_id,
                'user_ids'      => $chunk,
                'subject'       => $subject,
                'template'      => $template,
                'batch_index'   => $index + 1,
                'total_batches' => $total_batches,
            ];

            // Schedule chunked action with slight staggering (20 seconds between chunks)
            $delay = $index * 20;

            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + $delay, self::HOOK_PROCESS_BATCH, [$batch_args], 'matchmaker-bulk-email');
            } else {
                // Fallback for test/local environments without Action Scheduler active
                $this->process_batch($batch_args);
            }
        }

        return [
            'success'          => true,
            'campaign_id'      => $campaign_id,
            'total_recipients' => count($clean_recipients),
            'total_batches'    => $total_batches,
            'message'          => sprintf(
                __('Bulk email campaign queued successfully! %d recipients scheduled across %d background batches.', 'matchmaker'),
                count($clean_recipients),
                $total_batches
            ),
        ];
    }

    /**
     * Action Scheduler Callback: Process a single batch of recipients.
     *
     * @param array<string, mixed> $args
     * @return void
     */
    public function process_batch(array $args): void
    {
        $campaign_id = (string) ($args['campaign_id'] ?? 'unknown');
        $user_ids    = (array) ($args['user_ids'] ?? []);
        $raw_subject = (string) ($args['subject'] ?? '');
        $raw_tpl     = (string) ($args['template'] ?? '');
        $batch_idx   = (int) ($args['batch_index'] ?? 1);
        $total_b     = (int) ($args['total_batches'] ?? 1);

        $sent_count = 0;
        $failed_count = 0;
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $repo = MatchRepository::instance();

        foreach ($user_ids as $uid) {
            $uid = (int) $uid;
            $user = get_userdata($uid);
            if (!$user || empty($user->user_email)) {
                $failed_count++;
                continue;
            }

            $subject = $this->interpolate($raw_subject, $uid);
            $message = $this->interpolate($raw_tpl, $uid);

            $mail_sent = wp_mail($user->user_email, $subject, $message, $headers);

            if ($mail_sent) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }

        // Log batch completion
        $repo->log_event(
            'email',
            'bulk_email_batch_complete',
            sprintf(__('Bulk Email Batch %d/%d processed (%s)', 'matchmaker'), $batch_idx, $total_b, $campaign_id),
            sprintf(
                __('Batch %d/%d completed: %d sent, %d failed.', 'matchmaker'),
                $batch_idx,
                $total_b,
                $sent_count,
                $failed_count
            ),
            [
                'campaign_id' => $campaign_id,
                'batch_index' => $batch_idx,
                'sent_count'  => $sent_count,
                'failed_count'=> $failed_count,
            ],
            null,
            null,
            null,
            ($failed_count > 0 && $sent_count === 0) ? 'error' : 'success'
        );
    }
}
