<?php
/**
 * View: Admin Single Match Side-by-Side Review
 *
 * Available variables:
 *   @var int                  $match_id
 *   @var array<string, mixed> $match
 *   @var int                  $u1_id
 *   @var int                  $u2_id
 *   @var \WP_User|null        $u1
 *   @var \WP_User|null        $u2
 *   @var array<string, mixed> $p1
 *   @var array<string, mixed> $p2
 *   @var array<string, mixed> $m1
 *   @var array<string, mixed> $m2
 *   @var string               $back_url
 *   @var string               $approve_url
 *   @var string               $reject_url
 *   @var string               $cancel_url
 *   @var string               $st
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
?>
<p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Matches Queue', 'matchmaker'); ?></a></p>

<div class="mm-detail-header">
    <div>
        <h2><?php echo esc_html(sprintf(__('Match Pair #%d Side-by-Side Review', 'matchmaker'), $match_id)); ?></h2>
        <p class="description">
            <strong><?php esc_html_e('Compatibility Score:', 'matchmaker'); ?></strong> <?php echo (int) ($match['score'] ?? 0); ?> / 6 &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Match Source:', 'matchmaker'); ?></strong> <?php echo esc_html(ucfirst($match['match_source'] ?? 'auto')); ?> &nbsp;|&nbsp; 
            <?php $st_label = ($st === 'matched') ? __('Mutual Match', 'matchmaker') : ucfirst(str_replace('_', ' ', $st)); ?>
            <strong><?php esc_html_e('Status:', 'matchmaker'); ?></strong> <span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html($st_label); ?></span>
        </p>
    </div>
    <div>
        <?php if ($st === 'pending_review') : ?>
            <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-hero" style="margin-right:8px;"><?php esc_html_e('Approve Match', 'matchmaker'); ?></a>
            <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary button-hero mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
        <?php elseif ($st === 'approved') : ?>
            <a href="<?php echo esc_url($cancel_url); ?>" class="button button-secondary button-hero" style="color:#b91c1c;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this approved match and revert it to pending review? Member quotas will be restored.', 'matchmaker')); ?>');"><?php esc_html_e('Cancel Approval', 'matchmaker'); ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="mm-grid-two">
    <!-- User 1 Card -->
    <?php
    $p1_loc_parts = is_array($p1) ? array_filter([$p1['city'] ?? '', $p1['state'] ?? '', $p1['country'] ?? '']) : [];
    $p1_loc = !empty($p1_loc_parts) ? implode(', ', $p1_loc_parts) : (is_array($p1) ? ($p1['location'] ?? '—') : '—');
    $u1_photo = !empty($m1['user_photo1']) ? $m1['user_photo1'] : (!empty($p1['user_photo1']) ? $p1['user_photo1'] : (string) get_user_meta($u1_id, 'user_photo1', true));
    ?>
    <div class="mm-card">
        <h3><?php echo esc_html($u1 ? $u1->display_name : 'User #' . $u1_id); ?> (User 1)</h3>
        <?php if (!empty($u1_photo)) : ?>
            <div style="margin-bottom:15px;"><img src="<?php echo esc_url($u1_photo); ?>" style="width:120px;height:140px;object-fit:cover;border-radius:6px;cursor:zoom-in;" alt="" data-mm-lightbox="match-user-1" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>"></div>
        <?php endif; ?>
        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Email', 'matchmaker'); ?></th><td><?php echo esc_html($u1 ? $u1->user_email : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Age', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? $repo->calc_age($p1['birth_date'] ?? '') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ucfirst($p1['gender'] ?? '') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($p1_loc); ?></td></tr>
            <tr><th><?php esc_html_e('Origin', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['origin'] ?? '—') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Religion', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['religion'] ?? '—') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['modesty'] ?? '—') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo $repo->format_social_links_html($m1['user_social_links'] ?? ''); ?></td></tr>
            <tr><th><?php esc_html_e('Response', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($match['user_one_response'] ?? 'pending')); ?></strong></td></tr>
        </table>
    </div>

    <!-- User 2 Card -->
    <?php
    $p2_loc_parts = is_array($p2) ? array_filter([$p2['city'] ?? '', $p2['state'] ?? '', $p2['country'] ?? '']) : [];
    $p2_loc = !empty($p2_loc_parts) ? implode(', ', $p2_loc_parts) : (is_array($p2) ? ($p2['location'] ?? '—') : '—');
    $u2_photo = !empty($m2['user_photo1']) ? $m2['user_photo1'] : (!empty($p2['user_photo1']) ? $p2['user_photo1'] : (string) get_user_meta($u2_id, 'user_photo1', true));
    ?>
    <div class="mm-card">
        <h3><?php echo esc_html($u2 ? $u2->display_name : 'User #' . $u2_id); ?> (User 2)</h3>
        <?php if (!empty($u2_photo)) : ?>
            <div style="margin-bottom:15px;"><img src="<?php echo esc_url($u2_photo); ?>" style="width:120px;height:140px;object-fit:cover;border-radius:6px;cursor:zoom-in;" alt="" data-mm-lightbox="match-user-2" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>"></div>
        <?php endif; ?>
        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Email', 'matchmaker'); ?></th><td><?php echo esc_html($u2 ? $u2->user_email : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Age', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? $repo->calc_age($p2['birth_date'] ?? '') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ucfirst($p2['gender'] ?? '') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($p2_loc); ?></td></tr>
            <tr><th><?php esc_html_e('Origin', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['origin'] ?? '—') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Religion', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['religion'] ?? '—') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['modesty'] ?? '—') : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo $repo->format_social_links_html($m2['user_social_links'] ?? ''); ?></td></tr>
            <tr><th><?php esc_html_e('Response', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($match['user_two_response'] ?? 'pending')); ?></strong></td></tr>
        </table>
    </div>
</div>
