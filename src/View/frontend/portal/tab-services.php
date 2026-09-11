<?php
/**
 * View: Member Portal – Services Tab (Group 3 Add-on Services)
 *
 * Available variables:
 *   @var int                                    $user_id
 *   @var \WP_User                               $user
 *   @var string                                 $user_type
 *   @var bool                                   $is_premium
 *   @var \Matchmaker\Repository\MatchRepository $repo
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$sync            = \Matchmaker\Core\PMProSync::instance();
$services        = $sync->get_services_levels();
$checkout_base   = \Matchmaker\Service\ProfileService::instance()->get_membership_checkout_url();
$level_tags      = $sync->get_level_tags();
?>
<div class="az-wrap mm-services-wrap">
    <?php if (empty($services)) : ?>
        <div class="az-card" style="text-align: center; padding: 40px 20px;">
            <p style="color: #64748b; margin: 0; font-size: 15px;">
                <?php esc_html_e('No active service packages are currently available. Please check back soon!', 'matchmaker'); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="mm-services-grid">
            <?php foreach ($services as $srv) : 
                $lvl_id = is_object($srv) ? (int) ($srv->id ?? 0) : (int) $srv;
                if ($lvl_id <= 0) {
                    continue;
                }

                $srv_name = is_object($srv) && !empty($srv->name) ? $srv->name : sprintf(__('Service Package #%d', 'matchmaker'), $lvl_id);
                $srv_desc = is_object($srv) && !empty($srv->description) ? $srv->description : '';
                
                // Formatted Price
                $srv_price = '';
                if (is_object($srv)) {
                    if (function_exists('pmpro_getLevelCost')) {
                        $srv_price = pmpro_getLevelCost($srv, true, false);
                    } elseif (isset($srv->initial_payment)) {
                        $initial = (float) $srv->initial_payment;
                        $srv_price = '$' . number_format($initial, 2);
                        if (!empty($srv->recurring)) {
                            $srv_price .= ' / ' . __('period', 'matchmaker');
                        } else {
                            $srv_price .= ' ' . __('one-time', 'matchmaker');
                        }
                    }
                }
                if (empty($srv_price)) {
                    $srv_price = __('One-Time Service', 'matchmaker');
                }

                // Check active ownership
                $is_active_for_user = false;
                if (function_exists('pmpro_hasMembershipLevel')) {
                    $is_active_for_user = pmpro_hasMembershipLevel($lvl_id, $user_id);
                } elseif ($sync->has_active_one_on_one_service($user_id) && in_array($lvl_id, [4, 5], true)) {
                    $is_active_for_user = true;
                }

                // Custom Tag
                $custom_tag = $level_tags[$lvl_id] ?? '';
                if (empty($custom_tag) && in_array($lvl_id, [4, 5], true)) {
                    $custom_tag = __('1-on-1 VIP', 'matchmaker');
                }

                // Checkout URL
                $checkout_url = function_exists('pmpro_url') 
                    ? pmpro_url('checkout', '?level=' . $lvl_id)
                    : add_query_arg('level', $lvl_id, $checkout_base);
            ?>
                <div class="az-card mm-service-card <?php echo $is_active_for_user ? 'mm-service-active' : ''; ?>">
                    <div class="mm-service-card-header">
                        <?php if (!empty($custom_tag)) : ?>
                            <span class="mm-service-tag-badge">★ <?php echo esc_html($custom_tag); ?></span>
                        <?php endif; ?>
                        <h3 class="mm-service-title"><?php echo esc_html($srv_name); ?></h3>
                        <div class="mm-service-price-pill">
                            <?php echo wp_kses_post($srv_price); ?>
                        </div>
                    </div>

                    <div class="mm-service-card-body">
                        <?php if (!empty($srv_desc)) : ?>
                            <div class="mm-service-description">
                                <?php echo wp_kses_post(wpautop($srv_desc)); ?>
                            </div>
                        <?php else : ?>
                            <p class="mm-service-description" style="color: #64748b; font-size: 13.5px; line-height: 1.6;">
                                <?php esc_html_e('Personalized dedicated matchmaking consultations and prioritized candidate reviews curated by our senior matchmaking team.', 'matchmaker'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="mm-service-card-footer">
                        <?php if ($is_active_for_user) : ?>
                            <span class="mm-service-active-pill">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <?php esc_html_e('Active Service', 'matchmaker'); ?>
                            </span>
                        <?php else : ?>
                            <a href="<?php echo esc_url($checkout_url); ?>" class="btn btn-primary mm-service-cta-btn">
                                <?php esc_html_e('Purchase Service →', 'matchmaker'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
