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
$origin_options      = $fg->options_pref_origin();
$cand_gender         = !empty($f_gender) ? $f_gender : ($pool['pref_gender'] ?? 'female');
$modesty_options     = $fg->options_pref_modesty($cand_gender);
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
            <strong><?php esc_html_e('Citizenship:', 'matchmaker'); ?></strong> <?php echo esc_html($meta['user_citizenship'] ?? '—'); ?>
            <?php if (($pool['user_type'] ?? 'free') === 'monthly') : ?>
                &nbsp;|&nbsp; <strong><?php esc_html_e('Quota Used:', 'matchmaker'); ?></strong> <?php echo $quota_used; ?> / <?php echo (int) $repo->get_max_cycle_matches(); ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Navigation Tabs -->
<nav class="nav-tab-wrapper mm-manual-nav-tabs" style="margin-bottom: 20px;">
    <a href="#tab-filter" class="nav-tab nav-tab-active" data-tab="filter">
        <span class="dashicons dashicons-filter" style="margin-top:4px; margin-right:4px;"></span>
        <?php esc_html_e('Compatibility Filter Search', 'matchmaker'); ?>
    </a>
    <a href="#tab-direct" class="nav-tab" data-tab="direct">
        <span class="dashicons dashicons-search" style="margin-top:4px; margin-right:4px;"></span>
        <?php esc_html_e('Direct Member Search', 'matchmaker'); ?>
    </a>
</nav>

<!-- TAB 1: Compatibility Filter Search Panel -->
<div id="mm-panel-filter" class="mm-tab-panel active">
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
                    <select name="f_gender" id="mm_f_gender" style="width:100%;">
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
                    <select name="f_origin" id="mm_f_origin" style="width:100%;">
                        <?php foreach ($origin_options as $orig_opt) : ?>
                            <option value="<?php echo esc_attr($orig_opt); ?>" <?php selected(strcasecmp($f_origin ?? '', $orig_opt) === 0); ?>>
                                <?php echo esc_html($orig_opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex:1 1 180px;">
                    <label><strong><?php esc_html_e('Religion', 'matchmaker'); ?></strong></label><br>
                    <input type="text" name="f_religion" value="<?php echo esc_attr($f_religion ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. Muslim or Any', 'matchmaker'); ?>" style="width:100%;">
                </div>

                <div style="flex:1 1 180px;">
                    <label><strong><?php esc_html_e('Modesty Level', 'matchmaker'); ?></strong></label><br>
                    <select name="f_modesty" id="mm_f_modesty" style="width:100%;">
                        <?php foreach ($modesty_options as $mod_opt) : ?>
                            <option value="<?php echo esc_attr($mod_opt); ?>" <?php selected(strcasecmp($f_modesty ?? '', $mod_opt) === 0); ?>>
                                <?php echo esc_html($mod_opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
</div>

<!-- TAB 2: Direct Member Search Panel -->
<div id="mm-panel-direct" class="mm-tab-panel" style="display:none;">
    <!-- Live Search Box Card -->
    <div class="mm-card" style="margin-bottom:24px;">
        <h3 style="margin-top:0;"><?php esc_html_e('Direct Member Search & Match Creation', 'matchmaker'); ?></h3>
        <p class="description" style="margin-top:-6px; margin-bottom:18px;">
            <?php esc_html_e('Search for any registered member by Name, Email, Username, or User ID. A detailed compatibility breakdown will be displayed for your information, and you can create a direct match pair regardless of criteria matching.', 'matchmaker'); ?>
        </p>

        <div class="mm-direct-search-box" style="position:relative; max-width:600px;">
            <label for="mm-direct-search-input" class="screen-reader-text"><?php esc_html_e('Search candidate', 'matchmaker'); ?></label>
            <div style="position:relative; display:flex; align-items:center;">
                <span class="dashicons dashicons-search" style="position:absolute; left:12px; color:#64748b; font-size:18px; pointer-events:none;"></span>
                <input 
                    type="text" 
                    id="mm-direct-search-input" 
                    placeholder="<?php esc_attr_e('Type name, email, or user ID to search...', 'matchmaker'); ?>" 
                    autocomplete="off" 
                    style="width:100%; padding:10px 40px 10px 38px; font-size:14px; border:1px solid #cbd5e1; border-radius:8px; outline:none; transition:border-color 0.2s, box-shadow 0.2s;"
                >
                <span id="mm-direct-search-spinner" class="spinner" style="position:absolute; right:12px; margin:0; float:none; display:none;"></span>
                <button type="button" id="mm-direct-search-clear" style="display:none; position:absolute; right:10px; background:none; border:none; color:#94a3b8; cursor:pointer; font-size:16px; padding:4px;" title="<?php esc_attr_e('Clear search', 'matchmaker'); ?>">&times;</button>
            </div>

            <!-- Autocomplete Results Dropdown -->
            <div id="mm-direct-search-results" class="mm-direct-results-dropdown" style="display:none;"></div>
        </div>
    </div>

    <!-- Empty State Prompt -->
    <div id="mm-direct-empty-state" class="mm-card" style="text-align:center; padding:50px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px;">
        <span class="dashicons dashicons-search" style="font-size:48px; width:48px; height:48px; color:#94a3b8; margin-bottom:12px;"></span>
        <h4 style="margin:0 0 8px 0; color:#334155; font-size:16px;"><?php esc_html_e('No candidate selected', 'matchmaker'); ?></h4>
        <p style="margin:0; color:#64748b; font-size:13px; max-width:450px; margin-left:auto; margin-right:auto;">
            <?php esc_html_e('Use the search box above to find any candidate in the matchmaking pool. Once selected, their full profile and side-by-side compatibility breakdown will appear here.', 'matchmaker'); ?>
        </p>
    </div>

    <!-- Candidate Breakdown & Action Container -->
    <div id="mm-direct-breakdown-card" class="mm-card" style="display:none; margin-bottom:24px;">
        <!-- Dynamic content rendered by JS -->
    </div>
</div>

<script>
(function() {
    var hierarchyData = <?php echo $hierarchy_json ?: '{}'; ?>;
    var countrySelect = document.getElementById('mm_f_country');
    var stateSelect   = document.getElementById('mm_f_state');
    var citySelect    = document.getElementById('mm_f_city');

    if (countrySelect && stateSelect && citySelect) {
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

        var modestyConfigs = {
            female: <?php echo json_encode($fg->options_pref_modesty('female')); ?>,
            male:   <?php echo json_encode($fg->options_pref_modesty('male')); ?>
        };
        var genderSelect  = document.getElementById('mm_f_gender');
        var modestySelect = document.getElementById('mm_f_modesty');

        if (genderSelect && modestySelect) {
            genderSelect.addEventListener('change', function() {
                var g = (genderSelect.value || '').toLowerCase();
                var opts = (g === 'male') ? modestyConfigs.male : modestyConfigs.female;
                var curr = modestySelect.value;
                modestySelect.innerHTML = '';
                opts.forEach(function(opt) {
                    var el = document.createElement('option');
                    el.value = opt;
                    el.textContent = opt;
                    if (curr && opt.toLowerCase() === curr.toLowerCase()) {
                        el.selected = true;
                    }
                    modestySelect.appendChild(el);
                });
            });
        }
    }

    // Tab Switching Logic
    var tabLinks = document.querySelectorAll('.mm-manual-nav-tabs .nav-tab');
    var panelFilter = document.getElementById('mm-panel-filter');
    var panelDirect = document.getElementById('mm-panel-direct');

    function switchTab(tabKey) {
        tabLinks.forEach(function(link) {
            var lKey = link.getAttribute('data-tab');
            if (lKey === tabKey) {
                link.classList.add('nav-tab-active');
            } else {
                link.classList.remove('nav-tab-active');
            }
        });

        if (tabKey === 'direct') {
            if (panelFilter) panelFilter.style.display = 'none';
            if (panelDirect) panelDirect.style.display = 'block';
        } else {
            if (panelFilter) panelFilter.style.display = 'block';
            if (panelDirect) panelDirect.style.display = 'none';
        }
    }

    tabLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var tabKey = this.getAttribute('data-tab') || 'filter';
            window.location.hash = 'tab-' + tabKey;
            switchTab(tabKey);
        });
    });

    // Check initial hash
    if (window.location.hash === '#tab-direct') {
        switchTab('direct');
    }

    // Direct Member Search & Breakdown Logic
    var targetUserId = <?php echo (int) $user_id; ?>;
    var searchInput   = document.getElementById('mm-direct-search-input');
    var searchSpinner = document.getElementById('mm-direct-search-spinner');
    var searchClear   = document.getElementById('mm-direct-search-clear');
    var searchResults = document.getElementById('mm-direct-search-results');
    var emptyState    = document.getElementById('mm-direct-empty-state');
    var breakdownCard = document.getElementById('mm-direct-breakdown-card');

    var debounceTimer = null;
    var activeAjaxReq = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.trim();
            if (query.length > 0) {
                if (searchClear) searchClear.style.display = 'block';
            } else {
                if (searchClear) searchClear.style.display = 'none';
                if (searchResults) searchResults.style.display = 'none';
                return;
            }

            if (query.length < 2) {
                if (searchResults) searchResults.style.display = 'none';
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                performCandidateSearch(query);
            }, 250);
        });

        searchInput.addEventListener('focus', function() {
            if (searchResults && searchResults.innerHTML.trim() !== '' && searchInput.value.trim().length >= 2) {
                searchResults.style.display = 'block';
            }
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchClear.style.display = 'none';
            if (searchResults) {
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
            }
            searchInput.focus();
        });
    }

    document.addEventListener('click', function(e) {
        if (searchResults && !searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });

    function performCandidateSearch(query) {
        if (searchSpinner) searchSpinner.style.display = 'inline-block';

        if (activeAjaxReq && typeof activeAjaxReq.abort === 'function') {
            activeAjaxReq.abort();
        }

        var ajaxUrl = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.ajax_url) ? matchmakerAdmin.ajax_url : '/wp-admin/admin-ajax.php';
        var nonce   = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.nonce) ? matchmakerAdmin.nonce : '';

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mm_admin_search_candidates',
                nonce: nonce,
                target_user_id: targetUserId,
                query: query
            },
            success: function(res) {
                if (searchSpinner) searchSpinner.style.display = 'none';
                if (res && res.success && res.data && res.data.candidates) {
                    renderSearchResults(res.data.candidates);
                } else {
                    renderSearchResults([]);
                }
            },
            error: function(xhr, status) {
                if (status !== 'abort') {
                    if (searchSpinner) searchSpinner.style.display = 'none';
                }
            }
        });
    }

    function renderSearchResults(candidates) {
        if (!searchResults) return;
        if (!candidates || candidates.length === 0) {
            searchResults.innerHTML = '<div class="mm-search-no-results"><?php echo esc_js(__('No matching candidates found in the pool.', 'matchmaker')); ?></div>';
            searchResults.style.display = 'block';
            return;
        }

        var html = '<ul class="mm-search-results-list">';
        candidates.forEach(function(cand) {
            var avatar = cand.photo 
                ? '<img src="' + escapeHtml(cand.photo) + '" class="mm-search-avatar" alt="">' 
                : '<div class="mm-search-avatar mm-search-avatar-placeholder">' + escapeHtml((cand.display_name || 'U').charAt(0).toUpperCase()) + '</div>';
            
            var metaParts = [];
            if (cand.gender) metaParts.push(cand.gender);
            if (cand.age) metaParts.push(cand.age + ' yrs');
            if (cand.location && cand.location !== '—') metaParts.push(cand.location);

            html += '<li class="mm-search-result-item" data-candidate-id="' + cand.id + '">';
            html += avatar;
            html += '<div class="mm-search-item-info">';
            html += '  <div class="mm-search-item-title">';
            html += '    <strong>' + escapeHtml(cand.display_name) + '</strong> <span class="mm-badge mm-badge-' + escapeHtml(cand.user_type) + '">' + escapeHtml(cand.tier_label) + '</span>';
            html += '  </div>';
            html += '  <div class="mm-search-item-meta">' + escapeHtml(cand.user_email) + ' &bull; ' + escapeHtml(metaParts.join(' &bull; ')) + '</div>';
            html += '</div>';
            html += '<div class="mm-search-item-action"><span class="button button-small button-secondary"><?php echo esc_js(__('Select', 'matchmaker')); ?></span></div>';
            html += '</li>';
        });
        html += '</ul>';

        searchResults.innerHTML = html;
        searchResults.style.display = 'block';

        // Bind clicks on search items
        var items = searchResults.querySelectorAll('.mm-search-result-item');
        items.forEach(function(item) {
            item.addEventListener('click', function() {
                var candId = parseInt(this.getAttribute('data-candidate-id'), 10);
                if (candId) {
                    searchResults.style.display = 'none';
                    loadCandidateBreakdown(candId);
                }
            });
        });
    }

    function loadCandidateBreakdown(candidateId) {
        if (emptyState) emptyState.style.display = 'none';
        if (breakdownCard) {
            breakdownCard.style.display = 'block';
            breakdownCard.innerHTML = '<div style="text-align:center; padding:40px;"><span class="spinner is-active" style="float:none; margin-bottom:10px;"></span><p><?php echo esc_js(__('Loading candidate compatibility analysis...', 'matchmaker')); ?></p></div>';
        }

        var ajaxUrl = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.ajax_url) ? matchmakerAdmin.ajax_url : '/wp-admin/admin-ajax.php';
        var nonce   = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.nonce) ? matchmakerAdmin.nonce : '';

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mm_admin_get_candidate_breakdown',
                nonce: nonce,
                target_user_id: targetUserId,
                candidate_id: candidateId
            },
            success: function(res) {
                if (res && res.success && res.data) {
                    renderBreakdownCard(res.data);
                } else {
                    var err = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Failed to load candidate breakdown.', 'matchmaker')); ?>';
                    breakdownCard.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(err) + '</p></div>';
                }
            },
            error: function() {
                breakdownCard.innerHTML = '<div class="notice notice-error inline"><p><?php echo esc_js(__('Server error occurred while loading breakdown.', 'matchmaker')); ?></p></div>';
            }
        });
    }

    function renderBreakdownCard(data) {
        var t = data.target;
        var c = data.candidate;
        var score = data.score;
        var maxScore = data.max_score || 6;
        var matchedCriteria = data.matching_criteria_count || 0;
        var totalCriteria = data.total_criteria_count || 11;
        var criteria = data.criteria || [];
        var matchExists = data.match_exists;
        var existingStatus = data.existing_status;
        var existingMatchId = data.existing_match_id;

        var html = '';

        // Top Summary Header
        html += '<div class="mm-breakdown-header-wrap" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; border-bottom:1px solid #e2e8f0; padding-bottom:16px; margin-bottom:20px;">';
        html += '  <div>';
        html += '    <h3 style="margin:0 0 4px 0; font-size:18px;"><?php echo esc_js(__('Candidate Compatibility Breakdown', 'matchmaker')); ?></h3>';
        html += '    <p class="description" style="margin:0;"><?php echo esc_js(__('Informational side-by-side comparison of pool criteria between the target member and candidate.', 'matchmaker')); ?></p>';
        html += '  </div>';
        html += '  <div style="display:flex; align-items:center; gap:12px;">';
        html += '    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:8px 16px; text-align:center;">';
        html += '      <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:#0369a1;"><?php echo esc_js(__('Compatibility Score', 'matchmaker')); ?></div>';
        html += '      <div style="font-size:20px; font-weight:800; color:#0284c7;">' + score + ' / ' + maxScore + '</div>';
        html += '    </div>';
        html += '    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 16px; text-align:center;">';
        html += '      <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:#475569;"><?php echo esc_js(__('Criteria Match Rate', 'matchmaker')); ?></div>';
        html += '      <div style="font-size:20px; font-weight:800; color:#334155;">' + matchedCriteria + ' / ' + totalCriteria + '</div>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        // Side-by-side Profile Cards
        html += '<div class="mm-compare-members-grid" style="display:grid; grid-template-columns:1fr auto 1fr; gap:16px; align-items:center; margin-bottom:24px;">';
        
        // Target Member Card
        html += '  <div class="mm-compare-user-card" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; display:flex; gap:14px; align-items:center;">';
        if (t.photo) {
            html += '    <img src="' + escapeHtml(t.photo) + '" style="width:52px; height:52px; border-radius:6px; object-fit:cover;" alt="">';
        } else {
            html += '    <div class="mm-avatar-thumb" style="width:52px; height:52px; font-size:18px; font-weight:bold;">' + escapeHtml((t.name || 'U').charAt(0).toUpperCase()) + '</div>';
        }
        html += '    <div style="flex:1;">';
        html += '      <div style="font-size:11px; text-transform:uppercase; font-weight:700; color:#64748b;"><?php echo esc_js(__('Target Member', 'matchmaker')); ?></div>';
        html += '      <div style="font-size:15px; font-weight:700; color:#1e293b;">' + escapeHtml(t.name) + ' <span class="mm-badge mm-badge-' + escapeHtml(t.user_type) + '">' + escapeHtml(t.tier_label) + '</span></div>';
        html += '      <div style="font-size:12px; color:#64748b; margin-top:2px;">' + escapeHtml(t.gender) + ' &bull; ' + escapeHtml(t.age) + ' yrs &bull; ' + escapeHtml(t.email) + '</div>';
        html += '    </div>';
        html += '  </div>';

        // VS Badge
        html += '  <div style="text-align:center;">';
        html += '    <span style="display:inline-block; width:34px; height:34px; line-height:34px; border-radius:50%; background:#e2e8f0; color:#475569; font-weight:800; font-size:12px;">VS</span>';
        html += '  </div>';

        // Candidate Member Card
        html += '  <div class="mm-compare-user-card" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; display:flex; gap:14px; align-items:center;">';
        if (c.photo) {
            html += '    <img src="' + escapeHtml(c.photo) + '" style="width:52px; height:52px; border-radius:6px; object-fit:cover;" alt="">';
        } else {
            html += '    <div class="mm-avatar-thumb" style="width:52px; height:52px; font-size:18px; font-weight:bold;">' + escapeHtml((c.name || 'U').charAt(0).toUpperCase()) + '</div>';
        }
        html += '    <div style="flex:1;">';
        html += '      <div style="font-size:11px; text-transform:uppercase; font-weight:700; color:#64748b;"><?php echo esc_js(__('Selected Candidate', 'matchmaker')); ?></div>';
        html += '      <div style="font-size:15px; font-weight:700; color:#1e293b;">' + escapeHtml(c.name) + ' <span class="mm-badge mm-badge-' + escapeHtml(c.user_type) + '">' + escapeHtml(c.tier_label) + '</span></div>';
        html += '      <div style="font-size:12px; color:#64748b; margin-top:2px;">' + escapeHtml(c.gender) + ' &bull; ' + escapeHtml(c.age) + ' yrs &bull; ' + escapeHtml(c.email) + '</div>';
        html += '    </div>';
        html += '  </div>';

        html += '</div>';

        // Criteria Comparison Table
        html += '<table class="wp-list-table widefat fixed striped mm-breakdown-table" style="margin-bottom:24px; border-radius:6px; overflow:hidden;">';
        html += '  <thead>';
        html += '    <tr>';
        html += '      <th style="width:160px; font-weight:700;"><?php echo esc_js(__('Criterion', 'matchmaker')); ?></th>';
        html += '      <th style="font-weight:700;">' + escapeHtml(t.name) + '</th>';
        html += '      <th style="font-weight:700;">' + escapeHtml(c.name) + '</th>';
        html += '      <th style="width:120px; text-align:center; font-weight:700;"><?php echo esc_js(__('Status', 'matchmaker')); ?></th>';
        html += '      <th style="width:240px; font-weight:700;"><?php echo esc_js(__('Analysis Note', 'matchmaker')); ?></th>';
        html += '    </tr>';
        html += '  </thead>';
        html += '  <tbody>';

        criteria.forEach(function(crit) {
            var matchBadge = crit.is_match 
                ? '<span class="mm-pill-match" style="display:inline-flex; align-items:center; gap:4px; font-weight:700; font-size:11px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:3px 8px; border-radius:12px;"><span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px;"></span> ' + '<?php echo esc_js(__('Match', 'matchmaker')); ?>' + '</span>'
                : '<span class="mm-pill-differ" style="display:inline-flex; align-items:center; gap:4px; font-weight:600; font-size:11px; background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:3px 8px; border-radius:12px;"><span class="dashicons dashicons-no-alt" style="font-size:14px; width:14px; height:14px;"></span> ' + '<?php echo esc_js(__('Differs', 'matchmaker')); ?>' + '</span>';

            html += '    <tr>';
            html += '      <td><strong>' + escapeHtml(crit.label) + '</strong></td>';
            html += '      <td>' + escapeHtml(crit.target_val || '—') + '</td>';
            html += '      <td>' + escapeHtml(crit.candidate_val || '—') + '</td>';
            html += '      <td style="text-align:center;">' + matchBadge + '</td>';
            html += '      <td><small style="color:#64748b;">' + escapeHtml(crit.note || '') + '</small></td>';
            html += '    </tr>';
        });

        html += '  </tbody>';
        html += '</table>';

        // Action Section
        html += '<div class="mm-breakdown-action-bar" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">';
        
        if (matchExists) {
            html += '  <div style="display:flex; align-items:center; gap:10px;">';
            html += '    <span class="dashicons dashicons-warning" style="color:#eab308; font-size:22px; width:22px; height:22px;"></span>';
            html += '    <div>';
            html += '      <strong><?php echo esc_js(__('Match Pair Already Exists', 'matchmaker')); ?></strong>';
            html += '      <div style="font-size:12px; color:#64748b;"><?php echo esc_js(__('Current status:', 'matchmaker')); ?> <span class="mm-status mm-status-' + escapeHtml(existingStatus) + '">' + escapeHtml(existingStatus) + '</span> (Match #' + existingMatchId + ')</div>';
            html += '    </div>';
            html += '  </div>';
            html += '  <div>';
            html += '    <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-matches&match_id=')); ?>' + existingMatchId + '" class="button button-secondary button-large" target="_blank"><?php echo esc_js(__('View Existing Match in Queue &rarr;', 'matchmaker')); ?></a>';
            html += '  </div>';
        } else {
            html += '  <div>';
            html += '    <strong><?php echo esc_js(__('Ready to Match?', 'matchmaker')); ?></strong>';
            html += '    <div style="font-size:12px; color:#64748b;"><?php echo esc_js(__('Creating this match pair will place it into the Matches queue (pending review) for final admin approval.', 'matchmaker')); ?></div>';
            html += '  </div>';
            html += '  <div style="display:flex; align-items:center; gap:10px;">';
            html += '    <span id="mm-direct-create-spinner" class="spinner" style="float:none; margin:0; display:none;"></span>';
            html += '    <button type="button" id="mm-btn-create-direct-match" class="button button-primary button-large" data-target-id="' + t.id + '" data-candidate-id="' + c.id + '" style="font-weight:700; background:#CC723F; border-color:#b55d2c; text-shadow:none;">';
            html += '      <span class="dashicons dashicons-heart" style="margin-top:3px; margin-right:4px;"></span> <?php echo esc_js(__('Create Direct Match Pair', 'matchmaker')); ?>';
            html += '    </button>';
            html += '  </div>';
        }

        html += '</div>';

        breakdownCard.innerHTML = html;

        // Bind Direct Match Button
        var createBtn = document.getElementById('mm-btn-create-direct-match');
        if (createBtn) {
            createBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var tId = parseInt(this.getAttribute('data-target-id'), 10);
                var cId = parseInt(this.getAttribute('data-candidate-id'), 10);
                if (!tId || !cId) return;

                if (!confirm('<?php echo esc_js(__('Are you sure you want to create a direct match pair for these two members?', 'matchmaker')); ?>')) {
                    return;
                }

                var spinner = document.getElementById('mm-direct-create-spinner');
                if (spinner) spinner.style.display = 'inline-block';
                createBtn.disabled = true;

                var ajaxUrl = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.ajax_url) ? matchmakerAdmin.ajax_url : '/wp-admin/admin-ajax.php';
                var nonce   = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.nonce) ? matchmakerAdmin.nonce : '';

                jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'mm_admin_create_direct_match',
                        nonce: nonce,
                        target_user_id: tId,
                        candidate_id: cId
                    },
                    success: function(res) {
                        if (spinner) spinner.style.display = 'none';
                        if (res && res.success && res.data) {
                            var matchId = res.data.match_id || 0;
                            var matchesUrl = res.data.matches_url || 'admin.php?page=matchmaking-matches';
                            
                            breakdownCard.innerHTML = '<div class="notice notice-success inline" style="padding:16px 20px; border-left-color:#10b981; border-radius:6px; margin:0;">' +
                                '<h4 style="margin:0 0 8px 0; color:#065f46; font-size:16px;"><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> ' + escapeHtml(res.data.message || 'Match pair created successfully!') + '</h4>' +
                                '<p style="margin:0 0 14px 0; color:#047857; font-size:13px;"><?php echo esc_js(__('The match is now in pending review status. You can view and approve it in the Matches queue.', 'matchmaker')); ?></p>' +
                                '<div style="display:flex; gap:10px;">' +
                                '  <a href="' + escapeHtml(matchesUrl) + '" class="button button-primary button-large"><?php echo esc_js(__('Go to Matches Queue &rarr;', 'matchmaker')); ?></a>' +
                                '  <button type="button" class="button button-secondary button-large" onclick="window.location.reload()"><?php echo esc_js(__('Match Another Candidate', 'matchmaker')); ?></button>' +
                                '</div>' +
                                '</div>';
                        } else {
                            createBtn.disabled = false;
                            var errMsg = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Failed to create direct match pair.', 'matchmaker')); ?>';
                            alert(errMsg);
                        }
                    },
                    error: function() {
                        if (spinner) spinner.style.display = 'none';
                        createBtn.disabled = false;
                        alert('<?php echo esc_js(__('Server error occurred while creating match pair.', 'matchmaker')); ?>');
                    }
                });
            });
        }
    }
})();
</script>

