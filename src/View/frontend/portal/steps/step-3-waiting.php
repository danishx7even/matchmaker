<?php
/**
 * View: Member Portal – Matches Step 3 (Response Status & Waiting State)
 *
 * Available variables:
 *   @var array<string, mixed> $active_match
 *   @var string               $my_resp
 *   @var string               $their_resp
 *   @var int                  $default_step
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$my_resp           = strtolower((string) ($active_match['my_response'] ?? $my_resp ?? 'pending'));
$their_resp        = strtolower((string) ($active_match['their_response'] ?? $their_resp ?? 'pending'));
$match_status      = strtolower((string) ($active_match['status'] ?? 'approved'));

$my_is_accepted    = ($my_resp === 'accepted');
$my_is_declined    = in_array($my_resp, ['rejected', 'declined'], true);
$my_is_pending     = !$my_is_accepted && !$my_is_declined;

$their_is_accepted = ($their_resp === 'accepted');
$their_is_declined = in_array($their_resp, ['rejected', 'declined'], true);
$their_is_pending  = !$their_is_accepted && !$their_is_declined;

$is_mutual         = ($my_is_accepted && $their_is_accepted) || ($match_status === 'matched');
$is_expired        = ($match_status === 'expired') || ((int) ($active_match['days_remaining'] ?? 7) <= 0 && !$is_mutual);
?>
<!-- STATE 3: RESPONSE STATUS & WAITING STATE (STEP 3) -->
<div id="step-3" class="view-state <?php echo (($default_step ?? 1) === 3) ? 'active' : ''; ?>">
    <div style="padding: 24px 48px 0;">
        <button type="button" class="mm-back-btn" data-mm-action="goback-step" style="display: inline-flex; align-items: center; gap: 6px; background: none; border: none; font-size: 14px; font-weight: 600; color: #CC723F; cursor: pointer; padding: 0;">
            ← <?php esc_html_e('Back to Matches', 'matchmaker'); ?>
        </button>
    </div>
    <div class="centered-state-wrapper">
        <div class="status-avatar-bubble <?php echo ($my_is_declined || $their_is_declined) ? 'danger' : (($is_mutual || $my_is_accepted) ? 'orange' : 'neutral'); ?>" style="<?php echo ($my_is_pending && !$their_is_declined && !$is_expired) ? 'background-color: #f1f5f9; color: #CC723F;' : ''; ?>">
            <?php if ($is_mutual) : ?>
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php elseif ($my_is_declined || $their_is_declined) : ?>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?php elseif ($is_expired) : ?>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <?php elseif ($my_is_accepted) : ?>
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php else : ?>
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#CC723F" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <?php endif; ?>
        </div>

        <h1 class="state-main-title font-cormorant">
            <?php
            if ($is_mutual) {
                esc_html_e("It's a Mutual Match!", 'matchmaker');
            } elseif ($is_expired) {
                esc_html_e('Match Expired', 'matchmaker');
            } elseif ($my_is_declined) {
                esc_html_e('Match Declined by You', 'matchmaker');
            } elseif ($their_is_declined) {
                esc_html_e('Match Closed', 'matchmaker');
            } elseif ($my_is_accepted && $their_is_pending) {
                esc_html_e('Match Accepted', 'matchmaker');
            } elseif ($my_is_pending && $their_is_accepted) {
                esc_html_e('Candidate Accepted — Awaiting Your Response', 'matchmaker');
            } else {
                esc_html_e('Match Pending Your Review', 'matchmaker');
            }
            ?>
        </h1>
        <p class="state-main-desc">
            <?php
            if ($is_mutual) {
                esc_html_e("Both you and the candidate have accepted! You can now view each other's approved direct contact details.", 'matchmaker');
            } elseif ($is_expired) {
                esc_html_e('The response window for this match recommendation has ended.', 'matchmaker');
            } elseif ($my_is_declined) {
                esc_html_e('You have declined this match recommendation. This profile is now closed.', 'matchmaker');
            } elseif ($their_is_declined) {
                esc_html_e('The candidate was unable to proceed with this match recommendation at this time.', 'matchmaker');
            } elseif ($my_is_accepted && $their_is_pending) {
                esc_html_e("You've accepted this match. We're now waiting for the candidate to review and respond.", 'matchmaker');
            } elseif ($my_is_pending && $their_is_accepted) {
                esc_html_e('The candidate has accepted this match recommendation! Please review their profile and submit your response to connect.', 'matchmaker');
            } else {
                esc_html_e("You haven't responded to this match yet. Please review the candidate's profile and choose to accept or decline before the match expires.", 'matchmaker');
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
                <?php elseif ($is_expired) : ?>
                    <span class="res-tag-waiting">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php esc_html_e('Expired', 'matchmaker'); ?>
                    </span>
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
                <?php elseif ($is_expired) : ?>
                    <span class="res-tag-waiting">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php esc_html_e('Expired', 'matchmaker'); ?>
                    </span>
                <?php else : ?>
                    <span class="res-tag-waiting">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="8" y2="12"></line><line x1="12" y1="12" x2="12" y2="12"></line><line x1="16" y1="12" x2="16" y2="12"></line></svg>
                        <?php esc_html_e('Waiting', 'matchmaker'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="state-next-note">
            <h3 class="font-cormorant">
                <?php
                if ($is_mutual) {
                    esc_html_e('Ready to Connect!', 'matchmaker');
                } elseif ($is_expired || $my_is_declined || $their_is_declined) {
                    esc_html_e('Next Steps', 'matchmaker');
                } else {
                    esc_html_e("What's Next?", 'matchmaker');
                }
                ?>
            </h3>
            <p>
                <?php
                if ($is_mutual) {
                    esc_html_e('You can reach out directly via phone, email, or social media to begin your conversation.', 'matchmaker');
                } elseif ($is_expired) {
                    esc_html_e('Our matchmakers will prepare new match recommendations for your profile in the upcoming cycle.', 'matchmaker');
                } elseif ($my_is_declined || $their_is_declined) {
                    esc_html_e('Our matchmakers will continue curating fresh potential matches for you in your next matching cycle.', 'matchmaker');
                } elseif ($my_is_pending) {
                    esc_html_e("Review the candidate's profile. If both of you accept, you will instantly unlock each other's approved direct contact details.", 'matchmaker');
                } else {
                    esc_html_e("If they also accept, you'll both receive access to each other's approved direct contact information.", 'matchmaker');
                }
                ?>
            </p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; width: 100%;">
            <?php if ($is_mutual) : ?>
                <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="navigate-step" data-step="5">
                    <?php esc_html_e('View Contact Details →', 'matchmaker'); ?>
                </button>
            <?php elseif ($my_is_pending && !$is_expired && !$their_is_declined) : ?>
                <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="navigate-step" data-step="2">
                    <?php esc_html_e('Review Profile & Respond →', 'matchmaker'); ?>
                </button>
            <?php endif; ?>

            <button type="button" class="btn <?php echo ($is_mutual || ($my_is_pending && !$is_expired && !$their_is_declined)) ? 'btn-outline-dark' : 'btn-primary'; ?>" style="width: 100%;" data-mm-action="switch-tab" data-tab="profile">
                <?php esc_html_e('Back to Profile Dashboard →', 'matchmaker'); ?>
            </button>
        </div>
    </div>
</div>

