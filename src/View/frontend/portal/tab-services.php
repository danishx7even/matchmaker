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
        <div class="mm-services-grid" style="display:grid !important; grid-template-columns:repeat(2, minmax(0, 1fr)) !important; gap:24px !important; width:100% !important; align-items:stretch !important;">
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
                <div class="az-card mm-service-card <?php echo $is_active_for_user ? 'mm-service-active' : ''; ?>" style="display:flex !important; flex-direction:column !important; justify-content:space-between !important; align-items:flex-start !important; text-align:left !important; height:100% !important; margin:0 !important; box-sizing:border-box !important;">
                    <div class="mm-service-card-header" style="text-align:left !important; align-items:flex-start !important; width:100% !important; display:flex !important; flex-direction:column !important; margin-bottom:14px !important;">
                        <?php if (!empty($custom_tag)) : ?>
                            <span class="mm-service-tag-badge" style="align-self:flex-start !important; margin-bottom:10px !important;">★ <?php echo esc_html($custom_tag); ?></span>
                        <?php endif; ?>
                        <h3 class="mm-service-title" style="text-align:left !important; margin:0 0 8px 0 !important; width:100% !important;"><?php echo esc_html($srv_name); ?></h3>
                        <div class="mm-service-price-pill" style="text-align:left !important; align-self:flex-start !important; margin-bottom:14px !important;">
                            <?php echo wp_kses_post($srv_price); ?>
                        </div>
                    </div>

                    <div class="mm-service-card-body" style="text-align:left !important; width:100% !important; flex:1 1 auto !important; margin-bottom:24px !important;">
                        <?php if (!empty($srv_desc)) : ?>
                            <div class="mm-service-description" style="text-align:left !important; margin:0 !important;">
                                <?php echo wp_kses_post(wpautop($srv_desc)); ?>
                            </div>
                        <?php else : ?>
                            <p class="mm-service-description" style="color: #64748b; font-size: 13.5px; line-height: 1.6; text-align:left !important; margin:0 !important;">
                                <?php esc_html_e('Personalized dedicated matchmaking consultations and prioritized candidate reviews curated by our senior matchmaking team.', 'matchmaker'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="mm-service-card-footer" style="margin-top:auto !important; width:100% !important; align-self:stretch !important; display:flex !important; flex-direction:column !important; gap:8px !important; align-items:stretch !important;">
                        <?php if ($is_active_for_user) : ?>
                            <div style="display:flex; align-items:center; justify-content:center; gap:5px; font-size:12px; font-weight:600; color:#15803d; background:#dcfce7; padding:6px 12px; border-radius:6px; width:100%; box-sizing:border-box;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <?php esc_html_e('Purchased / Active', 'matchmaker'); ?>
                            </div>
                            <a href="<?php echo esc_url($checkout_url); ?>" class="btn btn-primary mm-service-cta-btn" style="text-align:center !important; width:100% !important;">
                                <?php esc_html_e('Purchase Again →', 'matchmaker'); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url($checkout_url); ?>" class="btn btn-primary mm-service-cta-btn" style="text-align:center !important; width:100% !important;">
                                <?php esc_html_e('Purchase Service →', 'matchmaker'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
