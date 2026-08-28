<?php
declare(strict_types=1);

/**
 * Plugin Name:       Arab Zawaj Matchmaker
 * Plugin URI:        https://arabzawaj.com
 * Description:       Advanced Islamic Matchmaking Platform with Paid Memberships Pro, Action Scheduler, tabbed member portal, 5-state match flow, and bi-directional algorithmic matching.
 * Version:           2.0.0
 * Author:            Arab Zawaj Development Team
 * Author URI:        https://arabzawaj.com
 * Text Domain:       matchmaker
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * License:           Proprietary
 *
 * @package Matchmaker
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// Plugin constants
// =============================================================================

define('MM_VERSION',  '2.0.0');
define('MM_PATH',     plugin_dir_path(__FILE__));
define('MM_URL',      plugin_dir_url(__FILE__));
define('MM_SRC_PATH', MM_PATH . 'src/');

// Backward-compat aliases
define('MATCHMAKER_VERSION', MM_VERSION);
define('MATCHMAKER_PATH',    MM_PATH);
define('MATCHMAKER_URL',     MM_URL);

// =============================================================================
// Autoloader — PSR-4 style for Matchmaker namespace
// =============================================================================

/**
 * Custom PSR-4 autoloader for the Matchmaker plugin.
 *
 * Maps:
 *   Matchmaker\Repository\* => src/Repository/*.php
 *   Matchmaker\Service\*    => src/Service/*.php
 *   Matchmaker\Core\*       => src/Core/*.php
 *   Matchmaker\Frontend\*   => src/Frontend/*.php
 *   Matchmaker\Admin\*      => src/Admin/*.php
 *   Matchmaker\*            => src/*.php  (fallback to legacy includes/)
 *
 * @param string $class Fully qualified class name.
 * @return void
 */
spl_autoload_register(static function (string $class): void {
    // Only handle classes under our namespace
    if (strpos($class, 'Matchmaker\\') !== 0) {
        return;
    }

    // Strip the root namespace
    $relative = substr($class, strlen('Matchmaker\\'));

    // Build filesystem path: replace \ with DIRECTORY_SEPARATOR
    $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    // Try new src/ directory first
    $src_file = MM_SRC_PATH . $relative_path;
    if (file_exists($src_file)) {
        require_once $src_file;
        return;
    }

    // Fallback: legacy includes/ (for backward compat during migration)
    $legacy_parts    = explode('\\', $relative);
    $legacy_classname = end($legacy_parts);
    $legacy_file      = MM_PATH . 'includes/' . $legacy_classname . '.php';
    if (file_exists($legacy_file)) {
        require_once $legacy_file;
    }
});

// =============================================================================
// Always load global helper functions before plugins_loaded
// =============================================================================

// New src/functions.php (references new class namespaces)
require_once MM_SRC_PATH . 'functions.php';

// Legacy functions.php as safety net for older callers
if (!function_exists('mm_enqueue_user_matching_job')) {
    require_once MM_PATH . 'includes/functions.php';
}

// =============================================================================
// Action Scheduler bootstrap
// =============================================================================

if (!function_exists('as_enqueue_async_action')
    && file_exists(MM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php')
) {
    require_once MM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// =============================================================================
// Plugins Loaded — bootstrap all components
// =============================================================================

add_action('plugins_loaded', static function (): void {

    // 1. Database schema migration (runs on admin_init via the class constructor)
    \Matchmaker\Core\DBMigrator::instance();

    // 2. PMPro membership tier sync
    \Matchmaker\Core\PMProSync::instance();

    // 3. Auth redirects and login page customization
    \Matchmaker\Frontend\AuthController::instance();

    // 4. Free user registration via Elementor Forms
    \Matchmaker\Core\FreeRegHandler::instance();

    // 5. Matchmaking questionnaire form [matchmaking_form]
    \Matchmaker\Frontend\FormController::instance();

    // 6. Member portal dashboard [matchmaker_member_portal] / [az_profile]
    \Matchmaker\Frontend\PortalController::instance();

    // 7. Notification service (Heartbeat API polling + email dispatch)
    \Matchmaker\Service\NotificationService::instance();

    // 8. Async matching engine (Action Scheduler hooks + weekly cron)
    \Matchmaker\Core\MatchingEngine::instance();

    // 9. Admin portal (only needed in admin context, but safe to always init)
    if (is_admin()) {
        \Matchmaker\Admin\AdminPortal::instance();
    }

}, 10);

// =============================================================================
// Heartbeat enqueue (frontend only, for logged-in users)
// =============================================================================

add_action('wp_enqueue_scripts', static function (): void {
    if (!is_user_logged_in()) {
        return;
    }
    wp_enqueue_script('heartbeat');
}, 5);

// =============================================================================
// Activation Hook
// =============================================================================

register_activation_hook(__FILE__, static function (): void {
    if (!function_exists('as_enqueue_async_action')
        && file_exists(MM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php')
    ) {
        require_once MM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
    }

    \Matchmaker\Core\DBMigrator::activate();
    \Matchmaker\Core\MatchingEngine::activate();
});

// =============================================================================
// Deactivation Hook
// =============================================================================

register_deactivation_hook(__FILE__, static function (): void {
    if (!function_exists('as_enqueue_async_action')
        && file_exists(MM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php')
    ) {
        require_once MM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
    }

    \Matchmaker\Core\MatchingEngine::deactivate();
});
