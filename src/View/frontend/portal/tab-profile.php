<?php
/**
 * View: Member Portal – Profile Tab
 *
 * Available variables (extracted from $data array in PortalController):
 *   @var int                                    $user_id
 *   @var \WP_User                               $user
 *   @var string                                 $user_type
 *   @var bool                                   $is_premium
 *   @var array<string, mixed>|null              $pool
 *   @var array<string, mixed>                   $stats
 *   @var int                                    $unread_count
 *   @var array<string, string>                  $photos
 *   @var array<string, mixed>                   $meta
 *   @var string                                 $dashboard_url
 *   @var \Matchmaker\Repository\MatchRepository $repo
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$photo    = !empty($photos['user_photo1']) ? $photos['user_photo1'] : '';
$name     = $user->display_name;
$age      = $repo->calc_age($pool['birth_date'] ?? '');
$height   = $repo->cm_to_feet((int) ($pool['height_cm'] ?? 0));
$smoking  = !empty($pool['smoking']) ? $pool['smoking'] : '—';
$drinking = !empty($pool['drinking']) ? $pool['drinking'] : '—';

$form_url   = \Matchmaker\Service\ProfileService::instance()->get_form_url();
$events_url = \Matchmaker\Service\ProfileService::instance()->get_events_url();
$mem_url    = \Matchmaker\Service\ProfileService::instance()->get_membership_account_url();
$pmpro_url  = \Matchmaker\Service\ProfileService::instance()->get_membership_checkout_url();

$badge_label = $repo->format_tier_label($user_type);
?>
<div class="az-wrap">

    <div class="az-card az-about-card">
        <div class="az-about-photo">
            <?php if (!empty($photo)) : ?>
                <img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($name); ?>" style="border-radius:14px;object-fit:cover;">
            <?php else : ?>
                <div class="az-photo-placeholder" style="width:320px;height:320px;border-radius:14px;background:#f0ede6;display:flex;align-items:center;justify-content:center;font-size:48px;color:#CC723F;font-weight:700;">
                    <?php echo esc_html(strtoupper(substr($name ?: 'U', 0, 1))); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="az-about-info">
            <div class="az-about-header"><h2><?php esc_html_e('About Me', 'matchmaker'); ?></h2><a href="<?php echo esc_url($form_url); ?>" class="az-edit-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:2px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg><?php esc_html_e('Edit Profile', 'matchmaker'); ?></a></div>
            <p class="az-user-name"><?php echo esc_html($name); ?></p>

            <div class="az-rows">
                <?php
                $loc_parts = array_filter([$pool['city'] ?? '', $pool['state'] ?? '', $pool['country'] ?? '']);
                $user_location_display = !empty($loc_parts) ? implode(', ', $loc_parts) : ($pool['location'] ?? '—');
                ?>
                <div class="az-row"><span class="az-label"><?php esc_html_e('Location', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($user_location_display); ?></span></div>
                <div class="az-row"><span class="az-label"><?php esc_html_e('Age', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($age); ?> <?php esc_html_e('Years Old', 'matchmaker'); ?></span></div>
                <div class="az-row"><span class="az-label"><?php esc_html_e('Origin', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['origin'] ?? '—'); ?></span></div>
                <div class="az-row"><span class="az-label"><?php esc_html_e('Languages', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['languages'] ?? '—'); ?></span></div>
                <div class="az-row"><span class="az-label"><?php esc_html_e('Religion', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['religion'] ?? '—'); ?></span></div>
                <div class="az-row"><span class="az-label"><?php esc_html_e('Marital Status', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($meta['user_marital_status'] ?? '—'); ?></span></div>
            </div>
        </div>
    </div>

    <div class="az-card az-mm-card">
        <div class="az-mm-header">
            <h3><?php esc_html_e('Your Matchmaking', 'matchmaker'); ?></h3>
            <span class="az-badge">● <?php echo esc_html($badge_label); ?></span>
        </div>

        
        <?php if($is_premium): ?>
        <p class="az-mm-sub"><?php esc_html_e('Your profile is currently active and eligible to receive curated matches each cycle.', 'matchmaker'); ?></p>

        <div class="az-stats-grid">
            <div class="az-stat-box">
                <div class="az-stat-num"><?php echo esc_html(sprintf('%02d', $stats['received_this_term'])); ?></div>
                <div class="az-stat-caption"><?php esc_html_e('Matches received this month', 'matchmaker'); ?></div>
            </div>
            <div class="az-stat-box">
                <div class="az-stat-num"><?php echo esc_html(sprintf('%02d', $stats['days_remaining'])); ?></div>
                <div class="az-stat-caption"><?php esc_html_e('Days remaining to respond', 'matchmaker'); ?></div>
            </div>
            <div class="az-stat-box">
                <div class="az-stat-num"><?php echo esc_html(sprintf('%02d', $stats['total_accepted'])); ?></div>
                <div class="az-stat-caption"><?php esc_html_e('Total matches accepted this month', 'matchmaker'); ?></div>
            </div>
        </div>

        <?php else : ?>
            <div class="az-card mm-upsell-card profile-card" style="margin-bottom:0;">
                <div class="mm-upsell-badge">★ <?php esc_html_e('Monthly Membership Required', 'matchmaker'); ?></div>
                <h2><?php esc_html_e('Unlock Your Hand-Picked Matches', 'matchmaker'); ?></h2>
                <p><?php esc_html_e('You are currently on a Free or Event membership. Upgrade to our Monthly Matchmaking plan to receive curated, bi-directionally compatible matches every cycle.', 'matchmaker'); ?></p>
                <a href="<?php echo esc_url($pmpro_url); ?>" class="btn btn-primary mm-upsell-btn">
                    <?php esc_html_e('Get Monthly Membership →', 'matchmaker'); ?>
                </a>
            </div>

        <?php endif; ?>
    </div>

    <div class="az-card">
        <h3 class="az-card-title"><?php esc_html_e('A Little More About Me', 'matchmaker'); ?></h3>
        <div class="az-rows">
            <div class="az-row"><span class="az-label"><?php esc_html_e('Height', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($height); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Education', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($meta['user_education'] ?? '—'); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Career', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['job'] ?? '—'); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Lifestyle', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html("{$smoking}, {$drinking}"); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Prayer Habits', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($meta['user_prayer'] ?? '—'); ?></span></div>
        </div>
    </div>

    <div class="az-card">
        <h3 class="az-card-title"><?php esc_html_e('Who I Am Looking For', 'matchmaker'); ?></h3>
        <?php if (!empty($meta['pref_additional_info'])) : ?>
            <p class="az-looking-text"><?php echo esc_html($meta['pref_additional_info']); ?></p>
        <?php endif; ?>
        <div class="az-rows">
            <?php
            $pref_loc_parts = array_filter([$pool['pref_city'] ?? '', $pool['pref_state'] ?? '', $pool['pref_country'] ?? '']);
            $pref_location_display = !empty($pref_loc_parts) ? implode(', ', $pref_loc_parts) : ($pool['pref_location'] ?? '—');
            ?>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Preferred Location', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pref_location_display); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Preferred Background', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['pref_origin'] ?? '—'); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Age Preference', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html(($pool['preferred_age_min'] ?? 18) . ' to ' . ($pool['preferred_age_max'] ?? 80)); ?></span></div>
            <div class="az-row"><span class="az-label"><?php esc_html_e('Relationship Goal', 'matchmaker'); ?></span><span class="az-value"><?php esc_html_e('Marriage', 'matchmaker'); ?></span></div>
        </div>
    </div>

</div>
