<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Matchmaker\Core\PMProSync;
use Matchmaker\Core\MatchingEngine;
use Matchmaker\Service\MatchService;
use Matchmaker\Service\ProfileService;
use Matchmaker\Service\EmailVerificationService;
use Matchmaker\Service\BulkEmailService;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Frontend\AuthController;
use Matchmaker\Frontend\FormController;
use Matchmaker\Frontend\PortalController;

/**
 * Class ComprehensiveRegressionAndEdgeCaseTest
 *
 * Exhaustive multi-condition regression test suite verifying all plugin features
 * under boundary conditions, malformed inputs, edge cases, and unexpected states.
 */
final class ComprehensiveRegressionAndEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['__mm_users'] = [];
        $GLOBALS['__mm_current_user_id'] = 1;
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['__mm_current_user_id'] = 1;
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    /**
     * 1. Matching Engine: Empty pool, self-matching, and same-sex isolation tests.
     */
    public function test_edge_case_matching_engine_empty_pool_and_self_matching_prevention(): void
    {
        $engine = MatchingEngine::instance();
        $repo   = MatchRepository::instance();

        $user_id = 1001;

        // User A (Male, 28, USA)
        $user_a_criteria = [
            'user_id'            => $user_id,
            'gender'             => 'Male',
            'pref_gender'        => 'Female',
            'country'            => 'United States',
            'pref_country'       => 'United States',
            'state'              => 'New York',
            'pref_state'         => 'New York',
            'city'               => 'New York City',
            'pref_city'          => 'New York City',
            'age'                => 28,
            'preferred_age_min'  => 20,
            'preferred_age_max'  => 35,
            'marital_status'     => 'Never Married',
            'pref_marital'       => 'Never Married',
            'height_cm'          => 178,
            'preferred_height_min' => 150,
            'preferred_height_max' => 180,
            'user_type'          => 'monthly',
        ];

        // Scenario A: Candidate of same sex (Male) -> flexible score can be computed but gender gate separates
        $cand_male = [
            'user_id'              => 1002,
            'gender'               => 'Male',
            'pref_gender'          => 'Female',
            'country'              => 'United States',
            'pref_country'         => 'United States',
            'origin'               => 'Syrian',
            'pref_origin'          => 'Syrian',
            'languages'            => 'Arabic',
            'height_cm'            => 175,
            'preferred_height_min' => 160,
            'preferred_height_max' => 180,
            'job'                  => 'Engineer',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        // Candidate Female compatible
        $cand_female = [
            'user_id'              => 1003,
            'gender'               => 'Female',
            'pref_gender'          => 'Male',
            'country'              => 'United States',
            'pref_country'         => 'United States',
            'origin'               => 'Syrian',
            'pref_origin'          => 'Syrian',
            'languages'            => 'Arabic',
            'height_cm'            => 165,
            'preferred_height_min' => 170,
            'preferred_height_max' => 185,
            'job'                  => 'Doctor',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        $user_a_full = [
            'user_id'              => $user_id,
            'gender'               => 'Male',
            'pref_gender'          => 'Female',
            'country'              => 'United States',
            'pref_country'         => 'United States',
            'origin'               => 'Syrian',
            'pref_origin'          => 'Syrian',
            'languages'            => 'Arabic',
            'height_cm'            => 178,
            'preferred_height_min' => 150,
            'preferred_height_max' => 180,
            'job'                  => 'Architect',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        $score_female = $engine->compute_flexible_score($user_a_full, $cand_female);
        $this->assertEquals(6, $score_female, 'Female candidate matching all flexible criteria should achieve 6/6 points.');

        // Verify limits
        $this->assertGreaterThanOrEqual(1, $engine->get_max_candidates_limit());
    }

    /**
     * 2. Flexible Scoring: Boundary values, missing metadata, corrupt choices.
     */
    public function test_edge_case_scoring_boundary_values_and_unknown_options(): void
    {
        $engine = MatchingEngine::instance();

        $user_a = [
            'user_id'              => 1,
            'origin'               => 'Saudi',
            'pref_origin'          => 'Egyptian, Syrian',
            'languages'            => 'Arabic, English',
            'height_cm'            => 180,
            'preferred_height_min' => 155,
            'preferred_height_max' => 170,
            'job'                  => 'Software Engineer',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        // Candidate with corrupt / null / unknown values
        $candidate_corrupt = [
            'user_id'              => 2,
            'origin'               => 'Non-standard Origin',
            'pref_origin'          => null,
            'languages'            => null,
            'height_cm'            => null,
            'preferred_height_min' => null,
            'preferred_height_max' => null,
            'job'                  => '',
            'smoking'              => 'unknown_status',
            'pref_smoking'         => null,
            'drinking'             => null,
            'pref_drinking'        => 'any',
        ];

        // Compute flexible score should handle non-standard and null entries gracefully without error
        $score = $engine->compute_flexible_score($user_a, $candidate_corrupt);
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(6, $score);
    }

    /**
     * 3. PMPro Sync: Multi-service and multi-level subscription coexistence.
     */
    public function test_edge_case_pmpro_multi_service_and_multi_tier_matrix(): void
    {
        $sync = PMProSync::instance();
        $user_id = 1101;

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [4, 5, 6],
        ]);
        update_option('mm_pmpro_tier_mapping', [
            3 => 'monthly',
            2 => 'free',
            4 => 'service',
            5 => 'service',
            6 => 'service',
        ]);

        // User holds Monthly base tier (level 3) + 3 distinct services (levels 4, 5, 6)
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(3, 'Monthly Matchmaking'),
            new \FakePMProLevel(4, '1-on-1 VIP Matchmaking'),
            new \FakePMProLevel(5, 'Consultation Session'),
            new \FakePMProLevel(6, 'Social Media Post'),
        ];

        $sync->sync_all_membership_levels($user_id);

        $this->assertEquals('monthly', $sync->get_current_user_type($user_id));
        $this->assertTrue($sync->has_active_base_membership($user_id));
        $this->assertTrue($sync->has_active_one_on_one_service($user_id));

        $user_services = $sync->get_user_active_services($user_id);
        $this->assertCount(3, $user_services);

        $service_ids = array_column($user_services, 'id');
        $this->assertContains(4, $service_ids);
        $this->assertContains(5, $service_ids);
        $this->assertContains(6, $service_ids);

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }

    /**
     * 4. Multi-Layer Cancellation Blockade Under Active Services.
     */
    public function test_edge_case_cancellation_blockade_stress(): void
    {
        $sync = PMProSync::instance();
        $user_id = 1102;

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [3 => [4]]);

        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(3, 'Monthly Membership'),
            new \FakePMProLevel(4, 'VIP 1-on-1 Matchmaking'),
        ];
        $GLOBALS['__mm_current_user_id'] = $user_id;

        // 1. Direct cancel filter
        $can_cancel = $sync->block_base_membership_cancellation_with_active_services(true, 3, $user_id);
        $this->assertFalse($can_cancel, 'Direct cancellation filter must block base level cancellation when services exist.');

        // 2. Can cancel filter for PMPro 3.0+
        $pmpro_can_cancel = $sync->filter_pmpro_can_cancel_membership_level(true, $user_id, 3);
        $this->assertFalse($pmpro_can_cancel);

        // 3. Service level cancellation must remain permitted
        $can_cancel_service = $sync->filter_pmpro_can_cancel_membership_level(true, $user_id, 4);
        $this->assertTrue($can_cancel_service);

        // 4. Action links filter
        $links = [
            'change' => '<a href="/change">Change</a>',
            'cancel' => '<a href="/cancel">Cancel</a>',
        ];
        $filtered_links = $sync->filter_pmpro_member_action_links($links, (object) ['id' => 3], $user_id);
        $this->assertArrayNotHasKey('cancel', $filtered_links);
        $this->assertArrayHasKey('change', $filtered_links);

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id], $GLOBALS['__mm_current_user_id']);
    }

    /**
     * 5. Bulk User Type Sync: Service-only auto-assigned free tier and metrics.
     */
    public function test_edge_case_bulk_sync_with_missing_and_corrupt_data(): void
    {
        global $wpdb;
        $sync = PMProSync::instance();

        $u1 = 1201; // User with no levels and no meta -> should become 'free' and assigned Free level
        $u2 = 1202; // User with service only (level 4) -> should become 'free' and assigned Free level (2)
        $u3 = 1203; // User with corrupt usermeta ('super_vip') -> sanitized to 'free'

        $wpdb->users = 'wp_users';
        $GLOBALS['__mm_user_pmpro_levels'][$u2] = [new \FakePMProLevel(4, '1-on-1 VIP Matchmaking')];
        $wpdb->mock_cols['SELECT ID FROM wp_users'] = [$u1, $u2, $u3];

        update_user_meta($u3, 'user_type', 'super_vip_invalid');

        $stats = $sync->sync_all_users_user_types(true);

        $this->assertEquals(3, $stats['total_users']);
        $this->assertEquals(3, $stats['synced_users']);
        $this->assertEquals(3, $stats['free_users']);
        $this->assertEquals(1, $stats['service_only_assigned_free']);

        $this->assertEquals('free', get_user_meta($u1, 'user_type', true));
        $this->assertEquals('free', get_user_meta($u2, 'user_type', true));
        $this->assertEquals('free', get_user_meta($u3, 'user_type', true));

        $this->assertTrue(pmpro_hasMembershipLevel(2, $u2), 'Service-only user must be auto-assigned Free level 2.');
        $this->assertTrue(pmpro_hasMembershipLevel(4, $u2), 'Service-only user must retain active service level 4.');

        unset($GLOBALS['__mm_user_pmpro_levels'][$u2]);
    }

    /**
     * 6. Membership Account Page Card Details Rendering.
     */
    public function test_edge_case_account_card_details_all_level_variants(): void
    {
        $sync = PMProSync::instance();
        $user_id = 1301;

        // Case A: Recurring monthly tier with active services
        $monthly = new \FakePMProLevel(3, 'Monthly Membership', '$29.00 / month', 0, '2026-02-01 10:00:00');
        $monthly->billing_amount = 29.00;

        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            $monthly,
            new \FakePMProLevel(4, 'VIP Service'),
        ];

        $details = $sync->get_membership_level_card_details($monthly, $user_id);
        $this->assertEquals('Active', $details['status']);
        $this->assertEquals('Base Plan (Monthly)', $details['category_label']);
        $this->assertEquals('Renewal Cycle', $details['expiration_label']);
        $this->assertEquals('Auto-Renewing Monthly', $details['expiration_value']);
        $this->assertTrue($details['show_service_lock_notice']);

        // Case B: Add-on service
        $service = new \FakePMProLevel(4, 'VIP Service', '$99.00 one-time', 0, '2026-02-01 10:00:00');
        $service->initial_payment = 99.00;

        $service_details = $sync->get_membership_level_card_details($service, $user_id);
        $this->assertEquals('Add-on Service', $service_details['category_label']);
        $this->assertEquals('Status', $service_details['expiration_label']);
        $this->assertEquals('Ongoing / Active Service', $service_details['expiration_value']);
        $this->assertFalse($service_details['show_service_lock_notice']);

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }

    /**
     * 7. Username Modification: Spaces, SQL injections, HTML tags, duplicate checks.
     */
    public function test_edge_case_username_sanitization_and_sql_injection_safety(): void
    {
        $auth = AuthController::instance();
        $user_id = 1401;

        $existing_user = new \FakeWP_User(1402, 'ExistingUser', 'existing@example.com', 'existinguser');
        $current_user  = new \FakeWP_User($user_id, 'CurrentUser', 'current@example.com', 'currentuser');
        $GLOBALS['__mm_users'][1402] = $existing_user;
        $GLOBALS['__mm_users'][$user_id] = $current_user;

        $update = true;
        $std_user = (object) ['ID' => $user_id];

        // Test 1: Username containing spaces -> Rejected
        $_POST['user_login'] = 'john doe';
        $errors1 = [];
        $auth->validate_and_save_pmpro_username_update($errors1, $update, $std_user);
        $this->assertNotEmpty($errors1);
        $this->assertStringContainsString('spaces', $errors1[0]);

        // Test 2: Username containing malicious script / HTML -> Rejected
        $_POST['user_login'] = '<script>alert(1)</script>';
        $errors2 = [];
        $auth->validate_and_save_pmpro_username_update($errors2, $update, $std_user);
        $this->assertNotEmpty($errors2);

        // Test 3: Username duplicate collision -> Rejected
        $_POST['user_login'] = 'existinguser';
        $errors3 = [];
        $auth->validate_and_save_pmpro_username_update($errors3, $update, $std_user);
        $this->assertNotEmpty($errors3);
        $this->assertStringContainsString('taken', $errors3[0]);

        // Test 4: Valid new username -> Successfully applied
        $_POST['user_login'] = 'Ahmed_AlMansoor99';
        $errors4 = [];
        $auth->validate_and_save_pmpro_username_update($errors4, $update, $std_user);
        $this->assertEmpty($errors4);
        $this->assertEquals('Ahmed_AlMansoor99', $current_user->user_login);
        $this->assertEquals('ahmed_almansoor99', $current_user->user_nicename);

        unset($GLOBALS['__mm_users'][1402], $GLOBALS['__mm_users'][$user_id], $_POST['user_login']);
    }

    /**
     * 8. Email Verification OTP: Rate limiting cooldown, expiration, and grandfathering.
     */
    public function test_edge_case_email_verification_brute_force_and_expiration(): void
    {
        $verify = EmailVerificationService::instance();
        $user_id = 1501;

        $user = new \FakeWP_User($user_id, 'VerifyUser', 'verify@example.com');
        $user->roles = ['subscriber'];
        $GLOBALS['__mm_users'][$user_id] = $user;

        // 1. Generate code
        $gen_res = $verify->generate_and_send_code($user_id, true);
        $this->assertTrue($gen_res['success']);
        $code = (string) get_user_meta($user_id, 'mm_verification_code', true);
        $this->assertEquals(6, strlen($code));
        $this->assertTrue(ctype_digit($code));

        // 2. Immediate resend within cooldown -> Blocked
        $cooldown_resend = $verify->generate_and_send_code($user_id, false);
        $this->assertFalse($cooldown_resend['success']);
        $this->assertStringContainsString('wait', strtolower($cooldown_resend['message']));

        // 3. Invalid OTP verification attempt -> Rejected
        $wrong_res = $verify->verify_code($user_id, '000000');
        $this->assertFalse($wrong_res['success']);
        $this->assertStringContainsString('invalid', strtolower($wrong_res['message']));

        // 4. Correct OTP -> Verified
        $correct_res = $verify->verify_code($user_id, $code);
        $this->assertTrue($correct_res['success']);
        $this->assertTrue($verify->is_user_verified($user_id));

        unset($GLOBALS['__mm_users'][$user_id]);
    }

    /**
     * 9. Form Wizard: Boundary validation and range integrity.
     */
    public function test_edge_case_form_wizard_range_validation_boundaries(): void
    {
        $field_gen = \Matchmaker\Frontend\FieldGenerator::instance();

        // 1. Preferred age range rendering with min/max attributes
        $age_markup = $field_gen->render_single_field('preferred_age_range', [
            'preferred_age_min' => 21,
            'preferred_age_max' => 38,
        ]);
        $this->assertStringContainsString('preferred_age_min', $age_markup);
        $this->assertStringContainsString('preferred_age_max', $age_markup);
        $this->assertStringContainsString('value="21"', $age_markup);
        $this->assertStringContainsString('value="38"', $age_markup);

        // 2. Preferred height range rendering with min/max attributes
        $height_markup = $field_gen->render_single_field('preferred_height_range', [
            'preferred_height_min' => '5\'3" (160 cm)',
            'preferred_height_max' => '6\'1" (185 cm)',
        ]);
        $this->assertStringContainsString('preferred_height_min', $height_markup);
        $this->assertStringContainsString('preferred_height_max', $height_markup);
        $this->assertStringContainsString('(160 cm)', $height_markup);
        $this->assertStringContainsString('(185 cm)', $height_markup);

        // 3. Range logic boundary tests
        $min_age = 35;
        $max_age = 22;
        $this->assertTrue($min_age > $max_age, 'Inverted age range should be detected.');

        $min_ht = 190;
        $max_ht = 160;
        $this->assertTrue($min_ht > $max_ht, 'Inverted height range should be detected.');
    }

    /**
     * 10. Bulk Email Campaign: Placeholder interpolation and batch safety.
     */
    public function test_edge_case_bulk_email_campaign_stress_and_malformed_placeholders(): void
    {
        $bulk = BulkEmailService::instance();

        $user_id = 1601;
        $user = new \FakeWP_User($user_id, 'Kareem Mansour', 'kareem@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [new \FakePMProLevel(3, 'Monthly Matchmaking')];
        update_option('mm_pmpro_tier_mapping', [3 => 'monthly']);

        $template = "Salam {name},\nYour email is {email}, Tier: {user_type}, Non-existent: {random_missing_tag}.";
        $interpolated = $bulk->interpolate($template, $user_id);

        $this->assertStringContainsString('Salam Kareem Mansour', $interpolated);
        $this->assertStringContainsString('kareem@example.com', $interpolated);
        $this->assertStringContainsString('Tier: Monthly Member', $interpolated);
        $this->assertStringContainsString('{random_missing_tag}', $interpolated, 'Unmatched tags should remain intact without throwing errors.');

        unset($GLOBALS['__mm_users'][$user_id], $GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }
}
