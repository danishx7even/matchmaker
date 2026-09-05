<?php
declare(strict_types=1);

namespace Matchmaker\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DBMigrator
 *
 * Handles database schema creation and migration for the matchmaking tables.
 */
class DBMigrator {
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
        $new_version = '2.5.0';
        $installed_version = (string) get_option($option_name, '0.0.0');
        
        // Handle legacy versioning correctly without blocking upgrades
        $legacy_installed = (int) get_option('mm_matchmaking_db_version', 0);
        if ($legacy_installed >= 1 && $installed_version === '0.0.0') {
            $installed_version = '2.0.0'; // Assume 2.0.0 if legacy is set but v2 is not
        }

        if (version_compare($installed_version, $new_version, '>=')) {
            return;
        }

        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();

        $pool_table          = $wpdb->prefix . 'matchmaking_pool';
        $matches_table       = $wpdb->prefix . 'matches';
        $notifications_table = $wpdb->prefix . 'matchmaker_notifications';
        $logs_table          = $wpdb->prefix . 'matchmaker_logs';

        $sql_pool = "CREATE TABLE {$pool_table} (
            user_id bigint(20) unsigned NOT NULL,
            gender enum('male','female') NOT NULL,
            pref_gender enum('male','female') NOT NULL,
            birth_date date NOT NULL,
            preferred_age_min tinyint(3) unsigned NOT NULL,
            preferred_age_max tinyint(3) unsigned NOT NULL,
            country varchar(100) NOT NULL DEFAULT '',
            state varchar(100) NOT NULL DEFAULT '',
            city varchar(100) NOT NULL DEFAULT '',
            pref_country varchar(255) NOT NULL DEFAULT '',
            pref_state varchar(255) NOT NULL DEFAULT '',
            pref_city varchar(255) NOT NULL DEFAULT '',
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
            KEY idx_active_gender_type (is_active, gender, user_type),
            KEY idx_birth_date (birth_date),
            KEY idx_country_city (country, city),
            KEY idx_country (country),
            KEY idx_religion (religion),
            KEY idx_user_type (user_type)
        ) {$charset_collate};";

        $sql_matches = "CREATE TABLE {$matches_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_one_id bigint(20) unsigned NOT NULL,
            user_two_id bigint(20) unsigned NOT NULL,
            initiator_user_id bigint(20) unsigned NOT NULL,
            status enum('pending_review','approved','admin_rejected','matched','rejected','expired') NOT NULL DEFAULT 'pending_review',
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
            KEY idx_pair_status (user_one_id, user_two_id, status),
            KEY idx_status_updated (status, updated_at),
            KEY idx_initiator_created (initiator_user_id, created_at),
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

        $sql_logs = "CREATE TABLE {$logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_type enum('match_lifecycle','match_engine','notification','email') NOT NULL DEFAULT 'match_lifecycle',
            reference_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            recipient varchar(191) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text DEFAULT NULL,
            details_json longtext DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'info',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_type_created (log_type, created_at),
            KEY idx_ref_id (reference_id),
            KEY idx_user_id (user_id),
            KEY idx_event_type (event_type),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql_pool);
        dbDelta($sql_matches);
        dbDelta($sql_notifications);
        dbDelta($sql_logs);

        // Direct schema patch: dbDelta does NOT modify existing ENUM definitions or always drop columns
        $wpdb->query(
            "ALTER TABLE {$matches_table} MODIFY COLUMN status ENUM('pending_review','approved','admin_rejected','matched','rejected','expired') NOT NULL DEFAULT 'pending_review'"
        );

        $cols = (array) $wpdb->get_col("DESC {$pool_table}", 0);
        if (!empty($cols)) {
            if (!in_array('country', $cols, true)) {
                $wpdb->query("ALTER TABLE {$pool_table} ADD COLUMN country varchar(100) NOT NULL DEFAULT '' AFTER preferred_age_max");
                $wpdb->query("ALTER TABLE {$pool_table} ADD COLUMN state varchar(100) NOT NULL DEFAULT '' AFTER country");
                $wpdb->query("ALTER TABLE {$pool_table} ADD COLUMN city varchar(100) NOT NULL DEFAULT '' AFTER state");
                $wpdb->query("ALTER TABLE {$pool_table} ADD COLUMN pref_country varchar(255) NOT NULL DEFAULT '' AFTER city");
                $wpdb->query("ALTER TABLE {$pool_table} ADD COLUMN pref_state varchar(255) NOT NULL DEFAULT '' AFTER pref_country");
                $wpdb->query("ALTER TABLE {$pool_table} ADD COLUMN pref_city varchar(255) NOT NULL DEFAULT '' AFTER pref_state");
            }
            if (in_array('location', $cols, true)) {
                $wpdb->query("UPDATE {$pool_table} SET country = location WHERE country = '' AND location != ''");
                $wpdb->query("ALTER TABLE {$pool_table} DROP COLUMN location");
            }
            if (in_array('pref_location', $cols, true)) {
                $wpdb->query("UPDATE {$pool_table} SET pref_country = pref_location WHERE pref_country = '' AND pref_location != ''");
                $wpdb->query("ALTER TABLE {$pool_table} DROP COLUMN pref_location");
            }
        }

        update_option($option_name, $new_version);
        // Maintain legacy numeric option for older code/tests expecting this.
        update_option('mm_matchmaking_db_version', 2);
    }
}
