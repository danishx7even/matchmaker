<?php
/**
 * View: Admin Logs – Tab 2 (Notification & Transactional Email Logs)
 *
 * Available variables:
 *   @var array<int, array<string, mixed>> $logs
 *   @var int                              $total_logs
 *   @var int                              $current_page
 *   @var int                              $total_pages
 *   @var string                           $search
 *   @var string                           $event_type
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mm-card" style="margin-top:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:15px;">
        <div>
            <h3 style="margin:0;"><?php esc_html_e('In-App Notifications & Transactional Email Dispatches', 'matchmaker'); ?></h3>
            <p class="description" style="margin:4px 0 0;">
                <?php echo esc_html(sprintf(__('Viewing %d recorded in-app alerts and transactional email transmission logs.', 'matchmaker'), $total_logs)); ?>
            </p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <form method="get" class="mm-filter-bar" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="matchmaking-logs">
        <input type="hidden" name="tab" value="notification_logs">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search recipient, subject, or message...', 'matchmaker'); ?>" class="regular-text">

        <select name="event_type">
            <option value=""><?php esc_html_e('All Channels & Types', 'matchmaker'); ?></option>
            <option value="email_sent" <?php selected($event_type, 'email_sent'); ?>><?php esc_html_e('Member Approval Email', 'matchmaker'); ?></option>
            <option value="admin_alert_email" <?php selected($event_type, 'admin_alert_email'); ?>><?php esc_html_e('Admin Alert Email', 'matchmaker'); ?></option>
            <option value="verification_code_sent" <?php selected($event_type, 'verification_code_sent'); ?>><?php esc_html_e('Verification Code Sent', 'matchmaker'); ?></option>
            <option value="verification_code_failed" <?php selected($event_type, 'verification_code_failed'); ?>><?php esc_html_e('Verification Code Failed', 'matchmaker'); ?></option>
            <option value="email_verified" <?php selected($event_type, 'email_verified'); ?>><?php esc_html_e('Email Verified Successfully', 'matchmaker'); ?></option>
            <option value="match_approved" <?php selected($event_type, 'match_approved'); ?>><?php esc_html_e('In-App Notification (Match Approved)', 'matchmaker'); ?></option>
            <option value="match_revealed" <?php selected($event_type, 'match_revealed'); ?>><?php esc_html_e('In-App Notification (Contact Revealed)', 'matchmaker'); ?></option>
        </select>

        <input type="submit" class="button" value="<?php esc_attr_e('Filter Logs', 'matchmaker'); ?>">
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:65px;"><?php esc_html_e('Log ID', 'matchmaker'); ?></th>
                <th style="width:110px;"><?php esc_html_e('Channel', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Title / Subject', 'matchmaker'); ?></th>
                <th style="width:200px;"><?php esc_html_e('Recipient', 'matchmaker'); ?></th>
                <th style="width:100px;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                <th style="width:150px;"><?php esc_html_e('Timestamp', 'matchmaker'); ?></th>
                <th style="width:110px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr><td colspan="7"><?php esc_html_e('No notification or email logs recorded yet.', 'matchmaker'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($logs as $log) :
                    $lid     = (int) $log['id'];
                    $channel = (string) $log['log_type'];
                    $ev_type = (string) $log['event_type'];
                    $st      = (string) ($log['status'] ?? 'info');
                    $recipient = (string) ($log['recipient'] ?? '');
                    $meta_json = $log['details_json'] ?? '';
                    $meta_arr  = !empty($meta_json) ? json_decode($meta_json, true) : [];
                    $body_html = is_array($meta_arr) ? ($meta_arr['body_html'] ?? '') : '';
                ?>
                    <tr>
                        <td><strong>#<?php echo $lid; ?></strong></td>
                        <td>
                            <?php if ($channel === 'email') : ?>
                                <span style="background:#e0e7ff; color:#3730a3; padding:2px 8px; border-radius:10px; font-weight:700; font-size:11px;">
                                    ✉️ <?php esc_html_e('EMAIL', 'matchmaker'); ?>
                                </span>
                            <?php else : ?>
                                <span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; font-weight:700; font-size:11px;">
                                    🔔 <?php esc_html_e('IN-APP', 'matchmaker'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($log['title']); ?></strong>
                            <?php if (!empty($log['message'])) : ?>
                                <br><small style="color:#64748b;"><?php echo esc_html(wp_trim_words($log['message'], 14)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($recipient)) : ?>
                                <code><?php echo esc_html($recipient); ?></code>
                            <?php elseif (!empty($log['user_id'])) : ?>
                                <?php $u = get_userdata((int)$log['user_id']); ?>
                                <strong><?php echo esc_html($u ? $u->display_name : 'User #' . $log['user_id']); ?></strong>
                            <?php else : ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status_class = match ($st) {
                                'success' => 'background:#dcfce7; color:#15803d;',
                                'warning' => 'background:#fef3c7; color:#b45309;',
                                'error'   => 'background:#fee2e2; color:#b91c1c;',
                                default   => 'background:#f1f5f9; color:#475569;',
                            };
                            ?>
                            <span style="<?php echo esc_attr($status_class); ?> padding:2px 8px; border-radius:10px; font-weight:600; font-size:11px; text-transform:uppercase;">
                                <?php echo esc_html($st); ?>
                            </span>
                        </td>
                        <td><small><?php echo esc_html($log['created_at']); ?></small></td>
                        <td style="text-align:center;">
                            <button type="button" class="button button-small mm-btn-view-log"
                                data-log-id="<?php echo $lid; ?>"
                                data-log-title="<?php echo esc_attr($log['title']); ?>"
                                data-log-type="<?php echo esc_attr($channel); ?>"
                                data-event-type="<?php echo esc_attr($ev_type); ?>"
                                data-recipient="<?php echo esc_attr($recipient); ?>"
                                data-status="<?php echo esc_attr($st); ?>"
                                data-created-at="<?php echo esc_attr($log['created_at']); ?>"
                                data-message="<?php echo esc_attr($log['message'] ?? ''); ?>"
                                data-email-body="<?php echo esc_attr($body_html); ?>"
                                data-payload="<?php echo esc_attr($meta_json); ?>">
                                <?php echo ($channel === 'email') ? esc_html__('View Email', 'matchmaker') : esc_html__('Inspect', 'matchmaker'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav" style="margin-top:15px;">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html(sprintf(__('%d items', 'matchmaker'), $total_logs)); ?></span>
                <?php
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => __('&laquo; Previous', 'matchmaker'),
                    'next_text' => __('Next &raquo;', 'matchmaker'),
                    'total'     => $total_pages,
                    'current'   => $current_page,
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
