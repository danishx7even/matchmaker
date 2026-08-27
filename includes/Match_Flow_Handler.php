<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Match_Flow_Handler
 *
 * Handles the unified member portal shortcode [matchmaker_member_portal],
 * tab navigation, 5-state interactive match flow rendering, free tier upsell,
 * and AJAX match response processing (Accept / Decline).
 */
class Match_Flow_Handler
{
    private static ?self $instance = null;

    public const EVENTS_URL = 'https://arabzawaj.org/events-2/';
    public const FORM_URL   = 'https://arabzawaj.org/personal-matchmaking-questionnaire/';

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('matchmaker_member_portal', [$this, 'render_portal_shortcode']);
        add_shortcode('az_profile', [$this, 'render_portal_shortcode']); // Backward-compatibility alias

        // AJAX handlers for frontend match actions
        add_action('wp_ajax_mm_submit_match_response', [$this, 'handle_ajax_match_response']);
        add_action('wp_ajax_mm_reload_tab_content', [$this, 'handle_ajax_reload_tab']);
    }

    /**
     * Render the single unified member portal dashboard [matchmaker_member_portal].
     */
    public function render_portal_shortcode(): string
    {
        if (!is_user_logged_in()) {
            return '<div class="mm-portal-wrap"><div class="az-card"><p>'
                . esc_html__('Please log in to view your matchmaking member portal.', 'matchmaker')
                . '</p></div></div>';
        }

        $user_id  = get_current_user_id();
        $user_obj = wp_get_current_user();

        global $wpdb;
        $pool_table = $wpdb->prefix . 'matchmaking_pool';
        $pool       = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $user_id), ARRAY_A);

        if (!$pool) {
            return '<div class="mm-portal-wrap"><div class="az-card"><p>'
                . esc_html__('Your matchmaking profile has not been set up yet. Please complete your registration questionnaire.', 'matchmaker')
                . '</p></div></div>';
        }

        // Determine user membership tier
        $user_type = class_exists('\Matchmaker\PMPro_Sync')
            ? PMPro_Sync::instance()->get_current_user_type($user_id)
            : (string) get_user_meta($user_id, 'user_type', true);

        if (empty($user_type)) {
            $user_type = 'free';
        }

        $is_premium = in_array($user_type, ['monthly', 'one_on_one'], true);

        // Fetch meta, stats, and approved match records
        $meta       = $this->get_meta_block($user_id);
        $stats      = $this->get_match_stats($user_id);
        $matches    = $this->get_approved_matches($user_id);
        $unread_cnt = Notification_Manager::instance()->get_user_unread_count($user_id);

        ob_start();
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
                    <button type="button" class="mm-toast-close" onclick="MM_Portal.closeToast()">&times;</button>
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
                        <span class="mm-tab-badge <?php echo $unread_cnt > 0 ? '' : 'mm-hidden'; ?>" <?php echo $unread_cnt > 0 ? '' : 'style="display:none;"'; ?>><?php echo (int) $unread_cnt; ?></span>
                    </button>
                    <button type="button" class="nav-tab nav-tab-link" onclick="window.location.href='<?php echo esc_url(self::FORM_URL); ?>'" role="tab">
                        <?php esc_html_e('Forms', 'matchmaker'); ?>
                    </button>
                    <button type="button" class="nav-tab nav-tab-link" onclick="window.location.href='<?php echo esc_url(self::EVENTS_URL); ?>'" role="tab">
                        <?php esc_html_e('Events', 'matchmaker'); ?>
                    </button>
                </nav>

                <div class="header-actions">
                    <div class="mm-bell-wrapper" title="<?php esc_attr_e('Notifications', 'matchmaker'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="mm-bell-badge <?php echo $unread_cnt > 0 ? '' : 'mm-hidden'; ?>" <?php echo $unread_cnt > 0 ? '' : 'style="display:none;"'; ?>>
                            <?php echo (int) $unread_cnt; ?>
                        </span>
                    </div>

                    <button type="button" class="header-icon-btn" onclick="window.location.href='<?php echo esc_url(self::FORM_URL); ?>'" title="<?php esc_attr_e('Settings', 'matchmaker'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </button>

                    <div class="header-avatar-box">
                        <?php echo get_avatar($user_id, 40, '', $user_obj->display_name, ['class' => 'header-avatar']); ?>
                    </div>
                </div>
            </header>

            <!-- TAB 1: PROFILE VIEW -->
            <div class="portal-tab-panel active" id="mm-tab-profile">
                <?php echo $this->render_profile_tab_html($user_id, $user_obj, $pool, $meta, $stats); ?>
            </div>

            <!-- TAB 2: MATCHES VIEW (5-State Flow or Free Upsell) -->
            <div class="portal-tab-panel" id="mm-tab-matches" style="display:none;">
                <?php if (!$is_premium) : ?>
                    <?php echo $this->render_free_upsell_html(); ?>
                <?php else : ?>
                    <?php echo $this->render_matches_tab_html($user_id, $matches); ?>
                <?php endif; ?>
            </div>

        </div><!-- .mm-portal-canvas -->
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the Profile Tab HTML content.
     */
    private function render_profile_tab_html(int $user_id, \WP_User $user_obj, array $pool, array $meta, array $stats): string
    {
        $photo    = !empty($meta['user_photo1']) ? $meta['user_photo1'] : get_avatar_url($user_id, ['size' => 300]);
        $name     = $user_obj->display_name;
        $age      = $this->calc_age($pool['birth_date'] ?? '');
        $height   = $this->cm_to_feet((int) ($pool['height_cm'] ?? 0));
        $smoking  = !empty($pool['smoking']) ? $pool['smoking'] : '—';
        $drinking = !empty($pool['drinking']) ? $pool['drinking'] : '—';

        ob_start();
        ?>
        <div class="az-wrap">

            <div class="az-card az-about-card">
                <div class="az-about-photo">
                    <img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($name); ?>">
                </div>
                <div class="az-about-info">
                    <div class="az-about-header">
                        <h2><?php esc_html_e('About Me', 'matchmaker'); ?></h2>
                        <a href="<?php echo esc_url(self::FORM_URL); ?>" class="az-edit-btn">
                            <?php esc_html_e('Edit Profile', 'matchmaker'); ?>
                        </a>
                    </div>
                    <p class="az-user-name"><?php echo esc_html($name); ?></p>

                    <div class="az-rows">
                        <div class="az-row"><span class="az-label"><?php esc_html_e('Location', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['location'] ?? '—'); ?></span></div>
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
                    <span class="az-badge">● <?php esc_html_e('You are an active member', 'matchmaker'); ?></span>
                </div>
                <p class="az-mm-sub"><?php esc_html_e('Your profile is currently active and eligible to receive curated matches each cycle.', 'matchmaker'); ?></p>

                <div class="az-stats-grid">
                    <div class="az-stat-box">
                        <div class="az-stat-num"><?php echo esc_html(sprintf('%02d', $stats['received_this_term'])); ?></div>
                        <div class="az-stat-caption"><?php esc_html_e('Match received this term', 'matchmaker'); ?></div>
                    </div>
                    <div class="az-stat-box">
                        <div class="az-stat-num"><?php echo esc_html(sprintf('%02d', $stats['days_remaining'])); ?></div>
                        <div class="az-stat-caption"><?php esc_html_e('Days remaining to respond', 'matchmaker'); ?></div>
                    </div>
                    <div class="az-stat-box">
                        <div class="az-stat-num"><?php echo esc_html(sprintf('%02d', $stats['total_accepted'])); ?></div>
                        <div class="az-stat-caption"><?php esc_html_e('Total match accepted', 'matchmaker'); ?></div>
                    </div>
                </div>
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
                    <div class="az-row"><span class="az-label"><?php esc_html_e('Preferred Location', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['pref_location'] ?? '—'); ?></span></div>
                    <div class="az-row"><span class="az-label"><?php esc_html_e('Preferred Background', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html($pool['pref_origin'] ?? '—'); ?></span></div>
                    <div class="az-row"><span class="az-label"><?php esc_html_e('Age Preference', 'matchmaker'); ?></span><span class="az-value"><?php echo esc_html(($pool['preferred_age_min'] ?? 18) . ' to ' . ($pool['preferred_age_max'] ?? 80)); ?></span></div>
                    <div class="az-row"><span class="az-label"><?php esc_html_e('Relationship Goal', 'matchmaker'); ?></span><span class="az-value"><?php esc_html_e('Marriage', 'matchmaker'); ?></span></div>
                </div>
            </div>

        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render Free Tier Upgrade Banner for non-monthly users.
     */
    private function render_free_upsell_html(): string
    {
        $pmpro_url = function_exists('pmpro_url') ? pmpro_url('levels') : home_url('/membership-levels/');

        ob_start();
        ?>
        <div class="az-wrap">
            <div class="az-card mm-upsell-card">
                <div class="mm-upsell-badge">★ <?php esc_html_e('Monthly Membership Required', 'matchmaker'); ?></div>
                <h2><?php esc_html_e('Unlock Your Hand-Picked Matches', 'matchmaker'); ?></h2>
                <p><?php esc_html_e('You are currently on a Free or Event membership. Upgrade to our Monthly Matchmaking plan to receive curated, bi-directionally compatible matches every cycle.', 'matchmaker'); ?></p>
                <a href="<?php echo esc_url($pmpro_url); ?>" class="btn btn-primary mm-upsell-btn">
                    <?php esc_html_e('Get Monthly Membership →', 'matchmaker'); ?>
                </a>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the Matches Tab HTML content (5-State Interactive Match Flow).
     */
    private function render_matches_tab_html(int $user_id, array $matches): string
    {
        if (empty($matches)) {
            return '<div class="az-wrap"><div class="az-card"><h3 class="az-card-title">'
                . esc_html__('Your Matches', 'matchmaker') . '</h3><p class="az-empty">'
                . esc_html__('No active matches found. Check back soon for your next curated recommendation.', 'matchmaker')
                . '</p></div></div>';
        }

        // Active match selected for interactive 5-state flow (default to first active match)
        $active_match = $matches[0];

        ob_start();
        ?>
        <div class="mm-flow-container">

            <!-- STATE 1: DASHBOARD DISCOVERY (STEP 1) -->
            <div id="step-1" class="view-state active">
                <div class="dashboard-body">
                    <main class="main-discovery-content" style="width:100%;">
                        <div class="status-pill">★ <?php esc_html_e('Active Match Recommendation', 'matchmaker'); ?></div>
                        <h1 class="discovery-title"><?php esc_html_e('You Have a New Match', 'matchmaker'); ?></h1>
                        <p class="discovery-subtitle"><?php esc_html_e("We've found someone we think could be a meaningful match for you based on your shared values and requirements.", 'matchmaker'); ?></p>

                        <div class="new-match-card">
                            <div>
                                <div class="candidate-hero-block">
                                    <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 150])); ?>" alt="" class="candidate-square-thumb">
                                    <div class="candidate-meta">
                                        <h2 class="font-cormorant"><?php echo esc_html($active_match['name']); ?>, <?php echo esc_html($active_match['age']); ?></h2>
                                        <div class="meta-loc">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                            <?php echo esc_html($active_match['location']); ?>
                                        </div>
                                        <div class="meta-lang"><?php esc_html_e('Speaks', 'matchmaker'); ?>: <?php echo esc_html($active_match['languages'] ?: '—'); ?></div>
                                    </div>
                                </div>

                                <div class="candidate-quote">
                                    "<?php echo esc_html($active_match['pref_additional_info'] ?: __('Seeking a serious, family-oriented partner built on mutual respect and shared values.', 'matchmaker')); ?>"
                                </div>

                                <div class="candidate-tags-row">
                                    <span><?php echo esc_html($active_match['marital_status'] ?: '—'); ?></span>
                                    <span><?php echo esc_html($active_match['education'] ?: '—'); ?></span>
                                </div>
                            </div>

                            <div class="action-column">
                                <div>
                                    <div style="font-family: 'Cormorant SC', serif; font-size: 15px; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">
                                        <?php esc_html_e('Your Response', 'matchmaker'); ?>
                                    </div>
                                    <p><?php esc_html_e('Take your time to review this profile. You have 7 days to accept or decline this match before it expires.', 'matchmaker'); ?></p>
                                    <div class="timer-card">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        <div>
                                            <div class="timer-title"><?php esc_html_e('Time Remaining', 'matchmaker'); ?></div>
                                            <div class="timer-val"><?php echo (int) $active_match['days_remaining']; ?> <?php esc_html_e('days remaining', 'matchmaker'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px;">
                                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="MM_Portal.navigateStep(2)">
                                        <?php esc_html_e('View Match →', 'matchmaker'); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-dark" style="width: 100%;" onclick="MM_Portal.navigateStep(3)">
                                        <?php esc_html_e('View Status', 'matchmaker'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>

            <!-- STATE 2: FULL PROFILE REVIEW (STEP 2) -->
            <div id="step-2" class="view-state">
                <div style="padding: 24px 48px 0;">
                    <button type="button" class="mm-back-btn" onclick="MM_Portal.goBackStep()" style="display: inline-flex; align-items: center; gap: 6px; background: none; border: none; font-size: 14px; font-weight: 600; color: #CC723F; cursor: pointer; padding: 0;">
                        ← <?php esc_html_e('Back to Matches', 'matchmaker'); ?>
                    </button>
                </div>

                <div class="profile-view-body">
                    <h1 class="profile-view-title font-cormorant"><?php esc_html_e('Your Potential Match', 'matchmaker'); ?></h1>

                    <div class="profile-content-grid">
                        <aside class="photo-sidebar">
                            <div class="main-photo-frame">
                                <span class="photo-verified-tag">✓ <?php esc_html_e('Verified Profile', 'matchmaker'); ?></span>
                                <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 600])); ?>" alt="">
                            </div>

                            <div class="person-meta">
                                <h2 class="font-cormorant"><?php echo esc_html($active_match['name']); ?></h2>
                                <div class="loc-line"><?php echo esc_html($active_match['age']); ?> • <?php echo esc_html($active_match['location']); ?></div>

                                <div class="person-details-list">
                                    <div>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                        <?php echo esc_html($active_match['job'] ?: '—'); ?>
                                    </div>
                                    <div>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                                        <?php echo esc_html($active_match['education'] ?: '—'); ?>
                                    </div>
                                    <div>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 13 12 18 17 13"></polyline><polyline points="7 6 12 11 17 6"></polyline></svg>
                                        <?php echo esc_html($active_match['height_formatted']); ?>
                                    </div>
                                </div>
                            </div>
                        </aside>

                        <main class="details-stream">
                            <div class="about-quote-card">
                                <div class="about-title"><span style="color: #CC723F; font-size: 20px;">❞</span> <?php esc_html_e('About', 'matchmaker'); ?></div>
                                <p><?php echo esc_html($active_match['pref_additional_info'] ?: __('Dedicated individual who values family, growth, and building a meaningful connection based on shared values.', 'matchmaker')); ?></p>
                            </div>

                            <div>
                                <div class="section-header-title font-cormorant"><?php esc_html_e('Background', 'matchmaker'); ?></div>
                                <div class="background-tiles-row">
                                    <div class="bg-tile">
                                        <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></div>
                                        <div class="tile-label"><?php esc_html_e('Origin', 'matchmaker'); ?></div>
                                        <div class="tile-value"><?php echo esc_html($active_match['origin']); ?></div>
                                    </div>
                                    <div class="bg-tile">
                                        <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></div>
                                        <div class="tile-label"><?php esc_html_e('Location', 'matchmaker'); ?></div>
                                        <div class="tile-value"><?php echo esc_html($active_match['location']); ?></div>
                                    </div>
                                    <div class="bg-tile">
                                        <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10a3 3 0 0 1 6 0"></path></svg></div>
                                        <div class="tile-label"><?php esc_html_e('Religion', 'matchmaker'); ?></div>
                                        <div class="tile-value"><?php echo esc_html($active_match['religion']); ?></div>
                                    </div>
                                    <div class="bg-tile">
                                        <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></div>
                                        <div class="tile-label"><?php esc_html_e('Modesty', 'matchmaker'); ?></div>
                                        <div class="tile-value"><?php echo esc_html($active_match['modesty']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>

                <!-- Footer Action Dock (Inside Canvas Container) -->
                <footer class="floating-action-footer">
                    <?php if (($active_match['my_response'] ?? 'pending') === 'pending') : ?>
                        <div class="footer-cta-prompt">
                            <h4 class="font-cormorant"><?php esc_html_e('What do you think?', 'matchmaker'); ?></h4>
                            <p><?php esc_html_e('You have 7 days to respond to this match.', 'matchmaker'); ?></p>
                        </div>
                        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-outline-dark" onclick="MM_Portal.navigateStep(4)">
                                <?php esc_html_e('Decline Match', 'matchmaker'); ?>
                            </button>
                            <button type="button" class="btn btn-primary" onclick="MM_Portal.submitResponse(<?php echo (int) $active_match['match_id']; ?>, 'accept')">
                                <?php esc_html_e('Accept Match →', 'matchmaker'); ?>
                            </button>
                        </div>
                    <?php else : ?>
                        <div class="footer-cta-prompt">
                            <h4 class="font-cormorant"><?php esc_html_e('Response Submitted', 'matchmaker'); ?></h4>
                            <p><?php esc_html_e('You have already submitted your response for this match.', 'matchmaker'); ?></p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" onclick="MM_Portal.navigateStep(3)">
                                <?php esc_html_e('View Status →', 'matchmaker'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </footer>
            </div>

            <!-- STATE 3: ACCEPTED — WAITING FOR RESPONSE (STEP 3) -->
            <div id="step-3" class="view-state">
                <div style="padding: 24px 48px 0;">
                    <button type="button" class="mm-back-btn" onclick="MM_Portal.goBackStep()" style="display: inline-flex; align-items: center; gap: 6px; background: none; border: none; font-size: 14px; font-weight: 600; color: #CC723F; cursor: pointer; padding: 0;">
                        ← <?php esc_html_e('Back to Matches', 'matchmaker'); ?>
                    </button>
                </div>
                <div class="centered-state-wrapper">
                    <div class="status-avatar-bubble orange">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>

                    <h1 class="state-main-title font-cormorant"><?php esc_html_e('Match Accepted', 'matchmaker'); ?></h1>
                    <p class="state-main-desc"><?php esc_html_e("You've accepted this match. We're now waiting for the candidate to respond.", 'matchmaker'); ?></p>

                    <div class="responses-container-box">
                        <div class="response-entry-card">
                            <span class="res-label">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <?php esc_html_e('Your Response', 'matchmaker'); ?>
                            </span>
                            <span class="res-tag-accepted">✓ <?php esc_html_e('Accepted', 'matchmaker'); ?></span>
                        </div>

                        <div class="response-entry-card">
                            <span class="res-label">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                <?php esc_html_e('Their Response', 'matchmaker'); ?>
                            </span>
                            <span class="res-tag-waiting">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="8" y2="12"></line><line x1="12" y1="12" x2="12" y2="12"></line><line x1="16" y1="12" x2="16" y2="12"></line></svg>
                                <?php esc_html_e('Waiting', 'matchmaker'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="state-next-note">
                        <h3 class="font-cormorant"><?php esc_html_e("What's Next?", 'matchmaker'); ?></h3>
                        <p><?php esc_html_e("If they also accept, you'll both receive access to each other's approved direct contact information.", 'matchmaker'); ?></p>
                    </div>

                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="MM_Portal.switchTab('profile')">
                        <?php esc_html_e('Back to Profile Dashboard →', 'matchmaker'); ?>
                    </button>
                </div>
            </div>

            <!-- STATE 4: DECLINE CONFIRMATION MODAL (STEP 4) -->
            <div id="step-4" class="view-state">
                <div class="centered-state-wrapper">
                    <div class="status-avatar-bubble danger">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    </div>

                    <h1 class="state-main-title font-cormorant"><?php esc_html_e('Decline this match?', 'matchmaker'); ?></h1>
                    <p class="state-main-desc"><?php esc_html_e('Are you sure you want to decline this match? Once declined, this match will no longer be available to you.', 'matchmaker'); ?></p>

                    <div class="pending-id-pill-box">
                        <div class="pending-id-inner">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                            <?php esc_html_e('Pending Match for', 'matchmaker'); ?> <?php echo esc_html($active_match['name']); ?>
                        </div>
                    </div>

                    <div style="display: flex; gap: 14px;">
                        <button type="button" class="btn btn-primary" style="flex: 1;" onclick="MM_Portal.navigateStep(2)">
                            <?php esc_html_e('Keep Match', 'matchmaker'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-danger" style="flex: 1;" onclick="MM_Portal.submitResponse(<?php echo (int) $active_match['match_id']; ?>, 'decline')">
                            <?php esc_html_e('Decline Match →', 'matchmaker'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- STATE 5: MUTUAL MATCH — REVEALED CONTACT INFO (STEP 5) -->
            <div id="step-5" class="view-state">
                <div class="centered-state-wrapper" style="max-width: 620px;">
                    <div class="status-avatar-bubble orange">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M5.8 11.3 2 22l10.7-3.79"></path><path d="M4 3h.01"></path><path d="M22 8h.01"></path><path d="M15 2h.01"></path><path d="M22 20h.01"></path></svg>
                    </div>

                    <h1 class="state-main-title font-cormorant" style="color: #144D34;"><?php esc_html_e("It's a Match!", 'matchmaker'); ?></h1>
                    <p class="state-main-desc"><?php esc_html_e('You both accepted the match. Now you can connect directly outside Arab Zawaj.', 'matchmaker'); ?></p>

                    <div class="matched-profile-summary-box">
                        <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 150])); ?>" alt="">
                        <div>
                            <h2 class="font-cormorant"><?php echo esc_html($active_match['name']); ?></h2>
                            <div class="meta-sub"><?php echo esc_html($active_match['age']); ?> • <?php echo esc_html($active_match['location']); ?></div>
                            <span class="premium-tag-gold">★ <?php esc_html_e('Mutual Match Accepted', 'matchmaker'); ?></span>
                        </div>
                    </div>

                    <div style="text-align: left; font-family: 'Cormorant SC', serif; font-size: 18px; text-transform: uppercase; font-weight: 700; margin-bottom: 14px;">
                        <?php esc_html_e('Direct Contact Information', 'matchmaker'); ?>
                    </div>

                    <div class="contacts-grid">
                        <div class="contact-entry-field">
                            <div class="contact-icon-bubble">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </div>
                            <div class="contact-data-text">
                                <div class="label"><?php esc_html_e('Phone', 'matchmaker'); ?></div>
                                <div class="val"><?php echo esc_html($active_match['phone_number'] ?: '—'); ?></div>
                            </div>
                        </div>

                        <div class="contact-entry-field">
                            <div class="contact-icon-bubble">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path></svg>
                            </div>
                            <div class="contact-data-text">
                                <div class="label"><?php esc_html_e('Email', 'matchmaker'); ?></div>
                                <div class="val"><?php echo esc_html($active_match['email'] ?: '—'); ?></div>
                            </div>
                        </div>

                        <div class="contact-entry-field full">
                            <div class="contact-icon-bubble">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path></svg>
                            </div>
                            <div class="contact-data-text">
                                <div class="label"><?php esc_html_e('Social / Handle', 'matchmaker'); ?></div>
                                <div class="val"><?php echo esc_html($active_match['social_links'] ?: '—'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="notice-gray-box">
                        <strong>ⓘ <?php esc_html_e('Important Note:', 'matchmaker'); ?></strong> <?php esc_html_e('Arab Zawaj does not provide chat on the website. You can now contact each other directly using the information above. Please communicate respectfully.', 'matchmaker'); ?>
                    </div>

                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="MM_Portal.switchTab('profile')">
                        <?php esc_html_e('Back to Profile Dashboard →', 'matchmaker'); ?>
                    </button>
                </div>
            </div>

        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Fetch approved matches list for a user.
     */
    private function get_approved_matches(int $user_id): array
    {
        global $wpdb;
        $table      = $wpdb->prefix . 'matches';
        $pool_table = $wpdb->prefix . 'matchmaking_pool';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND status IN ('approved', 'matched')
                 ORDER BY created_at DESC",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        $out = [];
        foreach ((array) $rows as $row) {
            $is_user_one = ((int) $row['user_one_id'] === $user_id);
            $other_id    = $is_user_one ? (int) $row['user_two_id'] : (int) $row['user_one_id'];
            $my_response = $is_user_one ? $row['user_one_response'] : $row['user_two_response'];

            $other_user = get_userdata($other_id);
            $other_pool = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $other_id), ARRAY_A);

            $other_meta = [
                'phone_number'         => get_user_meta($other_id, 'phone_number', true),
                'user_social_links'    => get_user_meta($other_id, 'user_social_links', true),
                'user_marital_status'  => get_user_meta($other_id, 'user_marital_status', true),
                'user_education'       => get_user_meta($other_id, 'user_education', true),
                'user_photo1'          => get_user_meta($other_id, 'user_photo1', true),
                'pref_additional_info' => get_user_meta($other_id, 'pref_additional_info', true),
            ];

            // Expiration days remaining
            $days_remaining = 0;
            if ($my_response === 'pending') {
                $deadline       = strtotime($row['created_at'] . ' +7 days');
                $days_remaining = max(0, (int) ceil(($deadline - current_time('timestamp')) / DAY_IN_SECONDS));
            }

            $out[] = [
                'match_id'             => (int) $row['id'],
                'candidate_id'         => $other_id,
                'name'                 => $other_user ? $other_user->display_name : 'Candidate #' . $other_id,
                'email'                => $other_user ? $other_user->user_email : '',
                'phone_number'         => $other_meta['phone_number'],
                'social_links'         => $other_meta['user_social_links'],
                'marital_status'       => $other_meta['user_marital_status'],
                'education'            => $other_meta['user_education'],
                'photo'                => $other_meta['user_photo1'],
                'pref_additional_info' => $other_meta['pref_additional_info'],
                'age'                  => $this->calc_age($other_pool['birth_date'] ?? ''),
                'location'             => $other_pool['location'] ?? '—',
                'origin'               => $other_pool['origin'] ?? '—',
                'religion'             => $other_pool['religion'] ?? '—',
                'modesty'              => $other_pool['modesty'] ?? '—',
                'job'                  => $other_pool['job'] ?? '—',
                'languages'            => $other_pool['languages'] ?? '—',
                'height_formatted'     => $this->cm_to_feet((int) ($other_pool['height_cm'] ?? 0)),
                'status'               => $row['status'],
                'score'                => $row['score'],
                'contact_revealed'     => (int) $row['contact_revealed'],
                'my_response'          => $my_response,
                'days_remaining'       => $days_remaining,
            ];
        }

        return $out;
    }

    /**
     * AJAX handler for match accept/decline actions.
     */
    public function handle_ajax_match_response(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mm_portal_nonce')) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'matchmaker')]);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('You must be logged in.', 'matchmaker')]);
        }

        $match_id = isset($_POST['match_id']) ? (int) $_POST['match_id'] : 0;
        $action   = isset($_POST['response_action']) ? sanitize_key((string) $_POST['response_action']) : '';

        if ($match_id <= 0 || !in_array($action, ['accept', 'decline'], true)) {
            wp_send_json_error(['message' => __('Invalid action request.', 'matchmaker')]);
        }

        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';

        $match = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$matches_table} WHERE id = %d AND (user_one_id = %d OR user_two_id = %d)", $match_id, $user_id, $user_id),
            ARRAY_A
        );

        if (!$match) {
            wp_send_json_error(['message' => __('Match record not found.', 'matchmaker')]);
        }

        $is_user_one = ((int) $match['user_one_id'] === $user_id);
        $new_val     = ($action === 'accept') ? 'accepted' : 'rejected';

        $update_data = $is_user_one ? ['user_one_response' => $new_val] : ['user_two_response' => $new_val];

        $other_response = $is_user_one ? $match['user_two_response'] : $match['user_one_response'];

        if ($action === 'decline') {
            $update_data['status'] = 'rejected';
        } elseif ($action === 'accept' && $other_response === 'accepted') {
            $update_data['status']           = 'matched';
            $update_data['contact_revealed'] = 1;
        }

        $wpdb->update($matches_table, $update_data, ['id' => $match_id]);

        // Flush heartbeat transients
        Notification_Manager::instance()->flush_user_unread_transient($user_id);
        Notification_Manager::instance()->flush_user_unread_transient($is_user_one ? (int)$match['user_two_id'] : (int)$match['user_one_id']);

        $next_step = 3;
        if ($action === 'decline') {
            $next_step = 1;
        } elseif ($action === 'accept' && $other_response === 'accepted') {
            $next_step = 5;
        }

        wp_send_json_success([
            'message'   => ($action === 'accept') ? __('Match accepted successfully!', 'matchmaker') : __('Match declined.', 'matchmaker'),
            'next_step' => $next_step,
            'is_mutual' => ($action === 'accept' && $other_response === 'accepted'),
        ]);
    }

    private function calc_age(?string $birth_date): string
    {
        if (empty($birth_date) || $birth_date === '0000-00-00') {
            return '—';
        }
        try {
            return (string) (new \DateTime($birth_date))->diff(new \DateTime())->y;
        } catch (\Exception $e) {
            return '—';
        }
    }

    private function cm_to_feet(?int $cm): string
    {
        if (empty($cm) || $cm <= 0) {
            return '—';
        }
        $inches = $cm / 2.54;
        $feet   = floor($inches / 12);
        $rem    = (int) round($inches - ($feet * 12));
        if ($rem === 12) {
            $feet++;
            $rem = 0;
        }
        return "{$feet}'{$rem}\" ({$cm} cm)";
    }

    private function get_meta_block(int $user_id): array
    {
        $keys = [
            'user_citizenship', 'pref_citizenship', 'user_social_links', 'pref_social_links',
            'user_marital_status', 'pref_marital_status', 'user_children', 'pref_children',
            'user_prayer', 'pref_prayer', 'user_education', 'pref_education', 'user_income',
            'pref_income', 'pref_additional_info', 'user_photo1', 'user_photo2', 'user_photo3',
            'cycle_matches_count', 'mm_last_match_run',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = get_user_meta($user_id, $k, true);
        }
        return $out;
    }

    private function get_match_stats(int $user_id): array
    {
        global $wpdb;
        $table       = $wpdb->prefix . 'matches';
        $month_start = gmdate('Y-m-01 00:00:00');

        $received_this_term = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND created_at >= %s",
                $user_id,
                $user_id,
                $month_start
            )
        );

        $pending_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_one_id, user_two_id, user_one_response, user_two_response, created_at
                 FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND status IN ('pending_review', 'approved')
                 ORDER BY created_at ASC LIMIT 1",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        $days_remaining = 0;
        if ($pending_row) {
            $is_u1 = ((int) $pending_row['user_one_id'] === $user_id);
            $my_resp = $is_u1 ? $pending_row['user_one_response'] : $pending_row['user_two_response'];
            if ($my_resp === 'pending') {
                $deadline = strtotime($pending_row['created_at'] . ' +7 days');
                $days_remaining = max(0, (int) ceil(($deadline - current_time('timestamp')) / DAY_IN_SECONDS));
            }
        }

        $cycle_count = get_user_meta($user_id, 'cycle_matches_count', true);
        if ($cycle_count === '' || $cycle_count === false) {
            $cycle_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table}
                     WHERE (user_one_id = %d OR user_two_id = %d) AND status = 'matched'",
                    $user_id,
                    $user_id
                )
            );
        }

        return [
            'received_this_term' => $received_this_term,
            'days_remaining'     => $days_remaining,
            'total_accepted'     => (int) $cycle_count,
        ];
    }

    /**
     * AJAX Handler: Dynamically reload active tab content (Profile or Matches).
     */
    public function handle_ajax_reload_tab(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mm_portal_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => 'User not logged in.']);
        }

        $tab = isset($_POST['tab']) ? sanitize_key((string) $_POST['tab']) : 'profile';

        $user_obj = get_userdata($user_id);
        $meta     = $this->get_meta_block($user_id);
        $stats    = $this->get_match_stats($user_id);
        $matches  = $this->get_approved_matches($user_id);

        global $wpdb;
        $pool_table = $wpdb->prefix . 'matchmaking_pool';
        $pool       = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $user_id), ARRAY_A) ?: [];

        $html = ($tab === 'matches')
            ? $this->render_matches_tab_html($user_id, $matches)
            : $this->render_profile_tab_html($user_id, $user_obj, $pool, $meta, $stats);

        wp_send_json_success(['html' => $html, 'tab' => $tab]);
    }
}
