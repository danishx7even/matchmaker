<?php
declare(strict_types=1);

namespace Matchmaker\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FreeRegHandler
 *
 * Handles free user registration via Elementor Pro Forms.
 */
class FreeRegHandler {
    private static ?self $instance = null;

    /**
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
        add_action('elementor_pro/forms/validation', [$this, 'validate_free_user_registration'], 10, 2);
        add_action('elementor_pro/forms/new_record', [$this, 'handle_free_user_registration'], 10, 2);
    }

    /**
     * Get configured Elementor Free Registration Form ID(s).
     *
     * @return string
     */
    public function get_configured_form_id(): string
    {
        return (string) get_option('mm_free_reg_form_id', '2784843');
    }

    /**
     * Check if a given form ID matches the configured free registration form(s).
     *
     * @param string $form_id
     * @return bool
     */
    public function matches_form_id(string $form_id): bool
    {
        $configured = trim($this->get_configured_form_id());
        if ($configured === '') {
            return false;
        }
        $ids = array_map('trim', explode(',', $configured));
        return in_array($form_id, $ids, true);
    }

    /**
     * Validates free user registration form fields.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     */
    public function validate_free_user_registration($record, $ajax_handler): void
    {
        $form_id = (string) $record->get_form_settings('id');
        if (!$this->matches_form_id($form_id)) {
            return;
        }

        $raw_fields = $record->get('fields');
        $data = [];
        foreach ($raw_fields as $key => $field) { 
            $data[$key] = $field['value'] ?? null; 
        }

        $email = sanitize_email($data['email'] ?? '');
        $phone_number = sanitize_text_field($data['phone_number'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $confirm_password = (string) ($data['password2'] ?? '');

        if (empty($email) || !is_email($email)) { 
            $ajax_handler->add_error('email', __('Please provide a valid email address.', 'matchmaking')); 
        } elseif (email_exists($email) || username_exists($email)) { 
            $ajax_handler->add_error('email', __('An account with this email address already exists. Please log in.', 'matchmaking')); 
        }

        if (!empty($phone_number)) { 
            $digits = preg_replace('/\D/', '', $phone_number); 
            if (strlen($digits) < 7 || strlen($digits) > 15) { 
                $ajax_handler->add_error('phone_number', __('Please enter a valid phone number between 7 and 15 digits.', 'matchmaking')); 
            } 
        }

        if (empty($password) || strlen($password) < 8) { 
            $ajax_handler->add_error('password', __('Password must be at least 8 characters long.', 'matchmaking')); 
        }

        if ($password !== $confirm_password) { 
            $ajax_handler->add_error('password2', __('Passwords do not match. Please verify and try again.', 'matchmaking')); 
        }
    }

    /**
     * Creates new user and assigns PMPro level for valid form submission.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler
     */
    public function handle_free_user_registration($record, $handler): void
    {
        $form_id = (string) $record->get_form_settings('id');
        if (!$this->matches_form_id($form_id)) {
            return;
        }

        $raw_fields = $record->get('fields');
        $data = [];
        foreach ($raw_fields as $key => $field) { 
            $data[$key] = $field['value'] ?? null; 
        }

        $full_name    = sanitize_text_field($data['full_name'] ?? '');
        $email        = sanitize_email($data['email'] ?? '');
        $phone_number = sanitize_text_field($data['phone_number'] ?? '');
        $password     = (string) ($data['password'] ?? '');

        try {
            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) { 
                $handler->add_error('email', $user_id->get_error_message()); 
                $handler->set_status('error'); 
                return; 
            }
            wp_update_user([
                'ID' => $user_id, 
                'display_name' => !empty($full_name) ? $full_name : $email, 
                'first_name' => $full_name, 
                'role' => 'subscriber'
            ]);
            update_user_meta($user_id, 'user_type', 'free');
            if (!empty($phone_number)) { 
                update_user_meta($user_id, 'phone_number', $phone_number); 
            }

            update_user_meta($user_id, 'mm_email_verified', 0);

            if (class_exists('\Matchmaker\Service\EmailVerificationService')) {
                \Matchmaker\Service\EmailVerificationService::instance()->generate_and_send_code($user_id, true);
            }

            if (function_exists('pmpro_changeMembershipLevel')) {
                $free_level = PMProSync::instance()->get_primary_level_for_tier('free', 2);
                add_filter('pmpro_send_checkout_emails', '__return_false', 999);
                try { 
                    pmpro_changeMembershipLevel($free_level, $user_id); 
                } finally { 
                    remove_filter('pmpro_send_checkout_emails', '__return_false', 999); 
                }
            }

            $credentials = [
                'user_login' => $email, 
                'user_password' => $password, 
                'remember' => true
            ];
            wp_signon($credentials, is_ssl());
        } catch (\Throwable $e) {
            error_log('Free User Registration Fatal: ' . $e->getMessage());
            $handler->add_error_message(__('An unexpected error occurred during account creation. Please try again.', 'matchmaking'));
            $handler->set_status('error');
        }
    }
}
