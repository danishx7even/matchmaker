<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Frontend\AuthController;
use Matchmaker\Service\ProfileService;
use FakeWP_User;

class AuthAndRedirectsTest
{
    private AuthController $auth;
    private ProfileService $profile_service;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']   = [];
        $GLOBALS['__mm_usermeta']  = [];
        $GLOBALS['wpdb']->queries  = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];

        $this->auth = AuthController::instance();
        $this->profile_service = ProfileService::instance();
    }

    public function test_admin_login_redirects_to_wp_admin(): void
    {
        $admin_user = new FakeWP_User(1, 'admin', 'admin@example.com');
        $admin_user->roles = ['administrator'];

        $dest = $this->auth->custom_role_based_login_redirect('https://example.com/', '', $admin_user);
        if (!str_contains($dest, 'wp-admin')) {
            throw new \RuntimeException("Expected admin user to be redirected to wp-admin, got: " . $dest);
        }
    }

    public function test_member_with_completed_profile_redirects_to_dashboard(): void
    {
        $member = new FakeWP_User(50, 'subscriber50', 'sub50@example.com');
        $member->roles = ['subscriber'];

        // Mock pool record
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 50"] = [
            'user_id' => 50,
            'gender'  => 'male',
        ];

        $dest = $this->auth->custom_role_based_login_redirect('https://example.com/', '', $member);
        if (!str_contains($dest, '/dashboard/')) {
            throw new \RuntimeException("Expected member with profile to redirect to /dashboard/, got: " . $dest);
        }
    }

    public function test_member_without_profile_redirects_to_form_wizard(): void
    {
        $member = new FakeWP_User(51, 'newuser51', 'sub51@example.com');
        $member->roles = ['subscriber'];

        // Mock empty pool record
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 51"] = null;

        $dest = $this->auth->custom_role_based_login_redirect('https://example.com/', '', $member);
        if (!str_contains($dest, 'personal-matchmaking-questionnaire')) {
            throw new \RuntimeException("Expected new member without pool profile to redirect to questionnaire form, got: " . $dest);
        }
    }

    public function test_logout_url_shortcode_generates_valid_url(): void
    {
        $out = $this->auth->custom_logout_url_shortcode([]);
        if (!str_contains($out, 'action=logout')) {
            throw new \RuntimeException("Expected logout_url shortcode to output logout link: " . $out);
        }
    }

    public function test_matchmaker_admin_login_redirects_to_matchmaking_pool(): void
    {
        $mm_admin = new FakeWP_User(77, 'mm_agent', 'agent@example.com');
        $mm_admin->roles = ['matchmaker_admin'];

        $dest = $this->auth->custom_role_based_login_redirect('https://example.com/', '', $mm_admin);
        if (!str_contains($dest, 'page=matchmaking-pool')) {
            throw new \RuntimeException("Expected matchmaker_admin to redirect to page=matchmaking-pool, got: " . $dest);
        }
    }

    public function test_events_organizer_login_redirects_to_events_cpt(): void
    {
        update_option('mm_events_cpt_slug', 'tribe_events');
        $eo_user = new FakeWP_User(78, 'event_manager', 'manager@example.com');
        $eo_user->roles = ['events_organizer'];

        $dest = $this->auth->custom_role_based_login_redirect('https://example.com/', '', $eo_user);
        if (!str_contains($dest, 'edit.php?post_type=tribe_events')) {
            throw new \RuntimeException("Expected events_organizer to redirect to edit.php?post_type=tribe_events, got: " . $dest);
        }
        delete_option('mm_events_cpt_slug');
    }

    public function test_pmpro_profile_update_redirects_to_membership_account_page(): void
    {
        $_POST['action'] = 'update-profile';
        $GLOBALS['__mm_last_redirect'] = '';

        $this->auth->redirect_after_pmpro_profile_update(50);

        if (empty($GLOBALS['__mm_last_redirect']) || !str_contains($GLOBALS['__mm_last_redirect'], 'account')) {
            throw new \RuntimeException("Expected redirect to membership account, got: " . ($GLOBALS['__mm_last_redirect'] ?? 'none'));
        }

        unset($_POST['action'], $GLOBALS['__mm_last_redirect']);
    }

    public function test_pmpro_service_checkout_redirects_to_dashboard_and_membership_redirects_to_questionnaire(): void
    {
        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [4, 6],
        ]);

        $membership_level = (object) ['id' => 2, 'name' => 'Monthly Matchmaking'];
        $service_level    = (object) ['id' => 6, 'name' => 'Social Media Post'];

        $mem_redirect = $this->auth->custom_pmpro_level_based_registration_redirect('https://example.com/default/', 10, $membership_level);
        $srv_redirect = $this->auth->custom_pmpro_level_based_registration_redirect('https://example.com/default/', 10, $service_level);

        if (!str_contains($mem_redirect, 'personal-matchmaking-questionnaire')) {
            throw new \RuntimeException("Expected membership checkout to redirect to questionnaire form, got: " . $mem_redirect);
        }

        if (!str_contains($srv_redirect, '/dashboard/')) {
            throw new \RuntimeException("Expected service checkout to redirect to /dashboard/, got: " . $srv_redirect);
        }
    }

    public function test_pmpro_profile_fields_includes_username(): void
    {
        $fields = [
            'first_name'   => 'First Name',
            'last_name'    => 'Last Name',
            'display_name' => 'Display Name',
            'user_email'   => 'Email',
        ];

        $filtered = $this->auth->add_username_to_pmpro_profile_fields($fields);

        if (!isset($filtered['user_login']) || $filtered['user_login'] !== 'Username') {
            throw new \RuntimeException("Expected 'user_login' field with label 'Username' in PMPro profile fields");
        }
    }

    public function test_username_change_rejects_spaces(): void
    {
        $user_id = 105;
        $GLOBALS['__mm_users'][$user_id] = new FakeWP_User($user_id, 'Old User', 'user105@example.com', 'olduser');

        $_POST['user_login'] = 'new username with space';
        $errors = [];
        $std_user = new \stdClass();
        $std_user->ID = $user_id;

        $this->auth->validate_and_save_pmpro_username_update($errors, true, $std_user);

        if (empty($errors) || !str_contains($errors[0], 'spaces')) {
            throw new \RuntimeException("Expected error rejecting spaces in username, got: " . json_encode($errors));
        }

        unset($_POST['user_login']);
    }

    public function test_username_change_rejects_duplicates(): void
    {
        $user_id_1 = 106;
        $user_id_2 = 107;
        $GLOBALS['__mm_users'][$user_id_1] = new FakeWP_User($user_id_1, 'Existing User', 'user106@example.com', 'existinguser');
        $GLOBALS['__mm_users'][$user_id_2] = new FakeWP_User($user_id_2, 'Current User', 'user107@example.com', 'currentuser');

        $_POST['user_login'] = 'existinguser';
        $errors = [];
        $std_user = new \stdClass();
        $std_user->ID = $user_id_2;

        $this->auth->validate_and_save_pmpro_username_update($errors, true, $std_user);

        if (empty($errors) || !str_contains($errors[0], 'taken')) {
            throw new \RuntimeException("Expected error rejecting taken username, got: " . json_encode($errors));
        }

        unset($_POST['user_login']);
    }

    public function test_username_change_rejects_short_or_invalid(): void
    {
        $user_id = 108;
        $GLOBALS['__mm_users'][$user_id] = new FakeWP_User($user_id, 'Valid User', 'user108@example.com', 'validuser');

        // Test < 3 chars
        $_POST['user_login'] = 'ab';
        $errors = [];
        $std_user = new \stdClass();
        $std_user->ID = $user_id;

        $this->auth->validate_and_save_pmpro_username_update($errors, true, $std_user);
        if (empty($errors) || !str_contains($errors[0], 'between 3 and 60')) {
            throw new \RuntimeException("Expected error for short username, got: " . json_encode($errors));
        }

        // Test invalid chars (e.g. #$%)
        $_POST['user_login'] = 'invalid#user%';
        $errors = [];
        $this->auth->validate_and_save_pmpro_username_update($errors, true, $std_user);
        if (empty($errors) || !str_contains($errors[0], 'invalid characters')) {
            throw new \RuntimeException("Expected error for invalid username characters, got: " . json_encode($errors));
        }

        unset($_POST['user_login']);
    }

    public function test_username_change_updates_login_and_nicename_safely(): void
    {
        $user_id = 109;
        $GLOBALS['__mm_users'][$user_id] = new FakeWP_User($user_id, 'Old Username', 'user109@example.com', 'oldusername');
        $GLOBALS['__mm_current_user_id'] = $user_id;

        $_POST['user_login'] = 'new-awesome-username';
        $errors = [];
        $std_user = new \stdClass();
        $std_user->ID = $user_id;

        $this->auth->validate_and_save_pmpro_username_update($errors, true, $std_user);

        if (!empty($errors)) {
            throw new \RuntimeException("Expected no errors on valid username update, got: " . json_encode($errors));
        }

        $updated_user = $GLOBALS['__mm_users'][$user_id];
        if ($updated_user->user_login !== 'new-awesome-username') {
            throw new \RuntimeException("Expected user_login to be 'new-awesome-username', got: " . $updated_user->user_login);
        }

        if ($updated_user->user_nicename !== 'new-awesome-username') {
            throw new \RuntimeException("Expected user_nicename to be 'new-awesome-username', got: " . ($updated_user->user_nicename ?? ''));
        }

        unset($_POST['user_login']);
    }

    public function test_privacy_policy_checkbox_rendering_markup(): void
    {
        // 1. Logged-out user should render the full compliance fields (DOB, Age Confirm, Privacy Policy, AUP)
        $GLOBALS['__mm_current_user_id'] = 0;
        ob_start();
        $this->auth->render_checkout_privacy_policy_checkbox();
        $html = (string) ob_get_clean();

        if (!str_contains($html, 'id="pmpro_privacy_policy_wrapper"')) {
            throw new \RuntimeException("Expected compliance wrapper in HTML for logged-out user: " . $html);
        }

        // DOB field
        if (!str_contains($html, 'name="user_dob"') || !str_contains($html, 'Date of Birth')) {
            throw new \RuntimeException("Expected user_dob date field in HTML: " . $html);
        }

        // Confirm your age card
        if (!str_contains($html, 'Confirm your age') || !str_contains($html, 'name="mm_age_confirmed"')) {
            throw new \RuntimeException("Expected Confirm your age section and checkbox in HTML: " . $html);
        }
        if (!str_contains($html, 'You must be at least 18 years old and have reached the age of legal majority')) {
            throw new \RuntimeException("Expected age confirmation descriptive text in HTML: " . $html);
        }

        // Privacy Policy checkbox
        if (!str_contains($html, 'name="privacy_policy_consent"') || !str_contains($html, 'privacy-policy/')) {
            throw new \RuntimeException("Expected privacy_policy_consent input name and URL in HTML: " . $html);
        }

        // AUP checkbox
        if (!str_contains($html, 'name="mm_aup_consent"') || !str_contains($html, 'law-enforcement-requests-member-safety-policy/')) {
            throw new \RuntimeException("Expected AUP checkbox and link in HTML: " . $html);
        }

        if (!str_contains($html, 'pmpro_asterisk') || !str_contains($html, '*')) {
            throw new \RuntimeException("Expected required asterisk in compliance fields: " . $html);
        }

        // 2. Logged-in user should NOT render the compliance fields
        $GLOBALS['__mm_current_user_id'] = 109;
        ob_start();
        $this->auth->render_checkout_privacy_policy_checkbox();
        $logged_in_html = (string) ob_get_clean();

        if (!empty($logged_in_html)) {
            throw new \RuntimeException("Expected no compliance fields for logged-in user, got: " . $logged_in_html);
        }

        $GLOBALS['__mm_current_user_id'] = 0;
    }

    public function test_privacy_policy_consent_validation_on_checkout(): void
    {
        // 1. For logged-out user:
        $GLOBALS['__mm_current_user_id'] = 0;
        $_POST['submit-checkout'] = '1';

        // Case A: Missing DOB
        unset($_POST['user_dob'], $_POST['mm_age_confirmed'], $_POST['privacy_policy_consent'], $_POST['mm_aup_consent']);
        $valid = $this->auth->check_privacy_policy_consent(true);
        if ($valid !== false) {
            throw new \RuntimeException("Expected logged-out checkout without DOB to fail validation.");
        }

        // Case B: Underage (< 18)
        $underage_year = (int) date('Y') - 16;
        $_POST['user_dob'] = "{$underage_year}-05-15";
        $_POST['mm_age_confirmed'] = '1';
        $_POST['privacy_policy_consent'] = '1';
        $_POST['mm_aup_consent'] = '1';
        $valid_underage = $this->auth->check_privacy_policy_consent(true);
        if ($valid_underage !== false) {
            throw new \RuntimeException("Expected underage user (<18) to fail registration validation.");
        }
        global $pmpro_msg;
        if ($pmpro_msg !== 'You do not meet the minimum age requirement to use Arab Zawaj.') {
            throw new \RuntimeException("Expected exact underage error message, got: " . $pmpro_msg);
        }

        // Case C: Valid DOB (25 yrs), but missing age confirm checkbox
        $valid_year = (int) date('Y') - 25;
        $_POST['user_dob'] = "{$valid_year}-01-01";
        unset($_POST['mm_age_confirmed']);
        $valid_no_age_chk = $this->auth->check_privacy_policy_consent(true);
        if ($valid_no_age_chk !== false || $pmpro_msg !== 'You must confirm that you meet the age requirements.') {
            throw new \RuntimeException("Expected missing age confirmation error, got: " . $pmpro_msg);
        }

        // Case D: Valid DOB & age confirm, but missing Privacy Policy
        $_POST['mm_age_confirmed'] = '1';
        unset($_POST['privacy_policy_consent']);
        $valid_no_privacy = $this->auth->check_privacy_policy_consent(true);
        if ($valid_no_privacy !== false || $pmpro_msg !== 'You must agree to the Privacy Policy to complete your registration.') {
            throw new \RuntimeException("Expected missing privacy policy error, got: " . $pmpro_msg);
        }

        // Case E: Valid DOB, age confirm, privacy policy, but missing AUP
        $_POST['privacy_policy_consent'] = '1';
        unset($_POST['mm_aup_consent']);
        $valid_no_aup = $this->auth->check_privacy_policy_consent(true);
        if ($valid_no_aup !== false || $pmpro_msg !== 'You must agree to the Acceptable Use Policy (AUP) to complete your registration.') {
            throw new \RuntimeException("Expected missing AUP error, got: " . $pmpro_msg);
        }

        // Case F: All valid (DOB 25yo, age confirmed, privacy consent, AUP consent) - MUST PASS
        $_POST['mm_aup_consent'] = '1';
        $valid_all = $this->auth->check_privacy_policy_consent(true);
        if ($valid_all !== true) {
            throw new \RuntimeException("Expected valid checkout submission to pass validation.");
        }

        // 2. For logged-in user (already agreed previously):
        $GLOBALS['__mm_current_user_id'] = 109;
        unset($_POST['user_dob'], $_POST['mm_age_confirmed'], $_POST['privacy_policy_consent'], $_POST['mm_aup_consent']);
        $valid_logged_in = $this->auth->check_privacy_policy_consent(true);
        if ($valid_logged_in !== true) {
            throw new \RuntimeException("Expected logged-in checkout to pass validation without re-prompting.");
        }

        unset($_POST['submit-checkout'], $_POST['user_dob'], $_POST['mm_age_confirmed'], $_POST['privacy_policy_consent'], $_POST['mm_aup_consent']);
        $GLOBALS['__mm_current_user_id'] = 0;
    }

    public function test_privacy_policy_consent_saved_on_checkout(): void
    {
        $user_id = 110;
        $_POST['user_dob'] = '1996-08-14';
        $_POST['mm_age_confirmed'] = '1';
        $_POST['privacy_policy_consent'] = '1';
        $_POST['mm_aup_consent'] = '1';

        $this->auth->save_privacy_policy_consent_on_checkout($user_id);

        $saved_dob       = get_user_meta($user_id, 'user_dob', true);
        $saved_age_conf  = get_user_meta($user_id, 'mm_age_confirmed', true);
        $saved_privacy   = get_user_meta($user_id, 'mm_privacy_policy_consent', true);
        $saved_aup       = get_user_meta($user_id, 'mm_aup_consent', true);

        if ($saved_dob !== '1996-08-14') {
            throw new \RuntimeException("Expected user_dob '1996-08-14' saved for user #{$user_id}, got: " . var_export($saved_dob, true));
        }

        if (empty($saved_age_conf)) {
            throw new \RuntimeException("Expected mm_age_confirmed meta saved for user #{$user_id}.");
        }

        if (empty($saved_privacy)) {
            throw new \RuntimeException("Expected mm_privacy_policy_consent meta saved for user #{$user_id}.");
        }

        if (empty($saved_aup)) {
            throw new \RuntimeException("Expected mm_aup_consent meta saved for user #{$user_id}.");
        }

        unset($_POST['user_dob'], $_POST['mm_age_confirmed'], $_POST['privacy_policy_consent'], $_POST['mm_aup_consent']);
    }

    public function test_pmpro_login_page_design_does_not_contain_username_edit_validation(): void
    {
        ob_start();
        $this->auth->custom_pmpro_login_page_design();
        $html = (string) ob_get_clean();

        // Must contain login title, subtitle, and signup link
        if (!str_contains($html, 'Sign Into Your Account')) {
            throw new \RuntimeException("Expected login page to contain 'Sign Into Your Account', got: " . $html);
        }
        if (!str_contains($html, 'Please enter your email and password below.')) {
            throw new \RuntimeException("Expected login page subtitle in markup.");
        }

        // Must NOT contain username validation / restrictions / pattern / hint
        if (str_contains($html, 'No spaces allowed') || str_contains($html, 'input#user_login') || str_contains($html, 'mm-username-hint')) {
            throw new \RuntimeException("PMPro login page design must NOT contain username edit restrictions or hints.");
        }
    }

    public function test_pmpro_profile_edit_username_script_scopes_strictly_to_profile_form(): void
    {
        ob_start();
        $this->auth->custom_pmpro_profile_edit_username_script();
        $html = (string) ob_get_clean();

        // Must scope strictly to member profile edit forms
        if (!str_contains($html, '#pmpro_member_profile_edit') || !str_contains($html, '#member-profile-edit')) {
            throw new \RuntimeException("Expected profile edit script to scope to #pmpro_member_profile_edit and #member-profile-edit.");
        }
        if (!str_contains($html, 'mm-username-hint') || !str_contains($html, 'No spaces allowed')) {
            throw new \RuntimeException("Expected profile edit script to include hint and space prevention.");
        }

        // Must NOT target global input#user_login without profile form scope
        if (str_contains($html, ', input#user_login\');') || str_contains($html, ', input#user_login"')) {
            throw new \RuntimeException("Profile edit script must not match generic input#user_login on login pages.");
        }
    }

    public function test_exact_age_boundaries_and_profile_dob_sync(): void
    {
        $GLOBALS['__mm_current_user_id'] = 0;
        $_POST['submit-checkout']        = '1';
        $_POST['mm_age_confirmed']       = '1';
        $_POST['privacy_policy_consent'] = '1';
        $_POST['mm_aup_consent']         = '1';

        // 1. Exactly 18 years old today -> MUST PASS
        $exact_18 = (new \DateTime('today'))->sub(new \DateInterval('P18Y'))->format('Y-m-d');
        $_POST['user_dob'] = $exact_18;
        $pass_18 = $this->auth->check_privacy_policy_consent(true);
        if ($pass_18 !== true) {
            throw new \RuntimeException("Expected exactly 18-year-old user to pass age check.");
        }

        // 2. 17 years and 364 days old (born 18 years ago tomorrow) -> MUST FAIL
        $almost_18 = (new \DateTime('tomorrow'))->sub(new \DateInterval('P18Y'))->format('Y-m-d');
        $_POST['user_dob'] = $almost_18;
        $fail_17 = $this->auth->check_privacy_policy_consent(true);
        if ($fail_17 !== false) {
            throw new \RuntimeException("Expected 17-year-old user to fail age check.");
        }

        // 3. User Register hook saves DOB & compliance metas
        $user_id = 115;
        $_POST['user_dob'] = '1998-11-20';
        $this->auth->save_privacy_policy_consent_on_user_register($user_id);

        if (get_user_meta($user_id, 'user_dob', true) !== '1998-11-20') {
            throw new \RuntimeException("Expected user_dob to be saved on user_register.");
        }
        if (empty(get_user_meta($user_id, 'mm_age_confirmed', true))) {
            throw new \RuntimeException("Expected mm_age_confirmed to be saved on user_register.");
        }
        if (empty(get_user_meta($user_id, 'mm_aup_consent', true))) {
            throw new \RuntimeException("Expected mm_aup_consent to be saved on user_register.");
        }

        unset($_POST['submit-checkout'], $_POST['user_dob'], $_POST['mm_age_confirmed'], $_POST['privacy_policy_consent'], $_POST['mm_aup_consent']);
    }
}


