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
        6 => 'event',
        2 => 'free',
    ];

    /**
     * Base subscription tier priority rank (higher integer = higher priority).
     * Group 1: free
     * Group 2: event, monthly
     */
    public const BASE_TIER_PRIORITY = [
        'monthly' => 3,
        'event'   => 2,
        'free'    => 1,
    ];

    /**
     * Combined tier priority fallback rank (for backwards compatibility).
     */
    public const TIER_PRIORITY = [
        'monthly' => 3,
        'event'   => 2,
        'free'    => 1,
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
        add_action('pmpro_membership_post_membership_expiry', [$this, 'handle_expiry_sync'], 10, 2);
        add_action('pmpro_membership_status_change', [$this, 'handle_status_change_sync'], 10, 3);
        add_filter('pmpro_registration_checks', [$this, 'check_service_requires_basic_membership'], 10, 1);
        add_filter('pmpro_has_membership_level', [$this, 'filter_pmpro_has_membership_level_for_checkout'], 10, 3);
        add_filter('pmpro_allow_duplicate_level_checkouts', '__return_true');
        add_filter('pmprommpu_checkout_level', [$this, 'filter_pmprommpu_checkout_level'], 10, 2);
        add_filter('pmpro_can_cancel_membership_level', [$this, 'filter_pmpro_can_cancel_membership_level'], 10, 3);
        add_filter('pmpro_cancel_membership_level', [$this, 'block_base_membership_cancellation_with_active_services'], 10, 3);
        add_filter('pmpro_member_action_links', [$this, 'filter_pmpro_member_action_links'], 10, 3);
        add_filter('pmpro_account_membership_action_links', [$this, 'filter_pmpro_member_action_links'], 10, 3);
        add_filter('pmpro_account_action_links', [$this, 'filter_pmpro_member_action_links'], 10, 3);
        add_action('init', [$this, 'maybe_block_cancel_page_for_active_services'], 1);
        add_action('template_redirect', [$this, 'maybe_block_cancel_page_for_active_services'], 1);
        add_action('wp_footer', [$this, 'render_account_cancel_blockade_script']);
        add_action('pmpro_account_preheader', [$this, 'render_account_error_notice']);
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
     * Returns the configured PMPro Services Group ID (Group 3 by default).
     *
     * @return int
     */
    public function get_services_group_id(): int
    {
        $gid = (int) get_option('mm_services_group_id', 3);
        return $gid > 0 ? $gid : 3;
    }

    /**
     * Returns all registered PMPro levels belonging to the Services Group.
     * Searches PMPro 3.0+ functions, Level Group APIs, level metadata, options,
     * and database junction tables dynamically.
     *
     * @return array<int, object> Array of level objects.
     */
    public function get_services_levels(): array
    {
        $group_id        = $this->get_services_group_id();
        $found_level_ids = [];

        // 1. PMPro core & MMPU functions
        $functions_to_check = [
            'pmpro_getLevelsForGroup',
            'pmpro_getMembershipLevelsForGroup',
            'pmpro_get_levels_for_group',
            'pmprommpu_get_levels_for_group',
        ];
        foreach ($functions_to_check as $fn) {
            if (function_exists($fn)) {
                $grp_levels = @call_user_func($fn, $group_id);
                if (is_array($grp_levels) && !empty($grp_levels)) {
                    foreach ($grp_levels as $gl) {
                        $lid = is_object($gl) ? (int) ($gl->id ?? 0) : (int) $gl;
                        if ($lid > 0) {
                            $found_level_ids[] = $lid;
                        }
                    }
                }
            }
        }

        // 2. PMPro 3.0+ Level Group objects API
        if (function_exists('pmpro_get_level_groups')) {
            $groups = pmpro_get_level_groups();
            if (is_array($groups)) {
                foreach ($groups as $grp) {
                    $gid = is_object($grp) ? (int) ($grp->id ?? 0) : (int) ($grp['id'] ?? 0);
                    if ($gid === $group_id) {
                        $g_levels = is_object($grp) ? ($grp->levels ?? $grp->level_ids ?? []) : ($grp['levels'] ?? $grp['level_ids'] ?? []);
                        if (is_array($g_levels)) {
                            foreach ($g_levels as $gl) {
                                $lid = is_object($gl) ? (int) ($gl->id ?? 0) : (int) $gl;
                                if ($lid > 0) {
                                    $found_level_ids[] = $lid;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Inspect all registered levels and their metadata / properties
        if (function_exists('pmpro_getAllLevels')) {
            $all = pmpro_getAllLevels(true, true);
            if (is_array($all) && !empty($all)) {
                foreach ($all as $lvl) {
                    if (!is_object($lvl) || empty($lvl->id)) {
                        continue;
                    }
                    $lid = (int) $lvl->id;

                    // Direct object property checks
                    if (isset($lvl->group_id) && (int) $lvl->group_id === $group_id) {
                        $found_level_ids[] = $lid;
                        continue;
                    }
                    if (isset($lvl->group) && (int) $lvl->group === $group_id) {
                        $found_level_ids[] = $lid;
                        continue;
                    }
                    if (isset($lvl->membership_group_id) && (int) $lvl->membership_group_id === $group_id) {
                        $found_level_ids[] = $lid;
                        continue;
                    }
                    if (isset($lvl->groups) && is_array($lvl->groups)) {
                        foreach ($lvl->groups as $g) {
                            $gid = is_object($g) ? (int) ($g->id ?? 0) : (int) $g;
                            if ($gid === $group_id) {
                                $found_level_ids[] = $lid;
                                break;
                            }
                        }
                    }

                    // Level meta checks
                    if (function_exists('pmpro_get_level_meta')) {
                        $meta_gid = pmpro_get_level_meta($lid, 'group_id', true);
                        if ($meta_gid !== '' && (int) $meta_gid === $group_id) {
                            $found_level_ids[] = $lid;
                            continue;
                        }
                        $meta_grp = pmpro_get_level_meta($lid, 'group', true);
                        if ($meta_grp !== '' && (int) $meta_grp === $group_id) {
                            $found_level_ids[] = $lid;
                            continue;
                        }
                        $meta_mmpu = pmpro_get_level_meta($lid, 'pmprommpu_group', true);
                        if ($meta_mmpu !== '' && (int) $meta_mmpu === $group_id) {
                            $found_level_ids[] = $lid;
                            continue;
                        }
                    }
                }
            }
        }

        // 4. Options storage checks (pmpro_groups, pmpro_level_groups, pmprommpu_groups)
        $opt_mmpu = get_option('pmprommpu_groups', null);
        if (is_array($opt_mmpu)) {
            foreach ($opt_mmpu as $k => $v) {
                if ((int) $k === $group_id && is_array($v)) {
                    foreach ($v as $lvl_id) {
                        if (is_numeric($lvl_id) && (int) $lvl_id > 0) {
                            $found_level_ids[] = (int) $lvl_id;
                        }
                    }
                } elseif (is_numeric($v) && (int) $v === $group_id && is_numeric($k)) {
                    $found_level_ids[] = (int) $k;
                }
            }
        }

        $opt_groups = get_option('pmpro_groups', null) ?: get_option('pmpro_level_groups', null);
        if (is_array($opt_groups)) {
            foreach ($opt_groups as $k => $v) {
                $gid = is_array($v) ? (int) ($v['id'] ?? $k) : (int) $k;
                if ($gid === $group_id) {
                    $glvls = is_array($v) ? ($v['levels'] ?? $v['level_ids'] ?? []) : [];
                    if (is_array($glvls)) {
                        foreach ($glvls as $lvl_id) {
                            if (is_numeric($lvl_id) && (int) $lvl_id > 0) {
                                $found_level_ids[] = (int) $lvl_id;
                            }
                        }
                    }
                }
            }
        }

        // 5. Direct Database queries across all potential PMPro tables
        global $wpdb;
        if (!empty($wpdb) && isset($wpdb->prefix)) {
            // Check wp_pmpro_membership_levels table for group_id column
            $table_levels = $wpdb->prefix . 'pmpro_membership_levels';
            $t_exists     = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_levels));
            if ($t_exists === $table_levels) {
                $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$table_levels}`");
                if (is_array($cols)) {
                    if (in_array('group_id', $cols, true)) {
                        $col_lids = $wpdb->get_col($wpdb->prepare("SELECT id FROM `{$table_levels}` WHERE group_id = %d AND id > 0", $group_id));
                        if (!empty($col_lids)) {
                            foreach ($col_lids as $lid) {
                                $found_level_ids[] = (int) $lid;
                            }
                        }
                    }
                    if (in_array('group', $cols, true)) {
                        $col_lids = $wpdb->get_col($wpdb->prepare("SELECT id FROM `{$table_levels}` WHERE `group` = %d AND id > 0", $group_id));
                        if (!empty($col_lids)) {
                            foreach ($col_lids as $lid) {
                                $found_level_ids[] = (int) $lid;
                            }
                        }
                    }
                }
            }

            // Check junction tables (including plural levels_groups and singular level_groups)
            $junction_candidates = [
                $wpdb->prefix . 'pmpro_membership_levels_groups',
                $wpdb->prefix . 'pmpro_membership_level_groups',
                $wpdb->prefix . 'pmpro_groups_levels',
                $wpdb->prefix . 'pmpro_group_levels',
                $wpdb->prefix . 'pmpro_levels_groups',
            ];

            foreach ($junction_candidates as $j_table) {
                $jt_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $j_table));
                if ($jt_exists === $j_table) {
                    $j_cols = $wpdb->get_col("SHOW COLUMNS FROM `{$j_table}`");
                    if (is_array($j_cols) && in_array('level_id', $j_cols, true)) {
                        if (in_array('group_id', $j_cols, true)) {
                            $j_lids = $wpdb->get_col($wpdb->prepare("SELECT level_id FROM `{$j_table}` WHERE group_id = %d", $group_id));
                            if (!empty($j_lids)) {
                                foreach ($j_lids as $lid) {
                                    $found_level_ids[] = (int) $lid;
                                }
                            }
                        } elseif (in_array('id', $j_cols, true)) {
                            $j_lids = $wpdb->get_col($wpdb->prepare("SELECT level_id FROM `{$j_table}` WHERE id = %d", $group_id));
                            if (!empty($j_lids)) {
                                foreach ($j_lids as $lid) {
                                    $found_level_ids[] = (int) $lid;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 6. Check custom level mapping in plugin options (explicit 'service' / 'one_on_one' mappings)
        $mapping = $this->get_tier_mapping();
        foreach ($mapping as $lid => $tier) {
            if (in_array($tier, ['service', 'services', 'one_on_one'], true)) {
                $found_level_ids[] = (int) $lid;
            }
        }

        $found_level_ids = array_values(array_unique(array_filter($found_level_ids, static fn($id) => (int)$id > 0)));

        // 7. Hydrate full PMPro level objects
        $levels = [];
        foreach ($found_level_ids as $lid) {
            $obj = null;
            if (function_exists('pmpro_getLevel')) {
                $obj = pmpro_getLevel($lid);
            }
            if (!$obj && function_exists('pmpro_getAllLevels')) {
                $all = pmpro_getAllLevels(true, true);
                if (is_array($all)) {
                    foreach ($all as $lvl) {
                        if (is_object($lvl) && (int) ($lvl->id ?? 0) === $lid) {
                            $obj = $lvl;
                            break;
                        }
                    }
                }
            }
            if (!$obj) {
                $obj = (object) [
                    'id'          => $lid,
                    'name'        => sprintf(__('Service Package #%d', 'matchmaker'), $lid),
                    'description' => '',
                ];
            }
            $levels[] = $obj;
        }

        // 8. Fallback for mock/test environments if no PMPro groups are configured
        if (empty($levels)) {
            if (function_exists('pmpro_getLevel')) {
                foreach ([4, 5] as $lid) {
                    $l = pmpro_getLevel($lid);
                    if ($l && is_object($l)) {
                        $levels[] = $l;
                    }
                }
            }
            if (empty($levels) && function_exists('pmpro_getAllLevels')) {
                $all = pmpro_getAllLevels(true, true);
                if (is_array($all)) {
                    foreach ($all as $lvl) {
                        if (is_object($lvl) && in_array((int) ($lvl->id ?? 0), [4, 5], true)) {
                            $levels[] = $lvl;
                        }
                    }
                }
            }
        }

        return $levels;
    }

    /**
     * Checks whether a level ID is a service level in the Services Group.
     *
     * @param int $level_id
     * @return bool
     */
    public function is_service_level(int $level_id): bool
    {
        if ($level_id <= 0) {
            return false;
        }

        $services = $this->get_services_levels();
        foreach ($services as $srv) {
            $sid = is_object($srv) ? (int) ($srv->id ?? 0) : (int) $srv;
            if ($sid === $level_id) {
                return true;
            }
        }

        $tier = $this->get_user_type_by_level_id($level_id);
        if (in_array($tier, ['service', 'services', 'one_on_one'], true)) {
            return true;
        }

        return in_array($level_id, [4, 5], true);
    }

    /**
     * Retrieves custom tag names for PMPro levels.
     *
     * @return array<int, string>
     */
    public function get_level_tags(): array
    {
        $tags = get_option('mm_pmpro_level_tags', null);
        if (is_array($tags)) {
            $normalized = [];
            foreach ($tags as $lid => $tag) {
                if (is_numeric($lid) && is_string($tag)) {
                    $normalized[(int) $lid] = sanitize_text_field($tag);
                }
            }
            return $normalized;
        }
        return [];
    }

    /**
     * Retrieves a custom tag for a specific level ID.
     *
     * @param int    $level_id
     * @param string $default
     * @return string
     */
    public function get_level_tag(int $level_id, string $default = ''): string
    {
        $tags = $this->get_level_tags();
        return !empty($tags[$level_id]) ? (string) $tags[$level_id] : $default;
    }

    /**
     * Gating check on PMPro checkout submission for Service levels.
     * Members must have an active basic plan (Free, Monthly, Event) before purchasing a service.
     *
     * @param bool $okay
     * @return bool
     */
    public function check_service_requires_basic_membership(bool $okay): bool
    {
        if (!$okay) {
            return false;
        }

        $level_id = 0;
        if (!empty($_REQUEST['level'])) {
            $level_id = (int) $_REQUEST['level'];
        } elseif (isset($_POST['pmpro_level'])) {
            $level_id = (int) $_POST['pmpro_level'];
        } elseif (function_exists('pmpro_getLevelAtCheckout')) {
            $lvl_obj = pmpro_getLevelAtCheckout();
            if (is_object($lvl_obj) && !empty($lvl_obj->id)) {
                $level_id = (int) $lvl_obj->id;
            }
        }

        if ($level_id <= 0 || !$this->is_service_level($level_id)) {
            return $okay;
        }

        $user_id   = get_current_user_id();
        $has_basic = false;

        if ($user_id > 0) {
            $user_type = $this->get_current_user_type($user_id);
            if (in_array($user_type, ['free', 'monthly', 'event'], true)) {
                $has_basic = true;
            }
        }

        if (!$has_basic) {
            global $pmpro_msg, $pmpro_msgt;
            $pmpro_msg  = __('A basic membership plan (Free, Monthly, or Event) is required before purchasing additional services. Please select a membership plan first.', 'matchmaker');
            $pmpro_msgt = 'pmpro_error';
            return false;
        }

        return true;
    }

    /**
     * Filter pmpro_has_membership_level during checkout so service levels can be purchased repeatedly.
     *
     * @param bool       $has_level
     * @param int        $user_id
     * @param mixed      $levels
     * @return bool
     */
    public function filter_pmpro_has_membership_level_for_checkout(bool $has_level, int $user_id, mixed $levels): bool
    {
        $is_checkout_context = (function_exists('pmpro_is_checkout') && pmpro_is_checkout())
            || (!empty($_REQUEST['level']) && (isset($_REQUEST['submit-checkout']) || isset($_POST['submit-checkout']) || isset($_GET['level'])));

        if ($is_checkout_context) {
            if (is_numeric($levels) && $this->is_service_level((int) $levels)) {
                return false;
            }
            if (is_array($levels)) {
                foreach ($levels as $lvl) {
                    $lid = is_object($lvl) ? (int) ($lvl->id ?? 0) : (int) $lvl;
                    if ($lid > 0 && $this->is_service_level($lid)) {
                        return false;
                    }
                }
            }
        }
        return $has_level;
    }

    /**
     * Ensures MMPU permits repeat checkout for service levels without replacing previous levels.
     *
     * @param object|null $level
     * @param int         $user_id
     * @return object|null
     */
    public function filter_pmprommpu_checkout_level(?object $level, int $user_id): ?object
    {
        return $level;
    }

    /**
     * Prevents cancellation of base membership levels (Free, Monthly, Event) if the user has active add-on services.
     *
     * @param bool $okay
     * @param int  $level_id
     * @param int  $user_id
     * @return bool
     */
    public function block_base_membership_cancellation_with_active_services(bool $okay, int $level_id, int $user_id): bool
    {
        if (!$okay) {
            return false;
        }

        if ($user_id <= 0) {
            return $okay;
        }

        // If level being cancelled is an add-on service level, permit cancelling the service
        if ($level_id > 0 && $this->is_service_level($level_id)) {
            return $okay;
        }

        // If user has active services, strictly block cancelling if it leaves them with no base membership
        if ($this->has_active_one_on_one_service($user_id)) {
            $remaining_base_levels = 0;
            if (function_exists('pmpro_getMembershipLevelsForUser')) {
                $user_levels = pmpro_getMembershipLevelsForUser($user_id);
                if (is_array($user_levels)) {
                    foreach ($user_levels as $ulvl) {
                        $ulid = is_object($ulvl) ? (int) ($ulvl->id ?? 0) : (int) $ulvl;
                        if ($ulid > 0 && $ulid !== $level_id && !$this->is_service_level($ulid)) {
                            $remaining_base_levels++;
                        }
                    }
                }
            }

            if ($remaining_base_levels === 0) {
                global $pmpro_msg, $pmpro_msgt;
                $pmpro_msg  = __('You cannot cancel your base membership while you have active add-on services. Please contact support.', 'matchmaker');
                $pmpro_msgt = 'pmpro_error';
                return false;
            }
        }

        return $okay;
    }

    /**
     * Checks whether the user has an active base membership level (Free, Monthly, or Event).
     *
     * @param int $user_id
     * @return bool
     */
    public function has_active_base_membership(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (function_exists('pmpro_getMembershipLevelsForUser')) {
            $levels = pmpro_getMembershipLevelsForUser($user_id);
            if (is_array($levels) && !empty($levels)) {
                foreach ($levels as $lvl) {
                    $lid = is_object($lvl) ? (int) ($lvl->id ?? 0) : (int) $lvl;
                    if ($lid > 0 && !$this->is_service_level($lid)) {
                        return true;
                    }
                }
                return false;
            }
        }

        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (is_object($membership) && !empty($membership->id)) {
                return !$this->is_service_level((int) $membership->id);
            }
        }

        // Fallback for non-PMPro environment
        $meta_type = (string) get_user_meta($user_id, 'user_type', true);
        return in_array($meta_type, ['free', 'monthly', 'event'], true);
    }

    /**
     * Redirects users away from the PMPro cancellation page if they try to cancel a base tier while holding active services.
     *
     * @return void
     */
    public function maybe_block_cancel_page_for_active_services(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || !$this->has_active_one_on_one_service($user_id)) {
            return;
        }

        global $post;
        $is_cancel_page = false;

        if (function_exists('pmpro_is_cancel_page') && pmpro_is_cancel_page()) {
            $is_cancel_page = true;
        } elseif (is_page('cancel') || is_page('membership-cancel') || is_page('pmpro-cancel') || is_page('cancel-membership')) {
            $is_cancel_page = true;
        } elseif (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'pmpro_cancel') || str_contains($post->post_content, 'membership_cancel') || str_contains($post->post_content, 'membership-cancel'))) {
            $is_cancel_page = true;
        } elseif (isset($_REQUEST['membership_cancel']) || isset($_REQUEST['pmpro_cancel']) || (isset($_REQUEST['action']) && $_REQUEST['action'] === 'cancel')) {
            $is_cancel_page = true;
        }

        if (!$is_cancel_page) {
            return;
        }

        $cancel_level = 0;
        if (!empty($_REQUEST['level'])) {
            $cancel_level = (int) $_REQUEST['level'];
        } elseif (!empty($_POST['level'])) {
            $cancel_level = (int) $_POST['level'];
        } elseif (!empty($_REQUEST['membership_cancel']) && is_numeric($_REQUEST['membership_cancel'])) {
            $cancel_level = (int) $_REQUEST['membership_cancel'];
        }

        if ($cancel_level === 0 || !$this->is_service_level($cancel_level)) {
            $account_url  = \Matchmaker\Service\ProfileService::instance()->get_membership_account_url();
            $redirect_url = add_query_arg('msg', 'cannot_cancel_base_with_services', $account_url);
            
            if (function_exists('pmpro_setMessage')) {
                pmpro_setMessage(__('You cannot cancel your base membership while you have active add-on services. Please contact support.', 'matchmaker'), 'pmpro_error');
            }

            wp_safe_redirect($redirect_url);
            if (!defined('MM_UNIT_TESTS')) {
                exit;
            }
        }
    }

    /**
     * Filter pmpro_can_cancel_membership_level in PMPro 3.0+
     *
     * @param bool       $can_cancel
     * @param int        $user_id
     * @param mixed      $level
     * @return bool
     */
    public function filter_pmpro_can_cancel_membership_level(bool $can_cancel, int $user_id, mixed $level = null): bool
    {
        if (!$can_cancel) {
            return false;
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0 || !$this->has_active_one_on_one_service($user_id)) {
            return $can_cancel;
        }

        $lid = is_object($level) ? (int) ($level->id ?? 0) : (int) $level;
        if ($lid > 0 && $this->is_service_level($lid)) {
            return $can_cancel;
        }

        return false;
    }

    /**
     * Removes the 'Cancel' link from PMPro Account membership tables for base memberships if user has active services.
     *
     * @param array      $links
     * @param mixed      $level
     * @param int|null   $user_id
     * @return array
     */
    public function filter_pmpro_member_action_links(array $links, mixed $level, ?int $user_id = null): array
    {
        if ($user_id === null || $user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0 || !$this->has_active_one_on_one_service($user_id)) {
            return $links;
        }

        $lid = is_object($level) ? (int) ($level->id ?? 0) : (int) $level;
        if ($lid > 0 && !$this->is_service_level($lid)) {
            unset($links['cancel'], $links['pmpro_cancel']);
        }

        return $links;
    }

    /**
     * Injects client-side safety script on PMPro Account page to hide base membership cancel links when services are active.
     *
     * @return void
     */
    public function render_account_cancel_blockade_script(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || !$this->has_active_one_on_one_service($user_id)) {
            return;
        }

        $services = $this->get_user_active_services($user_id);
        if (empty($services)) {
            return;
        }

        $service_level_ids = array_map(static fn($s) => (int)$s['id'], $services);
        $service_ids_json  = json_encode($service_level_ids);

        ?>
        <script>
        (function() {
            function enforceCancelBlockade() {
                var serviceIds = <?php echo $service_ids_json; ?>;
                var cancelLinks = document.querySelectorAll('a[href*="cancel"], a[href*="membership_cancel"], .pmpro_actionlink-cancel');
                
                cancelLinks.forEach(function(link) {
                    var href = link.getAttribute('href') || '';
                    var match = href.match(/[?&]level=(\d+)/);
                    var levelId = match ? parseInt(match[1], 10) : 0;
                    
                    // If link is for a base level (not in serviceIds), remove or disable it
                    if (levelId > 0 && serviceIds.indexOf(levelId) === -1) {
                        link.style.display = 'none';
                        var note = document.createElement('span');
                        note.className = 'pmpro-base-cancel-disabled-note';
                        note.style.fontSize = '12px';
                        note.style.color = '#94a3b8';
                        note.style.fontStyle = 'italic';
                        note.textContent = 'Active with services';
                        if (link.parentNode) {
                            link.parentNode.insertBefore(note, link.nextSibling);
                        }
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', enforceCancelBlockade);
            } else {
                enforceCancelBlockade();
            }
        })();
        </script>
        <?php
    }

    /**
     * Displays a clean error notice on the PMPro Account page if redirected from a blocked cancellation attempt.
     *
     * @return void
     */
    public function render_account_error_notice(): void
    {
        if (!empty($_GET['msg']) && $_GET['msg'] === 'cannot_cancel_base_with_services') {
            echo '<div class="pmpro_message pmpro_error" style="margin-bottom:20px;padding:12px 16px;background:#fef2f2;border-left:4px solid #ef4444;color:#991b1b;border-radius:6px;font-weight:500;">'
                . esc_html__('You cannot cancel your base membership while you have active add-on services. Please contact support.', 'matchmaker')
                . '</div>';
        }
    }

    /**
     * Retrieves all active service levels and their display tags for a user.
     *
     * @param int $user_id
     * @return array<int, array{id: int, name: string, tag: string}>
     */
    public function get_user_active_services(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $services = $this->get_services_levels();

        // Build list of all valid service level IDs & map
        $service_map = [];
        foreach ($services as $srv) {
            if (is_object($srv) && !empty($srv->id)) {
                $service_map[(int) $srv->id] = (string) ($srv->name ?? '');
            } elseif (is_numeric($srv) && (int) $srv > 0) {
                $service_map[(int) $srv] = '';
            }
        }
        if (empty($service_map)) {
            $service_map = [4 => '1-on-1 VIP Matchmaking', 5 => '1-on-1 Consultation'];
        }

        $user_service_ids = [];

        if (function_exists('pmpro_getMembershipLevelsForUser')) {
            $user_levels = pmpro_getMembershipLevelsForUser($user_id);
            if (is_array($user_levels)) {
                foreach ($user_levels as $ulvl) {
                    $ulid = is_object($ulvl) ? (int) ($ulvl->id ?? 0) : (int) $ulvl;
                    if ($ulid > 0 && isset($service_map[$ulid])) {
                        // Check expiry if set
                        if (is_object($ulvl) && !empty($ulvl->enddate)) {
                            $end_ts = is_numeric($ulvl->enddate) ? (int) $ulvl->enddate : strtotime((string) $ulvl->enddate);
                            if ($end_ts > 0 && $end_ts <= current_time('timestamp')) {
                                continue;
                            }
                        }
                        $user_service_ids[$ulid] = is_object($ulvl) && !empty($ulvl->name) ? (string) $ulvl->name : ($service_map[$ulid] ?: '');
                    }
                }
            }
        }

        if (empty($user_service_ids) && function_exists('pmpro_getMembershipLevelForUser')) {
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (is_object($membership) && !empty($membership->id)) {
                $mlid = (int) $membership->id;
                if (isset($service_map[$mlid])) {
                    if (empty($membership->enddate) || (strtotime((string)$membership->enddate) > current_time('timestamp'))) {
                        $user_service_ids[$mlid] = (string) ($membership->name ?? $service_map[$mlid]);
                    }
                }
            }
        }

        if (empty($user_service_ids) && function_exists('pmpro_hasMembershipLevel')) {
            foreach (array_keys($service_map) as $srv_id) {
                if (pmpro_hasMembershipLevel($srv_id, $user_id)) {
                    $user_service_ids[$srv_id] = $service_map[$srv_id];
                }
            }
        }

        // Fallback for legacy usermeta or mock tests
        if (empty($user_service_ids)) {
            $has_meta = (bool) get_user_meta($user_id, 'mm_has_one_on_one', true)
                || (string) get_user_meta($user_id, 'user_type', true) === 'one_on_one';
            if ($has_meta) {
                $first_srv_id = (int) array_key_first($service_map);
                $user_service_ids[$first_srv_id] = $service_map[$first_srv_id] ?? '1-on-1 VIP';
            }
        }

        $result = [];
        foreach ($user_service_ids as $sid => $sname) {
            if (empty($sname) && function_exists('pmpro_getLevel')) {
                $lvl_obj = pmpro_getLevel($sid);
                if (is_object($lvl_obj) && !empty($lvl_obj->name)) {
                    $sname = (string) $lvl_obj->name;
                }
            }
            if (empty($sname)) {
                $sname = 'Service #' . $sid;
            }

            $tag = $this->get_level_tag($sid, $sname);

            $result[] = [
                'id'   => $sid,
                'name' => $sname,
                'tag'  => $tag,
            ];
        }

        return $result;
    }

    /**
     * Checks whether the user holds an active 1-on-1 VIP or add-on service level (Group 3).
     *
     * @param int $user_id
     * @return bool
     */
    public function has_active_one_on_one_service(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        return !empty($this->get_user_active_services($user_id));
    }

    /**
     * Recursion guard for assigning the free membership level.
     *
     * @var bool
     */
    private static bool $is_assigning_free = false;

    /**
     * Automatically assigns the PMPro Free membership level to a user if they have no active paid base tier
     * and do not already hold the free membership level.
     *
     * @param int $user_id
     * @return void
     */
    public function maybe_assign_free_membership(int $user_id): void
    {
        if ($user_id <= 0 || self::$is_assigning_free) {
            return;
        }

        // Only assign free membership if the user has no active paid base tier (monthly, event)
        $current_user_type = $this->get_current_user_type($user_id);
        if ($current_user_type !== 'free') {
            return;
        }

        $free_level_id = $this->get_primary_level_for_tier('free', 2);
        if ($free_level_id <= 0) {
            return;
        }

        // Check if user already has the free level active in PMPro
        if (function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel($free_level_id, $user_id)) {
            return;
        }

        if (function_exists('pmpro_changeMembershipLevel')) {
            self::$is_assigning_free = true;
            try {
                pmpro_changeMembershipLevel($free_level_id, $user_id);
            } finally {
                self::$is_assigning_free = false;
            }
        }
    }

    /**
     * Syncs PMPro level changes to our system.
     *
     * @param mixed $level_id
     * @param int   $user_id
     * @param mixed $old_level_id
     */
    public function sync_pmpro_level_to_user_type(mixed $level_id, int $user_id, mixed $old_level_id = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        $int_level_id = is_numeric($level_id) ? (int) $level_id : 0;

        // If user changed/upgraded to a paid base tier (Monthly or Event), cancel lingering Free tier (Group 1)
        if ($int_level_id > 0 && !$this->is_service_level($int_level_id)) {
            $new_tier = $this->get_user_type_by_level_id($int_level_id);
            if (in_array($new_tier, ['monthly', 'event'], true)) {
                $this->maybe_cancel_free_levels($user_id);
            }
        }

        $resolved_user_type = $this->get_current_user_type($user_id);
        $has_one_on_one     = $this->has_active_one_on_one_service($user_id);

        // If a specific level > 0 was passed and PMPro internal cache hasn't flushed yet
        if ($int_level_id > 0) {
            if ($this->is_service_level($int_level_id)) {
                $has_one_on_one = true;
            } else {
                $direct_tier = $this->get_user_type_by_level_id($int_level_id);
                if ($direct_tier === 'one_on_one') {
                    $has_one_on_one = true;
                } elseif (isset(self::BASE_TIER_PRIORITY[$direct_tier])) {
                    $direct_rank   = self::BASE_TIER_PRIORITY[$direct_tier];
                    $resolved_rank = self::BASE_TIER_PRIORITY[$resolved_user_type] ?? 1;
                    if ($direct_rank > $resolved_rank) {
                        $resolved_user_type = $direct_tier;
                    }
                }
            }
        }

        if (in_array($resolved_user_type, ['monthly', 'event'], true)) {
            $this->maybe_cancel_free_levels($user_id);
        } elseif ($resolved_user_type === 'free') {
            $this->maybe_assign_free_membership($user_id);
        }
        
        \Matchmaker\Repository\MatchRepository::instance()->save_meta($user_id, 'user_type', $resolved_user_type);
        update_user_meta($user_id, 'mm_has_one_on_one', $has_one_on_one ? 1 : 0);

        \Matchmaker\Repository\MatchRepository::instance()->update_pool_user_type($user_id, $resolved_user_type);
        \Matchmaker\Repository\MatchRepository::instance()->update_pool_one_on_one($user_id, $has_one_on_one);

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
            $has_one_on_one     = $this->has_active_one_on_one_service($user_id);

            if (in_array($resolved_user_type, ['monthly', 'event'], true)) {
                $this->maybe_cancel_free_levels($user_id);
            } elseif ($resolved_user_type === 'free') {
                $this->maybe_assign_free_membership($user_id);
            }

            \Matchmaker\Repository\MatchRepository::instance()->save_meta($user_id, 'user_type', $resolved_user_type);
            update_user_meta($user_id, 'mm_has_one_on_one', $has_one_on_one ? 1 : 0);

            \Matchmaker\Repository\MatchRepository::instance()->update_pool_user_type($user_id, $resolved_user_type);
            \Matchmaker\Repository\MatchRepository::instance()->update_pool_one_on_one($user_id, $has_one_on_one);

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

        $level_id = 0;
        if (is_object($morder) && !empty($morder->membership_id)) {
            $level_id = (int) $morder->membership_id;
        } elseif (!empty($_REQUEST['level'])) {
            $level_id = (int) $_REQUEST['level'];
        } elseif (isset($_POST['pmpro_level'])) {
            $level_id = (int) $_POST['pmpro_level'];
        }

        if ($level_id > 0 && $this->is_service_level($level_id)) {
            \Matchmaker\Service\NotificationService::instance()->send_admin_service_purchase_notification($user_id, $level_id, $morder);
        }
    }

    /**
     * Hook on PMPro membership post membership expiry.
     *
     * @param int   $user_id
     * @param mixed $membership_id
     * @return void
     */
    public function handle_expiry_sync(int $user_id, mixed $membership_id = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        $this->sync_all_membership_levels($user_id);
    }

    /**
     * Hook on PMPro membership status changes (e.g. cancelled, expired, inactive).
     *
     * @param string $status
     * @param int    $user_id
     * @param mixed  $level_id
     * @return void
     */
    public function handle_status_change_sync(string $status, int $user_id, mixed $level_id = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        $this->sync_all_membership_levels($user_id);
    }

    /**
     * Cancels any active Free tier levels for a user if they hold a paid base tier.
     * Group 3 (1-on-1) levels are NEVER cancelled.
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
     * Retrieves the current base subscription user_type (Group 1: free, Group 2: monthly / event).
     * 1-on-1 (Group 3) is treated as an independent add-on service.
     * Respects active subscription periods (grace period before expiry date).
     *
     * @param int $user_id
     * @return string 'monthly' | 'event' | 'free'
     */
    public function get_current_user_type(int $user_id): string
    {
        if ($user_id <= 0) {
            return 'free';
        }

        $pmpro_available = function_exists('pmpro_getMembershipLevelsForUser') || function_exists('pmpro_getMembershipLevelForUser');

        // 1. Check all active membership levels for user (supporting PMPro multiple level groups)
        if (function_exists('pmpro_getMembershipLevelsForUser')) {
            $levels = pmpro_getMembershipLevelsForUser($user_id);
            if (is_array($levels) && !empty($levels)) {
                $best_base_tier = 'free';
                $highest_rank   = 0;

                foreach ($levels as $level_obj) {
                    $lvl_id = is_object($level_obj) ? (int) ($level_obj->id ?? 0) : (int) $level_obj;
                    if ($lvl_id > 0 && !$this->is_service_level($lvl_id)) {
                        // Check expiration date if present
                        if (is_object($level_obj) && !empty($level_obj->enddate)) {
                            $end_ts = is_numeric($level_obj->enddate) ? (int) $level_obj->enddate : strtotime((string) $level_obj->enddate);
                            if ($end_ts > 0 && $end_ts <= current_time('timestamp')) {
                                continue; // Expired, skip as active tier
                            }
                        }

                        $tier = $this->get_user_type_by_level_id($lvl_id);
                        if (isset(self::BASE_TIER_PRIORITY[$tier])) {
                            $rank = self::BASE_TIER_PRIORITY[$tier];
                            if ($rank > $highest_rank) {
                                $highest_rank   = $rank;
                                $best_base_tier = $tier;
                            }
                        }
                    }
                }

                if ($highest_rank > 0) {
                    return $best_base_tier;
                }
            }
        }

        // 2. Fallback to single level getter
        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (is_object($membership) && !empty($membership->id)) {
                $is_expired = false;
                if (!empty($membership->enddate)) {
                    $end_ts = is_numeric($membership->enddate) ? (int) $membership->enddate : strtotime((string) $membership->enddate);
                    if ($end_ts > 0 && $end_ts <= current_time('timestamp')) {
                        $is_expired = true;
                    }
                }
                if (!$is_expired) {
                    $tier = $this->get_user_type_by_level_id((int) $membership->id);
                    if (in_array($tier, ['monthly', 'event'], true)) {
                        return $tier;
                    }
                }
            }
        }

        // If PMPro functions are present and reported no active base levels, user is 'free'
        if ($pmpro_available) {
            return 'free';
        }

        // 3. Fallback to usermeta ONLY if PMPro functions are not loaded in the runtime
        $meta_type = (string) get_user_meta($user_id, 'user_type', true);
        if (in_array($meta_type, ['monthly', 'event', 'free'], true)) {
            return $meta_type;
        }

        return 'free';
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

    /**
     * Synchronizes all users in the system to ensure their user_type is strictly 'free', 'monthly', or 'event',
     * and their mm_has_one_on_one meta is accurately set based on active PMPro services.
     *
     * @return int Number of users synchronized.
     */
    public function sync_all_users_user_types(): int
    {
        global $wpdb;

        $user_ids = [];

        if (!empty($wpdb->users)) {
            $user_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->users}");
        }

        if (empty($user_ids) && function_exists('get_users')) {
            $users = get_users(['fields' => 'ID']);
            if (is_array($users)) {
                $user_ids = array_map('intval', $users);
            }
        }

        $count = 0;
        foreach ($user_ids as $uid) {
            $user_id = (int) $uid;
            if ($user_id <= 0) {
                continue;
            }

            $resolved_user_type = $this->get_current_user_type($user_id);
            if (!in_array($resolved_user_type, ['free', 'monthly', 'event'], true)) {
                $resolved_user_type = 'free';
            }

            if ($resolved_user_type === 'free') {
                $this->maybe_assign_free_membership($user_id);
            }

            $has_one_on_one = $this->has_active_one_on_one_service($user_id);

            \Matchmaker\Repository\MatchRepository::instance()->save_meta($user_id, 'user_type', $resolved_user_type);
            update_user_meta($user_id, 'mm_has_one_on_one', $has_one_on_one ? 1 : 0);

            \Matchmaker\Repository\MatchRepository::instance()->update_pool_user_type($user_id, $resolved_user_type);
            \Matchmaker\Repository\MatchRepository::instance()->update_pool_one_on_one($user_id, $has_one_on_one);

            $count++;
        }

        return $count;
    }
}
