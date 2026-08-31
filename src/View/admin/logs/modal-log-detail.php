<?php
/**
 * View: Admin Log Details Modal Dialog
 *
 * @package Matchmaker\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Log Detail Modal -->
<div id="mm-log-detail-modal" class="mm-modal-overlay" style="display:none;" aria-hidden="true" role="dialog">
    <div class="mm-modal-dialog">
        <div class="mm-modal-header">
            <h3 id="mm-modal-log-title" style="margin:0; font-size:18px; color:#1e293b;"><?php esc_html_e('Log Entry Details', 'matchmaker'); ?></h3>
            <button type="button" class="mm-modal-close" aria-label="<?php esc_attr_e('Close modal', 'matchmaker'); ?>">&times;</button>
        </div>
        <div class="mm-modal-body">
            <div id="mm-modal-meta" style="margin-bottom:15px; font-size:13px; color:#64748b; display:flex; flex-wrap:wrap; gap:12px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
                <!-- Populated via JS -->
            </div>
            
            <div id="mm-modal-message-container" style="margin-bottom:15px;">
                <h4 style="margin:0 0 6px; font-size:13px; text-transform:uppercase; color:#475569;"><?php esc_html_e('Message / Summary', 'matchmaker'); ?></h4>
                <div id="mm-modal-message" style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; font-size:13px; line-height:1.5;"></div>
            </div>

            <div id="mm-modal-email-container" style="margin-bottom:15px; display:none;">
                <h4 style="margin:0 0 6px; font-size:13px; text-transform:uppercase; color:#475569;"><?php esc_html_e('Rendered Email Preview', 'matchmaker'); ?></h4>
                <div id="mm-modal-email-preview" style="background:#fff; padding:15px; border-radius:6px; border:1px solid #cbd5e1; max-height:280px; overflow-y:auto; font-size:13px;"></div>
            </div>

            <div id="mm-modal-payload-container">
                <h4 style="margin:0 0 6px; font-size:13px; text-transform:uppercase; color:#475569;"><?php esc_html_e('Structured Event Metadata (JSON)', 'matchmaker'); ?></h4>
                <pre id="mm-modal-payload" style="background:#0f172a; color:#38bdf8; padding:14px; border-radius:6px; font-size:12px; overflow-x:auto; max-height:240px; margin:0; font-family:monospace; line-height:1.4;"></pre>
            </div>
        </div>
        <div class="mm-modal-footer" style="padding:12px 20px; border-top:1px solid #e2e8f0; text-align:right; background:#f8fafc;">
            <button type="button" class="button button-secondary mm-modal-close"><?php esc_html_e('Close', 'matchmaker'); ?></button>
        </div>
    </div>
</div>
