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
        add_action('template_redirect',                    [$this, 'custom_redirect_logged_in_user_from_login']);
        add_shortcode('logout_url',                        [$this, 'custom_logout_url_shortcode']);
        add_action('wp_logout',                            [$this, 'custom_logout_redirect']);
        add_filter('login_redirect',                       [$this, 'custom_role_based_login_redirect'], 99, 3);
        add_filter('pmpro_confirmation_url',               [$this, 'custom_pmpro_level_based_registration_redirect'], 10, 3);
        add_filter('show_admin_bar',                       [$this, 'custom_hide_admin_bar_for_subscribers']);
        add_action('wp_footer',                            [$this, 'custom_pmpro_login_page_design']);
        add_action('profile_update',                       [$this, 'redirect_after_pmpro_profile_update'], 99, 3);
        add_filter('pmpro_member_profile_edit_user_object_fields', [$this, 'add_username_to_pmpro_profile_fields'], 10, 1);
        add_action('pmpro_user_profile_update_errors',             [$this, 'validate_and_save_pmpro_username_update'], 10, 3);
        add_action('user_profile_update_errors',                   [$this, 'validate_and_save_wp_username_update'], 10, 3);
        add_action('pmpro_checkout_after_email',                   [$this, 'render_checkout_privacy_policy_checkbox']);
        add_action('pmpro_checkout_after_user_fields',              [$this, 'render_checkout_privacy_policy_checkbox']);
        add_filter('pmpro_registration_checks',                    [$this, 'check_privacy_policy_consent'], 20, 1);
        add_action('pmpro_after_checkout',                         [$this, 'save_privacy_policy_consent_on_checkout'], 10, 2);
        add_action('user_register',                                [$this, 'save_privacy_policy_consent_on_user_register'], 10, 1);
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
    /**
     * Redirect GET requests to wp-login.php to the PMPro login page.
     * Redirects already logged-in users visiting wp-login.php to the home page.
     *
     * Allows pass-through for logout, password reset, and registration actions.
     *
     * @return void
     */
    public function custom_pmpro_redirect_wp_login(): void
    {
        global $pagenow;
        if ('wp-login.php' !== $pagenow) {
            return;
        }

        $action = isset($_REQUEST['action'])
            ? sanitize_text_field(wp_unslash($_REQUEST['action']))
            : '';

        if ('logout' === $action) {
            return;
        }

        if (is_user_logged_in()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        if ('GET' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        $allowed_actions = ['lostpassword', 'rp', 'resetpass', 'register', 'postpass'];
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
     * Redirect already logged-in users visiting frontend login pages to the home page.
     *
     * @return void
     */
    public function custom_redirect_logged_in_user_from_login(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $is_login_page = false;
        if (function_exists('pmpro_is_login_page') && pmpro_is_login_page()) {
            $is_login_page = true;
        } elseif (is_page('login')) {
            $is_login_page = true;
        }

        if ($is_login_page) {
            wp_safe_redirect(home_url('/'));
            exit;
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
     * - Administrators / manage_options → /wp-admin/ (or requested wp-admin URL)
     * - All other logged-in members → /dashboard/
     *
     * @param string                  $redirect_to Default redirect destination URL.
     * @param string                  $request     Requested redirect destination URL passed via query string.
     * @param \WP_User|\WP_Error|null $user        WP_User object if login was successful.
     * @return string Redirect destination URL.
     */
    public function custom_role_based_login_redirect($redirect_to, $request = '', $user = null): string
    {
        if ($user instanceof \WP_User && $user->exists()) {
            if (in_array('administrator', (array) $user->roles, true) || user_can($user, 'manage_options')) {
                if (!empty($request) && strpos((string) $request, 'wp-admin') !== false) {
                    return (string) $request;
                }
                return admin_url();
            }

            if (in_array('matchmaker_admin', (array) $user->roles, true) || user_can($user, 'manage_matchmaker')) {
                if (!empty($request) && strpos((string) $request, 'wp-admin') !== false) {
                    return (string) $request;
                }
                return admin_url('admin.php?page=matchmaking-pool');
            }

            // Check if member has completed their profile in wp_matchmaking_pool
            $pool = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool((int) $user->ID);
            if (empty($pool) || empty($pool['gender'])) {
                return \Matchmaker\Service\ProfileService::instance()->get_form_url();
            }

            return \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();
        }
        return (string) $redirect_to;
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
            $lid = (int) $pmpro_level->id;
            if (\Matchmaker\Core\PMProSync::instance()->is_service_level($lid)) {
                return \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();
            }
            return \Matchmaker\Service\ProfileService::instance()->get_form_url();
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
            if (in_array('subscriber', (array) $user->roles, true) && !user_can($user, 'manage_matchmaker') && !user_can($user, 'manage_options')) {
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

            /* PMPro Profile Edit Username Field Enhancements */
            var usernameInput = document.querySelector('#member-profile-edit input[name="user_login"], #pmpro_member_profile_edit input[name="user_login"], input#user_login');
            if (usernameInput) {
                usernameInput.setAttribute('pattern', '^[a-zA-Z0-9_\\-\\.]+$');
                usernameInput.setAttribute('minlength', '3');
                usernameInput.setAttribute('maxlength', '60');
                usernameInput.setAttribute('placeholder', '<?php echo esc_js(__('Enter username', 'matchmaker')); ?>');
                
                var parentField = usernameInput.closest('.pmpro_form_field') || usernameInput.parentElement;
                if (parentField && !parentField.querySelector('.mm-username-hint')) {
                    var hint = document.createElement('p');
                    hint.className = 'pmpro_form_hint mm-username-hint';
                    hint.style.cssText = 'font-size: 12px; color: #6B7280; margin-top: 4px;';
                    hint.textContent = '<?php echo esc_js(__('No spaces allowed. Use letters, numbers, underscores, dashes, and periods.', 'matchmaker')); ?>';
                    parentField.appendChild(hint);
                }

                usernameInput.addEventListener('keydown', function (e) {
                    if (e.key === ' ' || e.keyCode === 32) {
                        e.preventDefault();
                    }
                });

                usernameInput.addEventListener('input', function () {
                    this.value = this.value.replace(/\s+/g, '');
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Add Username field to PMPro frontend member profile edit fields list.
     *
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    public function add_username_to_pmpro_profile_fields(array $fields): array
    {
        $ordered = [];
        foreach ($fields as $key => $label) {
            if ($key === 'display_name') {
                $ordered['user_login'] = __('Username', 'matchmaker');
            }
            $ordered[$key] = $label;
        }
        if (!isset($ordered['user_login'])) {
            $ordered['user_login'] = __('Username', 'matchmaker');
        }
        return $ordered;
    }

    /**
     * Validate and safely save username updates on PMPro member profile edit form.
     *
     * @param mixed     $errors Array or WP_Error object passed by reference.
     * @param bool      $update Whether this is an update.
     * @param \stdClass $user   User object passed by reference.
     * @return void
     */
    public function validate_and_save_pmpro_username_update(mixed &$errors, bool $update, \stdClass &$user): void
    {
        if (!$update || empty($user->ID)) {
            return;
        }

        $user_id = (int) $user->ID;
        $current_user_data = get_userdata($user_id);
        if (!$current_user_data) {
            return;
        }

        // Retrieve submitted username
        $submitted_username = '';
        if (isset($_POST['user_login'])) {
            $submitted_username = (string) wp_unslash($_POST['user_login']);
        } elseif (isset($_POST['username'])) {
            $submitted_username = (string) wp_unslash($_POST['username']);
        } elseif (isset($user->user_login)) {
            $submitted_username = (string) $user->user_login;
        }

        $submitted_username = trim($submitted_username);
        if ($submitted_username === '') {
            return;
        }

        $current_login = (string) $current_user_data->user_login;
        if (strcasecmp($submitted_username, $current_login) === 0 && $submitted_username === $current_login) {
            return; // No change
        }

        // 1. Check for spaces
        if (preg_match('/\s/', $submitted_username)) {
            $msg = __('Username cannot contain spaces.', 'matchmaker');
            $this->add_profile_error($errors, 'username_spaces', $msg);
            return;
        }

        // 2. Check length (min 3, max 60)
        if (mb_strlen($submitted_username) < 3 || mb_strlen($submitted_username) > 60) {
            $msg = __('Username must be between 3 and 60 characters long.', 'matchmaker');
            $this->add_profile_error($errors, 'username_length', $msg);
            return;
        }

        // 3. Check format validity
        if (!validate_username($submitted_username)) {
            $msg = __('Username contains invalid characters.', 'matchmaker');
            $this->add_profile_error($errors, 'username_invalid', $msg);
            return;
        }

        // 4. Check database for existing username
        $existing_user_id = username_exists($submitted_username);
        if ($existing_user_id && (int) $existing_user_id !== $user_id) {
            $msg = __('This username is already taken. Please choose another.', 'matchmaker');
            $this->add_profile_error($errors, 'username_exists', $msg);
            return;
        }

        // 5. If there are other errors already accumulated, don't execute update yet
        $has_errors = is_array($errors) ? !empty($errors) : (is_object($errors) && method_exists($errors, 'has_errors') && $errors->has_errors());
        if ($has_errors) {
            return;
        }

        // 6. Safe database update of user_login and user_nicename on wp_users
        global $wpdb;
        $sanitized_login    = sanitize_user($submitted_username, true);
        $sanitized_nicename = sanitize_title($sanitized_login);

        if (empty($sanitized_login)) {
            $msg = __('Username contains invalid characters.', 'matchmaker');
            $this->add_profile_error($errors, 'username_invalid', $msg);
            return;
        }

        $updated = $wpdb->update(
            $wpdb->users,
            [
                'user_login'    => $sanitized_login,
                'user_nicename' => $sanitized_nicename,
            ],
            ['ID' => $user_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            $msg = __('Could not update username. Please try again.', 'matchmaker');
            $this->add_profile_error($errors, 'username_db_error', $msg);
            return;
        }

        // Clear user cache in WordPress core
        clean_user_cache($user_id);

        // Update $user object reference
        $user->user_login = $sanitized_login;

        // If updating the currently logged-in user, refresh auth cookie and session
        if ($user_id === get_current_user_id()) {
            if (function_exists('wp_set_auth_cookie')) {
                wp_set_auth_cookie($user_id, true);
            }
            if (function_exists('wp_set_current_user')) {
                wp_set_current_user($user_id);
            }
        }

        // Log the change
        \Matchmaker\Repository\MatchRepository::instance()->log_event(
            'profile',
            'username_updated',
            sprintf(__('Username updated to %s', 'matchmaker'), $sanitized_login),
            sprintf(__('User #%d changed username from "%s" to "%s".', 'matchmaker'), $user_id, $current_login, $sanitized_login),
            [
                'user_id'      => $user_id,
                'old_username' => $current_login,
                'new_username' => $sanitized_login,
            ],
            null,
            $user_id,
            $current_user_data->user_email,
            'info'
        );
    }

    /**
     * Intercept standard WordPress profile update errors.
     *
     * @param mixed $errors
     * @param bool  $update
     * @param mixed $user
     * @return void
     */
    public function validate_and_save_wp_username_update(mixed &$errors, bool $update, mixed &$user): void
    {
        if (is_object($user) && isset($user->ID)) {
            $std_user = new \stdClass();
            $std_user->ID = (int) $user->ID;
            $std_user->user_login = isset($user->user_login) ? (string) $user->user_login : '';
            $this->validate_and_save_pmpro_username_update($errors, $update, $std_user);
            if (isset($std_user->user_login)) {
                $user->user_login = $std_user->user_login;
            }
        }
    }

    /**
     * Helper to append error messages to array or WP_Error.
     *
     * @param mixed  $errors
     * @param string $code
     * @param string $message
     * @return void
     */
    private function add_profile_error(mixed &$errors, string $code, string $message): void
    {
        if (is_array($errors)) {
            $errors[] = $message;
        } elseif (is_object($errors) && method_exists($errors, 'add')) {
            $errors->add($code, $message);
        }
    }

    /**
     * Redirect member to the PMPro membership account page after submitting the frontend profile edit form.
     *
     * @param int   $user_id
     * @param mixed $old_user_data
     * @param mixed $userdata
     * @return void
     */
    public function redirect_after_pmpro_profile_update(int $user_id, mixed $old_user_data = null, mixed $userdata = null): void
    {
        if (function_exists('is_admin') && is_admin()) {
            return;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'update-profile') {
            $account_url = \Matchmaker\Service\ProfileService::instance()->get_membership_account_url();
            if (!empty($account_url)) {
                wp_safe_redirect($account_url);
                if (defined('MM_UNIT_TESTS')) {
                    return;
                }
                if (!headers_sent()) {
                    exit;
                } else {
                    echo '<script>window.location.href = ' . json_encode($account_url) . ';</script>';
                    exit;
                }
            }
        }
    }

    /**
     * Render the Privacy Policy consent checkbox on the PMPro checkout page.
     *
     * @return void
     */
    public function render_checkout_privacy_policy_checkbox(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $checked = (!empty($_REQUEST['privacy_policy_consent']) || !empty($_POST['privacy_policy_consent'])) ? ' checked="checked"' : '';
        $privacy_url = 'https://arabzawaj.org/privacy-policy/';

        ?>
        <div id="pmpro_privacy_policy_wrapper" class="pmpro_checkout-field pmpro_checkout-field-checkbox pmpro_checkout-field-privacy-policy" style="margin: 16px 0 20px 0; padding: 14px 16px; background: #FFFDF9; border: 1px solid #F0E6D8; border-radius: 8px; box-sizing: border-box;">
            <div class="pmpro_checkout-field-inner" style="display: flex; align-items: flex-start; gap: 10px;">
                <input type="checkbox" id="privacy_policy_consent" name="privacy_policy_consent" value="1" <?php echo $checked; ?> required="required" style="margin-top: 3px; accent-color: #CC723F; width: 18px; height: 18px; cursor: pointer;" />
                <label for="privacy_policy_consent" class="pmpro_label" style="font-size: 14px; line-height: 1.5; color: #334155; margin: 0; cursor: pointer; display: inline;">
                    <?php
                    printf(
                        /* translators: %s: Privacy Policy link */
                        __('I have read and agree to the %s <span class="pmpro_asterisk" style="color: #e11d48; font-weight: bold;">*</span>', 'matchmaker'),
                        '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener noreferrer" style="color: #CC723F; font-weight: 600; text-decoration: underline;">' . esc_html__('Privacy Policy', 'matchmaker') . '</a>'
                    );
                    ?>
                </label>
            </div>
        </div>
        <script>
        (function() {
            function placePrivacyCheckbox() {
                var wrapper = document.getElementById('pmpro_privacy_policy_wrapper');
                if (!wrapper) return;
                
                // Target confirm email row or field
                var confirmEmail = document.getElementById('bconfirmemail') 
                    || document.querySelector('input[name="bconfirmemail"]')
                    || document.getElementById('bemail')
                    || document.querySelector('input[name="bemail"]');
                    
                if (confirmEmail) {
                    var container = confirmEmail.closest('.pmpro_checkout-field') 
                        || confirmEmail.closest('tr') 
                        || confirmEmail.closest('div.pmpro_form_field')
                        || confirmEmail.closest('.form-group')
                        || confirmEmail.parentElement;
                    
                    if (container && container.parentNode && container.nextSibling !== wrapper) {
                        container.parentNode.insertBefore(wrapper, container.nextSibling);
                    }
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', placePrivacyCheckbox);
            } else {
                placePrivacyCheckbox();
            }
            setTimeout(placePrivacyCheckbox, 300);
        })();
        </script>
        <?php
    }

    /**
     * Validates Privacy Policy consent during PMPro checkout registration.
     *
     * @param bool $okay
     * @return bool
     */
    public function check_privacy_policy_consent(bool $okay): bool
    {
        if (!$okay) {
            return false;
        }

        $is_checkout = !empty($_REQUEST['submit-checkout']) 
            || isset($_POST['submit-checkout']) 
            || isset($_POST['bemail']) 
            || isset($_POST['pmpro_level'])
            || (function_exists('pmpro_is_checkout') && pmpro_is_checkout());

        if (!$is_checkout) {
            return $okay;
        }

        $consent = !empty($_REQUEST['privacy_policy_consent']) 
            || !empty($_POST['privacy_policy_consent']) 
            || !empty($_REQUEST['mm_privacy_policy_consent']) 
            || !empty($_POST['mm_privacy_policy_consent']);

        if (!$consent) {
            $user_id = get_current_user_id();
            if ($user_id > 0 && get_user_meta($user_id, 'mm_privacy_policy_consent', true)) {
                return true;
            }

            global $pmpro_msg, $pmpro_msgt;
            $pmpro_msg  = __('You must agree to the Privacy Policy to complete your registration.', 'matchmaker');
            $pmpro_msgt = 'pmpro_error';
            return false;
        }

        return true;
    }

    /**
     * Save Privacy Policy consent timestamp on successful checkout.
     *
     * @param int   $user_id
     * @param mixed $morder
     * @return void
     */
    public function save_privacy_policy_consent_on_checkout(int $user_id, mixed $morder = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        $consent = !empty($_REQUEST['privacy_policy_consent']) 
            || !empty($_POST['privacy_policy_consent']) 
            || !empty($_REQUEST['mm_privacy_policy_consent']) 
            || !empty($_POST['mm_privacy_policy_consent']);

        if ($consent) {
            update_user_meta($user_id, 'mm_privacy_policy_consent', current_time('mysql'));
        }
    }

    /**
     * Save Privacy Policy consent timestamp on standard user registration.
     *
     * @param int $user_id
     * @return void
     */
    public function save_privacy_policy_consent_on_user_register(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        $consent = !empty($_REQUEST['privacy_policy_consent']) 
            || !empty($_POST['privacy_policy_consent']) 
            || !empty($_REQUEST['mm_privacy_policy_consent']) 
            || !empty($_POST['mm_privacy_policy_consent']);

        if ($consent) {
            update_user_meta($user_id, 'mm_privacy_policy_consent', current_time('mysql'));
        }
    }
}

