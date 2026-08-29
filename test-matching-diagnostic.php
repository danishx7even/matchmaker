<?php
declare(strict_types=1);
/**
 * Quick diagnostic endpoint: append to any admin page URL:
 *   ?mm_run_diagnostic=1
 *
 * Or visit: /wp-admin/admin.php?page=matchmaking-pool&mm_run_diagnostic=1
 */
add_action('admin_init', function () {
    if (empty($_GET['mm_run_diagnostic'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    header('Content-Type: text/plain; charset=utf-8');

    global $wpdb;
    $pool_table    = $wpdb->prefix . 'matchmaking_pool';
    $matches_table = $wpdb->prefix . 'matches';

    echo "=== MATCHMAKER DIAGNOSTIC ===\n\n";

    $pool_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pool_table}");
    $active_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pool_table} WHERE is_active = 1");
    echo "Pool entries: {$pool_count} (active: {$active_count})\n\n";

    $users = $wpdb->get_results(
        "SELECT user_id, gender, pref_gender, birth_date, preferred_age_min, preferred_age_max,
                location, pref_location, religion, pref_religion, modesty, pref_modesty,
                user_type, is_active
         FROM {$pool_table} ORDER BY user_id", ARRAY_A
    );

    echo "--- Pool Users ---\n";
    foreach ($users as $u) {
        $age = 'N/A';
        if (!empty($u['birth_date']) && $u['birth_date'] !== '0000-00-00') {
            try { $age = (string)(int)(new DateTime())->diff(new DateTime($u['birth_date']))->y; } catch(Throwable $e) { $age = 'ERR'; }
        }
        echo sprintf(
            "  ID:%-5d %-7s→%-7s Age:%-3s(%d-%d) Loc:%-15s Type:%-10s Active:%s\n",
            $u['user_id'], $u['gender'], $u['pref_gender'], $age,
            $u['preferred_age_min'], $u['preferred_age_max'],
            $u['location'], $u['user_type'], $u['is_active']
        );
    }

    // Existing matches
    $match_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
    echo "\nExisting matches: {$match_count}\n";
    if ($match_count > 0) {
        $matches = $wpdb->get_results(
            "SELECT id, user_one_id, user_two_id, status, match_source, score
             FROM {$matches_table} ORDER BY id LIMIT 30", ARRAY_A
        );
        foreach ($matches as $m) {
            echo sprintf("  #%-3d U%-5d↔U%-5d Status:%-15s Src:%-6s Score:%d\n",
                $m['id'], $m['user_one_id'], $m['user_two_id'], $m['status'], $m['match_source'], (int)$m['score']);
        }
    }

    // Test for first male user
    echo "\n\n--- Gate-by-gate test for FIRST MALE user ---\n";
    $test_user = $wpdb->get_row("SELECT * FROM {$pool_table} WHERE gender = 'male' AND is_active = 1 LIMIT 1", ARRAY_A);
    if (!$test_user) {
        echo "No active male user in pool.\n";
        exit;
    }

    $uid = (int) $test_user['user_id'];
    $user_gender   = strtolower(trim((string)($test_user['gender'] ?? '')));
    $pref_gender   = strtolower(trim((string)($test_user['pref_gender'] ?? '')));
    $user_location = trim((string)($test_user['location'] ?? ''));
    $pref_location = trim((string)($test_user['pref_location'] ?? ''));
    $user_religion = trim((string)($test_user['religion'] ?? ''));
    $pref_religion = trim((string)($test_user['pref_religion'] ?? ''));
    $user_modesty  = trim((string)($test_user['modesty'] ?? ''));
    $pref_modesty  = trim((string)($test_user['pref_modesty'] ?? ''));
    $user_age_min  = (int)($test_user['preferred_age_min'] ?? 18);
    $user_age_max  = (int)($test_user['preferred_age_max'] ?? 99);

    $user_age = 28;
    if (!empty($test_user['birth_date']) && $test_user['birth_date'] !== '0000-00-00') {
        try { $user_age = (int)(new DateTime())->diff(new DateTime($test_user['birth_date']))->y; } catch(Throwable $e) {}
    }

    echo "User #{$uid}: gender={$user_gender} pref={$pref_gender} age={$user_age} loc={$user_location} rel={$user_religion} mod={$user_modesty}\n\n";

    $tcg = ($pref_gender !== '' && $pref_gender !== 'any') ? $pref_gender : '';
    $tug = ($user_gender !== '' && $user_gender !== 'any') ? $user_gender : '';

    // Gate 1
    $g1 = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pool_table} WHERE user_id != %d AND (is_active = 1 OR is_active IS NULL)", $uid));
    echo "G1 all-others-active: {$g1}\n";

    // Gate 2: gender
    $g2 = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$pool_table} c WHERE c.user_id != %d AND (c.is_active = 1 OR c.is_active IS NULL)
         AND (%s = '' OR LOWER(TRIM(c.gender)) = %s)
         AND (c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = 'any' OR %s = '' OR LOWER(TRIM(c.pref_gender)) = %s)",
        $uid, $tcg, $tcg, $tug, $tug
    ));
    echo "G2 +gender: {$g2}" . ($g2 < $g1 ? " (dropped ".($g1-$g2).")" : "") . "\n";

    // Gate 3: age
    $g3 = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$pool_table} c WHERE c.user_id != %d AND (c.is_active = 1 OR c.is_active IS NULL)
         AND (%s = '' OR LOWER(TRIM(c.gender)) = %s)
         AND (c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = 'any' OR %s = '' OR LOWER(TRIM(c.pref_gender)) = %s)
         AND (c.preferred_age_min IS NULL OR c.preferred_age_min <= 0 OR %d >= c.preferred_age_min)
         AND (c.preferred_age_max IS NULL OR c.preferred_age_max <= 0 OR %d <= c.preferred_age_max)
         AND (c.birth_date IS NULL OR c.birth_date = '0000-00-00' OR TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d)",
        $uid, $tcg, $tcg, $tug, $tug, $user_age, $user_age, $user_age_min, $user_age_max
    ));
    echo "G3 +age: {$g3}" . ($g3 < $g2 ? " (dropped ".($g2-$g3).")" : "") . "\n";

    // Gate 4: location
    $g4 = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$pool_table} c WHERE c.user_id != %d AND (c.is_active = 1 OR c.is_active IS NULL)
         AND (%s = '' OR LOWER(TRIM(c.gender)) = %s)
         AND (c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = 'any' OR %s = '' OR LOWER(TRIM(c.pref_gender)) = %s)
         AND (c.preferred_age_min IS NULL OR c.preferred_age_min <= 0 OR %d >= c.preferred_age_min)
         AND (c.preferred_age_max IS NULL OR c.preferred_age_max <= 0 OR %d <= c.preferred_age_max)
         AND (c.birth_date IS NULL OR c.birth_date = '0000-00-00' OR TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d)
         AND (c.pref_location IS NULL OR c.pref_location = '' OR LOWER(TRIM(c.pref_location)) = 'any' OR %s = '' OR FIND_IN_SET(%s, REPLACE(c.pref_location, ', ', ',')) > 0 OR LOWER(c.pref_location) LIKE CONCAT('%%', %s, '%%'))
         AND (%s = '' OR LOWER(%s) = 'any' OR c.location IS NULL OR c.location = '' OR FIND_IN_SET(c.location, REPLACE(%s, ', ', ',')) > 0 OR %s LIKE CONCAT('%%', c.location, '%%'))",
        $uid, $tcg, $tcg, $tug, $tug, $user_age, $user_age, $user_age_min, $user_age_max,
        $user_location, $user_location, strtolower($user_location),
        $pref_location, $pref_location, $pref_location, strtolower($pref_location)
    ));
    echo "G4 +location: {$g4}" . ($g4 < $g3 ? " (dropped ".($g3-$g4).")" : "") . "\n";

    // Gate 5: religion
    $g5 = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$pool_table} c WHERE c.user_id != %d AND (c.is_active = 1 OR c.is_active IS NULL)
         AND (%s = '' OR LOWER(TRIM(c.gender)) = %s)
         AND (c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = 'any' OR %s = '' OR LOWER(TRIM(c.pref_gender)) = %s)
         AND (c.preferred_age_min IS NULL OR c.preferred_age_min <= 0 OR %d >= c.preferred_age_min)
         AND (c.preferred_age_max IS NULL OR c.preferred_age_max <= 0 OR %d <= c.preferred_age_max)
         AND (c.birth_date IS NULL OR c.birth_date = '0000-00-00' OR TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d)
         AND (c.pref_location IS NULL OR c.pref_location = '' OR LOWER(TRIM(c.pref_location)) = 'any' OR %s = '' OR FIND_IN_SET(%s, REPLACE(c.pref_location, ', ', ',')) > 0 OR LOWER(c.pref_location) LIKE CONCAT('%%', %s, '%%'))
         AND (%s = '' OR LOWER(%s) = 'any' OR c.location IS NULL OR c.location = '' OR FIND_IN_SET(c.location, REPLACE(%s, ', ', ',')) > 0 OR %s LIKE CONCAT('%%', c.location, '%%'))
         AND (c.pref_religion IS NULL OR c.pref_religion = '' OR LOWER(TRIM(c.pref_religion)) = 'any' OR %s = '' OR FIND_IN_SET(%s, REPLACE(c.pref_religion, ', ', ',')) > 0)
         AND (%s = '' OR c.religion IS NULL OR c.religion = '' OR FIND_IN_SET(c.religion, REPLACE(%s, ', ', ',')) > 0)",
        $uid, $tcg, $tcg, $tug, $tug, $user_age, $user_age, $user_age_min, $user_age_max,
        $user_location, $user_location, strtolower($user_location),
        $pref_location, $pref_location, $pref_location, strtolower($pref_location),
        $user_religion, $user_religion,
        $pref_religion, $pref_religion
    ));
    echo "G5 +religion: {$g5}" . ($g5 < $g4 ? " (dropped ".($g4-$g5).")" : "") . "\n";

    // Gate 6: modesty
    $g6 = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$pool_table} c WHERE c.user_id != %d AND (c.is_active = 1 OR c.is_active IS NULL)
         AND (%s = '' OR LOWER(TRIM(c.gender)) = %s)
         AND (c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = 'any' OR %s = '' OR LOWER(TRIM(c.pref_gender)) = %s)
         AND (c.preferred_age_min IS NULL OR c.preferred_age_min <= 0 OR %d >= c.preferred_age_min)
         AND (c.preferred_age_max IS NULL OR c.preferred_age_max <= 0 OR %d <= c.preferred_age_max)
         AND (c.birth_date IS NULL OR c.birth_date = '0000-00-00' OR TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d)
         AND (c.pref_location IS NULL OR c.pref_location = '' OR LOWER(TRIM(c.pref_location)) = 'any' OR %s = '' OR FIND_IN_SET(%s, REPLACE(c.pref_location, ', ', ',')) > 0 OR LOWER(c.pref_location) LIKE CONCAT('%%', %s, '%%'))
         AND (%s = '' OR LOWER(%s) = 'any' OR c.location IS NULL OR c.location = '' OR FIND_IN_SET(c.location, REPLACE(%s, ', ', ',')) > 0 OR %s LIKE CONCAT('%%', c.location, '%%'))
         AND (c.pref_religion IS NULL OR c.pref_religion = '' OR LOWER(TRIM(c.pref_religion)) = 'any' OR %s = '' OR FIND_IN_SET(%s, REPLACE(c.pref_religion, ', ', ',')) > 0)
         AND (%s = '' OR c.religion IS NULL OR c.religion = '' OR FIND_IN_SET(c.religion, REPLACE(%s, ', ', ',')) > 0)
         AND (c.pref_modesty IS NULL OR c.pref_modesty = '' OR LOWER(TRIM(c.pref_modesty)) = 'any' OR %s = '' OR FIND_IN_SET(%s, REPLACE(c.pref_modesty, ', ', ',')) > 0)
         AND (%s = '' OR c.modesty IS NULL OR c.modesty = '' OR FIND_IN_SET(c.modesty, REPLACE(%s, ', ', ',')) > 0)",
        $uid, $tcg, $tcg, $tug, $tug, $user_age, $user_age, $user_age_min, $user_age_max,
        $user_location, $user_location, strtolower($user_location),
        $pref_location, $pref_location, $pref_location, strtolower($pref_location),
        $user_religion, $user_religion,
        $pref_religion, $pref_religion,
        $user_modesty, $user_modesty,
        $pref_modesty, $pref_modesty
    ));
    echo "G6 +modesty: {$g6}" . ($g6 < $g5 ? " (dropped ".($g5-$g6).")" : "") . "\n";

    // Test matching engine run
    echo "\n--- Attempting MatchingEngine::run_matching_for_user ---\n";
    try {
        $before_matches = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
        \Matchmaker\Core\MatchingEngine::instance()->run_matching_for_user($uid, 'admin_manual_trigger');
        $after_matches = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
        $new = $after_matches - $before_matches;
        echo "Matches before: {$before_matches}, after: {$after_matches}, NEW: {$new}\n";

        if ($new > 0) {
            $new_matches = $wpdb->get_results($wpdb->prepare(
                "SELECT id, user_one_id, user_two_id, status, score
                 FROM {$matches_table}
                 WHERE (user_one_id = %d OR user_two_id = %d)
                 ORDER BY id DESC LIMIT 10",
                $uid, $uid
            ), ARRAY_A);
            foreach ($new_matches as $nm) {
                echo sprintf("  NEW: #%-3d U%-5d↔U%-5d Status:%s Score:%d\n",
                    $nm['id'], $nm['user_one_id'], $nm['user_two_id'], $nm['status'], (int)$nm['score']);
            }
        }
    } catch (Throwable $e) {
        echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo $e->getTraceAsString() . "\n";
    }

    if (!empty($wpdb->last_error)) {
        echo "\nWPDB ERROR: {$wpdb->last_error}\n";
    }

    echo "\n=== DONE ===\n";
    exit;
});
