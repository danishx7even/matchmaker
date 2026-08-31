<?php
/**
 * View: Member Portal – Matches Step 2 (Full Potential Match Profile Review)
 *
 * Available variables:
 *   @var array<string, mixed> $active_match
 *   @var string               $my_resp
 *   @var bool                 $is_mutual
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- STATE 2: FULL PROFILE REVIEW (STEP 2) -->
<div id="step-2" class="view-state">
    <div style="padding: 24px 48px 0;">
        <button type="button" class="mm-back-btn" data-mm-action="goback-step" style="display: inline-flex; align-items: center; gap: 6px; background: none; border: none; font-size: 14px; font-weight: 600; color: #CC723F; cursor: pointer; padding: 0;">
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

    <!-- Footer Action Dock -->
    <footer class="floating-action-footer">
        <?php if ($my_resp === 'pending') : ?>
            <div class="footer-cta-prompt">
                <h4 class="font-cormorant"><?php esc_html_e('What do you think?', 'matchmaker'); ?></h4>
                <p><?php esc_html_e('You have 7 days to respond to this match.', 'matchmaker'); ?></p>
            </div>
            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline-dark" data-mm-action="navigate-step" data-step="4">
                    <?php esc_html_e('Decline Match', 'matchmaker'); ?>
                </button>
                <button type="button" class="btn btn-primary" data-mm-action="submit-response" data-match-id="<?php echo (int) $active_match['match_id']; ?>" data-decision="accept">
                    <?php esc_html_e('Accept Match →', 'matchmaker'); ?>
                </button>
            </div>
        <?php elseif ($is_mutual) : ?>
            <div class="footer-cta-prompt">
                <h4 class="font-cormorant"><?php esc_html_e("It's a Match!", 'matchmaker'); ?></h4>
                <p><?php esc_html_e('Both of you have accepted the match.', 'matchmaker'); ?></p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-mm-action="navigate-step" data-step="5">
                    <?php esc_html_e('View Contact Details →', 'matchmaker'); ?>
                </button>
            </div>
        <?php else : ?>
            <div class="footer-cta-prompt">
                <h4 class="font-cormorant"><?php esc_html_e('Response Submitted', 'matchmaker'); ?></h4>
                <p><?php esc_html_e('You have already submitted your response for this match.', 'matchmaker'); ?></p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-mm-action="navigate-step" data-step="3">
                    <?php esc_html_e('View Status →', 'matchmaker'); ?>
                </button>
            </div>
        <?php endif; ?>
    </footer>
</div>
