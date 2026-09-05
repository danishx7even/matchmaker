/**
 * Matchmaker Admin Portal — admin-matchmaker.js
 * Handles matchmaker admin actions, confirm dialogs, and log inspection modal.
 */
(function ($) {
    'use strict';

    function initMatchmakerAdmin() {
        /* Confirm dialog before reject actions via delegated listener */
        $(document).on('click', '.mm-reject-link', function (e) {
            if (!window.confirm('Are you sure you want to reject this match? This cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });

        /* Modal dialog helpers */
        var $modal = $('#mm-log-detail-modal');

        function closeModal() {
            var modalEl = document.getElementById('mm-log-detail-modal');
            if (modalEl) {
                modalEl.style.setProperty('display', 'none', 'important');
                modalEl.setAttribute('aria-hidden', 'true');
            }
        }

        function openModal() {
            var modalEl = document.getElementById('mm-log-detail-modal');
            if (modalEl) {
                modalEl.style.setProperty('display', 'flex', 'important');
                modalEl.setAttribute('aria-hidden', 'false');
            }
        }

        /* Delegated Close modal buttons & backdrop click */
        $(document).on('click', '.mm-modal-close', function (e) {
            e.preventDefault();
            closeModal();
        });

        $(document).on('click', '#mm-log-detail-modal', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                var modalEl = document.getElementById('mm-log-detail-modal');
                if (modalEl && modalEl.style.display !== 'none') {
                    closeModal();
                }
            }
        });

        /* Delegated Log Inspection Click Handler */
        $(document).on('click', '.mm-btn-view-log', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var logId     = $btn.attr('data-log-id') || '';
            var title     = $btn.attr('data-log-title') || ('Log Entry #' + logId);
            var logType   = $btn.attr('data-log-type') || '';
            var evType    = $btn.attr('data-event-type') || '';
            var status    = $btn.attr('data-status') || 'info';
            var created   = $btn.attr('data-created-at') || '';
            var message   = $btn.attr('data-message') || '';
            var recip     = $btn.attr('data-recipient') || '';
            var emailBody = $btn.attr('data-email-body') || '';
            
            // Raw attribute retrieval to prevent double-encoding issues
            var payload = $btn.attr('data-payload') || '';

            // Populate Modal Title
            $('#mm-modal-log-title').text(title + ' (#' + logId + ')');

            // Populate Meta Tags
            var metaHtml = '<span><strong>Type:</strong> ' + escapeHtml(logType) + '</span>' +
                '<span><strong>Event:</strong> <code>' + escapeHtml(evType) + '</code></span>' +
                '<span><strong>Status:</strong> ' + escapeHtml(status.toUpperCase()) + '</span>' +
                '<span><strong>Recorded At:</strong> ' + escapeHtml(created) + '</span>';
            if (recip) {
                metaHtml += '<span><strong>Recipient:</strong> <code>' + escapeHtml(recip) + '</code></span>';
            }
            $('#mm-modal-meta').html(metaHtml);

            // Populate Message
            $('#mm-modal-message').text(message || '—');

            // Populate Rendered Email Preview
            var $emailContainer = $('#mm-modal-email-container');
            var $emailPreview   = $('#mm-modal-email-preview');
            if ($emailContainer.length && $emailPreview.length) {
                if (emailBody && emailBody.trim().length > 0) {
                    $emailPreview.html(emailBody);
                    $emailContainer.show();
                } else {
                    $emailPreview.empty();
                    $emailContainer.hide();
                }
            }

            // Populate JSON Metadata
            var $modalPayload = $('#mm-modal-payload');
            if ($modalPayload.length) {
                if (payload && payload.trim().length > 0) {
                    try {
                        var parsed = (typeof payload === 'object') ? payload : JSON.parse(payload);
                        $modalPayload.text(JSON.stringify(parsed, null, 2));
                    } catch (err) {
                        $modalPayload.text(payload);
                    }
                } else {
                    $modalPayload.text('No additional metadata recorded.');
                }
            }

            openModal();
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize as soon as possible, handling DOM ready and footer execution
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMatchmakerAdmin);
    } else {
        initMatchmakerAdmin();
    }
})(jQuery);
