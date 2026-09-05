<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Service\EmailVerificationService;

final class EmailVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['__mm_users'] = [];
        $GLOBALS['__mm_sent_mails'] = [];
    }

    public function test_admin_is_always_verified(): void
    {
        $service = EmailVerificationService::instance();
        
        $admin_id = 1; // get_userdata(1) or admin capability in test bootstrap
        $user = new \FakeWP_User($admin_id, 'Admin User', 'admin@example.com');
        $user->roles = ['administrator'];
        $GLOBALS['__mm_users'][$admin_id] = $user;

        $this->assertTrue($service->is_user_verified($admin_id));
    }

    public function test_generate_and_send_code_creates_meta_and_sends_email(): void
    {
        $service = EmailVerificationService::instance();
        $user_id = 501;
        $user = new \FakeWP_User($user_id, 'Test Candidate', 'candidate501@example.com');
        $user->roles = ['subscriber'];
        $GLOBALS['__mm_users'][$user_id] = $user;

        $this->assertFalse($service->is_user_verified($user_id));

        $res = $service->generate_and_send_code($user_id, true);
        $this->assertTrue($res['success']);
        $this->assertEquals(60, $res['cooldown_remaining']);

        // Check usermeta
        $code = (string) get_user_meta($user_id, 'mm_verification_code', true);
        $this->assertEquals(6, strlen($code));
        $this->assertTrue(is_numeric($code));

        $expires = (int) get_user_meta($user_id, 'mm_verification_expires_at', true);
        $this->assertGreaterThan(time(), $expires);

        // Check wp_mail was dispatched
        $this->assertNotEmpty($GLOBALS['__mm_sent_mails']);
        $last_mail = end($GLOBALS['__mm_sent_mails']);
        $this->assertEquals('candidate501@example.com', $last_mail['to']);
        $this->assertStringContainsString($code, $last_mail['subject']);
        $this->assertStringContainsString($code, $last_mail['message']);
    }

    public function test_resend_cooldown_blocks_rapid_requests(): void
    {
        $service = EmailVerificationService::instance();
        $user_id = 502;
        $user = new \FakeWP_User($user_id, 'Cooldown Test', 'cooldown502@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        // First send
        $res1 = $service->generate_and_send_code($user_id, true);
        $this->assertTrue($res1['success']);

        // Immediate second send without force -> should be blocked by 60s cooldown
        $res2 = $service->generate_and_send_code($user_id, false);
        $this->assertFalse($res2['success']);
        $this->assertGreaterThan(0, $res2['cooldown_remaining']);
        $this->assertStringContainsString('Please wait', $res2['message']);
    }

    public function test_verify_code_validates_and_marks_verified(): void
    {
        $service = EmailVerificationService::instance();
        $user_id = 503;
        $user = new \FakeWP_User($user_id, 'Verify Test', 'verify503@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        $service->generate_and_send_code($user_id, true);
        $valid_code = (string) get_user_meta($user_id, 'mm_verification_code', true);

        // 1. Invalid code format (not 6 digits)
        $invalid_res = $service->verify_code($user_id, '123');
        $this->assertFalse($invalid_res['success']);

        // 2. Incorrect code
        $wrong_res = $service->verify_code($user_id, '999999' === $valid_code ? '888888' : '999999');
        $this->assertFalse($wrong_res['success']);
        $this->assertFalse($service->is_user_verified($user_id));

        // 3. Correct code
        $correct_res = $service->verify_code($user_id, $valid_code);
        $this->assertTrue($correct_res['success']);
        $this->assertTrue($service->is_user_verified($user_id));

        // Code and expiration should be cleared
        $this->assertEmpty(get_user_meta($user_id, 'mm_verification_code', true));
    }

    public function test_expired_code_fails_verification(): void
    {
        $service = EmailVerificationService::instance();
        $user_id = 504;
        $user = new \FakeWP_User($user_id, 'Expiry Test', 'expiry504@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        update_user_meta($user_id, 'mm_verification_code', '123456');
        update_user_meta($user_id, 'mm_verification_expires_at', time() - 100); // in past

        $res = $service->verify_code($user_id, '123456');
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('expired', $res['message']);
        $this->assertFalse($service->is_user_verified($user_id));
    }

    public function test_existing_user_with_pool_profile_is_grandfathered(): void
    {
        $service = EmailVerificationService::instance();
        $user_id = 505;
        $user = new \FakeWP_User($user_id, 'Grandfathered User', 'existing505@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        // User has pool profile in repository mock
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = {$user_id}"] = [
            'user_id' => $user_id,
            'gender' => 'male',
            'is_active' => 1,
        ];

        // Before check, no mm_email_verified meta is set
        $this->assertEquals('', get_user_meta($user_id, 'mm_email_verified', true));

        // is_user_verified should detect existing profile, set mm_email_verified = 1, and return true
        $this->assertTrue($service->is_user_verified($user_id));
        $this->assertEquals(1, (int) get_user_meta($user_id, 'mm_email_verified', true));
    }

    public function test_existing_users_migration_sets_meta_1(): void
    {
        $service = EmailVerificationService::instance();
        delete_option('mm_email_verification_grandfathered_v1');

        $GLOBALS['wpdb']->mock_cols["SELECT ID FROM wp_users"] = [506, 507];

        $service->maybe_grandfather_existing_users();

        $this->assertEquals(1, (int) get_user_meta(506, 'mm_email_verified', true));
        $this->assertEquals(1, (int) get_user_meta(507, 'mm_email_verified', true));
        $this->assertEquals(1, (int) get_option('mm_email_verification_grandfathered_v1'));
    }

    public function test_email_verification_logs_events_on_success_and_failure(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $service = EmailVerificationService::instance();
        $user_id = 508;
        $user = new \FakeWP_User($user_id, 'Log Test User', 'logtest508@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        // 1. Success send creates log event
        $res = $service->generate_and_send_code($user_id, true);
        $this->assertTrue($res['success']);

        $queries_str = implode("\n", $wpdb->queries);
        $this->assertStringContainsString('wp_matchmaker_logs', $queries_str);

        // 2. Failure send logs error event
        $wpdb->queries = [];
        $GLOBALS['__mm_wp_mail_return'] = false;

        $user_id2 = 509;
        $user2 = new \FakeWP_User($user_id2, 'Failed Mail User', 'failed509@example.com');
        $GLOBALS['__mm_users'][$user_id2] = $user2;

        $fail_res = $service->generate_and_send_code($user_id2, true);
        $this->assertFalse($fail_res['success']);
        $this->assertEquals(0, $fail_res['cooldown_remaining']); // No cooldown on failed send
        $this->assertStringContainsString('Failed to send verification email', $fail_res['message']);

        $fail_queries = implode("\n", $wpdb->queries);
        $this->assertStringContainsString('wp_matchmaker_logs', $fail_queries);

        unset($GLOBALS['__mm_wp_mail_return']);
    }

    public function test_custom_verification_settings_affect_subject_template_and_sender(): void
    {
        $service = EmailVerificationService::instance();

        update_option('mm_email_verify_from_email', 'custom-verify@arabzawaj.com');
        update_option('mm_email_verify_from_name', 'Arab Zawaj Verification Desk');
        update_option('mm_email_verify_subject', 'Custom Security Code: {code}');
        update_option('mm_email_verify_template', '<p>Hello {user_name}, your PIN is {code} for {user_email}. Valid for {expiry_hours} hours on {site_name}.</p>');
        update_option('mm_email_verify_expiry_hours', 48);
        update_option('mm_email_verify_cooldown_seconds', 45);

        $this->assertEquals('custom-verify@arabzawaj.com', $service->get_sender_email());
        $this->assertEquals('Arab Zawaj Verification Desk', $service->get_sender_name());
        $this->assertEquals(48 * 3600, $service->get_expiry_seconds());
        $this->assertEquals(45, $service->get_cooldown_seconds());

        $subject = $service->get_email_subject('654321');
        $this->assertEquals('Custom Security Code: 654321', $subject);

        $html = $service->get_email_html('Zaid', '654321', 'zaid@example.com');
        $this->assertStringContainsString('Hello Zaid', $html);
        $this->assertStringContainsString('654321', $html);
        $this->assertStringContainsString('zaid@example.com', $html);
        $this->assertStringContainsString('48 hours', $html);
    }

    public function test_test_mode_simulates_email_dispatch_on_mail_failure(): void
    {
        $service = EmailVerificationService::instance();
        update_option('mm_environment_mode', 'test');

        $GLOBALS['__mm_wp_mail_return'] = false; // Simulate offline mail function

        $user_id = 510;
        $user = new \FakeWP_User($user_id, 'Test Mode User', 'testmode510@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;

        $res = $service->generate_and_send_code($user_id, true);

        // In Test mode, failure should be converted to simulation with code returned
        $this->assertTrue($res['success']);
        $this->assertStringContainsString('Test Mode', $res['message']);

        $code = (string) get_user_meta($user_id, 'mm_verification_code', true);
        $this->assertEquals(6, strlen($code));
        $this->assertStringContainsString($code, $res['message']);

        unset($GLOBALS['__mm_wp_mail_return']);
        update_option('mm_environment_mode', 'live');
    }

    public function test_ajax_hooks_are_registered(): void
    {
        $service = EmailVerificationService::instance();
        
        $this->assertTrue(has_action('wp_ajax_mm_verify_email_code'));
        $this->assertTrue(has_action('wp_ajax_nopriv_mm_verify_email_code'));
        $this->assertTrue(has_action('wp_ajax_mm_resend_verification_code'));
        $this->assertTrue(has_action('wp_ajax_nopriv_mm_resend_verification_code'));
    }
}


