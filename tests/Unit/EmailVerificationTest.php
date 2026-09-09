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
        EmailVerificationService::reset_in_memory_state();
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
        $this->assertTrue(has_action('wp_ajax_mm_verify_pending_email_code'));
        $this->assertTrue(has_action('wp_ajax_mm_resend_pending_email_code'));
        $this->assertTrue(has_action('mm_purge_unverified_users_job'));
    }

    public function test_default_expiry_is_60_minutes(): void
    {
        $service = EmailVerificationService::instance();
        delete_option('mm_email_verify_expiry_hours');

        $this->assertEquals(3600, $service->get_expiry_seconds());

        $html = $service->get_email_html('Fatima', '123456', 'fatima@example.com');
        $this->assertStringContainsString('60 minutes', $html);
    }

    public function test_purge_unverified_users_older_than_48h_deletes_account_and_membership(): void
    {
        global $wpdb;
        $service = EmailVerificationService::instance();

        // Create test unverified user registered 3 days ago
        $uid = 601;
        $user = new \FakeWP_User($uid, 'unverified601', 'unverified601@example.com');
        $user->user_registered = gmdate('Y-m-d H:i:s', time() - (72 * 3600)); // 72 hours ago
        $user->roles = ['subscriber'];
        $GLOBALS['__mm_users'][$uid] = $user;
        $GLOBALS['__mm_user_pmpro_level'][$uid] = 3; // Assigned PMPro level
        update_user_meta($uid, 'mm_email_verified', 0);

        // Mock wpdb get_results for cutoff query
        $cutoff_date = gmdate('Y-m-d H:i:s', time() - (48 * 3600));
        $wpdb->mock_results["
            SELECT u.ID, u.user_email, u.user_registered 
            FROM wp_users u
            LEFT JOIN wp_usermeta um ON (u.ID = um.user_id AND um.meta_key = 'mm_email_verified')
            WHERE u.user_registered <= '{$cutoff_date}'
              AND (um.meta_value IS NULL OR um.meta_value = '' OR um.meta_value = '0')
        "] = [
            (object) [
                'ID' => $uid,
                'user_email' => 'unverified601@example.com',
                'user_registered' => $user->user_registered,
            ]
        ];

        $purged = $service->purge_unverified_users();
        $this->assertEquals(1, $purged);

        // User should be deleted from wp_users
        $this->assertArrayNotHasKey($uid, $GLOBALS['__mm_users']);

        // PMPro level should be cancelled (0 or removed)
        $this->assertEquals(0, $GLOBALS['__mm_user_pmpro_level'][$uid] ?? 0);
    }

    public function test_purge_unverified_users_skips_admins(): void
    {
        global $wpdb;
        $service = EmailVerificationService::instance();

        // Create administrator registered 3 days ago
        $admin_uid = 602;
        $admin_user = new \FakeWP_User($admin_uid, 'admin602', 'admin602@example.com');
        $admin_user->user_registered = gmdate('Y-m-d H:i:s', time() - (72 * 3600));
        $admin_user->roles = ['administrator'];
        $GLOBALS['__mm_users'][$admin_uid] = $admin_user;

        $cutoff_date = gmdate('Y-m-d H:i:s', time() - (48 * 3600));
        $wpdb->mock_results["
            SELECT u.ID, u.user_email, u.user_registered 
            FROM wp_users u
            LEFT JOIN wp_usermeta um ON (u.ID = um.user_id AND um.meta_key = 'mm_email_verified')
            WHERE u.user_registered <= '{$cutoff_date}'
              AND (um.meta_value IS NULL OR um.meta_value = '' OR um.meta_value = '0')
        "] = [
            (object) [
                'ID' => $admin_uid,
                'user_email' => 'admin602@example.com',
                'user_registered' => $admin_user->user_registered,
            ]
        ];

        $purged = $service->purge_unverified_users();
        $this->assertEquals(0, $purged);

        // Admin must not be deleted
        $this->assertArrayHasKey($admin_uid, $GLOBALS['__mm_users']);
    }

    public function test_intercept_profile_email_update_holds_pending_email_and_sends_code(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 603;
        $user = new \FakeWP_User($uid, 'member603', 'old603@example.com');
        $user->roles = ['subscriber'];
        $GLOBALS['__mm_users'][$uid] = $user;
        update_user_meta($uid, 'mm_email_verified', 1);

        // Simulate PMPro profile form POST submission with new email
        $_POST['email'] = 'new603@example.com';
        $user_obj = (object) ['ID' => $uid, 'user_email' => 'new603@example.com'];
        $errors = new \WP_Error();

        $service->intercept_profile_email_update($errors, true, $user_obj);

        // Primary user_email in $user_obj and $_POST should remain old email
        $this->assertEquals('old603@example.com', $user_obj->user_email);
        $this->assertEquals('old603@example.com', $_POST['email']);

        // Pending email should be saved in usermeta
        $this->assertEquals('new603@example.com', get_user_meta($uid, 'mm_pending_new_email', true));

        // Verification code should be generated and stored
        $code = (string) get_user_meta($uid, 'mm_pending_verification_code', true);
        $this->assertEquals(6, strlen($code));

        // Email should have been sent to the new email address
        $last_mail = end($GLOBALS['__mm_sent_mails']);
        $this->assertEquals('new603@example.com', $last_mail['to']);
        $this->assertStringContainsString($code, $last_mail['subject']);

        // User remains email verified for their account (portal & questionnaire remain unlocked)
        $this->assertTrue($service->is_user_verified($uid));
    }

    public function test_verify_pending_email_code_updates_user_email(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 604;
        $user = new \FakeWP_User($uid, 'member604', 'old604@example.com');
        $GLOBALS['__mm_users'][$uid] = $user;

        // Set pending email and code
        update_user_meta($uid, 'mm_pending_new_email', 'new604@example.com');
        update_user_meta($uid, 'mm_pending_verification_code', '889900');
        update_user_meta($uid, 'mm_pending_verification_expires_at', time() + 3600);

        // 1. Wrong code fails
        $wrong_res = $service->verify_pending_email_code($uid, '112233');
        $this->assertFalse($wrong_res['success']);
        $this->assertEquals('old604@example.com', $GLOBALS['__mm_users'][$uid]->user_email);

        // 2. Correct code succeeds, updates wp_users.user_email and cleans up pending metas
        $ok_res = $service->verify_pending_email_code($uid, '889900');
        $this->assertTrue($ok_res['success']);
        $this->assertEquals('new604@example.com', $GLOBALS['__mm_users'][$uid]->user_email);
        $this->assertEmpty(get_user_meta($uid, 'mm_pending_new_email', true));
        $this->assertEmpty(get_user_meta($uid, 'mm_pending_verification_code', true));
    }

    public function test_render_pending_email_notice_outputs_banner_and_modal(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 605;
        $user = new \FakeWP_User($uid, 'member605', 'old605@example.com');
        $GLOBALS['__mm_users'][$uid] = $user;

        // When no pending email, notice is empty string
        $this->assertEmpty($service->render_pending_email_notice($uid));

        // When pending email is set, notice contains banner and modal markup
        update_user_meta($uid, 'mm_pending_new_email', 'pending605@example.com');
        $html = $service->render_pending_email_notice($uid);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('mm-pending-email-notice', $html);
        $this->assertStringContainsString('pending605@example.com', $html);
        $this->assertStringContainsString('mm-pending-verify-modal', $html);
        $this->assertStringContainsString('Verify Email', $html);
    }

    public function test_pmpro_profile_update_hook_intercepts_array_errors_and_resets_user_object(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 606;
        $user = new \FakeWP_User($uid, 'member606', 'original606@example.com');
        $user->roles = ['subscriber'];
        $GLOBALS['__mm_users'][$uid] = $user;

        // PMPro passes errors as array and user as stdClass
        $errors = [];
        $user_obj = (object) ['ID' => $uid, 'user_email' => 'updated606@example.com'];
        $_POST['user_email'] = 'updated606@example.com';

        $service->intercept_pmpro_profile_update($errors, true, $user_obj);

        // Errors array should be empty (valid email)
        $this->assertEmpty($errors);

        // user_email in stdClass and $_POST must be reverted back to original
        $this->assertEquals('original606@example.com', $user_obj->user_email);
        $this->assertEquals('original606@example.com', $_POST['user_email']);

        // Usermeta holds pending new email
        $this->assertEquals('updated606@example.com', get_user_meta($uid, 'mm_pending_new_email', true));

        // OTP dispatched
        $last_mail = end($GLOBALS['__mm_sent_mails']);
        $this->assertEquals('updated606@example.com', $last_mail['to']);
    }

    public function test_pmpro_personal_options_update_hook_saves_pending_email(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 607;
        $user = new \FakeWP_User($uid, 'member607', 'old607@example.com');
        $GLOBALS['__mm_users'][$uid] = $user;

        $_POST['user_email'] = 'new607@example.com';

        $service->intercept_pmpro_personal_options_update($uid);

        $this->assertEquals('new607@example.com', get_user_meta($uid, 'mm_pending_new_email', true));
        $this->assertEquals('old607@example.com', $_POST['user_email']);
    }

    public function test_wp_pre_insert_user_data_prevents_unverified_email_update(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 608;
        $user = new \FakeWP_User($uid, 'member608', 'keep608@example.com');
        $GLOBALS['__mm_users'][$uid] = $user;

        $incoming_data = [
            'user_email' => 'sneaky608@example.com',
            'display_name' => 'Member 608',
        ];

        $filtered = $service->intercept_wp_pre_insert_user_data($incoming_data, true, $uid, []);

        // Filter should revert user_email to keep608@example.com
        $this->assertEquals('keep608@example.com', $filtered['user_email']);
        $this->assertEquals('Member 608', $filtered['display_name']);

        // Pending email should be set
        $this->assertEquals('sneaky608@example.com', get_user_meta($uid, 'mm_pending_new_email', true));
    }

    public function test_content_and_shortcode_filters_prepend_notice_banner(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 609;
        $user = new \FakeWP_User($uid, 'member609', 'old609@example.com');
        $GLOBALS['__mm_users'][$uid] = $user;
        $GLOBALS['__mm_current_user_id'] = $uid;

        update_user_meta($uid, 'mm_pending_new_email', 'pending609@example.com');

        // Content containing pmpro shortcode
        $post_content = '<p>Your account details:</p>[pmpro_account]';
        $filtered = $service->filter_the_content_for_pending_email_notice($post_content);

        $this->assertStringContainsString('mm-pending-email-notice', $filtered);
        $this->assertStringContainsString('pending609@example.com', $filtered);
        $this->assertStringContainsString('[pmpro_account]', $filtered);

        // PMPro shortcode filter
        $shortcode_output = '<div class="pmpro_account_wrap">Account Content</div>';
        $shortcode_filtered = $service->filter_pmpro_shortcode_notice($shortcode_output);
        $this->assertStringContainsString('mm-pending-email-notice', $shortcode_filtered);
    }

    public function test_dashboard_and_form_wizard_do_not_render_pending_email_notice(): void
    {
        $service = EmailVerificationService::instance();

        $uid = 610;
        $user = new \FakeWP_User($uid, 'member610', 'old610@example.com');
        $GLOBALS['__mm_users'][$uid] = $user;
        $GLOBALS['__mm_current_user_id'] = $uid;

        update_user_meta($uid, 'mm_pending_new_email', 'pending610@example.com');

        // Dashboard shortcode content
        $dashboard_content = '<p>Welcome</p>[az_profile]';
        $filtered_dashboard = $service->filter_the_content_for_pending_email_notice($dashboard_content);
        $this->assertStringNotContainsString('mm-pending-email-notice', $filtered_dashboard);

        // Member portal shortcode content
        $portal_content = '<p>Portal</p>[matchmaker_member_portal]';
        $filtered_portal = $service->filter_the_content_for_pending_email_notice($portal_content);
        $this->assertStringNotContainsString('mm-pending-email-notice', $filtered_portal);

        // Form wizard shortcode content
        $form_content = '<p>Questionnaire</p>[matchmaking_form]';
        $filtered_form = $service->filter_the_content_for_pending_email_notice($form_content);
        $this->assertStringNotContainsString('mm-pending-email-notice', $filtered_form);
    }

    public function test_custom_update_email_settings_affect_subject_and_template(): void
    {
        $service = EmailVerificationService::instance();

        update_option('mm_email_verify_update_subject', 'Confirm Security PIN: {code}');
        update_option('mm_email_verify_update_template', '<p>Hello {user_name}, update PIN for {new_email} is {code}. Valid for {expiry_hours} hours on {site_name}.</p>');

        $mail_error = null;
        $sent = $service->send_pending_email_verification('newdest@example.com', 'Aisha', '998877', $mail_error);
        $this->assertTrue($sent);

        $last_mail = end($GLOBALS['__mm_sent_mails']);
        $this->assertEquals('newdest@example.com', $last_mail['to']);
        $this->assertEquals('Confirm Security PIN: 998877', $last_mail['subject']);
        $this->assertStringContainsString('Hello Aisha', $last_mail['message']);
        $this->assertStringContainsString('newdest@example.com', $last_mail['message']);
        $this->assertStringContainsString('998877', $last_mail['message']);

        // Clean up
        delete_option('mm_email_verify_update_subject');
        delete_option('mm_email_verify_update_template');
    }

    public function test_default_emails_do_not_contain_religious_terms(): void
    {
        $service = EmailVerificationService::instance();

        delete_option('mm_email_verify_template');
        delete_option('mm_email_verify_update_template');

        // Registration OTP email
        $html1 = $service->get_email_html('Tariq', '112233', 'tariq@example.com');
        $this->assertStringNotContainsString('Assalamu', $html1);
        $this->assertStringNotContainsString('Barakallahu', $html1);
        $this->assertStringNotContainsString('Muslim', $html1);
        $this->assertStringContainsString('Hello, Tariq!', $html1);

        // Email update OTP email
        $mail_error = null;
        $service->send_pending_email_verification('tariq.new@example.com', 'Tariq', '445566', $mail_error);
        $last_mail = end($GLOBALS['__mm_sent_mails']);
        $this->assertStringNotContainsString('Assalamu', $last_mail['message']);
        $this->assertStringNotContainsString('Barakallahu', $last_mail['message']);
        $this->assertStringNotContainsString('Muslim', $last_mail['message']);
        $this->assertStringContainsString('Hello, Tariq!', $last_mail['message']);
    }
}




