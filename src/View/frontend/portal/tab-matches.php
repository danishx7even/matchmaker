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

$pmpro_url    = \Matchmaker\Service\ProfileService::instance()->get_membership_checkout_url();
$expiry_days  = \Matchmaker\Repository\MatchRepository::instance()->get_match_expiry_days();
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
    <?php endif; ?>
</div>
