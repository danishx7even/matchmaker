<?php
declare(strict_types=1);

namespace Matchmaker\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FieldGenerator
 *
 * Generates form fields for matchmaking forms.
 */
class FieldGenerator {
    private static ?self $instance = null;

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
    }

    /**
     * Get hierarchy location data (Country -> State -> Cities).
     *
     * @return array<string, array<string, list<string>>>
     */
    public function get_hierarchy_data(): array
    {
        static $data = null;
        if ($data === null) {
            $json_path = (defined('MM_PATH') ? MM_PATH : dirname(__DIR__, 2) . '/') . 'assets/file/hierarchy_names.json';
            if (file_exists($json_path)) {
                $content = @file_get_contents($json_path);
                if ($content) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        $data = $decoded;
                    }
                }
            }
            if ($data === null) {
                $data = [];
            }
        }
        return $data;
    }

    /**
     * Get country options (specific country names)
     *
     * @return array
     */
    public function options_country(): array
    {
        $hierarchy = $this->get_hierarchy_data();
        if (!empty($hierarchy)) {
            $countries = array_keys($hierarchy);
            sort($countries, SORT_STRING | SORT_FLAG_CASE);
            return array_merge(['Select country'], $countries);
        }

        return [
            'Select country',
            'Saudi Arabia', 'United Arab Emirates', 'Qatar', 'Kuwait', 'Bahrain', 'Oman',
            'Jordan', 'Lebanon', 'Egypt', 'Iraq', 'Syria', 'Palestine', 'Yemen',
            'Morocco', 'Algeria', 'Tunisia', 'Libya', 'Sudan', 'Somalia', 'Mauritania',
            'United States', 'United Kingdom', 'Canada', 'Australia', 'New Zealand',
            'Germany', 'France', 'Netherlands', 'Sweden', 'Norway', 'Denmark', 'Switzerland', 'Belgium', 'Austria', 'Italy', 'Spain', 'Turkey',
            'Pakistan', 'India', 'Bangladesh', 'Indonesia', 'Malaysia', 'Singapore',
            'South Africa', 'Nigeria', 'Kenya',
            'Other'
        ];
    }

    /**
     * Get states for a specific country
     *
     * @param string $country
     * @return array
     */
    public function options_user_state(string $country = ''): array
    {
        $hierarchy = $this->get_hierarchy_data();
        $clean_country = trim($country);
        if (!empty($clean_country) && isset($hierarchy[$clean_country]) && is_array($hierarchy[$clean_country])) {
            $states = array_keys($hierarchy[$clean_country]);
            sort($states, SORT_STRING | SORT_FLAG_CASE);
            return array_merge(['Select state'], $states);
        }
        return ['Select state'];
    }

    /**
     * Get cities for a specific country and state
     *
     * @param string $country
     * @param string $state
     * @return array
     */
    public function options_user_city(string $country = '', string $state = ''): array
    {
        $hierarchy = $this->get_hierarchy_data();
        $clean_country = trim($country);
        $clean_state   = trim($state);
        if (!empty($clean_country) && !empty($clean_state) && isset($hierarchy[$clean_country][$clean_state]) && is_array($hierarchy[$clean_country][$clean_state])) {
            $cities = $hierarchy[$clean_country][$clean_state];
            sort($cities, SORT_STRING | SORT_FLAG_CASE);
            return array_merge(['Select city'], $cities);
        }
        return ['Select city'];
    }

    /**
     * Get preferred country options (with Any Country)
     *
     * @return array
     */
    public function options_pref_country(): array
    {
        $countries = $this->options_country();
        array_shift($countries); // Remove "Select country"
        array_unshift($countries, 'Any Country');
        return $countries;
    }

    /**
     * Get preferred states for a specific preferred country (with Any State)
     *
     * @param string $country
     * @return array
     */
    public function options_pref_state(string $country = ''): array
    {
        $clean_country = trim($country);
        if (empty($clean_country) || strcasecmp($clean_country, 'Any Country') === 0) {
            return ['Any State'];
        }
        $hierarchy = $this->get_hierarchy_data();
        if (isset($hierarchy[$clean_country]) && is_array($hierarchy[$clean_country])) {
            $states = array_keys($hierarchy[$clean_country]);
            sort($states, SORT_STRING | SORT_FLAG_CASE);
            return array_merge(['Any State'], $states);
        }
        return ['Any State'];
    }

    /**
     * Get preferred cities for a specific preferred country and state (with Any City)
     *
     * @param string $country
     * @param string $state
     * @return array
     */
    public function options_pref_city(string $country = '', string $state = ''): array
    {
        $clean_country = trim($country);
        $clean_state   = trim($state);
        if (empty($clean_state) || strcasecmp($clean_state, 'Any State') === 0 || empty($clean_country) || strcasecmp($clean_country, 'Any Country') === 0) {
            return ['Any City'];
        }
        $hierarchy = $this->get_hierarchy_data();
        if (isset($hierarchy[$clean_country][$clean_state]) && is_array($hierarchy[$clean_country][$clean_state])) {
            $cities = $hierarchy[$clean_country][$clean_state];
            sort($cities, SORT_STRING | SORT_FLAG_CASE);
            return array_merge(['Any City'], $cities);
        }
        return ['Any City'];
    }

    /**
     * Get legacy location options fallback
     *
     * @return array
     */
    public function options_location(): array { return $this->options_country(); }
    
    /**
     * Get citizenship options
     *
     * @return array
     */
    public function options_citizenship(): array { return ['Select citizenship', 'Saudi Arabia', 'United Arab Emirates', 'Qatar', 'Kuwait', 'Bahrain', 'Oman', 'Jordan', 'Lebanon', 'Egypt', 'Iraq', 'Syria', 'Palestine', 'Yemen', 'United States', 'Canada', 'United Kingdom', 'Australia', 'Pakistan', 'India', 'Bangladesh', 'Other']; }
    
    /**
     * Get preferred citizenship options (with Any Citizenship as first option)
     *
     * @return array
     */
    public function options_pref_citizenship(): array
    {
        $citizenships = $this->options_citizenship();
        array_shift($citizenships); // Remove "Select citizenship"
        array_unshift($citizenships, 'Any Citizenship');
        return $citizenships;
    }

    /**
     * Get origin options
     *
     * @return array
     */
    public function options_origin(): array { return ['Arab', 'South Asian', 'Middle Eastern', 'North African', 'African', 'European', 'North American', 'South American', 'Central American', 'Caribbean', 'Central Asian', 'Southeast Asian', 'East Asian', 'Australian / Oceanian', 'Other']; }
    
    /**
     * Get preferred origin options (with No Preference)
     *
     * @return array
     */
    public function options_pref_origin(): array { return array_merge($this->options_origin(), ['No Preference']); }

    /**
     * Get religion options
     *
     * @return array
     */
    public function options_religion(): array { return ['Islam', 'Christianity', 'Judaism', 'Hinduism', 'Sikhism', 'Buddhism', 'Other']; }
    
    /**
     * Get preferred religion options (with No Preference)
     *
     * @return array
     */
    public function options_pref_religion(): array { return array_merge($this->options_religion(), ['No Preference']); }

    /**
     * Get marital status options
     *
     * @return array
     */
    public function options_marital(): array { return ['Select status', 'Never Married', 'Divorced', 'Widowed', 'Separated', 'Annulled']; }
    
    /**
     * Get preferred marital status options (with No Preference)
     *
     * @return array
     */
    public function options_pref_marital(): array { return array_merge($this->options_marital(), ['No Preference']); }

    /**
     * Get children options
     *
     * @return array
     */
    public function options_children(): array { return ['Select status', 'Yes', 'No']; }
    
    /**
     * Get preferred children options (with No Preference)
     *
     * @return array
     */
    public function options_pref_children(): array { return array_merge($this->options_children(), ['No Preference']); }

    /**
     * Get modesty options
     *
     * @return array
     */
    public function options_modesty(): array { return ['Select preference', 'Modest', 'Hijab', 'No Hijab', 'Sometimes', 'Niqab']; }
    
    /**
     * Get preferred modesty options (with No Preference)
     *
     * @return array
     */
    public function options_pref_modesty(): array { return array_merge($this->options_modesty(), ['No Preference']); }

    /**
     * Get drinking options
     *
     * @return array
     */
    public function options_drinking(): array { return ['Select preference', 'Yes', 'No', 'Occasionally']; }
    
    /**
     * Get preferred drinking options (with No Preference)
     *
     * @return array
     */
    public function options_pref_drinking(): array { return array_merge($this->options_drinking(), ['No Preference']); }

    /**
     * Get smoking options
     *
     * @return array
     */
    public function options_smoking(): array { return ['Select preference', 'Non-Smoker', 'Occasional Smoker', 'Regular Smoker', 'Former Smoker']; }
    
    /**
     * Get preferred smoking options (with No Preference)
     *
     * @return array
     */
    public function options_pref_smoking(): array { return array_merge($this->options_smoking(), ['No Preference']); }

    /**
     * Get prayer options
     *
     * @return array
     */
    public function options_prayer(): array { return ['Select preference', 'Pray 5 Times a Day', 'Pray Regularly', 'Pray Occasionally', 'Rarely Pray', 'Do Not Pray']; }
    
    /**
     * Get preferred prayer options (with No Preference)
     *
     * @return array
     */
    public function options_pref_prayer(): array { return array_merge($this->options_prayer(), ['No Preference']); }

    /**
     * Get education options
     *
     * @return array
     */
    public function options_education(): array { return ['Select education', 'High School', 'Some College', 'Associate Degree', "Bachelor's Degree", "Master's Degree", 'Doctorate (PhD)', 'Professional Degree', 'Other']; }
    
    /**
     * Get preferred education options (with No Preference)
     *
     * @return array
     */
    public function options_pref_education(): array { return array_merge($this->options_education(), ['No Preference']); }

    /**
     * Get income options in USD
     *
     * @return array
     */
    public function options_income(): array { return ['Select range', '0-100k USD', '100k-500k USD', '500k-1million USD', '1 million + USD']; }

    /**
     * Get preferred income options in USD (with No Preference)
     *
     * @return array
     */
    public function options_pref_income(): array { return ['Select range', 'No Preference', '0-100k USD', '100k-500k USD', '500k-1million USD', '1 million + USD']; }
    
    /**
     * Get height options
     *
     * @return array
     */
    public function options_height(): array {
        return [
            '4\'6" (137 cm)', '4\'7" (140 cm)', '4\'8" (142 cm)', '4\'9" (145 cm)', '4\'10" (147 cm)', '4\'11" (150 cm)',
            '5\'0" (152 cm)', '5\'1" (155 cm)', '5\'2" (157 cm)', '5\'3" (160 cm)', '5\'4" (163 cm)', '5\'5" (165 cm)',
            '5\'6" (168 cm)', '5\'7" (170 cm)', '5\'8" (173 cm)', '5\'9" (175 cm)', '5\'10" (178 cm)', '5\'11" (180 cm)',
            '6\'0" (183 cm)', '6\'1" (185 cm)', '6\'2" (188 cm)', '6\'3" (191 cm)', '6\'4" (193 cm)', '6\'5" (196 cm)',
            '6\'6" (198 cm)', '6\'7" (201 cm)', '6\'8" (203 cm) or taller',
        ];
    }
    
    /**
     * Get age options
     *
     * @return array
     */
    public function options_age(): array { $out = []; for ($a = 18; $a <= 80; $a++) { $out[] = (string)$a; } return $out; }

    /* Low-level render primitives — return HTML strings */
    private function field_open(string $name, string $extra_class = ''): string { return '<div class="elementor-field-group elementor-field-group-' . esc_attr($name) . ($extra_class ? ' ' . esc_attr($extra_class) : '') . '">'; }
    private function field_close(): string { return '</div>'; }
    private function label(string $for, string $text, bool $required = true): string {
        $req_html = $required ? ' <span class="mm-required-star" style="color:#e11d48;font-weight:700;">*</span>' : '';
        return '<label for="form-field-' . esc_attr($for) . '" class="elementor-field-label">' . esc_html($text) . $req_html . '</label>';
    }
    private function text(string $name, string $type = 'text', string $placeholder = '', $value = ''): string { return '<input size="1" type="' . esc_attr($type) . '" name="form_fields[' . esc_attr($name) . ']" id="form-field-' . esc_attr($name) . '" class="elementor-field elementor-size-sm elementor-field-textual" placeholder="' . esc_attr($placeholder) . '" value="' . esc_attr((string)$value) . '" required>'; }
    private function date(string $name, $value = ''): string { return '<input type="date" name="form_fields[' . esc_attr($name) . ']" id="form-field-' . esc_attr($name) . '" class="elementor-field elementor-size-sm elementor-field-textual" value="' . esc_attr((string)$value) . '" required>'; }
    private function textarea(string $name, string $placeholder = '', int $rows = 2, $value = ''): string { return '<textarea class="elementor-field-textual elementor-field elementor-size-sm" name="form_fields[' . esc_attr($name) . ']" id="form-field-' . esc_attr($name) . '" rows="' . (int)$rows . '" placeholder="' . esc_attr($placeholder) . '" required>' . esc_textarea((string)$value) . '</textarea>'; }

    private function select(string $name, array $options, $selected_val = ''): string {
        $field_id = 'form-field-' . $name;
        $current  = (string) $selected_val !== '' ? (string) $selected_val : ($options[0] ?? '');
        $html = '<div class="elementor-field elementor-select-wrapper remove-before">';
        $html .= '<div class="custom-select-wrapper">';
        $html .= '<select name="form_fields[' . esc_attr($name) . ']" id="' . esc_attr($field_id) . '" class="elementor-field-textual elementor-size-sm" required>';
        foreach ($options as $i => $opt) {
            $is_placeholder = (preg_match('/^select\b/i', trim($opt)) === 1);
            $val_attr       = $is_placeholder ? '' : $opt;
            $dis_attr       = $is_placeholder ? ' disabled' : '';
            $selected       = ($opt === $current || ($is_placeholder && empty($selected_val))) ? ' selected' : '';
            $html .= '<option value="' . esc_attr($val_attr) . '"' . $dis_attr . $selected . '>' . esc_html($opt) . '</option>';
        }
        $html .= '</select>';
        $is_placeholder_display = (preg_match('/^select\b/i', trim($current)) === 1 || empty($current));
        $html .= '<div class="custom-select-display' . ($is_placeholder_display ? ' placeholder' : '') . '">' . esc_html($current) . '</div>';
        $html .= '<div class="custom-select-options">';
        foreach ($options as $i => $opt) {
            $is_ph = (preg_match('/^select\b/i', trim($opt)) === 1);
            $sel_class = ($opt === $current) ? ' selected' : '';
            $dis_class = $is_ph ? ' disabled' : '';
            $html .= '<div class="custom-select-option' . $sel_class . $dis_class . '" data-index="' . (int)$i . '">' . esc_html($opt) . '</div>';
        }
        $html .= '</div></div></div>';
        return $html;
    }

    private function multiselect(string $name, array $options, string $placeholder, $selected_values = []): string {
        $field_id = 'form-field-' . $name;
        $selected_arr = is_array($selected_values) ? $selected_values : array_filter(array_map('trim', explode(',', (string)$selected_values)));
        $html = '<div class="elementor-field elementor-select-wrapper remove-before">';
        $html .= '<div class="custom-select-wrapper custom-multiselect-wrapper">';
        $html .= '<select name="form_fields[' . esc_attr($name) . '][]" id="' . esc_attr($field_id) . '" multiple class="elementor-field-textual elementor-size-sm" required>';
        foreach ($options as $opt) {
            $selected = in_array($opt, $selected_arr, true) ? ' selected' : '';
            $html .= '<option value="' . esc_attr($opt) . '"' . $selected . '>' . esc_html($opt) . '</option>';
        }
        $html .= '</select>';
        $count = count($selected_arr);
        $display_text = $count > 0 ? implode(', ', $selected_arr) : $placeholder;
        $has_val = $count > 0;
        $html .= '<div class="custom-select-display' . ($has_val ? '' : ' placeholder') . '" data-placeholder="' . esc_attr($placeholder) . '" title="' . esc_attr($display_text) . '">' . esc_html($display_text) . '</div>';
        $html .= '<div class="custom-select-options">';
        foreach ($options as $i => $opt) {
            $checked = in_array($opt, $selected_arr, true) ? ' checked' : '';
            $html .= '<label class="custom-select-checkbox-option"><input type="checkbox" data-index="' . (int)$i . '"' . $checked . '> ' . esc_html($opt) . '</label>';
        }
        $html .= '</div></div></div>';
        return $html;
    }

    private function radio(string $name, array $options, $selected_val = ''): string {
        $html = '<div class="elementor-field-subgroup elementor-subgroup-inline">';
        foreach ($options as $i => $opt) {
            $checked = (strcasecmp((string)$selected_val, (string)$opt) === 0) ? ' checked' : '';
            $html .= '<span class="elementor-field-option">';
            $html .= '<input type="radio" value="' . esc_attr($opt) . '" id="form-field-' . esc_attr($name) . '-' . $i . '" name="form_fields[' . esc_attr($name) . ']"' . $checked . ' required> ';
            $html .= '<label for="form-field-' . esc_attr($name) . '-' . $i . '">' . esc_html($opt) . '</label>';
            $html .= '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    private function upload(string $name, $preview_url = ''): string {
        $has_preview = !empty($preview_url);
        $extra_class = $has_preview ? ' has-preview' : '';
        $html = '<div class="elementor-field-type-upload elementor-field-group elementor-column elementor-field-group-' . esc_attr($name) . $extra_class . '">';
        $html .= '<input type="file" accept="image/*" name="form_fields[' . esc_attr($name) . ']" id="form-field-' . esc_attr($name) . '" class="elementor-field elementor-size-sm elementor-upload-field"' . ($has_preview ? '' : ' required') . '>';
        if ($has_preview) { $html .= '<img src="' . esc_url($preview_url) . '" class="upload-preview-img" alt="Photo Preview">'; }
        $html .= '</div>';
        return $html;
    }

    private function range_select(string $name, array $options, string $placeholder, $selected_val = ''): string {
        $html = '<div class="custom-select-wrapper">';
        $html .= '<select name="form_fields[' . esc_attr($name) . ']" class="elementor-field-textual elementor-size-sm" required>';
        $html .= '<option value="">' . esc_html($placeholder) . '</option>';
        foreach ($options as $opt) {
            $selected = ((string)$opt === (string)$selected_val) ? ' selected' : '';
            $html .= '<option value="' . esc_attr($opt) . '"' . $selected . '>' . esc_html($opt) . '</option>';
        }
        $html .= '</select>';
        $is_set = !empty($selected_val);
        $display = $is_set ? (string)$selected_val : $placeholder;
        $html .= '<div class="custom-select-display' . ($is_set ? '' : ' placeholder') . '">' . esc_html($display) . '</div>';
        $html .= '<div class="custom-select-options">';
        $html .= '<div class="custom-select-option' . (!$is_set ? ' selected' : '') . '" data-index="0">' . esc_html($placeholder) . '</div>';
        foreach ($options as $i => $opt) {
            $sel = ((string)$opt === (string)$selected_val) ? ' selected' : '';
            $html .= '<div class="custom-select-option' . $sel . '" data-index="' . ($i + 1) . '">' . esc_html($opt) . '</div>';
        }
        $html .= '</div></div>';
        return $html;
    }

    private function range(string $label, string $min_name, string $max_name, array $options, string $min_placeholder, string $max_placeholder, $min_val = '', $max_val = ''): string {
        $html = '<div class="elementor-field-group elementor-column range-field-group">';
        $html .= '<label class="elementor-field-label">' . esc_html($label) . ' <span class="mm-required-star" style="color:#e11d48;font-weight:700;">*</span></label>';
        $html .= '<div class="custom-range-field">';
        $html .= $this->range_select($min_name, $options, $min_placeholder, $min_val);
        $html .= '<span class="range-to-label">to</span>';
        $html .= $this->range_select($max_name, $options, $max_placeholder, $max_val);
        $html .= '</div></div>';
        return $html;
    }

    /**
     * Get SVG icon by name
     *
     * @param string $name Icon name
     * @return string
     */
    public function icon(string $name): string {
        $icons = [
            'user'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.5-4 4-6 7.5-6s6 2 7.5 6"/></svg>',
            'pin'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>',
            'info'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5" stroke-linecap="round"/><circle cx="12" cy="7.7" r="0.9" fill="currentColor" stroke="none"/></svg>',
            'heart'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20s-7-4.4-9.3-8.8C1.3 8 2.7 5 6 5c2 0 3.3 1 4 2.2C10.7 6 12 5 14 5c3.3 0 4.7 3 3.3 6.2C15 15.6 12 20 12 20z"/></svg>',
            'moon'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.8 6.8 0 0 0 10.5 10.5z"/></svg>',
            'cap'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 9l10-4 10 4-10 4-10-4z"/><path d="M6 11v4.5c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V11" stroke-linecap="round"/></svg>',
            'camera' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 8h3l2-2h6l2 2h3v11H4z"/><circle cx="12" cy="13" r="3.2"/></svg>',
        ];
        return $icons[$name] ?? '';
    }

    /**
     * Open a form section
     *
     * @param string $icon Icon name
     * @param string $title Section title
     * @param string $subtitle Optional subtitle
     * @param string $extra_class Optional extra class
     * @return string
     */
    public function section_open(string $icon, string $title, string $subtitle = '', string $extra_class = ''): string {
        $html = '<div class="form-section' . ($extra_class ? ' ' . esc_attr($extra_class) : '') . '">';
        $html .= '<div class="form-section-title">';
        if ($icon) $html .= '<span class="section-icon">' . $this->icon($icon) . '</span>';
        $html .= esc_html($title) . '</div>';
        if ($subtitle) $html .= '<p class="form-section-subtitle">' . esc_html($subtitle) . '</p>';
        $html .= '<div class="form-section-fields">';
        return $html;
    }

    /**
     * Close a form section
     *
     * @return string
     */
    public function section_close(): string { return '</div></div>'; }

    /**
     * Render a single field by name
     *
     * @param string $name Field name
     * @param array $values Current values array
     * @return string
     */
    public function render_single_field(string $name, array $values = []): string {
        $val  = $values[$name] ?? '';
        $html = '';

        if ($name === 'email') {
            $html .= $this->field_open('email');
            $html .= $this->label('email', 'Email');
            $html .= '<input size="1" type="email" name="form_fields[email]" id="form-field-email" class="elementor-field elementor-size-sm elementor-field-textual is-readonly" placeholder="Enter your email address" value="' . esc_attr((string)$val) . '" readonly tabindex="-1">';
            $html .= $this->field_close();
            return $html;
        }

        if ($name === 'user_country' || $name === 'user_location') {
            $label = ($name === 'user_location') ? 'Country / Location' : 'Country';
            $html .= $this->field_open($name, 'location-cascade-group');
            $html .= $this->label($name, $label);
            $html .= $this->select($name, $this->options_country(), $val);
            $html .= $this->field_close();
            return $html;
        }

        if ($name === 'user_state') {
            $country = (string) ($values['user_country'] ?? $values['country'] ?? $values['user_location'] ?? '');
            $html .= $this->field_open('user_state', 'location-cascade-group');
            $html .= $this->label('user_state', 'State / Province');
            $html .= $this->select('user_state', $this->options_user_state($country), $val);
            $html .= $this->field_close();
            return $html;
        }

        if ($name === 'user_city') {
            $country = (string) ($values['user_country'] ?? $values['country'] ?? $values['user_location'] ?? '');
            $state   = (string) ($values['user_state'] ?? $values['state'] ?? '');
            $html .= $this->field_open('user_city', 'location-cascade-group');
            $html .= $this->label('user_city', 'City');
            $html .= $this->select('user_city', $this->options_user_city($country, $state), $val);
            $html .= $this->field_close();
            return $html;
        }

        if ($name === 'pref_country' || $name === 'pref_location') {
            $label = ($name === 'pref_location') ? 'Preferred Country / Location' : 'Preferred Country';
            $html .= $this->field_open($name, 'location-cascade-group');
            $html .= $this->label($name, $label);
            $html .= $this->select($name, $this->options_pref_country(), $val !== '' ? $val : 'Any Country');
            $html .= $this->field_close();
            return $html;
        }

        if ($name === 'pref_state') {
            $pref_country = (string) ($values['pref_country'] ?? $values['pref_location'] ?? '');
            $html .= $this->field_open('pref_state', 'location-cascade-group');
            $html .= $this->label('pref_state', 'Preferred State / Province');
            $html .= $this->select('pref_state', $this->options_pref_state($pref_country), $val !== '' ? $val : 'Any State');
            $html .= $this->field_close();
            return $html;
        }

        if ($name === 'pref_city') {
            $pref_country = (string) ($values['pref_country'] ?? $values['pref_location'] ?? '');
            $pref_state   = (string) ($values['pref_state'] ?? '');
            $html .= $this->field_open('pref_city', 'location-cascade-group');
            $html .= $this->label('pref_city', 'Preferred City');
            $html .= $this->select('pref_city', $this->options_pref_city($pref_country, $pref_state), $val !== '' ? $val : 'Any City');
            $html .= $this->field_close();
            return $html;
        }

        $text_configs = [
            'full_name'      => ['text', 'Full Name', 'Enter your full name'],
            'phone_number'   => ['text', 'Phone Number', 'Enter your phone number'],
            'user_languages' => ['text', 'Spoken Languages (Separate multiple selections with commas)', 'Enter the languages you speak'],
            'pref_languages' => ['text', 'Preferred Languages (Separate multiple selections with commas)', 'Enter preferred languages'],
            'user_job'       => ['text', 'Job / Career', 'Enter your job or career'],
        ];

        $select_configs = [
            'user_origin'         => ['Ethnicity / Origin', $this->options_origin()],
            'user_religion'       => ['Religion', $this->options_religion()],
            'user_citizenship'    => ['Citizenship', $this->options_citizenship()],
            'user_height'         => ['Height', $this->options_height()],
            'user_marital_status' => ['Marital Status', $this->options_marital()],
            'user_children'       => ['Do You Have Children', $this->options_children()],
            'user_modesty'        => ['Hijab / Modesty Practice', $this->options_modesty()],
            'user_drinking'       => ['Drinking Habits', $this->options_drinking()],
            'user_smoking'        => ['Smoking Habits', $this->options_smoking()],
            'user_prayer'         => ['Prayer Habits', $this->options_prayer()],
            'user_education'      => ['Highest Education Level', $this->options_education()],
            'user_income'         => ['Income Range', $this->options_income()],
            'pref_origin'         => ['Preferred Origin / Ethnicity', $this->options_pref_origin()],
            'pref_religion'       => ['Preferred Religion', $this->options_pref_religion()],
            'pref_marital_status' => ['Preferred Marital Status', $this->options_pref_marital()],
            'pref_children'       => ['Children Preference', $this->options_pref_children()],
            'pref_modesty'        => ['Preferred Hijab / Modesty', $this->options_pref_modesty()],
            'pref_drinking'       => ['Drinking Preference', $this->options_pref_drinking()],
            'pref_smoking'        => ['Smoking Preference', $this->options_pref_smoking()],
            'pref_prayer'         => ['Prayer Habits Preference', $this->options_pref_prayer()],
            'pref_education'      => ['Preferred Education Level', $this->options_pref_education()],
            'pref_income'         => ['Preferred Income Range', $this->options_pref_income()],
        ];

        $multi_configs = [
            'pref_citizenship' => ['Preferred Citizenship', $this->options_pref_citizenship(), 'Any Citizenship'],
        ];

        $html = '';

        if (isset($text_configs[$name])) {
            $html .= $this->field_open($name);
            $html .= $this->label($name, $text_configs[$name][1]);
            $html .= $this->text($name, $text_configs[$name][0], $text_configs[$name][2], $val);
            $html .= $this->field_close();
        } elseif (isset($select_configs[$name])) {
            $html .= $this->field_open($name);
            $html .= $this->label($name, $select_configs[$name][0]);
            $html .= $this->select($name, $select_configs[$name][1], $val);
            $html .= $this->field_close();
        } elseif (isset($multi_configs[$name])) {
            $html .= $this->field_open($name);
            $html .= $this->label($name, $multi_configs[$name][0]);
            $html .= $this->multiselect($name, $multi_configs[$name][1], $multi_configs[$name][2], $val);
            $html .= $this->field_close();
        } elseif ($name === 'birth_date') {
            $html .= $this->field_open('birth_date');
            $html .= $this->label('birth_date', 'Date of Birth');
            $html .= $this->date('birth_date', $val);
            $html .= $this->field_close();
        } elseif ($name === 'user_gender' || $name === 'pref_gender') {
            $label = ($name === 'user_gender') ? 'Gender' : 'Preferred Gender';
            $html .= $this->field_open($name);
            $html .= $this->label($name, $label);
            $html .= $this->radio($name, ['Female', 'Male'], $val);
            $html .= $this->field_close();
        } elseif (in_array($name, ['user_photo1', 'user_photo2', 'user_photo3'], true)) {
            $html .= $this->upload($name, $val);
        } elseif ($name === 'user_social_links') {
            $html .= $this->field_open('user_social_links');
            $html .= $this->label('user_social_links', 'Social Media Links (Separate multiple selections with commas)');
            $html .= $this->textarea('user_social_links', 'Add your social media links', 2, $val);
            $html .= $this->field_close();
        } elseif ($name === 'pref_additional_info') {
            $html .= $this->field_open('pref_additional_info');
            $html .= $this->label('pref_additional_info', 'About Your Ideal Partner');
            $html .= $this->textarea('pref_additional_info', 'Describe the qualities, values, and traits you are seeking in a lifelong partner...', 4, $val);
            $html .= $this->field_close();
        } elseif ($name === 'preferred_age_range') {
            $html .= $this->range('Preferred Age Range', 'preferred_age_min', 'preferred_age_max', $this->options_age(), 'Min Age', 'Max Age', $values['preferred_age_min'] ?? '', $values['preferred_age_max'] ?? '');
        } elseif ($name === 'preferred_height_range') {
            $html .= $this->range('Preferred Height', 'preferred_height_min', 'preferred_height_max', $this->options_height(), 'Min Height', 'Max Height', $values['preferred_height_min'] ?? '', $values['preferred_height_max'] ?? '');
        }

        return $html;
    }

    /**
     * Print required inline assets for fields
     *
     * @return string
     */
    public function print_assets(): string
    {
        ob_start();
        ?>
<style>
/* minimal placeholder styles to keep tests stable */
.mmf-form-placeholder{padding:1rem;background:#fff;border:1px solid #e5e5e5}
</style>
<script>(function(){/* placeholder */})();</script>
        <?php
        return (string) ob_get_clean();
    }
}
