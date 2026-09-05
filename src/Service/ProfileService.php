<?php
declare(strict_types=1);

namespace Matchmaker\Service;

use Matchmaker\Repository\MatchRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ProfileService
 * @package Matchmaker\Service
 */
class ProfileService {

    /**
     * @var ProfileService|null
     */
    private static ?ProfileService $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return ProfileService
     */
    public static function instance(): ProfileService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ProfileService constructor.
     */
    private function __construct() {}

    /**
     * Get user type.
     *
     * @param int $user_id The user ID.
     * @return string
     */
    public function get_user_type(int $user_id): string {
        if ($user_id <= 0) {
            return 'free';
        }

        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $level = pmpro_getMembershipLevelForUser($user_id);
            if ($level && !empty($level->id)) {
                return \Matchmaker\Core\PMProSync::instance()->get_user_type_by_level_id((int) $level->id);
            }
        }

        $meta_type = get_user_meta($user_id, 'user_type', true);
        if (!empty($meta_type)) {
            return (string) $meta_type;
        }

        $az_type = get_user_meta($user_id, 'az_user_type', true);
        if (!empty($az_type)) {
            return (string) $az_type;
        }

        $pool = MatchRepository::instance()->get_user_pool($user_id);
        if ($pool && !empty($pool['user_type'])) {
            return (string) $pool['user_type'];
        }

        return 'free';
    }

    /**
     * Assemble profile data.
     *
     * @param int $user_id The user ID.
     * @return array
     */
    public function assemble_profile(int $user_id): array {
        $repo = MatchRepository::instance();
        
        $user_type = $this->get_user_type($user_id);
        $is_premium = in_array($user_type, ['premium', 'vip', 'elite'], true);
        
        return [
            'pool' => $repo->get_user_pool($user_id),
            'meta' => $repo->get_meta_block($user_id),
            'stats' => $repo->get_match_stats($user_id),
            'user_type' => $user_type,
            'is_premium' => $is_premium
        ];
    }

    /**
     * Get dashboard URL.
     *
     * @return string
     */
    public function get_dashboard_url(): string {
        $page_id = (int) get_option('mm_page_dashboard_id', 0);
        if ($page_id > 0) {
            $link = get_permalink($page_id);
            if (!empty($link)) {
                return $link;
            }
        }
        return home_url('/dashboard/');
    }

    /**
     * Get form (questionnaire) URL.
     *
     * @return string
     */
    public function get_form_url(): string {
        $page_id = (int) get_option('mm_page_questionnaire_id', 0);
        if ($page_id > 0) {
            $link = get_permalink($page_id);
            if (!empty($link)) {
                return $link;
            }
        }
        return home_url('/personal-matchmaking-questionnaire/');
    }

    /**
     * Get events URL.
     *
     * @return string
     */
    public function get_events_url(): string {
        $page_id = (int) get_option('mm_page_events_id', 0);
        if ($page_id > 0) {
            $link = get_permalink($page_id);
            if (!empty($link)) {
                return $link;
            }
        }
        return home_url('/events-2/');
    }

    /**
     * Get membership account URL.
     *
     * @return string
     */
    public function get_membership_account_url(): string {
        $page_id = (int) get_option('mm_page_account_id', 0);
        if ($page_id > 0) {
            $link = get_permalink($page_id);
            if (!empty($link)) {
                return $link;
            }
        }
        if (function_exists('pmpro_url')) {
            $pmpro_acc = pmpro_url('account');
            if (!empty($pmpro_acc)) {
                return $pmpro_acc;
            }
        }
        return home_url('/membership-account/');
    }

    /**
     * Get membership checkout URL for upgrades.
     *
     * @param int|null $level_id
     * @return string
     */
    public function get_membership_checkout_url(?int $level_id = null): string {
        if ($level_id === null || $level_id <= 0) {
            $level_id = \Matchmaker\Core\PMProSync::instance()->get_primary_level_for_tier('monthly', 3);
        }

        $page_id = (int) get_option('mm_page_checkout_id', 0);
        if ($page_id > 0) {
            $link = get_permalink($page_id);
            if (!empty($link)) {
                return add_query_arg('pmpro_level', $level_id, $link);
            }
        }

        if (function_exists('pmpro_url')) {
            $pmpro_checkout = pmpro_url('checkout');
            if (!empty($pmpro_checkout)) {
                return add_query_arg('pmpro_level', $level_id, $pmpro_checkout);
            }
        }

        return home_url('/membership-checkout/?pmpro_level=' . $level_id);
    }
}
