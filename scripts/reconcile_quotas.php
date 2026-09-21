<?php
declare(strict_types=1);

/**
 * Matchmaker Plugin — Quota Counter Reconciliation CLI Script
 *
 * Recalculates and synchronizes the monthly cycle match count (cycle_matches_count)
 * for all active monthly members based on actual wp_matches rows in the active cycle.
 *
 * Usage:
 *   1. Via WP-CLI:
 *      wp eval-file wp-content/plugins/matchmaker/scripts/reconcile_quotas.php
 *
 *   2. Via standalone PHP CLI:
 *      php wp-content/plugins/matchmaker/scripts/reconcile_quotas.php
 */

// If running outside WP-CLI, attempt to bootstrap WordPress
if (!defined('ABSPATH')) {
    $possible_wp_loads = [
        dirname(__DIR__, 4) . '/wp-load.php',
        dirname(__DIR__, 3) . '/wp-load.php',
        dirname(__DIR__, 5) . '/wp-load.php',
    ];

    $loaded = false;
    foreach ($possible_wp_loads as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }

    if (!$loaded && !defined('ABSPATH')) {
        fwrite(STDERR, "Error: Could not locate wp-load.php. Please run via WP-CLI:\n");
        fwrite(STDERR, "  wp eval-file wp-content/plugins/matchmaker/scripts/reconcile_quotas.php\n");
        exit(1);
    }
}

// Ensure Matchmaker repository is loaded
if (!class_exists(\Matchmaker\Repository\MatchRepository::class)) {
    require_once dirname(__DIR__) . '/matchmaker.php';
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
$current_month = gmdate('Y-m');

echo "\n========================================================\n";
echo "  Matchmaker: Monthly Quota Counter Reconciliation\n";
echo "  Active Cycle Month: {$current_month}\n";
echo "========================================================\n\n";

$result = $repo->recalculate_all_user_quotas();
$user_quotas = $result['user_quotas'] ?? [];
$updated_count = (int) ($result['updated_count'] ?? 0);

if (empty($user_quotas)) {
    echo "No active monthly tier members found in the candidate pool.\n";
    echo "Done.\n\n";
    exit(0);
}

printf("%-10s | %-25s | %-12s | %-10s\n", "User ID", "Display Name / Login", "Tier", "Quota Used");
echo str_repeat('-', 65) . "\n";

foreach ($user_quotas as $user_id => $quota_count) {
    $user_obj = get_userdata($user_id);
    $name     = $user_obj ? ($user_obj->display_name ?: $user_obj->user_login) : "User #{$user_id}";
    $pool     = $repo->get_user_pool($user_id);
    $tier     = is_array($pool) ? ($pool['user_type'] ?? 'monthly') : 'monthly';

    printf("%-10d | %-25s | %-12s | %-10d\n", $user_id, substr($name, 0, 25), $tier, $quota_count);
}

echo str_repeat('-', 65) . "\n";
echo "Successfully reconciled and updated quota counters for {$updated_count} member(s).\n\n";
