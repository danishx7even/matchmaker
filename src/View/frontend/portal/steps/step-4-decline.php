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

        <div style="display: flex; gap: 14px;">
            <button type="button" class="btn btn-primary" style="flex: 1;" data-mm-action="navigate-step" data-step="2">
                <?php esc_html_e('Keep Match', 'matchmaker'); ?>
            </button>
            <button type="button" class="btn btn-outline-danger" style="flex: 1;" data-mm-action="submit-response" data-match-id="<?php echo (int) $active_match['match_id']; ?>" data-decision="decline">
                <?php esc_html_e('Decline Match →', 'matchmaker'); ?>
            </button>
        </div>
    </div>
</div>
