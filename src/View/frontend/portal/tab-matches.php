<?php
/**
 * View: Member Portal – Matches Tab
 *
 * Available variables:
 *   @var int                        $user_id
 *   @var string                     $user_type
 *   @var bool                       $is_premium
 *   @var array<int, array>          $matches
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$pmpro_url    = home_url('/membership-checkout/?pmpro_level=3');
$active_match = !empty($matches) ? $matches[0] : null;
$my_resp      = strtolower((string) ($active_match['my_response'] ?? 'pending'));
$their_resp   = strtolower((string) ($active_match['their_response'] ?? 'pending'));
$is_mutual    = ($my_resp === 'accepted' && $their_resp === 'accepted') || (($active_match['status'] ?? '') === 'matched');
?>
<div class="mm-flow-container">
    <?php if (!$is_premium) : ?>
        <div class="dashboard-body">
            <div class="az-card mm-upsell-card" style="margin-bottom:0;">
                <div class="mm-upsell-badge">★ <?php esc_html_e('Monthly Membership Required', 'matchmaker'); ?></div>
                <h2><?php esc_html_e('Unlock Your Hand-Picked Matches', 'matchmaker'); ?></h2>
                <p><?php esc_html_e('You are currently on a Free or Event membership. Upgrade to our Monthly Matchmaking plan to receive curated, bi-directionally compatible matches every cycle.', 'matchmaker'); ?></p>
                <a href="<?php echo esc_url($pmpro_url); ?>" class="btn btn-primary mm-upsell-btn">
                    <?php esc_html_e('Get Monthly Membership →', 'matchmaker'); ?>
                </a>
            </div>
        </div>
    <?php elseif (empty($matches)) : ?>
        <div class="dashboard-body">
            <div class="az-card" style="margin-bottom:0; text-align:center; padding:48px 24px;">
                <div style="font-size:42px; margin-bottom:12px;">✨</div>
                <h2 style="font-family:'Cormorant SC', serif; font-size:24px; font-weight:700; color:#1e293b; margin-bottom:10px;">
                    <?php esc_html_e('Hand-Curating Your Next Match', 'matchmaker'); ?>
                </h2>
                <p style="max-width:540px; margin:0 auto 18px; color:#64748b; font-size:15px; line-height:1.6;">
                    <?php esc_html_e('Our expert matchmakers are currently searching and hand-curating the best profile matching your criteria. As soon as your next match is ready, we will notify you via email!', 'matchmaker'); ?>
                </p>
                <span class="status-pill" style="display:inline-block; background:#f1f5f9; color:#475569; font-weight:600; padding:6px 14px; border-radius:20px; font-size:13px;">
                    ⌛ <?php esc_html_e('Status: In Matchmaker Review Queue', 'matchmaker'); ?>
                </span>
            </div>
        </div>
    <?php else : ?>
        <!-- STATE 1: DASHBOARD DISCOVERY (STEP 1) -->
        <div id="step-1" class="view-state active">
            <div class="dashboard-body">
                <main class="main-discovery-content" style="width:100%;">
                    <div class="status-pill">★ <?php esc_html_e('Active Match Recommendation', 'matchmaker'); ?></div>
                    <h1 class="discovery-title"><?php esc_html_e('You Have a New Match', 'matchmaker'); ?></h1>
                    <p class="discovery-subtitle"><?php esc_html_e("We've found someone we think could be a meaningful match for you based on your shared values and requirements.", 'matchmaker'); ?></p>

                    <div class="new-match-card">
                        <div>
                            <div class="candidate-hero-block">
                                <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 150])); ?>" alt="" class="candidate-square-thumb">
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
                            <?php else : ?>
                                <div>
                                    <div style="font-family: 'Cormorant SC', serif; font-size: 15px; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">
                                        <?php esc_html_e('Your Response', 'matchmaker'); ?>
                                    </div>
                                    <p><?php esc_html_e('Take your time to review this profile. You have 7 days to accept or decline this match before it expires.', 'matchmaker'); ?></p>
                                    <div class="timer-card">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        <div>
                                            <div class="timer-title"><?php esc_html_e('Time Remaining', 'matchmaker'); ?></div>
                                            <div class="timer-val"><?php echo (int) $active_match['days_remaining']; ?> <?php esc_html_e('days remaining', 'matchmaker'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px;">
                                <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="navigate-step" data-step="2">
                                    <?php esc_html_e('View Match →', 'matchmaker'); ?>
                                </button>
                                <button type="button" class="btn btn-outline-dark" style="width: 100%;" data-mm-action="navigate-step" data-step="3">
                                    <?php esc_html_e('View Status', 'matchmaker'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

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

        <!-- STATE 3: ACCEPTED — WAITING FOR RESPONSE (STEP 3) -->
        <div id="step-3" class="view-state">
            <div style="padding: 24px 48px 0;">
                <button type="button" class="mm-back-btn" data-mm-action="goback-step" style="display: inline-flex; align-items: center; gap: 6px; background: none; border: none; font-size: 14px; font-weight: 600; color: #CC723F; cursor: pointer; padding: 0;">
                    ← <?php esc_html_e('Back to Matches', 'matchmaker'); ?>
                </button>
            </div>
            <div class="centered-state-wrapper">
                <?php
                $my_resp    = strtolower((string) ($active_match['my_response'] ?? 'pending'));
                $their_resp = strtolower((string) ($active_match['their_response'] ?? 'pending'));

                $my_is_accepted    = ($my_resp === 'accepted');
                $my_is_declined    = in_array($my_resp, ['rejected', 'declined'], true);
                $their_is_accepted = ($their_resp === 'accepted');
                $their_is_declined = in_array($their_resp, ['rejected', 'declined'], true);
                ?>

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

        <!-- STATE 5: MUTUAL MATCH — REVEALED CONTACT INFO (STEP 5) -->
        <div id="step-5" class="view-state">
            <div class="centered-state-wrapper" style="max-width: 620px;">
                <div class="status-avatar-bubble orange" style="background: linear-gradient(135deg, #CC723F 0%, #E89158 100%); width: 76px; height: 76px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(204, 114, 63, 0.35); margin: 0 auto 20px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m2 22 5.8-10.7 8.12 8.12L2 22Z"/>
                        <path d="M17.13 3.29a2 2 0 0 1 2.83 0l.75.75a2 2 0 0 1 0 2.83l-8.12 8.12-3.58-3.58 8.12-8.12Z"/>
                        <path d="m15 5 2 2"/>
                        <circle cx="18" cy="14" r="1.2" fill="#ffffff"/>
                        <circle cx="12" cy="5" r="1.2" fill="#ffffff"/>
                        <circle cx="21" cy="9" r="1.2" fill="#ffffff"/>
                        <path d="M10 2 9 3.5"/>
                        <path d="M15 1.5 16 3"/>
                        <path d="M22 6 20.5 7"/>
                    </svg>
                </div>

                <h1 class="state-main-title font-cormorant" style="color: #144D34;"><?php esc_html_e("It's a Match!", 'matchmaker'); ?></h1>
                <p class="state-main-desc"><?php esc_html_e('You both accepted the match. Now you can connect directly outside Arab Zawaj.', 'matchmaker'); ?></p>

                <div class="matched-profile-summary-box">
                    <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 150])); ?>" alt="">
                    <div>
                        <h2 class="font-cormorant"><?php echo esc_html($active_match['name']); ?></h2>
                        <div class="meta-sub"><?php echo esc_html($active_match['age']); ?> • <?php echo esc_html($active_match['location']); ?></div>
                        <span class="premium-tag-gold">★ <?php esc_html_e('Mutual Match Accepted', 'matchmaker'); ?></span>
                    </div>
                </div>

                <div style="text-align: left; font-family: 'Cormorant SC', serif; font-size: 18px; text-transform: uppercase; font-weight: 700; margin-bottom: 14px;">
                    <?php esc_html_e('Direct Contact Information', 'matchmaker'); ?>
                </div>

                <div class="contacts-grid">
                    <div class="contact-entry-field">
                        <div class="contact-icon-bubble">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </div>
                        <div class="contact-data-text">
                            <div class="label"><?php esc_html_e('Phone', 'matchmaker'); ?></div>
                            <div class="val"><?php echo esc_html($active_match['phone_number'] ?: '—'); ?></div>
                        </div>
                    </div>

                    <div class="contact-entry-field">
                        <div class="contact-icon-bubble">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path></svg>
                        </div>
                        <div class="contact-data-text">
                            <div class="label"><?php esc_html_e('Email', 'matchmaker'); ?></div>
                            <div class="val"><?php echo esc_html($active_match['email'] ?: '—'); ?></div>
                        </div>
                    </div>

                    <div class="contact-entry-field full">
                        <div class="contact-icon-bubble">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path></svg>
                        </div>
                        <div class="contact-data-text">
                            <div class="label"><?php esc_html_e('Social / Handle', 'matchmaker'); ?></div>
                            <div class="val"><?php echo esc_html($active_match['social_links'] ?: '—'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="notice-gray-box">
                    <strong>ⓘ <?php esc_html_e('Important Note:', 'matchmaker'); ?></strong> <?php esc_html_e('Our platform does not provide internal chat messaging. You can now contact each other directly using the information above. Please communicate respectfully.', 'matchmaker'); ?>
                </div>

                <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="switch-tab" data-tab="profile">
                    <?php esc_html_e('Back to Profile Dashboard →', 'matchmaker'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>
