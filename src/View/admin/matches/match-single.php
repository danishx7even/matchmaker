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
 *   @var string               $reset_url
 *   @var string               $st
 *   @var array<string, mixed> $breakdown
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo       = \Matchmaker\Repository\MatchRepository::instance();
$pmpro_sync = \Matchmaker\Core\PMProSync::instance();

// User 1 Photos & Data
$photo1_1 = !empty($m1['user_photo1']) ? $m1['user_photo1'] : (!empty($p1['user_photo1']) ? $p1['user_photo1'] : (string) get_user_meta($u1_id, 'user_photo1', true));
$photo1_2 = !empty($m1['user_photo2']) ? $m1['user_photo2'] : (!empty($p1['user_photo2']) ? $p1['user_photo2'] : (string) get_user_meta($u1_id, 'user_photo2', true));
$photo1_3 = !empty($m1['user_photo3']) ? $m1['user_photo3'] : (!empty($p1['user_photo3']) ? $p1['user_photo3'] : (string) get_user_meta($u1_id, 'user_photo3', true));
$age1     = is_array($p1) ? $repo->calc_age($p1['birth_date'] ?? '') : '—';
$height1  = is_array($p1) ? $repo->cm_to_feet(!empty($p1['height_cm']) ? (int) $p1['height_cm'] : null) : '—';
$type1    = is_array($p1) ? ($p1['user_type'] ?? 'free') : 'free';
$srv1     = $pmpro_sync->get_user_active_services($u1_id);
$cancel1  = $pmpro_sync->get_user_cancellation_info($u1_id);

// User 2 Photos & Data
$photo2_1 = !empty($m2['user_photo1']) ? $m2['user_photo1'] : (!empty($p2['user_photo1']) ? $p2['user_photo1'] : (string) get_user_meta($u2_id, 'user_photo1', true));
$photo2_2 = !empty($m2['user_photo2']) ? $m2['user_photo2'] : (!empty($p2['user_photo2']) ? $p2['user_photo2'] : (string) get_user_meta($u2_id, 'user_photo2', true));
$photo2_3 = !empty($m2['user_photo3']) ? $m2['user_photo3'] : (!empty($p2['user_photo3']) ? $p2['user_photo3'] : (string) get_user_meta($u2_id, 'user_photo3', true));
$age2     = is_array($p2) ? $repo->calc_age($p2['birth_date'] ?? '') : '—';
$height2  = is_array($p2) ? $repo->cm_to_feet(!empty($p2['height_cm']) ? (int) $p2['height_cm'] : null) : '—';
$type2    = is_array($p2) ? ($p2['user_type'] ?? 'free') : 'free';
$srv2     = $pmpro_sync->get_user_active_services($u2_id);
$cancel2  = $pmpro_sync->get_user_cancellation_info($u2_id);

$st_label = ($st === 'matched') ? __('Mutual Match', 'matchmaker') : ucfirst(str_replace('_', ' ', $st));
?>

<div class="wrap mm-admin-wrap">
    <p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Matches Queue', 'matchmaker'); ?></a></p>

    <!-- Top Header Card with Actions -->
    <div class="mm-detail-header">
        <div>
            <h2 style="margin:0 0 6px;">
                <?php echo esc_html(sprintf(__('Match Pair #%d Side-by-Side Review', 'matchmaker'), $match_id)); ?>
            </h2>
            <p class="description" style="margin:0;">
                <strong><?php esc_html_e('Compatibility Score:', 'matchmaker'); ?></strong> 
                <span style="color:#0284c7; font-weight:700;"><?php echo (int) ($match['score'] ?? 0); ?> / 6</span> &nbsp;|&nbsp; 
                <strong><?php esc_html_e('Match Source:', 'matchmaker'); ?></strong> <?php echo esc_html(ucfirst($match['match_source'] ?? 'auto')); ?> &nbsp;|&nbsp; 
                <strong><?php esc_html_e('Status:', 'matchmaker'); ?></strong> <span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html($st_label); ?></span>
                <?php if (!empty($match['created_at'])) : ?>
                    &nbsp;|&nbsp; <strong><?php esc_html_e('Created:', 'matchmaker'); ?></strong> <?php echo esc_html(substr($match['created_at'], 0, 10)); ?>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($st === 'pending_review') : ?>
                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-hero"><?php esc_html_e('Approve Match', 'matchmaker'); ?></a>
                <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary button-hero mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
            <?php elseif ($st === 'approved') : ?>
                <a href="<?php echo esc_url($cancel_url); ?>" class="button button-secondary button-hero mm-cancel-approval-link" style="color:#b91c1c;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this approved match and revert it to pending review? Member quotas will be restored.', 'matchmaker')); ?>');"><?php esc_html_e('Cancel Approval', 'matchmaker'); ?></a>
            <?php elseif (in_array($st, ['rejected', 'admin_rejected', 'expired'], true)) : ?>
                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary button-hero mm-reset-pending-link" style="color:#0284c7; border-color:#0284c7;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset this match to pending review? Member responses will be reset to pending and the match can be re-evaluated.', 'matchmaker')); ?>');"><?php esc_html_e('Reset to Pending', 'matchmaker'); ?></a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dual Full Profiles Section -->
    <div class="mm-grid-two">
        <!-- =========================================================
             USER 1 FULL PROFILE CARD
             ========================================================= -->
        <div class="mm-card" style="margin-bottom:0;">
            <!-- User 1 Header -->
            <div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0 0 6px; font-size:18px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $u1_id)); ?>" style="text-decoration:none; color:inherit;">
                                <?php echo esc_html($u1 ? $u1->display_name : 'User #' . $u1_id); ?>
                            </a>
                            <span class="mm-badge mm-badge-<?php echo esc_attr($type1); ?>">
                                <?php echo esc_html($repo->format_tier_label($type1)); ?>
                            </span>
                            <?php if (!empty($p1['is_parent_applying']) || !empty($m1['is_parent_applying'])) : ?>
                                <span class="mm-badge mm-badge-parent" style="font-size:11px; padding:3px 8px;">
                                    👨‍👩‍👧 <?php esc_html_e('Parent Applying', 'matchmaker'); ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="description" style="margin:0; font-size:12px; color:#64748b;">
                            <strong><?php esc_html_e('Email:', 'matchmaker'); ?></strong> <?php echo esc_html($u1 ? $u1->user_email : '—'); ?> &nbsp;|&nbsp; 
                            <strong><?php esc_html_e('Phone:', 'matchmaker'); ?></strong> <?php echo esc_html($m1['phone_number'] ?? ($m1['user_phone'] ?? 'N/A')); ?>
                        </p>
                    </div>
                    <span style="font-size:11px; font-weight:700; color:#64748b; background:#f1f5f9; padding:3px 8px; border-radius:4px;">
                        <?php esc_html_e('User 1 (Initiator / A)', 'matchmaker'); ?>
                    </span>
                </div>

                <?php if (!empty($srv1)) : ?>
                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:8px;">
                        <?php foreach ($srv1 as $usrv) : ?>
                            <span class="mm-badge mm-badge-service" style="font-size:10px; padding:2px 6px;">
                                ★ <?php echo esc_html($usrv['tag']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($cancel1)) : ?>
                    <div style="margin-top:8px; font-size:11px; color:#b45309; background:#fffbeb; padding:4px 8px; border-radius:4px; border:1px solid #fde68a;">
                        ⚠️ <?php echo esc_html(!empty($cancel1['expires_at']) ? sprintf(__('Cancelled (Active until %s)', 'matchmaker'), $cancel1['expires_at']) : __('Subscription Cancelled', 'matchmaker')); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User 1 Photos Grid -->
            <?php if (!empty($photo1_1) || !empty($photo1_2) || !empty($photo1_3)) : ?>
                <div class="mm-photos-grid" style="margin-bottom:15px;">
                    <?php if (!empty($photo1_1)) : ?><img src="<?php echo esc_url($photo1_1); ?>" alt="Photo 1" data-mm-lightbox="u1-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                    <?php if (!empty($photo1_2)) : ?><img src="<?php echo esc_url($photo1_2); ?>" alt="Photo 2" data-mm-lightbox="u1-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                    <?php if (!empty($photo1_3)) : ?><img src="<?php echo esc_url($photo1_3); ?>" alt="Photo 3" data-mm-lightbox="u1-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- User 1 Self Profile -->
            <h4 style="margin:0 0 8px; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                <?php esc_html_e('Self Profile', 'matchmaker'); ?>
            </h4>
            <?php
            $u1_loc_parts = is_array($p1) ? array_filter([$p1['city'] ?? '', $p1['state'] ?? '', $p1['country'] ?? '']) : [];
            $u1_loc = !empty($u1_loc_parts) ? implode(', ', $u1_loc_parts) : (is_array($p1) ? ($p1['location'] ?? '—') : '—');
            ?>
            <table class="mm-kv-table" style="margin-bottom:15px;">
                <?php if (!empty($p1['is_parent_applying']) || !empty($m1['is_parent_applying'])) : ?>
                    <tr><th><?php esc_html_e('Application Type', 'matchmaker'); ?></th><td><strong style="color:#CC723F;"><?php esc_html_e('Parent applying on behalf of child', 'matchmaker'); ?></strong></td></tr>
                <?php endif; ?>
                <tr><th><?php esc_html_e('Age / Date of Birth', 'matchmaker'); ?></th><td><?php echo esc_html($age1 . ' yrs (' . (is_array($p1) ? ($p1['birth_date'] ?? 'N/A') : 'N/A') . ')'); ?></td></tr>
                <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ucfirst($p1['gender'] ?? '') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($u1_loc); ?></td></tr>
                <tr><th><?php esc_html_e('Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($m1['user_citizenship'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['origin'] ?: '—') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p1) ? ($p1['religion'] ?? '—') : '—') . ' / ' . (is_array($p1) ? ($p1['modesty'] ?? '—') : '—')); ?></td></tr>
                <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th><td><?php echo esc_html($height1); ?></td></tr>
                <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['languages'] ?: '—') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Job / Career', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['job'] ?: '—') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p1) ? ($p1['smoking'] ?: '—') : '—') . ' / ' . (is_array($p1) ? ($p1['drinking'] ?: '—') : '—')); ?></td></tr>
                <tr><th><?php esc_html_e('Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($m1['user_marital_status'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Children', 'matchmaker'); ?></th><td><?php echo esc_html($m1['user_children'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Education Level', 'matchmaker'); ?></th><td><?php echo esc_html($m1['user_education'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Yearly Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($m1['user_income'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo $repo->format_social_links_html($m1['user_social_links'] ?? ''); ?></td></tr>
            </table>

            <!-- User 1 Partner Preferences -->
            <h4 style="margin:15px 0 8px; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                <?php esc_html_e('Partner Preferences', 'matchmaker'); ?>
            </h4>
            <?php
            $p1_pref_loc_parts = is_array($p1) ? array_filter([$p1['pref_city'] ?? '', $p1['pref_state'] ?? '', $p1['pref_country'] ?? '']) : [];
            $p1_pref_loc = !empty($p1_pref_loc_parts) ? implode(', ', $p1_pref_loc_parts) : (is_array($p1) ? ($p1['pref_location'] ?: 'Any') : 'Any');
            ?>
            <table class="mm-kv-table" style="margin-bottom:15px;">
                <tr><th><?php esc_html_e('Preferred Gender', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ucfirst($p1['pref_gender'] ?? 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Age Range', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p1) ? ($p1['preferred_age_min'] ?? 18) : 18) . ' – ' . (is_array($p1) ? ($p1['preferred_age_max'] ?? 80) : 80) . ' yrs'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Location', 'matchmaker'); ?></th><td><?php echo esc_html($p1_pref_loc); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($m1['pref_citizenship'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Origin', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['pref_origin'] ?: 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Religion', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['pref_religion'] ?: 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) ? ($p1['pref_modesty'] ?: 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p1) && ($p1['preferred_height_min'] || $p1['preferred_height_max']) ? ($p1['preferred_height_min'] ? $p1['preferred_height_min'] . 'cm' : 'Min') . ' – ' . ($p1['preferred_height_max'] ? $p1['preferred_height_max'] . 'cm' : 'Max') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p1) ? ($p1['pref_smoking'] ?: 'Any') : 'Any') . ' / ' . (is_array($p1) ? ($p1['pref_drinking'] ?: 'Any') : 'Any')); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($m1['pref_marital_status'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Children', 'matchmaker'); ?></th><td><?php echo esc_html($m1['pref_children'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Education', 'matchmaker'); ?></th><td><?php echo esc_html($m1['pref_education'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Yearly Income', 'matchmaker'); ?></th><td><?php echo esc_html($m1['pref_income'] ?? 'Any'); ?></td></tr>
            </table>

            <!-- User 1 About Me / Perfect Match Text -->
            <?php if (!empty($m1['user_about_me'])) : ?>
                <div style="margin-top:12px; padding:10px 12px; background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:4px;">
                    <strong style="font-size:12px; color:#334155;"><?php esc_html_e('About Myself:', 'matchmaker'); ?></strong>
                    <p style="margin:4px 0 0; color:#475569; font-style:italic; font-size:13px; line-height:1.4;">
                        "<?php echo esc_html($m1['user_about_me']); ?>"
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($m1['pref_additional_info'])) : ?>
                <div style="margin-top:8px; padding:10px 12px; background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:4px;">
                    <strong style="font-size:12px; color:#334155;"><?php esc_html_e('About My Perfect Match:', 'matchmaker'); ?></strong>
                    <p style="margin:4px 0 0; color:#475569; font-style:italic; font-size:13px; line-height:1.4;">
                        "<?php echo esc_html($m1['pref_additional_info']); ?>"
                    </p>
                </div>
            <?php endif; ?>

            <!-- User 1 Response Box -->
            <div style="margin-top:15px; padding:12px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:13px; color:#334155;"><?php esc_html_e('Member Response:', 'matchmaker'); ?></strong>
                    <?php
                    $u1_resp = $match['user_one_response'] ?? 'pending';
                    if ($u1_resp === 'accepted') : ?>
                        <span style="color:#16a34a; font-weight:700; font-size:13px;">✓ <?php esc_html_e('Accepted', 'matchmaker'); ?></span>
                    <?php elseif ($u1_resp === 'rejected') : ?>
                        <span style="color:#dc2626; font-weight:700; font-size:13px;">✕ <?php esc_html_e('Declined', 'matchmaker'); ?></span>
                    <?php else : ?>
                        <span style="color:#d97706; font-weight:600; font-size:13px;">⏳ <?php esc_html_e('Pending Member Review', 'matchmaker'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($match['user_one_rejection_reason'])) : ?>
                    <div style="margin-top:8px; padding:8px 10px; background:#fee2e2; border-left:3px solid #ef4444; border-radius:4px; font-size:12px; color:#991b1b; line-height:1.4;">
                        <strong><?php esc_html_e('Decline Reason:', 'matchmaker'); ?></strong> <?php echo esc_html($match['user_one_rejection_reason']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =========================================================
             USER 2 FULL PROFILE CARD
             ========================================================= -->
        <div class="mm-card" style="margin-bottom:0;">
            <!-- User 2 Header -->
            <div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0 0 6px; font-size:18px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $u2_id)); ?>" style="text-decoration:none; color:inherit;">
                                <?php echo esc_html($u2 ? $u2->display_name : 'User #' . $u2_id); ?>
                            </a>
                            <span class="mm-badge mm-badge-<?php echo esc_attr($type2); ?>">
                                <?php echo esc_html($repo->format_tier_label($type2)); ?>
                            </span>
                            <?php if (!empty($p2['is_parent_applying']) || !empty($m2['is_parent_applying'])) : ?>
                                <span class="mm-badge mm-badge-parent" style="font-size:11px; padding:3px 8px;">
                                    👨‍👩‍👧 <?php esc_html_e('Parent Applying', 'matchmaker'); ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="description" style="margin:0; font-size:12px; color:#64748b;">
                            <strong><?php esc_html_e('Email:', 'matchmaker'); ?></strong> <?php echo esc_html($u2 ? $u2->user_email : '—'); ?> &nbsp;|&nbsp; 
                            <strong><?php esc_html_e('Phone:', 'matchmaker'); ?></strong> <?php echo esc_html($m2['phone_number'] ?? ($m2['user_phone'] ?? 'N/A')); ?>
                        </p>
                    </div>
                    <span style="font-size:11px; font-weight:700; color:#64748b; background:#f1f5f9; padding:3px 8px; border-radius:4px;">
                        <?php esc_html_e('User 2 (Candidate / B)', 'matchmaker'); ?>
                    </span>
                </div>

                <?php if (!empty($srv2)) : ?>
                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:8px;">
                        <?php foreach ($srv2 as $usrv) : ?>
                            <span class="mm-badge mm-badge-service" style="font-size:10px; padding:2px 6px;">
                                ★ <?php echo esc_html($usrv['tag']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($cancel2)) : ?>
                    <div style="margin-top:8px; font-size:11px; color:#b45309; background:#fffbeb; padding:4px 8px; border-radius:4px; border:1px solid #fde68a;">
                        ⚠️ <?php echo esc_html(!empty($cancel2['expires_at']) ? sprintf(__('Cancelled (Active until %s)', 'matchmaker'), $cancel2['expires_at']) : __('Subscription Cancelled', 'matchmaker')); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User 2 Photos Grid -->
            <?php if (!empty($photo2_1) || !empty($photo2_2) || !empty($photo2_3)) : ?>
                <div class="mm-photos-grid" style="margin-bottom:15px;">
                    <?php if (!empty($photo2_1)) : ?><img src="<?php echo esc_url($photo2_1); ?>" alt="Photo 1" data-mm-lightbox="u2-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                    <?php if (!empty($photo2_2)) : ?><img src="<?php echo esc_url($photo2_2); ?>" alt="Photo 2" data-mm-lightbox="u2-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                    <?php if (!empty($photo2_3)) : ?><img src="<?php echo esc_url($photo2_3); ?>" alt="Photo 3" data-mm-lightbox="u2-gallery" class="mm-lightbox-trigger" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>" onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}"><?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- User 2 Self Profile -->
            <h4 style="margin:0 0 8px; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                <?php esc_html_e('Self Profile', 'matchmaker'); ?>
            </h4>
            <?php
            $u2_loc_parts = is_array($p2) ? array_filter([$p2['city'] ?? '', $p2['state'] ?? '', $p2['country'] ?? '']) : [];
            $u2_loc = !empty($u2_loc_parts) ? implode(', ', $u2_loc_parts) : (is_array($p2) ? ($p2['location'] ?? '—') : '—');
            ?>
            <table class="mm-kv-table" style="margin-bottom:15px;">
                <?php if (!empty($p2['is_parent_applying']) || !empty($m2['is_parent_applying'])) : ?>
                    <tr><th><?php esc_html_e('Application Type', 'matchmaker'); ?></th><td><strong style="color:#CC723F;"><?php esc_html_e('Parent applying on behalf of child', 'matchmaker'); ?></strong></td></tr>
                <?php endif; ?>
                <tr><th><?php esc_html_e('Age / Date of Birth', 'matchmaker'); ?></th><td><?php echo esc_html($age2 . ' yrs (' . (is_array($p2) ? ($p2['birth_date'] ?? 'N/A') : 'N/A') . ')'); ?></td></tr>
                <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ucfirst($p2['gender'] ?? '') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($u2_loc); ?></td></tr>
                <tr><th><?php esc_html_e('Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($m2['user_citizenship'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['origin'] ?: '—') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p2) ? ($p2['religion'] ?? '—') : '—') . ' / ' . (is_array($p2) ? ($p2['modesty'] ?? '—') : '—')); ?></td></tr>
                <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th><td><?php echo esc_html($height2); ?></td></tr>
                <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['languages'] ?: '—') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Job / Career', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['job'] ?: '—') : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p2) ? ($p2['smoking'] ?: '—') : '—') . ' / ' . (is_array($p2) ? ($p2['drinking'] ?: '—') : '—')); ?></td></tr>
                <tr><th><?php esc_html_e('Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($m2['user_marital_status'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Children', 'matchmaker'); ?></th><td><?php echo esc_html($m2['user_children'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Education Level', 'matchmaker'); ?></th><td><?php echo esc_html($m2['user_education'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Yearly Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($m2['user_income'] ?? '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo $repo->format_social_links_html($m2['user_social_links'] ?? ''); ?></td></tr>
            </table>

            <!-- User 2 Partner Preferences -->
            <h4 style="margin:15px 0 8px; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; color:#475569; border-bottom:1px solid #f1f5f9; padding-bottom:4px;">
                <?php esc_html_e('Partner Preferences', 'matchmaker'); ?>
            </h4>
            <?php
            $p2_pref_loc_parts = is_array($p2) ? array_filter([$p2['pref_city'] ?? '', $p2['pref_state'] ?? '', $p2['pref_country'] ?? '']) : [];
            $p2_pref_loc = !empty($p2_pref_loc_parts) ? implode(', ', $p2_pref_loc_parts) : (is_array($p2) ? ($p2['pref_location'] ?: 'Any') : 'Any');
            ?>
            <table class="mm-kv-table" style="margin-bottom:15px;">
                <tr><th><?php esc_html_e('Preferred Gender', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ucfirst($p2['pref_gender'] ?? 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Age Range', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p2) ? ($p2['preferred_age_min'] ?? 18) : 18) . ' – ' . (is_array($p2) ? ($p2['preferred_age_max'] ?? 80) : 80) . ' yrs'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Location', 'matchmaker'); ?></th><td><?php echo esc_html($p2_pref_loc); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($m2['pref_citizenship'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Origin', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['pref_origin'] ?: 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Religion', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['pref_religion'] ?: 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) ? ($p2['pref_modesty'] ?: 'Any') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(is_array($p2) && ($p2['preferred_height_min'] || $p2['preferred_height_max']) ? ($p2['preferred_height_min'] ? $p2['preferred_height_min'] . 'cm' : 'Min') . ' – ' . ($p2['preferred_height_max'] ? $p2['preferred_height_max'] . 'cm' : 'Max') : 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html((is_array($p2) ? ($p2['pref_smoking'] ?: 'Any') : 'Any') . ' / ' . (is_array($p2) ? ($p2['pref_drinking'] ?: 'Any') : 'Any')); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($m2['pref_marital_status'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Children', 'matchmaker'); ?></th><td><?php echo esc_html($m2['pref_children'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Education', 'matchmaker'); ?></th><td><?php echo esc_html($m2['pref_education'] ?? 'Any'); ?></td></tr>
                <tr><th><?php esc_html_e('Preferred Yearly Income', 'matchmaker'); ?></th><td><?php echo esc_html($m2['pref_income'] ?? 'Any'); ?></td></tr>
            </table>

            <!-- User 2 About Me / Perfect Match Text -->
            <?php if (!empty($m2['user_about_me'])) : ?>
                <div style="margin-top:12px; padding:10px 12px; background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:4px;">
                    <strong style="font-size:12px; color:#334155;"><?php esc_html_e('About Myself:', 'matchmaker'); ?></strong>
                    <p style="margin:4px 0 0; color:#475569; font-style:italic; font-size:13px; line-height:1.4;">
                        "<?php echo esc_html($m2['user_about_me']); ?>"
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($m2['pref_additional_info'])) : ?>
                <div style="margin-top:8px; padding:10px 12px; background:#f8fafc; border-left:3px solid #cbd5e1; border-radius:4px;">
                    <strong style="font-size:12px; color:#334155;"><?php esc_html_e('About My Perfect Match:', 'matchmaker'); ?></strong>
                    <p style="margin:4px 0 0; color:#475569; font-style:italic; font-size:13px; line-height:1.4;">
                        "<?php echo esc_html($m2['pref_additional_info']); ?>"
                    </p>
                </div>
            <?php endif; ?>

            <!-- User 2 Response Box -->
            <div style="margin-top:15px; padding:12px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:13px; color:#334155;"><?php esc_html_e('Member Response:', 'matchmaker'); ?></strong>
                    <?php
                    $u2_resp = $match['user_two_response'] ?? 'pending';
                    if ($u2_resp === 'accepted') : ?>
                        <span style="color:#16a34a; font-weight:700; font-size:13px;">✓ <?php esc_html_e('Accepted', 'matchmaker'); ?></span>
                    <?php elseif ($u2_resp === 'rejected') : ?>
                        <span style="color:#dc2626; font-weight:700; font-size:13px;">✕ <?php esc_html_e('Declined', 'matchmaker'); ?></span>
                    <?php else : ?>
                        <span style="color:#d97706; font-weight:600; font-size:13px;">⏳ <?php esc_html_e('Pending Member Review', 'matchmaker'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($match['user_two_rejection_reason'])) : ?>
                    <div style="margin-top:8px; padding:8px 10px; background:#fee2e2; border-left:3px solid #ef4444; border-radius:4px; font-size:12px; color:#991b1b; line-height:1.4;">
                        <strong><?php esc_html_e('Decline Reason:', 'matchmaker'); ?></strong> <?php echo esc_html($match['user_two_rejection_reason']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- =========================================================
         SIDE-BY-SIDE COMPATIBILITY COMPARISON TABLE
         ========================================================= -->
    <div class="mm-card" style="margin-top:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="margin:0 0 4px; border:0; padding:0; font-size:16px; font-weight:700; color:#0f172a;">
                    📊 <?php esc_html_e('Side-by-Side Compatibility Criteria Comparison', 'matchmaker'); ?>
                </h3>
                <p class="description" style="margin:0; font-size:13px;">
                    <?php esc_html_e('Full matrix evaluation comparing both candidates across hard eligibility gates and 6 flexible scoring points.', 'matchmaker'); ?>
                </p>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <span class="mm-badge mm-badge-free" style="font-size:12px; padding:4px 10px; border-radius:12px;">
                    <?php echo esc_html(sprintf(__('Matched %d / %d Criteria', 'matchmaker'), $breakdown['matching_criteria_count'] ?? 0, $breakdown['total_criteria_count'] ?? count($breakdown['criteria'] ?? []))); ?>
                </span>
                <span style="font-size:12px; font-weight:700; color:#0284c7; background:#e0f2fe; border:1px solid #bae6fd; padding:4px 10px; border-radius:12px;">
                    ★ <?php echo esc_html(sprintf(__('Flexible Score: %d / 6', 'matchmaker'), $breakdown['flexible_score'] ?? ($match['score'] ?? 0))); ?>
                </span>
            </div>
        </div>

        <div class="mm-table-responsive">
            <table class="wp-list-table widefat fixed striped mm-breakdown-table" style="min-width:700px;">
                <thead>
                    <tr>
                        <th style="width:22%; font-weight:700;"><?php esc_html_e('Criterion', 'matchmaker'); ?></th>
                        <th style="width:26%; font-weight:700;"><?php echo esc_html($u1 ? $u1->display_name : __('User 1', 'matchmaker')); ?></th>
                        <th style="width:26%; font-weight:700;"><?php echo esc_html($u2 ? $u2->display_name : __('User 2', 'matchmaker')); ?></th>
                        <th style="width:12%; text-align:center; font-weight:700;"><?php esc_html_e('Match Status', 'matchmaker'); ?></th>
                        <th style="width:14%; font-weight:700;"><?php esc_html_e('Scoring / Notes', 'matchmaker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($breakdown['criteria'])) : ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:20px; color:#64748b;">
                                <?php esc_html_e('No criteria comparison data available for this match pair.', 'matchmaker'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($breakdown['criteria'] as $c) :
                            $is_match = !empty($c['is_match']);
                            $cat      = $c['category'] ?? 'score';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($c['label']); ?></strong>
                                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; color:<?php echo ($cat === 'gate') ? '#b45309' : '#0284c7'; ?>;">
                                        <?php echo ($cat === 'gate') ? '🔒 ' . esc_html__('Hard Gate', 'matchmaker') : '★ ' . esc_html__('Flexible Point', 'matchmaker'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="color:#1e293b;"><?php echo esc_html($c['target_val']); ?></span>
                                </td>
                                <td>
                                    <span style="color:#1e293b;"><?php echo esc_html($c['candidate_val']); ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_match) : ?>
                                        <span class="mm-pill-match">✓ <?php esc_html_e('Matched', 'matchmaker'); ?></span>
                                    <?php else : ?>
                                        <span class="mm-pill-differ">✕ <?php esc_html_e('Differ', 'matchmaker'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color:<?php echo $is_match ? '#15803d' : '#64748b'; ?>; line-height:1.3; display:block;">
                                        <?php echo esc_html($c['note']); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bottom Action Bar Card -->
    <div class="mm-card" style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:16px 20px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <strong><?php esc_html_e('Current Match Status:', 'matchmaker'); ?></strong> 
            <span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html($st_label); ?></span>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($st === 'pending_review') : ?>
                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary"><?php esc_html_e('Approve Match', 'matchmaker'); ?></a>
                <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
            <?php elseif ($st === 'approved') : ?>
                <a href="<?php echo esc_url($cancel_url); ?>" class="button button-secondary mm-cancel-approval-link" style="color:#b91c1c;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this approved match and revert it to pending review? Member quotas will be restored.', 'matchmaker')); ?>');"><?php esc_html_e('Cancel Approval', 'matchmaker'); ?></a>
            <?php elseif (in_array($st, ['rejected', 'admin_rejected', 'expired'], true)) : ?>
                <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary mm-reset-pending-link" style="color:#0284c7; border-color:#0284c7;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset this match to pending review? Member responses will be reset to pending and the match can be re-evaluated.', 'matchmaker')); ?>');"><?php esc_html_e('Reset to Pending', 'matchmaker'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
