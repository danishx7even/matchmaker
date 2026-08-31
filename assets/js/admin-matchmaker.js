/**
 * Matchmaker Admin Portal — admin-matchmaker.js
 * Handles matchmaker admin actions, confirm dialogs, and log inspection modal.
 */
(function ($) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        /* Confirm dialog before reject actions */
        var rejectLinks = document.querySelectorAll('.mm-reject-link');
        rejectLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (!window.confirm('Are you sure you want to reject this match? This cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        /* Log Detail Modal Viewer */
        var modal = document.getElementById('mm-log-detail-modal');
        if (!modal) {
            return;
        }

        var modalTitle    = document.getElementById('mm-modal-log-title');
        var modalMeta     = document.getElementById('mm-modal-meta');
        var modalMessage  = document.getElementById('mm-modal-message');
        var modalPayload  = document.getElementById('mm-modal-payload');
        var emailContainer= document.getElementById('mm-modal-email-container');
        var emailPreview  = document.getElementById('mm-modal-email-preview');
        var closeButtons  = modal.querySelectorAll('.mm-modal-close');

        function closeModal() {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }

        function openModal() {
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        }

        closeButtons.forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });

        // Delegate inspect log button clicks
        document.querySelectorAll('.mm-btn-view-log').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var ds = btn.dataset;
                var logId    = ds.logId || '';
                var title    = ds.logTitle || 'Log Entry #' + logId;
                var logType  = ds.logType || '';
                var evType   = ds.eventType || '';
                var status   = ds.status || 'info';
                var created  = ds.createdAt || '';
                var message  = ds.message || '';
                var recip    = ds.recipient || '';
                var emailBody= ds.emailBody || '';
                var payload  = ds.payload || '';

                if (modalTitle) {
                    modalTitle.textContent = title + ' (#' + logId + ')';
                }

                if (modalMeta) {
                    var metaHtml = '<span><strong>Type:</strong> ' + escapeHtml(logType) + '</span>' +
                        '<span><strong>Event:</strong> <code>' + escapeHtml(evType) + '</code></span>' +
                        '<span><strong>Status:</strong> ' + escapeHtml(status.toUpperCase()) + '</span>' +
                        '<span><strong>Recorded At:</strong> ' + escapeHtml(created) + '</span>';
                    if (recip) {
                        metaHtml += '<span><strong>Recipient:</strong> <code>' + escapeHtml(recip) + '</code></span>';
                    }
                    modalMeta.innerHTML = metaHtml;
                }

                if (modalMessage) {
                    modalMessage.textContent = message || '—';
                }

                if (emailContainer && emailPreview) {
                    if (emailBody && emailBody.trim().length > 0) {
                        emailPreview.innerHTML = emailBody;
                        emailContainer.style.display = 'block';
                    } else {
                        emailPreview.innerHTML = '';
                        emailContainer.style.display = 'none';
                    }
                }

                if (modalPayload) {
                    if (payload && payload.trim().length > 0) {
                        try {
                            var parsed = JSON.parse(payload);
                            modalPayload.textContent = JSON.stringify(parsed, null, 2);
                        } catch (err) {
                            modalPayload.textContent = payload;
                        }
                    } else {
                        modalPayload.textContent = 'No additional metadata recorded.';
                    }
                }

                openModal();
            });
        });

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
})(jQuery);
