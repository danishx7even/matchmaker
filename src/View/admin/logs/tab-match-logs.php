<?php
/**
 * View: Admin Logs – Tab 1 (Match Lifecycle & Engine Logs)
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
            <h3 style="margin:0;"><?php esc_html_e('Match Generation, Lifecycle & Member Response Logs', 'matchmaker'); ?></h3>
            <p class="description" style="margin:4px 0 0;">
                <?php echo esc_html(sprintf(__('Viewing %d recorded match engine, approval, rejection, and member response events.', 'matchmaker'), $total_logs)); ?>
            </p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <form method="get" class="mm-filter-bar" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="matchmaking-logs">
        <input type="hidden" name="tab" value="match_logs">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search in logs...', 'matchmaker'); ?>" class="regular-text">

        <select name="event_type">
            <option value=""><?php esc_html_e('All Event Types', 'matchmaker'); ?></option>
            <option value="matching_completed" <?php selected($event_type, 'matching_completed'); ?>><?php esc_html_e('Matching Run Completed', 'matchmaker'); ?></option>
            <option value="matching_skipped" <?php selected($event_type, 'matching_skipped'); ?>><?php esc_html_e('Matching Run Skipped', 'matchmaker'); ?></option>
            <option value="matching_no_candidates" <?php selected($event_type, 'matching_no_candidates'); ?>><?php esc_html_e('0 Candidates Qualified', 'matchmaker'); ?></option>
            <option value="admin_approved" <?php selected($event_type, 'admin_approved'); ?>><?php esc_html_e('Admin Approved', 'matchmaker'); ?></option>
            <option value="admin_rejected" <?php selected($event_type, 'admin_rejected'); ?>><?php esc_html_e('Admin Rejected', 'matchmaker'); ?></option>
            <option value="admin_approval_blocked" <?php selected($event_type, 'admin_approval_blocked'); ?>><?php esc_html_e('Approval Blocked', 'matchmaker'); ?></option>
            <option value="user_accepted" <?php selected($event_type, 'user_accepted'); ?>><?php esc_html_e('Member Accepted', 'matchmaker'); ?></option>
            <option value="user_rejected" <?php selected($event_type, 'user_rejected'); ?>><?php esc_html_e('Member Declined', 'matchmaker'); ?></option>
            <option value="test_data_reset" <?php selected($event_type, 'test_data_reset'); ?>><?php esc_html_e('Test Data Reset', 'matchmaker'); ?></option>
        </select>

        <input type="submit" class="button" value="<?php esc_attr_e('Filter Logs', 'matchmaker'); ?>">
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:65px;"><?php esc_html_e('Log ID', 'matchmaker'); ?></th>
                <th style="width:140px;"><?php esc_html_e('Event Type', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Title / Event Summary', 'matchmaker'); ?></th>
                <th style="width:120px;"><?php esc_html_e('Target Entity', 'matchmaker'); ?></th>
                <th style="width:100px;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                <th style="width:150px;"><?php esc_html_e('Timestamp', 'matchmaker'); ?></th>
                <th style="width:100px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr><td colspan="7"><?php esc_html_e('No match activity logs recorded yet.', 'matchmaker'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($logs as $log) :
                    $lid     = (int) $log['id'];
                    $ev_type = (string) $log['event_type'];
                    $st      = (string) ($log['status'] ?? 'info');
                    $ref_id  = (int) ($log['reference_id'] ?? 0);
                    $uid     = (int) ($log['user_id'] ?? 0);
                    $meta_json = is_array($log['details_json'] ?? null)
                        ? (function_exists('wp_json_encode') ? wp_json_encode($log['details_json']) : json_encode($log['details_json']))
                        : (string) ($log['details_json'] ?? '');
                ?>
                    <tr>
                        <td><strong>#<?php echo $lid; ?></strong></td>
                        <td>
                            <code style="font-size:11px; background:#f1f5f9; color:#0f172a; padding:2px 6px; border-radius:4px;"><?php echo esc_html($ev_type); ?></code>
                        </td>
                        <td>
                            <strong><?php echo esc_html($log['title']); ?></strong>
                            <?php if (!empty($log['message'])) : ?>
                                <br><small style="color:#64748b;"><?php echo esc_html(wp_trim_words($log['message'], 16)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ref_id > 0) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-matches&view_match=' . $ref_id)); ?>">Match #<?php echo $ref_id; ?></a>
                            <?php elseif ($uid > 0) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $uid)); ?>">User #<?php echo $uid; ?></a>
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
                                data-log-type="<?php echo esc_attr($log['log_type']); ?>"
                                data-event-type="<?php echo esc_attr($ev_type); ?>"
                                data-status="<?php echo esc_attr($st); ?>"
                                data-created-at="<?php echo esc_attr($log['created_at']); ?>"
                                data-message="<?php echo esc_attr($log['message'] ?? ''); ?>"
                                data-payload="<?php echo esc_attr($meta_json); ?>">
                                <?php esc_html_e('Inspect', 'matchmaker'); ?>
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
