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
            <strong><?php esc_html_e('Status:', 'matchmaker'); ?></strong> <span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?></span>
        </p>
    </div>
    <div>
        <?php if ($st === 'pending_review') :
            $t1 = $p1['user_type'] ?? 'free';
            $t2 = $p2['user_type'] ?? 'free';
            $is_foe = in_array($t1, ['free', 'event'], true) || in_array($t2, ['free', 'event'], true);
        ?>
            <?php if ($is_foe) : ?>
                <span style="display:inline-block; margin-right:8px; padding:6px 12px; border-radius:4px; font-size:12px; font-weight:600; background:#fef3c7; color:#92400e; border:1px solid #f59e0b;">
                    ⚠️ Free/Event Tier (Upgrade Required)
                </span>
                <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary button-hero mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
            <?php else : ?>
                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-hero" style="margin-right:8px;"><?php esc_html_e('Approve Match', 'matchmaker'); ?></a>
                <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary button-hero mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="mm-grid-two">
    <!-- User 1 Card -->
    <div class="mm-card">
        <h3><?php echo esc_html($u1 ? $u1->display_name : 'User #' . $u1_id); ?> (User 1)</h3>
        <?php if (!empty($m1['user_photo1'])) : ?>
            <div style="margin-bottom:15px;"><img src="<?php echo esc_url($m1['user_photo1']); ?>" style="width:120px;height:140px;object-fit:cover;border-radius:6px;" alt=""></div>
        <?php endif; ?>
        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Email', 'matchmaker'); ?></th><td><?php echo esc_html($u1 ? $u1->user_email : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Age', 'matchmaker'); ?></th><td><?php echo esc_html($repo->calc_age($p1['birth_date'] ?? '')); ?></td></tr>
            <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p1['gender'] ?? '')); ?></td></tr>
            <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($p1['location'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Origin', 'matchmaker'); ?></th><td><?php echo esc_html($p1['origin'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Religion', 'matchmaker'); ?></th><td><?php echo esc_html($p1['religion'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($p1['modesty'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Response', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($match['user_one_response'] ?? 'pending')); ?></strong></td></tr>
        </table>
    </div>

    <!-- User 2 Card -->
    <div class="mm-card">
        <h3><?php echo esc_html($u2 ? $u2->display_name : 'User #' . $u2_id); ?> (User 2)</h3>
        <?php if (!empty($m2['user_photo1'])) : ?>
            <div style="margin-bottom:15px;"><img src="<?php echo esc_url($m2['user_photo1']); ?>" style="width:120px;height:140px;object-fit:cover;border-radius:6px;" alt=""></div>
        <?php endif; ?>
        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Email', 'matchmaker'); ?></th><td><?php echo esc_html($u2 ? $u2->user_email : '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Age', 'matchmaker'); ?></th><td><?php echo esc_html($repo->calc_age($p2['birth_date'] ?? '')); ?></td></tr>
            <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p2['gender'] ?? '')); ?></td></tr>
            <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($p2['location'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Origin', 'matchmaker'); ?></th><td><?php echo esc_html($p2['origin'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Religion', 'matchmaker'); ?></th><td><?php echo esc_html($p2['religion'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($p2['modesty'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Response', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($match['user_two_response'] ?? 'pending')); ?></strong></td></tr>
        </table>
    </div>
</div>
