<?php
/**
 * View: Admin Candidate Pool Browser
 *
 * Available variables:
 *   @var array<int, array<string, mixed>> $candidates
 *   @var string                           $search
 *   @var string                           $gender
 *   @var string                           $tier
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
?>
<h1 class="wp-heading-inline"><?php esc_html_e('Candidate Pool Browser', 'matchmaker'); ?></h1>
<hr class="wp-header-end">

<form method="get" class="mm-filter-bar">
    <input type="hidden" name="page" value="matchmaking-pool">
    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name, email, or location...', 'matchmaker'); ?>" class="regular-text">

    <select name="filter_gender">
        <option value=""><?php esc_html_e('All Genders', 'matchmaker'); ?></option>
        <option value="male" <?php selected($gender, 'male'); ?>><?php esc_html_e('Male', 'matchmaker'); ?></option>
        <option value="female" <?php selected($gender, 'female'); ?>><?php esc_html_e('Female', 'matchmaker'); ?></option>
    </select>

    <select name="filter_tier">
        <option value=""><?php esc_html_e('All Base Tiers', 'matchmaker'); ?></option>
        <option value="monthly" <?php selected($tier, 'monthly'); ?>><?php esc_html_e('Monthly', 'matchmaker'); ?></option>
        <option value="event" <?php selected($tier, 'event'); ?>><?php esc_html_e('Event', 'matchmaker'); ?></option>
        <option value="free" <?php selected($tier, 'free'); ?>><?php esc_html_e('Free', 'matchmaker'); ?></option>
    </select>

    <select name="filter_one_on_one">
        <option value=""><?php esc_html_e('All Services / VIP', 'matchmaker'); ?></option>
        <option value="1" <?php selected($one_on_one ?? '', '1'); ?>><?php esc_html_e('⭐ Has VIP / Service', 'matchmaker'); ?></option>
        <option value="0" <?php selected($one_on_one ?? '', '0'); ?>><?php esc_html_e('No Active Services', 'matchmaker'); ?></option>
    </select>

    <select name="filter_parent_applying">
        <option value=""><?php esc_html_e('All Applications', 'matchmaker'); ?></option>
        <option value="1" <?php selected($parent_applying ?? '', '1'); ?>><?php esc_html_e('👨‍👩‍👧 Parent Applying', 'matchmaker'); ?></option>
        <option value="0" <?php selected($parent_applying ?? '', '0'); ?>><?php esc_html_e('Self Applying', 'matchmaker'); ?></option>
    </select>

    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'matchmaker'); ?>">
    <button type="button" 
            id="mm-export-pool-csv-btn" 
            class="button button-secondary" 
            data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce('mm_admin_nonce')); ?>"
            onclick="if(window.mmTriggerPoolExport){window.mmTriggerPoolExport(event);}else{window.mmFallbackExportPoolCsv && window.mmFallbackExportPoolCsv(this, event);}" 
            style="margin-left: 8px;">
        📥 <?php esc_html_e('Export to CSV', 'matchmaker'); ?>
    </button>
    <span id="mm-export-csv-spinner" class="spinner" style="float:none; margin:0 0 0 6px; vertical-align:middle;"></span>
</form>

<script>
window.mmFallbackExportPoolCsv = function(btn, e) {
    if (e) e.preventDefault();
    var form = btn.closest('form') || document.querySelector('form.mm-filter-bar');
    var s = form ? (form.querySelector('input[name="s"]') ? form.querySelector('input[name="s"]').value : '') : '';
    var gender = form ? (form.querySelector('select[name="filter_gender"]') ? form.querySelector('select[name="filter_gender"]').value : '') : '';
    var tier = form ? (form.querySelector('select[name="filter_tier"]') ? form.querySelector('select[name="filter_tier"]').value : '') : '';
    var oneOnOne = form ? (form.querySelector('select[name="filter_one_on_one"]') ? form.querySelector('select[name="filter_one_on_one"]').value : '') : '';
    var parentApplying = form ? (form.querySelector('select[name="filter_parent_applying"]') ? form.querySelector('select[name="filter_parent_applying"]').value : '') : '';
    
    var ajaxUrl = btn.getAttribute('data-ajax-url') || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var nonce   = btn.getAttribute('data-nonce') || '<?php echo esc_js(wp_create_nonce('mm_admin_nonce')); ?>';
    var spinner = document.getElementById('mm-export-csv-spinner');

    btn.disabled = true;
    if (spinner) spinner.classList.add('is-active');

    var params = new URLSearchParams({
        action: 'mm_export_pool_csv',
        nonce: nonce,
        s: s,
        filter_gender: gender,
        filter_tier: tier,
        filter_one_on_one: oneOnOne,
        filter_parent_applying: parentApplying
    });

    fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res && res.success && res.data && res.data.csv) {
            var blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url  = URL.createObjectURL(blob);
            link.href = url;
            link.download = res.data.filename || 'candidates-export.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } else {
            var msg = (res && res.data && res.data.message) ? res.data.message : 'Export failed.';
            alert(msg);
        }
    })
    .catch(function(err) {
        alert('Export error: ' + err.message);
    })
    .finally(function() {
        btn.disabled = false;
        if (spinner) spinner.classList.remove('is-active');
    });
};
</script>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th style="width:50px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
            <th style="width:20%; min-width:160px;"><?php esc_html_e('Name / Email', 'matchmaker'); ?></th>
            <th style="width:75px;"><?php esc_html_e('Gender', 'matchmaker'); ?></th>
            <th style="width:60px;"><?php esc_html_e('Age', 'matchmaker'); ?></th>
            <th style="width:13%; min-width:110px;"><?php esc_html_e('Location', 'matchmaker'); ?></th>
            <th style="width:14%; min-width:130px;"><?php esc_html_e('Tier & Services', 'matchmaker'); ?></th>
            <th style="width:75px; text-align:center;"><?php esc_html_e('Quota', 'matchmaker'); ?></th>
            <th style="width:14%; min-width:120px;"><?php esc_html_e('Active Matches', 'matchmaker'); ?></th>
            <th style="width:150px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($candidates)) : ?>
            <tr><td colspan="9"><?php esc_html_e('No candidates found in pool.', 'matchmaker'); ?></td></tr>
        <?php else : ?>
            <?php foreach ($candidates as $c) :
                $uid = (int) ($c['user_id'] ?? 0);
                $user_obj = get_userdata($uid);
                $photo = $repo->get_meta($uid, 'user_photo1');
                $age = $repo->calc_age($c['birth_date'] ?? '');
                $view_url = admin_url('admin.php?page=matchmaking-pool&view_user=' . $uid);
                $approved_cnt = (int) ($c['approved_matches'] ?? 0);
                $pending_cnt  = (int) ($c['pending_matches'] ?? 0);
                $has_mutual   = $repo->has_mutual_match_this_month($uid);
                $c_has_vip    = !empty($c['has_one_on_one']) || ($c['user_type'] ?? '') === 'one_on_one' || $repo->has_one_on_one($uid);
            ?>
                <tr>
                    <td>
                        <?php if (!empty($photo)) : ?>
                            <img src="<?php echo esc_url($photo); ?>" style="width:36px;height:36px;border-radius:4px;object-fit:cover;" alt="">
                        <?php else : ?>
                            <div class="mm-avatar-thumb">
                                <?php echo esc_html(strtoupper(substr($user_obj ? $user_obj->display_name : 'U', 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><a href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($user_obj ? $user_obj->display_name : 'User #' . $uid); ?></a></strong><br>
                        <small style="color:#666;"><?php echo esc_html($user_obj ? $user_obj->user_email : ''); ?></small>
                        <?php if (!empty($c['is_parent_applying'])) : ?>
                            <div style="margin-top:4px;">
                                <span class="mm-badge mm-badge-parent">👨‍👩‍👧 <?php esc_html_e('Parent Applying', 'matchmaker'); ?></span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(ucfirst($c['gender'] ?? '')); ?></td>
                    <td><?php echo esc_html($age); ?></td>
                    <?php
                    $c_loc_parts = array_filter([$c['city'] ?? '', $c['state'] ?? '', $c['country'] ?? '']);
                    $c_loc = !empty($c_loc_parts) ? implode(', ', $c_loc_parts) : ($c['location'] ?? '—');
                    ?>
                    <td><?php echo esc_html($c_loc); ?></td>
                    <td>
                        <div class="mm-tier-badges-cell">
                            <span class="mm-badge mm-badge-<?php echo esc_attr($c['user_type'] ?? 'free'); ?>">
                                <?php echo esc_html($repo->format_tier_label($c['user_type'] ?? 'free')); ?>
                            </span>
                            <?php 
                            $user_services = \Matchmaker\Core\PMProSync::instance()->get_user_active_services($uid);
                            if (!empty($user_services)) : 
                            ?>
                                <div class="mm-service-badges-group">
                                    <?php foreach ($user_services as $usrv) : ?>
                                        <span class="mm-badge mm-badge-service">
                                            ★ <?php echo esc_html($usrv['tag']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <?php if (($c['user_type'] ?? 'free') === 'monthly') : 
                            $user_quota = $repo->maybe_reset_monthly_quota($uid);
                            $max_quota  = (int) $repo->get_max_cycle_matches();
                        ?>
                            <span style="font-weight:600; color:#0f172a;"><?php echo $user_quota; ?> / <?php echo $max_quota; ?></span>
                        <?php else : ?>
                            <span style="color:#94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="mm-count-approved"><?php echo $approved_cnt; ?> <?php esc_html_e('approved', 'matchmaker'); ?></span> / 
                        <span class="mm-count-pending"><?php echo $pending_cnt; ?> <?php esc_html_e('pending', 'matchmaker'); ?></span>
                        <?php if ($has_mutual) : ?>
                            <br><span style="color:#2e7d32;font-size:11px;font-weight:bold;">★ <?php esc_html_e('Mutually Matched', 'matchmaker'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <div class="mm-action-btns-wrap" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                            <a href="<?php echo esc_url($view_url); ?>" class="button button-small button-primary">
                                <?php esc_html_e('View', 'matchmaker'); ?>
                            </a>
                            <button type="button" class="button button-small button-secondary mm-open-notes-btn" data-user-id="<?php echo (int) $uid; ?>" data-user-name="<?php echo esc_attr($user_obj ? $user_obj->display_name : 'User #' . $uid); ?>" title="<?php esc_attr_e('View or edit admin notes for this candidate', 'matchmaker'); ?>">
                                📝 <?php esc_html_e('Notes', 'matchmaker'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Admin Notes Modal Popup -->
<div id="mm-admin-notes-modal" class="mm-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mm-notes-modal-title">
    <div class="mm-modal-backdrop mm-close-notes-modal"></div>
    <div class="mm-modal-dialog" style="max-width:650px;">
        <div class="mm-modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-bottom:1px solid #e2e8f0;">
            <h3 id="mm-notes-modal-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                📝 <?php esc_html_e('Admin Notes', 'matchmaker'); ?>: <span id="mm-notes-modal-username" style="color:#CC723F;"></span>
            </h3>
            <button type="button" class="mm-modal-close mm-close-notes-modal" aria-label="<?php esc_attr_e('Close', 'matchmaker'); ?>" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
        </div>
        <div class="mm-modal-body" style="padding:20px;">
            <div id="mm-notes-modal-loading" style="text-align:center; padding:30px; display:none;">
                <span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span>
                <?php esc_html_e('Loading candidate notes...', 'matchmaker'); ?>
            </div>
            <div id="mm-notes-modal-editor-wrap">
                <p class="description" style="margin-top:0; margin-bottom:10px;">
                    <?php esc_html_e('Internal staff notes for this candidate. Visible only to Matchmakers and Administrators.', 'matchmaker'); ?>
                </p>
                <?php
                $modal_editor_id = 'mm_modal_admin_notes_editor';
                $modal_settings  = [
                    'textarea_name' => 'mm_modal_admin_notes',
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                    'tinymce'       => [
                        'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                        'toolbar2' => '',
                    ],
                ];
                wp_editor('', $modal_editor_id, $modal_settings);
                ?>
            </div>
        </div>
        <div class="mm-modal-footer" style="padding:14px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:flex-end; align-items:center; gap:10px;">
            <span id="mm-modal-notes-status" style="font-size:13px; font-weight:600; display:none;"></span>
            <button type="button" class="button button-secondary mm-close-notes-modal">
                <?php esc_html_e('Cancel', 'matchmaker'); ?>
            </button>
            <button type="button" class="button button-primary" id="mm-save-modal-notes-btn" data-user-id="0">
                <?php esc_html_e('Save Notes', 'matchmaker'); ?>
            </button>
        </div>
    </div>
</div>
