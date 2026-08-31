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
        <option value=""><?php esc_html_e('All Tiers', 'matchmaker'); ?></option>
        <option value="monthly" <?php selected($tier, 'monthly'); ?>><?php esc_html_e('Monthly', 'matchmaker'); ?></option>
        <option value="one_on_one" <?php selected($tier, 'one_on_one'); ?>><?php esc_html_e('1-on-1 VIP', 'matchmaker'); ?></option>
        <option value="free" <?php selected($tier, 'free'); ?>><?php esc_html_e('Free', 'matchmaker'); ?></option>
        <option value="event" <?php selected($tier, 'event'); ?>><?php esc_html_e('Event', 'matchmaker'); ?></option>
    </select>

    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'matchmaker'); ?>">
</form>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th style="width:50px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Name / Email', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Gender', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Age', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Location', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Tier', 'matchmaker'); ?></th>
            <th><?php esc_html_e('Active Matches', 'matchmaker'); ?></th>
            <th style="width:100px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($candidates)) : ?>
            <tr><td colspan="8"><?php esc_html_e('No candidates found in pool.', 'matchmaker'); ?></td></tr>
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
                    </td>
                    <td><?php echo esc_html(ucfirst($c['gender'] ?? '')); ?></td>
                    <td><?php echo esc_html($age); ?></td>
                    <td><?php echo esc_html($c['location'] ?? '—'); ?></td>
                    <td>
                        <span class="mm-badge mm-badge-<?php echo esc_attr($c['user_type'] ?? 'free'); ?>">
                            <?php echo esc_html($repo->format_tier_label($c['user_type'] ?? 'free')); ?>
                        </span>
                    </td>
                    <td>
                        <span class="mm-count-approved"><?php echo $approved_cnt; ?> <?php esc_html_e('approved', 'matchmaker'); ?></span> / 
                        <span class="mm-count-pending"><?php echo $pending_cnt; ?> <?php esc_html_e('pending', 'matchmaker'); ?></span>
                        <?php if ($has_mutual) : ?>
                            <br><span style="color:#2e7d32;font-size:11px;font-weight:bold;">★ <?php esc_html_e('Mutually Matched', 'matchmaker'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <a href="<?php echo esc_url($view_url); ?>" class="button button-small button-primary">
                            <?php esc_html_e('View', 'matchmaker'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
