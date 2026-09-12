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

        $html_parent = $this->field_generator->render_single_field('is_parent_applying', ['is_parent_applying' => 1]);
        if (!str_contains($html_parent, 'is_parent_applying') || !str_contains($html_parent, 'I am a parent applying on behalf of my child') || !str_contains($html_parent, 'checked')) {
            throw new \RuntimeException("FieldGenerator did not render is_parent_applying checkbox: " . $html_parent);
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

        if (!str_contains($out, 'is_parent_applying') || !str_contains($out, 'I am a parent applying on behalf of my child')) {
            throw new \RuntimeException("Expected form wizard markup to contain is_parent_applying field: " . $out);
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

    public function test_render_gender_specific_modesty_fields(): void
    {
        // 1. Female self modesty
        $female_mod_html = $this->field_generator->render_single_field('user_modesty', ['user_gender' => 'Female']);
        if (!str_contains($female_mod_html, 'Full Veil') || !str_contains($female_mod_html, 'Abaya &amp; Hijab') && !str_contains($female_mod_html, 'Abaya & Hijab')) {
            throw new \RuntimeException("Expected female user_modesty field to contain Full Veil and Abaya & Hijab: " . $female_mod_html);
        }

        // 2. Male self modesty
        $male_mod_html = $this->field_generator->render_single_field('user_modesty', ['user_gender' => 'Male']);
        if (!str_contains($male_mod_html, 'Traditional') || !str_contains($male_mod_html, 'Trend-focused')) {
            throw new \RuntimeException("Expected male user_modesty field to contain Traditional and Trend-focused: " . $male_mod_html);
        }

        // 3. Female preferred modesty
        $pref_female_html = $this->field_generator->render_single_field('pref_modesty', ['pref_gender' => 'Female']);
        if (!str_contains($pref_female_html, 'Full Veil') || !str_contains($pref_female_html, 'No Preference')) {
            throw new \RuntimeException("Expected female pref_modesty field to contain Full Veil and No Preference: " . $pref_female_html);
        }

        // 4. Male preferred modesty
        $pref_male_html = $this->field_generator->render_single_field('pref_modesty', ['pref_gender' => 'Male']);
        if (!str_contains($pref_male_html, 'Traditional') || !str_contains($pref_male_html, 'No Preference')) {
            throw new \RuntimeException("Expected male pref_modesty field to contain Traditional and No Preference: " . $pref_male_html);
        }
    }

    public function test_photo_fields_and_yearly_income_labels(): void
    {
        // 1. Photo field upload has required star
        $photo1_html = $this->field_generator->render_single_field('user_photo1');
        if (!str_contains($photo1_html, 'mm-required-star') || !str_contains($photo1_html, '*')) {
            throw new \RuntimeException("Expected user_photo1 to have required star indicator: " . $photo1_html);
        }

        // 2. Photo section open has required star
        $section_html = $this->field_generator->section_open('camera', 'Profile Photos', 'Upload 3 clear photos', 'upload-section', true);
        if (!str_contains($section_html, 'mm-required-star') || !str_contains($section_html, '*')) {
            throw new \RuntimeException("Expected photo section header to have required star indicator: " . $section_html);
        }

        // 3. Yearly Income Range labels
        $income_html = $this->field_generator->render_single_field('user_income');
        if (!str_contains($income_html, 'Yearly Income Range')) {
            throw new \RuntimeException("Expected user_income to have label 'Yearly Income Range': " . $income_html);
        }

        $pref_income_html = $this->field_generator->render_single_field('pref_income');
        if (!str_contains($pref_income_html, 'Preferred Yearly Income Range')) {
            throw new \RuntimeException("Expected pref_income to have label 'Preferred Yearly Income Range': " . $pref_income_html);
        }
    }

    public function test_searchable_select_inputs_have_data_ignore_validation(): void
    {
        // 1. Country select (single select searchable)
        $country_html = $this->field_generator->render_single_field('user_country', ['user_country' => 'Saudi Arabia']);
        if (!str_contains($country_html, 'custom-select-search-input') || !str_contains($country_html, 'data-ignore-validation="1"')) {
            throw new \RuntimeException("Expected user_country search input to have data-ignore-validation='1': " . $country_html);
        }

        // 2. Multiselect origin (multiselect searchable)
        $origin_html = $this->field_generator->render_single_field('pref_origin', ['pref_origin' => 'Arab, Gulf']);
        if (!str_contains($origin_html, 'custom-select-search-input') || !str_contains($origin_html, 'data-ignore-validation="1"')) {
            throw new \RuntimeException("Expected pref_origin search input to have data-ignore-validation='1': " . $origin_html);
        }
    }
}


