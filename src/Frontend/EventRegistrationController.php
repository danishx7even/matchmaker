<?php
declare(strict_types=1);

namespace Matchmaker\Frontend;

/**
 * Handles Event RSVP Button shortcode and Event Registration Form resolver.
 *
 * Provides:
 * 1. [event_rsvp_button]: Renders an RSVP button with class "rsvp-btn" linking to /event-reg/?e=<hash>.
 *    Reads ACF field 'forminator_form_shortcode' from current post in loops.
 * 2. [event_reg_form]: Shown on /event-reg/ page, verifies the ?e=<hash> parameter against
 *    known Forminator form IDs and dynamically executes [forminator_form id="<ID>"].
 *
 * NOTE: This button is completely independent from the portal "Join Event" click-tracking popup button.
 *
 * @package Matchmaker\Frontend
 */
final class EventRegistrationController
{
    private static ?self $instance = null;

    /**
     * Singleton instance accessor.
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

    /**
     * Constructor registers shortcodes and frontend styles.
     */
    private function __construct()
    {
        // 1. RSVP Button Shortcode & Aliases
        add_shortcode('event_rsvp_button',       [$this, 'render_rsvp_button']);
        add_shortcode('rsvp_button',              [$this, 'render_rsvp_button']);
        add_shortcode('az_event_rsvp_button',     [$this, 'render_rsvp_button']);

        // 2. Event Registration Page Form Shortcode & Aliases
        add_shortcode('event_reg_form',           [$this, 'render_reg_form']);
        add_shortcode('event_registration_form',  [$this, 'render_reg_form']);
        add_shortcode('az_event_reg_form',        [$this, 'render_reg_form']);

        // 3. Frontend styles for .rsvp-btn
        add_action('wp_head',                     [$this, 'print_inline_styles']);
    }

    /**
     * Extracts numeric Forminator form ID from raw ACF value or shortcode string.
     *
     * Supports formats:
     * - "[forminator_form id=\"1234\"]"
     * - "[forminator_form id='1234']"
     * - "[forminator_form id=1234]"
     * - "1234"
     *
     * @param string $raw
     * @return int
     */
    public static function extract_form_id(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        if (preg_match('/id=["\']?(\d+)["\']?/i', $raw, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b\d+\b/', $raw, $matches)) {
            return (int) $matches[0];
        }

        return 0;
    }

    /**
     * Generates a deterministic, secure 32-character hexadecimal hash for a Forminator form ID.
     *
     * @param int $form_id
     * @return string
     */
    public static function generate_hash(int $form_id): string
    {
        if ($form_id <= 0) {
            return '';
        }

        $salt = function_exists('wp_salt')
            ? (string) wp_salt('auth')
            : (defined('AUTH_KEY') ? (string) AUTH_KEY : 'matchmaker_salt');

        return substr(hash_hmac('sha256', 'forminator_event_' . $form_id, $salt), 0, 32);
    }

    /**
     * Matches the given hash against known Forminator form IDs in the database.
     *
     * Inspects:
     * 1. Posts table for post_type = 'forminator_forms'
     * 2. Postmeta table for meta_key = 'forminator_form_shortcode'
     *
     * @param string $hash
     * @return int Matching Form ID or 0 if not found.
     */
    public static function find_form_id_by_hash(string $hash): int
    {
        $hash = trim($hash);
        if ($hash === '') {
            return 0;
        }

        global $wpdb;
        $candidate_ids = [];

        // 1. Query all Forminator form post IDs if wpdb is available
        if (isset($wpdb) && !empty($wpdb->posts)) {
            $table = $wpdb->posts;
            $form_posts = $wpdb->get_col("SELECT ID FROM {$table} WHERE post_type = 'forminator_forms' AND post_status != 'trash'");
            if (is_array($form_posts)) {
                foreach ($form_posts as $pid) {
                    $candidate_ids[] = (int) $pid;
                }
            }
        }

        // 2. Query event posts that define 'forminator_form_shortcode' in postmeta
        if (isset($wpdb) && !empty($wpdb->postmeta)) {
            $meta_table = $wpdb->postmeta;
            $meta_values = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$meta_table} WHERE meta_key = 'forminator_form_shortcode'");
            if (is_array($meta_values)) {
                foreach ($meta_values as $raw_val) {
                    $fid = self::extract_form_id((string) $raw_val);
                    if ($fid > 0) {
                        $candidate_ids[] = $fid;
                    }
                }
            }
        }

        $candidate_ids = array_values(array_unique(array_filter($candidate_ids)));

        // 3. Compare hash using timing-safe comparison
        foreach ($candidate_ids as $fid) {
            $expected = self::generate_hash($fid);
            if (hash_equals($expected, $hash)) {
                return $fid;
            }
        }

        return 0;
    }

    /**
     * Renders the RSVP button shortcode: [event_rsvp_button].
     *
     * Shortcode attributes:
     * - id / post_id: Event post ID (defaults to get_the_ID() in loops)
     * - text: Button label (default: "RSVP")
     * - class: Additional CSS classes (default: "")
     * - target: Link target (default: "_self")
     * - url: Base registration URL (default: home_url('/event-reg/'))
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public function render_rsvp_button(mixed $atts = []): string
    {
        $atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'id'      => 0,
            'post_id' => 0,
            'text'    => 'RSVP',
            'class'   => '',
            'target'  => '_self',
            'url'     => '',
        ], $atts, 'event_rsvp_button');

        $post_id = !empty($atts['post_id'])
            ? (int) $atts['post_id']
            : (!empty($atts['id']) ? (int) $atts['id'] : (int) get_the_ID());

        if ($post_id <= 0) {
            return '';
        }

        // Retrieve ACF field 'forminator_form_shortcode'
        $raw_shortcode = '';
        if (function_exists('get_field')) {
            $raw_shortcode = (string) get_field('forminator_form_shortcode', $post_id);
        }
        if ($raw_shortcode === '' && function_exists('get_post_meta')) {
            $raw_shortcode = (string) get_post_meta($post_id, 'forminator_form_shortcode', true);
        }

        $raw_shortcode = trim($raw_shortcode);
        if ($raw_shortcode === '') {
            return '';
        }

        $form_id = self::extract_form_id($raw_shortcode);
        if ($form_id <= 0) {
            return '';
        }

        $hash = self::generate_hash($form_id);
        if ($hash === '') {
            return '';
        }

        $base_url = !empty($atts['url'])
            ? (string) $atts['url']
            : (function_exists('home_url') ? home_url('/event-reg/') : '/event-reg/');

        $reg_url = function_exists('add_query_arg')
            ? add_query_arg('e', $hash, $base_url)
            : $base_url . '?e=' . rawurlencode($hash);

        $button_text = !empty($atts['text']) ? esc_html((string) $atts['text']) : 'RSVP';
        $extra_class = !empty($atts['class']) ? ' ' . esc_attr(trim((string) $atts['class'])) : '';
        $target_attr = ($atts['target'] === '_blank') ? ' target="_blank" rel="noopener noreferrer"' : '';

        return sprintf(
            '<a href="%s" class="rsvp-btn%s"%s>%s</a>',
            esc_url($reg_url),
            $extra_class,
            $target_attr,
            $button_text
        );
    }

    /**
     * Renders the event registration form shortcode: [event_reg_form].
     *
     * Placed on /event-reg/ page. Reads ?e=<hash>, resolves to Forminator form ID,
     * and evaluates do_shortcode('[forminator_form id="..."]').
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public function render_reg_form(mixed $atts = []): string
    {
        $hash = isset($_GET['e']) ? sanitize_text_field((string) $_GET['e']) : '';

        if ($hash === '') {
            return '<div class="az-event-reg-notice az-event-reg-empty" style="padding: 16px 20px; background: #FFFDF9; border: 1px solid #F0E6D8; border-radius: 8px; color: #475569; font-size: 14px; margin: 20px 0;"><p style="margin: 0;">' . esc_html__('No event specified for registration. Please return to the events page.', 'matchmaker') . '</p></div>';
        }

        $form_id = self::find_form_id_by_hash($hash);
        if ($form_id <= 0) {
            return '<div class="az-event-reg-notice az-event-reg-error" style="padding: 16px 20px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; color: #991B1B; font-size: 14px; margin: 20px 0;"><p style="margin: 0;">' . esc_html__('Registration form not found or link has expired. Please return to the events page.', 'matchmaker') . '</p></div>';
        }

        if (function_exists('do_shortcode')) {
            return (string) do_shortcode('[forminator_form id="' . $form_id . '"]');
        }

        return '[forminator_form id="' . $form_id . '"]';
    }

    /**
     * Prints inline CSS styling for .rsvp-btn to guarantee exact visual match with design system.
     *
     * @return void
     */
    public function print_inline_styles(): void
    {
        ?>
        <style id="az-rsvp-btn-styles">
        .rsvp-btn {
            display: inline-block;
            background-color: #CC723F;
            color: #ffffff !important;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            text-decoration: none !important;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            box-sizing: border-box;
            transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
            vertical-align: middle;
        }
        .rsvp-btn:hover,
        .rsvp-btn:focus {
            background-color: #B65F2E;
            color: #ffffff !important;
            text-decoration: none !important;
            box-shadow: 0 4px 12px rgba(204, 114, 63, 0.25);
        }
        .rsvp-btn:active {
            transform: scale(0.98);
        }
        </style>
        <?php
    }
}
