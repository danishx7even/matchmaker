<?php
declare(strict_types=1);
namespace Matchmaker\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FormController
 *
 * Registers [matchmaking_form] and [matchmaking_field] shortcodes.
 * Handles the wp_ajax_mmf_submit_form AJAX endpoint.
 * Conditionally enqueues assets only on pages that contain the shortcode.
 */
class FormController {

    private static ?self $instance = null;
    private FieldGenerator $fg;

    /**
     * Get singleton instance
     *
     * @return self
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
        $this->fg = FieldGenerator::instance();
        $this->boot();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function boot(): void
    {
        add_shortcode('matchmaking_form',  [$this, 'render_form']);
        add_shortcode('matchmaking_field', [$this, 'render_standalone_field']);

        // AJAX handlers
        add_action('wp_ajax_mmf_submit_form',            [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_mmf_submit_form',     [$this, 'handle_ajax']);
        add_action('wp_ajax_mm_get_location_cascade',    [$this, 'handle_location_cascade_ajax']);
        add_action('wp_ajax_nopriv_mm_get_location_cascade', [$this, 'handle_location_cascade_ajax']);

        // Conditional asset enqueue — fires after post data is available
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    /**
     * Asset Enqueue (only on pages that have the shortcode)
     *
     * @return void
     */
    public function maybe_enqueue_assets(): void
    {
        global $post;

        // Check both the full shortcode and the standalone field shortcode
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_form  = has_shortcode($post->post_content, 'matchmaking_form');
        $has_field = has_shortcode($post->post_content, 'matchmaking_field');

        if (!$has_form && !$has_field) {
            return;
        }

        $plugin_url = defined('MM_URL') ? MM_URL : plugin_dir_url(dirname(__FILE__, 3));
        $version    = defined('MM_VERSION') ? MM_VERSION : '2.0.0';

        wp_enqueue_style(
            'mm-form-styles',
            $plugin_url . 'assets/css/matchmaking-form.css',
            [],
            $version
        );

        wp_enqueue_script(
            'mm-form-script',
            $plugin_url . 'assets/js/matchmaking-form.js',
            [],
            $version,
            true // load in footer
        );

        // Pass AJAX URL and hierarchy URL to JS
        wp_localize_script('mm-form-script', 'mmfData', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('mmf_form_nonce'),
            'hierarchyUrl' => $plugin_url . 'assets/file/hierarchy_names.json',
        ]);
    }

    /**
     * Data Hydration — pull saved values for pre-filling form
     *
     * @param int $user_id
     * @return array
     */
    private function get_user_form_values(int $user_id): array
    {
        $values = [];
        if ($user_id <= 0) {
            return $values;
        }

        $current_user = get_userdata($user_id);
        if ($current_user) {
            $values['full_name'] = $current_user->display_name ?: $current_user->first_name;
            $values['email']     = $current_user->user_email;
        }

        $pool = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);

        if ($pool) {
            $values['user_gender']          = !empty($pool['gender'])      ? ucfirst($pool['gender'])      : '';
            $values['pref_gender']          = !empty($pool['pref_gender']) ? ucfirst($pool['pref_gender']) : '';
            $values['birth_date']           = $pool['birth_date']          ?? '';
            $values['preferred_age_min']    = $pool['preferred_age_min']   ?? '';
            $values['preferred_age_max']    = $pool['preferred_age_max']   ?? '';
            $values['user_location']        = $pool['location']            ?? '';
            $values['pref_location']        = !empty($pool['pref_location']) ? explode(',', $pool['pref_location']) : [];
            $values['user_religion']        = $pool['religion']            ?? '';
            $values['pref_religion']        = $pool['pref_religion']       ?? '';
            $values['user_modesty']         = $pool['modesty']             ?? '';
            $values['pref_modesty']         = $pool['pref_modesty']        ?? '';
            $values['user_origin']          = $pool['origin']              ?? '';
            $values['pref_origin']          = $pool['pref_origin']         ?? '';
            $values['user_languages']       = $pool['languages']           ?? '';
            $values['country']              = $pool['country']             ?? ($pool['location'] ?? '');
            $values['state']                = $pool['state']               ?? '';
            $values['city']                 = $pool['city']                ?? '';
            $values['user_country']         = $pool['country']             ?? ($pool['location'] ?? '');
            $values['user_state']           = $pool['state']               ?? '';
            $values['user_city']            = $pool['city']                ?? '';
            $values['pref_country']         = $pool['pref_country']        ?? ($pool['pref_location'] ?? '');
            $values['pref_state']           = $pool['pref_state']          ?? '';
            $values['pref_city']            = $pool['pref_city']           ?? '';
            $values['user_origin']          = $pool['origin']              ?? '';
            $values['pref_origin']          = $pool['pref_origin']         ?? '';
            $values['user_religion']        = $pool['religion']            ?? '';
            $values['pref_religion']        = $pool['pref_religion']       ?? '';
            $values['user_modesty']         = $pool['modesty']             ?? '';
            $values['pref_modesty']         = $pool['pref_modesty']        ?? '';
            $values['user_languages']       = $pool['languages']          ?? '';
            $values['pref_languages']       = $pool['pref_languages']      ?? '';
            $values['user_job']             = $pool['job']                 ?? '';
            $values['user_smoking']         = $pool['smoking']             ?? '';
            $values['pref_smoking']         = $pool['pref_smoking']        ?? '';
            $values['user_drinking']        = $pool['drinking']            ?? '';
            $values['pref_drinking']        = $pool['pref_drinking']       ?? '';

            // Resolve height label from stored cm value
            foreach ($this->fg->options_height() as $h_opt) {
                if (!empty($pool['height_cm']) && strpos($h_opt, (string) $pool['height_cm'] . ' cm') !== false) {
                    $values['user_height'] = $h_opt;
                }
                if (!empty($pool['preferred_height_min']) && strpos($h_opt, (string) $pool['preferred_height_min'] . ' cm') !== false) {
                    $values['preferred_height_min'] = $h_opt;
                }
                if (!empty($pool['preferred_height_max']) && strpos($h_opt, (string) $pool['preferred_height_max'] . ' cm') !== false) {
                    $values['preferred_height_max'] = $h_opt;
                }
            }
        }

        $meta_keys = [
            'phone_number', 'user_citizenship', 'user_social_links', 'user_marital_status',
            'user_children', 'user_prayer', 'user_education', 'user_income',
            'user_country', 'user_state', 'user_city',
            'user_photo1', 'user_photo2', 'user_photo3',
            'pref_country', 'pref_state', 'pref_city',
            'pref_citizenship', 'pref_marital_status',
            'pref_children', 'pref_prayer', 'pref_education', 'pref_income', 'pref_additional_info',
        ];

        foreach ($meta_keys as $k) {
            $meta_val = \Matchmaker\Repository\MatchRepository::instance()->get_meta($user_id, $k, true);
            if ($meta_val !== '') {
                $values[$k] = $meta_val;
            }
        }

        return $values;
    }

    /**
     * Shortcode: [matchmaking_form]
     *
     * @param array|string $atts Shortcode attributes
     * @return string
     */
    public function render_form(array|string $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to access this form.', 'matchmaker') . '</p>';
        }

        $user_id = get_current_user_id();

        // Email Verification Gating: Unverified members must enter 6-digit code
        if (class_exists('\Matchmaker\Service\EmailVerificationService')) {
            $verify_service = \Matchmaker\Service\EmailVerificationService::instance();
            if (!$verify_service->is_user_verified($user_id)) {
                return $verify_service->render_verification_screen($user_id, 'form');
            }
        }

        $atts = shortcode_atts(['redirect' => ''], $atts, 'matchmaking_form');
        $redirect_url = !empty($atts['redirect']) ? esc_url_raw((string) $atts['redirect']) : '';

        $v = $this->get_user_form_values($user_id);

        $has_existing_profile = !empty($v['user_gender']);
        $btn_label = $has_existing_profile ? __('Update Profile', 'matchmaker') : __('Submit Application', 'matchmaker');

        ob_start();
        ?>
        <form class="mmf-form" method="post" id="matchmaking_form" name="Matchmaking Form"
              aria-label="Matchmaking Form" enctype="multipart/form-data" novalidate
              data-redirect="<?php echo esc_attr($redirect_url); ?>">

            <input type="hidden" name="mmf_nonce" value="<?php echo esc_attr(wp_create_nonce('mmf_form_nonce')); ?>">

            <!-- Step Indicators -->
            <div class="e-form__indicators e-form__indicators--type-number">
                <div class="e-form__indicators__indicator e-form__indicators__indicator--state-active" data-step-indicator="1">
                    <div class="e-form__indicators__indicator__number e-form__indicators__indicator--shape-circle">1</div>
                </div>
                <div class="e-form__indicators__indicator__separator"></div>
                <div class="e-form__indicators__indicator e-form__indicators__indicator--state-inactive" data-step-indicator="2">
                    <div class="e-form__indicators__indicator__number e-form__indicators__indicator--shape-circle">2</div>
                </div>
            </div>

            <!-- Top Notification & Error Display Box -->
            <div class="mmf-form-message" role="alert" aria-live="assertive"></div>

            <div class="elementor-form-fields-wrapper elementor-labels-above">

                <!-- ===================== STEP 1: About You ===================== -->
                <div class="elementor-column elementor-col-100 e-form__step" data-step="1">

                    <?php echo $this->fg->section_open('user', 'Personal Information'); ?>
                        <?php echo $this->fg->render_single_field('full_name', $v); ?>
                        <?php echo $this->fg->render_single_field('email', $v); ?>
                        <?php echo $this->fg->render_single_field('phone_number', $v); ?>
                        <?php echo $this->fg->render_single_field('birth_date', $v); ?>
                        <?php echo $this->fg->render_single_field('user_gender', $v); ?>
                        <?php echo $this->fg->render_single_field('user_languages', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('pin', 'Location &amp; Background'); ?>
                        <?php echo $this->fg->render_single_field('user_country', $v); ?>
                        <?php echo $this->fg->render_single_field('user_state', $v); ?>
                        <?php echo $this->fg->render_single_field('user_city', $v); ?>
                        <?php echo $this->fg->render_single_field('user_origin', $v); ?>
                        <?php echo $this->fg->render_single_field('user_religion', $v); ?>
                        <?php echo $this->fg->render_single_field('user_citizenship', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('info', 'About You'); ?>
                        <?php echo $this->fg->render_single_field('user_height', $v); ?>
                        <?php echo $this->fg->render_single_field('user_job', $v); ?>
                        <?php echo $this->fg->render_single_field('user_social_links', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('heart', 'Marital Status &amp; Family'); ?>
                        <?php echo $this->fg->render_single_field('user_marital_status', $v); ?>
                        <?php echo $this->fg->render_single_field('user_children', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('moon', 'Lifestyle Habits'); ?>
                        <?php echo $this->fg->render_single_field('user_modesty', $v); ?>
                        <?php echo $this->fg->render_single_field('user_drinking', $v); ?>
                        <?php echo $this->fg->render_single_field('user_smoking', $v); ?>
                        <?php echo $this->fg->render_single_field('user_prayer', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('cap', 'Education &amp; Finance'); ?>
                        <?php echo $this->fg->render_single_field('user_education', $v); ?>
                        <?php echo $this->fg->render_single_field('user_income', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('camera', 'Profile Photos', 'Upload 3 clear, recent photos. All 3 photos are mandatory.', 'upload-section'); ?>
                        <?php echo $this->fg->render_single_field('user_photo1', $v); ?>
                        <?php echo $this->fg->render_single_field('user_photo2', $v); ?>
                        <?php echo $this->fg->render_single_field('user_photo3', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <div class="e-form__buttons elementor-column elementor-col-100">
                        <div class="elementor-field-group e-form__buttons__wrapper elementor-field-type-next">
                            <button type="button"
                                    class="elementor-button elementor-size-sm e-form__buttons__wrapper__button e-form__buttons__wrapper__button-next"
                                    data-direction="next">
                                <?php esc_html_e('Continue', 'matchmaker'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ===================== STEP 2: Partner Preferences ===================== -->
                <div class="elementor-column elementor-col-100 e-form__step elementor-hidden" data-step="2">

                    <?php echo $this->fg->section_open('', 'Match Information'); ?>
                        <?php echo $this->fg->render_single_field('preferred_age_range', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_languages', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_gender', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('', 'Preferred Location &amp; Background'); ?>
                        <?php echo $this->fg->render_single_field('pref_country', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_state', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_city', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_origin', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_religion', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_citizenship', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('', 'Preferred Partner'); ?>
                        <?php echo $this->fg->render_single_field('preferred_height_range', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('', 'Preferred Marital Status'); ?>
                        <?php echo $this->fg->render_single_field('pref_marital_status', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_children', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('', 'Lifestyle Preferences'); ?>
                        <?php echo $this->fg->render_single_field('pref_modesty', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_drinking', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_smoking', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_prayer', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open('', 'Education &amp; Finance Preferences'); ?>
                        <?php echo $this->fg->render_single_field('pref_education', $v); ?>
                        <?php echo $this->fg->render_single_field('pref_income', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <?php echo $this->fg->section_open(
                        '',
                        'Your Ideal Match',
                        'Use this space to describe the person you hope to meet. This helps our matchmakers understand your personal preferences beyond the checkboxes.',
                        'ideal-match-section'
                    ); ?>
                        <?php echo $this->fg->render_single_field('pref_additional_info', $v); ?>
                    <?php echo $this->fg->section_close(); ?>

                    <div class="e-form__buttons elementor-column elementor-col-100">
                        <div class="elementor-field-group e-form__buttons__wrapper elementor-field-type-previous">
                            <button type="button"
                                    class="elementor-button elementor-size-sm e-form__buttons__wrapper__button e-form__buttons__wrapper__button-previous"
                                    data-direction="previous">
                                <?php esc_html_e('Back', 'matchmaker'); ?>
                            </button>
                        </div>
                        <div class="elementor-field-group elementor-field-type-submit e-form__buttons__wrapper">
                            <button class="elementor-button elementor-size-sm e-form__buttons__wrapper__button" type="submit">
                                <span class="elementor-button-content-wrapper">
                                    <span class="mmf-btn-spinner" aria-hidden="true"></span>
                                    <span class="elementor-button-text mmf-btn-default-text"><?php echo esc_html($btn_label); ?></span>
                                    <span class="elementor-button-text mmf-btn-loading-text"><?php esc_html_e('Saving your profile…', 'matchmaker'); ?></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

            </div><!-- .elementor-form-fields-wrapper -->
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Shortcode: [matchmaking_field name="field_name"]
     *
     * @param array|string $atts Shortcode attributes
     * @return string
     */
    public function render_standalone_field(array|string $atts = []): string
    {
        $atts = shortcode_atts(['name' => ''], $atts, 'matchmaking_field');
        $name = sanitize_key((string) $atts['name']);

        if (empty($name)) {
            return '';
        }

        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        $values  = $this->get_user_form_values($user_id);

        return '<div id="matchmaking_form" class="mmf-standalone-field-wrapper">'
            . $this->fg->render_single_field($name, $values)
            . '</div>';
    }

    /**
     * AJAX: wp_ajax_mmf_submit_form
     *
     * @return void
     */
    public function handle_ajax(): void
    {
        // 1. Nonce verification
        $nonce = isset($_POST['mmf_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['mmf_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mmf_form_nonce')) {
            wp_send_json_error(['message' => __('Security token expired. Please refresh the page.', 'matchmaker')]);
        }

        // 2. Authentication check
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('You must be logged in to save your matchmaking profile.', 'matchmaker')]);
        }

        // Email Verification Check
        if (class_exists('\Matchmaker\Service\EmailVerificationService')) {
            if (!\Matchmaker\Service\EmailVerificationService::instance()->is_user_verified($user_id)) {
                wp_send_json_error(['message' => __('Please verify your email address before saving your profile.', 'matchmaker')]);
            }
        }

        // 3. Sanitize input
        $f = isset($_POST['form_fields']) && is_array($_POST['form_fields'])
            ? wp_unslash($_POST['form_fields'])
            : [];

        $full_name = sanitize_text_field((string) ($f['full_name'] ?? ''));
        $email     = sanitize_email((string) ($f['email'] ?? ''));

        if (empty($full_name) || empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please provide a valid full name and email address.', 'matchmaker')]);
        }

        // 4. Validate mandatory profile photos (All 3 photos required)
        for ($i = 1; $i <= 3; $i++) {
            $photo_key    = 'user_photo' . $i;
            $has_existing = !empty(get_user_meta($user_id, $photo_key, true));
            $has_uploaded = !empty($_FILES['form_fields']['name'][$photo_key])
                && (int) ($_FILES['form_fields']['error'][$photo_key] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

            if (!$has_existing && !$has_uploaded) {
                wp_send_json_error(['message' => sprintf(__('Photo %d is mandatory. Please provide all 3 profile photos.', 'matchmaker'), $i)]);
            }
        }

        // 5. Helpers
        $sanitize_select = static function (?string $raw): string {
            if (empty($raw)) {
                return '';
            }
            $clean = sanitize_text_field(trim($raw));
            if (preg_match('/^select\b/i', $clean) === 1) {
                return '';
            }
            return $clean;
        };

        $parse_height = static function (?string $raw): ?int {
            if (empty($raw)) {
                return null;
            }
            if (preg_match('/\((\d{3})\s*cm\)/', $raw, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/\d{3}/', $raw, $m)) {
                return (int) $m[0];
            }
            return null;
        };

        $normalize_list = static function ($raw) use ($sanitize_select): string {
            if (empty($raw)) {
                return '';
            }
            $items = is_array($raw) ? $raw : explode(',', (string) $raw);
            $clean = [];
            foreach ($items as $item) {
                $val = $sanitize_select((string) $item);
                if ($val !== '') {
                    $clean[] = $val;
                }
            }
            return implode(',', $clean);
        };

        // 6. Resolve user_type from PMPro or usermeta
        $user_type = class_exists('\Matchmaker\Core\PMProSync')
            ? \Matchmaker\Core\PMProSync::instance()->get_current_user_type($user_id)
            : (string) get_user_meta($user_id, 'user_type', true);

        if (!in_array($user_type, ['monthly', 'one_on_one', 'free', 'event'], true)) {
            $user_type = 'free';
        }

        $gender      = strtolower(trim((string) ($f['user_gender'] ?? 'male')));
        $pref_gender = strtolower(trim((string) ($f['pref_gender'] ?? 'female')));
        $birth_date  = !empty($f['birth_date'])
            ? gmdate('Y-m-d', (int) strtotime((string) $f['birth_date']))
            : '1995-01-01';

        $pref_age_min = !empty($f['preferred_age_min']) ? (int) $f['preferred_age_min'] : 18;
        $pref_age_max = !empty($f['preferred_age_max']) ? (int) $f['preferred_age_max'] : 80;
        if (!empty($f['preferred_age_min']) && !empty($f['preferred_age_max']) && $pref_age_min >= $pref_age_max) {
            wp_send_json_error(['message' => __('Preferred Maximum Age must be higher than Minimum Age.', 'matchmaker')]);
        }

        $pref_h_min = $parse_height((string) ($f['preferred_height_min'] ?? ''));
        $pref_h_max = $parse_height((string) ($f['preferred_height_max'] ?? ''));
        if ($pref_h_min !== null && $pref_h_max !== null && $pref_h_min >= $pref_h_max) {
            wp_send_json_error(['message' => __('Preferred Maximum Height must be higher than Minimum Height.', 'matchmaker')]);
        }

        // 7. Build pool payload (Country, State, City)
        $pool_payload = [
            'user_id'              => $user_id,
            'gender'               => in_array($gender, ['male', 'female'], true) ? $gender : 'male',
            'pref_gender'          => in_array($pref_gender, ['male', 'female'], true) ? $pref_gender : 'female',
            'birth_date'           => $birth_date,
            'preferred_age_min'    => $pref_age_min,
            'preferred_age_max'    => $pref_age_max,
            'country'              => $sanitize_select((string) ($f['user_country'] ?? $f['user_location'] ?? '')),
            'state'                => sanitize_text_field((string) ($f['user_state'] ?? '')),
            'city'                 => sanitize_text_field((string) ($f['user_city'] ?? '')),
            'pref_country'         => $normalize_list($f['pref_country'] ?? $f['pref_location'] ?? ''),
            'pref_state'           => sanitize_text_field((string) ($f['pref_state'] ?? '')),
            'pref_city'            => sanitize_text_field((string) ($f['pref_city'] ?? '')),
            'religion'             => $sanitize_select((string) ($f['user_religion'] ?? '')),
            'pref_religion'        => $normalize_list($f['pref_religion'] ?? ''),
            'modesty'              => $sanitize_select((string) ($f['user_modesty'] ?? '')),
            'pref_modesty'         => $normalize_list($f['pref_modesty'] ?? ''),
            'origin'               => $sanitize_select((string) ($f['user_origin'] ?? '')),
            'pref_origin'          => $normalize_list($f['pref_origin'] ?? ''),
            'languages'            => $normalize_list($f['user_languages'] ?? ''),
            'pref_languages'       => $normalize_list($f['pref_languages'] ?? ''),
            'height_cm'            => $parse_height((string) ($f['user_height'] ?? '')),
            'preferred_height_min' => $pref_h_min,
            'preferred_height_max' => $pref_h_max,
            'job'                  => sanitize_text_field((string) ($f['user_job'] ?? '')),
            'smoking'              => $sanitize_select((string) ($f['user_smoking'] ?? '')),
            'pref_smoking'         => $normalize_list($f['pref_smoking'] ?? ''),
            'drinking'             => $sanitize_select((string) ($f['user_drinking'] ?? '')),
            'pref_drinking'        => $normalize_list($f['pref_drinking'] ?? ''),
            'user_type'            => $user_type,
            'is_active'            => 1,
        ];

        // 8. Check if user is updating an existing profile (vs filling for the first time)
        $existing_pool      = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);
        $is_update_profile  = !empty($existing_pool) && !empty($existing_pool['gender']);

        $inserted = \Matchmaker\Repository\MatchRepository::instance()->upsert_pool($pool_payload);

        if ($inserted === false) {
            wp_send_json_error(['message' => __('Database error while updating match pool. Please try again.', 'matchmaker')]);
        }

        // 9. Update WP user display name
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $full_name,
            'first_name'   => $full_name,
        ]);

        // 10. Save usermeta fields (remove pref_social_links)
        $meta_map = [
            'phone_number'        => sanitize_text_field((string) ($f['phone_number'] ?? '')),
            'user_citizenship'    => sanitize_text_field((string) ($f['user_citizenship'] ?? '')),
            'user_social_links'   => sanitize_textarea_field((string) ($f['user_social_links'] ?? '')),
            'user_marital_status' => sanitize_text_field((string) ($f['user_marital_status'] ?? '')),
            'user_children'       => sanitize_text_field((string) ($f['user_children'] ?? '')),
            'user_prayer'         => sanitize_text_field((string) ($f['user_prayer'] ?? '')),
            'user_education'      => sanitize_text_field((string) ($f['user_education'] ?? '')),
            'user_income'         => sanitize_text_field((string) ($f['user_income'] ?? '')),
            'user_country'        => sanitize_text_field((string) ($f['user_country'] ?? '')),
            'user_state'          => sanitize_text_field((string) ($f['user_state'] ?? '')),
            'user_city'           => sanitize_text_field((string) ($f['user_city'] ?? '')),
            'pref_country'        => $normalize_list($f['pref_country'] ?? ''),
            'pref_state'          => sanitize_text_field((string) ($f['pref_state'] ?? '')),
            'pref_city'           => sanitize_text_field((string) ($f['pref_city'] ?? '')),
            'pref_citizenship'    => $normalize_list($f['pref_citizenship'] ?? ''),
            'pref_marital_status' => sanitize_text_field((string) ($f['pref_marital_status'] ?? '')),
            'pref_children'       => sanitize_text_field((string) ($f['pref_children'] ?? '')),
            'pref_prayer'         => sanitize_text_field((string) ($f['pref_prayer'] ?? '')),
            'pref_education'      => sanitize_text_field((string) ($f['pref_education'] ?? '')),
            'pref_income'         => sanitize_text_field((string) ($f['pref_income'] ?? '')),
            'pref_additional_info'=> sanitize_textarea_field((string) ($f['pref_additional_info'] ?? '')),
        ];

        \Matchmaker\Repository\MatchRepository::instance()->save_meta_block($user_id, $meta_map);

        // 10. Handle photo uploads via WP Media Library
        if (!empty($_FILES['form_fields']) && is_array($_FILES['form_fields']['name'] ?? null)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            foreach (['user_photo1', 'user_photo2', 'user_photo3'] as $photo_key) {
                $has_file = !empty($_FILES['form_fields']['name'][$photo_key])
                    && (int) ($_FILES['form_fields']['error'][$photo_key] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

                if (!$has_file) {
                    continue;
                }

                // Temporarily remap to a top-level $_FILES key for media_handle_upload()
                $_FILES['mmf_tmp_file'] = [
                    'name'     => $_FILES['form_fields']['name'][$photo_key],
                    'type'     => $_FILES['form_fields']['type'][$photo_key],
                    'tmp_name' => $_FILES['form_fields']['tmp_name'][$photo_key],
                    'error'    => $_FILES['form_fields']['error'][$photo_key],
                    'size'     => $_FILES['form_fields']['size'][$photo_key],
                ];

                $attachment_id = media_handle_upload('mmf_tmp_file', 0);
                if (!is_wp_error($attachment_id)) {
                    $url = wp_get_attachment_url($attachment_id);
                    if ($url) {
                        update_user_meta($user_id, $photo_key, esc_url_raw($url));
                    }
                } else {
                    error_log(
                        "Matchmaker: photo upload failed for {$photo_key}, user {$user_id} — "
                        . $attachment_id->get_error_message()
                    );
                }

                unset($_FILES['mmf_tmp_file']);
            }
        }

        // 11. Enqueue async matching job (for monthly/one_on_one users or admins upon profile form submit/update)
        $user_type_meta = (string) get_user_meta($user_id, 'user_type', true);
        $effective_type = !empty($user_type_meta) ? $user_type_meta : $user_type;

        if (in_array($effective_type, ['monthly', 'one_on_one'], true) || current_user_can('manage_options')) {
            if (function_exists('mm_enqueue_user_matching_job')) {
                $is_update = !empty(get_user_meta($user_id, 'mm_last_match_run', true));
                $trigger   = $is_update ? 'form_update' : 'form_submit';
                mm_enqueue_user_matching_job($user_id, $trigger);
            }
        }

        $response_data = [
            'message' => sprintf(
                /* translators: %s: user's full name */
                __('Thank you, %s! Your matchmaking profile has been saved successfully.', 'matchmaker'),
                esc_html($full_name)
            ),
        ];

        if ($is_update_profile) {
            $response_data['redirect_url'] = \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();
        }

        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler for dynamic cascading location queries.
     *
     * @return void
     */
    public function handle_location_cascade_ajax(): void
    {
        $country = isset($_GET['country']) ? sanitize_text_field(wp_unslash((string) $_GET['country'])) : '';
        $state   = isset($_GET['state'])   ? sanitize_text_field(wp_unslash((string) $_GET['state']))   : '';
        $mode    = isset($_GET['mode'])    ? sanitize_key((string) $_GET['mode']) : 'user'; // 'user' | 'pref'

        $states = ($mode === 'pref')
            ? $this->fg->options_pref_state($country)
            : $this->fg->options_user_state($country);

        $cities = ($mode === 'pref')
            ? $this->fg->options_pref_city($country, $state)
            : $this->fg->options_user_city($country, $state);

        wp_send_json_success([
            'states' => $states,
            'cities' => $cities,
        ]);
    }
}
