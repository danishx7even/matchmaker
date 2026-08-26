<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

class Legacy_Form_Wrapper {
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
        // Include the legacy form implementation which registers shortcodes and AJAX handlers
        $legacy = MATCHMAKER_PATH . 'old_files/matchmaking-form.php';
        if (file_exists($legacy)) {
            require_once $legacy;
        }
        $pmpro_css = MATCHMAKER_PATH . 'old_files/pmpro-form-design.php';
        if (file_exists($pmpro_css)) {
            require_once $pmpro_css;
        }
    }
}
