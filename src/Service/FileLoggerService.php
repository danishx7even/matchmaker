<?php
declare(strict_types=1);

namespace Matchmaker\Service;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FileLoggerService
 *
 * Provides dedicated file-based logging for the Matchmaker plugin.
 * Separates logs into:
 *   - info.log  : General events, background jobs, matching runs, member updates, emails.
 *   - error.log : Errors, failures, exceptions, rejected dispatches, and invalid states.
 *
 * Logs are stored securely in wp-content/uploads/matchmaker-logs/ with .htaccess and index.php guards.
 *
 * @package Matchmaker\Service
 * @since   2.5.0
 */
class FileLoggerService
{
    private static ?string $custom_log_dir = null;

    /**
     * Get the absolute path to the secure log directory.
     *
     * @return string
     */
    public static function get_log_dir(): string
    {
        if (self::$custom_log_dir !== null) {
            return self::$custom_log_dir;
        }

        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $base_dir   = !empty($upload_dir['basedir']) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';
            $dir        = trailingslashit($base_dir) . 'matchmaker-logs';
        } else {
            $dir = dirname(__DIR__, 2) . '/logs';
        }

        self::ensure_directory_secure($dir);
        return $dir;
    }

    /**
     * Override the log directory (primarily for testing).
     *
     * @param string|null $dir
     * @return void
     */
    public static function set_custom_log_dir(?string $dir): void
    {
        self::$custom_log_dir = $dir;
        if ($dir !== null) {
            self::ensure_directory_secure($dir);
        }
    }

    /**
     * Ensure the log directory exists and is secured against direct public access.
     *
     * @param string $dir
     * @return void
     */
    public static function ensure_directory_secure(string $dir): void
    {
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                @mkdir($dir, 0755, true);
            }
        }

        // 1. Write .htaccess security barrier if missing
        $htaccess_file = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess_file) && is_dir($dir) && is_writable($dir)) {
            $htaccess_rules = "# Protect Matchmaker log files from direct HTTP access\n"
                . "<IfModule !authz_core_module>\n"
                . "    Order deny,allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n"
                . "<IfModule authz_core_module>\n"
                . "    Require all denied\n"
                . "</IfModule>\n";
            @file_put_contents($htaccess_file, $htaccess_rules);
        }

        // 2. Write index.php silence guard
        $index_file = trailingslashit($dir) . 'index.php';
        if (!file_exists($index_file) && is_dir($dir) && is_writable($dir)) {
            @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * Get the absolute path to a specific log file.
     *
     * @param string $type 'info' | 'error' | 'general'
     * @return string
     */
    public static function get_log_file_path(string $type = 'info'): string
    {
        $dir = self::get_log_dir();
        $filename = ($type === 'error' || $type === 'errors') ? 'error.log' : 'info.log';
        return trailingslashit($dir) . $filename;
    }

    /**
     * Write an INFO level message to info.log.
     *
     * @param string               $message Log message.
     * @param array<string, mixed> $context Optional structured contextual data.
     * @param string               $category Category label (e.g. 'match_engine', 'auth', 'email').
     * @return void
     */
    public static function info(string $message, array $context = [], string $category = 'general'): void
    {
        self::write_entry('info.log', 'INFO', $message, $context, $category);
    }

    /**
     * Write a WARNING level message to info.log (and error.log).
     *
     * @param string               $message Log message.
     * @param array<string, mixed> $context Optional structured contextual data.
     * @param string               $category Category label.
     * @return void
     */
    public static function warning(string $message, array $context = [], string $category = 'general'): void
    {
        self::write_entry('info.log', 'WARNING', $message, $context, $category);
        self::write_entry('error.log', 'WARNING', $message, $context, $category);
    }

    /**
     * Write an ERROR level message to error.log (and info.log).
     *
     * @param string               $message Log message.
     * @param array<string, mixed> $context Optional structured contextual data.
     * @param string               $category Category label.
     * @return void
     */
    public static function error(string $message, array $context = [], string $category = 'general'): void
    {
        self::write_entry('error.log', 'ERROR', $message, $context, $category);
        self::write_entry('info.log', 'ERROR', $message, $context, $category);
    }

    /**
     * Write a DEBUG level message to info.log.
     *
     * @param string               $message Log message.
     * @param array<string, mixed> $context Optional structured contextual data.
     * @param string               $category Category label.
     * @return void
     */
    public static function debug(string $message, array $context = [], string $category = 'general'): void
    {
        self::write_entry('info.log', 'DEBUG', $message, $context, $category);
    }

    /**
     * Internal writer appending formatted log line to file.
     *
     * @param string               $filename 'info.log' | 'error.log'
     * @param string               $level    'INFO' | 'WARNING' | 'ERROR' | 'DEBUG'
     * @param string               $message  Log message text.
     * @param array<string, mixed> $context  Context metadata.
     * @param string               $category Category tag.
     * @return void
     */
    private static function write_entry(string $filename, string $level, string $message, array $context = [], string $category = 'general'): void
    {
        $dir = self::get_log_dir();
        $file_path = trailingslashit($dir) . $filename;

        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        $category_tag = !empty($category) ? strtoupper(trim($category)) : 'GENERAL';

        $context_str = '';
        if (!empty($context)) {
            $json = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);
            if (!empty($json) && $json !== '[]') {
                $context_str = ' | context: ' . $json;
            }
        }

        $line = sprintf("[%s] [%s] [%s] %s%s\n", $timestamp, $level, $category_tag, $message, $context_str);

        @file_put_contents($file_path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get the content of a log file, optionally limiting to the last N lines.
     *
     * @param string $type      'info' | 'error'
     * @param int    $max_lines Number of latest lines to retrieve (0 for all).
     * @return string
     */
    public static function get_log_content(string $type = 'info', int $max_lines = 500): string
    {
        $file_path = self::get_log_file_path($type);

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return '';
        }

        $filesize = filesize($file_path);
        if ($filesize === false || $filesize === 0) {
            return '';
        }

        // For small to medium files or full fetch, read directly
        if ($max_lines <= 0 || $filesize < 250000) {
            $lines = file($file_path, FILE_IGNORE_NEW_LINES);
            if ($lines === false || empty($lines)) {
                return '';
            }

            if ($max_lines > 0 && count($lines) > $max_lines) {
                $lines = array_slice($lines, -$max_lines);
            }

            return implode("\n", $lines);
        }

        // Efficient tail read for larger files
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return '';
        }

        $buffer_size = 4096;
        $offset      = -$buffer_size;
        $output      = '';
        $line_count  = 0;

        while ($filesize + $offset >= 0 && $line_count <= $max_lines) {
            fseek($handle, $offset, SEEK_END);
            $chunk      = fread($handle, $buffer_size);
            $output     = $chunk . $output;
            $line_count = substr_count($output, "\n");
            $offset    -= $buffer_size;
        }

        if ($filesize + $offset < 0 && $line_count <= $max_lines) {
            fseek($handle, 0);
            $remaining = $filesize + $offset + $buffer_size;
            if ($remaining > 0) {
                $chunk  = fread($handle, $remaining);
                $output = $chunk . $output;
            }
        }

        fclose($handle);

        $lines = explode("\n", trim($output));
        if ($max_lines > 0 && count($lines) > $max_lines) {
            $lines = array_slice($lines, -$max_lines);
        }

        return implode("\n", $lines);
    }

    /**
     * Clear the specified log file.
     *
     * @param string $type 'info' | 'error'
     * @return bool
     */
    public static function clear_log(string $type = 'info'): bool
    {
        $file_path = self::get_log_file_path($type);
        if (!file_exists($file_path)) {
            return true;
        }

        $res = @file_put_contents($file_path, '', LOCK_EX);
        return $res !== false;
    }

    /**
     * Get human-readable file size for a log file.
     *
     * @param string $type 'info' | 'error'
     * @return string
     */
    public static function get_log_file_size(string $type = 'info'): string
    {
        $file_path = self::get_log_file_path($type);
        if (!file_exists($file_path)) {
            return '0 B';
        }

        $bytes = filesize($file_path);
        if ($bytes === false || $bytes <= 0) {
            return '0 B';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return round($bytes / 1048576, 2) . ' MB';
    }

    /**
     * Get the last modified timestamp string for a log file.
     *
     * @param string $type 'info' | 'error'
     * @return string
     */
    public static function get_log_file_mtime(string $type = 'info'): string
    {
        $file_path = self::get_log_file_path($type);
        if (!file_exists($file_path)) {
            return __('Never', 'matchmaker');
        }

        $mtime = filemtime($file_path);
        if ($mtime === false || $mtime <= 0) {
            return __('Never', 'matchmaker');
        }

        if (function_exists('human_time_diff')) {
            return sprintf(__('%s ago', 'matchmaker'), human_time_diff($mtime, time()));
        }

        return gmdate('Y-m-d H:i:s', $mtime) . ' UTC';
    }

    /**
     * Get total line count in a log file.
     *
     * @param string $type 'info' | 'error'
     * @return int
     */
    public static function get_log_line_count(string $type = 'info'): int
    {
        $file_path = self::get_log_file_path($type);
        if (!file_exists($file_path)) {
            return 0;
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return is_array($lines) ? count($lines) : 0;
    }
}
