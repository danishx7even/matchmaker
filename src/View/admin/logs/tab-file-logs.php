<?php
/**
 * View: Admin Matchmaking System File Logs Tab
 *
 * Available variables:
 *   @var string $download_nonce
 *   @var string $file_logs_nonce
 *   @var array  $info_log_stats
 *   @var array  $error_log_stats
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="mm-card" style="margin-top:20px; background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:20px;">
    <!-- Header & Log Switcher Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:18px;">
        <div>
            <h2 style="margin:0 0 4px; border:0; padding:0; font-size:18px; display:flex; align-items:center; gap:8px;">
                🖥️ <?php esc_html_e('System File Logs (info.log & error.log)', 'matchmaker'); ?>
            </h2>
            <p class="description" style="margin:0;">
                <?php esc_html_e('Inspect real-time physical log files written directly to disk. Tracks background workers, scoring diagnostics, Action Scheduler jobs, and system exceptions.', 'matchmaker'); ?>
            </p>
        </div>

        <!-- Dual Log Switcher Toggle -->
        <div style="display:inline-flex; background:#f1f5f9; padding:4px; border-radius:8px; gap:4px;" id="mm-log-switcher-group" data-nonce="<?php echo esc_attr($file_logs_nonce); ?>">
            <button type="button" class="button mm-log-type-btn active-log-btn" data-log-type="info" style="border-radius:6px; font-weight:600; font-size:13px; background:#CC723F; color:#fff; border:none; padding:6px 16px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                📋 <?php esc_html_e('General Info Log (info.log)', 'matchmaker'); ?>
            </button>
            <button type="button" class="button mm-log-type-btn" data-log-type="error" style="border-radius:6px; font-weight:600; font-size:13px; background:transparent; color:#64748b; border:none; padding:6px 16px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                ⚠️ <?php esc_html_e('Error Log (error.log)', 'matchmaker'); ?>
            </button>
        </div>
    </div>

    <!-- Active Log Meta Bar -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:18px; background:#f8fafc; padding:14px; border-radius:8px; border:1px solid #e2e8f0;">
        <div>
            <span style="font-size:11px; text-transform:uppercase; font-weight:700; color:#94a3b8; display:block;"><?php esc_html_e('File Path', 'matchmaker'); ?></span>
            <code id="mm-log-meta-path" style="font-size:11px; color:#334155; word-break:break-all; background:transparent; padding:0;"><?php echo esc_html($info_log_stats['path'] ?? ''); ?></code>
        </div>
        <div>
            <span style="font-size:11px; text-transform:uppercase; font-weight:700; color:#94a3b8; display:block;"><?php esc_html_e('File Size', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-size" style="font-size:13px; color:#0f172a;"><?php echo esc_html($info_log_stats['size'] ?? '0 B'); ?></strong>
        </div>
        <div>
            <span style="font-size:11px; text-transform:uppercase; font-weight:700; color:#94a3b8; display:block;"><?php esc_html_e('Total Lines', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-lines" style="font-size:13px; color:#0f172a;"><?php echo (int) ($info_log_stats['lines'] ?? 0); ?></strong>
        </div>
        <div>
            <span style="font-size:11px; text-transform:uppercase; font-weight:700; color:#94a3b8; display:block;"><?php esc_html_e('Last Modified', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-mtime" style="font-size:13px; color:#0f172a;"><?php echo esc_html($info_log_stats['mtime'] ?? 'Never'); ?></strong>
        </div>
    </div>

    <!-- Controls & Filter Toolbar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <input type="text" id="mm-log-filter-input" placeholder="<?php esc_attr_e('Filter log entries...', 'matchmaker'); ?>" style="padding:6px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; min-width:240px;">
            
            <label style="font-size:12px; color:#64748b; display:flex; align-items:center; gap:6px;">
                <span><?php esc_html_e('Show:', 'matchmaker'); ?></span>
                <select id="mm-log-max-lines" style="padding:4px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    <option value="100">100 <?php esc_html_e('lines', 'matchmaker'); ?></option>
                    <option value="300" selected>300 <?php esc_html_e('lines', 'matchmaker'); ?></option>
                    <option value="500">500 <?php esc_html_e('lines', 'matchmaker'); ?></option>
                    <option value="1000">1000 <?php esc_html_e('lines', 'matchmaker'); ?></option>
                    <option value="0"><?php esc_html_e('All lines', 'matchmaker'); ?></option>
                </select>
            </label>
            
            <label style="font-size:12px; color:#64748b; display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                <input type="checkbox" id="mm-log-auto-scroll" checked>
                <span><?php esc_html_e('Auto-scroll to bottom', 'matchmaker'); ?></span>
            </label>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" id="mm-btn-refresh-log" class="button" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; font-size:13px;">
                <span class="dashicons dashicons-update" style="margin-top:2px;"></span> <?php esc_html_e('Refresh', 'matchmaker'); ?>
            </button>
            
            <?php 
            $init_download_url = admin_url('admin.php?page=matchmaking-logs&action=download_file_log&log_type=info&_wpnonce=' . $download_nonce);
            ?>
            <a href="<?php echo esc_url($init_download_url); ?>" id="mm-btn-download-log" class="button" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; font-size:13px;">
                <span class="dashicons dashicons-download" style="margin-top:2px;"></span> <?php esc_html_e('Download Log', 'matchmaker'); ?>
            </a>

            <button type="button" id="mm-btn-clear-log" class="button" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; font-size:13px; color:#e11d48; border-color:#fecdd3;">
                <span class="dashicons dashicons-trash" style="margin-top:2px;"></span> <?php esc_html_e('Clear Log', 'matchmaker'); ?>
            </button>
        </div>
    </div>

    <!-- Monospace Terminal Log Viewer -->
    <div style="position:relative;">
        <div id="mm-log-terminal-container" style="background:#0f172a; color:#f8fafc; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; line-height:1.6; padding:16px; border-radius:8px; max-height:550px; overflow-y:auto; margin:0; border:1px solid #1e293b; box-shadow:inset 0 2px 4px rgba(0,0,0,0.3);">
            <div id="mm-log-lines-wrapper"><?php 
                $raw_content = $info_log_stats['content'] ?? '';
                if (empty($raw_content)) {
                    echo '<div style="color:#64748b; font-style:italic; padding:20px 0; text-align:center;">' . esc_html__('Log file is currently empty.', 'matchmaker') . '</div>';
                } else {
                    $lines = explode("\n", $raw_content);
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if ($trimmed === '') continue;
                        $color = '#f8fafc';
                        if (strpos($trimmed, '[ERROR]') !== false) {
                            $color = '#f87171';
                        } elseif (strpos($trimmed, '[WARNING]') !== false) {
                            $color = '#fbbf24';
                        } elseif (strpos($trimmed, '[INFO]') !== false) {
                            $color = '#38bdf8';
                        } elseif (strpos($trimmed, '[DEBUG]') !== false) {
                            $color = '#c084fc';
                        }
                        echo '<div class="mm-log-line" style="color:' . esc_attr($color) . '; border-bottom:1px solid rgba(255,255,255,0.03); padding:2px 0;">' . esc_html($trimmed) . '</div>';
                    }
                }
            ?></div>
        </div>
        
        <div id="mm-log-loading-overlay" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); border-radius:8px; justify-content:center; align-items:center;">
            <div style="color:#fff; font-weight:600; display:flex; align-items:center; gap:8px;">
                <span class="spinner is-active" style="float:none; margin:0;"></span> <?php esc_html_e('Loading log stream...', 'matchmaker'); ?>
            </div>
        </div>
    </div>
</div>
