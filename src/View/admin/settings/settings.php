<?php
/**
 * View: Admin Matchmaker Settings & Configuration (5 Structured Tabs)
 *
 * Available variables:
 *   @var string               $environment_mode
 *   @var bool                 $is_test_mode
 *   @var array<int, string>   $current_mapping
 *   @var array<int, object>   $pmpro_levels
 *   @var array<int, string>   $pmpro_level_tags
 *   @var int                  $services_group_id
 *   @var int                  $featured_profile_level_id
 *   @var string               $featured_profile_default_image
 *   @var int                  $max_matches

 *   @var int                  $expiry_days
 *   @var int                  $recurrence
 *   @var int                  $max_candidates
 *   @var int                  $page_dashboard
 *   @var int                  $page_questionnaire
 *   @var int                  $page_account
 *   @var int                  $page_checkout
 *   @var int                  $page_events
 *   @var string               $free_form_id
 *   @var string               $events_cpt_slug
 *   @var int                  $events_template_id
 *   @var int                  $events_per_page
 *   @var string               $subject
 *   @var string               $template
 *   @var string               $verify_from_email
 *   @var string               $verify_from_name
 *   @var string               $verify_subject
 *   @var string               $verify_template
 *   @var string               $verify_update_subject
 *   @var string               $verify_update_template
 *   @var int                  $verify_expiry_hours
 *   @var int                  $verify_cooldown_seconds
 *   @var string               $admin_service_recipient
 *   @var string               $admin_service_subject
 *   @var string               $admin_service_template
 *   @var string               $admin_featured_recipient
 *   @var string               $admin_featured_subject
 *   @var string               $admin_featured_template
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
?>
<div class="wrap mm-admin-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
        <h1 style="margin:0;"><?php esc_html_e('Matchmaker Settings & Configuration', 'matchmaker'); ?></h1>
        <div>
            <?php if ($is_test_mode) : ?>
                <span style="background:#fef3c7; color:#92400e; border:1px solid #f59e0b; font-weight:700; padding:6px 14px; border-radius:20px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    🧪 <?php esc_html_e('Test Mode Active', 'matchmaker'); ?>
                </span>
            <?php else : ?>
                <span style="background:#ecfdf5; color:#065f46; border:1px solid #10b981; font-weight:700; padding:6px 14px; border-radius:20px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    🛡️ <?php esc_html_e('Live / Production Mode', 'matchmaker'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php settings_errors('mm_admin_notices'); ?>

    <!-- 5-Tab Navigation Bar -->
    <nav class="nav-tab-wrapper mm-settings-tab-wrapper" style="margin-bottom: 24px;">
        <a href="#tab-general" class="nav-tab nav-tab-active" data-tab="general">
            ⚙️ <?php esc_html_e('General & Mode', 'matchmaker'); ?>
        </a>
        <a href="#tab-membership" class="nav-tab" data-tab="membership">
            👥 <?php esc_html_e('Membership & Services', 'matchmaker'); ?>
        </a>
        <a href="#tab-matching" class="nav-tab" data-tab="matching">
            🎯 <?php esc_html_e('Quotas & Matching', 'matchmaker'); ?>
        </a>
        <a href="#tab-emails" class="nav-tab" data-tab="emails">
            ✉️ <?php esc_html_e('Email Templates', 'matchmaker'); ?>
        </a>
        <a href="#tab-bulk-email" class="nav-tab" data-tab="bulk-email">
            📨 <?php esc_html_e('Bulk Email', 'matchmaker'); ?>
        </a>
        <a href="#tab-shortcodes" class="nav-tab" data-tab="shortcodes">
            📖 <?php esc_html_e('Shortcodes & System', 'matchmaker'); ?>
        </a>
    </nav>

    <form method="post" action="" id="mm-settings-form">
        <?php wp_nonce_field('mm_save_settings_nonce'); ?>
        
        <!-- ==========================================
             TAB 1: GENERAL & SYSTEM MODE
             ========================================== -->
        <div class="mm-settings-tab-panel active" id="mm-panel-general">
            
            <?php if ($is_test_mode) : ?>
                <!-- Test Mode Reset Tool Card (Visible ONLY in Test Mode) -->
                <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff1f2; border:2px solid #f43f5e; border-radius:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px;">
                        <div>
                            <h2 style="margin-top:0; color:#9f1239; display:flex; align-items:center; gap:8px;">
                                ⚠️ <?php esc_html_e('Test Mode: Reset Matchmaking Data', 'matchmaker'); ?>
                            </h2>
                            <p style="margin:6px 0 0; color:#881337; font-size:13px; line-height:1.5; max-width:750px;">
                                <?php esc_html_e('This tool purges all match records (wp_matches), member in-app notifications (wp_matchmaker_notifications), and activity/email logs (wp_matchmaker_logs), and resets user cycle match counts. All candidate pool profiles (wp_matchmaking_pool) and WordPress user accounts will be strictly preserved.', 'matchmaker'); ?>
                            </p>
                        </div>
                        <div>
                            <button type="button" class="button button-secondary" onclick="if(confirm('<?php echo esc_js(__('Are you ABSOLUTELY sure you want to reset all test matches, notifications, and logs? Candidate pool profiles will be preserved.', 'matchmaker')); ?>')) { document.getElementById('mm_reset_test_data_form').submit(); }" style="background:#e11d48; color:#fff; border-color:#be123c; font-weight:700; padding:4px 16px; height:auto; line-height:28px;">
                                🗑️ <?php esc_html_e('Reset Test Matchmaking Data', 'matchmaker'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('Environment System Mode', 'matchmaker'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_environment_mode"><?php esc_html_e('Active System Mode', 'matchmaker'); ?></label></th>
                        <td>
                            <select name="mm_environment_mode" id="mm_environment_mode" style="min-width:260px; font-weight:600;">
                                <option value="live" <?php selected($environment_mode, 'live'); ?>><?php esc_html_e('🛡️ Live / Production Mode', 'matchmaker'); ?></option>
                                <option value="test" <?php selected($environment_mode, 'test'); ?>><?php esc_html_e('🧪 Test Mode (Enables Data Reset Tool)', 'matchmaker'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('When set to Test Mode, the "Reset Test Matchmaking Data" tool is enabled, and email delivery is simulated if mail fails.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('Page Routing & Dynamic Resolvers', 'matchmaker'); ?>
                </h2>
                <p class="description"><?php esc_html_e('Select target WordPress pages for member routing and shortcode placement.', 'matchmaker'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_page_dashboard_id"><?php esc_html_e('Member Dashboard Page', 'matchmaker'); ?></label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages([
                                'name'              => 'mm_page_dashboard_id',
                                'id'                => 'mm_page_dashboard_id',
                                'selected'          => $page_dashboard,
                                'show_option_none'  => __('— Default (/dashboard/) —', 'matchmaker'),
                                'option_none_value' => '0',
                            ]); 
                            ?>
                            <p class="description"><?php esc_html_e('Page containing [matchmaker_member_portal] shortcode.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_page_questionnaire_id"><?php esc_html_e('Questionnaire Form Page', 'matchmaker'); ?></label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages([
                                'name'              => 'mm_page_questionnaire_id',
                                'id'                => 'mm_page_questionnaire_id',
                                'selected'          => $page_questionnaire,
                                'show_option_none'  => __('— Default (/personal-matchmaking-questionnaire/) —', 'matchmaker'),
                                'option_none_value' => '0',
                            ]); 
                            ?>
                            <p class="description"><?php esc_html_e('Page containing [matchmaking_form] shortcode.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_page_account_id"><?php esc_html_e('Membership Account Page', 'matchmaker'); ?></label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages([
                                'name'              => 'mm_page_account_id',
                                'id'                => 'mm_page_account_id',
                                'selected'          => $page_account,
                                'show_option_none'  => __('— Default (/membership-account/) —', 'matchmaker'),
                                'option_none_value' => '0',
                            ]); 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_page_checkout_id"><?php esc_html_e('Membership Checkout Page', 'matchmaker'); ?></label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages([
                                'name'              => 'mm_page_checkout_id',
                                'id'                => 'mm_page_checkout_id',
                                'selected'          => $page_checkout,
                                'show_option_none'  => __('— Default (/membership-checkout/) —', 'matchmaker'),
                                'option_none_value' => '0',
                            ]); 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_page_events_id"><?php esc_html_e('Events Page', 'matchmaker'); ?></label></th>
                        <td>
                            <?php 
                            wp_dropdown_pages([
                                'name'              => 'mm_page_events_id',
                                'id'                => 'mm_page_events_id',
                                'selected'          => $page_events,
                                'show_option_none'  => __('— Default (/events-2/) —', 'matchmaker'),
                                'option_none_value' => '0',
                            ]); 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_free_reg_form_id"><?php esc_html_e('Elementor Free Reg Form ID(s)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_free_reg_form_id" id="mm_free_reg_form_id" value="<?php echo esc_attr($free_form_id); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Elementor Pro Form Widget ID(s) used for decoupled free registration (e.g. 2784843). Multiple IDs can be comma-separated.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ==========================================
             TAB 2: MEMBERSHIP PLANS & SERVICES
             ========================================== -->
        <div class="mm-settings-tab-panel" id="mm-panel-membership" style="display:none;">
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('PMPro Membership Plan Connector', 'matchmaker'); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e('Map each Paid Memberships Pro base level to a Matchmaker tier, and configure custom badge tags to be displayed across the frontend dashboard and admin tables.', 'matchmaker'); ?>
                </p>

                <?php if (!empty($pmpro_levels)) : ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
                        <thead>
                            <tr>
                                <th style="width:60px;"><?php esc_html_e('ID', 'matchmaker'); ?></th>
                                <th><?php esc_html_e('PMPro Level Name', 'matchmaker'); ?></th>
                                <th><?php esc_html_e('Description / Price', 'matchmaker'); ?></th>
                                <th style="width:220px;"><?php esc_html_e('Base Membership Tier', 'matchmaker'); ?></th>
                                <th style="width:200px;"><?php esc_html_e('Custom Badge / Tag Name', 'matchmaker'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pmpro_levels as $lvl) : 
                                $lid      = (int) $lvl->id;
                                $assigned = $current_mapping[$lid] ?? 'free';
                                $tag_val  = $pmpro_level_tags[$lid] ?? '';
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $lid; ?></strong></td>
                                    <td><strong><?php echo esc_html($lvl->name); ?></strong></td>
                                    <td>
                                        <small style="color:#666;">
                                            <?php echo !empty($lvl->description) ? wp_trim_words(strip_tags($lvl->description), 10) : '—'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <select name="mm_pmpro_levels[<?php echo $lid; ?>]" style="width:100%;">
                                            <option value="monthly" <?php selected($assigned, 'monthly'); ?>>
                                                <?php esc_html_e('Monthly (Group 2 Subscription)', 'matchmaker'); ?>
                                            </option>
                                            <option value="event" <?php selected($assigned, 'event'); ?>>
                                                <?php esc_html_e('Event (Group 2 Subscription)', 'matchmaker'); ?>
                                            </option>
                                            <option value="free" <?php selected($assigned, 'free'); ?>>
                                                <?php esc_html_e('Free (Group 1 Plan)', 'matchmaker'); ?>
                                            </option>
                                            <option value="service" <?php selected($assigned, 'service'); ?>>
                                                <?php esc_html_e('Service / Add-on (Group 3)', 'matchmaker'); ?>
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="mm_pmpro_level_tags[<?php echo $lid; ?>]" value="<?php echo esc_attr($tag_val); ?>" placeholder="<?php esc_attr_e('e.g. VIP Member', 'matchmaker'); ?>" style="width:100%;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="color:#c02b0a; background:#fff2f0; padding:10px 14px; border-left:4px solid #c02b0a; border-radius:4px; margin-top:12px;">
                        <?php esc_html_e('Paid Memberships Pro is not active or has no registered levels. Default level mapping is being used.', 'matchmaker'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('PMPro Services Group (Group 3 Add-on Services)', 'matchmaker'); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e('Configure the PMPro Membership Group ID containing specialized services (e.g. 1-on-1 VIP Matchmaking). All levels created in this PMPro group will automatically appear inside the Member Portal "Services" tab.', 'matchmaker'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_services_group_id"><?php esc_html_e('PMPro Services Group ID', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="999" name="mm_services_group_id" id="mm_services_group_id" value="<?php echo (int) $services_group_id; ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Default: 3. Any membership levels assigned to this PMPro group are treated as add-on services that require an active basic membership.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_featured_profile_level_id"><?php esc_html_e('Featured Profile Service Level', 'matchmaker'); ?></label></th>
                        <td>
                            <select name="mm_featured_profile_level_id" id="mm_featured_profile_level_id" class="regular-text">
                                <option value="0"><?php esc_html_e('— Auto-Detect from Group 3 (Level named/tagged "Featured") —', 'matchmaker'); ?></option>
                                <?php if (!empty($pmpro_levels)) : ?>
                                    <?php foreach ($pmpro_levels as $lvl) : ?>
                                        <option value="<?php echo (int) $lvl->id; ?>" <?php selected((int) $featured_profile_level_id, (int) $lvl->id); ?>>
                                            <?php echo esc_html($lvl->name); ?> (ID: <?php echo (int) $lvl->id; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select which PMPro level corresponds to the "Featured Profile" add-on service. When purchased, members unlock the Featured Profile toggle in their Member Portal.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_featured_profile_default_image"><?php esc_html_e('Featured Profile Default Anonymous Image', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="url" name="mm_featured_profile_default_image" id="mm_featured_profile_default_image" value="<?php echo esc_attr($featured_profile_default_image); ?>" class="regular-text" placeholder="https://example.com/wp-content/uploads/silhouette.png">
                            <p class="description"><?php esc_html_e('Optional. Image URL to display as the avatar on public featured cards when a member chooses Anonymous visibility. Leave blank to use the built-in SVG privacy silhouette.', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                </table>

            </div>

            <!-- Bulk User Type & Free Membership Sync Tool Card -->
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; display:flex; align-items:center; gap:8px;">
                    🔄 <?php esc_html_e('Bulk User Type & Free Membership Synchronization', 'matchmaker'); ?>
                </h2>
                <p class="description" style="margin-bottom:14px;">
                    <?php esc_html_e('Synchronizes all users in the system to ensure their user_type is strictly free, monthly, or event. If a member does not have a base membership but holds an active add-on service, this tool automatically assigns them the Free membership tier and preserves their active service status.', 'matchmaker'); ?>
                </p>

                <?php 
                $special_sync_url = \Matchmaker\Core\PMProSync::instance()->get_special_sync_url(); 
                ?>

                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Special Trigger Link', 'matchmaker'); ?></label></th>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px; max-width:700px;">
                                <input type="text" readonly value="<?php echo esc_url($special_sync_url); ?>" id="mm_special_sync_url_input" class="regular-text" style="background:#f8fafc; font-family:monospace; font-size:12px; width:100%;">
                                <button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(document.getElementById('mm_special_sync_url_input').value); alert('<?php echo esc_js(__('Special synchronization URL copied to clipboard!', 'matchmaker')); ?>');">
                                    📋 <?php esc_html_e('Copy Link', 'matchmaker'); ?>
                                </button>
                            </div>
                            <p class="description" style="margin-top:6px;">
                                <?php esc_html_e('This special secured link can be visited directly in any browser, executed via external cron/webhooks, or integrated with cURL/server scripts. Append &format=json for API responses.', 'matchmaker'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Instant Action', 'matchmaker'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($special_sync_url); ?>" target="_blank" class="button button-secondary" style="font-weight:600; color:#b55d2c; border-color:#cc723f; background:#fff8f5; display:inline-flex; align-items:center; gap:6px;">
                                ⚡ <?php esc_html_e('Run Synchronization Now (Opens in New Tab)', 'matchmaker'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ==========================================
             TAB 3: QUOTAS & MATCHING ENGINE
             ========================================== -->
        <div class="mm-settings-tab-panel" id="mm-panel-matching" style="display:none;">
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('Matchmaking Quotas & Expiration Rules', 'matchmaker'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_max_cycle_matches"><?php esc_html_e('Max Matches per Cycle', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="100" name="mm_max_cycle_matches" id="mm_max_cycle_matches" value="<?php echo esc_attr((string)$max_matches); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum number of approved matches allowed per paid member per billing cycle (default: 10).', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_match_expiry_days"><?php esc_html_e('Match Expiry Window (Days)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="60" name="mm_match_expiry_days" id="mm_match_expiry_days" value="<?php echo esc_attr((string)$expiry_days); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Number of days a member has to accept or decline an approved match before it expires automatically (default: 7).', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_auto_match_recurrence_days"><?php esc_html_e('Idle User Auto-Match Recurrence (Days)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="60" name="mm_auto_match_recurrence_days" id="mm_auto_match_recurrence_days" value="<?php echo esc_attr((string)$recurrence); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Number of days between automatic matching runs for idle subscribers (default: 7).', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_max_candidates_per_run"><?php esc_html_e('Max Candidates per Matching Run', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="50" name="mm_max_candidates_per_run" id="mm_max_candidates_per_run" value="<?php echo esc_attr((string)$max_candidates); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Top N scored candidates to insert into pending review queue per matching run (default: 10).', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Quota Reconciliation & Counter Sync Tool -->
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; display:flex; align-items:center; gap:8px;">
                    🔄 <?php esc_html_e('Quota Counter Reconciliation & Synchronization', 'matchmaker'); ?>
                </h2>
                <p style="color:#555; font-size:13px; line-height:1.5;">
                    <?php esc_html_e('Recalculates and synchronizes the active monthly cycle match counter (cycle_matches_count) for all monthly tier members based on their actual approved, matched, and active matches in the current cycle month. Free and Event tier members remain uncounted and unconstrained.', 'matchmaker'); ?>
                </p>
                <div style="margin:12px 0 16px; padding:12px 16px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; font-size:13px; color:#0369a1; line-height:1.5;">
                    ℹ️ <strong><?php esc_html_e('How Quota is Counted:', 'matchmaker'); ?></strong> <?php esc_html_e('Quota is incremented whenever an admin approves a match for a monthly user. When members accept and transition to a mutual match, the quota remains counted for that monthly cycle. Running this tool heals and recalculates counters from historical match records.', 'matchmaker'); ?>
                </div>
                <div>
                    <button type="button" class="button button-secondary" onclick="if(confirm('<?php echo esc_js(__('Are you sure you want to recalculate quota counters for all monthly members from active match records?', 'matchmaker')); ?>')) { document.getElementById('mm_recalculate_quotas_form').submit(); }" style="font-weight:600; padding:4px 16px; height:auto; line-height:28px;">
                        🔄 <?php esc_html_e('Recalculate & Sync All Member Quotas', 'matchmaker'); ?>
                    </button>
                </div>
            </div>

            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('Member Portal Events Configuration', 'matchmaker'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_events_cpt_slug"><?php esc_html_e('Event CPT Slug', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_events_cpt_slug" id="mm_events_cpt_slug" value="<?php echo esc_attr($events_cpt_slug ?? 'event'); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The post type slug for Events (default: "event").', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_events_template_id"><?php esc_html_e('Elementor Loop Template ID', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" name="mm_events_template_id" id="mm_events_template_id" value="<?php echo (int) ($events_template_id ?? 395); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('The Elementor Loop Item Template ID used to render event cards (default: 395).', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_events_per_page"><?php esc_html_e('Events Per Page', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" name="mm_events_per_page" id="mm_events_per_page" value="<?php echo (int) ($events_per_page ?? 6); ?>" min="1" max="50" class="small-text">
                            <p class="description"><?php esc_html_e('Number of events displayed per page with in-canvas AJAX pagination (default: 6).', 'matchmaker'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ==========================================
             TAB 4: EMAIL TEMPLATES
             ========================================== -->
        <div class="mm-settings-tab-panel" id="mm-panel-emails" style="display:none;">
            
            <!-- 1. Match Approval Email -->
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('1. Match Approval Notification Template', 'matchmaker'); ?>
                </h2>
                <p class="description"><?php esc_html_e('Sent to both matched members when an admin approves a match.', 'matchmaker'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_email_approval_subject"><?php esc_html_e('Email Subject', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_approval_subject" id="mm_email_approval_subject" value="<?php echo esc_attr($subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_approval_template"><?php esc_html_e('Email Body Template', 'matchmaker'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($template, 'mm_email_approval_template', [
                                'textarea_name' => 'mm_email_approval_template',
                                'textarea_rows' => 8,
                                'media_buttons' => true,
                                'teeny'         => false,
                            ]);
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 2. Admin Service Purchase Notification -->
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('2. Admin Notification: Service Package Purchased', 'matchmaker'); ?>
                </h2>
                <p class="description"><?php esc_html_e('Sent to the site admin whenever a member purchases an add-on service level (Group 3).', 'matchmaker'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_email_admin_service_recipient"><?php esc_html_e('Admin Recipient Email', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="email" name="mm_email_admin_service_recipient" id="mm_email_admin_service_recipient" value="<?php echo esc_attr($admin_service_recipient); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Leave empty to default to site admin email (', 'matchmaker') . esc_html(get_option('admin_email')) . ').'; ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_admin_service_purchase_subject"><?php esc_html_e('Email Subject', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_admin_service_purchase_subject" id="mm_email_admin_service_purchase_subject" value="<?php echo esc_attr($admin_service_subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_admin_service_purchase_template"><?php esc_html_e('Email Body Template', 'matchmaker'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($admin_service_template, 'mm_email_admin_service_purchase_template', [
                                'textarea_name' => 'mm_email_admin_service_purchase_template',
                                'textarea_rows' => 8,
                                'media_buttons' => true,
                                'teeny'         => false,
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Available Placeholders', 'matchmaker'); ?></th>
                        <td>
                            <code>{user_name}</code>, <code>{user_email}</code>, <code>{user_id}</code>, <code>{service_name}</code>, <code>{service_price}</code>, <code>{purchase_date}</code>, <code>{admin_profile_url}</code>, <code>{site_name}</code>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 3. User Email Verification Code -->
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('3. Email Verification OTP & Delivery Headers', 'matchmaker'); ?>
                </h2>
                <p class="description"><?php esc_html_e('Configure the sender headers, 6-digit OTP template, expiration, and cooldown for user email verification.', 'matchmaker'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_email_verify_from_email"><?php esc_html_e('Sender Email (From Email)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="email" name="mm_email_verify_from_email" id="mm_email_verify_from_email" value="<?php echo esc_attr($verify_from_email); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_from_name"><?php esc_html_e('Sender Name (From Name)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_verify_from_name" id="mm_email_verify_from_name" value="<?php echo esc_attr($verify_from_name); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name') ?: 'Arab Zawaj Matrimony'); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_subject"><?php esc_html_e('Verification Subject', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_verify_subject" id="mm_email_verify_subject" value="<?php echo esc_attr($verify_subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_template"><?php esc_html_e('Verification Body', 'matchmaker'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($verify_template, 'mm_email_verify_template', [
                                'textarea_name' => 'mm_email_verify_template',
                                'textarea_rows' => 8,
                                'media_buttons' => true,
                                'teeny'         => false,
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_update_subject"><?php esc_html_e('Email Change Subject', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_verify_update_subject" id="mm_email_verify_update_subject" value="<?php echo esc_attr($verify_update_subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_update_template"><?php esc_html_e('Email Change Body', 'matchmaker'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($verify_update_template, 'mm_email_verify_update_template', [
                                'textarea_name' => 'mm_email_verify_update_template',
                                'textarea_rows' => 8,
                                'media_buttons' => true,
                                'teeny'         => false,
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_expiry_hours"><?php esc_html_e('Code Expiration (Hours)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" name="mm_email_verify_expiry_hours" id="mm_email_verify_expiry_hours" value="<?php echo (int) $verify_expiry_hours; ?>" min="1" max="168" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_verify_cooldown_seconds"><?php esc_html_e('Resend Cooldown (Seconds)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="number" name="mm_email_verify_cooldown_seconds" id="mm_email_verify_cooldown_seconds" value="<?php echo (int) $verify_cooldown_seconds; ?>" min="5" max="600" class="small-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 4. Admin Featured Match Application Alert -->
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('4. Admin Featured Profile Match Alert', 'matchmaker'); ?>
                </h2>
                <p class="description"><?php esc_html_e('Sent immediately to designated administrator(s) whenever a logged-in member applies to match with a candidate on the Public Featured Profiles showcase.', 'matchmaker'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mm_email_admin_featured_match_recipient"><?php esc_html_e('Admin Recipient Email(s)', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_admin_featured_match_recipient" id="mm_email_admin_featured_match_recipient" value="<?php echo esc_attr($admin_featured_recipient); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Comma-separated email addresses. Leave blank to default to the primary site admin email (', 'matchmaker') . esc_html(get_option('admin_email')) . ').'; ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_admin_featured_match_subject"><?php esc_html_e('Email Subject', 'matchmaker'); ?></label></th>
                        <td>
                            <input type="text" name="mm_email_admin_featured_match_subject" id="mm_email_admin_featured_match_subject" value="<?php echo esc_attr($admin_featured_subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mm_email_admin_featured_match_template"><?php esc_html_e('Email Body Template', 'matchmaker'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($admin_featured_template, 'mm_email_admin_featured_match_template', [
                                'textarea_name' => 'mm_email_admin_featured_match_template',
                                'textarea_rows' => 8,
                                'media_buttons' => true,
                                'teeny'         => false,
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Available Placeholders', 'matchmaker'); ?></th>
                        <td>
                            <code>{applicant_name}</code>, <code>{applicant_id}</code>, <code>{applicant_email}</code>, <code>{candidate_name}</code>, <code>{candidate_id}</code>, <code>{match_id}</code>, <code>{score}</code>, <code>{admin_match_url}</code>, <code>{site_name}</code>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ==========================================
             TAB 5: BULK EMAIL CAMPAIGN
             ========================================== -->
        <div class="mm-settings-tab-panel" id="mm-panel-bulk-email" style="display:none;">
            <?php require __DIR__ . '/tab-bulk-email.php'; ?>
        </div>

        <!-- ==========================================
             TAB 6: SHORTCODES & SYSTEM REFERENCE
             ========================================== -->
        <div class="mm-settings-tab-panel" id="mm-panel-shortcodes" style="display:none;">

            <!-- Master Overview Card -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #f1f5f9; padding-bottom:14px; margin-bottom:16px;">
                    <div>
                        <h2 style="margin:0 0 6px 0; font-size:19px; color:#1e293b;">
                            📖 <?php esc_html_e('Matchmaker Shortcodes Reference Guide', 'matchmaker'); ?>
                        </h2>
                        <p style="color:#64748b; font-size:13.5px; margin:0; line-height:1.5;">
                            <?php esc_html_e('Complete documentation for all 17 registered shortcodes. Embed these directly into WordPress pages, Elementor templates, custom themes, or landing pages.', 'matchmaker'); ?>
                        </p>
                    </div>
                    <span class="badge" style="background:#fff7ed; color:#CC723F; border:1px solid #fed7aa; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px;">
                        v<?php echo defined('MM_VERSION') ? esc_html(MM_VERSION) : '2.14.0'; ?>
                    </span>
                </div>

                <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:280px;"><?php esc_html_e('Shortcode', 'matchmaker'); ?></th>
                            <th style="width:140px;"><?php esc_html_e('Category', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description', 'matchmaker'); ?></th>
                            <th style="width:220px;"><?php esc_html_e('Target Location', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong style="font-size:13.5px;"><code>[matchmaker_member_portal]</code></strong><br>
                                <small style="color:#64748b;"><?php esc_html_e('Alias:', 'matchmaker'); ?> <code>[az_profile]</code></small>
                            </td>
                            <td><span class="badge" style="background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Member Portal', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Full member portal with Profile overview, 5-state Matches review flow, Services, and Events tabs.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Member Dashboard Page', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong style="font-size:13.5px;"><code>[matchmaker_services_grid]</code></strong><br>
                                <small style="color:#64748b;"><?php esc_html_e('Aliases:', 'matchmaker'); ?> <code>[az_services_grid]</code>, <code>[matchmaker_services]</code></small>
                            </td>
                            <td><span class="badge" style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Services Grid', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Embeds standalone add-on services cards on any page with automatic base membership purchasing rules.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Pricing / Services Landing Pages', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[matchmaking_form]</code></strong></td>
                            <td><span class="badge" style="background:#f3e8ff; color:#6b21a8; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Questionnaire', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Interactive 37-field matchmaking questionnaire wizard with photo uploads & location cascade.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Questionnaire Page', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[matchmaking_field]</code></strong></td>
                            <td><span class="badge" style="background:#f3e8ff; color:#6b21a8; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Questionnaire', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Renders a single standalone profile questionnaire input field with styling and options.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Custom Landing Pages / Elementor', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong style="font-size:13.5px;"><code>[event_rsvp_button]</code></strong><br>
                                <small style="color:#64748b;"><?php esc_html_e('Aliases:', 'matchmaker'); ?> <code>[az_event_rsvp_button]</code>, <code>[rsvp_button]</code></small>
                            </td>
                            <td><span class="badge" style="background:#ecfdf5; color:#065f46; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Events', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Dynamic Event RSVP button with membership tier gating and modal dialog.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Single Event Pages / Event Grids', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong style="font-size:13.5px;"><code>[event_reg_form]</code></strong><br>
                                <small style="color:#64748b;"><?php esc_html_e('Aliases:', 'matchmaker'); ?> <code>[az_event_reg_form]</code>, <code>[event_registration_form]</code></small>
                            </td>
                            <td><span class="badge" style="background:#ecfdf5; color:#065f46; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Events', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Embeds the dedicated event registration form with secure token resolution.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Event Registration Page', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[featured_profile_details]</code></strong></td>
                            <td><span class="badge" style="background:#fef2f2; color:#991b1b; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Featured Profiles', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Detailed card for a featured candidate (respects anonymous vs. public privacy mode).', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Featured Profile Single / Loop Grid', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[featured_profile_apply_button]</code></strong></td>
                            <td><span class="badge" style="background:#fef2f2; color:#991b1b; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Featured Profiles', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Interactive Apply for a Match CTA button with member login gating, quota validation, and email alerts.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Featured Profile Single / Loop Grid', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[featured_profile_field]</code></strong></td>
                            <td><span class="badge" style="background:#fef2f2; color:#991b1b; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Featured Profiles', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Outputs individual featured profile field (name, photo, age, location, country, job, etc.).', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Elementor Custom Loop Grid items', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[az_email_verification]</code></strong></td>
                            <td><span class="badge" style="background:#e0e7ff; color:#3730a3; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Verification', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('6-digit email confirmation OTP code submission and resend cooldown form.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Email Verification Page', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><strong style="font-size:13.5px;"><code>[logout_url]</code></strong></td>
                            <td><span class="badge" style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Utility', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Outputs a secure WordPress logout URL with nonces and optional redirection.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Navigation Menus / Custom Links', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong style="font-size:13.5px;"><code>[matchmaker_profile_photo]</code></strong><br>
                                <small style="color:#64748b;"><?php esc_html_e('Aliases:', 'matchmaker'); ?> <code>[az_profile_photo]</code>, <code>[matchmaker_photo]</code></small>
                            </td>
                            <td><span class="badge" style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;"><?php esc_html_e('Photos & Media', 'matchmaker'); ?></span></td>
                            <td><?php esc_html_e('Outputs member profile photo with complete control over width, height, size, radius, and lightbox.', 'matchmaker'); ?></td>
                            <td><?php esc_html_e('Any Page / Custom Header / Bio', 'matchmaker'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Detailed Section 1: Member Dashboard Portal -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>🖥️</span> <?php esc_html_e('1. Member Dashboard Portal', 'matchmaker'); ?>
                    <code>[matchmaker_member_portal]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Renders the complete unified member matchmaking dashboard. It features a modern 2-column layout with 4 dynamic tabs:', 'matchmaker'); ?>
                </p>
                <ul style="list-style:disc; margin-left:24px; color:#475569; font-size:13.5px; line-height:1.7;">
                    <li><strong><?php esc_html_e('Profile Tab:', 'matchmaker'); ?></strong> <?php esc_html_e('Displays member personal information, criteria tags, profile photos, membership tier badge (Free, Monthly Gold, Event Pass), and a quick CTA to edit the questionnaire.', 'matchmaker'); ?></li>
                    <li><strong><?php esc_html_e('Matches Tab (5-Step Interactive Flow):', 'matchmaker'); ?></strong>
                        <ol style="margin-left:20px; list-style:decimal; margin-top:4px;">
                            <li><em><?php esc_html_e('Step 1 (Discovery):', 'matchmaker'); ?></em> <?php esc_html_e('Curated candidate overview card with blurred photo and initial score.', 'matchmaker'); ?></li>
                            <li><em><?php esc_html_e('Step 2 (Profile Review):', 'matchmaker'); ?></em> <?php esc_html_e('Full candidate bio, photo lightbox (up to 3 photos), compatibility breakdown, and Accept/Decline action buttons.', 'matchmaker'); ?></li>
                            <li><em><?php esc_html_e('Step 3 (Status / Waiting Room):', 'matchmaker'); ?></em> <?php esc_html_e('Dynamic response feedback: Pending Your Review, Candidate Accepted (Awaiting You), Waiting on Candidate Response, or Declined states.', 'matchmaker'); ?></li>
                            <li><em><?php esc_html_e('Step 4 (Decision Processing):', 'matchmaker'); ?></em> <?php esc_html_e('Automated transition state during response submission.', 'matchmaker'); ?></li>
                            <li><em><?php esc_html_e('Step 5 (Mutual Match Celebration):', 'matchmaker'); ?></em> <?php esc_html_e('Heart celebration bubble, direct phone/email/social contact details reveal, and pause subscription helper.', 'matchmaker'); ?></li>
                            <li><em><?php esc_html_e('Match History Card:', 'matchmaker'); ?></em> <?php esc_html_e('Lists all previously reviewed and mutual matches with instant 1-click action buttons (Review Match, View Status, View Match) to reopen any match directly.', 'matchmaker'); ?></li>
                        </ol>
                    </li>
                    <li><strong><?php esc_html_e('Services Tab:', 'matchmaker'); ?></strong> <?php esc_html_e('Displays active and available add-on services (1-on-1 VIP consultations, Social Media Spotlight, etc.) with repeat purchase support.', 'matchmaker'); ?></li>
                    <li><strong><?php esc_html_e('Events Tab:', 'matchmaker'); ?></strong> <?php esc_html_e('Displays upcoming single matrimony events with RSVP actions (automatically hidden for Free members).', 'matchmaker'); ?></li>
                </ul>
                <div style="background:#f8fafc; border-left:4px solid #0284c7; padding:12px 16px; border-radius:4px; margin-top:14px; font-size:13px; color:#334155;">
                    💡 <strong><?php esc_html_e('Automatic Real-time Heartbeat Polling:', 'matchmaker'); ?></strong> <?php esc_html_e('This shortcode automatically enqueues real-time 15-second WordPress Heartbeat API listeners that update unread match notification badges and show alert toasts without requiring manual page reloads.', 'matchmaker'); ?>
                </div>
            </div>

            <!-- Detailed Section 2: Standalone Add-on Services Grid -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>🛍️</span> <?php esc_html_e('2. Standalone Add-on Services Grid', 'matchmaker'); ?>
                    <code>[matchmaker_services_grid]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Displays the responsive card grid of all active PMPro add-on services on public frontend pages, pricing pages, marketing landing pages, or Elementor templates outside the member dashboard.', 'matchmaker'); ?>
                </p>

                <h4 style="margin:16px 0 8px 0; font-size:14px; color:#1e293b;"><?php esc_html_e('Shortcode Attributes & Parameters', 'matchmaker'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e('Parameter', 'matchmaker'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Type', 'matchmaker'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Default', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description & Usage', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>columns</code></td>
                            <td><em>integer</em></td>
                            <td><code>2</code></td>
                            <td><?php esc_html_e('Controls the number of grid columns on desktop screens (1, 2, 3, or 4). On mobile devices (≤768px), the layout automatically collapses to a responsive 1-column stack.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>show_notice</code></td>
                            <td><em>boolean</em></td>
                            <td><code>true</code></td>
                            <td><?php esc_html_e('When set to "true", displays the "Base Membership Required" notice banner above the grid if the visiting user lacks an active base membership plan. Set to "false" to hide the banner notice.', 'matchmaker'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h4 style="margin:16px 0 8px 0; font-size:14px; color:#1e293b;"><?php esc_html_e('Base Membership Purchasing Rules & Gating Logic', 'matchmaker'); ?></h4>
                <div style="background:#fff8f5; border:1px solid #fbd6c5; border-radius:6px; padding:14px 16px; font-size:13.5px; line-height:1.6; color:#1e293b;">
                    <p style="margin:0 0 8px 0;">
                        <strong>🔒 <?php esc_html_e('Enforced Membership Gatekeeping:', 'matchmaker'); ?></strong> <?php esc_html_e('Members must hold an active base plan (Free, Monthly Gold, or Event Pass) before purchasing add-on services.', 'matchmaker'); ?>
                    </p>
                    <ul style="list-style:disc; margin-left:20px; margin-bottom:0;">
                        <li><strong><?php esc_html_e('Active Base Members:', 'matchmaker'); ?></strong> <?php esc_html_e('Action buttons display "Purchase Service →" linking directly to PMPro checkout. If the user already owns the service, a green "✓ Purchased / Active" badge is displayed alongside a "Purchase Again →" button.', 'matchmaker'); ?></li>
                        <li><strong><?php esc_html_e('Guests & Non-Base Members:', 'matchmaker'); ?></strong> <?php esc_html_e('An informative banner explains the base plan requirement. Action buttons automatically adapt to "Membership Required →" with a tooltip, routing visitors to the Membership Plans / Pricing page to choose a base tier first.', 'matchmaker'); ?></li>
                        <li><strong><?php esc_html_e('Backend Checkout Protection:', 'matchmaker'); ?></strong> <?php esc_html_e('Even if a user attempts to bypass the UI by visiting a service checkout URL directly, the PMPro registration validator intercepts the checkout and requires them to select a base plan first.', 'matchmaker'); ?></li>
                    </ul>
                </div>

                <h4 style="margin:16px 0 8px 0; font-size:14px; color:#1e293b;"><?php esc_html_e('Usage Examples', 'matchmaker'); ?></h4>
                <pre style="background:#f1f5f9; padding:12px 16px; border-radius:6px; font-size:13px; line-height:1.7; overflow-x:auto;">
<code>&lt;!-- Standard 2-column services grid with requirement notice --&gt;
[matchmaker_services_grid]

&lt;!-- 3-column services grid for wide landing pages --&gt;
[matchmaker_services_grid columns="3"]

&lt;!-- 2-column services grid without top notice banner --&gt;
[matchmaker_services_grid show_notice="false"]

&lt;!-- Aliases (interchangeable) --&gt;
[az_services_grid]
[matchmaker_services]</code></pre>
            </div>

            <!-- Detailed Section 3: Matchmaking Questionnaire -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>📝</span> <?php esc_html_e('3. Matchmaking Questionnaire Form', 'matchmaker'); ?>
                    <code>[matchmaking_form]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Renders the interactive 37-field matchmaking onboarding and profile update wizard. Designed for initial profile creation as well as ongoing profile maintenance.', 'matchmaker'); ?>
                </p>
                <ul style="list-style:disc; margin-left:24px; color:#475569; font-size:13.5px; line-height:1.7;">
                    <li><strong><?php esc_html_e('Step 1 (About You):', 'matchmaker'); ?></strong> <?php esc_html_e('Personal criteria including First/Last Name, Gender, Preferred Gender, Birth Date (strictly enforces 18+ age limit), Marital Status, Children, Religion, Modesty/Hijab, Country/State/City (with real-time AJAX cascading), Citizenship, Origin/Ethnicity, Languages, Height, Profession, Education, About Me bio, and Photo Uploads (Photo 1 mandatory, up to 3 photos with client preview).', 'matchmaker'); ?></li>
                    <li><strong><?php esc_html_e('Step 2 (Partner Preferences):', 'matchmaker'); ?></strong> <?php esc_html_e('Flexible criteria including Preferred Age Min/Max, Preferred Height Min/Max, Preferred Countries, States, Cities, Religions, Modesty levels, Origins, Languages, Smoking, and Drinking preferences with multiple-choice pills.', 'matchmaker'); ?></li>
                    <li><strong><?php esc_html_e('Automatic Asynchronous Matching:', 'matchmaker'); ?></strong> <?php esc_html_e('Upon questionnaire submission or update, the plugin automatically schedules an asynchronous background matching calculation via Action Scheduler (zero page freeze).', 'matchmaker'); ?></li>
                </ul>

                <h4 style="margin:16px 0 8px 0; font-size:14px; color:#1e293b;"><?php esc_html_e('Standalone Field Shortcode:', 'matchmaker'); ?> <code>[matchmaking_field field="..."]</code></h4>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Allows embedding individual questionnaire input primitives into custom landing page forms or Elementor widgets. Supported field slugs:', 'matchmaker'); ?>
                </p>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 16px; font-size:12.5px; line-height:1.8; color:#334155;">
                    <code>first_name</code>, <code>last_name</code>, <code>email</code>, <code>phone</code>, <code>gender</code>, <code>pref_gender</code>, <code>birth_date</code>, <code>marital_status</code>, <code>children_count</code>, <code>religion</code>, <code>religiosity</code>, <code>modesty</code>, <code>country</code>, <code>state</code>, <code>city</code>, <code>citizenship</code>, <code>origin</code>, <code>languages</code>, <code>height_cm</code>, <code>job</code>, <code>education</code>, <code>smoking</code>, <code>drinking</code>, <code>about_me</code>, <code>partner_preferences</code>, <code>photo_1</code>, <code>photo_2</code>, <code>photo_3</code>
                </div>
                <pre style="background:#f1f5f9; padding:12px 16px; border-radius:6px; font-size:13px; margin-top:10px;">
<code>&lt;!-- Example: Standalone Country and Modesty inputs --&gt;
[matchmaking_field field="country"]
[matchmaking_field field="modesty"]</code></pre>
            </div>

            <!-- Detailed Section 4: Event Registration & RSVP -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>🎟️</span> <?php esc_html_e('4. Event Registration & RSVP', 'matchmaker'); ?>
                    <code>[event_rsvp_button]</code> / <code>[event_reg_form]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Integrates Matrimony Events into your site, handling RSVP button interactions and dedicated event registration forms.', 'matchmaker'); ?>
                </p>
                <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:240px;"><?php esc_html_e('Shortcode', 'matchmaker'); ?></th>
                            <th style="width:180px;"><?php esc_html_e('Parameters', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description & Gating Behavior', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <code>[event_rsvp_button]</code><br>
                                <small style="color:#64748b;"><code>[az_event_rsvp_button]</code>, <code>[rsvp_button]</code></small>
                            </td>
                            <td><em>none (auto-detects post)</em></td>
                            <td><?php esc_html_e('Renders an interactive RSVP button on single event posts. If user is logged out, routes to login. If user is Free tier, prompts upgrade to Event Pass or Monthly Gold. If user holds eligible tier, opens the interactive RSVP confirmation.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <code>[event_reg_form]</code><br>
                                <small style="color:#64748b;"><code>[az_event_reg_form]</code>, <code>[event_registration_form]</code></small>
                            </td>
                            <td><code>form_id</code>, <code>event_id</code></td>
                            <td><?php esc_html_e('Embeds the dedicated Elementor event registration form on a standalone page. Accepts an optional encrypted hash or numeric form ID.', 'matchmaker'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <pre style="background:#f1f5f9; padding:12px 16px; border-radius:6px; font-size:13px; margin-top:12px;">
<code>&lt;!-- RSVP Button on Event Single Template --&gt;
[event_rsvp_button]

&lt;!-- Event Registration Form on Dedicated Page --&gt;
[event_reg_form]</code></pre>
            </div>

            <!-- Detailed Section 5: Featured Profiles (CPT: featured-profile) -->
            <!-- Detailed Section 5: Featured Profiles -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>⭐</span> <?php esc_html_e('5. Featured Profiles Directory & Loop Grids', 'matchmaker'); ?>
                    <code>[featured_profile_details]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Powers the Featured Profile public showcase and Elementor Custom Loop Grids. Connected members can display their profile publicly or anonymously.', 'matchmaker'); ?>
                </p>
                <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:240px;"><?php esc_html_e('Shortcode', 'matchmaker'); ?></th>
                            <th style="width:220px;"><?php esc_html_e('Attributes & Sizing', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Functionality & Privacy Rules', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[featured_profile_details]</code></td>
                            <td><code>post_id</code>, <code>photo_width</code>, <code>photo_height</code>, <code>photo_size</code>, <code>dimensions</code></td>
                            <td><?php esc_html_e('Renders candidate presentation card. Supports custom main image dimensions (e.g. photo_size="350x300"). In Anonymous mode, masks real name with alias or Candidate #ID and scales silhouette placeholder.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[featured_profile_apply_button]</code></td>
                            <td><code>post_id</code> <em>(optional)</em></td>
                            <td><?php esc_html_e('Interactive Apply for a Match CTA button. Enforces candidate isolation: members cannot apply for their own profile, locked if already applied/rejected, checks monthly quota, creates pending match, and notifies admin by email.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[featured_profile_field]</code></td>
                            <td><code>key</code>, <code>post_id</code>, <code>width</code>, <code>height</code>, <code>size</code>, <code>dimensions</code>, <code>radius</code>, <code>fit</code></td>
                            <td><?php esc_html_e('Outputs individual attribute for Elementor Loop Items: name, photo, photo_1, photo_2, photo_3, age, country, state, city, location, job, religion, modesty, origin, languages. Photo fields support full dimensional, aspect-fit, and circular avatar styling.', 'matchmaker'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <pre style="background:#f1f5f9; padding:12px 16px; border-radius:6px; font-size:13px; margin-top:12px;">
<code>&lt;!-- Single Featured Profile Details Card with Custom Photo Size --&gt;
[featured_profile_details photo_size="350x320"]

&lt;!-- Apply for a Match Button --&gt;
[featured_profile_apply_button]

&lt;!-- Elementor Custom Loop Grid Field Snippets with Image Dimensions --&gt;
[featured_profile_field key="name"]
[featured_profile_field key="photo" size="200x200" radius="16px"]
[featured_profile_field key="photo" width="100%" height="250px" fit="cover"]
[featured_profile_field key="photo" dimensions="120x120" radius="50%"] &lt;!-- Circular Avatar --&gt;
[featured_profile_field key="age"]
[featured_profile_field key="country"]
[featured_profile_field key="job"]</code></pre>
            </div>

            <!-- Detailed Section 6 & 7: Email Verification & Utility -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>🔐</span> <?php esc_html_e('6. Email Verification & Security', 'matchmaker'); ?>
                    <code>[az_email_verification]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Renders the 6-digit OTP email confirmation code input card. Features include a 60-second resend cooldown timer, 60-minute code expiration, brute-force throttling, and grandfathering for existing active members.', 'matchmaker'); ?>
                </p>
                <pre style="background:#f1f5f9; padding:10px 14px; border-radius:6px; font-size:13px;"><code>[az_email_verification]</code></pre>

                <hr style="border:0; border-top:1px solid #f1f5f9; margin:20px 0;">

                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>🚪</span> <?php esc_html_e('7. Secure Authentication Logout Link', 'matchmaker'); ?>
                    <code>[logout_url]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Generates a valid WordPress logout URL protected with wp_nonce. Can be used inside custom HTML blocks, navigation menu links, or user action dropdowns.', 'matchmaker'); ?>
                </p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e('Parameter', 'matchmaker'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Default', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>redirect</code></td>
                            <td><code>home_url('/')</code></td>
                            <td><?php esc_html_e('Custom URL path to redirect the user to immediately after logging out.', 'matchmaker'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <pre style="background:#f1f5f9; padding:10px 14px; border-radius:6px; font-size:13px; margin-top:10px;">
<code>&lt;!-- Logout with default home redirect --&gt;
&lt;a href="[logout_url]"&gt;Log Out&lt;/a&gt;

&lt;!-- Logout with custom redirect to login page --&gt;
&lt;a href="[logout_url redirect="/login/"]"&gt;Log Out&lt;/a&gt;</code></pre>
            </div>

            <!-- Detailed Section 8: Standalone Member Profile Photo -->
            <div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0; font-size:16px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span>📸</span> <?php esc_html_e('8. Member Profile Photo', 'matchmaker'); ?>
                    <code>[matchmaker_profile_photo]</code>
                </h3>
                <p style="color:#475569; font-size:13.5px; line-height:1.6;">
                    <?php esc_html_e('Renders a member or current user profile photo anywhere on the site with complete control over dimensions, size, circular cropping, aspect fit, and lightbox gallery.', 'matchmaker'); ?>
                    <br><small style="color:#64748b;"><?php esc_html_e('Aliases:', 'matchmaker'); ?> <code>[az_profile_photo]</code>, <code>[matchmaker_photo]</code></small>
                </p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e('Parameter', 'matchmaker'); ?></th>
                            <th style="width:120px;"><?php esc_html_e('Default', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description & Accepted Values', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>user_id</code></td>
                            <td><code>0</code> <em>(Logged-in)</em></td>
                            <td><?php esc_html_e('Target member ID. If omitted, automatically displays current logged-in member photo.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>photo</code> / <code>photo_num</code></td>
                            <td><code>1</code></td>
                            <td><?php esc_html_e('Photo slot index: 1, 2, or 3.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>size</code> / <code>dimensions</code></td>
                            <td><em>(none)</em></td>
                            <td><?php esc_html_e('Shorthand dimension: e.g. "150" (150x150), "200x300", "thumbnail" (150x150), "medium" (300x300), "large" (800x800).', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>width</code></td>
                            <td><code>100%</code></td>
                            <td><?php esc_html_e('Explicit CSS width: e.g. "150", "150px", "100%", "12rem". Numbers default to px.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>height</code></td>
                            <td><code>auto</code></td>
                            <td><?php esc_html_e('Explicit CSS height: e.g. "150", "150px", "auto", "300px".', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>radius</code> / <code>border_radius</code></td>
                            <td><code>12px</code></td>
                            <td><?php esc_html_e('Border radius styling: e.g. "8px", "16px", "50%", "circle", "round". Set to "50%" or "circle" for round avatars.', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>fit</code> / <code>object_fit</code></td>
                            <td><code>cover</code></td>
                            <td><?php esc_html_e('CSS object-fit: "cover", "contain", "fill", "none", "scale-down".', 'matchmaker'); ?></td>
                        </tr>
                        <tr>
                            <td><code>lightbox</code></td>
                            <td><code>no</code></td>
                            <td><?php esc_html_e('Set to "yes" to enable popup full-screen gallery lightbox on image click.', 'matchmaker'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <pre style="background:#f1f5f9; padding:12px 16px; border-radius:6px; font-size:13px; margin-top:12px;">
<code>&lt;!-- Current Logged-in User Circular Avatar --&gt;
[matchmaker_profile_photo size="100x100" radius="50%"]

&lt;!-- Specific Member Photo with Lightbox --&gt;
[matchmaker_profile_photo user_id="45" dimensions="250x300" fit="cover" lightbox="yes"]

&lt;!-- Secondary Photo Preview --&gt;
[matchmaker_profile_photo photo="2" width="180px" height="180px" radius="14px"]</code></pre>
            </div>
        </div>

        <p class="submit" id="mm-main-submit-row" style="margin-top: 24px;">
            <input type="submit" name="mm_save_settings" class="button button-primary button-large" value="<?php esc_attr_e('Save All Settings', 'matchmaker'); ?>">
        </p>
    </form>

    <?php if ($is_test_mode) : ?>
        <form method="post" action="" id="mm_reset_test_data_form" style="display:none;">
            <?php wp_nonce_field('mm_reset_test_data_nonce'); ?>
            <input type="hidden" name="mm_reset_test_data" value="1">
        </form>
    <?php endif; ?>

    <form method="post" action="" id="mm_recalculate_quotas_form" style="display:none;">
        <?php wp_nonce_field('mm_recalculate_quotas_nonce'); ?>
        <input type="hidden" name="mm_recalculate_quotas" value="1">
    </form>
</div>

<script>
(function() {
    'use strict';

    // Log viewer state
    var currentLogType = 'info';
    var logsNonce      = '<?php echo esc_js($file_logs_nonce); ?>';
    var downloadNonce  = '<?php echo esc_js($download_nonce); ?>';
    var baseDownloadUrl = '<?php echo esc_js(admin_url('admin.php?page=matchmaking-settings&action=download_file_log')); ?>';

    function initSettingsTabs() {
        var tabLinks = document.querySelectorAll('.mm-settings-tab-wrapper a.nav-tab');
        var panels   = document.querySelectorAll('.mm-settings-tab-panel');
        var submitRow = document.getElementById('mm-main-submit-row');

        if (!tabLinks.length || !panels.length) {
            return;
        }

        function activateTab(tabKey) {
            if (!tabKey) return;
            var cleanKey = String(tabKey).replace(/^#?tab-/, '').replace(/^#/, '').trim();
            if (!cleanKey) return;

            var targetPanel = document.getElementById('mm-panel-' + cleanKey);
            if (!targetPanel) return;

            tabLinks.forEach(function(link) {
                var lKey = (link.getAttribute('data-tab') || link.getAttribute('href') || '').replace(/^#?tab-/, '').replace(/^#/, '').trim();
                if (lKey === cleanKey) {
                    link.classList.add('nav-tab-active');
                } else {
                    link.classList.remove('nav-tab-active');
                }
            });

            panels.forEach(function(panel) {
                panel.style.display = 'none';
                panel.classList.remove('active');
            });

            targetPanel.style.display = 'block';
            targetPanel.classList.add('active');

            if (submitRow) {
                submitRow.style.display = (cleanKey === 'bulk-email' || cleanKey === 'file-logs' || cleanKey === 'shortcodes') ? 'none' : 'block';
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, '#tab-' + cleanKey);
            }

        tabLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var tabKey = this.getAttribute('data-tab') || this.getAttribute('href') || '';
                activateTab(tabKey);
            });
        });

        if (window.location.hash) {
            activateTab(window.location.hash);
        }

        window.addEventListener('hashchange', function() {
            if (window.location.hash) {
                activateTab(window.location.hash);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSettingsTabs();
        });
    } else {
        initSettingsTabs();
    }
})();
</script>
