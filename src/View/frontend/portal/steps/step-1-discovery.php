<?php
/**
 * View: Member Portal – Matches Step 1 (Discovery & Active Match Card)
 *
 * Available variables:
 *   @var array<string, mixed> $active_match
 *   @var bool                 $is_mutual
 *   @var int                  $expiry_days
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- STATE 1: DASHBOARD DISCOVERY (STEP 1) -->
<div id="step-1" class="view-state <?php echo (($default_step ?? 1) === 1) ? 'active' : ''; ?>">
    <div class="dashboard-body">
        <main class="main-discovery-content" style="width:100%;">
            <div class="status-pill">★ <?php esc_html_e('Active Match Recommendation', 'matchmaker'); ?></div>
            <h1 class="discovery-title"><?php esc_html_e('You Have a New Match', 'matchmaker'); ?></h1>
            <p class="discovery-subtitle"><?php esc_html_e("We've found someone we think could be a meaningful match for you based on your shared values and requirements.", 'matchmaker'); ?></p>

            <div class="new-match-card">
                <div>
                    <div class="candidate-hero-block">
                        <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 150])); ?>" alt="<?php echo esc_attr($active_match['name']); ?>" class="candidate-square-thumb mm-lightbox-trigger" data-mm-lightbox="potential-match-photo" style="cursor:zoom-in;" title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>">
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
                    <?php if ($is_mutual) : ?>
                        <div>
                            <div style="font-family: 'Cormorant SC', serif; font-size: 15px; text-transform: uppercase; font-weight: 700; margin-bottom: 6px; color: #144D34;">
                                ★ <?php esc_html_e("It's a Match!", 'matchmaker'); ?>
                            </div>
                            <p><?php esc_html_e('Both of you have accepted the match! You can now view each other\'s direct contact details.', 'matchmaker'); ?></p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px;">
                            <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="navigate-step" data-step="5">
                                <?php esc_html_e('View Contact Details →', 'matchmaker'); ?>
                            </button>
                            <button type="button" class="btn btn-outline-dark" style="width: 100%;" data-mm-action="navigate-step" data-step="2">
                                <?php esc_html_e('View Full Profile', 'matchmaker'); ?>
                            </button>
                        </div>
                    <?php else : ?>
                        <div>
                            <div style="font-family: 'Cormorant SC', serif; font-size: 15px; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">
                                <?php
                                $cur_my_resp = strtolower((string) ($active_match['my_response'] ?? 'pending'));
                                if ($cur_my_resp === 'accepted') {
                                    esc_html_e('Match Accepted', 'matchmaker');
                                } elseif (in_array($cur_my_resp, ['declined', 'rejected'], true)) {
                                    esc_html_e('Match Declined', 'matchmaker');
                                } else {
                                    esc_html_e('Your Response', 'matchmaker');
                                }
                                ?>
                            </div>
                            <p>
                                <?php
                                if ($cur_my_resp === 'accepted') {
                                    esc_html_e("You've accepted this match. We're now waiting for the candidate to review and respond.", 'matchmaker');
                                } elseif (in_array($cur_my_resp, ['declined', 'rejected'], true)) {
                                    esc_html_e('You have declined this match recommendation.', 'matchmaker');
                                } else {
                                    echo esc_html(sprintf(__('Take your time to review this profile. You have %d days to accept or decline this match before it expires.', 'matchmaker'), $expiry_days));
                                }
                                ?>
                            </p>
                            <?php if ($cur_my_resp === 'pending') : ?>
                                <div class="timer-card">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <div>
                                        <div class="timer-title"><?php esc_html_e('Time Remaining', 'matchmaker'); ?></div>
                                        <div class="timer-val"><?php echo (int) $active_match['days_remaining']; ?> <?php esc_html_e('days remaining', 'matchmaker'); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px;">
                            <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="navigate-step" data-step="2">
                                <?php esc_html_e('View Match →', 'matchmaker'); ?>
                            </button>
                            <button type="button" class="btn btn-outline-dark" style="width: 100%;" data-mm-action="navigate-step" data-step="3">
                                <?php esc_html_e('View Status', 'matchmaker'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
