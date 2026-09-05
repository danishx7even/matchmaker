<?php
/**
 * View: Admin Matchmaker Settings & Mode Configuration
 *
 * Available variables:
 *   @var string               $environment_mode
 *   @var bool                 $is_test_mode
 *   @var array<int, string>   $current_mapping
 *   @var array<int, object>   $pmpro_levels
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
 *   @var string               $subject
 *   @var string               $template
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
?>
<div class="wrap mm-admin-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:15px;">
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
                    <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Are you ABSOLUTELY sure you want to reset all test matches, notifications, and logs? Candidate pool profiles will be preserved.', 'matchmaker')); ?>');">
                        <?php wp_nonce_field('mm_reset_test_data_nonce'); ?>
                        <input type="hidden" name="mm_reset_test_data" value="1">
                        <button type="submit" class="button button-secondary" style="background:#e11d48; color:#fff; border-color:#be123c; font-weight:700; padding:4px 16px; height:auto; line-height:28px;">
                            🗑️ <?php esc_html_e('Reset Test Matchmaking Data', 'matchmaker'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="margin-top:20px;">
        <?php wp_nonce_field('mm_save_settings_nonce'); ?>
        
        <!-- SECTION 0: Environment Mode -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('1. Environment Mode', 'matchmaker'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Toggle between Test Mode and Live Production Mode. In Test Mode, you can freely reset match pairs, logs, and notification history without losing member questionnaire data.', 'matchmaker'); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mm_environment_mode"><?php esc_html_e('Active System Mode', 'matchmaker'); ?></label></th>
                    <td>
                        <select name="mm_environment_mode" id="mm_environment_mode" style="min-width:260px; font-weight:600;">
                            <option value="live" <?php selected($environment_mode, 'live'); ?>><?php esc_html_e('🛡️ Live / Production Mode', 'matchmaker'); ?></option>
                            <option value="test" <?php selected($environment_mode, 'test'); ?>><?php esc_html_e('🧪 Test Mode (Enables Data Reset Tool)', 'matchmaker'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('When set to Test Mode, the "Reset Test Matchmaking Data" button will appear above.', 'matchmaker'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- SECTION 1: PMPro Membership Plan Mapping -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('2. PMPro Membership Plan Connector', 'matchmaker'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Map each Paid Memberships Pro membership level to a Matchmaker tier. This eliminates hardcoded plan IDs and lets you create or modify membership levels freely in PMPro.', 'matchmaker'); ?>
            </p>

            <?php if (!empty($pmpro_levels)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:70px;"><?php esc_html_e('ID', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('PMPro Level Name', 'matchmaker'); ?></th>
                            <th><?php esc_html_e('Description / Price', 'matchmaker'); ?></th>
                            <th style="width:240px;"><?php esc_html_e('Assigned Matchmaking Tier', 'matchmaker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pmpro_levels as $lvl) : 
                            $lid      = (int) $lvl->id;
                            $assigned = $current_mapping[$lid] ?? 'free';
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
                                            <?php esc_html_e('Monthly Member (Active Matching)', 'matchmaker'); ?>
                                        </option>
                                        <option value="one_on_one" <?php selected($assigned, 'one_on_one'); ?>>
                                            <?php esc_html_e('1-on-1 VIP Member (VIP Matching)', 'matchmaker'); ?>
                                        </option>
                                        <option value="event" <?php selected($assigned, 'event'); ?>>
                                            <?php esc_html_e('Event Member (Event Only / Upsell)', 'matchmaker'); ?>
                                        </option>
                                        <option value="free" <?php selected($assigned, 'free'); ?>>
                                            <?php esc_html_e('Free Member (Upsell Banner)', 'matchmaker'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color:#c02b0a; background:#fff2f0; padding:10px 14px; border-left:4px solid #c02b0a; border-radius:4px;">
                    <?php esc_html_e('Paid Memberships Pro is not active or has no registered levels. Default level mapping is being used (Level 3 => Monthly, Levels 4/5 => VIP, Level 6 => Event, Level 2 => Free).', 'matchmaker'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: Quotas & Expiration Rules -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('3. Matchmaking Quota & Expiry Rules', 'matchmaker'); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mm_max_cycle_matches"><?php esc_html_e('Max Matches per Cycle', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="number" min="1" max="100" name="mm_max_cycle_matches" id="mm_max_cycle_matches" value="<?php echo esc_attr((string)$max_matches); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Maximum number of approved matches allowed per member per billing cycle (default: 10).', 'matchmaker'); ?></p>
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

        <!-- SECTION 3: Page Routing & Elementor Integration -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('4. Page Routing & Form Integration', 'matchmaker'); ?>
            </h2>
            <p class="description"><?php esc_html_e('Select the target WordPress pages for member flows to eliminate hardcoded URL slugs.', 'matchmaker'); ?></p>
            
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
                        <p class="description"><?php esc_html_e('Page containing the [matchmaker_member_portal] shortcode.', 'matchmaker'); ?></p>
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
                        <p class="description"><?php esc_html_e('Page containing the [matchmaking_form] shortcode.', 'matchmaker'); ?></p>
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
                        <p class="description"><?php esc_html_e('Elementor Pro Form Widget ID(s) used for free registration (e.g. 2784843). Multiple IDs can be comma-separated.', 'matchmaker'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- SECTION 4: Events Configuration -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('4. Member Portal Events Configuration', 'matchmaker'); ?>
            </h2>
            <p class="description"><?php esc_html_e('Configure how the Events custom post type and Elementor loop template are displayed inside the Member Portal Events tab.', 'matchmaker'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mm_events_cpt_slug"><?php esc_html_e('Event CPT Slug', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="text" name="mm_events_cpt_slug" id="mm_events_cpt_slug" value="<?php echo esc_attr($events_cpt_slug ?? 'event'); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('The post type slug for Events created via ACF (default: "event").', 'matchmaker'); ?></p>
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
                        <p class="description"><?php esc_html_e('Number of events to display per page with in-canvas AJAX pagination (default: 6).', 'matchmaker'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- SECTION 5: Approval Email Template -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('5. Approval Email Notification Template', 'matchmaker'); ?>
            </h2>
            <p class="description"><?php esc_html_e('Customize the email notification sent to both matched members when an admin approves a match.', 'matchmaker'); ?></p>

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
                            'textarea_rows' => 10,
                            'media_buttons' => true,
                            'teeny'         => false,
                        ]);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Available Placeholders', 'matchmaker'); ?></th>
                    <td>
                        <table class="widefat compact striped" style="max-width:550px;">
                            <thead>
                                <tr><th><?php esc_html_e('Variable Tag', 'matchmaker'); ?></th><th><?php esc_html_e('Description', 'matchmaker'); ?></th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>{user_name}</code></td><td><?php esc_html_e('Recipient member display name', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{candidate_name}</code></td><td><?php esc_html_e('Matched candidate display name', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{candidate_age}</code></td><td><?php esc_html_e('Matched candidate age in years', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{candidate_location}</code></td><td><?php esc_html_e('Matched candidate location', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{dashboard_url}</code></td><td><?php esc_html_e('Direct member portal dashboard URL', 'matchmaker'); ?></td></tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- SECTION 6: Verification Email Configuration -->
        <div class="mm-card" style="margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                <?php esc_html_e('6. Email Verification Code & Delivery Settings', 'matchmaker'); ?>
            </h2>
            <p class="description"><?php esc_html_e('Configure the sender headers, 6-digit OTP template, expiration, and resend cooldown for user email verification.', 'matchmaker'); ?></p>
            <p class="description" style="margin-top: 10px; color: #1e3a8a; background: #eff6ff; padding: 10px 14px; border-left: 4px solid #3b82f6; border-radius: 4px; font-size: 13px; line-height: 1.5;">
                <strong>ℹ️ <?php esc_html_e('SMTP Configuration Note:', 'matchmaker'); ?></strong><br>
                <?php esc_html_e('If your server returns "Could not instantiate mail function", WordPress is trying to use PHP\'s built-in mail() without a local mail transfer agent. Please ensure an SMTP plugin (such as WP Mail SMTP, FluentSMTP, or Post SMTP) is configured with valid credentials. In Test Mode, the plugin will automatically simulate code delivery so you can test locally without an active SMTP server.', 'matchmaker'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mm_email_verify_from_email"><?php esc_html_e('Sender Email (From Email)', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="email" name="mm_email_verify_from_email" id="mm_email_verify_from_email" value="<?php echo esc_attr($verify_from_email ?? ''); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Email address used as the sender (e.g. no-reply@arabzawaj.com). Leave empty to default to site admin email / domain.', 'matchmaker'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mm_email_verify_from_name"><?php esc_html_e('Sender Name (From Name)', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="text" name="mm_email_verify_from_name" id="mm_email_verify_from_name" value="<?php echo esc_attr($verify_from_name ?? ''); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name') ?: 'Arab Zawaj Matrimony'); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Display name used as the sender (e.g. Arab Zawaj Matrimony).', 'matchmaker'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mm_email_verify_subject"><?php esc_html_e('Verification Email Subject', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="text" name="mm_email_verify_subject" id="mm_email_verify_subject" value="<?php echo esc_attr($verify_subject ?? ''); ?>" class="large-text">
                        <p class="description"><?php esc_html_e('Subject line for the verification email. Supports {code} placeholder.', 'matchmaker'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mm_email_verify_template"><?php esc_html_e('Verification Email Body', 'matchmaker'); ?></label></th>
                    <td>
                        <?php
                        wp_editor($verify_template ?? '', 'mm_email_verify_template', [
                            'textarea_name' => 'mm_email_verify_template',
                            'textarea_rows' => 10,
                            'media_buttons' => true,
                            'teeny'         => false,
                        ]);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Available Placeholders', 'matchmaker'); ?></th>
                    <td>
                        <table class="widefat compact striped" style="max-width:550px;">
                            <thead>
                                <tr><th><?php esc_html_e('Variable Tag', 'matchmaker'); ?></th><th><?php esc_html_e('Description', 'matchmaker'); ?></th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>{code}</code></td><td><strong><?php esc_html_e('The 6-digit verification security code (REQUIRED)', 'matchmaker'); ?></strong></td></tr>
                                <tr><td><code>{user_name}</code></td><td><?php esc_html_e('Member display name', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{user_email}</code></td><td><?php esc_html_e('Member recipient email address', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{site_name}</code></td><td><?php esc_html_e('Website title', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{expiry_hours}</code></td><td><?php esc_html_e('Code expiry duration in hours', 'matchmaker'); ?></td></tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mm_email_verify_expiry_hours"><?php esc_html_e('Code Expiration (Hours)', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="number" name="mm_email_verify_expiry_hours" id="mm_email_verify_expiry_hours" value="<?php echo (int) ($verify_expiry_hours ?? 24); ?>" min="1" max="168" class="small-text">
                        <p class="description"><?php esc_html_e('Number of hours a 6-digit verification code remains valid before expiring (default: 24).', 'matchmaker'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mm_email_verify_cooldown_seconds"><?php esc_html_e('Resend Cooldown (Seconds)', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="number" name="mm_email_verify_cooldown_seconds" id="mm_email_verify_cooldown_seconds" value="<?php echo (int) ($verify_cooldown_seconds ?? 60); ?>" min="5" max="600" class="small-text">
                        <p class="description"><?php esc_html_e('Minimum seconds a user must wait before requesting another verification code via resend (default: 60).', 'matchmaker'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="mm_save_settings" class="button button-primary button-large" value="<?php esc_attr_e('Save All Settings', 'matchmaker'); ?>">
        </p>
    </form>

    <hr style="margin:30px 0;">

    <h2><?php esc_html_e('Available Shortcodes Reference', 'matchmaker'); ?></h2>
    <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Shortcode', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Description', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Target Page / Location', 'matchmaker'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[matchmaker_member_portal]</code></td>
                <td><?php esc_html_e('Primary 2-tab member portal dashboard (Profile & Matches)', 'matchmaker'); ?></td>
                <td><code>/dashboard/</code></td>
            </tr>
            <tr>
                <td><code>[az_profile]</code></td>
                <td><?php esc_html_e('Backward-compatibility alias for member portal dashboard', 'matchmaker'); ?></td>
                <td><code>/dashboard/</code></td>
            </tr>
            <tr>
                <td><code>[matchmaking_form]</code></td>
                <td><?php esc_html_e('Full 37-field matchmaking questionnaire wizard', 'matchmaker'); ?></td>
                <td><code>/personal-matchmaking-questionnaire/</code></td>
            </tr>
            <tr>
                <td><code>[matchmaking_field field="..."]</code></td>
                <td><?php esc_html_e('Renders a single standalone matchmaking field input', 'matchmaker'); ?></td>
                <td>Any page/elementor block</td>
            </tr>
            <tr>
                <td><code>[logout_url]</code></td>
                <td><?php esc_html_e('Outputs formatted logout URL with optional redirect attribute', 'matchmaker'); ?></td>
                <td>Any menu or template</td>
            </tr>
        </tbody>
    </table>
</div>
