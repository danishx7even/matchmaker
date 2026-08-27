<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

class DB_Migrator {
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
        add_action('admin_init', [$this, 'maybe_migrate']);
    }

    public static function activate(): void
    {
        $self = self::instance();
        $self->maybe_migrate();
    }

    public function maybe_migrate(): void
    {
        global $wpdb;

        $option_name = 'mm_matchmaking_db_v2_version';
        $new_version = '2.1.0';
        $installed_version = (string) get_option($option_name, '0.0.0');
        
        // Handle legacy versioning correctly without blocking upgrades
        $legacy_installed = (int) get_option('mm_matchmaking_db_version', 0);
        if ($legacy_installed >= 1 && $installed_version === '0.0.0') {
            $installed_version = '2.0.0'; // Assume 2.0.0 if legacy is set but v2 is not
        }

        if (version_compare($installed_version, $new_version, '>=')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $pool_table          = $wpdb->prefix . 'matchmaking_pool';
        $matches_table       = $wpdb->prefix . 'matches';
        $notifications_table = $wpdb->prefix . 'matchmaker_notifications';

        $sql_pool = "CREATE TABLE {$pool_table} (
            user_id bigint(20) unsigned NOT NULL,
            gender enum('male','female') NOT NULL,
            pref_gender enum('male','female') NOT NULL,
            birth_date date NOT NULL,
            preferred_age_min tinyint(3) unsigned NOT NULL,
            preferred_age_max tinyint(3) unsigned NOT NULL,
            location varchar(191) NOT NULL,
            pref_location varchar(255) NOT NULL,
            religion varchar(100) NOT NULL,
            pref_religion varchar(255) NOT NULL,
            modesty varchar(50) NOT NULL,
            pref_modesty varchar(255) NOT NULL,
            origin varchar(100) DEFAULT NULL,
            pref_origin varchar(255) DEFAULT NULL,
            languages varchar(255) DEFAULT NULL,
            pref_languages varchar(255) DEFAULT NULL,
            height_cm smallint(5) unsigned DEFAULT NULL,
            preferred_height_min smallint(5) unsigned DEFAULT NULL,
            preferred_height_max smallint(5) unsigned DEFAULT NULL,
            job varchar(150) DEFAULT NULL,
            smoking varchar(50) DEFAULT NULL,
            pref_smoking varchar(100) DEFAULT NULL,
            drinking varchar(50) DEFAULT NULL,
            pref_drinking varchar(100) DEFAULT NULL,
            user_type enum('monthly','one_on_one','free','event') NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (user_id),
            KEY idx_match_core (is_active, gender, pref_gender),
            KEY idx_birth_date (birth_date),
            KEY idx_location (location),
            KEY idx_religion (religion),
            KEY idx_user_type (user_type)
        ) {$charset_collate};";

        $sql_matches = "CREATE TABLE {$matches_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_one_id bigint(20) unsigned NOT NULL,
            user_two_id bigint(20) unsigned NOT NULL,
            initiator_user_id bigint(20) unsigned NOT NULL,
            status enum('pending_review','approved','admin_rejected','matched','rejected') NOT NULL DEFAULT 'pending_review',
            user_one_response enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
            user_two_response enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
            match_source enum('auto','manual') NOT NULL DEFAULT 'auto',
            score smallint(5) unsigned DEFAULT NULL,
            contact_revealed tinyint(1) NOT NULL DEFAULT 0,
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_pair  (user_one_id, user_two_id),
            KEY idx_user_one (user_one_id),
            KEY idx_user_two (user_two_id),
            KEY idx_initiator (initiator_user_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        $sql_notifications = "CREATE TABLE {$notifications_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            match_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'match_approved',
            title varchar(255) NOT NULL,
            message text DEFAULT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_read (user_id, is_read),
            KEY idx_match_id (match_id)
        ) {$charset_collate};";

        dbDelta($sql_pool);
        dbDelta($sql_matches);
        dbDelta($sql_notifications);

        update_option($option_name, $new_version);
        // Maintain legacy numeric option for older code/tests expecting this.
        update_option('mm_matchmaking_db_version', 2);
    }
}
