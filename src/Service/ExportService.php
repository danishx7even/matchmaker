<?php
declare(strict_types=1);

namespace Matchmaker\Service;

use Matchmaker\Repository\MatchRepository;
use Matchmaker\Core\PMProSync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ExportService
 *
 * Handles generating clean, human-readable CSV exports of candidate pool profiles.
 *
 * @package Matchmaker\Service
 * @since   2.8.0
 */
class ExportService
{
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
    }

    /**
     * Get human-readable column headers for the candidate CSV export.
     *
     * @return array<int, string>
     */
    public function get_csv_headers(): array
    {
        return [
            'User ID',
            'Full Name',
            'Email Address',
            'Phone Number',
            'Application Type',
            'Gender',
            'Age',
            'Birth Date',
            'Country',
            'State / Province',
            'City',
            'Citizenship',
            'Origin / Ethnicity',
            'Religion',
            'Modesty Practice',
            'Height',
            'Languages Spoken',
            'Job / Career',
            'Smoking Habits',
            'Drinking Habits',
            'Prayer Habits',
            'Marital Status',
            'Children',
            'Education Level',
            'Yearly Income Range',
            'Social Links',
            'Preferred Gender',
            'Preferred Age Range',
            'Preferred Location',
            'Preferred Citizenship',
            'Preferred Origin',
            'Preferred Religion',
            'Preferred Modesty',
            'Preferred Height Range',
            'Preferred Smoking',
            'Preferred Drinking',
            'Preferred Marital Status',
            'Preferred Children',
            'Preferred Education',
            'Preferred Yearly Income',
            'About Ideal Partner',
            'Base Membership Tier',
            'Active Services',
            'Approved Matches',
            'Pending Matches',
            'Mutually Matched This Month',
            'Profile Last Updated',
        ];
    }

    /**
     * Format a single candidate pool row into an associative or index-aligned CSV array.
     *
     * @param array<string, mixed> $c Pool candidate row
     * @return array<int, string>
     */
    public function format_candidate_row(array $c): array
    {
        $repo = MatchRepository::instance();
        $uid  = (int) ($c['user_id'] ?? 0);
        $user_obj = get_userdata($uid);
        $meta = $repo->get_meta_block($uid);

        // Name & Email
        $name  = $user_obj ? $user_obj->display_name : ($c['display_name'] ?? ('User #' . $uid));
        $email = $user_obj ? $user_obj->user_email : ($c['user_email'] ?? '');
        $phone = (string) ($meta['phone_number'] ?? '');

        // Application Type
        $is_parent = !empty($c['is_parent_applying']) || !empty($meta['is_parent_applying']);
        $app_type  = $is_parent ? 'Parent applying on behalf of child' : 'Self applying';

        // Age & Height
        $age    = $repo->calc_age((string) ($c['birth_date'] ?? ''));
        $height = $repo->cm_to_feet(!empty($c['height_cm']) ? (int) $c['height_cm'] : null);

        // Location
        $country = (string) ($c['country'] ?? '');
        $state   = (string) ($c['state'] ?? '');
        $city    = (string) ($c['city'] ?? '');

        // Preferred Location
        $pref_loc_parts = array_filter([$c['pref_city'] ?? '', $c['pref_state'] ?? '', $c['pref_country'] ?? '']);
        $pref_location  = !empty($pref_loc_parts) ? implode(', ', $pref_loc_parts) : ($c['pref_location'] ?? 'Any');

        // Preferred Age Range
        $pref_age_min = (int) ($c['preferred_age_min'] ?? 18);
        $pref_age_max = (int) ($c['preferred_age_max'] ?? 80);
        $pref_age_str = "{$pref_age_min} - {$pref_age_max} yrs";

        // Preferred Height Range
        $pref_h_min = !empty($c['preferred_height_min']) ? (int) $c['preferred_height_min'] : null;
        $pref_h_max = !empty($c['preferred_height_max']) ? (int) $c['preferred_height_max'] : null;
        if ($pref_h_min && $pref_h_max) {
            $pref_height_str = "{$pref_h_min}cm - {$pref_h_max}cm";
        } elseif ($pref_h_min) {
            $pref_height_str = "Min {$pref_h_min}cm";
        } elseif ($pref_h_max) {
            $pref_height_str = "Max {$pref_h_max}cm";
        } else {
            $pref_height_str = 'Any';
        }

        // Tier & Services
        $tier_raw   = (string) ($c['user_type'] ?? 'free');
        $tier_label = $repo->format_tier_label($tier_raw);
        $active_srv = class_exists(PMProSync::class) ? PMProSync::instance()->get_user_active_services($uid) : [];
        $services_names = array_column($active_srv, 'name');
        $services_str = !empty($services_names) ? implode(', ', $services_names) : 'None';

        // Matches
        $approved_cnt = (int) ($c['approved_matches'] ?? 0);
        $pending_cnt  = (int) ($c['pending_matches'] ?? 0);
        $has_mutual   = $repo->has_mutual_match_this_month($uid) ? 'Yes' : 'No';

        return [
            (string) $uid,
            $name,
            $email,
            $phone ?: '—',
            $app_type,
            ucfirst((string) ($c['gender'] ?? '')),
            $age !== '—' ? $age : '—',
            (string) ($c['birth_date'] ?? '—'),
            $country ?: '—',
            $state ?: '—',
            $city ?: '—',
            (string) ($meta['user_citizenship'] ?? '—') ?: '—',
            (string) ($c['origin'] ?? '—') ?: '—',
            (string) ($c['religion'] ?? '—') ?: '—',
            (string) ($c['modesty'] ?? '—') ?: '—',
            $height !== '—' ? $height : '—',
            (string) ($c['languages'] ?? '—') ?: '—',
            (string) ($c['job'] ?? '—') ?: '—',
            (string) ($c['smoking'] ?? '—') ?: '—',
            (string) ($c['drinking'] ?? '—') ?: '—',
            (string) ($meta['user_prayer'] ?? '—') ?: '—',
            (string) ($meta['user_marital_status'] ?? '—') ?: '—',
            (string) ($meta['user_children'] ?? '—') ?: '—',
            (string) ($meta['user_education'] ?? '—') ?: '—',
            (string) ($meta['user_income'] ?? '—') ?: '—',
            (string) ($meta['user_social_links'] ?? '—') ?: '—',
            ucfirst((string) ($c['pref_gender'] ?? 'Any')),
            $pref_age_str,
            $pref_location ?: 'Any',
            (string) ($meta['pref_citizenship'] ?? 'Any') ?: 'Any',
            (string) ($c['pref_origin'] ?? 'Any') ?: 'Any',
            (string) ($c['pref_religion'] ?? 'Any') ?: 'Any',
            (string) ($c['pref_modesty'] ?? 'Any') ?: 'Any',
            $pref_height_str,
            (string) ($c['pref_smoking'] ?? 'Any') ?: 'Any',
            (string) ($c['pref_drinking'] ?? 'Any') ?: 'Any',
            (string) ($meta['pref_marital_status'] ?? 'Any') ?: 'Any',
            (string) ($meta['pref_children'] ?? 'Any') ?: 'Any',
            (string) ($meta['pref_education'] ?? 'Any') ?: 'Any',
            (string) ($meta['pref_income'] ?? 'Any') ?: 'Any',
            (string) ($meta['pref_additional_info'] ?? '—') ?: '—',
            $tier_label,
            $services_str,
            (string) $approved_cnt,
            (string) $pending_cnt,
            $has_mutual,
            (string) ($c['updated_at'] ?? '—'),
        ];
    }

    /**
     * Generate complete CSV string with UTF-8 BOM.
     *
     * @param array<string, string> $filters Filters to pass to search_pool
     * @return string CSV file content
     */
    public function generate_csv(array $filters = []): string
    {
        $repo = MatchRepository::instance();
        $candidates = $repo->search_pool($filters);

        $out = fopen('php://temp', 'r+');
        if (!$out) {
            return '';
        }

        // Add UTF-8 BOM for Microsoft Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Write human-readable headers
        fputcsv($out, $this->get_csv_headers());

        // Write candidate rows
        foreach ($candidates as $candidate) {
            fputcsv($out, $this->format_candidate_row($candidate));
        }

        rewind($out);
        $csv_content = stream_get_contents($out);
        fclose($out);

        return is_string($csv_content) ? $csv_content : '';
    }
}
