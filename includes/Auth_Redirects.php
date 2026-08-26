<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

class Auth_Redirects {
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('login_url', [$this, 'custom_pmpro_login_url'], 10, 3);
        add_action('login_init', [$this, 'custom_pmpro_redirect_wp_login']);
        add_shortcode('logout_url', [$this, 'custom_logout_url_shortcode']);
        add_filter('login_redirect', [$this, 'custom_role_based_login_redirect'], 10, 3);
        add_filter('pmpro_confirmation_url', [$this, 'custom_pmpro_level_based_registration_redirect'], 10, 3);
        add_filter('show_admin_bar', [$this, 'custom_hide_admin_bar_for_subscribers']);
        add_action('wp_footer', [$this, 'custom_pmpro_login_page_design']);
    }

    public function custom_pmpro_login_url($login_url, $redirect, $force_reauth)
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

    public function custom_pmpro_redirect_wp_login(): void
    {
        global $pagenow;
        if ('wp-login.php' === $pagenow && 'GET' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
            $allowed_actions = ['logout', 'lostpassword', 'rp', 'resetpass', 'register', 'postpass'];
            if (in_array($action, $allowed_actions, true)) { return; }
            if (function_exists('pmpro_url')) {
                $pmpro_login = pmpro_url('login');
                if (!empty($pmpro_login)) {
                    $redirect_to = !empty($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
                    if (!empty($redirect_to)) { $pmpro_login = add_query_arg('redirect_to', $redirect_to, $pmpro_login); }
                    wp_safe_redirect($pmpro_login);
                    exit;
                }
            }
        }
    }

    public function custom_logout_url_shortcode($atts)
    {
        if (!is_user_logged_in()) { return ''; }
        $atts = shortcode_atts(['redirect' => home_url()], $atts, 'logout_url');
        return esc_url(wp_logout_url($atts['redirect']));
    }

    public function custom_role_based_login_redirect($redirect_to, $request, $user)
    {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('administrator', $user->roles, true)) { return admin_url(); }
            return home_url('/membership-account/');
        }
        return $redirect_to;
    }

    public function custom_pmpro_level_based_registration_redirect($rurl, $user_id, $pmpro_level)
    {
        if (!empty($pmpro_level->id)) { return home_url('/personal-matchmaking-questionnaire/'); }
        return $rurl;
    }

    public function custom_hide_admin_bar_for_subscribers($show_admin_bar)
    {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('subscriber', (array) $user->roles, true)) { return false; }
        }
        return $show_admin_bar;
    }

    public function custom_pmpro_login_page_design(): void
    {
        if (function_exists('pmpro_is_login_page') && !pmpro_is_login_page()) {
            return;
        }

        $signup_url = esc_url(home_url('/membership-checkout/?pmpro_level=3'));
        if (function_exists('pmpro_url')) {
            $checkout_url = pmpro_url('checkout');
            if (!empty($checkout_url)) {
                $signup_url = esc_url(add_query_arg('pmpro_level', '3', $checkout_url));
            }
        }
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
                signup.innerHTML = '<?php echo esc_js(__('Don\'t have an account?', 'matchmaker')); ?> <a href="<?php echo $signup_url; ?>"><?php echo esc_js(__('Sign Up', 'matchmaker')); ?></a>';
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
