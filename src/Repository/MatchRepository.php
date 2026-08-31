<?php
declare(strict_types=1);
namespace Matchmaker\Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MatchRepository
 *
 * The ONLY class permitted to call `global $wpdb` in the Matchmaker plugin.
 *
 * Responsibilities:
 *   - All CRUD operations on `wp_matchmaking_pool` (candidate criteria).
 *   - All CRUD operations on `wp_matches` (bidirectional match records).
 *   - All CRUD operations on `wp_matchmaker_notifications`.
 *   - All `wp_usermeta` read/write for matchmaking-specific meta keys.
 *   - Data normalization helpers: calc_age(), cm_to_feet(), etc.
 *
 * Canonical Pair Rule (enforced in write methods):
 *   user_one_id = min(id_a, id_b), user_two_id = max(id_a, id_b)
 *
 * @package Matchmaker\Repository
 * @since   1.0.0
 */
class MatchRepository
{
    private static ?self $instance = null;

    /**
     * All usermeta keys managed by this plugin.
     */
    public const META_KEYS = [
        'phone_number', 'user_citizenship', 'pref_citizenship',
        'user_social_links', 'pref_social_links',
        'user_marital_status', 'pref_marital_status',
        'user_children', 'pref_children',
        'user_prayer', 'pref_prayer',
        'user_education', 'pref_education',
        'user_income', 'pref_income',
        'pref_additional_info',
        'user_photo1', 'user_photo2', 'user_photo3',
        'cycle_matches_count', 'mm_last_match_run',
        'user_type',
    ];

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

    private function __construct() {}

    // =========================================================================
    // HELPER / NORMALIZE METHODS
    // =========================================================================

    /**
     * Calculate age in years from a birth date string.
     *
     * @param string|null $birth_date Date string in Y-m-d format.
     * @return string Age in years, or '—' on invalid input.
     */
    public function calc_age(?string $birth_date): string
    {
        if (empty($birth_date) || $birth_date === '0000-00-00') {
            return '—';
        }
        try {
            return (string) (new \DateTime($birth_date))->diff(new \DateTime())->y;
        } catch (\Exception $e) {
            return '—';
        }
    }

    /**
     * Convert centimetres to a human-readable feet+inches string.
     *
     * @param int|null $cm Height in centimetres.
     * @return string e.g. "5'10" (178 cm)" or '—' for zero/null input.
     */
    public function cm_to_feet(?int $cm): string
    {
        if (empty($cm) || $cm <= 0) {
            return '—';
        }
        $inches = $cm / 2.54;
        $feet   = (int) floor($inches / 12);
        $rem    = (int) round($inches - ($feet * 12));
        if ($rem === 12) {
            $feet++;
            $rem = 0;
        }
        return "{$feet}'{$rem}\" ({$cm} cm)";
    }

    /**
     * Format a membership tier slug into a human-readable label.
     *
     * @param string $user_type e.g. 'monthly', 'one_on_one', 'free', 'event'.
     * @return string Translated label.
     */
    public function format_tier_label(string $user_type): string
    {
        return match ($user_type) {
            'monthly'    => __('Monthly Member', 'matchmaker'),
            'one_on_one' => __('1-on-1 VIP Member', 'matchmaker'),
            'event'      => __('Event Member', 'matchmaker'),
            default      => __('Free Member', 'matchmaker'),
        };
    }

    /**
     * Determine whether a user_type slug is premium (eligible for matches).
     *
     * @param string $user_type Membership tier slug.
     * @return bool True if monthly or one_on_one.
     */
    public function is_premium_tier(string $user_type): bool
    {
        return in_array($user_type, ['monthly', 'one_on_one'], true);
    }

    /**
     * Get maximum matches allowed per billing cycle from settings.
     *
     * @return int
     */
    public function get_max_cycle_matches(): int
    {
        $quota = (int) get_option('mm_max_cycle_matches', 10);
        return $quota > 0 ? $quota : 10;
    }

    /**
     * Get match review expiry duration in days from settings.
     *
     * @return int
     */
    public function get_match_expiry_days(): int
    {
        $days = (int) get_option('mm_match_expiry_days', 7);
        return $days > 0 ? $days : 7;
    }

    /**
     * Parse a height option string and extract the centimetre value.
     *
     * Supports formats like "5'10" (178 cm)" or plain "178".
     *
     * @param string|null $raw Raw height string.
     * @return int|null Parsed cm value, or null if not parseable.
     */
    public function parse_height_to_cm(?string $raw): ?int
    {
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
    }

    /**
     * Normalize a comma-or-array list of values into a clean CSV string.
     *
     * @param mixed $raw Array or comma-separated string.
     * @return string Cleaned, comma-separated list.
     */
    public function normalize_list(mixed $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);
        $clean = [];
        foreach ($items as $item) {
            $val = $this->sanitize_select((string) $item);
            if ($val !== '') {
                $clean[] = $val;
            }
        }
        return implode(',', $clean);
    }

    /**
     * Sanitize a dropdown/select value, returning '' for placeholder values.
     *
     * @param string|null $raw Raw select value.
     * @return string Cleaned value or empty string.
     */
    public function sanitize_select(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        $clean = sanitize_text_field(trim($raw));
        if (preg_match('/^select\b/i', $clean) === 1) {
            return '';
        }
        return $clean;
    }

    // =========================================================================
    // USERMETA METHODS
    // =========================================================================

    /**
     * Get all matchmaking-related usermeta for a given user.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string, mixed> Keyed by meta key.
     */
    public function get_meta_block(int $user_id): array
    {
        $out = [];
        foreach (self::META_KEYS as $k) {
            $out[$k] = get_user_meta($user_id, $k, true);
        }
        return $out;
    }

    /**
     * Save a map of meta key => value pairs for a user.
     *
     * @param int                  $user_id  WordPress user ID.
     * @param array<string, mixed> $meta_map Associative array of meta key => value.
     * @return void
     */
    public function save_meta_block(int $user_id, array $meta_map): void
    {
        foreach ($meta_map as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
    }

    /**
     * Save a single usermeta value.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $key     Meta key.
     * @param mixed  $value   Meta value.
     * @return void
     */
    public function save_meta(int $user_id, string $key, mixed $value): void
    {
        update_user_meta($user_id, $key, $value);
    }

    /**
     * Get a single usermeta value.
     *
     * @param int    $user_id WordPress user ID.
     * @param string $key     Meta key.
     * @return mixed Meta value or empty string.
     */
    public function get_meta(int $user_id, string $key): mixed
    {
        return get_user_meta($user_id, $key, true);
    }

    /**
     * Get profile photo URLs for a user (up to 3).
     *
     * @param int $user_id WordPress user ID.
     * @return array<string, string> Keys: user_photo1, user_photo2, user_photo3.
     */
    public function get_user_photos(int $user_id): array
    {
        return [
            'user_photo1' => (string) get_user_meta($user_id, 'user_photo1', true),
            'user_photo2' => (string) get_user_meta($user_id, 'user_photo2', true),
            'user_photo3' => (string) get_user_meta($user_id, 'user_photo3', true),
        ];
    }

    /**
     * Increment the cycle_matches_count counter for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return void
     */
    public function increment_quota(int $user_id): void
    {
        $current_val = $this->maybe_reset_monthly_quota($user_id);
        update_user_meta($user_id, 'cycle_matches_count', $current_val + 1);
    }

    /**
     * Check and reset monthly quota if user has entered a new PMPro cycle month.
     *
     * @param int $user_id WordPress user ID.
     * @return int Current quota count for the term.
     */
    public function maybe_reset_monthly_quota(int $user_id): int
    {
        $last_cycle_month = (string) get_user_meta($user_id, 'mm_cycle_month', true);
        $current_month    = gmdate('Y-m');

        if ($last_cycle_month !== $current_month) {
            update_user_meta($user_id, 'cycle_matches_count', 0);
            update_user_meta($user_id, 'mm_cycle_month', $current_month);
            return 0;
        }

        return (int) get_user_meta($user_id, 'cycle_matches_count', true);
    }

    // =========================================================================
    // POOL METHODS (wp_matchmaking_pool)
    // =========================================================================

    /**
     * Get a user's pool record by user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string, mixed>|null Pool row or null if not found.
     */
    public function get_user_pool(int $user_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaking_pool';
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Upsert (INSERT or REPLACE) a pool record.
     *
     * @param array<string, mixed> $payload Assoc array of column => value.
     * @return int|false Number of rows affected or false on failure.
     */
    public function upsert_pool(array $payload): int|false
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaking_pool';
        return $wpdb->replace($table, $payload);
    }

    /**
     * Search and filter the candidate pool with optional filters.
     *
     * @param array<string, string> $filters Assoc of filter key => value (search, user_type, gender).
     * @return array<int, object> Array of pool rows with joined user data.
     */
    public function search_pool(array $filters = []): array
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $where = ['1=1'];
        $args  = [];

        if (!empty($filters['user_type'])) {
            $where[] = 'p.user_type = %s';
            $args[]  = $filters['user_type'];
        }
        if (!empty($filters['gender'])) {
            $where[] = 'p.gender = %s';
            $args[]  = $filters['gender'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(u.user_email LIKE %s OR u.display_name LIKE %s OR p.location LIKE %s)';
            $wc      = '%' . $wpdb->esc_like($filters['search']) . '%';
            $args[]  = $wc;
            $args[]  = $wc;
            $args[]  = $wc;
        }

        $where_sql = implode(' AND ', $where);

        $query = "
            SELECT p.*, u.user_email, u.display_name,
                (SELECT COUNT(*) FROM {$matches_table} m
                    WHERE (m.user_one_id = p.user_id OR m.user_two_id = p.user_id) AND m.status = 'approved') AS approved_matches,
                (SELECT COUNT(*) FROM {$matches_table} m
                    WHERE (m.user_one_id = p.user_id OR m.user_two_id = p.user_id) AND m.status = 'pending_review') AS pending_matches
            FROM {$pool_table} p
            INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
            WHERE {$where_sql}
            ORDER BY p.updated_at DESC
        ";

        $results = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($query, ...$args), ARRAY_A)
            : $wpdb->get_results($query, ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get eligible candidate pool records for the async matching algorithm.
     *
     * Returns active pool members of opposite gender to the target user,
     * excluding candidates already matched with this user.
     *
     * @param array<string, mixed> $target_pool The pool record of the user we are matching for.
     * @return array<int, array<string, mixed>> Array of candidate pool rows.
     */
    public function get_candidates_for_matching(array $target_pool): array
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $target_user_id = (int) $target_pool['user_id'];
        $opp_gender     = ($target_pool['gender'] === 'male') ? 'female' : 'male';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.* FROM {$pool_table} c
                 WHERE c.user_id != %d
                   AND c.is_active = 1
                   AND c.gender = %s
                   AND NOT EXISTS (
                       SELECT 1 FROM {$matches_table} m
                       WHERE m.user_one_id = LEAST(%d, c.user_id)
                         AND m.user_two_id = GREATEST(%d, c.user_id)
                   )",
                $target_user_id,
                $opp_gender,
                $target_user_id,
                $target_user_id
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get candidates for the manual match page with advanced filters.
     *
     * @param int                   $user_id   The target user.
     * @param array<string, mixed>  $pool      The target user's pool record.
     * @param array<string, mixed>  $filters   Filter key => value (f_gender, f_age_min, etc.).
     * @return array<int, array<string, mixed>> Candidate rows.
     */
    public function get_manual_match_candidates(int $user_id, array $pool, array $filters): array
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $where = ['(c.user_id != %d AND (c.is_active = 1 OR c.is_active IS NULL))'];
        $args  = [$user_id];

        if (!empty($filters['f_gender'])) {
            $where[] = '(LOWER(TRIM(c.gender)) = %s OR FIND_IN_SET(%s, REPLACE(LOWER(c.gender), \', \', \',\')) > 0)';
            $gender_val = strtolower(trim($filters['f_gender']));
            $args[]  = $gender_val;
            $args[]  = $gender_val;
        }

        $f_age_min = (int) ($filters['f_age_min'] ?? 18);
        $f_age_max = (int) ($filters['f_age_max'] ?? 80);
        $where[] = '(c.birth_date IS NULL OR c.birth_date = \'0000-00-00\' OR TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d)';
        $args[]  = $f_age_min;
        $args[]  = $f_age_max;

        foreach (['f_location', 'f_religion', 'f_modesty', 'f_origin'] as $filter_key) {
            $col = str_replace('f_', '', $filter_key);
            if (!empty($filters[$filter_key]) && strtolower($filters[$filter_key]) !== 'any') {
                $where[] = "(c.{$col} IS NULL OR c.{$col} = '' OR FIND_IN_SET(c.{$col}, REPLACE(%s, ', ', ',')) > 0 OR c.{$col} LIKE CONCAT('%%', %s, '%%'))";
                $args[]  = $filters[$filter_key];
                $args[]  = $filters[$filter_key];
            }
        }

        // Bi-directional preference checks
        foreach (['location', 'religion', 'modesty'] as $field) {
            if (!empty($pool[$field]) && strtolower($pool[$field]) !== 'any') {
                $where[] = "(c.pref_{$field} IS NULL OR c.pref_{$field} = '' OR LOWER(TRIM(c.pref_{$field})) = 'any' OR FIND_IN_SET(%s, REPLACE(c.pref_{$field}, ', ', ',')) > 0 OR LOWER(c.pref_{$field}) LIKE CONCAT('%%', %s, '%%'))";
                $args[]  = $pool[$field];
                $args[]  = strtolower($pool[$field]);
            }
        }

        if (!empty($pool['gender'])) {
            $user_g = strtolower(trim($pool['gender']));
            $where[] = "(c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = 'any' OR LOWER(TRIM(c.pref_gender)) = %s OR FIND_IN_SET(%s, REPLACE(LOWER(c.pref_gender), ', ', ',')) > 0)";
            $args[]  = $user_g;
            $args[]  = $user_g;
        }

        $where[] = "NOT EXISTS (
            SELECT 1 FROM {$matches_table} m
            WHERE m.user_one_id = LEAST(%d, c.user_id)
              AND m.user_two_id = GREATEST(%d, c.user_id)
        )";
        $args[] = $user_id;
        $args[] = $user_id;

        // Usermeta filters (marital status, education, citizenship)
        if (!empty($filters['f_marital_status']) && strtolower($filters['f_marital_status']) !== 'any') {
            $where[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um_ms WHERE um_ms.user_id = c.user_id AND um_ms.meta_key = 'user_marital_status' AND um_ms.meta_value = %s)";
            $args[]  = $filters['f_marital_status'];
        }
        if (!empty($filters['f_education']) && strtolower($filters['f_education']) !== 'any') {
            $where[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um_ed WHERE um_ed.user_id = c.user_id AND um_ed.meta_key = 'user_education' AND um_ed.meta_value = %s)";
            $args[]  = $filters['f_education'];
        }
        if (!empty($filters['f_citizenship']) && strtolower($filters['f_citizenship']) !== 'any') {
            $where[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um_cz WHERE um_cz.user_id = c.user_id AND um_cz.meta_key = 'user_citizenship' AND um_cz.meta_value = %s)";
            $args[]  = $filters['f_citizenship'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT c.*, u.user_email, u.display_name
            FROM {$pool_table} c
            INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE {$where_sql}
            ORDER BY c.updated_at DESC
        ";

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        return $results;
    }

    /**
     * Check whether a user is present and active in the pool.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True if active pool record exists.
     */
    public function is_user_in_pool(int $user_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaking_pool';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_active = 1", $user_id)
        ) > 0;
    }

    /**
     * Get user_type from the pool for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return string User type slug or empty string.
     */
    public function get_pool_user_type(int $user_id): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaking_pool';
        return (string) $wpdb->get_var(
            $wpdb->prepare("SELECT user_type FROM {$table} WHERE user_id = %d", $user_id)
        );
    }

    /**
     * Update the user_type column in the pool.
     *
     * @param int    $user_id   WordPress user ID.
     * @param string $user_type New user type slug.
     * @return void
     */
    public function update_pool_user_type(int $user_id, string $user_type): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaking_pool';
        $wpdb->update($table, ['user_type' => $user_type], ['user_id' => $user_id], ['%s'], ['%d']);
    }

    /**
     * Get all active pool records (used for idle-user cron sweeps).
     *
     * @param string $user_type Membership tier slug to filter by.
     * @return array<int, array<string, mixed>> Active pool rows.
     */
    public function get_active_pool_by_type(string $user_type): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaking_pool';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_type = %s AND is_active = 1",
                $user_type
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    // =========================================================================
    // MATCH METHODS (wp_matches)
    // =========================================================================

    /**
     * Find a single match record by its primary key.
     *
     * @param int $match_id Match row ID.
     * @return array<string, mixed>|null Match row or null if not found.
     */
    public function find_match_by_id(int $match_id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matches';
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $match_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Alias for find_match_by_id.
     *
     * @param int $match_id Match row ID.
     * @return array<string, mixed>|null Match row or null if not found.
     */
    public function get_match(int $match_id): ?array
    {
        return $this->find_match_by_id($match_id);
    }

    /**
     * Get all approved or matched records for a user, enriched with candidate data.
     *
     * @param int $user_id WordPress user ID.
     * @return array<int, array<string, mixed>> Enriched match rows.
     */
    public function find_approved_matches_for_user(int $user_id): array
    {
        global $wpdb;
        $table      = $wpdb->prefix . 'matches';
        $pool_table = $wpdb->prefix . 'matchmaking_pool';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND status IN ('approved', 'matched')
                 ORDER BY created_at DESC",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $expiry_days = $this->get_match_expiry_days();
        $out = [];
        foreach ((array) $rows as $row) {
            $is_user_one    = ((int) $row['user_one_id'] === $user_id);
            $other_id       = $is_user_one ? (int) $row['user_two_id'] : (int) $row['user_one_id'];
            $my_response    = strtolower((string) ($is_user_one ? ($row['user_one_response'] ?? 'pending') : ($row['user_two_response'] ?? 'pending')));
            $their_response = strtolower((string) ($is_user_one ? ($row['user_two_response'] ?? 'pending') : ($row['user_one_response'] ?? 'pending')));

            $other_user = get_userdata($other_id);
            $other_pool = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $other_id),
                ARRAY_A
            );

            // Days remaining to respond
            $days_remaining = 0;
            if ($my_response === 'pending' && $row['status'] === 'approved') {
                $ref_date       = !empty($row['approved_at']) ? $row['approved_at'] : $row['updated_at'];
                $deadline       = strtotime($ref_date . ' +' . $expiry_days . ' days');
                $days_remaining = max(0, (int) ceil(($deadline - current_time('timestamp')) / DAY_IN_SECONDS));
            }

            $other_meta = [
                'phone_number'         => (string) get_user_meta($other_id, 'phone_number', true),
                'user_social_links'    => (string) get_user_meta($other_id, 'user_social_links', true),
                'user_marital_status'  => (string) get_user_meta($other_id, 'user_marital_status', true),
                'user_education'       => (string) get_user_meta($other_id, 'user_education', true),
                'user_photo1'          => (string) get_user_meta($other_id, 'user_photo1', true),
                'pref_additional_info' => (string) get_user_meta($other_id, 'pref_additional_info', true),
            ];

            $out[] = [
                'match_id'             => (int) $row['id'],
                'candidate_id'         => $other_id,
                'name'                 => $other_user ? $other_user->display_name : 'Candidate #' . $other_id,
                'email'                => $other_user ? $other_user->user_email : '',
                'phone_number'         => $other_meta['phone_number'],
                'social_links'         => $other_meta['user_social_links'],
                'marital_status'       => $other_meta['user_marital_status'],
                'education'            => $other_meta['user_education'],
                'photo'                => $other_meta['user_photo1'],
                'pref_additional_info' => $other_meta['pref_additional_info'],
                'age'                  => $this->calc_age($other_pool['birth_date'] ?? ''),
                'location'             => $other_pool['location'] ?? '—',
                'origin'               => $other_pool['origin'] ?? '—',
                'religion'             => $other_pool['religion'] ?? '—',
                'modesty'              => $other_pool['modesty'] ?? '—',
                'job'                  => $other_pool['job'] ?? '—',
                'languages'            => $other_pool['languages'] ?? '—',
                'height_formatted'     => $this->cm_to_feet((int) ($other_pool['height_cm'] ?? 0)),
                'status'               => $row['status'],
                'score'                => $row['score'],
                'contact_revealed'     => (int) $row['contact_revealed'],
                'my_response'          => $my_response,
                'their_response'       => $their_response,
                'days_remaining'       => $days_remaining,
            ];
        }

        return $out;
    }

    /**
     * Get all match records involving a user (for admin view), with candidate pivot columns.
     *
     * @param int $user_id WordPress user ID.
     * @return array<int, object> Match rows with candidate_id, my_response, their_response.
     */
    public function find_all_matches_for_user(int $user_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matches';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*,
                    CASE WHEN m.user_one_id = %d THEN m.user_two_id   ELSE m.user_one_id    END AS candidate_id,
                    CASE WHEN m.user_one_id = %d THEN m.user_one_response ELSE m.user_two_response END AS my_response,
                    CASE WHEN m.user_one_id = %d THEN m.user_two_response ELSE m.user_one_response END AS their_response
                 FROM {$table} m
                 WHERE m.user_one_id = %d OR m.user_two_id = %d
                 ORDER BY (m.status = 'pending_review') DESC, m.score DESC, m.created_at DESC",
                $user_id, $user_id, $user_id, $user_id, $user_id
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Search all matches globally (for the admin matches list page).
     *
     * @param array<string, string> $filters Assoc of filter key => value (search, status).
     * @return array<int, object> Match rows with joined user and pool data.
     */
    public function search_matches(array $filters = []): array
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';

        $where = ['1=1'];
        $args  = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'm.status = %s';
            $args[]  = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(u1.display_name LIKE %s OR u1.user_email LIKE %s OR u2.display_name LIKE %s OR u2.user_email LIKE %s)';
            $wc      = '%' . $wpdb->esc_like($filters['search']) . '%';
            $args[]  = $wc;
            $args[]  = $wc;
            $args[]  = $wc;
            $args[]  = $wc;
        }

        $where_sql = implode(' AND ', $where);

        $query = "
            SELECT m.*,
                u1.display_name AS u1_name, u1.user_email AS u1_email,
                u2.display_name AS u2_name, u2.user_email AS u2_email,
                p1.user_type AS u1_type, p1.gender AS u1_gender, p1.location AS u1_location, p1.birth_date AS u1_birth,
                p2.user_type AS u2_type, p2.gender AS u2_gender, p2.location AS u2_location, p2.birth_date AS u2_birth
            FROM {$matches_table} m
            LEFT JOIN {$wpdb->users} u1 ON m.user_one_id = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON m.user_two_id = u2.ID
            LEFT JOIN {$pool_table} p1 ON m.user_one_id = p1.user_id
            LEFT JOIN {$pool_table} p2 ON m.user_two_id = p2.user_id
            WHERE {$where_sql}
            ORDER BY (m.status = 'pending_review') DESC, m.created_at DESC
        ";

        $results = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($query, ...$args), ARRAY_A)
            : $wpdb->get_results($query, ARRAY_A);

        return $results ?: [];
    }

    /**
     * Create a new match record (INSERT IGNORE to prevent duplicates).
     *
     * Enforces canonical pair ordering: user_one_id = min, user_two_id = max.
     *
     * @param int    $user_a         First user ID.
     * @param int    $user_b         Second user ID.
     * @param int    $initiator_id   The user who triggered the match job.
     * @param string $status         Initial match status ('pending_review').
     * @param string $match_source   e.g. 'algorithm', 'admin_manual'.
     * @param int    $score          Flexible score (0–6).
     * @return int|false Insert ID on success, false on failure or duplicate.
     */
    public function create_match(
        int    $user_a,
        int    $user_b,
        int    $initiator_id,
        string $status = 'pending_review',
        string $match_source = 'auto',
        int    $score = 0
    ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'matches';

        // Enforce canonical ordering
        $u1 = min($user_a, $user_b);
        $u2 = max($user_a, $user_b);

        // Check for existing pair first to avoid duplicate errors
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_one_id = %d AND user_two_id = %d LIMIT 1",
                $u1, $u2
            )
        );
        if (!empty($existing)) {
            return false;
        }

        $result = $wpdb->insert(
            $table,
            [
                'user_one_id'       => $u1,
                'user_two_id'       => $u2,
                'initiator_user_id' => $initiator_id,
                'status'            => $status,
                'user_one_response' => 'pending',
                'user_two_response' => 'pending',
                'match_source'      => $match_source,
                'score'             => $score,
                'contact_revealed'  => 0,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d']
        );

        if ($result === false) {
            error_log('[Matchmaker] create_match failed: ' . $wpdb->last_error);
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a user's response on a match record and handle status transitions.
     *
     * @param int    $match_id   Match record ID.
     * @param int    $user_id    The responding user's ID.
     * @param string $action     'accept' or 'decline'.
     * @return array<string, mixed> Result with updated fields and next_step.
     */
    public function update_match_response(int $match_id, int $user_id, string $action): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matches';

        $match = $this->find_match_by_id($match_id);
        if (!$match) {
            return ['success' => false, 'message' => 'Match not found.'];
        }

        // Verify user is a participant
        if ((int) $match['user_one_id'] !== $user_id && (int) $match['user_two_id'] !== $user_id) {
            return ['success' => false, 'message' => 'Unauthorized.'];
        }

        $is_user_one    = ((int) $match['user_one_id'] === $user_id);
        $new_val        = ($action === 'accept') ? 'accepted' : 'rejected';
        $other_response = $is_user_one ? $match['user_two_response'] : $match['user_one_response'];

        $update_data = $is_user_one ? ['user_one_response' => $new_val] : ['user_two_response' => $new_val];
        $update_data['updated_at'] = current_time('mysql');

        if ($action === 'decline') {
            $update_data['status'] = 'rejected';
        } elseif ($action === 'accept' && $other_response === 'accepted') {
            $update_data['status']           = 'matched';
            $update_data['contact_revealed'] = 1;
        }

        $wpdb->update($table, $update_data, ['id' => $match_id]);

        if ($action === 'decline') {
            \Matchmaker\Service\NotificationService::instance()->send_match_expired_admin_email($match_id, 'declined_by_user', $user_id);
        }

        $next_step = 3;
        if ($action === 'decline') {
            $next_step = 1;
        } elseif ($action === 'accept' && $other_response === 'accepted') {
            $next_step = 5;
        }

        return [
            'success'        => true,
            'next_step'      => $next_step,
            'is_mutual'      => ($action === 'accept' && $other_response === 'accepted'),
            'other_user_id'  => $is_user_one ? (int) $match['user_two_id'] : (int) $match['user_one_id'],
            'match'          => $match,
        ];
    }

    /**
     * Check if user currently has an active approved match awaiting response.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public function has_active_approved_match(int $user_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matches';
        $cnt   = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE (user_one_id = %d OR user_two_id = %d) AND status = 'approved'",
            $user_id,
            $user_id
        ));
        return $cnt > 0;
    }

    /**
     * Mark a match as expired and trigger admin notification.
     *
     * @param int      $match_id          Match row ID.
     * @param string   $reason            Reason for expiry.
     * @param int|null $declining_user_id Optional user ID.
     * @return bool
     */
    public function expire_match(int $match_id, string $reason, ?int $declining_user_id = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matches';

        $updated = $wpdb->update(
            $table,
            [
                'status'     => 'expired',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $match_id]
        );

        if ($updated !== false) {
            \Matchmaker\Service\NotificationService::instance()->send_match_expired_admin_email($match_id, $reason, $declining_user_id);
            return true;
        }

        return false;
    }

    /**
     * Check for approved matches that have not been responded to for the configured expiry duration, and expire them.
     *
     * @return int Number of matches expired.
     */
    public function check_7day_match_expirations(): int
    {
        global $wpdb;
        $table       = $wpdb->prefix . 'matches';
        $expiry_days = $this->get_match_expiry_days();

        $expired_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = 'approved' AND (COALESCE(approved_at, updated_at) < DATE_SUB(NOW(), INTERVAL %d DAY) OR updated_at < DATE_SUB(NOW(), INTERVAL %d DAY))",
                $expiry_days,
                $expiry_days
            ),
            ARRAY_A
        );

        $count = 0;
        foreach ((array) $expired_rows as $row) {
            $match_id = (int) $row['id'];
            $this->expire_match($match_id, $expiry_days . '_day_idle_timeout');
            $count++;
        }

        return $count;
    }

    /**
     * Approve a match (admin action).
     *
     * Validates quota and tier before approving. Returns true on success.
     *
     * @param int $match_id   Match record ID.
     * @param int $admin_id   The approving admin user ID.
     * @return array<string, mixed> Result with success flag and message.
     */
    public function approve_match(int $match_id, int $admin_id): array
    {
        global $wpdb;
        $table      = $wpdb->prefix . 'matches';
        $pool_table = $wpdb->prefix . 'matchmaking_pool';

        $match = $this->find_match_by_id($match_id);
        if (!$match) {
            return ['success' => false, 'message' => __('Match record not found.', 'matchmaker')];
        }

        // Tier gate: free/event users cannot have approved matches
        $u1_type = (string) $wpdb->get_var(
            $wpdb->prepare("SELECT user_type FROM {$pool_table} WHERE user_id = %d", $match['user_one_id'])
        );
        $u2_type = (string) $wpdb->get_var(
            $wpdb->prepare("SELECT user_type FROM {$pool_table} WHERE user_id = %d", $match['user_two_id'])
        );

        if (in_array($u1_type, ['free', 'event'], true) || in_array($u2_type, ['free', 'event'], true)) {
            return ['success' => false, 'message' => __('Approval blocked: Matches involving a Free or Event tier user are informational only and cannot be approved.', 'matchmaker')];
        }

        // Quota gate: max matches per cycle per initiator (resets on new PMPro cycle month)
        $initiator_id  = (int) $match['initiator_user_id'];
        $current_quota = $this->maybe_reset_monthly_quota($initiator_id);
        $max_quota     = $this->get_max_cycle_matches();

        if ($current_quota >= $max_quota) {
            return ['success' => false, 'message' => sprintf(__('Approval blocked: This user has already used their %d-match monthly quota.', 'matchmaker'), $max_quota)];
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'      => 'approved',
                'approved_by' => $admin_id,
                'approved_at' => current_time('mysql'),
            ],
            ['id' => $match_id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return ['success' => false, 'message' => __('Database error. Please try again.', 'matchmaker')];
        }

        $this->increment_quota($initiator_id);

        return [
            'success'       => true,
            'quota_used'    => $current_quota + 1,
            'match_id'      => $match_id,
            'u1_id'         => (int) $match['user_one_id'],
            'u2_id'         => (int) $match['user_two_id'],
        ];
    }

    /**
     * Reject a match (admin action).
     *
     * @param int $match_id Match record ID.
     * @return bool True on success.
     */
    public function reject_match(int $match_id): bool
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'matches';
        $result = $wpdb->update(
            $table,
            ['status' => 'admin_rejected'],
            ['id'     => $match_id],
            ['%s'],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Get match statistics for a user's portal dashboard.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string, int> Stats: received_this_term, days_remaining, total_accepted.
     */
    public function get_match_stats(int $user_id): array
    {
        global $wpdb;
        $table       = $wpdb->prefix . 'matches';
        $month_start = gmdate('Y-m-01 00:00:00');

        // 1. Count of matches received this month (all statuses: approved, matched, rejected, expired)
        $received_this_term = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND created_at >= %s",
                $user_id, $user_id, $month_start
            )
        );

        // 2. Remaining days to respond to current active approved match
        $active_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_one_id, user_two_id, user_one_response, user_two_response, approved_at, updated_at, created_at
                 FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND status = 'approved'
                 ORDER BY created_at DESC LIMIT 1",
                $user_id, $user_id
            ),
            ARRAY_A
        );

        $days_remaining = 0;
        if ($active_row) {
            $is_u1   = ((int) $active_row['user_one_id'] === $user_id);
            $my_resp = $is_u1 ? $active_row['user_one_response'] : $active_row['user_two_response'];
            if ($my_resp === 'pending') {
                $expiry_days    = $this->get_match_expiry_days();
                $ref_date       = !empty($active_row['approved_at']) ? $active_row['approved_at'] : $active_row['updated_at'];
                $deadline       = strtotime($ref_date . ' +' . $expiry_days . ' days');
                $days_remaining = max(0, (int) ceil(($deadline - current_time('timestamp')) / DAY_IN_SECONDS));
            }
        }

        // 3. Count of total matches accepted by the user in the current month
        $total_accepted = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE ((user_one_id = %d AND user_one_response IN ('accepted', 'accept')) OR (user_two_id = %d AND user_two_response IN ('accepted', 'accept')))
                   AND updated_at >= %s",
                $user_id, $user_id, $month_start
            )
        );

        return [
            'received_this_term' => $received_this_term,
            'days_remaining'     => $days_remaining,
            'total_accepted'     => $total_accepted,
        ];
    }

    /**
     * Get the last match run timestamp for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return int|null Unix timestamp or null if never run.
     */
    public function get_last_match_run(int $user_id): ?int
    {
        $val = get_user_meta($user_id, 'mm_last_match_run', true);
        return $val ? (int) $val : null;
    }

    /**
     * Record the current time as the last match run for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return void
     */
    public function set_last_match_run(int $user_id): void
    {
        update_user_meta($user_id, 'mm_last_match_run', current_time('timestamp'));
    }

    // =========================================================================
    // NOTIFICATION METHODS (wp_matchmaker_notifications)
    // =========================================================================

    /**
     * Insert a new notification record into the database.
     *
     * @param int    $user_id  Recipient user ID.
     * @param int    $match_id Related match ID.
     * @param string $type     Notification type slug.
     * @param string $title    Short notification title.
     * @param string $message  Full notification message.
     * @return void
     */
    public function create_notification(int $user_id, int $match_id, string $type, string $title, string $message): void
    {
        if ($user_id <= 0 || $match_id <= 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_notifications';

        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'match_id'   => $match_id,
                'type'       => sanitize_key($type),
                'title'      => sanitize_text_field($title),
                'message'    => sanitize_textarea_field($message),
                'is_read'    => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        $this->flush_unread_transient($user_id);
    }

    /**
     * Count unread notifications for a user (with 15s transient caching).
     *
     * @param int $user_id WordPress user ID.
     * @return int Unread count.
     */
    public function get_unread_count(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $transient_key = "mm_unread_count_{$user_id}";
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_notifications';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );

        set_transient($transient_key, $count, 15);
        return $count;
    }

    /**
     * Mark all unread notifications as read for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return void
     */
    public function mark_notifications_read(int $user_id): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_notifications';

        $wpdb->update(
            $table,
            ['is_read' => 1],
            ['user_id' => $user_id, 'is_read' => 0],
            ['%d'],
            ['%d', '%d']
        );

        $this->flush_unread_transient($user_id);
    }

    /**
     * Flush the cached unread count transient for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return void
     */
    public function flush_unread_transient(int $user_id): void
    {
        if ($user_id > 0) {
            delete_transient("mm_unread_count_{$user_id}");
        }
    }

    /**
     * Check if a user has a mutually accepted match ('matched' status) in the current calendar month.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True if a mutual match exists this month, false otherwise.
     */
    public function has_mutual_match_this_month(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'matches';
        $month_start = gmdate('Y-m-01 00:00:00');

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND status = 'matched'
                   AND updated_at >= %s",
                $user_id,
                $user_id,
                $month_start
            )
        );

        return $count > 0;
    }

    // =========================================================================
    // ENVIRONMENT MODE & TEST RESET
    // =========================================================================

    /**
     * Get the current environment mode ('test' or 'live').
     *
     * @return string
     */
    public function get_environment_mode(): string
    {
        $mode = (string) get_option('mm_environment_mode', 'live');
        return in_array($mode, ['test', 'live'], true) ? $mode : 'live';
    }

    /**
     * Check if the plugin is currently running in test mode.
     *
     * @return bool
     */
    public function is_test_mode(): bool
    {
        return $this->get_environment_mode() === 'test';
    }

    /**
     * Reset matchmaking test data (matches, notifications, logs, cycle counters).
     * Strictly preserves all user profiles in wp_matchmaking_pool and wp_users.
     *
     * @return array{matches_deleted: int, notifications_deleted: int, logs_deleted: int, profiles_preserved: int}
     */
    public function reset_test_matchmaking_data(): array
    {
        global $wpdb;

        $matches_table       = $wpdb->prefix . 'matches';
        $notifications_table = $wpdb->prefix . 'matchmaker_notifications';
        $logs_table          = $wpdb->prefix . 'matchmaker_logs';
        $pool_table          = $wpdb->prefix . 'matchmaking_pool';

        $matches_deleted       = (int) $wpdb->query("TRUNCATE TABLE {$matches_table}");
        $notifications_deleted = (int) $wpdb->query("TRUNCATE TABLE {$notifications_table}");
        $logs_deleted          = (int) $wpdb->query("TRUNCATE TABLE {$logs_table}");

        // If truncate returns false or 0, fallback to DELETE
        if ($matches_deleted === 0) {
            $matches_deleted = (int) $wpdb->query("DELETE FROM {$matches_table}");
        }
        if ($notifications_deleted === 0) {
            $notifications_deleted = (int) $wpdb->query("DELETE FROM {$notifications_table}");
        }
        if ($logs_deleted === 0) {
            $logs_deleted = (int) $wpdb->query("DELETE FROM {$logs_table}");
        }

        // Reset cycle counters and match run timestamps in usermeta
        $usermeta_table = !empty($wpdb->usermeta) ? $wpdb->usermeta : ($wpdb->prefix . 'usermeta');
        $wpdb->query("DELETE FROM {$usermeta_table} WHERE meta_key IN ('cycle_matches_count', 'mm_cycle_matches_count', 'mm_last_match_run')");

        // Preserve count from pool
        $profiles_preserved = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pool_table}");

        // Log this reset event into the fresh logs table
        $this->log_event(
            'match_lifecycle',
            'test_data_reset',
            __('Test Mode Data Reset Executed', 'matchmaker'),
            sprintf(__('Cleared all match pairs, notifications, and logs. Preserved %d candidate profiles.', 'matchmaker'), $profiles_preserved),
            [
                'admin_user_id'      => get_current_user_id(),
                'profiles_preserved' => $profiles_preserved,
                'reset_at'           => current_time('mysql'),
            ],
            null,
            get_current_user_id(),
            null,
            'warning'
        );

        return [
            'matches_deleted'       => $matches_deleted,
            'notifications_deleted' => $notifications_deleted,
            'logs_deleted'          => $logs_deleted,
            'profiles_preserved'    => $profiles_preserved,
        ];
    }

    // =========================================================================
    // STRUCTURED LOGGING CRUD
    // =========================================================================

    /**
     * Log a matchmaking or notification event into wp_matchmaker_logs.
     *
     * @param string      $log_type     Type: 'match_lifecycle', 'match_engine', 'notification', 'email'.
     * @param string      $event_type   Event identifier (e.g. 'match_created', 'admin_approved', 'email_sent').
     * @param string      $title        Human-readable title.
     * @param string|null $message      Description or rendered message.
     * @param array|null  $details      Additional metadata array to be JSON-encoded.
     * @param int|null    $reference_id Match ID or target entity ID.
     * @param int|null    $user_id      Subject user ID.
     * @param string|null $recipient    Recipient email / identifier.
     * @param string      $status       'info', 'success', 'warning', 'error'.
     * @return int Log ID inserted.
     */
    public function log_event(
        string $log_type,
        string $event_type,
        string $title,
        ?string $message = null,
        ?array $details = null,
        ?int $reference_id = null,
        ?int $user_id = null,
        ?string $recipient = null,
        string $status = 'info'
    ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_logs';

        $json_str = null;
        if ($details !== null) {
            $json_str = function_exists('wp_json_encode') ? wp_json_encode($details) : json_encode($details);
        }

        $data = [
            'log_type'     => $log_type,
            'event_type'   => $event_type,
            'title'        => $title,
            'message'      => $message,
            'details_json' => $json_str,
            'reference_id' => $reference_id,
            'user_id'      => $user_id,
            'recipient'    => $recipient,
            'status'       => $status,
            'created_at'   => current_time('mysql'),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'];

        $inserted = $wpdb->insert($table, $data, $format);
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Retrieve paginated logs with optional filtering.
     *
     * @param string      $log_type   Filter by type ('match_lifecycle', 'match_engine', 'notification', 'email' or comma-separated / empty for all).
     * @param int         $limit      Number of rows to fetch.
     * @param int         $offset     Offset for pagination.
     * @param string|null $search     Search term in title, message, recipient.
     * @param string|null $event_type Filter by specific event_type.
     * @return array<int, array<string, mixed>>
     */
    public function get_logs(
        string $log_type = '',
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $event_type = null
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_logs';

        $where = [];
        $args  = [];

        if (!empty($log_type)) {
            $types = array_filter(array_map('trim', explode(',', $log_type)));
            if (!empty($types)) {
                $placeholders = implode(',', array_fill(0, count($types), '%s'));
                $where[] = "log_type IN ({$placeholders})";
                foreach ($types as $t) {
                    $args[] = $t;
                }
            }
        }

        if (!empty($event_type)) {
            $where[] = 'event_type = %s';
            $args[]  = $event_type;
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like(trim($search)) . '%';
            $where[] = '(title LIKE %s OR message LIKE %s OR recipient LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
            $args[]  = $like;
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $args[] = max(1, $limit);
        $args[] = max(0, $offset);

        $prepared = !empty($args) ? $wpdb->prepare($sql, $args) : $sql;
        $results  = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Get count of logs matching criteria.
     *
     * @param string      $log_type   Filter by type.
     * @param string|null $search     Search term.
     * @param string|null $event_type Filter by event type.
     * @return int
     */
    public function get_logs_count(
        string $log_type = '',
        ?string $search = null,
        ?string $event_type = null
    ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_logs';

        $where = [];
        $args  = [];

        if (!empty($log_type)) {
            $types = array_filter(array_map('trim', explode(',', $log_type)));
            if (!empty($types)) {
                $placeholders = implode(',', array_fill(0, count($types), '%s'));
                $where[] = "log_type IN ({$placeholders})";
                foreach ($types as $t) {
                    $args[] = $t;
                }
            }
        }

        if (!empty($event_type)) {
            $where[] = 'event_type = %s';
            $args[]  = $event_type;
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like(trim($search)) . '%';
            $where[] = '(title LIKE %s OR message LIKE %s OR recipient LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
            $args[]  = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $prepared = !empty($args) ? $wpdb->prepare($sql, $args) : $sql;
        return (int) $wpdb->get_var($prepared);
    }

    /**
     * Get a single log record by ID.
     *
     * @param int $log_id Log ID.
     * @return array<string, mixed>|null
     */
    public function get_log_by_id(int $log_id): ?array
    {
        if ($log_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'matchmaker_logs';
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $log_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }
}
