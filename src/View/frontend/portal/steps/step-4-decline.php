<?php
/**
 * View: Member Portal – Matches Step 4 (Decline Confirmation Modal/Card)
 *
 * Available variables:
 *   @var array<string, mixed> $active_match
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
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

        <div class="mm-rejection-prompt-box" style="text-align: left; margin: 20px 0 16px 0; width: 100%;">
            <label for="mm-rejection-reason" style="display: block; font-weight: 600; font-size: 14px; color: #1e293b; margin-bottom: 6px;">
                <?php esc_html_e('Help Us Improve Your Matches *', 'matchmaker'); ?>
            </label>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 10px; line-height: 1.5;">
                <?php esc_html_e("Please tell us why this match wasn't right for you so our matchmakers can curate better recommendations for your profile.", 'matchmaker'); ?>
            </p>
            <textarea 
                id="mm-rejection-reason" 
                name="rejection_reason" 
                class="mm-rejection-textarea" 
                rows="4" 
                placeholder="<?php esc_attr_e('e.g., Looking for someone in a different location, age preference, lifestyle differences, etc.', 'matchmaker'); ?>" 
                style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 12px; font-size: 14px; font-family: inherit; box-sizing: border-box; resize: vertical; outline: none; transition: border-color 0.2s;"
                onfocus="this.style.borderColor='#CC723F';"
                onblur="this.style.borderColor='#cbd5e1';"
                required
            ></textarea>
            <div id="mm-rejection-error" style="color: #dc2626; font-size: 13px; font-weight: 500; margin-top: 6px; display: none;"></div>
        </div>

        <div style="display: flex; gap: 14px; width: 100%;">
            <button type="button" class="btn btn-primary" style="flex: 1;" data-mm-action="navigate-step" data-step="2">
                <?php esc_html_e('Keep Match', 'matchmaker'); ?>
            </button>
            <button type="button" class="btn btn-outline-danger" style="flex: 1;" data-mm-action="submit-response" data-match-id="<?php echo (int) $active_match['match_id']; ?>" data-decision="decline">
                <?php esc_html_e('Decline Match →', 'matchmaker'); ?>
            </button>
        </div>
    </div>
</div>
