<?php
/**
 * View: Admin Matchmaking System File Logs Tab (Redesigned & High-Legibility)
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

$info_lines  = (int) ($info_log_stats['lines'] ?? 0);
$error_lines = (int) ($error_log_stats['lines'] ?? 0);

if (!function_exists('mm_render_log_entry_html')) {
    /**
     * Helper to render a structured log entry card in PHP.
     *
     * @param string $raw_line
     * @param int    $index
     * @return string
     */
    function mm_render_log_entry_html(string $raw_line, int $index = 0): string
    {
        $line = trim($raw_line);
        if ($line === '') {
            return '';
        }

        $timestamp = '';
        $level     = 'INFO';
        $category  = 'GENERAL';
        $message   = $line;
        $context   = '';

        if (preg_match('/^\[(.*?)\]\s*\[(.*?)\]\s*\[(.*?)\]\s*(.*?)(?:\s*\|\s*context:\s*(.*))?$/s', $line, $m)) {
            $timestamp = trim($m[1]);
            $level     = strtoupper(trim($m[2]));
            $category  = strtoupper(trim($m[3]));
            $message   = trim($m[4]);
            $context   = isset($m[5]) ? trim($m[5]) : '';
        } elseif (preg_match('/^\[(.*?)\]\s*\[(.*?)\]\s*(.*?)(?:\s*\|\s*context:\s*(.*))?$/s', $line, $m)) {
            $timestamp = trim($m[1]);
            $level     = strtoupper(trim($m[2]));
            $category  = 'GENERAL';
            $message   = trim($m[3]);
            $context   = isset($m[4]) ? trim($m[4]) : '';
        }

        $level_classes = [
            'INFO'    => 'mm-log-pill-info',
            'WARNING' => 'mm-log-pill-warning',
            'ERROR'   => 'mm-log-pill-error',
            'DEBUG'   => 'mm-log-pill-debug',
        ];
        $level_icons = [
            'INFO'    => 'ℹ️',
            'WARNING' => '⚠️',
            'ERROR'   => '🛑',
            'DEBUG'   => '🔍',
        ];

        $pill_class = $level_classes[$level] ?? 'mm-log-pill-info';
        $icon       = $level_icons[$level] ?? '•';

        $parsed_json = null;
        if (!empty($context)) {
            $decoded = json_decode($context, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsed_json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        $cat_attr   = esc_attr(strtolower($category));
        $level_attr = esc_attr(strtolower($level));

        ob_start();
        ?>
        <div class="mm-log-entry-row <?php echo esc_attr($pill_class); ?>" data-level="<?php echo $level_attr; ?>" data-category="<?php echo $cat_attr; ?>">
            <div class="mm-log-entry-header">
                <div class="mm-log-entry-badges">
                    <span class="mm-log-level-pill <?php echo esc_attr($pill_class); ?>">
                        <span class="mm-log-level-icon"><?php echo $icon; ?></span>
                        <strong><?php echo esc_html($level); ?></strong>
                    </span>
                    <?php if (!empty($category)) : ?>
                        <span class="mm-log-category-badge" title="<?php esc_attr_e('Log Category', 'matchmaker'); ?>">
                            <?php echo esc_html($category); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($timestamp)) : ?>
                        <span class="mm-log-time-badge" title="<?php esc_attr_e('Timestamp', 'matchmaker'); ?>">
                            🕒 <?php echo esc_html($timestamp); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mm-log-entry-body">
                <div class="mm-log-entry-message"><?php echo esc_html($message); ?></div>
                
                <?php if (!empty($parsed_json)) : ?>
                    <details class="mm-log-context-details">
                        <summary class="mm-log-context-summary">
                            <span>📦 <?php esc_html_e('Context Payload (JSON)', 'matchmaker'); ?></span>
                        </summary>
                        <pre class="mm-log-context-json"><?php echo esc_html($parsed_json); ?></pre>
                    </details>
                <?php elseif (!empty($context)) : ?>
                    <div class="mm-log-context-raw">
                        <span style="font-weight:600; color:#64748b;"><?php esc_html_e('Context:', 'matchmaker'); ?></span> <?php echo esc_html($context); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
?>

<div class="mm-system-logs-wrapper" style="margin-top:20px;">
    <!-- Log Switcher Bar -->
    <div class="mm-log-top-bar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div>
            <h2 style="margin:0 0 4px; border:0; padding:0; font-size:19px; font-weight:700; display:flex; align-items:center; gap:8px; color:#0f172a;">
                🖥️ <?php esc_html_e('System File Logs (info.log & error.log)', 'matchmaker'); ?>
            </h2>
            <p class="description" style="margin:0; color:#64748b; font-size:13px;">
                <?php esc_html_e('Dedicated physical disk logs tracking form submissions, profile updates, PMPro checkout sync, background matching workers, email dispatches, and system errors.', 'matchmaker'); ?>
            </p>
        </div>

        <!-- High-contrast Log Switcher Buttons -->
        <div style="display:inline-flex; background:#f1f5f9; padding:5px; border-radius:8px; gap:6px; border:1px solid #e2e8f0;" id="mm-log-switcher-group" data-nonce="<?php echo esc_attr($file_logs_nonce); ?>">
            <button type="button" class="button mm-log-type-btn active-log-btn" data-log-type="info" style="border-radius:6px; font-weight:700; font-size:13px; background:#CC723F; color:#fff; border:none; padding:8px 18px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 2px 4px rgba(204,114,63,0.25); transition:all 0.15s ease;">
                <span>📋 <?php esc_html_e('General Info & Access Log', 'matchmaker'); ?></span>
                <span class="mm-log-btn-counter" id="mm-log-info-badge" style="background:rgba(255,255,255,0.25); padding:2px 8px; border-radius:12px; font-size:11px; font-weight:800;"><?php echo $info_lines; ?></span>
            </button>
            <button type="button" class="button mm-log-type-btn" data-log-type="error" style="border-radius:6px; font-weight:700; font-size:13px; background:transparent; color:#64748b; border:none; padding:8px 18px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.15s ease;">
                <span>⚠️ <?php esc_html_e('Errors & Exceptions Log', 'matchmaker'); ?></span>
                <span class="mm-log-btn-counter" id="mm-log-error-badge" style="<?php echo $error_lines > 0 ? 'background:#ef4444; color:#fff;' : 'background:#e2e8f0; color:#475569;'; ?> padding:2px 8px; border-radius:12px; font-size:11px; font-weight:800;"><?php echo $error_lines; ?></span>
            </button>
        </div>
    </div>

    <!-- Active Log Meta Bar -->
    <div class="mm-log-meta-card" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:14px; margin-bottom:18px; background:#fff; padding:16px 20px; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="border-right:1px solid #f1f5f9; padding-right:12px;">
            <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#94a3b8; display:block; letter-spacing:0.5px;"><?php esc_html_e('Active Log File', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-title" style="font-size:14px; color:#0f172a; display:flex; align-items:center; gap:6px; margin-top:2px;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981;"></span>
                info.log (General Events)
            </strong>
        </div>
        <div style="border-right:1px solid #f1f5f9; padding-right:12px;">
            <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#94a3b8; display:block; letter-spacing:0.5px;"><?php esc_html_e('File Path', 'matchmaker'); ?></span>
            <code id="mm-log-meta-path" style="font-size:11px; color:#475569; word-break:break-all; background:#f8fafc; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0; display:inline-block; margin-top:2px;"><?php echo esc_html($info_log_stats['path'] ?? ''); ?></code>
        </div>
        <div style="border-right:1px solid #f1f5f9; padding-right:12px;">
            <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#94a3b8; display:block; letter-spacing:0.5px;"><?php esc_html_e('File Size', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-size" style="font-size:14px; color:#0f172a; margin-top:2px; display:block;"><?php echo esc_html($info_log_stats['size'] ?? '0 B'); ?></strong>
        </div>
        <div style="border-right:1px solid #f1f5f9; padding-right:12px;">
            <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#94a3b8; display:block; letter-spacing:0.5px;"><?php esc_html_e('Total Entries', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-lines" style="font-size:14px; color:#0f172a; margin-top:2px; display:block;"><?php echo (int) ($info_log_stats['lines'] ?? 0); ?></strong>
        </div>
        <div>
            <span style="font-size:11px; text-transform:uppercase; font-weight:800; color:#94a3b8; display:block; letter-spacing:0.5px;"><?php esc_html_e('Last Modified', 'matchmaker'); ?></span>
            <strong id="mm-log-meta-mtime" style="font-size:14px; color:#0f172a; margin-top:2px; display:block;"><?php echo esc_html($info_log_stats['mtime'] ?? 'Never'); ?></strong>
        </div>
    </div>

    <!-- Controls & Multi-Filter Toolbar -->
    <div class="mm-log-toolbar-card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px; background:#fff; padding:14px 18px; border-radius:10px; border:1px solid #e2e8f0;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <!-- Keyword search -->
            <div style="position:relative;">
                <span class="dashicons dashicons-search" style="position:absolute; left:10px; top:8px; color:#94a3b8; font-size:16px;"></span>
                <input type="text" id="mm-log-filter-input" placeholder="<?php esc_attr_e('Filter by keyword, user ID, email...', 'matchmaker'); ?>" style="padding:6px 12px 6px 32px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; min-width:260px;">
            </div>
            
            <!-- Level Filter -->
            <label style="font-size:12px; color:#475569; font-weight:600; display:flex; align-items:center; gap:6px;">
                <span><?php esc_html_e('Level:', 'matchmaker'); ?></span>
                <select id="mm-log-filter-level" style="padding:4px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    <option value=""><?php esc_html_e('All Levels', 'matchmaker'); ?></option>
                    <option value="info">ℹ️ INFO</option>
                    <option value="warning">⚠️ WARNING</option>
                    <option value="error">🛑 ERROR</option>
                    <option value="debug">🔍 DEBUG</option>
                </select>
            </label>

            <!-- Category Filter -->
            <label style="font-size:12px; color:#475569; font-weight:600; display:flex; align-items:center; gap:6px;">
                <span><?php esc_html_e('Category:', 'matchmaker'); ?></span>
                <select id="mm-log-filter-category" style="padding:4px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    <option value=""><?php esc_html_e('All Categories', 'matchmaker'); ?></option>
                    <option value="form"><?php esc_html_e('FORM (Profile Submissions)', 'matchmaker'); ?></option>
                    <option value="pmpro"><?php esc_html_e('PMPRO (Memberships & Sync)', 'matchmaker'); ?></option>
                    <option value="match_engine"><?php esc_html_e('MATCH_ENGINE (Calculations)', 'matchmaker'); ?></option>
                    <option value="repository"><?php esc_html_e('REPOSITORY (Database)', 'matchmaker'); ?></option>
                    <option value="admin"><?php esc_html_e('ADMIN (Approvals & Actions)', 'matchmaker'); ?></option>
                    <option value="registration"><?php esc_html_e('REGISTRATION (Free Signups)', 'matchmaker'); ?></option>
                    <option value="email"><?php esc_html_e('EMAIL (Dispatches)', 'matchmaker'); ?></option>
                    <option value="general"><?php esc_html_e('GENERAL', 'matchmaker'); ?></option>
                </select>
            </label>

            <!-- Max Lines Selector -->
            <label style="font-size:12px; color:#475569; font-weight:600; display:flex; align-items:center; gap:6px;">
                <span><?php esc_html_e('Show:', 'matchmaker'); ?></span>
                <select id="mm-log-max-lines" style="padding:4px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    <option value="100">100 <?php esc_html_e('entries', 'matchmaker'); ?></option>
                    <option value="300" selected>300 <?php esc_html_e('entries', 'matchmaker'); ?></option>
                    <option value="500">500 <?php esc_html_e('entries', 'matchmaker'); ?></option>
                    <option value="1000">1000 <?php esc_html_e('entries', 'matchmaker'); ?></option>
                    <option value="0"><?php esc_html_e('All entries', 'matchmaker'); ?></option>
                </select>
            </label>
            
            <label style="font-size:12px; color:#64748b; display:inline-flex; align-items:center; gap:4px; cursor:pointer; user-select:none;">
                <input type="checkbox" id="mm-log-auto-scroll" checked>
                <span><?php esc_html_e('Auto-scroll to bottom', 'matchmaker'); ?></span>
            </label>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" id="mm-btn-refresh-log" class="button" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; font-size:13px; font-weight:600;">
                <span class="dashicons dashicons-update" style="margin-top:2px;"></span> <?php esc_html_e('Refresh', 'matchmaker'); ?>
            </button>
            
            <?php 
            $init_download_url = admin_url('admin.php?page=matchmaking-logs&action=download_file_log&log_type=info&_wpnonce=' . $download_nonce);
            ?>
            <a href="<?php echo esc_url($init_download_url); ?>" id="mm-btn-download-log" class="button" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; font-size:13px; font-weight:600;">
                <span class="dashicons dashicons-download" style="margin-top:2px;"></span> <?php esc_html_e('Download Log', 'matchmaker'); ?>
            </a>

            <button type="button" id="mm-btn-clear-log" class="button" style="display:inline-flex; align-items:center; gap:6px; border-radius:6px; font-size:13px; font-weight:600; color:#e11d48; border-color:#fecdd3;">
                <span class="dashicons dashicons-trash" style="margin-top:2px;"></span> <?php esc_html_e('Clear Log', 'matchmaker'); ?>
            </button>
        </div>
    </div>

    <!-- Structured Stream Log Viewer Container -->
    <div style="position:relative;">
        <div id="mm-log-terminal-container" class="mm-log-stream-container">
            <div id="mm-log-lines-wrapper"><?php 
                $raw_content = $info_log_stats['content'] ?? '';
                if (empty(trim($raw_content))) {
                    echo '<div class="mm-log-empty-state"><span class="dashicons dashicons-media-document" style="font-size:36px; width:36px; height:36px; color:#94a3b8;"></span><p>' . esc_html__('Log file is currently empty.', 'matchmaker') . '</p></div>';
                } else {
                    $lines = explode("\n", $raw_content);
                    $idx = 0;
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if ($trimmed === '') continue;
                        echo mm_render_log_entry_html($trimmed, $idx++);
                    }
                }
            ?></div>
        </div>
        
        <div id="mm-log-loading-overlay" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); border-radius:10px; justify-content:center; align-items:center; backdrop-filter:blur(3px); z-index:10;">
            <div style="background:#0f172a; color:#fff; font-weight:600; padding:14px 24px; border-radius:8px; display:flex; align-items:center; gap:10px; box-shadow:0 10px 25px rgba(0,0,0,0.3); border:1px solid #334155;">
                <span class="spinner is-active" style="float:none; margin:0;"></span> <?php esc_html_e('Loading log stream...', 'matchmaker'); ?>
            </div>
        </div>
    </div>
</div>
