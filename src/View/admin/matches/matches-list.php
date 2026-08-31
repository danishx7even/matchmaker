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
    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search member name or email...', 'matchmaker'); ?>" class="regular-text">

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

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th style="width:65px;"><?php esc_html_e('Match ID', 'matchmaker'); ?></th>
            <th><?php esc_html_e('User 1 (Initiator)', 'matchmaker'); ?></th>
            <th><?php esc_html_e('User 2 (Candidate)', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Score', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Status', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Source', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Responses', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Created At', 'matchmaker'); ?></th>
            <th style="width:160px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
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
                    <td>#<?php echo $mid; ?></td>
                    <td>
                        <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . ($m['user_one_id'] ?? 0))); ?>"><?php echo esc_html($u1 ? $u1->display_name : 'User #' . ($m['user_one_id'] ?? 0)); ?></a></strong><br>
                        <small style="color:#666;"><?php echo esc_html($u1 ? $u1->user_email : ''); ?></small>
                    </td>
                    <td>
                        <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . ($m['user_two_id'] ?? 0))); ?>"><?php echo esc_html($u2 ? $u2->display_name : 'User #' . ($m['user_two_id'] ?? 0)); ?></a></strong><br>
                        <small style="color:#666;"><?php echo esc_html($u2 ? $u2->user_email : ''); ?></small>
                    </td>
                    <td><strong><?php echo (int) ($m['score'] ?? 0); ?></strong> / 6</td>
                    <td><span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?></span></td>
                    <td><?php echo esc_html(ucfirst($m['match_source'] ?? 'auto')); ?></td>
                    <td><small>U1: <?php echo esc_html($m['user_one_response'] ?? 'pending'); ?> | U2: <?php echo esc_html($m['user_two_response'] ?? 'pending'); ?></small></td>
                    <td><small><?php echo esc_html($m['created_at'] ?? '—'); ?></small></td>
                    <td style="text-align:center;">
                        <?php if ($st === 'pending_review') :
                            $p1 = $repo->get_user_pool((int)($m['user_one_id'] ?? 0));
                            $p2 = $repo->get_user_pool((int)($m['user_two_id'] ?? 0));
                            $t1 = $p1['user_type'] ?? 'free';
                            $t2 = $p2['user_type'] ?? 'free';
                            $is_foe = in_array($t1, ['free', 'event'], true) || in_array($t2, ['free', 'event'], true);
                        ?>
                            <?php if ($is_foe) : ?>
                                <span style="display:block; margin-bottom:4px; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; background:#fef3c7; color:#92400e; border:1px solid #f59e0b;">
                                    ⚠️ Free/Event
                                </span>
                                <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                            <?php else : ?>
                                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'matchmaker'); ?></a>
                                <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                            <?php endif; ?>
                        <?php else : ?>
                            <a href="<?php echo esc_url($view_url); ?>" class="button button-small"><?php esc_html_e('View', 'matchmaker'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
