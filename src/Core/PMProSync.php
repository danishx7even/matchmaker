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
     * Default PMPro level ID to user_type mapping fallback.
     */
    public const DEFAULT_LEVEL_MAPPING = [
        3 => 'monthly',
        4 => 'one_on_one',
        5 => 'one_on_one',
        6 => 'event',
        2 => 'free',
    ];

    /**
     * @return self
     */
    /**
     * Tier priority rank (higher integer = higher priority).
     */
    public const TIER_PRIORITY = [
        'one_on_one' => 4,
        'monthly'    => 3,
        'event'      => 2,
        'free'       => 1,
    ];

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
        add_action('pmpro_after_all_membership_level_changes', [$this, 'sync_all_membership_levels'], 10, 1);
        add_action('pmpro_after_checkout', [$this, 'handle_checkout_sync'], 10, 2);
        add_action('pmpro_subscription_payment_completed', [$this, 'reset_user_quota_on_renewal'], 10, 1);
    }

    /**
     * Retrieves the active PMPro level ID to user_type mapping array.
     *
     * @return array<int, string>
     */
    public function get_tier_mapping(): array
    {
        $mapping = get_option('mm_pmpro_tier_mapping', null);
        if (is_array($mapping) && !empty($mapping)) {
            // Ensure integer keys
            $normalized = [];
            foreach ($mapping as $lvl => $tier) {
                if (is_numeric($lvl) && (int) $lvl > 0 && is_string($tier)) {
                    $normalized[(int) $lvl] = sanitize_key($tier);
                }
            }
            if (!empty($normalized)) {
                return $normalized;
            }
        }
        return self::DEFAULT_LEVEL_MAPPING;
    }

    /**
     * Maps PMPro level ID to a matchmaking user_type.
     *
     * @param int $level_id
     * @return string
     */
    public function get_user_type_by_level_id(int $level_id): string
    {
        $mapping = $this->get_tier_mapping();
        return $mapping[$level_id] ?? 'free';
    }

    /**
     * Returns all PMPro level IDs assigned to a given user_type tier.
     *
     * @param string $tier e.g. 'monthly', 'one_on_one', 'event', 'free'.
     * @return int[]
     */
    public function get_levels_for_tier(string $tier): array
    {
        $mapping = $this->get_tier_mapping();
        $levels  = [];
        foreach ($mapping as $lvl_id => $t) {
            if ($t === $tier) {
                $levels[] = (int) $lvl_id;
            }
        }
        return $levels;
    }

    /**
     * Check if a specific level ID belongs to a given tier.
     *
     * @param int    $level_id
     * @param string $tier
     * @return bool
     */
    public function is_tier_level(int $level_id, string $tier): bool
    {
        return $this->get_user_type_by_level_id($level_id) === $tier;
    }

    /**
     * Returns the primary (first) level ID for a given tier (e.g. for checkout links).
     *
     * @param string $tier
     * @param int    $default
     * @return int
     */
    public function get_primary_level_for_tier(string $tier, int $default = 3): int
    {
        $levels = $this->get_levels_for_tier($tier);
        return !empty($levels) ? $levels[0] : $default;
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

        $new_tier = $this->get_user_type_by_level_id($level_id);

        // If user upgraded to a paid tier, cancel any lingering Free tier level from separate PMPro level groups
        if (in_array($new_tier, ['monthly', 'one_on_one', 'event'], true)) {
            $this->maybe_cancel_free_levels($user_id);
        }

        $resolved_user_type = $this->get_current_user_type($user_id);
        $new_rank = self::TIER_PRIORITY[$new_tier] ?? 1;
        $resolved_rank = self::TIER_PRIORITY[$resolved_user_type] ?? 1;
        if ($new_rank > $resolved_rank) {
            $resolved_user_type = $new_tier;
        }
        
        \Matchmaker\Repository\MatchRepository::instance()->save_meta($user_id, 'user_type', $resolved_user_type);
        \Matchmaker\Repository\MatchRepository::instance()->update_pool_user_type($user_id, $resolved_user_type);

        if ($resolved_user_type === 'monthly') {
            $pool_user = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);
            if (!empty($pool_user)) {
                if (function_exists('mm_enqueue_user_matching_job')) {
                    mm_enqueue_user_matching_job($user_id, 'tier_upgrade');
                }
            }
        }
    }

    /**
     * Hook after all membership level changes (multi-group support).
     * PMPro passes an array of [$user_id => $old_levels] or array of user IDs.
     *
     * @param mixed $user_data
     * @return void
     */
    public function sync_all_membership_levels(mixed $user_data): void
    {
        $user_ids = [];

        if (is_numeric($user_data)) {
            $user_ids[] = (int) $user_data;
        } elseif (is_array($user_data)) {
            foreach ($user_data as $key => $val) {
                if (is_numeric($key) && (int) $key > 0) {
                    $user_ids[] = (int) $key;
                }
                if (is_numeric($val) && (int) $val > 0) {
                    $user_ids[] = (int) $val;
                }
            }
        }

        $user_ids = array_unique(array_filter($user_ids, static fn($id) => (int) $id > 0));

        foreach ($user_ids as $user_id) {
            $resolved_user_type = $this->get_current_user_type($user_id);

            if (in_array($resolved_user_type, ['monthly', 'one_on_one', 'event'], true)) {
                $this->maybe_cancel_free_levels($user_id);
            }

            \Matchmaker\Repository\MatchRepository::instance()->save_meta($user_id, 'user_type', $resolved_user_type);
            \Matchmaker\Repository\MatchRepository::instance()->update_pool_user_type($user_id, $resolved_user_type);

            if ($resolved_user_type === 'monthly') {
                $pool_user = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);
                if (!empty($pool_user)) {
                    if (function_exists('mm_enqueue_user_matching_job')) {
                        mm_enqueue_user_matching_job($user_id, 'tier_upgrade');
                    }
                }
            }
        }
    }

    /**
     * Hook on PMPro checkout completion.
     *
     * @param int $user_id
     * @param mixed $morder
     * @return void
     */
    public function handle_checkout_sync(int $user_id, mixed $morder = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        $this->sync_all_membership_levels($user_id);
    }

    /**
     * Cancels any active Free tier levels for a user if they hold a paid tier.
     *
     * @param int $user_id
     * @return void
     */
    public function maybe_cancel_free_levels(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        $free_levels = $this->get_levels_for_tier('free');
        if (empty($free_levels)) {
            $free_levels = [2];
        }

        if (function_exists('pmpro_getMembershipLevelsForUser')) {
            $active_levels = pmpro_getMembershipLevelsForUser($user_id);
            if (is_array($active_levels)) {
                foreach ($active_levels as $lvl) {
                    $lid = is_object($lvl) ? (int) ($lvl->id ?? 0) : (int) $lvl;
                    if (in_array($lid, $free_levels, true) && function_exists('pmpro_cancelMembershipLevel')) {
                        pmpro_cancelMembershipLevel($lid, $user_id);
                    }
                }
            }
        }
    }

    /**
     * Retrieves the current user_type with multi-group priority resolution.
     *
     * @param int $user_id
     * @return string
     */
    public function get_current_user_type(int $user_id): string
    {
        if ($user_id <= 0) {
            return 'free';
        }

        // 1. Check all active membership levels for user (supporting PMPro multiple level groups)
        if (function_exists('pmpro_getMembershipLevelsForUser')) {
            $levels = pmpro_getMembershipLevelsForUser($user_id);
            if (is_array($levels) && !empty($levels)) {
                $best_tier     = 'free';
                $highest_rank  = 0;

                foreach ($levels as $level_obj) {
                    $lvl_id = is_object($level_obj) ? (int) ($level_obj->id ?? 0) : (int) $level_obj;
                    if ($lvl_id > 0) {
                        $tier = $this->get_user_type_by_level_id($lvl_id);
                        $rank = self::TIER_PRIORITY[$tier] ?? 0;
                        if ($rank > $highest_rank) {
                            $highest_rank = $rank;
                            $best_tier    = $tier;
                        }
                    }
                }

                if ($highest_rank > 0) {
                    return $best_tier;
                }
            }
        }

        // 2. Fallback to single level getter
        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (!empty($membership->id)) {
                return $this->get_user_type_by_level_id((int) $membership->id);
            }
        }

        // 3. Fallback to usermeta
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
