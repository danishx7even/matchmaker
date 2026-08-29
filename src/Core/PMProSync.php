<?php
declare(strict_types=1);

namespace Matchmaker\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PMProSync
 *
 * Syncs PMPro membership levels to user_type properties.
 */
class PMProSync {
    private static ?self $instance = null;

    /**
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('pmpro_after_change_membership_level', [$this, 'sync_pmpro_level_to_user_type'], 10, 3);
        add_action('pmpro_subscription_payment_completed', [$this, 'reset_user_quota_on_renewal'], 10, 1);
    }

    /**
     * Maps PMPro level ID to a matchmaking user_type.
     *
     * @param int $level_id
     * @return string
     */
    public function get_user_type_by_level_id(int $level_id): string
    {
        return match ($level_id) {
            3       => 'monthly',
            4, 5    => 'one_on_one',
            6       => 'event',
            2       => 'free',
            default => 'free',
        };
    }

    /**
     * Syncs PMPro level changes to our system.
     *
     * @param int $level_id
     * @param int $user_id
     * @param int|null $old_level_id
     */
    public function sync_pmpro_level_to_user_type(int $level_id, int $user_id, ?int $old_level_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        $user_type = $this->get_user_type_by_level_id($level_id);
        
        \Matchmaker\Repository\MatchRepository::instance()->save_meta($user_id, 'user_type', $user_type);
        \Matchmaker\Repository\MatchRepository::instance()->update_pool_user_type($user_id, $user_type);

        if ($user_type === 'monthly') {
            $pool_user = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);
            if (!empty($pool_user)) {
                if (function_exists('mm_enqueue_user_matching_job')) {
                    mm_enqueue_user_matching_job($user_id, 'tier_upgrade');
                }
            }
        }
    }

    /**
     * Retrieves the current user_type.
     *
     * @param int $user_id
     * @return string
     */
    public function get_current_user_type(int $user_id): string
    {
        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (!empty($membership->id)) {
                return $this->get_user_type_by_level_id((int) $membership->id);
            }
        }

        $meta_type = (string) get_user_meta($user_id, 'user_type', true);
        return !empty($meta_type) ? $meta_type : 'free';
    }

    /**
     * Resets user quota counter to 0 on subscription renewal.
     *
     * @param int|object $user_or_order User ID or order object.
     * @return void
     */
    public function reset_user_quota_on_renewal(mixed $user_or_order): void
    {
        $user_id = 0;
        if (is_numeric($user_or_order)) {
            $user_id = (int) $user_or_order;
        } elseif (is_object($user_or_order) && !empty($user_or_order->user_id)) {
            $user_id = (int) $user_or_order->user_id;
        }

        if ($user_id > 0) {
            update_user_meta($user_id, 'cycle_matches_count', 0);
            update_user_meta($user_id, 'mm_cycle_month', gmdate('Y-m'));
        }
    }
}
