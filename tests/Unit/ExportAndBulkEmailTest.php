<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Service\ExportService;
use Matchmaker\Service\BulkEmailService;
use Matchmaker\Repository\MatchRepository;

final class ExportAndBulkEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_user_pmpro_level'] = [];
        $GLOBALS['__mm_pmpro_levels'] = [];
        if (isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb']->queries = [];
        }
    }

    public function test_export_service_headers(): void
    {
        $export_service = ExportService::instance();
        $headers = $export_service->get_csv_headers();

        $this->assertIsArray($headers);
        $this->assertGreaterThanOrEqual(40, count($headers));
        $this->assertContains('User ID', $headers);
        $this->assertContains('Full Name', $headers);
        $this->assertContains('Email Address', $headers);
        $this->assertContains('Base Membership Tier', $headers);
        $this->assertContains('Active Services', $headers);
        $this->assertContains('Application Type', $headers);
        $this->assertContains('Approved Matches', $headers);
    }

    public function test_export_service_generate_csv_with_utf8_bom(): void
    {
        $export_service = ExportService::instance();
        $csv = $export_service->generate_csv();

        $this->assertNotEmpty($csv);
        // Verify UTF-8 BOM prefix (\xEF\xBB\xBF)
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertStringContainsString('User ID', $csv);
        $this->assertStringContainsString('Base Membership Tier', $csv);
    }

    public function test_bulk_email_default_subject_and_template(): void
    {
        $bulk = BulkEmailService::instance();

        $subject = $bulk->get_default_subject();
        $this->assertNotEmpty($subject);
        $this->assertStringContainsString('{site_name}', $subject);

        $template = $bulk->get_default_template();
        $this->assertNotEmpty($template);
        $this->assertStringContainsString('{first_name}', $template);
        $this->assertStringContainsString('{dashboard_url}', $template);

        // Test save default template
        $bulk->save_default_template('New Default Subject', '<p>New Default Body</p>');
        $this->assertEquals('New Default Subject', $bulk->get_default_subject());
        $this->assertEquals('<p>New Default Body</p>', $bulk->get_default_template());
    }

    public function test_bulk_email_placeholders_guide(): void
    {
        $bulk = BulkEmailService::instance();
        $guide = $bulk->get_placeholders_guide();

        $this->assertArrayHasKey('{name}', $guide);
        $this->assertArrayHasKey('{first_name}', $guide);
        $this->assertArrayHasKey('{email}', $guide);
        $this->assertArrayHasKey('{user_type}', $guide);
        $this->assertArrayHasKey('{services}', $guide);
        $this->assertArrayHasKey('{dashboard_url}', $guide);
    }

    public function test_bulk_email_interpolation(): void
    {
        $bulk = BulkEmailService::instance();
        $user_id = 1;
        $user = new \FakeWP_User($user_id, 'Sarah Connor', 'sarah@example.com');
        $user->first_name = 'Sarah';
        $GLOBALS['__mm_users'][$user_id] = $user;

        $template = 'Hello {first_name}, your email is {email}. Tier: {user_type}. Link: {dashboard_url}';
        $interpolated = $bulk->interpolate($template, $user_id);

        $this->assertStringContainsString('Hello Sarah', $interpolated);
        $this->assertStringContainsString('your email is sarah@example.com', $interpolated);
        $this->assertStringNotContainsString('{first_name}', $interpolated);
        $this->assertStringNotContainsString('{email}', $interpolated);
    }

    public function test_bulk_email_recipient_resolution(): void
    {
        $bulk = BulkEmailService::instance();

        // 1. Explicit member IDs mode (with invalid zero or negative IDs)
        $u1 = new \FakeWP_User(101, 'User 101', 'u101@example.com');
        $u2 = new \FakeWP_User(102, 'User 102', 'u102@example.com');
        $GLOBALS['__mm_users'][101] = $u1;
        $GLOBALS['__mm_users'][102] = $u2;

        $recipients = $bulk->resolve_recipients([
            'mode' => 'members',
            'member_ids' => [101, 102, 0, -5]
        ]);

        $this->assertContains(101, $recipients);
        $this->assertContains(102, $recipients);
        $this->assertNotContains(0, $recipients);
        $this->assertNotContains(-5, $recipients);

        // 2. Criteria mode
        $recipients_criteria = $bulk->resolve_recipients([
            'mode' => 'criteria',
            'tier' => 'monthly',
        ]);
        $this->assertIsArray($recipients_criteria);
    }

    public function test_bulk_email_queue_and_batch_processing(): void
    {
        global $wpdb;
        $bulk = BulkEmailService::instance();
        $user_id = 201;
        $user = new \FakeWP_User($user_id, 'John Doe', 'john@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        // Queue bulk email
        $result = $bulk->queue_bulk_email([$user_id], 'Test Subject', '<p>Hello {name}</p>');

        $this->assertTrue($result['success'] ?? false);
        $this->assertNotEmpty($result['campaign_id'] ?? '');
        $this->assertEquals(1, $result['total_recipients'] ?? 0);

        // Process batch directly
        $bulk->process_batch([
            'campaign_id'   => $result['campaign_id'],
            'user_ids'      => [$user_id],
            'subject'       => 'Test Subject {site_name}',
            'template'      => '<p>Test Message {name}</p>',
            'batch_index'   => 1,
            'total_batches' => 1,
        ]);

        // Check audit log recorded in wpdb queries
        $queries_str = implode("\n", $wpdb->queries);
        $this->assertStringContainsString('wp_matchmaker_logs', $queries_str);
    }
}
