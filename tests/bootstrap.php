<?php
declare(strict_types=1);

// Test bootstrap stubbing WordPress functions and globals.
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('MM_PATH')) {
    define('MM_PATH', dirname(__DIR__) . '/');
}
if (!defined('MM_SRC_PATH')) {
    define('MM_SRC_PATH', dirname(__DIR__) . '/src/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

$GLOBALS['__mm_dbdelta_sql']     = null;
$GLOBALS['__mm_options']         = [];
$GLOBALS['__mm_usermeta']        = [];
$GLOBALS['__mm_users']           = [];
$GLOBALS['__mm_actions']         = [];
$GLOBALS['__mm_filters']         = [];
$GLOBALS['__mm_scheduled_jobs']  = [];

// -----------------------------------------------------------------------------
// Options API Stubs
// -----------------------------------------------------------------------------

function get_option($name, $default = false) {
    return $GLOBALS['__mm_options'][$name] ?? $default;
}

function update_option($name, $value) {
    $GLOBALS['__mm_options'][$name] = $value;
    return true;
}

function delete_option($name) {
    unset($GLOBALS['__mm_options'][$name]);
    return true;
}

// -----------------------------------------------------------------------------
// Transient API Stubs
// -----------------------------------------------------------------------------

function get_transient($transient) {
    return $GLOBALS['__mm_options']['_transient_' . $transient] ?? false;
}

function set_transient($transient, $value, $expiration = 0) {
    $GLOBALS['__mm_options']['_transient_' . $transient] = $value;
    return true;
}

function delete_transient($transient) {
    unset($GLOBALS['__mm_options']['_transient_' . $transient]);
    return true;
}

// -----------------------------------------------------------------------------
// User Meta API Stubs
// -----------------------------------------------------------------------------

function get_user_meta($user_id, $key = '', $single = false) {
    $uid = (int) $user_id;
    if ($key === '') {
        return $GLOBALS['__mm_usermeta'][$uid] ?? [];
    }
    $val = $GLOBALS['__mm_usermeta'][$uid][$key] ?? null;
    if ($single) {
        return $val !== null ? (is_array($val) ? ($val[0] ?? '') : $val) : '';
    }
    return $val !== null ? (is_array($val) ? $val : [$val]) : [];
}

function update_user_meta($user_id, $key, $value) {
    $uid = (int) $user_id;
    if (!isset($GLOBALS['__mm_usermeta'][$uid])) {
        $GLOBALS['__mm_usermeta'][$uid] = [];
    }
    $GLOBALS['__mm_usermeta'][$uid][$key] = $value;
    return true;
}

function delete_user_meta($user_id, $key) {
    $uid = (int) $user_id;
    unset($GLOBALS['__mm_usermeta'][$uid][$key]);
    return true;
}

// -----------------------------------------------------------------------------
// User & Auth Stubs
// -----------------------------------------------------------------------------

class FakeWP_User {
    public int $ID = 0;
    public string $display_name = '';
    public string $user_email = '';
    public array $roles = ['subscriber'];

    public function __construct(int $id = 0, string $name = 'Test User', string $email = 'test@example.com') {
        $this->ID = $id;
        $this->display_name = $name;
        $this->user_email = $email;
    }

    public function exists(): bool {
        return $this->ID > 0;
    }
}

function get_userdata($user_id) {
    $uid = (int) $user_id;
    if (isset($GLOBALS['__mm_users'][$uid])) {
        return $GLOBALS['__mm_users'][$uid];
    }
    if ($uid > 0) {
        return new FakeWP_User($uid, "User #{$uid}", "user{$uid}@example.com");
    }
    return false;
}

function get_current_user_id(): int {
    return 1;
}

function is_user_logged_in(): bool {
    return true;
}

function current_user_can($cap): bool {
    return true;
}

function wp_create_user($username, $password, $email) {
    $id = count($GLOBALS['__mm_users']) + 100;
    $user = new FakeWP_User($id, $username, $email);
    $GLOBALS['__mm_users'][$id] = $user;
    return $id;
}

function wp_update_user($args) {
    $uid = $args['ID'] ?? 0;
    if ($uid && isset($GLOBALS['__mm_users'][$uid])) {
        if (!empty($args['display_name'])) {
            $GLOBALS['__mm_users'][$uid]->display_name = $args['display_name'];
        }
    }
    return $uid;
}

function email_exists($email) {
    foreach ($GLOBALS['__mm_users'] as $u) {
        if ($u->user_email === $email) return $u->ID;
    }
    return false;
}

function username_exists($username) {
    return false;
}

function is_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function wp_signon($credentials = [], $secure_cookie = false) {
    return new FakeWP_User(1, 'Logged In User');
}

function is_ssl() {
    return true;
}

// -----------------------------------------------------------------------------
// Formatting & Sanitization Stubs
// -----------------------------------------------------------------------------

function sanitize_text_field($str) {
    return is_string($str) ? trim(strip_tags($str)) : '';
}

function sanitize_textarea_field($str) {
    return is_string($str) ? trim(strip_tags($str)) : '';
}

function sanitize_email($email) {
    return is_string($email) ? trim($email) : '';
}

function sanitize_key($key) {
    return is_string($key) ? strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key)) : '';
}

function wp_unslash($val) {
    return $val;
}

function wp_kses_post($val) {
    return $val;
}

function wp_json_encode($data, $options = 0, $depth = 512) {
    return json_encode($data, $options, $depth);
}

function wp_send_json_success($data = null, $status_code = null, $options = 0): void {
    $response = ['success' => true];
    if (isset($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response, $options);
    throw new \RuntimeException('wp_send_json_success');
}

function wp_send_json_error($data = null, $status_code = null, $options = 0): void {
    $response = ['success' => false];
    if (isset($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response, $options);
    throw new \RuntimeException('wp_send_json_error');
}

function esc_html($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL) ?: $url;
}

function esc_js($text) {
    return addcslashes((string)$text, "\'\"\n\r\\");
}

function __($text, $domain = 'default') {
    return $text;
}

function _e($text, $domain = 'default') {
    echo $text;
}

function esc_html__($text, $domain = 'default') {
    return esc_html($text);
}

function esc_html_e($text, $domain = 'default') {
    echo esc_html($text);
}

function esc_attr__($text, $domain = 'default') {
    return esc_attr($text);
}

function esc_attr_e($text, $domain = 'default') {
    echo esc_attr($text);
}

function esc_textarea($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

class WP_Post {
    public int $ID = 0;
    public string $post_title = '';
    public string $post_content = '';
    public string $post_excerpt = '';
    public string $post_date = '2026-09-01 12:00:00';
    public function __construct(int $id = 1, string $title = 'Sample Event', string $excerpt = 'Event excerpt details.') {
        $this->ID = $id;
        $this->post_title = $title;
        $this->post_excerpt = $excerpt;
    }
}

class WP_Query {
    public array $posts = [];
    public int $post_count = 0;
    public int $max_num_pages = 1;
    public int $current_post = -1;

    public function __construct(array $args = []) {
        if (isset($GLOBALS['__mm_mock_posts'])) {
            $this->posts = $GLOBALS['__mm_mock_posts'];
        } else {
            $this->posts = [
                new WP_Post(101, 'Arab Zawaj Matrimonial Mixer', 'Join our exclusive networking mixer for Muslim singles.'),
                new WP_Post(102, 'Pre-Marital Harmony Webinar', 'Expert counseling webinar discussing core marital compatibility.')
            ];
        }
        $this->post_count = count($this->posts);
        $per_page = !empty($args['posts_per_page']) ? (int) $args['posts_per_page'] : 6;
        $this->max_num_pages = max(1, (int) ceil($this->post_count / $per_page));
    }

    public function have_posts(): bool {
        return ($this->current_post + 1) < $this->post_count;
    }

    public function the_post(): void {
        $this->current_post++;
        $GLOBALS['post'] = $this->posts[$this->current_post] ?? null;
    }
}

function get_post($post_id = null) {
    if ($post_id instanceof WP_Post) return $post_id;
    if ($post_id && isset($GLOBALS['post']) && $GLOBALS['post']->ID === (int) $post_id) {
        return $GLOBALS['post'];
    }
    return $GLOBALS['post'] ?? new WP_Post((int) ($post_id ?: 1));
}

function setup_postdata($post): bool {
    $GLOBALS['post'] = $post;
    return true;
}

function wp_reset_postdata(): void {
    // Reset global post
}

function get_the_ID(): int {
    return $GLOBALS['post']->ID ?? 0;
}

function the_permalink(): void {
    echo esc_url('https://example.com/event/' . get_the_ID());
}

function get_post_meta(int $post_id, string $key = '', bool $single = false) {
    return $GLOBALS['__mm_postmeta'][$post_id][$key] ?? ($single ? '' : []);
}

function update_post_meta(int $post_id, string $key, $value): bool {
    $GLOBALS['__mm_postmeta'][$post_id][$key] = $value;
    return true;
}

function get_the_title($post = 0): string {
    return $GLOBALS['post']->post_title ?? '';
}

function the_title(): void {
    echo esc_html($GLOBALS['post']->post_title ?? '');
}

function get_the_excerpt(): string {
    return $GLOBALS['post']->post_excerpt ?? '';
}

function the_excerpt(): void {
    echo esc_html(get_the_excerpt());
}

function get_the_date($format = 'M j, Y'): string {
    return 'Sep 3, 2026';
}

function the_title_attribute(array|string $args = ''): void {
    echo esc_attr($GLOBALS['post']->post_title ?? '');
}

function has_post_thumbnail($post = null): bool {
    return !empty($GLOBALS['__mm_mock_post_thumbnail']);
}

function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail'): ?string {
    return $GLOBALS['__mm_mock_post_thumbnail'] ?? null;
}

function wp_get_attachment_image_url(int $attachment_id, $size = 'thumbnail'): ?string {
    return 'https://example.com/uploads/attachment-' . $attachment_id . '.jpg';
}

function the_post_thumbnail($size = 'post-thumbnail', $attr = ''): void {
    echo '<img src="https://example.com/placeholder.jpg" alt="Event">';
}

if (!class_exists('WP_User', false)) {
    class_alias(FakeWP_User::class, 'WP_User');
}

function current_time($type = 'mysql') {
    return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s');
}

function home_url($path = '') {
    return 'https://example.com' . $path;
}

function wpautop($pee, $br = true) {
    return "<p>" . nl2br((string) $pee) . "</p>";
}

$GLOBALS['__mm_sent_mails'] = [];
function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
    $GLOBALS['__mm_sent_mails'][] = [
        'to'          => $to,
        'subject'     => $subject,
        'message'     => $message,
        'headers'     => $headers,
        'attachments' => $attachments,
    ];
    if (isset($GLOBALS['__mm_wp_mail_return'])) {
        return (bool) $GLOBALS['__mm_wp_mail_return'];
    }
    return true;
}

function admin_url($path = '') {
    return 'https://example.com/wp-admin/' . $path;
}

function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}

function get_permalink($page_id) {
    return $page_id > 0 ? "https://example.com/?page_id={$page_id}" : '';
}

function wp_create_nonce($action = -1) {
    return 'test_nonce_' . $action;
}

function wp_verify_nonce($nonce, $action = -1) {
    return !empty($nonce);
}

function wp_trim_words($text, $num_words = 55, $more = null) {
    $words = explode(' ', (string) $text);
    if (count($words) > $num_words) {
        $words = array_slice($words, 0, $num_words);
        return implode(' ', $words) . ($more ?: '...');
    }
    return (string) $text;
}

function wp_logout_url($redirect = '') {
    return 'https://example.com/wp-login.php?action=logout' . ($redirect ? '&redirect_to=' . urlencode((string)$redirect) : '');
}

function wp_get_current_user() {
    $uid = get_current_user_id();
    return get_userdata($uid) ?: new FakeWP_User(0, '', '');
}

function user_can($user, $capability) {
    $u = is_numeric($user) ? get_userdata((int)$user) : $user;
    if ($u instanceof FakeWP_User) {
        return in_array('administrator', (array)$u->roles, true);
    }
    return false;
}

function add_query_arg($key, $val, $url = '') {
    $sep = str_contains($url, '?') ? '&' : '?';
    return $url . $sep . urlencode((string)$key) . '=' . urlencode((string)$val);
}

// -----------------------------------------------------------------------------
// Hooks & Actions API Stubs
// -----------------------------------------------------------------------------

function add_action($hook, $callable = null, $priority = 10, $accepted_args = 1) {
    $GLOBALS['__mm_actions'][$hook][] = $callable;
    return true;
}

function remove_action($hook, $callable = null, $priority = 10) {
    return true;
}

function add_filter($hook, $callable = null, $priority = 10, $accepted_args = 1) {
    $GLOBALS['__mm_filters'][$hook][] = $callable;
    return true;
}

function remove_filter($hook, $callable = null, $priority = 10) {
    return true;
}

function did_action($hook) {
    return 1;
}

function has_action($hook, $callback_to_check = false) {
    if (!isset($GLOBALS['__mm_actions'][$hook])) {
        return false;
    }
    return !empty($GLOBALS['__mm_actions'][$hook]);
}

// -----------------------------------------------------------------------------
// Shortcode API Stubs
// -----------------------------------------------------------------------------

$GLOBALS['__mm_shortcodes'] = [];

function add_shortcode($tag, $callback) {
    $GLOBALS['__mm_shortcodes'][$tag] = $callback;
    return true;
}

function do_shortcode($content) {
    return $content;
}

function shortcode_atts($pairs, $atts, $shortcode = '') {
    $atts = (array) $atts;
    $out  = [];
    foreach ($pairs as $name => $default) {
        if (array_key_exists($name, $atts)) {
            $out[$name] = $atts[$name];
        } else {
            $out[$name] = $default;
        }
    }
    return $out;
}

function has_shortcode($content, $tag) {
    return is_string($content) && str_contains($content, '[' . $tag);
}

function dbDelta($sql) {
    if ($GLOBALS['__mm_dbdelta_sql'] === null) {
        $GLOBALS['__mm_dbdelta_sql'] = $sql;
    } else {
        $GLOBALS['__mm_dbdelta_sql'] .= "\n" . $sql;
    }
}

// -----------------------------------------------------------------------------
// PMPro Stubs
// -----------------------------------------------------------------------------

class FakePMProLevel {
    public int $id;
    public string $name;
    public string $description;

    public function __construct(int $id, string $name, string $description = '') {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
    }
}

function pmpro_getAllLevels($include_hidden = false, $force = false) {
    return [
        new FakePMProLevel(2, 'Free Membership', 'Free limited access'),
        new FakePMProLevel(3, 'Monthly Matchmaking', '$29/mo full access'),
        new FakePMProLevel(4, '1-on-1 VIP Matchmaking', '$99/mo VIP access'),
        new FakePMProLevel(5, 'Elite 1-on-1 VIP', '$199/mo premium access'),
        new FakePMProLevel(6, 'Event Single Pass', 'Event only access'),
    ];
}

function pmpro_getMembershipLevelForUser(int $user_id) {
    if (isset($GLOBALS['__mm_user_pmpro_level'][$user_id])) {
        $lvl_id = $GLOBALS['__mm_user_pmpro_level'][$user_id];
        return new FakePMProLevel($lvl_id, 'Level ' . $lvl_id);
    }
    if (isset($GLOBALS['__mm_pmpro_levels'][$user_id])) {
        return $GLOBALS['__mm_pmpro_levels'][$user_id];
    }
    $user_type = get_user_meta($user_id, 'user_type', true);
    if ($user_type === 'monthly') return new FakePMProLevel(3, 'Monthly Matchmaking');
    if ($user_type === 'one_on_one') return new FakePMProLevel(4, '1-on-1 VIP Matchmaking');
    if ($user_type === 'event') return new FakePMProLevel(6, 'Event Single Pass');
    return null;
}

function pmpro_changeMembershipLevel($level_id, $user_id) {
    $GLOBALS['__mm_user_pmpro_level'][(int)$user_id] = (int)$level_id;
    if (!isset($GLOBALS['__mm_user_pmpro_levels'][(int)$user_id])) {
        $GLOBALS['__mm_user_pmpro_levels'][(int)$user_id] = [];
    }
    $GLOBALS['__mm_user_pmpro_levels'][(int)$user_id][] = new FakePMProLevel((int)$level_id, 'Level ' . $level_id);
    return true;
}

function pmpro_getMembershipLevelsForUser(int $user_id) {
    if (isset($GLOBALS['__mm_user_pmpro_levels'][$user_id])) {
        return $GLOBALS['__mm_user_pmpro_levels'][$user_id];
    }
    if (isset($GLOBALS['__mm_user_pmpro_level'][$user_id])) {
        $lvl_id = $GLOBALS['__mm_user_pmpro_level'][$user_id];
        return [new FakePMProLevel($lvl_id, 'Level ' . $lvl_id)];
    }
    $single = pmpro_getMembershipLevelForUser($user_id);
    return $single ? [$single] : [];
}

function pmpro_cancelMembershipLevel(int $level_id, int $user_id): bool {
    if (isset($GLOBALS['__mm_user_pmpro_levels'][$user_id])) {
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = array_values(array_filter(
            $GLOBALS['__mm_user_pmpro_levels'][$user_id],
            fn($lvl) => (is_object($lvl) ? $lvl->id : $lvl) != $level_id
        ));
    }
    if (isset($GLOBALS['__mm_user_pmpro_level'][$user_id]) && $GLOBALS['__mm_user_pmpro_level'][$user_id] === $level_id) {
        unset($GLOBALS['__mm_user_pmpro_level'][$user_id]);
    }
    return true;
}

function pmpro_url($page = '') {
    return "https://example.com/{$page}/";
}

// -----------------------------------------------------------------------------
// Action Scheduler Stubs
// -----------------------------------------------------------------------------

function as_schedule_single_action($timestamp, $hook, $args = [], $group = '') {
    $GLOBALS['__mm_scheduled_jobs'][] = ['hook' => $hook, 'args' => $args, 'group' => $group];
    return 1;
}

function as_enqueue_async_action($hook, $args = [], $group = '') {
    $GLOBALS['__mm_scheduled_jobs'][] = ['hook' => $hook, 'args' => $args, 'group' => $group];
    return 1;
}

function as_has_scheduled_action($hook) {
    return false;
}

function as_schedule_recurring_action($timestamp, $interval, $hook, $args = [], $group = '') {
    return 1;
}

function as_unschedule_all_actions($hook, $args = [], $group = '') {
    return 1;
}

function mm_enqueue_user_matching_job(int $user_id, string $trigger = 'auto'): void {
    $GLOBALS['__mm_scheduled_jobs'][] = [
        'hook' => 'mm_run_async_matching_job',
        'args' => [$user_id, $trigger],
        'group' => 'matchmaker'
    ];
}

// -----------------------------------------------------------------------------
// Fake wpdb Stub
// -----------------------------------------------------------------------------

class Fakewpdb {
    public string $prefix = 'wp_';
    public string $usermeta = 'wp_usermeta';
    public string $users = 'wp_users';
    public int $insert_id = 1;
    public array $queries = [];
    public array $mock_results = [];
    public array $mock_rows = [];
    public array $mock_cols = [];
    public array $mock_vars = [];

    public function esc_like(string $text): string {
        return addcslashes($text, '_%\\');
    }

    public function get_charset_collate(): string {
        return 'DEFAULT CHARSET=utf8mb4';
    }

    public function prepare(string $query, ...$args): string {
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
            $query = preg_replace('/%[d|s|f]/', (string)$val, $query, 1);
        }
        return $query;
    }

    public function query(string $query): bool {
        $this->queries[] = $query;
        return true;
    }

    public function get_results(string $query, $output = OBJECT): array {
        $this->queries[] = $query;
        return $this->mock_results[$query] ?? [];
    }

    public function get_row(string $query, $output = OBJECT): ?array {
        $this->queries[] = $query;
        return $this->mock_rows[$query] ?? null;
    }

    public function get_col(string $query): array {
        $this->queries[] = $query;
        return $this->mock_cols[$query] ?? [];
    }

    public function get_var(string $query) {
        $this->queries[] = $query;
        return $this->mock_vars[$query] ?? null;
    }

    public function update($table, $data, $where, $format = null, $where_format = null): int {
        $this->queries[] = "UPDATE {$table}";
        return 1;
    }

    public function insert($table, $data, $format = null): int {
        $this->queries[] = "INSERT INTO {$table}";
        return 1;
    }

    public function replace($table, $data, $format = null): int {
        $this->queries[] = "REPLACE INTO {$table}";
        return 1;
    }
}

$GLOBALS['wpdb'] = new Fakewpdb();

// -----------------------------------------------------------------------------
// PSR-4 Autoloader
// -----------------------------------------------------------------------------

spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'Matchmaker\\') !== 0) {
        return;
    }

    $relative = substr($class, strlen('Matchmaker\\'));
    $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    $src_file = MM_SRC_PATH . $relative_path;
    if (file_exists($src_file)) {
        require_once $src_file;
        return;
    }

    $legacy_parts     = explode('\\', $relative);
    $legacy_classname = end($legacy_parts);
    $legacy_file      = MM_PATH . 'includes/' . $legacy_classname . '.php';
    if (file_exists($legacy_file)) {
        require_once $legacy_file;
    }
});

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
