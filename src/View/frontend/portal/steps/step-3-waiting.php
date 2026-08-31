<?php
/**
 * View: Member Portal – Matches Step 3 (Response Status & Waiting State)
 *
 * Available variables:
 *   @var array<string, mixed> $active_match
 *   @var string               $my_resp
 *   @var string               $their_resp
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$my_is_accepted    = ($my_resp === 'accepted');
$my_is_declined    = in_array($my_resp, ['rejected', 'declined'], true);
$their_is_accepted = ($their_resp === 'accepted');
$their_is_declined = in_array($their_resp, ['rejected', 'declined'], true);
?>
<!-- STATE 3: ACCEPTED — WAITING FOR RESPONSE (STEP 3) -->
<div id="step-3" class="view-state">
    <div style="padding: 24px 48px 0;">
        <button type="button" class="mm-back-btn" data-mm-action="goback-step" style="display: inline-flex; align-items: center; gap: 6px; background: none; border: none; font-size: 14px; font-weight: 600; color: #CC723F; cursor: pointer; padding: 0;">
            ← <?php esc_html_e('Back to Matches', 'matchmaker'); ?>
        </button>
    </div>
    <div class="centered-state-wrapper">
        <div class="status-avatar-bubble <?php echo ($my_is_declined || $their_is_declined) ? 'danger' : 'orange'; ?>">
            <?php if ($my_is_declined || $their_is_declined) : ?>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?php else : ?>
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php endif; ?>
        </div>

        <h1 class="state-main-title font-cormorant">
            <?php
            if ($my_is_accepted && $their_is_accepted) {
                esc_html_e("It's a Match!", 'matchmaker');
            } elseif ($their_is_declined) {
                esc_html_e('Match Declined by Candidate', 'matchmaker');
            } elseif ($my_is_declined) {
                esc_html_e('Match Declined by You', 'matchmaker');
            } else {
                esc_html_e('Match Accepted', 'matchmaker');
            }
            ?>
        </h1>
        <p class="state-main-desc">
            <?php
            if ($my_is_accepted && $their_is_accepted) {
                esc_html_e("You both accepted! Access each other's contact info.", 'matchmaker');
            } elseif ($their_is_declined) {
                esc_html_e("The candidate has chosen to decline this match recommendation.", 'matchmaker');
            } elseif ($my_is_declined) {
                esc_html_e("You have declined this match recommendation.", 'matchmaker');
            } else {
                esc_html_e("You've accepted this match. We're now waiting for the candidate to respond.", 'matchmaker');
            }
            ?>
        </p>

        <div class="responses-container-box">
            <div class="response-entry-card">
                <span class="res-label">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <?php esc_html_e('Your Response', 'matchmaker'); ?>
                </span>
                <?php if ($my_is_accepted) : ?>
                    <span class="res-tag-accepted">✓ <?php esc_html_e('Accepted', 'matchmaker'); ?></span>
                <?php elseif ($my_is_declined) : ?>
                    <span class="res-tag-declined">✕ <?php esc_html_e('Declined', 'matchmaker'); ?></span>
                <?php else : ?>
                    <span class="res-tag-waiting">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="8" y2="12"></line><line x1="12" y1="12" x2="12" y2="12"></line><line x1="16" y1="12" x2="16" y2="12"></line></svg>
                        <?php esc_html_e('Pending', 'matchmaker'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="response-entry-card">
                <span class="res-label">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <?php esc_html_e('Their Response', 'matchmaker'); ?>
                </span>
                <?php if ($their_is_accepted) : ?>
                    <span class="res-tag-accepted">✓ <?php esc_html_e('Accepted', 'matchmaker'); ?></span>
                <?php elseif ($their_is_declined) : ?>
                    <span class="res-tag-declined">✕ <?php esc_html_e('Declined', 'matchmaker'); ?></span>
                <?php else : ?>
                    <span class="res-tag-waiting">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="8" y2="12"></line><line x1="12" y1="12" x2="12" y2="12"></line><line x1="16" y1="12" x2="16" y2="12"></line></svg>
                        <?php esc_html_e('Waiting', 'matchmaker'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="state-next-note">
            <h3 class="font-cormorant"><?php esc_html_e("What's Next?", 'matchmaker'); ?></h3>
            <p><?php esc_html_e("If they also accept, you'll both receive access to each other's approved direct contact information.", 'matchmaker'); ?></p>
        </div>

        <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="switch-tab" data-tab="profile">
            <?php esc_html_e('Back to Profile Dashboard →', 'matchmaker'); ?>
        </button>
    </div>
</div>
