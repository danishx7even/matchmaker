<?php
/**
 * View: Admin Settings – Bulk Email Campaign Tab
 *
 * Available variables:
 *   @var string $bulk_default_subject
 *   @var string $bulk_default_template
 *   @var array  $placeholders_guide
 *   @var array  $services_list
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$bulk_service = \Matchmaker\Service\BulkEmailService::instance();
$placeholders = $bulk_service->get_placeholders_guide();
$default_sub  = $bulk_service->get_default_subject();
$default_tpl  = $bulk_service->get_default_template();
$services     = class_exists('\Matchmaker\Core\PMProSync') ? \Matchmaker\Core\PMProSync::instance()->get_services_levels() : [];
?>

<div class="mm-card" style="margin-bottom:24px; padding:24px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:16px;">
        <div>
            <h2 style="margin:0 0 6px; font-size:18px; color:#1D1E20; display:flex; align-items:center; gap:8px;">
                📨 <?php esc_html_e('Bulk Email Campaign Dispatcher', 'matchmaker'); ?>
            </h2>
            <p style="margin:0; color:#64748b; font-size:13px; line-height:1.5; max-width:700px;">
                <?php esc_html_e('Target member groups by tier, active services, or select individual members. Campaigns are queued and processed asynchronously via Action Scheduler with complete audit logging.', 'matchmaker'); ?>
            </p>
        </div>
        <div id="mm-recipient-counter-badge" style="background:#f0fdf4; border:1px solid #86efac; color:#166534; padding:8px 16px; border-radius:20px; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
            👥 <?php esc_html_e('Target Recipients:', 'matchmaker'); ?> <span id="mm-recipient-count" style="font-size:15px; text-decoration:underline;">0</span>
        </div>
    </div>

    <!-- Alert / Response Notification Box -->
    <div id="mm-bulk-email-notice" style="display:none; margin-bottom:20px;"></div>

    <!-- Section 1: Recipient Targeting Mode -->
    <div style="margin-bottom:24px;">
        <label style="font-weight:700; font-size:14px; display:block; margin-bottom:8px; color:#1e293b;">
            1. <?php esc_html_e('Select Target Audience', 'matchmaker'); ?>
        </label>
        
        <div style="display:flex; gap:24px; margin-bottom:14px; flex-wrap:wrap;">
            <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:13px; color:#334155;">
                <input type="radio" name="mm_target_mode" value="criteria" checked>
                <?php esc_html_e('Segment by Criteria (Tier, Services, Application Type)', 'matchmaker'); ?>
            </label>
            <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:13px; color:#334155;">
                <input type="radio" name="mm_target_mode" value="members">
                <?php esc_html_e('Specific Members (Search & Select)', 'matchmaker'); ?>
            </label>
        </div>

        <!-- Mode A: Criteria Filters -->
        <div id="mm-audience-criteria-wrap" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:16px; display:flex; flex-wrap:wrap; gap:16px; align-items:center;">
            <div>
                <label for="mm_bulk_tier" style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:4px;">
                    <?php esc_html_e('Base Membership Tier', 'matchmaker'); ?>
                </label>
                <select id="mm_bulk_tier" name="mm_bulk_tier" class="regular-text" style="min-width:180px;">
                    <option value=""><?php esc_html_e('All Base Tiers', 'matchmaker'); ?></option>
                    <option value="monthly"><?php esc_html_e('Monthly Members', 'matchmaker'); ?></option>
                    <option value="event"><?php esc_html_e('Event Members', 'matchmaker'); ?></option>
                    <option value="free"><?php esc_html_e('Free Members', 'matchmaker'); ?></option>
                </select>
            </div>

            <div>
                <label for="mm_bulk_service" style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:4px;">
                    <?php esc_html_e('Active Purchased Services', 'matchmaker'); ?>
                </label>
                <select id="mm_bulk_service" name="mm_bulk_service" class="regular-text" style="min-width:200px;">
                    <option value=""><?php esc_html_e('All Services / Any', 'matchmaker'); ?></option>
                    <option value="has_service"><?php esc_html_e('⭐ Has Any Active Service', 'matchmaker'); ?></option>
                    <option value="no_service"><?php esc_html_e('No Active Services', 'matchmaker'); ?></option>
                    <?php if (!empty($services)) : ?>
                        <optgroup label="<?php esc_attr_e('Specific Service:', 'matchmaker'); ?>">
                            <?php foreach ($services as $srv) : ?>
                                <option value="<?php echo (int) $srv->id; ?>">
                                    <?php echo esc_html($srv->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label for="mm_bulk_parent" style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:4px;">
                    <?php esc_html_e('Application Type', 'matchmaker'); ?>
                </label>
                <select id="mm_bulk_parent" name="mm_bulk_parent" class="regular-text" style="min-width:180px;">
                    <option value="all"><?php esc_html_e('All Applications', 'matchmaker'); ?></option>
                    <option value="1"><?php esc_html_e('👨‍👩‍👧 Parent Applying Only', 'matchmaker'); ?></option>
                    <option value="0"><?php esc_html_e('Self Applying Only', 'matchmaker'); ?></option>
                </select>
            </div>
        </div>

        <!-- Mode B: Specific Member Autocomplete Search Chips -->
        <div id="mm-audience-members-wrap" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:16px; display:none;">
            <label for="mm_member_search_input" style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:6px;">
                <?php esc_html_e('Search member by name or email to add to list:', 'matchmaker'); ?>
            </label>
            <div style="position:relative; max-width:480px;">
                <input type="text" id="mm_member_search_input" placeholder="<?php esc_attr_e('Type name or email...', 'matchmaker'); ?>" class="widefat" autocomplete="off">
                <div id="mm-member-search-results" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccd0d4; border-radius:4px; max-height:220px; overflow-y:auto; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
            </div>
            
            <div id="mm-selected-member-chips" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; min-height:36px; align-items:center;">
                <span class="description" id="mm-no-members-msg" style="color:#94a3b8;"><?php esc_html_e('No individual members selected yet. Search above to add.', 'matchmaker'); ?></span>
            </div>
            <input type="hidden" id="mm_selected_member_ids" name="mm_selected_member_ids" value="">
        </div>
    </div>

    <!-- Section 2: Campaign Subject Line -->
    <div style="margin-bottom:20px;">
        <label for="mm_bulk_subject" style="font-weight:700; font-size:14px; display:block; margin-bottom:6px; color:#1e293b;">
            2. <?php esc_html_e('Email Subject Line', 'matchmaker'); ?>
        </label>
        <input type="text" id="mm_bulk_subject" name="mm_bulk_subject" value="<?php echo esc_attr($default_sub); ?>" class="large-text" placeholder="<?php esc_attr_e('e.g. Important Update from {site_name}', 'matchmaker'); ?>" style="font-size:14px; padding:6px 10px;">
    </div>

    <!-- Section 3: Dynamic Placeholders Cheat Sheet -->
    <div style="margin-bottom:20px; background:#faf5f0; border:1px solid #eeddc8; border-radius:6px; padding:14px;">
        <span style="font-weight:700; font-size:12px; text-transform:uppercase; color:#CC723F; display:block; margin-bottom:6px;">
            ℹ️ <?php esc_html_e('Dynamic Personalization Placeholders (Click tag to insert into template):', 'matchmaker'); ?>
        </span>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <?php foreach ($placeholders as $ph => $ph_desc) : ?>
                <button type="button" class="mm-placeholder-pill" data-placeholder="<?php echo esc_attr($ph); ?>" title="<?php echo esc_attr($ph_desc); ?>" style="background:#fff; border:1px solid #d7c2a7; color:#7c3aed; padding:3px 8px; border-radius:4px; font-family:monospace; font-size:12px; cursor:pointer; font-weight:600;">
                    <?php echo esc_html($ph); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Section 4: Email Body Editor (HTML WYSIWYG) -->
    <div style="margin-bottom:24px;">
        <label for="mm_bulk_template" style="font-weight:700; font-size:14px; display:block; margin-bottom:8px; color:#1e293b;">
            3. <?php esc_html_e('Email Body Content (HTML)', 'matchmaker'); ?>
        </label>
        <?php
        wp_editor($default_tpl, 'mm_bulk_template', [
            'textarea_name' => 'mm_bulk_template',
            'textarea_rows' => 12,
            'teeny'         => false,
            'media_buttons' => true,
            'quicktags'     => true,
        ]);
        ?>
    </div>

    <!-- Section 5: Action Buttons -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid #eee; padding-top:16px;">
        <div style="display:flex; gap:10px; align-items:center;">
            <button type="button" id="mm-send-bulk-email-btn" class="button button-primary button-large" style="background:#CC723F; border-color:#b56132; font-weight:700; font-size:14px; padding:6px 20px;">
                🚀 <?php esc_html_e('Send Bulk Email (Asynchronous)', 'matchmaker'); ?>
            </button>
            <span id="mm-bulk-email-spinner" class="spinner" style="float:none; margin:0;"></span>
        </div>

        <div>
            <button type="button" id="mm-save-default-bulk-email-btn" class="button button-secondary" title="<?php esc_attr_e('Persistently save current subject and body as the default template for future campaigns', 'matchmaker'); ?>">
                💾 <?php esc_html_e('Save as Default Template', 'matchmaker'); ?>
            </button>
        </div>
    </div>
</div>
