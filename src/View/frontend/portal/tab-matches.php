<?php
/**
 * View: Member Portal – Matches Tab
 *
 * Available variables:
 *   @var int                        $user_id
 *   @var string                     $user_type
 *   @var bool                       $is_premium
 *   @var array<int, array>          $matches
 *   @var array<int, array>|null     $match_history
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$pmpro_url       = \Matchmaker\Service\ProfileService::instance()->get_membership_checkout_url();
$expiry_days     = \Matchmaker\Repository\MatchRepository::instance()->get_match_expiry_days();
$active_match    = !empty($matches) ? $matches[0] : null;
$active_match_id = !empty($active_match['match_id']) ? (int) $active_match['match_id'] : 0;
$my_resp         = strtolower((string) ($active_match['my_response'] ?? 'pending'));
$their_resp      = strtolower((string) ($active_match['their_response'] ?? 'pending'));
$is_mutual       = ($my_resp === 'accepted' && $their_resp === 'accepted') || (($active_match['status'] ?? '') === 'matched');

if (!isset($match_history)) {
    $match_history = \Matchmaker\Repository\MatchRepository::instance()->find_match_history_for_user($user_id, $active_match_id);
}

$default_step = 1;
if ($is_mutual) {
    $default_step = 5;
} elseif ($my_resp === 'accepted' || in_array($my_resp, ['declined', 'rejected'], true)) {
    $default_step = 3;
}
?>
<div class="mm-flow-container">
    <?php if (empty($matches)) : ?>
        <div class="dashboard-body">
            <?php if (!$is_premium) : ?>
                <div class="az-card mm-upsell-card" style="margin-bottom:0;">
                    <div class="mm-upsell-badge">★ <?php esc_html_e('Monthly Membership Required', 'matchmaker'); ?></div>
                    <h2><?php esc_html_e('Unlock Your Hand-Picked Matches', 'matchmaker'); ?></h2>
                    <p><?php esc_html_e('You are currently on a Free membership. Upgrade to our Monthly Matchmaking plan to receive curated, bi-directionally compatible matches every cycle.', 'matchmaker'); ?></p>
                    <a href="<?php echo esc_url($pmpro_url); ?>" class="btn btn-primary mm-upsell-btn">
                        <?php esc_html_e('Get Monthly Membership →', 'matchmaker'); ?>
                    </a>
                </div>
            <?php else : ?>
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
            <?php endif; ?>
        </div>
    <?php else : ?>
        <?php
        // Step 1: Active match discovery card
        require __DIR__ . '/steps/step-1-discovery.php';

        // Step 2: Full potential match profile review & action dock
        require __DIR__ . '/steps/step-2-profile.php';

        // Step 3: Response status & waiting state
        require __DIR__ . '/steps/step-3-waiting.php';

        // Step 4: Decline confirmation modal/card
        require __DIR__ . '/steps/step-4-decline.php';

        // Step 5: Mutual match celebration & contact reveal
        require __DIR__ . '/steps/step-5-contact.php';
        ?>

        <?php if (!$is_premium) : ?>
            <div class="dashboard-body" style="margin-top: 24px;">
                <div class="az-card mm-upsell-card" style="margin-bottom:0;">
                    <div class="mm-upsell-badge">★ <?php esc_html_e('Upgrade to Monthly Membership', 'matchmaker'); ?></div>
                    <h2><?php esc_html_e('Enjoy Regular Curated Matches', 'matchmaker'); ?></h2>
                    <p><?php esc_html_e('You are reviewing a match as a Free member. Upgrade to our Monthly Matchmaking plan to receive guaranteed curated matches every single cycle.', 'matchmaker'); ?></p>
                    <a href="<?php echo esc_url($pmpro_url); ?>" class="btn btn-primary mm-upsell-btn">
                        <?php esc_html_e('Get Monthly Membership →', 'matchmaker'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($match_history)) : ?>
        <div class="mm-matches-history-section">
            <div class="mm-matches-history-card">
                <div class="mm-history-header">
                    <div class="mm-history-header-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <h3><?php esc_html_e('Match History', 'matchmaker'); ?></h3>
                    </div>
                    <span class="mm-history-count"><?php echo esc_html(sprintf(_n('%d Past Match', '%d Past Matches', count($match_history), 'matchmaker'), count($match_history))); ?></span>
                </div>
                <div class="mm-history-list">
                    <?php foreach ($match_history as $hist_item) : ?>
                        <div class="mm-history-item">
                            <div class="mm-history-avatar">
                                <?php if (!empty($hist_item['photo'])) : ?>
                                    <img src="<?php echo esc_url($hist_item['photo']); ?>" alt="<?php echo esc_attr($hist_item['name']); ?>" class="mm-history-photo" />
                                <?php else : ?>
                                    <div class="mm-history-photo-placeholder">
                                        <?php echo esc_html(strtoupper(substr($hist_item['name'] ?: 'M', 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mm-history-details">
                                <div class="mm-history-name-row">
                                    <h4 class="mm-history-name">
                                        <?php echo esc_html($hist_item['name']); ?><?php if (!empty($hist_item['age'])) : ?><span class="mm-history-age">, <?php echo (int) $hist_item['age']; ?></span><?php endif; ?>
                                    </h4>
                                </div>
                                <div class="mm-history-meta-row">
                                    <?php if (!empty($hist_item['location']) && $hist_item['location'] !== '—') : ?>
                                        <span class="mm-history-meta-item mm-history-location">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            <?php echo esc_html($hist_item['location']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($hist_item['date_formatted']) && $hist_item['date_formatted'] !== '—') : ?>
                                        <span class="mm-history-meta-item mm-history-date">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            <?php echo esc_html($hist_item['date_formatted']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mm-history-responses-row">
                                    <div class="mm-response-tag mm-response-tag-you">
                                        <span class="mm-response-tag-label"><?php esc_html_e('Your Response:', 'matchmaker'); ?></span>
                                        <span class="mm-response-tag-value <?php echo esc_attr($hist_item['my_response_class'] ?? 'mm-response-val-pending'); ?>">
                                            <?php echo esc_html($hist_item['my_response_label'] ?? ucfirst((string) ($hist_item['my_response'] ?? 'Pending'))); ?>
                                        </span>
                                    </div>
                                    <div class="mm-response-tag mm-response-tag-candidate">
                                        <span class="mm-response-tag-label"><?php esc_html_e('Candidate Response:', 'matchmaker'); ?></span>
                                        <span class="mm-response-tag-value <?php echo esc_attr($hist_item['their_response_class'] ?? 'mm-response-val-pending'); ?>">
                                            <?php echo esc_html($hist_item['their_response_label'] ?? ucfirst((string) ($hist_item['their_response'] ?? 'Pending'))); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($hist_item['is_mutual']) && (!empty($hist_item['phone_number']) || !empty($hist_item['user_social_links']) || !empty($hist_item['user_email']))) : ?>
                                    <div class="mm-history-contacts-wrap" style="margin-top:10px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; font-size:13px; color:#166534; display:flex; flex-wrap:wrap; align-items:center; gap:12px;">
                                        <span style="font-weight:700; color:#15803d; display:inline-flex; align-items:center; gap:4px;">
                                            🎉 <?php esc_html_e('Contact Revealed:', 'matchmaker'); ?>
                                        </span>
                                        <?php if (!empty($hist_item['phone_number'])) : ?>
                                            <span style="display:inline-flex; align-items:center; gap:4px;">
                                                📞 <strong><?php echo esc_html($hist_item['phone_number']); ?></strong>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($hist_item['user_email'])) : ?>
                                            <span style="display:inline-flex; align-items:center; gap:4px;">
                                                ✉️ <a href="mailto:<?php echo esc_attr($hist_item['user_email']); ?>" style="color:#15803d; text-decoration:underline; font-weight:600;"><?php echo esc_html($hist_item['user_email']); ?></a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($hist_item['user_social_links'])) : ?>
                                            <span style="display:inline-flex; align-items:center; gap:4px;">
                                                🔗 <?php echo \Matchmaker\Repository\MatchRepository::instance()->format_social_links_html($hist_item['user_social_links']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mm-history-status">
                                <span class="mm-history-badge <?php echo esc_attr($hist_item['status_class'] ?? 'mm-history-badge-approved'); ?>">
                                    <?php echo esc_html($hist_item['status_label'] ?? __('Approved', 'matchmaker')); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
