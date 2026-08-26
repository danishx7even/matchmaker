<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

class PMPro_Sync {
    private static ?self $instance = null;

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
    }

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

    public function sync_pmpro_level_to_user_type(int $level_id, int $user_id, ?int $old_level_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        $user_type = $this->get_user_type_by_level_id($level_id);
        update_user_meta($user_id, 'user_type', $user_type);

        global $wpdb;
        $pool_table = $wpdb->prefix . 'matchmaking_pool';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$pool_table} WHERE user_id = %d", $user_id));
        if ($exists) {
            $wpdb->update($pool_table, ['user_type' => $user_type], ['user_id' => $user_id], ['%s'], ['%d']);
        }
    }

    public function get_current_user_type(int $user_id): string
    {
        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (!empty($membership->id)) {
                return $this->get_user_type_by_level_id((int) $membership->id);
            }
        }

        $meta_type = get_user_meta($user_id, 'user_type', true);
        return !empty($meta_type) ? (string) $meta_type : 'free';
    }
}
