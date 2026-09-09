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
}

