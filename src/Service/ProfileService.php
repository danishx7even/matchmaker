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
        $user_type = 'free';
        if (class_exists('\Matchmaker\PMPro_Sync')) {
            $user_type = \Matchmaker\PMPro_Sync::get_user_type($user_id);
        } else {
            $meta_type = get_user_meta($user_id, 'user_type', true);
            if (!empty($meta_type)) {
                $user_type = $meta_type;
            }
        }
        return $user_type;
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
        // if (function_exists('pmpro_url')) {
        //     return pmpro_url('account');
        // }
        return home_url('/dashboard/');
    }

    /**
     * Get form URL.
     *
     * @return string
     */
    public function get_form_url(): string {
        return home_url('/personal-matchmaking-questionnaire/');
    }

    /**
     * Get events URL.
     *
     * @return string
     */
    public function get_events_url(): string {
        return home_url('/events-2/');
    }

    /**
     * Get membership account URL.
     *
     * @return string
     */
    public function get_membership_account_url(): string {
        return home_url('/membership-account/');
    }
}
