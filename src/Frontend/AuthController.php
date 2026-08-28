<?php
declare(strict_types=1);
namespace Matchmaker\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AuthController
 *
 * Handles all authentication-related redirects and login page customizations.
 *
 * - Redirects wp-login.php to PMPro login page.
 * - Provides role-based login redirect (admins → wp-admin, members → /dashboard/).
 * - Redirects PMPro checkout confirmation to the questionnaire form.
 * - Hides the admin bar for subscribers.
 * - Customizes the PMPro login page UI with JS/CSS injection.
 * - Registers the [logout_url] shortcode.
 *
 * @package Matchmaker\Frontend
 * @since   1.0.0
 */
class AuthController
{
    private static ?self $instance = null;

    /**
     * @return static
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->boot();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function boot(): void
    {
        add_filter('login_url',                            [$this, 'custom_pmpro_login_url'], 10, 3);
        add_action('login_init',                           [$this, 'custom_pmpro_redirect_wp_login']);
        add_shortcode('logout_url',                        [$this, 'custom_logout_url_shortcode']);
        add_action('wp_logout',                            [$this, 'custom_logout_redirect']);
        add_filter('login_redirect',                       [$this, 'custom_role_based_login_redirect'], 10, 3);
        add_filter('pmpro_confirmation_url',               [$this, 'custom_pmpro_level_based_registration_redirect'], 10, 3);
        add_filter('show_admin_bar',                       [$this, 'custom_hide_admin_bar_for_subscribers']);
        add_action('wp_footer',                            [$this, 'custom_pmpro_login_page_design']);
    }

    /**
     * Override the default login URL to use PMPro's login page when available.
     *
     * @param string $login_url     The default login URL.
     * @param string $redirect      Optional redirect URL after login.
     * @param bool   $force_reauth  Whether to force re-authentication.
     * @return string Modified login URL.
     */
    public function custom_pmpro_login_url(string $login_url, string $redirect, bool $force_reauth): string
    {
        if (function_exists('pmpro_url')) {
            $pmpro_login = pmpro_url('login');
            if (!empty($pmpro_login)) {
                if (!empty($redirect)) {
                    $pmpro_login = add_query_arg('redirect_to', $redirect, $pmpro_login);
                }
                return $pmpro_login;
            }
        }
        return $login_url;
    }

    /**
     * Redirect GET requests to wp-login.php to the PMPro login page.
     *
     * Allows pass-through for logout, password reset, and registration actions.
     *
     * @return void
     */
    public function custom_pmpro_redirect_wp_login(): void
    {
        global $pagenow;
        if ('wp-login.php' !== $pagenow || 'GET' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        $action = isset($_REQUEST['action'])
            ? sanitize_text_field(wp_unslash($_REQUEST['action']))
            : '';

        $allowed_actions = ['logout', 'lostpassword', 'rp', 'resetpass', 'register', 'postpass'];
        if (in_array($action, $allowed_actions, true)) {
            return;
        }

        if (function_exists('pmpro_url')) {
            $pmpro_login = pmpro_url('login');
            if (!empty($pmpro_login)) {
                $redirect_to = !empty($_REQUEST['redirect_to'])
                    ? esc_url_raw(wp_unslash($_REQUEST['redirect_to']))
                    : '';

                if (!empty($redirect_to)) {
                    $pmpro_login = add_query_arg('redirect_to', $redirect_to, $pmpro_login);
                }

                wp_safe_redirect($pmpro_login);
                exit;
            }
        }
    }

    /**
     * [logout_url] shortcode — outputs the WordPress logout URL pointing to PMPro login page.
     *
     * @param array<string, string>|string $atts Shortcode attributes. Accepts 'redirect'.
     * @return string Escaped logout URL or empty string if not logged in.
     */
    public function custom_logout_url_shortcode(array|string $atts): string
    {
        if (!is_user_logged_in()) {
            return '';
        }
        $pmpro_login = function_exists('pmpro_url') ? pmpro_url('login') : home_url('/login/');
        $atts = shortcode_atts(['redirect' => $pmpro_login], $atts, 'logout_url');
        return esc_url(wp_logout_url($atts['redirect']));
    }

    /**
     * Force redirect to PMPro login page upon logout.
     *
     * @return void
     */
    public function custom_logout_redirect(): void
    {
        $pmpro_login = function_exists('pmpro_url') ? pmpro_url('login') : home_url('/login/');
        wp_safe_redirect($pmpro_login);
        exit;
    }

    /**
     * Redirect users to the appropriate destination after login.
     *
     * - Administrators / manage_options → /wp-admin/
     * - All other logged-in members → /dashboard/
     *
     * @param string             $redirect_to The originally requested redirect URL.
     * @param string             $request     The raw requested redirect URL.
     * @param \WP_User|\WP_Error $user        The authenticated user object.
     * @return string Redirect destination URL.
     */
    public function custom_role_based_login_redirect(string $redirect_to, string $request, \WP_User|\WP_Error $user): string
    {
        if ($user instanceof \WP_User) {
            if (in_array('administrator', (array) $user->roles, true) || user_can($user, 'manage_options')) {
                return admin_url();
            }
            return home_url('/dashboard/');
        }
        return $redirect_to;
    }

    /**
     * Redirect users to the matchmaking questionnaire after a PMPro checkout.
     *
     * @param string   $rurl        The default confirmation URL.
     * @param int      $user_id     The user who completed checkout.
     * @param object   $pmpro_level The PMPro level object.
     * @return string Redirect URL.
     */
    public function custom_pmpro_level_based_registration_redirect(string $rurl, int $user_id, object $pmpro_level): string
    {
        if (!empty($pmpro_level->id)) {
            return home_url('/personal-matchmaking-questionnaire/');
        }
        return $rurl;
    }

    /**
     * Hide the WordPress admin bar for subscribers.
     *
     * @param bool $show_admin_bar Whether to show the admin bar.
     * @return bool False for subscribers, original value otherwise.
     */
    public function custom_hide_admin_bar_for_subscribers(bool $show_admin_bar): bool
    {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('subscriber', (array) $user->roles, true)) {
                return false;
            }
        }
        return $show_admin_bar;
    }

    /**
     * Inject CSS and JavaScript to enhance the PMPro login page appearance.
     *
     * Adds a subtitle, customizes heading text, adds a sign-up CTA, and adjusts link labels.
     * Only runs on the PMPro login page.
     *
     * @return void
     */
    public function custom_pmpro_login_page_design(): void
    {
        if (function_exists('pmpro_is_login_page') && !pmpro_is_login_page()) {
            return;
        }

        $signup_url = (function_exists('pmpro_url') && !empty(pmpro_url('checkout')))
            ? esc_url(add_query_arg('pmpro_level', '3', pmpro_url('checkout')))
            : esc_url(home_url('/membership-checkout/?pmpro_level=3'));
        ?>
        <style>
            #pmpro_login .pmpro_card_title,
            #pmpro_login h2.pmpro_card_title {
                font-family: 'Marcellus SC', serif !important;
                font-weight: 400 !important;
                font-size: 27px !important;
                color: #000000 !important;
                margin: 0 0 8px !important;
                border: none !important;
                padding-left: 38px !important;
            }

            .pmpro-login-subtitle {
                font-family: 'Poppins', sans-serif !important;
                font-weight: 400 !important;
                font-size: 14px !important;
                color: #000000A8 !important;
                margin: 0 0 24px !important;
                line-height: 1.4 !important;
                padding-left: 38px !important;
            }

            .pmpro-bottom-signup {
                font-family: 'Poppins', sans-serif !important;
                font-weight: 400 !important;
                font-size: 14px !important;
                color: #000000A8 !important;
                text-align: center !important;
                margin: 24px 0 0 !important;
                padding: 0 !important;
            }
            .pmpro-bottom-signup a {
                color: #000000 !important;
                font-weight: 600 !important;
                text-decoration: none !important;
            }
            .pmpro-bottom-signup a:hover {
                color: #d6712c !important;
                text-decoration: underline !important;
            }

            #pmpro_login .pmpro_actions_nav a,
            #pmpro_login .pmpro_card_actions a {
                font-family: 'Inter', sans-serif !important;
                font-weight: 400 !important;
                font-size: 14px !important;
                color: #000000 !important;
                text-decoration: none !important;
            }
            #pmpro_login .pmpro_actions_nav a:hover,
            #pmpro_login .pmpro_card_actions a:hover {
                color: #d6712c !important;
                text-decoration: underline !important;
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var title = document.querySelector('#pmpro_login .pmpro_card_title, #pmpro_login h2.pmpro_card_title');
            if (title) {
                title.textContent = '<?php echo esc_js(__('Sign Into Your Account', 'matchmaker')); ?>';
            }

            if (title && !document.querySelector('.pmpro-login-subtitle')) {
                var subtitle = document.createElement('p');
                subtitle.className = 'pmpro-login-subtitle';
                subtitle.textContent = '<?php echo esc_js(__('Please enter your email and password below.', 'matchmaker')); ?>';
                title.parentNode.insertBefore(subtitle, title.nextSibling);
            }

            var form = document.querySelector('#loginform');
            if (form && !document.querySelector('.pmpro-bottom-signup')) {
                var signup = document.createElement('p');
                signup.className = 'pmpro-bottom-signup';
                signup.innerHTML = '<?php echo esc_js(__("Don't have an account?", 'matchmaker')); ?> <a href="<?php echo $signup_url; ?>"><?php echo esc_js(__('Sign Up', 'matchmaker')); ?></a>';
                form.parentNode.insertBefore(signup, form.nextSibling);
            }

            var forgotLink = document.querySelector('#pmpro_login .pmpro_actions_nav a, #pmpro_login .pmpro_card_actions a');
            if (forgotLink) {
                forgotLink.textContent = '<?php echo esc_js(__('Forget password', 'matchmaker')); ?>';
            }
        });
        </script>
        <?php
    }
}
