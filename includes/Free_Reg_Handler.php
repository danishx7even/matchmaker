<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

class Free_Reg_Handler {
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
        add_action('wp_footer', [$this, 'print_phone_mask_script']);
        add_action('elementor_pro/forms/validation', [$this, 'validate_free_user_registration'], 10, 2);
        add_action('elementor_pro/forms/new_record', [$this, 'handle_free_user_registration'], 10, 2);
    }

    public function print_phone_mask_script(): void
    {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('free_user_registration_form');
            if (!form) return;
            const phoneInput = form.querySelector('input[name="form_fields[phone_number]"]');
            if (!phoneInput) return;
            phoneInput.addEventListener('input', function(e) {
                let val = e.target.value;
                let hasPlus = val.startsWith('+');
                let cleaned = val.replace(/\D/g, '');
                if (hasPlus) { e.target.value = '+' + cleaned.substring(0, 15); }
                else if (cleaned.length > 10) { e.target.value = cleaned.substring(0, 15); }
                else if (cleaned.length <= 3) { e.target.value = cleaned; }
                else if (cleaned.length <= 6) { e.target.value = '(' + cleaned.substring(0, 3) + ') ' + cleaned.substring(3); }
                else { e.target.value = '(' + cleaned.substring(0, 3) + ') ' + cleaned.substring(3, 6) + '-' + cleaned.substring(6); }
            });
        });
        </script>
        <?php
    }

    public function validate_free_user_registration($record, $ajax_handler): void
    {
        $form_name = $record->get_form_settings('form_name');
        $form_id   = (string) $record->get_form_settings('id');
        if ($form_name !== 'Free User Registration Form' && $form_id !== '2784843') {
            return;
        }
        $raw_fields = $record->get('fields');
        $data = [];
        foreach ($raw_fields as $key => $field) { $data[$key] = $field['value'] ?? null; }
        $email = sanitize_email($data['email'] ?? '');
        $phone_number = sanitize_text_field($data['phone_number'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $confirm_password = (string) ($data['password2'] ?? '');
        if (empty($email) || !is_email($email)) { $ajax_handler->add_error('email', __('Please provide a valid email address.', 'matchmaking')); }
        elseif (email_exists($email) || username_exists($email)) { $ajax_handler->add_error('email', __('An account with this email address already exists. Please log in.', 'matchmaking')); }
        if (!empty($phone_number)) { $digits = preg_replace('/\D/', '', $phone_number); if (strlen($digits) < 7 || strlen($digits) > 15) { $ajax_handler->add_error('phone_number', __('Please enter a valid phone number between 7 and 15 digits.', 'matchmaking')); } }
        if (empty($password) || strlen($password) < 8) { $ajax_handler->add_error('password', __('Password must be at least 8 characters long.', 'matchmaking')); }
        if ($password !== $confirm_password) { $ajax_handler->add_error('password2', __('Passwords do not match. Please verify and try again.', 'matchmaking')); }
    }

    public function handle_free_user_registration($record, $handler): void
    {
        $form_name = $record->get_form_settings('form_name');
        $form_id   = (string) $record->get_form_settings('id');
        if ($form_name !== 'Free User Registration Form' && $form_id !== '2784843') {
            return;
        }
        $raw_fields = $record->get('fields');
        $data = [];
        foreach ($raw_fields as $key => $field) { $data[$key] = $field['value'] ?? null; }
        $full_name    = sanitize_text_field($data['full_name'] ?? '');
        $email        = sanitize_email($data['email'] ?? '');
        $phone_number = sanitize_text_field($data['phone_number'] ?? '');
        $password     = (string) ($data['password'] ?? '');

        try {
            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) { $handler->add_error('email', $user_id->get_error_message()); $handler->set_status('error'); return; }
            wp_update_user(['ID' => $user_id, 'display_name' => !empty($full_name) ? $full_name : $email, 'first_name' => $full_name, 'role' => 'subscriber']);
            update_user_meta($user_id, 'user_type', 'free_user');
            if (!empty($phone_number)) { update_user_meta($user_id, 'phone_number', $phone_number); }
            if (function_exists('pmpro_changeMembershipLevel')) {
                add_filter('pmpro_send_checkout_emails', '__return_false', 999);
                try { pmpro_changeMembershipLevel(2, $user_id); } finally { remove_filter('pmpro_send_checkout_emails', '__return_false', 999); }
            }
            $credentials = ['user_login' => $email, 'user_password' => $password, 'remember' => true];
            wp_signon($credentials, is_ssl());
        } catch (\Throwable $e) {
            error_log('Free User Registration Fatal: ' . $e->getMessage());
            $handler->add_error_message(__('An unexpected error occurred during account creation. Please try again.', 'matchmaking'));
            $handler->set_status('error');
        }
    }
}
