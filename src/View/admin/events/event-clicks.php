<?php
/**
 * View: Admin Event Click Analytics — All Events Overview
 *
 * Available variables:
 *   @var array<int, array<string, mixed>> $events_summary
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpt_slug = (string) get_option('mm_events_cpt_slug', 'event');
$total_events = count($events_summary);
$total_clicks = array_sum(array_column($events_summary, 'total_clicks'));
$total_unique = array_sum(array_column($events_summary, 'unique_users'));
?>
<div class="mm-admin-header-wrap" style="margin-bottom:20px;">
    <h1 class="wp-heading-inline" style="display:flex; align-items:center; gap:10px;">
        <span class="dashicons dashicons-analytics" style="font-size:28px; width:28px; height:28px; color:#CC723F;"></span>
        <?php esc_html_e('Event "Join Event" Click Analytics', 'matchmaker'); ?>
    </h1>
    <p class="description" style="margin-top:6px; color:#64748b; font-size:14px;">
        <?php esc_html_e('Track and monitor member engagement when users click the "Join Event" button on event cards in the Member Portal.', 'matchmaker'); ?>
    </p>
</div>
<hr class="wp-header-end">

<!-- Metric Summary Cards -->
<div class="mm-metrics-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin:20px 0 25px;">
    <div class="az-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#64748b; letter-spacing:0.5px; margin-bottom:6px;">
            <?php esc_html_e('Tracked Events', 'matchmaker'); ?>
        </div>
        <div style="font-size:28px; font-weight:700; color:#1e293b; font-family:'Cormorant SC', Georgia, serif;">
            <?php echo esc_html((string) $total_events); ?>
        </div>
    </div>

    <div class="az-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#64748b; letter-spacing:0.5px; margin-bottom:6px;">
            <?php esc_html_e('Total "Join" Clicks', 'matchmaker'); ?>
        </div>
        <div style="font-size:28px; font-weight:700; color:#CC723F; font-family:'Cormorant SC', Georgia, serif;">
            <?php echo esc_html((string) $total_clicks); ?>
        </div>
    </div>

    <div class="az-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#64748b; letter-spacing:0.5px; margin-bottom:6px;">
            <?php esc_html_e('Unique Members Engaged', 'matchmaker'); ?>
        </div>
        <div style="font-size:28px; font-weight:700; color:#0D9488; font-family:'Cormorant SC', Georgia, serif;">
            <?php echo esc_html((string) $total_unique); ?>
        </div>
    </div>
</div>

<!-- Events Table -->
<table class="wp-list-table widefat fixed striped" style="border-radius:8px; overflow:hidden; border:1px solid #e2e8f0;">
    <thead>
        <tr>
            <th style="width:70px;"><?php esc_html_e('Event ID', 'matchmaker'); ?></th>
            <th style="width:30%;"><?php esc_html_e('Event Title', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Event Published Date', 'matchmaker'); ?></th>
            <th style="text-align:center;"><?php esc_html_e('Total Clicks', 'matchmaker'); ?></th>
            <th style="text-align:center;"><?php esc_html_e('Unique Members', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Last Click Recorded', 'matchmaker'); ?></th>
            <th style="width:160px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($events_summary)) : ?>
            <tr>
                <td colspan="7" style="text-align:center; padding:32px 16px; color:#64748b;">
                    <p style="font-size:15px; margin:0 0 8px;"><strong><?php esc_html_e('No event clicks recorded yet.', 'matchmaker'); ?></strong></p>
                    <p style="margin:0; font-size:13px;"><?php esc_html_e('When members click the "Join Event" button on event cards in the portal, click analytics will appear here automatically.', 'matchmaker'); ?></p>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ($events_summary as $item) :
                $event_id    = (int) ($item['event_id'] ?? 0);
                $title       = (string) ($item['event_title'] ?? "Event #{$event_id}");
                $date        = !empty($item['event_date']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $item['event_date'])) : '—';
                $last_click  = !empty($item['last_clicked_at']) ? human_time_diff(strtotime((string) $item['last_clicked_at']), current_time('timestamp', 1)) . ' ' . __('ago', 'matchmaker') : '—';
                $detail_url  = admin_url('edit.php?post_type=' . urlencode($cpt_slug) . '&page=matchmaking-event-clicks&event_id=' . $event_id);
                $edit_url    = admin_url('post.php?post=' . $event_id . '&action=edit');
            ?>
                <tr>
                    <td><strong>#<?php echo esc_html((string) $event_id); ?></strong></td>
                    <td>
                        <strong><a href="<?php echo esc_url($detail_url); ?>" style="font-size:14px; color:#1e293b;"><?php echo esc_html($title); ?></a></strong>
                        <div class="row-actions" style="margin-top:4px;">
                            <span class="view"><a href="<?php echo esc_url($detail_url); ?>" style="color:#CC723F; font-weight:600;"><?php esc_html_e('View Member Breakdown', 'matchmaker'); ?></a> | </span>
                            <span class="edit"><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit Event', 'matchmaker'); ?></a></span>
                        </div>
                    </td>
                    <td><?php echo esc_html($date); ?></td>
                    <td style="text-align:center;">
                        <span style="display:inline-block; padding:4px 10px; border-radius:12px; background:#FEF3C7; color:#92400E; font-weight:700; font-size:13px;">
                            <?php echo esc_html((string) ($item['total_clicks'] ?? 0)); ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span style="display:inline-block; padding:4px 10px; border-radius:12px; background:#CCFBF1; color:#0F766E; font-weight:700; font-size:13px;">
                            <?php echo esc_html((string) ($item['unique_users'] ?? 0)); ?>
                        </span>
                    </td>
                    <td>
                        <span title="<?php echo esc_attr((string) ($item['last_clicked_at'] ?? '')); ?>">
                            <?php echo esc_html($last_click); ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <a href="<?php echo esc_url($detail_url); ?>" class="button button-secondary" style="font-weight:600;">
                            <?php esc_html_e('View Details →', 'matchmaker'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
