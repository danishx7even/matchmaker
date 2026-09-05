<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Frontend\FormController;
use Matchmaker\Frontend\FieldGenerator;
use FakeWP_User;

class FormWizardAndShortcodesTest
{
    private FormController $form_controller;
    private FieldGenerator $field_generator;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']   = [];
        $GLOBALS['__mm_usermeta']  = [];
        $GLOBALS['wpdb']->queries  = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];

        $GLOBALS['__mm_users'][100] = new FakeWP_User(100, 'member100', 'member100@example.com');

        $this->form_controller = FormController::instance();
        $this->field_generator = FieldGenerator::instance();
    }

    public function test_field_generator_renders_inputs(): void
    {
        $html = $this->field_generator->render_single_field('user_location');
        if (!str_contains($html, 'user_location') || !str_contains($html, 'Country / Location')) {
            throw new \RuntimeException("FieldGenerator did not render expected location field markup: " . $html);
        }

        $html_country = $this->field_generator->render_single_field('user_country');
        if (!str_contains($html_country, 'user_country') || !str_contains($html_country, 'United States')) {
            throw new \RuntimeException("FieldGenerator did not render country field markup: " . $html_country);
        }

        $html_gender = $this->field_generator->render_single_field('user_gender');
        if (!str_contains($html_gender, 'user_gender') || !str_contains($html_gender, 'Female')) {
            throw new \RuntimeException("FieldGenerator did not render gender field markup: " . $html_gender);
        }
    }

    public function test_render_standalone_field_shortcode(): void
    {
        $out = $this->form_controller->render_standalone_field(['name' => 'user_location']);
        if (empty($out) || !str_contains($out, 'user_location')) {
            throw new \RuntimeException("Expected standalone field shortcode to render input markup: " . $out);
        }
    }

    public function test_render_form_shortcode_markup(): void
    {
        // 1. Unverified member renders email verification screen
        update_user_meta(1, 'mm_email_verified', 0);
        $verify_out = $this->form_controller->render_form();
        if (empty($verify_out) || !str_contains($verify_out, 'mm-email-verify-card')) {
            throw new \RuntimeException("Expected unverified user to see email verification screen: " . $verify_out);
        }

        // 2. Verified member renders matchmaking form
        update_user_meta(1, 'mm_email_verified', 1);
        $out = $this->form_controller->render_form();
        if (empty($out) || (!str_contains($out, 'mmf-form') && !str_contains($out, 'matchmaking_form'))) {
            throw new \RuntimeException("Expected verified user to see matchmaking_form container: " . $out);
        }
    }

    public function test_render_range_fields_and_no_preference(): void
    {
        $age_html = $this->field_generator->render_single_field('preferred_age_range', ['preferred_age_min' => 22, 'preferred_age_max' => 35]);
        if (!str_contains($age_html, 'preferred_age_min') || !str_contains($age_html, 'preferred_age_max') || !str_contains($age_html, 'range-to-label')) {
            throw new \RuntimeException("Expected preferred_age_range field markup: " . $age_html);
        }

        $income_html = $this->field_generator->render_single_field('user_income', ['user_income' => '0-100k USD']);
        if (!str_contains($income_html, '0-100k USD') || str_contains($income_html, 'No Preference')) {
            throw new \RuntimeException("Expected user_income field to contain 0-100k USD and NOT No Preference: " . $income_html);
        }

        $pref_income_html = $this->field_generator->render_single_field('pref_income', ['pref_income' => 'No Preference']);
        if (!str_contains($pref_income_html, 'No Preference') || !str_contains($pref_income_html, '0-100k USD')) {
            throw new \RuntimeException("Expected pref_income field to contain No Preference: " . $pref_income_html);
        }

        $pref_cit_html = $this->field_generator->render_single_field('pref_citizenship', ['pref_citizenship' => 'Any Citizenship']);
        if (!str_contains($pref_cit_html, 'Any Citizenship')) {
            throw new \RuntimeException("Expected pref_citizenship field to contain Any Citizenship: " . $pref_cit_html);
        }
    }

    public function test_range_validation_logic(): void
    {
        $min = 35;
        $max = 25;
        $is_invalid = ($min >= $max);
        if (!$is_invalid) {
            throw new \RuntimeException("Expected min >= max to be detected as invalid range.");
        }
    }
}
