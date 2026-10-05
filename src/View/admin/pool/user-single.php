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
$photo1 = !empty($meta['user_photo1']) ? $meta['user_photo1'] : (!empty($pool['user_photo1']) ? $pool['user_photo1'] : (string) get_user_meta($user_id, 'user_photo1', true));
$photo2 = !empty($meta['user_photo2']) ? $meta['user_photo2'] : (!empty($pool['user_photo2']) ? $pool['user_photo2'] : (string) get_user_meta($user_id, 'user_photo2', true));
$photo3 = !empty($meta['user_photo3']) ? $meta['user_photo3'] : (!empty($pool['user_photo3']) ? $pool['user_photo3'] : (string) get_user_meta($user_id, 'user_photo3', true));
?>

<!-- Header Card -->
<div class="mm-detail-header">
    <div>
        <a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Candidate Pool', 'matchmaker'); ?></a>
        <h2 style="margin:8px 0 4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <?php echo esc_html($user_obj->display_name); ?>
            <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                <?php echo esc_html($repo->format_tier_label($pool['user_type'])); ?>
            </span>
            <?php if (!empty($pool['is_parent_applying']) || !empty($meta['is_parent_applying'])) : ?>
                <span class="mm-badge mm-badge-parent" style="font-size:11px; padding:3px 9px;">
                    👨‍👩‍👧 <?php esc_html_e('Parent Applying on Behalf of Child', 'matchmaker'); ?>
                </span>
            <?php endif; ?>
            <?php 
            $pmpro_sync = \Matchmaker\Core\PMProSync::instance();
            $user_services = $pmpro_sync->get_user_active_services($user_id);
            $cancel_info   = $pmpro_sync->get_user_cancellation_info($user_id);
            if (!empty($user_services)) :
                foreach ($user_services as $usrv) :
            ?>
                <span class="mm-badge mm-badge-service" style="font-size:11px; padding:3px 9px;">
                    ★ <?php echo esc_html($usrv['tag']); ?>
                </span>
            <?php 
                endforeach;
            endif; 
            if (!empty($cancel_info)) :
            ?>
                <span class="mm-badge mm-badge-cancelled-active" style="font-size:11px; padding:3px 9px;">
                    ⚠️ <?php echo esc_html(!empty($cancel_info['expires_at']) ? sprintf(__('Cancelled (Active until %s)', 'matchmaker'), $cancel_info['expires_at']) : __('Subscription Cancelled', 'matchmaker')); ?>
                </span>
            <?php endif; ?>
        </h2>
        <p class="description" style="margin:0;">
            <strong><?php esc_html_e('Email:', 'matchmaker'); ?></strong> <?php echo esc_html($user_obj->user_email); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Phone:', 'matchmaker'); ?></strong> <?php echo esc_html($meta['phone_number'] ?: 'N/A'); ?>
            <?php if (($pool['user_type'] ?? 'free') === 'monthly') : ?>
                &nbsp;|&nbsp; <strong><?php esc_html_e('Monthly Quota Used:', 'matchmaker'); ?></strong> <?php echo (int) $quota_used; ?> / <?php echo (int) $repo->get_max_cycle_matches(); ?>
            <?php endif; ?>
            &nbsp;|&nbsp; <strong><?php esc_html_e('Date joined:', 'matchmaker'); ?></strong> <?php echo esc_html($subscription_start_date ?? $repo->get_subscription_start_date($user_id)); ?>
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

<?php if (!empty($cancel_info)) : ?>
    <div class="notice notice-warning inline" style="margin-bottom:20px; padding:12px 16px; background:#fffbeb; border-left:4px solid #f59e0b; border-radius:4px;">
        <h4 style="margin:0 0 4px; color:#b45309; font-size:14px; font-weight:700;">
            ⚠️ <?php esc_html_e('Membership Cancellation Notice', 'matchmaker'); ?>
        </h4>
        <p style="margin:0 0 4px; font-size:13px; color:#92400e;">
            <strong><?php esc_html_e('Reason:', 'matchmaker'); ?></strong> <?php echo esc_html($cancel_info['reason']); ?>
            <?php if (!empty($cancel_info['cancellation_date'])) : ?>
                &nbsp;|&nbsp; <strong><?php esc_html_e('Cancelled On:', 'matchmaker'); ?></strong> <?php echo esc_html($cancel_info['cancellation_date']); ?>
            <?php endif; ?>
            <?php if (!empty($cancel_info['expires_at'])) : ?>
                &nbsp;|&nbsp; <strong><?php esc_html_e('Access Expires:', 'matchmaker'); ?></strong> <?php echo esc_html($cancel_info['expires_at']); ?>
            <?php endif; ?>
        </p>
        <?php if (!empty($cancel_info['details'])) : ?>
            <p style="margin:0; font-size:13px; color:#78350f; font-style:italic; line-height:1.4;">
                "<?php echo esc_html($cancel_info['details']); ?>"
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

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
                <?php if (!empty($photo1)) : ?><img src="<?php echo esc_url($photo1); ?>" alt="Photo 1" data-mm-lightbox="profile-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                <?php if (!empty($photo2)) : ?><img src="<?php echo esc_url($photo2); ?>" alt="Photo 2" data-mm-lightbox="profile-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                <?php if (!empty($photo3)) : ?><img src="<?php echo esc_url($photo3); ?>" alt="Photo 3" data-mm-lightbox="profile-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
            </div>
            <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
        <?php endif; ?>

        <table class="mm-kv-table">
            <?php if (!empty($pool['is_parent_applying']) || !empty($meta['is_parent_applying'])) : ?>
                <tr><th><?php esc_html_e('Application Type', 'matchmaker'); ?></th><td><strong style="color:#CC723F;"><?php esc_html_e('Parent applying on behalf of child', 'matchmaker'); ?></strong></td></tr>
            <?php endif; ?>
            <?php
            $u_loc_parts = array_filter([$pool['city'] ?? '', $pool['state'] ?? '', $pool['country'] ?? '']);
            $u_loc = !empty($u_loc_parts) ? implode(', ', $u_loc_parts) : ($pool['location'] ?? '—');
            ?>
            <tr><th><?php esc_html_e('Age / Date of Birth', 'matchmaker'); ?></th><td><?php echo esc_html($age . ' yrs (' . ($pool['birth_date'] ?? 'N/A') . ')'); ?></td></tr>
            <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($pool['gender'] ?? '')); ?></td></tr>
            <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($u_loc); ?></td></tr>
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
            <tr><th><?php esc_html_e('Yearly Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_income'] ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo $repo->format_social_links_html($meta['user_social_links'] ?? ''); ?></td></tr>
        </table>

        <?php if (!empty($meta['user_about_me'])) : ?>
            <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
            <strong><?php esc_html_e('About Myself:', 'matchmaker'); ?></strong>
            <p style="margin:6px 0 0; color:#444; font-style:italic; font-size:13px; line-height:1.4;">
                "<?php echo esc_html($meta['user_about_me']); ?>"
            </p>
        <?php endif; ?>
    </div>

    <!-- Candidate Partner Preferences Card -->
    <div class="mm-card">
        <h3><?php esc_html_e('Candidate Partner Preferences', 'matchmaker'); ?></h3>
        <?php
        $p_loc_parts = array_filter([$pool['pref_city'] ?? '', $pool['pref_state'] ?? '', $pool['pref_country'] ?? '']);
        $p_loc = !empty($p_loc_parts) ? implode(', ', $p_loc_parts) : ($pool['pref_location'] ?: 'Any');
        ?>
        <table class="mm-kv-table">
            <tr><th><?php esc_html_e('Preferred Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($pool['pref_gender'] ?? 'Any')); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Age Range', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['preferred_age_min'] ?? 18) . ' – ' . ($pool['preferred_age_max'] ?? 80) . ' yrs'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Location', 'matchmaker'); ?></th><td><?php echo esc_html($p_loc); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_citizenship'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Origin', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_origin'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Religion', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_religion'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_modesty'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['preferred_height_min'] ? $pool['preferred_height_min'] . 'cm' : 'Min') . ' – ' . ($pool['preferred_height_max'] ? $pool['preferred_height_max'] . 'cm' : 'Max')); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['pref_smoking'] ?: 'Any') . ' / ' . ($pool['pref_drinking'] ?: 'Any')); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_marital_status'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Children', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_children'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Education', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_education'] ?: 'Any'); ?></td></tr>
            <tr><th><?php esc_html_e('Preferred Yearly Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_income'] ?: 'Any'); ?></td></tr>
        </table>

        <?php if (!empty($meta['pref_additional_info'])) : ?>
            <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
            <strong><?php esc_html_e('About My Perfect Match:', 'matchmaker'); ?></strong>
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

    <div class="mm-table-responsive">
        <table class="wp-list-table widefat striped" style="min-width:800px; table-layout:auto;">
            <thead>
                <tr>
                    <th style="width:50px; text-align:center; white-space:nowrap;"><?php esc_html_e('ID', 'matchmaker'); ?></th>
                    <th style="min-width:180px; white-space:nowrap;"><?php esc_html_e('Candidate Profile', 'matchmaker'); ?></th>
                    <th style="width:75px; text-align:center; white-space:nowrap;"><?php esc_html_e('Score', 'matchmaker'); ?></th>
                    <th style="width:110px; text-align:center; white-space:nowrap;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                    <th style="width:75px; text-align:center; white-space:nowrap;"><?php esc_html_e('Source', 'matchmaker'); ?></th>
                    <th style="min-width:130px; white-space:nowrap;"><?php esc_html_e('Member Responses', 'matchmaker'); ?></th>
                    <th style="width:95px; text-align:center; white-space:nowrap;"><?php esc_html_e('Created Date', 'matchmaker'); ?></th>
                    <th style="width:160px; text-align:center; white-space:nowrap;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
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

                        $is_event_only = ($pool['user_type'] === 'event') || ($cand_type === 'event');

                        $view_match_url = admin_url('admin.php?page=matchmaking-matches&view_match=' . $mid);
                        $approve_url    = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=approve&match_id=' . $mid), 'mm_approve_' . $mid);
                        $reject_url     = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=reject&match_id=' . $mid), 'mm_reject_' . $mid);
                        $st             = (string) $m['status'];

                        $u1_resp = $m['user_one_response'] ?? 'pending';
                        $u2_resp = $m['user_two_response'] ?? 'pending';
                    ?>
                        <tr>
                            <td style="text-align:center;"><strong>#<?php echo $mid; ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px; min-width:180px;">
                                    <?php if (!empty($cand_photo)) : ?>
                                        <img src="<?php echo esc_url($cand_photo); ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">
                                    <?php else : ?>
                                        <div class="mm-avatar-thumb" style="width:34px;height:34px;border-radius:50%;flex-shrink:0;">
                                            <?php echo esc_html(strtoupper(substr($cand_user ? $cand_user->display_name : 'U', 0, 1))); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="min-width:0;">
                                        <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $cand_id)); ?>" style="white-space:nowrap;"><?php echo esc_html($cand_user ? $cand_user->display_name : 'User #' . $cand_id); ?></a></strong>
                                        <span class="mm-badge mm-badge-<?php echo esc_attr($cand_type); ?>" style="margin-left:4px; font-size:10px; padding:2px 6px;">
                                            <?php echo esc_html($repo->format_tier_label($cand_type)); ?>
                                        </span>
                                        <br>
                                        <small style="color:#666; word-break:break-all;"><?php echo esc_html($cand_user ? $cand_user->user_email : ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span style="display:inline-block; font-weight:700; color:#0284c7; background:#e0f2fe; padding:2px 8px; border-radius:12px; font-size:12px; white-space:nowrap;">
                                    <?php echo (int) ($m['score'] ?? 0); ?> / 6
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php $st_label = ($st === 'matched') ? __('Mutual Match', 'matchmaker') : ucfirst(str_replace('_', ' ', $st)); ?>
                                <span class="mm-status mm-status-<?php echo esc_attr($st); ?>" style="white-space:nowrap;">
                                    <?php echo esc_html($st_label); ?>
                                </span>
                            </td>
                            <td style="text-align:center;"><small><?php echo esc_html(ucfirst($m['match_source'] ?? 'auto')); ?></small></td>
                            <td>
                                <?php
                                $self_reason = (string) ($is_u1 ? ($m['user_one_rejection_reason'] ?? '') : ($m['user_two_rejection_reason'] ?? ''));
                                $cand_reason = (string) ($is_u1 ? ($m['user_two_rejection_reason'] ?? '') : ($m['user_one_rejection_reason'] ?? ''));
                                ?>
                                <small style="line-height:1.4; display:block;">
                                    <strong>Self:</strong> 
                                    <?php if (($is_u1 ? $u1_resp : $u2_resp) === 'accepted') : ?>
                                        <span style="color:#16a34a; font-weight:600;">✓ Accepted</span>
                                    <?php elseif (($is_u1 ? $u1_resp : $u2_resp) === 'rejected') : ?>
                                        <span style="color:#dc2626; font-weight:600;">✕ Declined</span>
                                        <?php if (!empty($self_reason)) : ?>
                                            <div style="font-size:11px; color:#991b1b; background:#fee2e2; padding:3px 6px; border-radius:4px; margin:2px 0 4px; max-width:200px; white-space:normal; line-height:1.3;" title="<?php echo esc_attr($self_reason); ?>">
                                                💬 "<?php echo esc_html(mb_strimwidth($self_reason, 0, 50, '...')); ?>"
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color:#d97706;">⏳ Pending</span>
                                    <?php endif; ?>
                                    <br>
                                    <strong>Candidate:</strong> 
                                    <?php if (($is_u1 ? $u2_resp : $u1_resp) === 'accepted') : ?>
                                        <span style="color:#16a34a; font-weight:600;">✓ Accepted</span>
                                    <?php elseif (($is_u1 ? $u2_resp : $u1_resp) === 'rejected') : ?>
                                        <span style="color:#dc2626; font-weight:600;">✕ Declined</span>
                                        <?php if (!empty($cand_reason)) : ?>
                                            <div style="font-size:11px; color:#991b1b; background:#fee2e2; padding:3px 6px; border-radius:4px; margin:2px 0 0; max-width:200px; white-space:normal; line-height:1.3;" title="<?php echo esc_attr($cand_reason); ?>">
                                                💬 "<?php echo esc_html(mb_strimwidth($cand_reason, 0, 50, '...')); ?>"
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color:#d97706;">⏳ Pending</span>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td style="text-align:center;"><small style="color:#555; white-space:nowrap;"><?php echo esc_html(substr($m['created_at'] ?? '', 0, 10)); ?></small></td>
                            <td style="text-align:center; white-space:nowrap;">
                                <?php if ($st === 'pending_review') : ?>
                                    <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'matchmaker'); ?></a>
                                    <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                <?php elseif ($st === 'approved') :
                                    $cancel_url = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=cancel_approved&match_id=' . $mid), 'mm_cancel_approved_' . $mid);
                                ?>
                                    <a href="<?php echo esc_url($view_match_url); ?>" class="button button-small"><?php esc_html_e('View Comparison', 'matchmaker'); ?></a>
                                    <a href="<?php echo esc_url($cancel_url); ?>" class="button button-small mm-cancel-approval-link" style="color:#b91c1c; margin-left:3px;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this approved match and revert it to pending review? Member quotas will be restored.', 'matchmaker')); ?>');"><?php esc_html_e('Cancel', 'matchmaker'); ?></a>
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
</div>

<!-- Admin Notes Card (Bottom of Match History) -->
<div class="mm-card mm-admin-notes-card mm-admin-notes-sidebar-card" style="margin-top:20px;">
    <h3 style="margin:0 0 8px; display:flex; align-items:center; gap:8px; font-size:16px; font-weight:700; color:#0f172a;">
        📝 <?php esc_html_e('Admin Notes', 'matchmaker'); ?>
    </h3>
    <p class="description" style="margin:0 0 12px; font-size:13px; line-height:1.4;">
        <?php esc_html_e('Internal staff notes for this candidate. Visible only to Matchmakers and Admins.', 'matchmaker'); ?>
    </p>
    <div class="mm-admin-notes-editor-wrap">
        <textarea id="mm-sidebar-admin-notes-textarea" name="mm_sidebar_admin_notes" rows="6" class="large-text mm-admin-notes-textarea" placeholder="<?php esc_attr_e('Enter private notes, observations, or follow-up details for this candidate...', 'matchmaker'); ?>" style="width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px; font-size:13px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; line-height:1.5; resize:vertical;"><?php echo esc_textarea($admin_notes ?? $repo->get_admin_notes($user_id)); ?></textarea>
    </div>
    <div style="margin-top:14px; display:flex; align-items:center; gap:10px;">
        <button type="button" class="button button-primary mm-save-notes-btn" data-user-id="<?php echo (int) $user_id; ?>" data-editor-id="mm-sidebar-admin-notes-textarea">
            <?php esc_html_e('Save Notes', 'matchmaker'); ?>
        </button>
        <span class="mm-notes-save-status" style="font-size:12px; font-weight:600; display:none;"></span>
    </div>
</div>
