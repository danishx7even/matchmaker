<?php
/**
 * View: Admin Single Candidate Profile View
 *
 * Available variables:
 *   @var int                  $user_id
 *   @var array<string, mixed> $pool
 *   @var \WP_User             $user_obj
 *   @var array<string, mixed> $meta
 *   @var array<int, array>    $matches
 *   @var string               $age
 *   @var string               $height
 *   @var int                  $quota_used
 *   @var bool                 $has_mutual
 *   @var string               $back_url
 *   @var string               $manual_url
 *   @var string               $trigger_url
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo   = \Matchmaker\Repository\MatchRepository::instance();
$photo1 = $meta['user_photo1'] ?? '';
$photo2 = $meta['user_photo2'] ?? '';
$photo3 = $meta['user_photo3'] ?? '';
?>

<!-- Header Card -->
<div class="mm-detail-header">
    <div>
        <a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Candidate Pool', 'matchmaker'); ?></a>
        <h2 style="margin:8px 0 4px;">
            <?php echo esc_html($user_obj->display_name); ?>
            <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                <?php echo esc_html($repo->format_tier_label($pool['user_type'])); ?>
            </span>
        </h2>
        <p class="description" style="margin:0;">
            <strong><?php esc_html_e('Email:', 'matchmaker'); ?></strong> <?php echo esc_html($user_obj->user_email); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Phone:', 'matchmaker'); ?></strong> <?php echo esc_html($meta['phone_number'] ?: 'N/A'); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Monthly Quota Used:', 'matchmaker'); ?></strong> <?php echo (int) $quota_used; ?> / <?php echo (int) $repo->get_max_cycle_matches(); ?>
        </p>
    </div>
    <div>
        <?php if (!$has_mutual) : ?>
            <a href="<?php echo esc_url($manual_url); ?>" class="button button-secondary" style="margin-right:8px;">
                + <?php esc_html_e('Manual Matchmaker', 'matchmaker'); ?>
            </a>
            <a href="<?php echo esc_url($trigger_url); ?>" class="button button-primary">
                ⚡ <?php esc_html_e('Run Auto-Match Scoring', 'matchmaker'); ?>
            </a>
        <?php else : ?>
            <span style="color:#2e7d32; font-weight:bold; font-size:13px; background:#e8f5e9; padding:6px 12px; border-radius:4px;">
                ★ <?php esc_html_e('Mutually Matched This Month', 'matchmaker'); ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<?php if ($has_mutual) : ?>
    <div class="notice notice-info inline" style="margin-bottom:20px;">
        <p><strong>★ <?php esc_html_e('Notice:', 'matchmaker'); ?></strong> <?php esc_html_e('This candidate has a mutually accepted match for the current calendar month. Additional automated and manual matching runs are paused.', 'matchmaker'); ?></p>
    </div>
<?php endif; ?>

<!-- Two-Column Profile Cards -->
<div class="mm-grid-two">
    <!-- Candidate Self Profile Card -->
    <div class="mm-card">
        <h3><?php esc_html_e('Candidate Self Profile', 'matchmaker'); ?></h3>
        
        <?php if (!empty($photo1) || !empty($photo2) || !empty($photo3)) : ?>
            <div class="mm-photos-grid">
                <?php if (!empty($photo1)) : ?><img src="<?php echo esc_url($photo1); ?>" alt="Photo 1"><?php endif; ?>
                <?php if (!empty($photo2)) : ?><img src="<?php echo esc_url($photo2); ?>" alt="Photo 2"><?php endif; ?>
                <?php if (!empty($photo3)) : ?><img src="<?php echo esc_url($photo3); ?>" alt="Photo 3"><?php endif; ?>
            </div>
            <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
        <?php endif; ?>

        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Age / Date of Birth', 'matchmaker'); ?></th><td><?php echo esc_html($age . ' yrs (' . ($pool['birth_date'] ?? 'N/A') . ')'); ?></td></tr>
            <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($pool['gender'] ?? '')); ?></td></tr>
            <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($pool['location'] ?? '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_citizenship'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></th><td><?php echo esc_html($pool['origin'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['religion'] ?? '—') . ' / ' . ($pool['modesty'] ?? '—')); ?></td></tr>
            <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th><td><?php echo esc_html($height); ?></td></tr>
            <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th><td><?php echo esc_html($pool['languages'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Job / Career', 'matchmaker'); ?></th><td><?php echo esc_html($pool['job'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['smoking'] ?: '—') . ' / ' . ($pool['drinking'] ?: '—')); ?></td></tr>
            <tr><th><?php esc_html_e('Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_marital_status'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Children', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_children'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Education Level', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_education'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_income'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_social_links'] ?: '—'); ?></td></tr>
        </table>
    </div>

    <!-- Candidate Partner Preferences Card -->
    <div class="mm-card">
        <h3><?php esc_html_e('Candidate Partner Preferences', 'matchmaker'); ?></h3>
        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Preferred Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($pool['pref_gender'] ?? 'Any')); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Age Range', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['preferred_age_min'] ?? 18) . ' – ' . ($pool['preferred_age_max'] ?? 80) . ' yrs'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Location', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_location'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_citizenship'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Origin', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_origin'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Religion', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_religion'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_modesty'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['preferred_height_min'] ? $pool['preferred_height_min'] . 'cm' : 'Min') . ' – ' . ($pool['preferred_height_max'] ? $pool['preferred_height_max'] . 'cm' : 'Max')); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['pref_smoking'] ?: 'Any') . ' / ' . ($pool['pref_drinking'] ?: 'Any')); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_marital_status'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Children', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_children'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Education', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_education'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_income'] ?: 'Any'); ?></td></tr>
        </table>

        <?php if (!empty($meta['pref_additional_info'])) : ?>
            <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
            <strong><?php esc_html_e('About Ideal Partner:', 'matchmaker'); ?></strong>
            <p style="margin:6px 0 0; color:#444; font-style:italic; font-size:13px; line-height:1.4;">
                "<?php echo esc_html($meta['pref_additional_info']); ?>"
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Match History & Approval Queue -->
<div class="mm-card" style="margin-top:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0; border:0; padding:0;"><?php esc_html_e('Match History & Approval Queue', 'matchmaker'); ?></h3>
        <span class="mm-badge mm-badge-free" style="font-size:12px; font-weight:600; padding:4px 10px; border-radius:12px;">
            <?php echo count($matches); ?> <?php esc_html_e('Total Matches Recorded', 'matchmaker'); ?>
        </span>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:65px;"><?php esc_html_e('ID', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Candidate Profile', 'matchmaker'); ?></th>
                <th style="width:120px; text-align:center;"><?php esc_html_e('Score', 'matchmaker'); ?></th>
                <th style="width:120px;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                <th style="width:100px;"><?php esc_html_e('Source', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Member Responses', 'matchmaker'); ?></th>
                <th style="width:110px;"><?php esc_html_e('Created Date', 'matchmaker'); ?></th>
                <th style="width:180px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($matches)) : ?>
                <tr><td colspan="8"><?php esc_html_e('No match history recorded for this candidate.', 'matchmaker'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($matches as $m) :
                    $mid       = (int) $m['id'];
                    $is_u1     = ((int) $m['user_one_id'] === $user_id);
                    $cand_id   = $is_u1 ? (int) $m['user_two_id'] : (int) $m['user_one_id'];
                    $cand_user = get_userdata($cand_id);
                    $cand_pool = $repo->get_user_pool($cand_id);
                    $cand_photo= $repo->get_meta($cand_id, 'user_photo1');
                    $cand_type = $cand_pool['user_type'] ?? 'free';

                    $is_free_or_event = in_array($pool['user_type'], ['free', 'event'], true) || in_array($cand_type, ['free', 'event'], true);

                    $view_match_url = admin_url('admin.php?page=matchmaking-matches&view_match=' . $mid);
                    $approve_url    = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=approve&match_id=' . $mid), 'mm_approve_' . $mid);
                    $reject_url     = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=reject&match_id=' . $mid), 'mm_reject_' . $mid);
                    $st             = (string) $m['status'];

                    $u1_resp = $m['user_one_response'] ?? 'pending';
                    $u2_resp = $m['user_two_response'] ?? 'pending';
                ?>
                    <tr>
                        <td><strong>#<?php echo $mid; ?></strong></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <?php if (!empty($cand_photo)) : ?>
                                    <img src="<?php echo esc_url($cand_photo); ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;" alt="">
                                <?php else : ?>
                                    <div class="mm-avatar-thumb" style="width:34px;height:34px;border-radius:50%;">
                                        <?php echo esc_html(strtoupper(substr($cand_user ? $cand_user->display_name : 'U', 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $cand_id)); ?>"><?php echo esc_html($cand_user ? $cand_user->display_name : 'User #' . $cand_id); ?></a></strong>
                                    <span class="mm-badge mm-badge-<?php echo esc_attr($cand_type); ?>" style="margin-left:4px;">
                                        <?php echo esc_html($repo->format_tier_label($cand_type)); ?>
                                    </span>
                                    <br>
                                    <small style="color:#666;"><?php echo esc_html($cand_user ? $cand_user->user_email : ''); ?></small>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span style="display:inline-block; font-weight:700; color:#0284c7; background:#e0f2fe; padding:2px 8px; border-radius:12px; font-size:12px;">
                                <?php echo (int) ($m['score'] ?? 0); ?> / 6
                            </span>
                        </td>
                        <td>
                            <span class="mm-status mm-status-<?php echo esc_attr($st); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?>
                            </span>
                        </td>
                        <td><small><?php echo esc_html(ucfirst($m['match_source'] ?? 'auto')); ?></small></td>
                        <td>
                            <small style="line-height:1.4; display:block;">
                                <strong>Self:</strong> 
                                <?php if (($is_u1 ? $u1_resp : $u2_resp) === 'accepted') : ?>
                                    <span style="color:#16a34a; font-weight:600;">✓ Accepted</span>
                                <?php elseif (($is_u1 ? $u1_resp : $u2_resp) === 'rejected') : ?>
                                    <span style="color:#dc2626; font-weight:600;">✕ Declined</span>
                                <?php else : ?>
                                    <span style="color:#d97706;">⏳ Pending</span>
                                <?php endif; ?>
                                <br>
                                <strong>Candidate:</strong> 
                                <?php if (($is_u1 ? $u2_resp : $u1_resp) === 'accepted') : ?>
                                    <span style="color:#16a34a; font-weight:600;">✓ Accepted</span>
                                <?php elseif (($is_u1 ? $u2_resp : $u1_resp) === 'rejected') : ?>
                                    <span style="color:#dc2626; font-weight:600;">✕ Declined</span>
                                <?php else : ?>
                                    <span style="color:#d97706;">⏳ Pending</span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td><small style="color:#555;"><?php echo esc_html(substr($m['created_at'] ?? '', 0, 10)); ?></small></td>
                        <td style="text-align:center;">
                            <?php if ($st === 'pending_review') : ?>
                                <?php if ($is_free_or_event) : ?>
                                    <span style="display:block; margin-bottom:4px; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; background:#fef3c7; color:#92400e; border:1px solid #f59e0b;" title="<?php esc_attr_e('Matching approvals are disabled for Free or Event users.', 'matchmaker'); ?>">
                                        ⚠️ Free/Event Tier
                                    </span>
                                    <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'matchmaker'); ?></a>
                                    <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                <?php endif; ?>
                            <?php else : ?>
                                <a href="<?php echo esc_url($view_match_url); ?>" class="button button-small"><?php esc_html_e('View Comparison', 'matchmaker'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
