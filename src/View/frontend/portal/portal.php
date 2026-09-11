<?php
/**
 * View: Member Portal – Main Outer Wrapper & Header Navigation
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

$form_url   = \Matchmaker\Service\ProfileService::instance()->get_form_url();
$events_url = \Matchmaker\Service\ProfileService::instance()->get_events_url();
$mem_url    = \Matchmaker\Service\ProfileService::instance()->get_membership_account_url();
?>
<div class="mm-portal-canvas" id="mm-portal-app">
    
    <!-- Top Slide-Out Toast Notification Box -->
    <div id="mm-toast-box" class="mm-toast-hidden" role="alert" aria-live="polite">
        <div class="mm-toast-content">
            <span class="mm-toast-icon">★</span>
            <div class="mm-toast-text">
                <strong><?php esc_html_e('New Match Available!', 'matchmaker'); ?></strong>
                <p><?php esc_html_e('You have a new approved match awaiting your review.', 'matchmaker'); ?></p>
            </div>
            <button type="button" class="mm-toast-close" data-mm-action="close-toast">&times;</button>
        </div>
    </div>

    <!-- Header Navigation -->
    <header class="portal-header">
        <nav class="nav-tabs" role="tablist">
            <button type="button" class="nav-tab active" data-tab="profile" role="tab">
                <?php esc_html_e('Profile', 'matchmaker'); ?>
            </button>
            <button type="button" class="nav-tab" data-tab="matches" role="tab">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" style="margin-right:2px;">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <?php esc_html_e('Matches', 'matchmaker'); ?>
                <span class="mm-tab-badge <?php echo $unread_count > 0 ? '' : 'mm-hidden'; ?>" <?php echo $unread_count > 0 ? '' : 'style="display:none;"'; ?>><?php echo (int) $unread_count; ?></span>
            </button>
            <button type="button" class="nav-tab" data-tab="events" role="tab">
                <?php esc_html_e('Events', 'matchmaker'); ?>
            </button>
            <button type="button" class="nav-tab" data-tab="services" role="tab">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:2px;">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <?php esc_html_e('Services', 'matchmaker'); ?>
            </button>
        </nav>

        <div class="header-actions">
            <?php 
            $is_vip_active = !empty($has_one_on_one) || (!empty($user_id) && $repo->has_one_on_one((int) $user_id));
            if ($is_vip_active) : 
            ?>
                <span class="mm-vip-header-badge" style="display:inline-flex; align-items:center; gap:5px; background:linear-gradient(135deg, #FAF5F0 0%, #F5EFEB 100%); color:#CC723F; border:1px solid rgba(204,114,63,0.35); padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; box-shadow:0 2px 6px rgba(204,114,63,0.12);">
                    <span style="font-size:12px;">★</span> <?php esc_html_e('1-on-1 VIP', 'matchmaker'); ?>
                </span>
            <?php endif; ?>
            <div class="mm-bell-wrapper" title="<?php esc_attr_e('Notifications', 'matchmaker'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <span class="mm-bell-badge <?php echo $unread_count > 0 ? '' : 'mm-hidden'; ?>" <?php echo $unread_count > 0 ? '' : 'style="display:none;"'; ?>><?php echo (int) $unread_count; ?></span>
            </div>
            <a href="<?php echo esc_url($mem_url); ?>" class="header-icon-btn" title="<?php esc_attr_e('Settings', 'matchmaker'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </a>
            <div class="header-avatar-box">
                <?php if (!empty($photos['user_photo1'])) : ?>
                    <img src="<?php echo esc_url($photos['user_photo1']); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="header-avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else : ?>
                    <div class="header-avatar-placeholder" style="width:40px;height:40px;border-radius:50%;background:#CC723F;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;"><?php echo esc_html(strtoupper(substr($user->display_name ?: 'U', 0, 1))); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- TAB 1: PROFILE VIEW -->
    <div class="portal-tab-panel active" id="mm-tab-profile">
        <?php include __DIR__ . '/tab-profile.php'; ?>
    </div>

    <!-- TAB 2: MATCHES VIEW -->
    <div class="portal-tab-panel" id="mm-tab-matches" style="display:none;">
        <?php include __DIR__ . '/tab-matches.php'; ?>
    </div>

    <!-- TAB 3: EVENTS VIEW -->
    <div class="portal-tab-panel" id="mm-tab-events" style="display:none;">
        <?php include __DIR__ . '/tab-events.php'; ?>
    </div>

    <!-- TAB 4: SERVICES VIEW -->
    <div class="portal-tab-panel" id="mm-tab-services" style="display:none;">
        <?php include __DIR__ . '/tab-services.php'; ?>
    </div>

</div><!-- .mm-portal-canvas -->
