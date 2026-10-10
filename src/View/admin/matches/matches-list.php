<?php
/**
 * View: Admin Matches Queue List View
 *
 * Available variables:
 *   @var array<int, array<string, mixed>> $matches
 *   @var string                           $search
 *   @var string                           $status
 *   @var string                           $source
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
?>
<h1 class="wp-heading-inline"><?php esc_html_e('All Matches Queue', 'matchmaker'); ?></h1>
<hr class="wp-header-end">

<form method="get" class="mm-filter-bar">
    <input type="hidden" name="page" value="matchmaking-matches">
    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search match ID, name, email', 'matchmaker'); ?>" class="regular-text">

    <select name="filter_status">
        <option value=""><?php esc_html_e('All Match Statuses', 'matchmaker'); ?></option>
        <option value="pending_review" <?php selected($status, 'pending_review'); ?>><?php esc_html_e('Pending Review', 'matchmaker'); ?></option>
        <option value="approved" <?php selected($status, 'approved'); ?>><?php esc_html_e('Approved', 'matchmaker'); ?></option>
        <option value="matched" <?php selected($status, 'matched'); ?>><?php esc_html_e('Mutual Match', 'matchmaker'); ?></option>
        <option value="expired" <?php selected($status, 'expired'); ?>><?php esc_html_e('Expired', 'matchmaker'); ?></option>
        <option value="rejected" <?php selected($status, 'rejected'); ?>><?php esc_html_e('Member Rejected', 'matchmaker'); ?></option>
        <option value="admin_rejected" <?php selected($status, 'admin_rejected'); ?>><?php esc_html_e('Admin Rejected', 'matchmaker'); ?></option>
    </select>

    <select name="filter_source">
        <option value=""><?php esc_html_e('All Sources', 'matchmaker'); ?></option>
        <option value="auto" <?php selected($source, 'auto'); ?>><?php esc_html_e('Auto Engine', 'matchmaker'); ?></option>
        <option value="manual" <?php selected($source, 'manual'); ?>><?php esc_html_e('Manual Matchmaker', 'matchmaker'); ?></option>
    </select>

    <input type="submit" class="button" value="<?php esc_attr_e('Filter Matches', 'matchmaker'); ?>">
</form>

<div class="mm-table-responsive">
    <table class="wp-list-table widefat striped" style="min-width:860px; table-layout:auto;">
        <thead>
            <tr>
                <th style="width:65px; white-space:nowrap;"><?php esc_html_e('Match ID', 'matchmaker'); ?></th>
                <th style="min-width:160px; white-space:nowrap;"><?php esc_html_e('User 1 (Initiator)', 'matchmaker'); ?></th>
                <th style="min-width:160px; white-space:nowrap;"><?php esc_html_e('User 2 (Candidate)', 'matchmaker'); ?></th>
                <th style="width:70px; text-align:center; white-space:nowrap;"><?php esc_html_e('Score', 'matchmaker'); ?></th>
                <th style="width:110px; text-align:center; white-space:nowrap;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                <th style="width:75px; text-align:center; white-space:nowrap;"><?php esc_html_e('Source', 'matchmaker'); ?></th>
                <th style="min-width:140px; white-space:nowrap;"><?php esc_html_e('Responses', 'matchmaker'); ?></th>
                <th style="width:95px; text-align:center; white-space:nowrap;"><?php esc_html_e('Created At', 'matchmaker'); ?></th>
                <th style="width:150px; text-align:center; white-space:nowrap;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($matches)) : ?>
                <tr><td colspan="9"><?php esc_html_e('No matches found matching filter criteria.', 'matchmaker'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($matches as $m) :
                    $mid = (int) ($m['id'] ?? 0);
                    $u1  = get_userdata((int) ($m['user_one_id'] ?? 0));
                    $u2  = get_userdata((int) ($m['user_two_id'] ?? 0));
                    $st  = (string) ($m['status'] ?? 'pending_review');

                    $view_url    = admin_url('admin.php?page=matchmaking-matches&view_match=' . $mid);
                    $approve_url = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=approve&match_id=' . $mid), 'mm_approve_' . $mid);
                    $reject_url  = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=reject&match_id=' . $mid), 'mm_reject_' . $mid);
                ?>
                    <tr>
                        <td style="text-align:center; white-space:nowrap;">#<?php echo $mid; ?></td>
                        <td>
                            <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . ($m['user_one_id'] ?? 0))); ?>" style="white-space:nowrap;"><?php echo esc_html($u1 ? $u1->display_name : 'User #' . ($m['user_one_id'] ?? 0)); ?></a></strong><br>
                            <small style="color:#666; word-break:break-all;"><?php echo esc_html($u1 ? $u1->user_email : ''); ?></small>
                        </td>
                        <td>
                            <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . ($m['user_two_id'] ?? 0))); ?>" style="white-space:nowrap;"><?php echo esc_html($u2 ? $u2->display_name : 'User #' . ($m['user_two_id'] ?? 0)); ?></a></strong><br>
                            <small style="color:#666; word-break:break-all;"><?php echo esc_html($u2 ? $u2->user_email : ''); ?></small>
                        </td>
                        <td style="text-align:center; white-space:nowrap;"><strong><?php echo (int) ($m['score'] ?? 0); ?></strong> / 6</td>
                        <?php $st_label = ($st === 'matched') ? __('Mutual Match', 'matchmaker') : ucfirst(str_replace('_', ' ', $st)); ?>
                        <td style="text-align:center; white-space:nowrap;"><span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html($st_label); ?></span></td>
                        <td style="text-align:center; white-space:nowrap;"><?php echo esc_html(ucfirst($m['match_source'] ?? 'auto')); ?></td>
                        <td style="white-space:nowrap;"><small>U1: <?php echo esc_html($m['user_one_response'] ?? 'pending'); ?> | U2: <?php echo esc_html($m['user_two_response'] ?? 'pending'); ?></small></td>
                        <td style="text-align:center; white-space:nowrap;"><small><?php echo esc_html(substr($m['created_at'] ?? '—', 0, 10)); ?></small></td>
                        <td style="text-align:center; white-space:nowrap;">
                            <?php if ($st === 'pending_review') : ?>
                                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'matchmaker'); ?></a>
                                <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                <a href="<?php echo esc_url($view_url); ?>" class="button button-small" style="margin-left:2px;"><?php esc_html_e('View Comparison', 'matchmaker'); ?></a>
                            <?php elseif ($st === 'approved') :
                                $cancel_url = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=cancel_approved&match_id=' . $mid), 'mm_cancel_approved_' . $mid);
                            ?>
                                <a href="<?php echo esc_url($view_url); ?>" class="button button-small"><?php esc_html_e('View Comparison', 'matchmaker'); ?></a>
                                <a href="<?php echo esc_url($cancel_url); ?>" class="button button-small mm-cancel-approval-link" style="color:#b91c1c; margin-left:3px;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this approved match and revert it to pending review? Member quotas will be restored.', 'matchmaker')); ?>');"><?php esc_html_e('Cancel', 'matchmaker'); ?></a>
                            <?php else : ?>
                                <a href="<?php echo esc_url($view_url); ?>" class="button button-small"><?php esc_html_e('View Comparison', 'matchmaker'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($total_pages) && $total_pages > 1) : ?>
    <div class="tablenav bottom" style="margin-top:15px;">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html(sprintf(_n('%d match', '%d matches', (int) ($total_matches ?? count($matches)), 'matchmaker'), (int) ($total_matches ?? count($matches)))); ?></span>
            <?php
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => __('&laquo; Previous', 'matchmaker'),
                'next_text' => __('Next &raquo;', 'matchmaker'),
                'total'     => (int) $total_pages,
                'current'   => (int) ($current_page ?? 1),
            ]);
            ?>
        </div>
    </div>
<?php endif; ?>
