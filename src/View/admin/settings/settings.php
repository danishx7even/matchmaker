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
            <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <?php esc_html_e('Available Shortcodes Reference', 'matchmaker'); ?>
                </h2>
                <table class="wp-list-table widefat fixed striped" style="margin-top:14px;">
                    <thead>
                        <tr>
                            <th style="width:260px;"><?php esc_html_e('Shortcode', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Target Page / Location', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[matchmaker_member_portal]</code></td>
                            <td><?php esc_html_e('Member matchmaking portal with Profile & Matches tabs.', 'matchmaker'); ?></td>
                            <td>Member Dashboard Page</td>
                        </tr>
                        <tr>
                            <td><code>[az_profile]</code></td>
                            <td><?php esc_html_e('Alias shortcode for the member matchmaking portal.', 'matchmaker'); ?></td>
                            <td>Member Dashboard Page</td>
                        </tr>
                        <tr>
                            <td><code>[matchmaking_form]</code></td>
                            <td><?php esc_html_e('Interactive 37-field multi-step matchmaking questionnaire wizard.', 'matchmaker'); ?></td>
                            <td>Questionnaire Page</td>
                        </tr>
                        <tr>
                            <td><code>[matchmaking_field field="..."]</code></td>
                            <td><?php esc_html_e('Renders a single standalone profile questionnaire field.', 'matchmaker'); ?></td>
                            <td>Any page or Elementor block</td>
                        </tr>
                        <tr>
                            <td><code>[az_email_verification]</code></td>
                            <td><?php esc_html_e('Renders the 6-digit email confirmation code submission form.', 'matchmaker'); ?></td>
                            <td>Email Verification Page</td>
                        </tr>
                        <tr>
                            <td><code>[logout_url]</code></td>
                            <td><?php esc_html_e('Outputs secure formatted logout URL with redirect support.', 'matchmaker'); ?></td>
                            <td>Any menu or custom link</td>
                        </tr>
                    </tbody>
                </table>
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
</div>

<script>
(function() {
    'use strict';
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
                submitRow.style.display = (cleanKey === 'bulk-email') ? 'none' : 'block';
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, '#tab-' + cleanKey);
            }
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
        document.addEventListener('DOMContentLoaded', initSettingsTabs);
    } else {
        initSettingsTabs();
    }
})();
</script>
