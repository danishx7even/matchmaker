<?php
/**
 * View: Admin Event Click Analytics — Single Event Member Breakdown
 *
 * Available variables:
 *   @var int                              $event_id
 *   @var WP_Post|null                     $event
 *   @var array<int, array<string, mixed>> $clicks
 *   @var array{total_clicks: int, unique_users: int} $summary
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpt_slug   = (string) get_option('mm_events_cpt_slug', 'event');
$back_url   = admin_url('edit.php?post_type=' . urlencode($cpt_slug) . '&page=matchmaking-event-clicks');
$export_url = wp_nonce_url(
    admin_url('edit.php?post_type=' . urlencode($cpt_slug) . '&page=matchmaking-event-clicks&mm_action=export_event_clicks&event_id=' . $event_id),
    'mm_export_event_clicks'
);

$event_title = $event ? $event->post_title : sprintf(__('Event #%d', 'matchmaker'), $event_id);
$event_date  = ($event && !empty($event->post_date)) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->post_date)) : '—';
?>
<div class="mm-admin-header-wrap" style="margin-bottom:20px;">
    <div style="margin-bottom:12px;">
        <a href="<?php echo esc_url($back_url); ?>" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-arrow-left-alt" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
            <?php esc_html_e('Back to All Events', 'matchmaker'); ?>
        </a>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 class="wp-heading-inline" style="margin:0; font-size:24px; color:#1e293b;">
                <?php echo esc_html($event_title); ?>
                <span style="font-size:16px; font-weight:normal; color:#64748b;">(#<?php echo esc_html((string) $event_id); ?>)</span>
            </h1>
            <p class="description" style="margin-top:6px; color:#64748b;">
                <strong><?php esc_html_e('Published Date:', 'matchmaker'); ?></strong> <?php echo esc_html($event_date); ?>
            </p>
        </div>

        <div>
            <?php if (!empty($clicks)) : ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button button-primary" style="background:#CC723F; border-color:#CC723F; display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-download" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span>
                    <?php esc_html_e('Export Attendees CSV', 'matchmaker'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<hr class="wp-header-end">

<!-- Metric Summary Cards -->
<div class="mm-metrics-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin:20px 0 25px;">
    <div class="az-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#64748b; letter-spacing:0.5px; margin-bottom:6px;">
            <?php esc_html_e('Total "Join" Clicks', 'matchmaker'); ?>
        </div>
        <div style="font-size:28px; font-weight:700; color:#CC723F; font-family:'Cormorant SC', Georgia, serif;">
            <?php echo esc_html((string) ($summary['total_clicks'] ?? 0)); ?>
        </div>
    </div>

    <div class="az-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#64748b; letter-spacing:0.5px; margin-bottom:6px;">
            <?php esc_html_e('Unique Members Clicked', 'matchmaker'); ?>
        </div>
        <div style="font-size:28px; font-weight:700; color:#0D9488; font-family:'Cormorant SC', Georgia, serif;">
            <?php echo esc_html((string) ($summary['unique_users'] ?? 0)); ?>
        </div>
    </div>
</div>

<!-- Member List Table -->
<table class="wp-list-table widefat fixed striped" style="border-radius:8px; overflow:hidden; border:1px solid #e2e8f0;">
    <thead>
        <tr>
            <th style="width:50px;"><?php esc_html_e('Avatar', 'matchmaker'); ?></th>
            <th style="width:22%;"><?php esc_html_e('Member Name', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Contact Information', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Membership Tier', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Gender & Location', 'matchmaker'); ?></th>
            <th style="text-align:center; width:110px;"><?php esc_html_e('Click Count', 'matchmaker'); ?></th>
            <th><?php esc_html_e('First Clicked', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Last Clicked', 'matchmaker'); ?></th>
            <th style="width:130px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($clicks)) : ?>
            <tr>
                <td colspan="9" style="text-align:center; padding:32px 16px; color:#64748b;">
                    <p style="font-size:15px; margin:0 0 8px;"><strong><?php esc_html_e('No members have clicked "Join Event" for this event yet.', 'matchmaker'); ?></strong></p>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ($clicks as $row) :
                $uid        = (int) ($row['user_id'] ?? 0);
                $full_name  = (string) ($row['full_name'] ?? "User #{$uid}");
                $email      = (string) ($row['user_email'] ?? '');
                $phone      = (string) ($row['phone'] ?? '');
                $gender     = (string) ($row['gender'] ?? '');
                $tier       = (string) ($row['user_type'] ?? '');
                $count      = (int) ($row['click_count'] ?? 1);
                $location   = trim(($row['city'] ?? '') . ', ' . ($row['country'] ?? ''), ', ');
                $first_time = !empty($row['first_clicked_at']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $row['first_clicked_at'])) : '—';
                $last_time  = !empty($row['last_clicked_at']) ? human_time_diff(strtotime((string) $row['last_clicked_at']), current_time('timestamp', 1)) . ' ' . __('ago', 'matchmaker') : '—';
                $view_user_url = admin_url('admin.php?page=matchmaking-pool&view_user=' . $uid);

                $tier_label = match ($tier) {
                    'monthly'    => 'Monthly Member',
                    'event'      => 'Event Pass',
                    'one_on_one' => '1-on-1 Member',
                    'free'       => 'Free Member',
                    default      => !empty($tier) ? ucfirst($tier) : 'Standard User',
                };
            ?>
                <tr>
                    <td>
                        <?php echo get_avatar($uid, 36, '', '', ['class' => 'mm-admin-avatar', 'style' => 'border-radius:50%;']); ?>
                    </td>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url($view_user_url); ?>" style="color:#1e293b; font-size:14px;">
                                <?php echo esc_html($full_name); ?>
                            </a>
                        </strong>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">
                            @<?php echo esc_html((string) ($row['user_login'] ?? '')); ?>
                        </div>
                    </td>
                    <td>
                        <div><a href="mailto:<?php echo esc_attr($email); ?>" style="color:#0284c7;"><?php echo esc_html($email); ?></a></div>
                        <?php if (!empty($phone)) : ?>
                            <div style="font-size:12px; color:#475569; margin-top:2px;">📞 <?php echo esc_html($phone); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="mm-tier-badge mm-tier-<?php echo esc_attr($tier); ?>" style="display:inline-block; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:600; background:#f1f5f9; color:#334155;">
                            <?php echo esc_html($tier_label); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($gender)) : ?>
                            <div><strong><?php echo esc_html(ucfirst($gender)); ?></strong></div>
                        <?php endif; ?>
                        <div style="font-size:12px; color:#64748b;"><?php echo esc_html($location ?: '—'); ?></div>
                    </td>
                    <td style="text-align:center;">
                        <span style="display:inline-block; padding:4px 10px; border-radius:12px; background:#FEF3C7; color:#92400E; font-weight:700; font-size:13px;">
                            <?php echo esc_html((string) $count); ?> <?php echo ($count === 1) ? esc_html__('click', 'matchmaker') : esc_html__('clicks', 'matchmaker'); ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?php echo esc_html($first_time); ?></td>
                    <td>
                        <span title="<?php echo esc_attr((string) ($row['last_clicked_at'] ?? '')); ?>" style="font-size:13px;">
                            <?php echo esc_html($last_time); ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <a href="<?php echo esc_url($view_user_url); ?>" class="button button-small" style="font-weight:600;">
                            <?php esc_html_e('View Profile', 'matchmaker'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
