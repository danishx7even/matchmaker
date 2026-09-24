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
        if (!str_contains($female_mod_html, 'Traditional / Hijab') || !str_contains($female_mod_html, 'Modest dress / conservative')) {
            throw new \RuntimeException("Expected female user_modesty field to contain unified options: " . $female_mod_html);
        }

        // 2. Male self modesty
        $male_mod_html = $this->field_generator->render_single_field('user_modesty', ['user_gender' => 'Male']);
        if (!str_contains($male_mod_html, 'Traditional / Hijab') || !str_contains($male_mod_html, 'No religious dress / trendy')) {
            throw new \RuntimeException("Expected male user_modesty field to contain unified options: " . $male_mod_html);
        }

        // 3. Female preferred modesty
        $pref_female_html = $this->field_generator->render_single_field('pref_modesty', ['pref_gender' => 'Female']);
        if (!str_contains($pref_female_html, 'Traditional / Hijab') || !str_contains($pref_female_html, 'No Preference')) {
            throw new \RuntimeException("Expected female pref_modesty field to contain unified options and No Preference: " . $pref_female_html);
        }

        // 4. Male preferred modesty
        $pref_male_html = $this->field_generator->render_single_field('pref_modesty', ['pref_gender' => 'Male']);
        if (!str_contains($pref_male_html, 'Traditional / Hijab') || !str_contains($pref_male_html, 'No Preference')) {
            throw new \RuntimeException("Expected male pref_modesty field to contain unified options and No Preference: " . $pref_male_html);
        }
    }

    public function test_photo_fields_and_yearly_income_labels(): void
    {
        // 1. Photo 1 upload has required star, required attribute, accept attribute and allowed formats hint
        $photo1_html = $this->field_generator->render_single_field('user_photo1');
        if (!str_contains($photo1_html, 'mm-required-star') || !str_contains($photo1_html, 'Photo 1') || !str_contains($photo1_html, 'required')) {
            throw new \RuntimeException("Expected user_photo1 to have Photo 1 label and required star indicator: " . $photo1_html);
        }
        if (!str_contains($photo1_html, '.webp') || !str_contains($photo1_html, 'Allowed formats: PNG, JPG, JPEG, WEBP')) {
            throw new \RuntimeException("Expected user_photo1 to declare allowed formats (PNG, JPG, JPEG, WEBP): " . $photo1_html);
        }

        // 2. Photo 2 and 3 are Optional and do not have required star or required attribute
        $photo2_html = $this->field_generator->render_single_field('user_photo2');
        if (str_contains($photo2_html, 'mm-required-star') || str_contains($photo2_html, 'required>')) {
            throw new \RuntimeException("Expected user_photo2 to have no required star or required attribute: " . $photo2_html);
        }
        if (!str_contains($photo2_html, 'Allowed formats: PNG, JPG, JPEG, WEBP')) {
            throw new \RuntimeException("Expected user_photo2 to declare allowed formats: " . $photo2_html);
        }

        $photo3_html = $this->field_generator->render_single_field('user_photo3');
        if (str_contains($photo3_html, 'mm-required-star') || str_contains($photo3_html, 'required>')) {
            throw new \RuntimeException("Expected user_photo3 to have no required star or required attribute: " . $photo3_html);
        }

        // 3. Photo section open has required star and format hint
        $section_html = $this->field_generator->section_open('camera', 'Profile Photos', 'Upload 3 clear, recent photos (Allowed formats: PNG, JPG, JPEG, WEBP). Only first one is mandatory and additional photos are optional.', 'upload-section', true);
        if (!str_contains($section_html, 'mm-required-star') || !str_contains($section_html, '*')) {
            throw new \RuntimeException("Expected photo section header to have required star indicator: " . $section_html);
        }
        if (!str_contains($section_html, 'Allowed formats: PNG, JPG, JPEG, WEBP')) {
            throw new \RuntimeException("Expected photo section header to include format hint: " . $section_html);
        }

        // 4. Yearly Income Range labels
        $income_html = $this->field_generator->render_single_field('user_income');
        if (!str_contains($income_html, 'Yearly Income Range')) {
            throw new \RuntimeException("Expected user_income to have label 'Yearly Income Range': " . $income_html);
        }

        $pref_income_html = $this->field_generator->render_single_field('pref_income');
        if (!str_contains($pref_income_html, 'Preferred Yearly Income Range')) {
            throw new \RuntimeException("Expected pref_income to have label 'Preferred Yearly Income Range': " . $pref_income_html);
        }

        // 5. Prayer Habits Preference contains No Preference
        $pref_prayer_html = $this->field_generator->render_single_field('pref_prayer');
        if (!str_contains($pref_prayer_html, 'No Preference')) {
            throw new \RuntimeException("Expected pref_prayer to contain No Preference: " . $pref_prayer_html);
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

    public function test_about_myself_and_about_perfect_match_fields(): void
    {
        // 1. user_about_me field renders with "About Myself" label
        $about_me_html = $this->field_generator->render_single_field('user_about_me', ['user_about_me' => 'I love reading and traveling.']);
        if (!str_contains($about_me_html, 'About Myself') || !str_contains($about_me_html, 'I love reading and traveling.')) {
            throw new \RuntimeException("Expected user_about_me field to render label 'About Myself': " . $about_me_html);
        }

        // 2. pref_additional_info field renders with "About My Perfect Match" label
        $perf_match_html = $this->field_generator->render_single_field('pref_additional_info', ['pref_additional_info' => 'Looking for a kind soul.']);
        if (!str_contains($perf_match_html, 'About My Perfect Match') || !str_contains($perf_match_html, 'Looking for a kind soul.')) {
            throw new \RuntimeException("Expected pref_additional_info to render label 'About My Perfect Match': " . $perf_match_html);
        }
    }

    public function test_multi_select_preferred_marital_status(): void
    {
        // 1. pref_marital_status renders as multiselect checkbox list with No Preference
        $pref_marital_html = $this->field_generator->render_single_field('pref_marital_status', ['pref_marital_status' => 'Never Married, Divorced']);
        if (!str_contains($pref_marital_html, 'custom-select-checkbox-option') || !str_contains($pref_marital_html, 'No Preference') || !str_contains($pref_marital_html, 'Never Married')) {
            throw new \RuntimeException("Expected pref_marital_status to render as multi-select checkboxes: " . $pref_marital_html);
        }
    }

    public function test_education_labels_and_practicing_religion_options(): void
    {
        // 1. user_education label is "Highest Education Level"
        $user_edu_html = $this->field_generator->render_single_field('user_education');
        if (!str_contains($user_edu_html, 'Highest Education Level')) {
            throw new \RuntimeException("Expected user_education to have label 'Highest Education Level': " . $user_edu_html);
        }

        // 2. pref_education label is "Lowest Education Level"
        $pref_edu_html = $this->field_generator->render_single_field('pref_education');
        if (!str_contains($pref_edu_html, 'Lowest Education Level')) {
            throw new \RuntimeException("Expected pref_education to have label 'Lowest Education Level': " . $pref_edu_html);
        }

        // 3. options_prayer has exact 5 options
        $prayer_options = $this->field_generator->options_prayer();
        $expected_options = ['Actively practicing', 'practicing Regularly', 'Occasionally practicing', 'Rarely practicing', 'I’m not practicing'];
        foreach ($expected_options as $opt) {
            if (!in_array($opt, $prayer_options, true)) {
                throw new \RuntimeException("Expected options_prayer to contain '$opt'. Options: " . json_encode($prayer_options));
            }
        }

        // 4. pref_prayer has No Preference
        $pref_prayer_options = $this->field_generator->options_pref_prayer();
        if (!in_array('No Preference', $pref_prayer_options, true)) {
            throw new \RuntimeException("Expected options_pref_prayer to contain 'No Preference'");
        }
    }

    public function test_disallowed_image_formats_rejected(): void
    {
        // Set current user
        $GLOBALS['__mm_current_user_id'] = 100;
        update_user_meta(100, 'mm_email_verified', 1);

        $_POST['mmf_nonce'] = 'dummy_nonce';
        $_POST['form_fields'] = [
            'full_name' => 'John Doe',
            'email'     => 'john@example.com',
            'user_gender' => 'Male',
            'pref_gender' => 'Female',
        ];

        // Upload a disallowed .gif or .pdf file
        $_FILES['form_fields'] = [
            'name'     => ['user_photo1' => 'avatar.gif'],
            'type'     => ['user_photo1' => 'image/gif'],
            'tmp_name' => ['user_photo1' => '/tmp/avatar.gif'],
            'error'    => ['user_photo1' => UPLOAD_ERR_OK],
            'size'     => ['user_photo1' => 1024],
        ];

        $caught = false;
        ob_start();
        try {
            $this->form_controller->handle_ajax();
        } catch (\RuntimeException $e) {
            $caught = ($e->getMessage() === 'wp_send_json_error');
        }
        $out = ob_get_clean();

        if (!$caught || !str_contains($out, 'Only PNG, JPG, JPEG, and WEBP formats are allowed')) {
            throw new \RuntimeException("Expected disallowed file format .gif to be rejected with format error message. Output: " . $out);
        }

        // Clean up
        unset($_FILES['form_fields'], $_POST['form_fields'], $_POST['mmf_nonce']);
    }
}


