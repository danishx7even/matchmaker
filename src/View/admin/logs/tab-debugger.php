<?php
/**
 * View: Admin Logs – Tab 3 (Candidate Rejection & Flow Debugger)
 *
 * Available variables:
 *   @var int                              $selected_user_id
 *   @var array<int, array<string, mixed>> $all_pool_users
 *   @var array<string, mixed>|null        $target_user
 *   @var \WP_User|null                    $target_wp_user
 *   @var string                           $run_notice
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$repo = \Matchmaker\Repository\MatchRepository::instance();
$pool_table    = $wpdb->prefix . 'matchmaking_pool';
$matches_table = $wpdb->prefix . 'matches';
?>
<?php if (!empty($run_notice)) : ?>
    <div class="notice notice-success is-dismissible" style="margin-top:15px;"><p><strong><?php echo esc_html($run_notice); ?></strong></p></div>
<?php endif; ?>

<!-- Target User Selector & Live Execution Bar -->
<div class="mm-card" style="margin: 20px 0; background: #f8fafc; border-left: 4px solid #0284c7;">
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
        <input type="hidden" name="page" value="matchmaking-logs">
        <input type="hidden" name="tab" value="debugger">
        <label for="user_id"><strong><?php esc_html_e('Select Profile to Audit:', 'matchmaker'); ?></strong></label>
        <select name="user_id" id="user_id" style="min-width:300px;">
            <?php foreach ($all_pool_users as $pu) : ?>
                <option value="<?php echo (int) $pu['user_id']; ?>" <?php selected($selected_user_id, (int) $pu['user_id']); ?>>
                    User #<?php echo (int) $pu['user_id']; ?>: <?php echo esc_html($pu['display_name'] ?: 'No Name'); ?> (<?php echo esc_html(ucfirst($pu['gender'] ?? '')); ?>, <?php echo esc_html(ucfirst($pu['user_type'] ?? '')); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Inspect & Debug Flow', 'matchmaker'); ?>">
    </form>

    <?php if ($target_user) : ?>
        <form method="post" action="" style="margin-top:12px; display:inline-block;">
            <?php wp_nonce_field('mm_run_live_matching_nonce'); ?>
            <input type="hidden" name="mm_run_live_user_id" value="<?php echo (int) $selected_user_id; ?>">
            <input type="submit" class="button button-secondary" style="color:#0284c7; border-color:#0284c7;" value="<?php echo esc_attr(sprintf(__('⚡ Run Live Matching Job For User #%d', 'matchmaker'), $selected_user_id)); ?>">
        </form>
    <?php endif; ?>
</div>

<?php if (!$target_user) : ?>
    <div class="notice notice-warning"><p><?php esc_html_e('No candidate profile found in wp_matchmaking_pool.', 'matchmaker'); ?></p></div>
<?php else :
    $target_age = $repo->calc_age($target_user['birth_date'] ?? '');
    $target_last_run = get_user_meta($selected_user_id, 'mm_last_match_run', true);
?>

    <!-- Section 1: DB Saved Profile Audit Card -->
    <div class="mm-card" style="margin-bottom:25px;">
        <h3><?php echo esc_html(sprintf(__('📁 Section 1: Saved DB Record for User #%d (%s)', 'matchmaker'), $selected_user_id, $target_wp_user ? $target_wp_user->display_name : '')); ?></h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; font-size:13px;">
            <div>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('User ID / Email:', 'matchmaker'); ?></th><td>#<?php echo (int) $selected_user_id; ?> — <?php echo esc_html($target_wp_user ? $target_wp_user->user_email : ''); ?></td></tr>
                    <tr><th><?php esc_html_e('Membership Tier:', 'matchmaker'); ?></th><td><span class="mm-badge mm-badge-<?php echo esc_attr($target_user['user_type'] ?? 'free'); ?>"><?php echo esc_html(strtoupper($target_user['user_type'] ?? 'free')); ?></span></td></tr>
                    <tr><th><?php esc_html_e('Active Status:', 'matchmaker'); ?></th><td><?php echo ((int)($target_user['is_active'] ?? 1) === 1) ? '<span style="color:#16a34a; font-weight:bold;">Active (1)</span>' : '<span style="color:#dc2626; font-weight:bold;">Inactive (0)</span>'; ?></td></tr>
                    <tr><th><?php esc_html_e('Gender & Preferred:', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($target_user['gender'] ?? '—')); ?></strong> seeking <strong><?php echo esc_html(ucfirst($target_user['pref_gender'] ?? 'Any')); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Age & Preferred Range:', 'matchmaker'); ?></th><td>Age <strong><?php echo esc_html($target_age); ?></strong> (Birth: <?php echo esc_html($target_user['birth_date'] ?? '—'); ?>) | Prefers Age <strong><?php echo (int)($target_user['preferred_age_min'] ?? 18); ?> - <?php echo (int)($target_user['preferred_age_max'] ?? 99); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Location & Preferred:', 'matchmaker'); ?></th><td>Loc: <strong><?php echo esc_html($target_user['location'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_location'] ?? 'Any'); ?></strong></td></tr>
                </table>
            </div>
            <div>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Religion & Preferred:', 'matchmaker'); ?></th><td>Rel: <strong><?php echo esc_html($target_user['religion'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_religion'] ?? 'Any'); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Modesty & Preferred:', 'matchmaker'); ?></th><td>Mod: <strong><?php echo esc_html($target_user['modesty'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_modesty'] ?? 'Any'); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Origin & Preferred:', 'matchmaker'); ?></th><td>Origin: <strong><?php echo esc_html($target_user['origin'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_origin'] ?? 'Any'); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Languages:', 'matchmaker'); ?></th><td><strong><?php echo esc_html($target_user['languages'] ?? '—'); ?></strong></td></tr>
                    <tr><th><?php esc_html_e('Height & Range:', 'matchmaker'); ?></th><td>Height: <strong><?php echo (int)($target_user['height_cm'] ?? 0); ?> cm</strong> | Pref: <strong><?php echo (int)($target_user['preferred_height_min'] ?? 0); ?> - <?php echo (int)($target_user['preferred_height_max'] ?? 0); ?> cm</strong></td></tr>
                    <tr><th><?php esc_html_e('Last Match Run:', 'matchmaker'); ?></th><td><code><?php echo esc_html($target_last_run ?: 'Never'); ?></code></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Candidate Evaluation & Flow Log -->
    <div class="mm-card">
        <h3><?php esc_html_e('⚙️ Section 2: Step-by-Step Candidate Gate Evaluation & Rejection Reasons', 'matchmaker'); ?></h3>
        <p class="description"><?php echo esc_html(sprintf(__('Evaluating User #%d against every candidate in wp_matchmaking_pool:', 'matchmaker'), $selected_user_id)); ?></p>

        <?php
        $candidates = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id != %d ORDER BY user_id ASC", $selected_user_id),
            ARRAY_A
        );

        if (empty($candidates)) : ?>
            <p><?php esc_html_e('No other candidate profiles exist in the pool to check.', 'matchmaker'); ?></p>
        <?php else :
            $target_gender   = strtolower(trim((string)($target_user['gender'] ?? '')));
            $target_pref_g   = strtolower(trim((string)($target_user['pref_gender'] ?? '')));
            $target_loc      = trim((string)($target_user['location'] ?? ''));
            $target_pref_loc = trim((string)($target_user['pref_location'] ?? ''));
            $target_rel      = trim((string)($target_user['religion'] ?? ''));
            $target_pref_rel = trim((string)($target_user['pref_religion'] ?? ''));
            $target_mod      = trim((string)($target_user['modesty'] ?? ''));
            $target_pref_mod = trim((string)($target_user['pref_modesty'] ?? ''));
            $target_age_min  = (int)($target_user['preferred_age_min'] ?? 18);
            $target_age_max  = (int)($target_user['preferred_age_max'] ?? 99);

            $split = static fn(?string $v) => empty($v) ? [] : array_filter(array_map('trim', explode(',', $v)));
            $in_list_ci = static fn(?string $n, ?string $h) => !empty($n) && !empty($h) && in_array(strtolower(trim($n)), array_map('strtolower', $split($h)), true);
            $like_match = static fn(?string $v, ?string $list) => empty($v) || empty($list) || strtolower($list) === 'any' || str_contains(strtolower($list), strtolower($v));

            foreach ($candidates as $cand) :
                $cid   = (int) $cand['user_id'];
                $cuser = get_userdata($cid);
                $cage  = $repo->calc_age($cand['birth_date'] ?? '');
                $c_gender = strtolower(trim((string)($cand['gender'] ?? '')));
                $c_pref_g = strtolower(trim((string)($cand['pref_gender'] ?? '')));

                $rejection_reasons = [];

                // Gate 1: Active Status
                $g1_pass = ((int)($cand['is_active'] ?? 1) === 1);
                if (!$g1_pass) {
                    $rejection_reasons[] = __('Candidate profile is set to Inactive (is_active = 0)', 'matchmaker');
                }

                // Gate 2: Gender Bi-directional
                $g2a_pass = ($target_pref_g === '' || $target_pref_g === 'any' || $target_pref_g === $c_gender || $in_list_ci($c_gender, $target_pref_g));
                $g2b_pass = ($c_pref_g === '' || $c_pref_g === 'any' || $c_pref_g === $target_gender || $in_list_ci($target_gender, $c_pref_g));
                $g2_pass  = $g2a_pass && $g2b_pass;
                if (!$g2a_pass) {
                    $rejection_reasons[] = sprintf(__('Gender Gate: User #%d prefers %s, but Candidate #%d is %s', 'matchmaker'), $selected_user_id, ucfirst($target_pref_g), $cid, ucfirst($c_gender));
                }
                if (!$g2b_pass) {
                    $rejection_reasons[] = sprintf(__('Gender Gate: Candidate #%d prefers %s, but User #%d is %s', 'matchmaker'), $cid, ucfirst($c_pref_g), $selected_user_id, ucfirst($target_gender));
                }

                // Gate 3: Age Bi-directional
                $cand_age_num = (is_numeric($cage) ? (int)$cage : 28);
                $g3a_pass = ($cand['preferred_age_min'] <= 0 || $target_age >= $cand['preferred_age_min']) && ($cand['preferred_age_max'] <= 0 || $target_age <= $cand['preferred_age_max']);
                $g3b_pass = ($cand_age_num >= $target_age_min && $cand_age_num <= $target_age_max);
                $g3_pass  = $g3a_pass && $g3b_pass;
                if (!$g3a_pass) {
                    $rejection_reasons[] = sprintf(__('Age Gate: User #%d age (%d) is outside Candidate #%d preferred age range (%d-%d)', 'matchmaker'), $selected_user_id, $target_age, $cid, $cand['preferred_age_min'], $cand['preferred_age_max']);
                }
                if (!$g3b_pass) {
                    $rejection_reasons[] = sprintf(__('Age Gate: Candidate #%d age (%d) is outside User #%d preferred age range (%d-%d)', 'matchmaker'), $cid, $cand_age_num, $selected_user_id, $target_age_min, $target_age_max);
                }

                // Gate 4: Location Bi-directional
                $g4a_pass = empty($target_pref_loc) || strtolower($target_pref_loc) === 'any' || empty($cand['location']) || $in_list_ci($cand['location'], $target_pref_loc) || $like_match($cand['location'], $target_pref_loc);
                $g4b_pass = empty($cand['pref_location']) || strtolower($cand['pref_location']) === 'any' || empty($target_loc) || $in_list_ci($target_loc, $cand['pref_location']) || $like_match($target_loc, $cand['pref_location']);
                $g4_pass  = $g4a_pass && $g4b_pass;
                if (!$g4a_pass) {
                    $rejection_reasons[] = sprintf(__('Location Gate: Candidate location "%s" is not in User preferred locations "%s"', 'matchmaker'), $cand['location'], $target_pref_loc);
                }
                if (!$g4b_pass) {
                    $rejection_reasons[] = sprintf(__('Location Gate: User location "%s" is not in Candidate preferred locations "%s"', 'matchmaker'), $target_loc, $cand['pref_location']);
                }

                // Gate 5: Religion Bi-directional
                $g5a_pass = empty($target_pref_rel) || strtolower($target_pref_rel) === 'any' || empty($cand['religion']) || $in_list_ci($cand['religion'], $target_pref_rel) || $like_match($cand['religion'], $target_pref_rel);
                $g5b_pass = empty($cand['pref_religion']) || strtolower($cand['pref_religion']) === 'any' || empty($target_rel) || $in_list_ci($target_rel, $cand['pref_religion']) || $like_match($target_rel, $cand['pref_religion']);
                $g5_pass  = $g5a_pass && $g5b_pass;
                if (!$g5a_pass) {
                    $rejection_reasons[] = sprintf(__('Religion Gate: Candidate religion "%s" does not match User preferred religion "%s"', 'matchmaker'), $cand['religion'], $target_pref_rel);
                }
                if (!$g5b_pass) {
                    $rejection_reasons[] = sprintf(__('Religion Gate: User religion "%s" does not match Candidate preferred religion "%s"', 'matchmaker'), $target_rel, $cand['pref_religion']);
                }

                // Gate 6: Modesty Bi-directional
                $g6a_pass = empty($target_pref_mod) || strtolower($target_pref_mod) === 'any' || empty($cand['modesty']) || $in_list_ci($cand['modesty'], $target_pref_mod) || $like_match($cand['modesty'], $target_pref_mod);
                $g6b_pass = empty($cand['pref_modesty']) || strtolower($cand['pref_modesty']) === 'any' || empty($target_mod) || $in_list_ci($target_mod, $cand['pref_modesty']) || $like_match($target_mod, $cand['pref_modesty']);
                $g6_pass  = $g6a_pass && $g6b_pass;
                if (!$g6a_pass) {
                    $rejection_reasons[] = sprintf(__('Modesty Gate: Candidate modesty "%s" does not match User preferred modesty "%s"', 'matchmaker'), $cand['modesty'], $target_pref_mod);
                }
                if (!$g6b_pass) {
                    $rejection_reasons[] = sprintf(__('Modesty Gate: User modesty "%s" does not match Candidate preferred modesty "%s"', 'matchmaker'), $target_mod, $cand['pref_modesty']);
                }

                // Gate 7: Pair Existence in DB
                $u1 = min($selected_user_id, $cid);
                $u2 = max($selected_user_id, $cid);
                $existing_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches_table} WHERE user_one_id = %d AND user_two_id = %d", $u1, $u2), ARRAY_A);
                $g7_pass = empty($existing_row);
                if (!$g7_pass) {
                    $rejection_reasons[] = sprintf(__('Existing Pair Check: Pair (User #%d ↔ Candidate #%d) already exists in wp_matches with Status "%s" (Match ID #%d)', 'matchmaker'), $selected_user_id, $cid, $existing_row['status'], $existing_row['id']);
                }

                // Overall Hard Gate Evaluation
                $all_hard_gates_pass = ($g1_pass && $g2_pass && $g3_pass && $g4_pass && $g5_pass && $g6_pass && $g7_pass);

                // Score calculation
                $score = \Matchmaker\Core\MatchingEngine::instance()->compute_flexible_score($target_user, $cand);
            ?>

                <div style="border:1px solid <?php echo $all_hard_gates_pass ? '#829067' : '#e2e8f0'; ?>; border-radius:8px; margin-top:20px; overflow:hidden; background:#fff;">
                    <div style="padding:15px; background:<?php echo $all_hard_gates_pass ? '#F0F4EC' : '#f8fafc'; ?>; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <strong style="font-size:15px;">Candidate #<?php echo $cid; ?>: <?php echo esc_html($cuser ? $cuser->display_name : 'No Name'); ?></strong>
                            <span class="mm-badge mm-badge-<?php echo esc_attr($cand['user_type'] ?? 'free'); ?>" style="margin-left:8px;"><?php echo esc_html(strtoupper($cand['user_type'] ?? 'free')); ?></span>
                            <span style="margin-left:8px; color:#64748b; font-size:12px;"><?php echo esc_html(ucfirst($cand['gender'] ?? '')); ?>, <?php echo esc_html($cage); ?> yrs, <?php echo esc_html($cand['location'] ?? '—'); ?></span>
                        </div>
                        <div>
                            <?php if ($all_hard_gates_pass) : ?>
                                <span style="background:#16a34a; color:#fff; padding:4px 12px; border-radius:12px; font-weight:bold; font-size:12px;">🟢 PASSED ALL HARD GATES (Score <?php echo (int)$score; ?>/6)</span>
                            <?php else : ?>
                                <span style="background:#dc2626; color:#fff; padding:4px 12px; border-radius:12px; font-weight:bold; font-size:12px;">🔴 EXCLUDED (<?php echo count($rejection_reasons); ?> Gate Failures)</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="padding:15px;">
                        <table class="wp-list-table widefat fixed striped" style="margin-bottom:15px; font-size:12px;">
                            <thead>
                                <tr>
                                    <th style="width:130px;"><?php esc_html_e('Matching Gate', 'matchmaker'); ?></th>
                                    <th><?php esc_html_e('Target User #', 'matchmaker'); ?><?php echo $selected_user_id; ?> Value</th>
                                    <th><?php esc_html_e('Candidate #', 'matchmaker'); ?><?php echo $cid; ?> Value</th>
                                    <th style="width:100px; text-align:center;"><?php esc_html_e('Result', 'matchmaker'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>1. Active Status</strong></td>
                                    <td>is_active = <?php echo (int)($target_user['is_active'] ?? 1); ?></td>
                                    <td>is_active = <?php echo (int)($cand['is_active'] ?? 1); ?></td>
                                    <td style="text-align:center;"><?php echo $g1_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>2. Gender Gate</strong></td>
                                    <td>Gender: <strong><?php echo esc_html(ucfirst($target_gender)); ?></strong> | Wants: <strong><?php echo esc_html(ucfirst($target_pref_g)); ?></strong></td>
                                    <td>Gender: <strong><?php echo esc_html(ucfirst($c_gender)); ?></strong> | Wants: <strong><?php echo esc_html(ucfirst($c_pref_g)); ?></strong></td>
                                    <td style="text-align:center;"><?php echo $g2_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>3. Age Gate</strong></td>
                                    <td>Age: <strong><?php echo esc_html($target_age); ?></strong> | Wants: <strong><?php echo $target_age_min; ?>-<?php echo $target_age_max; ?></strong></td>
                                    <td>Age: <strong><?php echo esc_html($cage); ?></strong> | Wants: <strong><?php echo (int)($cand['preferred_age_min'] ?? 18); ?>-<?php echo (int)($cand['preferred_age_max'] ?? 99); ?></strong></td>
                                    <td style="text-align:center;"><?php echo $g3_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>4. Location Gate</strong></td>
                                    <td>Loc: <strong><?php echo esc_html($target_loc); ?></strong> | Pref: <strong><?php echo esc_html($target_pref_loc); ?></strong></td>
                                    <td>Loc: <strong><?php echo esc_html($cand['location'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($cand['pref_location'] ?? 'Any'); ?></strong></td>
                                    <td style="text-align:center;"><?php echo $g4_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>5. Religion Gate</strong></td>
                                    <td>Rel: <strong><?php echo esc_html($target_rel); ?></strong> | Pref: <strong><?php echo esc_html($target_pref_rel); ?></strong></td>
                                    <td>Rel: <strong><?php echo esc_html($cand['religion'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($cand['pref_religion'] ?? 'Any'); ?></strong></td>
                                    <td style="text-align:center;"><?php echo $g5_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>6. Modesty Gate</strong></td>
                                    <td>Mod: <strong><?php echo esc_html($target_mod); ?></strong> | Pref: <strong><?php echo esc_html($target_pref_mod); ?></strong></td>
                                    <td>Mod: <strong><?php echo esc_html($cand['modesty'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($cand['pref_modesty'] ?? 'Any'); ?></strong></td>
                                    <td style="text-align:center;"><?php echo $g6_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>7. Pair Uniqueness</strong></td>
                                    <td>User #<?php echo $selected_user_id; ?></td>
                                    <td>Candidate #<?php echo $cid; ?></td>
                                    <td style="text-align:center;"><?php echo $g7_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Rejection Reasons or Match Success Explanation -->
                        <div style="padding:12px; border-radius:6px; background:<?php echo $all_hard_gates_pass ? '#f0fdf4' : '#fef2f2'; ?>; border:1px solid <?php echo $all_hard_gates_pass ? '#bbf7d0' : '#fecaca'; ?>;">
                            <strong style="color:<?php echo $all_hard_gates_pass ? '#166534' : '#991b1b'; ?>;">
                                <?php if ($all_hard_gates_pass) : ?>
                                    <?php esc_html_e('✅ MATCH ELIGIBLE: Candidate passes all 7 bi-directional hard gates with a flexible score of ' . $score . '/6.', 'matchmaker'); ?>
                                <?php else : ?>
                                    <?php esc_html_e('❌ EXCLUSION REASON(S): Why this candidate did not match:', 'matchmaker'); ?>
                                <?php endif; ?>
                            </strong>
                            <?php if (!empty($rejection_reasons)) : ?>
                                <ul style="margin:8px 0 0 20px; color:#991b1b; font-size:12px;">
                                    <?php foreach ($rejection_reasons as $reason) : ?>
                                        <li><?php echo esc_html($reason); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>
