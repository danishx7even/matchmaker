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
        // 1. Logged-out user should render the privacy policy checkbox
        $GLOBALS['__mm_current_user_id'] = 0;
        ob_start();
        $this->auth->render_checkout_privacy_policy_checkbox();
        $html = (string) ob_get_clean();

        if (!str_contains($html, 'id="pmpro_privacy_policy_wrapper"')) {
            throw new \RuntimeException("Expected privacy policy wrapper in HTML for logged-out user: " . $html);
        }

        if (!str_contains($html, 'name="privacy_policy_consent"')) {
            throw new \RuntimeException("Expected privacy_policy_consent input name in HTML: " . $html);
        }

        if (!str_contains($html, 'https://arabzawaj.org/privacy-policy/')) {
            throw new \RuntimeException("Expected privacy policy URL 'https://arabzawaj.org/privacy-policy/' in HTML: " . $html);
        }

        if (!str_contains($html, 'pmpro_asterisk') || !str_contains($html, '*')) {
            throw new \RuntimeException("Expected required asterisk in privacy policy checkbox: " . $html);
        }

        // 2. Logged-in user should NOT render the privacy policy checkbox
        $GLOBALS['__mm_current_user_id'] = 109;
        ob_start();
        $this->auth->render_checkout_privacy_policy_checkbox();
        $logged_in_html = (string) ob_get_clean();

        if (!empty($logged_in_html)) {
            throw new \RuntimeException("Expected no privacy policy checkbox for logged-in user, got: " . $logged_in_html);
        }

        $GLOBALS['__mm_current_user_id'] = 0;
    }

    public function test_privacy_policy_consent_validation_on_checkout(): void
    {
        // 1. For logged-out user:
        $GLOBALS['__mm_current_user_id'] = 0;
        $_POST['submit-checkout'] = '1';
        unset($_POST['privacy_policy_consent'], $_REQUEST['privacy_policy_consent']);

        // Check without consent - must fail
        $valid = $this->auth->check_privacy_policy_consent(true);
        if ($valid !== false) {
            throw new \RuntimeException("Expected logged-out checkout without privacy policy consent to fail validation.");
        }

        // Check with consent - must pass
        $_POST['privacy_policy_consent'] = '1';
        $valid_with_consent = $this->auth->check_privacy_policy_consent(true);
        if ($valid_with_consent !== true) {
            throw new \RuntimeException("Expected logged-out checkout with privacy policy consent to pass validation.");
        }

        // 2. For logged-in user (already agreed previously):
        $GLOBALS['__mm_current_user_id'] = 109;
        unset($_POST['privacy_policy_consent'], $_REQUEST['privacy_policy_consent']);
        $valid_logged_in = $this->auth->check_privacy_policy_consent(true);
        if ($valid_logged_in !== true) {
            throw new \RuntimeException("Expected logged-in checkout to pass validation without needing consent checkbox.");
        }

        unset($_POST['submit-checkout'], $_POST['privacy_policy_consent']);
        $GLOBALS['__mm_current_user_id'] = 0;
    }

    public function test_privacy_policy_consent_saved_on_checkout(): void
    {
        $user_id = 110;
        $_POST['privacy_policy_consent'] = '1';

        $this->auth->save_privacy_policy_consent_on_checkout($user_id);
        $consent_meta = get_user_meta($user_id, 'mm_privacy_policy_consent', true);

        if (empty($consent_meta)) {
            throw new \RuntimeException("Expected mm_privacy_policy_consent meta to be saved for user #{$user_id}.");
        }

        unset($_POST['privacy_policy_consent']);
    }
}


