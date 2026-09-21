<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Service\FileLoggerService;
use Matchmaker\Repository\MatchRepository;

class FileLoggerTest
{
    private string $test_log_dir;

    public function setUp(): void
    {
        $this->test_log_dir = sys_get_temp_dir() . '/mm_test_logs_' . uniqid('', true);
        FileLoggerService::set_custom_log_dir($this->test_log_dir);
    }

    public function tearDown(): void
    {
        $this->cleanup_dir($this->test_log_dir);
        FileLoggerService::set_custom_log_dir(null);
    }

    private function cleanup_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        if ($files) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $p = $dir . '/' . $file;
                if (is_dir($p)) {
                    $this->cleanup_dir($p);
                } else {
                    @unlink($p);
                }
            }
        }
        @rmdir($dir);
    }

    public function test_security_guards_created(): void
    {
        $dir = FileLoggerService::get_log_dir();
        if (!is_dir($dir)) {
            throw new \RuntimeException("Log dir was not created.");
        }

        $htaccess = $dir . '/.htaccess';
        $index    = $dir . '/index.php';

        if (!file_exists($htaccess)) {
            throw new \RuntimeException(".htaccess security barrier not found in log dir.");
        }
        $htaccess_content = (string) file_get_contents($htaccess);
        if (!str_contains($htaccess_content, 'Require all denied') && !str_contains($htaccess_content, 'Deny from all')) {
            throw new \RuntimeException(".htaccess does not contain security deny rules.");
        }

        if (!file_exists($index)) {
            throw new \RuntimeException("index.php silence guard not found in log dir.");
        }
    }

    public function test_info_log_writing(): void
    {
        FileLoggerService::info('Test info message', ['user_id' => 123], 'test_engine');

        $info_file = FileLoggerService::get_log_file_path('info');
        $error_file = FileLoggerService::get_log_file_path('error');

        if (!file_exists($info_file)) {
            throw new \RuntimeException("info.log was not created.");
        }

        $content = (string) file_get_contents($info_file);
        if (!str_contains($content, '[INFO]') || !str_contains($content, '[TEST_ENGINE]') || !str_contains($content, 'Test info message') || !str_contains($content, '"user_id":123')) {
            throw new \RuntimeException("info.log missing expected content: {$content}");
        }

        // Info message should NOT be in error.log
        if (file_exists($error_file)) {
            $err_content = (string) file_get_contents($error_file);
            if (str_contains($err_content, 'Test info message')) {
                throw new \RuntimeException("info message should not be present in error.log.");
            }
        }
    }

    public function test_error_log_writing(): void
    {
        FileLoggerService::error('Critical matching failure', ['err_code' => 500], 'match_engine');

        $error_file = FileLoggerService::get_log_file_path('error');
        $info_file  = FileLoggerService::get_log_file_path('info');

        if (!file_exists($error_file)) {
            throw new \RuntimeException("error.log was not created.");
        }

        $err_content = (string) file_get_contents($error_file);
        if (!str_contains($err_content, '[ERROR]') || !str_contains($err_content, 'Critical matching failure')) {
            throw new \RuntimeException("error.log missing expected content: {$err_content}");
        }

        // Errors also stream to info.log for unified chronology
        $info_content = (string) file_get_contents($info_file);
        if (!str_contains($info_content, '[ERROR]') || !str_contains($info_content, 'Critical matching failure')) {
            throw new \RuntimeException("error message should also be mirrored to info.log.");
        }
    }

    public function test_get_log_content_and_tail_limits(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            FileLoggerService::info("Line number {$i}", [], 'bulk');
        }

        $content_all = FileLoggerService::get_log_content('info', 0);
        $lines_all = explode("\n", trim($content_all));
        if (count($lines_all) !== 10) {
            throw new \RuntimeException("Expected 10 lines, got " . count($lines_all));
        }

        $content_tail = FileLoggerService::get_log_content('info', 3);
        $lines_tail = explode("\n", trim($content_tail));
        if (count($lines_tail) !== 3) {
            throw new \RuntimeException("Expected 3 lines, got " . count($lines_tail));
        }
        if (!str_contains($lines_tail[2], 'Line number 10')) {
            throw new \RuntimeException("Expected last line to contain 'Line number 10', got: {$lines_tail[2]}");
        }
    }

    public function test_clear_log(): void
    {
        FileLoggerService::info('Temporary entry to be deleted');
        if (FileLoggerService::get_log_line_count('info') !== 1) {
            throw new \RuntimeException("Expected 1 line before clear.");
        }

        $cleared = FileLoggerService::clear_log('info');
        if (!$cleared) {
            throw new \RuntimeException("clear_log returned false.");
        }

        $lines_after = FileLoggerService::get_log_line_count('info');
        if ($lines_after !== 0) {
            throw new \RuntimeException("Expected 0 lines after clear, got {$lines_after}");
        }

        $size_after = FileLoggerService::get_log_file_size('info');
        if ($size_after !== '0 B') {
            throw new \RuntimeException("Expected '0 B' after clear, got {$size_after}");
        }
    }

    public function test_match_repository_log_event_integration(): void
    {
        $repo = MatchRepository::instance();

        // 1. Success event -> info.log
        $repo->log_event(
            'match_lifecycle',
            'match_approved',
            'Match #101 Approved',
            'Match was approved by admin',
            ['match_id' => 101],
            101,
            1,
            'user@example.com',
            'success'
        );

        $info_content = FileLoggerService::get_log_content('info', 10);
        if (!str_contains($info_content, 'Match #101 Approved')) {
            throw new \RuntimeException("MatchRepository::log_event did not stream success event to info.log.");
        }

        // 2. Error event -> error.log
        $repo->log_event(
            'match_engine',
            'quota_exceeded',
            'Quota limit reached for User #5',
            'User has hit maximum cycle quota',
            ['user_id' => 5],
            null,
            5,
            null,
            'error'
        );

        $error_content = FileLoggerService::get_log_content('error', 10);
        if (!str_contains($error_content, 'Quota limit reached for User #5')) {
            throw new \RuntimeException("MatchRepository::log_event did not stream error event to error.log.");
        }
    }
}
