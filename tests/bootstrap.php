<?php
declare(strict_types=1);

// Minimal test bootstrap to stub required WP functions and globals.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/..' . DIRECTORY_SEPARATOR);
}

$GLOBALS['__mm_dbdelta_sql'] = null;
$GLOBALS['__mm_options'] = [];

function add_action($hook, $callable = null, $priority = 10) {
    // noop for tests
    return true;
}

function get_option($name, $default = false) {
    return $GLOBALS['__mm_options'][$name] ?? $default;
}

function update_option($name, $value) {
    $GLOBALS['__mm_options'][$name] = $value;
    return true;
}

function dbDelta($sql) {
    if ($GLOBALS['__mm_dbdelta_sql'] === null) {
        $GLOBALS['__mm_dbdelta_sql'] = $sql;
    } else {
        $GLOBALS['__mm_dbdelta_sql'] .= "\n" . $sql;
    }
}

class Fakewpdb {
    public $prefix = 'wp_';
    public function get_charset_collate() {
        return 'DEFAULT CHARSET=utf8mb4';
    }
}

// Use Composer autoload so PSR-4 classes are available in tests
$GLOBALS['wpdb'] = new Fakewpdb();

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
