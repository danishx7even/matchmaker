<?php
/**
 * View: Admin Manual Matchmaker Tool View
 *
 * Available variables:
 *   @var int                              $user_id
 *   @var array<string, mixed>             $pool
 *   @var \WP_User                         $user_obj
 *   @var array<string, mixed>             $meta
 *   @var string                           $user_age
 *   @var int                              $quota_used
 *   @var string                           $f_gender
 *   @var int                              $f_age_min
 *   @var int                              $f_age_max
 *   @var string                           $f_country
 *   @var string                           $f_state
 *   @var string                           $f_city
 *   @var string                           $f_citizenship
 *   @var string                           $f_location
 *   @var string                           $f_origin
 *   @var string                           $f_religion
 *   @var string                           $f_modesty
 *   @var array<int, array<string, mixed>> $candidates
 *   @var string                           $back_url
 *   @var string                           $reset_url
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
$fg   = \Matchmaker\Frontend\FieldGenerator::instance();

$country_options     = $fg->options_pref_country();
$state_options       = $fg->options_pref_state($f_country ?? '');
$city_options        = $fg->options_pref_city($f_country ?? '', $f_state ?? '');
$citizenship_options = $fg->options_pref_citizenship();
$hierarchy_json      = json_encode($fg->get_hierarchy_data(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$user_country_disp = trim(($pool['city'] ? $pool['city'] . ', ' : '') . ($pool['country'] ?: ($pool['location'] ?: '—')));
?>
<p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php echo esc_html(sprintf(__('Back to %s Profile', 'matchmaker'), $user_obj->display_name)); ?></a></p>

<!-- Header -->
<div class="mm-detail-header">
    <div>
        <h2>
            <?php esc_html_e('Manual Matchmaker for:', 'matchmaker'); ?> <?php echo esc_html($user_obj->display_name); ?>
            <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                <?php echo esc_html($repo->format_tier_label($pool['user_type'])); ?>
            </span>
        </h2>
        <p class="description">
            <strong><?php esc_html_e('Gender:', 'matchmaker'); ?></strong> <?php echo esc_html(ucfirst($pool['gender'])); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Age:', 'matchmaker'); ?></strong> <?php echo esc_html($user_age . ' yrs'); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Location:', 'matchmaker'); ?></strong> <?php echo esc_html($user_country_disp); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Citizenship:', 'matchmaker'); ?></strong> <?php echo esc_html($meta['user_citizenship'] ?? '—'); ?> &nbsp;|&nbsp; 
            <strong><?php esc_html_e('Quota Used:', 'matchmaker'); ?></strong> <?php echo $quota_used; ?> / <?php echo (int) $repo->get_max_cycle_matches(); ?>
        </p>
    </div>
</div>

<!-- Advanced Filter Form Card -->
<div class="mm-card" style="margin-bottom:24px;">
    <h3><?php esc_html_e('Advanced Candidate Match Filters', 'matchmaker'); ?></h3>
    <p class="description" style="margin-top:-6px; margin-bottom:16px;">
        <?php esc_html_e('Customize search criteria to find the best compatible candidates in the pool. Results are automatically ranked by compatibility score.', 'matchmaker'); ?>
    </p>
    <form method="get" id="mm-manual-match-filter-form">
        <input type="hidden" name="page" value="matchmaking-pool">
        <input type="hidden" name="manual_match" value="<?php echo $user_id; ?>">

        <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px;">
            <!-- Row 1: Core Demographics & Location -->
            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('Candidate Gender', 'matchmaker'); ?></strong></label><br>
                <select name="f_gender" style="width:100%;">
                    <option value="female" <?php selected(strtolower($f_gender ?? ''), 'female'); ?>><?php esc_html_e('Female', 'matchmaker'); ?></option>
                    <option value="male"   <?php selected(strtolower($f_gender ?? ''), 'male');   ?>><?php esc_html_e('Male', 'matchmaker'); ?></option>
                    <option value="any"    <?php selected(strtolower($f_gender ?? ''), 'any');    ?>><?php esc_html_e('Any Gender', 'matchmaker'); ?></option>
                </select>
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('Age Range (Min – Max)', 'matchmaker'); ?></strong></label><br>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="number" name="f_age_min" value="<?php echo esc_attr((string)($f_age_min ?? 18)); ?>" style="width:75px;" min="18" max="100">
                    <span>–</span>
                    <input type="number" name="f_age_max" value="<?php echo esc_attr((string)($f_age_max ?? 80)); ?>" style="width:75px;" min="18" max="100">
                </div>
            </div>

            <div style="flex:1 1 200px;">
                <label><strong><?php esc_html_e('Country', 'matchmaker'); ?></strong></label><br>
                <select name="f_country" id="mm_f_country" style="width:100%;">
                    <?php foreach ($country_options as $c_opt) : ?>
                        <option value="<?php echo esc_attr($c_opt); ?>" <?php selected(strcasecmp($f_country ?? '', $c_opt) === 0); ?>>
                            <?php echo esc_html($c_opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('State / Region', 'matchmaker'); ?></strong></label><br>
                <select name="f_state" id="mm_f_state" style="width:100%;">
                    <?php foreach ($state_options as $s_opt) : ?>
                        <option value="<?php echo esc_attr($s_opt); ?>" <?php selected(strcasecmp($f_state ?? '', $s_opt) === 0); ?>>
                            <?php echo esc_html($s_opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('City', 'matchmaker'); ?></strong></label><br>
                <select name="f_city" id="mm_f_city" style="width:100%;">
                    <?php foreach ($city_options as $ct_opt) : ?>
                        <option value="<?php echo esc_attr($ct_opt); ?>" <?php selected(strcasecmp($f_city ?? '', $ct_opt) === 0); ?>>
                            <?php echo esc_html($ct_opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px;">
            <!-- Row 2: Cultural, Religious, Citizenship & Free Text Filters -->
            <div style="flex:1 1 200px;">
                <label><strong><?php esc_html_e('Citizenship', 'matchmaker'); ?></strong></label><br>
                <select name="f_citizenship" id="mm_f_citizenship" style="width:100%;">
                    <?php foreach ($citizenship_options as $cz_opt) : ?>
                        <option value="<?php echo esc_attr($cz_opt); ?>" <?php selected(strcasecmp($f_citizenship ?? '', $cz_opt) === 0); ?>>
                            <?php echo esc_html($cz_opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></strong></label><br>
                <input type="text" name="f_origin" value="<?php echo esc_attr($f_origin ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Arab or Any', 'matchmaker'); ?>" style="width:100%;">
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('Religion', 'matchmaker'); ?></strong></label><br>
                <input type="text" name="f_religion" value="<?php echo esc_attr($f_religion ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Muslim or Any', 'matchmaker'); ?>" style="width:100%;">
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('Modesty Level', 'matchmaker'); ?></strong></label><br>
                <input type="text" name="f_modesty" value="<?php echo esc_attr($f_modesty ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Hijab, Niqab or Any', 'matchmaker'); ?>" style="width:100%;">
            </div>

            <div style="flex:1 1 180px;">
                <label><strong><?php esc_html_e('Keyword Location', 'matchmaker'); ?></strong></label><br>
                <input type="text" name="f_location" value="<?php echo esc_attr($f_location ?? ''); ?>" placeholder="<?php esc_attr_e('Optional text search', 'matchmaker'); ?>" style="width:100%;">
            </div>
        </div>

        <div>
            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Apply Advanced Filters', 'matchmaker'); ?>">
            <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" style="margin-left:8px;"><?php esc_html_e('Reset Filters', 'matchmaker'); ?></a>
        </div>
    </form>
</div>

<script>
(function() {
    var hierarchyData = <?php echo $hierarchy_json ?: '{}'; ?>;
    var countrySelect = document.getElementById('mm_f_country');
    var stateSelect   = document.getElementById('mm_f_state');
    var citySelect    = document.getElementById('mm_f_city');

    if (!countrySelect || !stateSelect || !citySelect) return;

    function populateStates(country, selectedState) {
        stateSelect.innerHTML = '<option value="Any State">Any State</option>';
        citySelect.innerHTML  = '<option value="Any City">Any City</option>';

        if (!country || country.toLowerCase() === 'any country' || country.toLowerCase() === 'select country' || !hierarchyData[country]) {
            return;
        }

        var states = Object.keys(hierarchyData[country]).sort(function(a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });

        states.forEach(function(st) {
            var opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            if (selectedState && selectedState.toLowerCase() === st.toLowerCase()) {
                opt.selected = true;
            }
            stateSelect.appendChild(opt);
        });
    }

    function populateCities(country, state, selectedCity) {
        citySelect.innerHTML = '<option value="Any City">Any City</option>';

        if (!country || !state || state.toLowerCase() === 'any state' || !hierarchyData[country] || !hierarchyData[country][state]) {
            return;
        }

        var cities = (hierarchyData[country][state] || []).slice().sort(function(a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });

        cities.forEach(function(ct) {
            var opt = document.createElement('option');
            opt.value = ct;
            opt.textContent = ct;
            if (selectedCity && selectedCity.toLowerCase() === ct.toLowerCase()) {
                opt.selected = true;
            }
            citySelect.appendChild(opt);
        });
    }

    countrySelect.addEventListener('change', function() {
        populateStates(this.value, '');
    });

    stateSelect.addEventListener('change', function() {
        populateCities(countrySelect.value, this.value, '');
    });
})();
</script>

<!-- Candidate Results Table -->
<div class="mm-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0; border:0; padding:0;"><?php echo esc_html(sprintf(__('Ranked Candidate Matches (%d found)', 'matchmaker'), count($candidates))); ?></h3>
        <span class="mm-badge mm-badge-monthly" style="font-size:12px; font-weight:600; padding:4px 10px; border-radius:12px;">
            <?php esc_html_e('Ranked by Highest Compatibility Score', 'matchmaker'); ?>
        </span>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Candidate Name / Email', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Gender / Age', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Country / City', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Citizenship / Origin', 'matchmaker'); ?></th>
                <th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th>
                <th style="width:130px; text-align:center;"><?php esc_html_e('Score', 'matchmaker'); ?></th>
                <th style="width:160px; text-align:center;"><?php esc_html_e('Action', 'matchmaker'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($candidates)) : ?>
                <tr><td colspan="8"><?php esc_html_e('No compatible candidates found matching current filter criteria.', 'matchmaker'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($candidates as $cand) :
                    $cid   = (int) $cand['user_id'];
                    $cuser = get_userdata($cid);
                    $photo = $repo->get_meta($cid, 'user_photo1');
                    $cage  = $repo->calc_age($cand['birth_date'] ?? '');
                    $score = (int) ($cand['compatibility_score'] ?? \Matchmaker\Service\MatchService::instance()->compute_flexible_score($pool, $cand));
                    
                    $c_country = $cand['country'] ?: '';
                    $c_city    = $cand['city'] ?: '';
                    $c_state   = $cand['state'] ?: '';
                    $loc_disp  = trim(($c_city ? $c_city . ', ' : '') . ($c_country ?: ($cand['location'] ?: '—')));
                    $cand_cit  = $repo->get_meta($cid, 'user_citizenship') ?: '—';

                    $create_url = wp_nonce_url(
                        admin_url('admin.php?page=matchmaking-pool&manual_match=' . $user_id . '&mm_action=create_manual_match&u1=' . $user_id . '&u2=' . $cid),
                        'mm_manual_match'
                    );
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($photo)) : ?>
                                <img src="<?php echo esc_url($photo); ?>" style="width:36px;height:36px;border-radius:4px;object-fit:cover;" alt="">
                            <?php else : ?>
                                <div class="mm-avatar-thumb">
                                    <?php echo esc_html(strtoupper(substr($cuser ? $cuser->display_name : 'U', 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $cid)); ?>"><?php echo esc_html($cuser ? $cuser->display_name : 'User #' . $cid); ?></a></strong>
                            <span class="mm-badge mm-badge-<?php echo esc_attr($cand['user_type'] ?? 'free'); ?>" style="margin-left:4px;">
                                <?php echo esc_html($repo->format_tier_label($cand['user_type'] ?? 'free')); ?>
                            </span>
                            <br>
                            <small style="color:#666;"><?php echo esc_html($cuser ? $cuser->user_email : ''); ?></small>
                        </td>
                        <td><?php echo esc_html(ucfirst($cand['gender'] ?? '')) . ' (' . esc_html($cage) . ' yrs)'; ?></td>
                        <td><?php echo esc_html($loc_disp); ?></td>
                        <td><?php echo esc_html($cand_cit . ' / ' . ($cand['origin'] ?: '—')); ?></td>
                        <td><?php echo esc_html(($cand['religion'] ?: '—') . ' / ' . ($cand['modesty'] ?: '—')); ?></td>
                        <td style="text-align:center;">
                            <span style="display:inline-block; font-weight:700; color:#0284c7; background:#e0f2fe; padding:3px 10px; border-radius:12px; font-size:12px;">
                                <?php echo $score; ?> / 6
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <a href="<?php echo esc_url($create_url); ?>" class="button button-primary button-small">
                                + <?php esc_html_e('Create Match Pair', 'matchmaker'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
