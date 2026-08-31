<?php
/**
 * View: Admin Matchmaking Logs, Notifications & Debugger Hub
 *
 * Available variables:
 *   @var string $active_tab ('match_logs', 'notification_logs', 'debugger')
 *   @var array  $tab_data
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$repo = \Matchmaker\Repository\MatchRepository::instance();
$is_test_mode = $repo->is_test_mode();
?>
<div class="wrap mm-admin-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:15px;">
        <div>
            <h1 style="margin:0;"><?php esc_html_e('Match Logs, Notifications & Diagnostics', 'matchmaker'); ?></h1>
            <p class="description" style="margin:4px 0 0;">
                <?php esc_html_e('Audit match lifecycle events, review in-app alerts and transactional email dispatches, and inspect live candidate rejection hard gates.', 'matchmaker'); ?>
            </p>
        </div>
        <div>
            <?php if ($is_test_mode) : ?>
                <span style="background:#fef3c7; color:#92400e; border:1px solid #f59e0b; font-weight:700; padding:6px 14px; border-radius:20px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    🧪 <?php esc_html_e('Test Mode Active', 'matchmaker'); ?>
                </span>
            <?php else : ?>
                <span style="background:#ecfdf5; color:#065f46; border:1px solid #10b981; font-weight:700; padding:6px 14px; border-radius:20px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
                    🛡️ <?php esc_html_e('Live Mode', 'matchmaker'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <h2 class="nav-tab-wrapper mm-admin-nav-tabs" style="margin-bottom:0;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-logs&tab=match_logs')); ?>" class="nav-tab <?php echo ($active_tab === 'match_logs') ? 'nav-tab-active' : ''; ?>">
            📊 <?php esc_html_e('Match Logs', 'matchmaker'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-logs&tab=notification_logs')); ?>" class="nav-tab <?php echo ($active_tab === 'notification_logs') ? 'nav-tab-active' : ''; ?>">
            ✉️ <?php esc_html_e('Notification & Email Logs', 'matchmaker'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-logs&tab=debugger')); ?>" class="nav-tab <?php echo ($active_tab === 'debugger') ? 'nav-tab-active' : ''; ?>">
            🔍 <?php esc_html_e('Candidate Gate Debugger', 'matchmaker'); ?>
        </a>
    </h2>

    <!-- Tab Content Renderers -->
    <?php
    if ($active_tab === 'notification_logs') {
        extract($tab_data, EXTR_SKIP);
        require __DIR__ . '/tab-notification-logs.php';
    } elseif ($active_tab === 'debugger') {
        extract($tab_data, EXTR_SKIP);
        require __DIR__ . '/tab-debugger.php';
    } else {
        extract($tab_data, EXTR_SKIP);
        require __DIR__ . '/tab-match-logs.php';
    }

    // Modal dialog for log inspection
    require __DIR__ . '/modal-log-detail.php';
    ?>
</div>
