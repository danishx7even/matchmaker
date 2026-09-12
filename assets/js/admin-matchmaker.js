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

        function getAjaxUrl() {
            if (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.ajax_url) {
                return matchmakerAdmin.ajax_url;
            }
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }
            return '/wp-admin/admin-ajax.php';
        }

        function getAdminNonce() {
            if (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.nonce) {
                return matchmakerAdmin.nonce;
            }
            var $wpnonce = $('#_wpnonce, input[name="_wpnonce"]');
            if ($wpnonce.length) {
                return $wpnonce.val();
            }
            return '';
        }

        /* Settings Page Tab Switching */
        function switchAdminSettingsTab(tabKey) {
            if (!tabKey) return;
            var cleanKey = String(tabKey).replace(/^#?tab-/, '').replace(/^#/, '').trim();
            if (!cleanKey) return;

            var $targetPanel = $('#mm-panel-' + cleanKey);
            if (!$targetPanel.length) return;

            $('.mm-settings-tab-wrapper a.nav-tab').each(function () {
                var $t = $(this);
                var lKey = ($t.attr('data-tab') || $t.attr('href') || '').replace(/^#?tab-/, '').replace(/^#/, '').trim();
                if (lKey === cleanKey) {
                    $t.addClass('nav-tab-active');
                } else {
                    $t.removeClass('nav-tab-active');
                }
            });

            $('.mm-settings-tab-panel').hide().removeClass('active');
            $targetPanel.css('display', 'block').addClass('active');

            var $submitRow = $('#mm-main-submit-row');
            if ($submitRow.length) {
                $submitRow.toggle(cleanKey !== 'bulk-email');
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, '#tab-' + cleanKey);
            }
        }

        $(document).on('click', '.mm-settings-tab-wrapper a.nav-tab', function (e) {
            e.preventDefault();
            var tabKey = $(this).attr('data-tab') || $(this).attr('href') || '';
            switchAdminSettingsTab(tabKey);
        });

        // Initialize active tab from hash if present
        if (window.location.hash && $('.mm-settings-tab-wrapper').length) {
            switchAdminSettingsTab(window.location.hash);
        }

        $(window).on('hashchange', function () {
            if (window.location.hash && $('.mm-settings-tab-wrapper').length) {
                switchAdminSettingsTab(window.location.hash);
            }
        });

        /* =============================================================
           Candidate Pool CSV Export
           ============================================================= */
        $(document).on('click', '#mm-export-pool-csv-btn', function (e) {
            e.preventDefault();

            var $btn     = $(this);
            var $spinner = $('#mm-export-csv-spinner');
            var $form    = $('form.mm-filter-bar');

            if ($btn.prop('disabled')) {
                return;
            }

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            var postData = {
                action: 'mm_export_pool_csv',
                nonce: getAdminNonce(),
                s: $form.find('input[name="s"]').val() || '',
                filter_gender: $form.find('select[name="filter_gender"]').val() || '',
                filter_tier: $form.find('select[name="filter_tier"]').val() || '',
                filter_one_on_one: $form.find('select[name="filter_one_on_one"]').val() || '',
                filter_parent_applying: $form.find('select[name="filter_parent_applying"]').val() || ''
            };

            $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function (res) {
                    if (res && res.success && res.data && res.data.csv) {
                        try {
                            var blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' });
                            if (window.navigator && window.navigator.msSaveOrOpenBlob) {
                                window.navigator.msSaveOrOpenBlob(blob, res.data.filename || 'candidates-export.csv');
                            } else {
                                var link = document.createElement('a');
                                var url  = URL.createObjectURL(blob);
                                link.setAttribute('href', url);
                                link.setAttribute('download', res.data.filename || 'candidates-export.csv');
                                link.style.visibility = 'hidden';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                            }
                        } catch (err) {
                            alert('Export generation error: ' + err.message);
                        }
                    } else {
                        var errMsg = (res && res.data && res.data.message) ? res.data.message : 'Export failed.';
                        alert(errMsg);
                    }
                },
                error: function (xhr, status, error) {
                    alert('Export request failed: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        /* =============================================================
           Bulk Email Campaign Dispatcher
           ============================================================= */
        var selectedMemberIds = [];

        function getEmailBodyContent() {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('mm_bulk_template') && !tinyMCE.get('mm_bulk_template').isHidden()) {
                return tinyMCE.get('mm_bulk_template').getContent();
            }
            return $('#mm_bulk_template').val() || '';
        }

        var countDebounceTimer = null;
        function updateRecipientCount() {
            clearTimeout(countDebounceTimer);
            countDebounceTimer = setTimeout(function () {
                var targetMode = $('input[name="mm_target_mode"]:checked').val() || 'criteria';

                var postData = {
                    action: 'mm_count_email_recipients',
                    nonce: getAdminNonce(),
                    target_mode: targetMode,
                    tier: $('#mm_bulk_tier').val() || '',
                    service: $('#mm_bulk_service').val() || '',
                    parent_applying: $('#mm_bulk_parent').val() || '',
                    member_ids: selectedMemberIds
                };

                $.post(getAjaxUrl(), postData, function (res) {
                    if (res && res.success && typeof res.data.count !== 'undefined') {
                        $('#mm-recipient-count').text(res.data.count);
                    }
                }, 'json');
            }, 250);
        }

        // Toggle audience targeting mode
        $(document).on('change', 'input[name="mm_target_mode"]', function () {
            var mode = $(this).val();
            if (mode === 'members') {
                $('#mm-audience-criteria-wrap').hide();
                $('#mm-audience-members-wrap').show();
            } else {
                $('#mm-audience-members-wrap').hide();
                $('#mm-audience-criteria-wrap').show();
            }
            updateRecipientCount();
        });

        // Trigger count when criteria change
        $(document).on('change', '#mm_bulk_tier, #mm_bulk_service, #mm_bulk_parent', function () {
            updateRecipientCount();
        });

        // Initialize recipient count on page load if bulk email tab is present
        if ($('#mm-recipient-count').length) {
            updateRecipientCount();
        }

        // Autocomplete search members
        var memberSearchTimer = null;
        $(document).on('input', '#mm_member_search_input', function () {
            var term = $(this).val().trim();
            clearTimeout(memberSearchTimer);

            if (term.length < 1) {
                $('#mm-member-search-results').hide().empty();
                return;
            }

            memberSearchTimer = setTimeout(function () {
                $.post(getAjaxUrl(), {
                    action: 'mm_search_members_for_email',
                    nonce: getAdminNonce(),
                    term: term
                }, function (res) {
                    var $resultsBox = $('#mm-member-search-results');
                    $resultsBox.empty();

                    if (res && res.success && res.data.results && res.data.results.length) {
                        res.data.results.forEach(function (user) {
                            var itemHtml = '<div class="mm-search-item" data-id="' + user.id + '" data-name="' + escapeHtml(user.name) + '" data-email="' + escapeHtml(user.email) + '">' +
                                '<div class="mm-search-name">' + escapeHtml(user.name) + ' (' + escapeHtml(user.email) + ')</div>' +
                                '<div class="mm-search-meta">ID: #' + user.id + ' &bull; Tier: ' + escapeHtml(user.user_type) + '</div>' +
                                '</div>';
                            $resultsBox.append(itemHtml);
                        });
                        $resultsBox.show();
                    } else {
                        $resultsBox.html('<div style="padding:10px; color:#94a3b8; font-size:12px;">No members found.</div>').show();
                    }
                }, 'json');
            }, 300);
        });

        // Select member from search dropdown
        $(document).on('click', '.mm-search-item', function () {
            var $item = $(this);
            var uid   = parseInt($item.attr('data-id'), 10);
            var name  = $item.attr('data-name') || ('User #' + uid);
            var email = $item.attr('data-email') || '';

            if (uid && selectedMemberIds.indexOf(uid) === -1) {
                selectedMemberIds.push(uid);

                var chipHtml = '<span class="mm-member-chip" data-id="' + uid + '">' +
                    escapeHtml(name) + ' (' + escapeHtml(email) + ') ' +
                    '<button type="button" class="mm-remove-chip" data-id="' + uid + '">&times;</button>' +
                    '</span>';

                $('#mm-no-members-msg').hide();
                $('#mm-selected-member-chips').append(chipHtml);
                $('#mm_selected_member_ids').val(selectedMemberIds.join(','));

                updateRecipientCount();
            }

            $('#mm_member_search_input').val('').focus();
            $('#mm-member-search-results').hide().empty();
        });

        // Remove chip
        $(document).on('click', '.mm-remove-chip', function (e) {
            e.preventDefault();
            var uid = parseInt($(this).attr('data-id'), 10);
            selectedMemberIds = selectedMemberIds.filter(function (id) {
                return id !== uid;
            });

            $(this).closest('.mm-member-chip').remove();
            $('#mm_selected_member_ids').val(selectedMemberIds.join(','));

            if (!selectedMemberIds.length) {
                $('#mm-no-members-msg').show();
            }

            updateRecipientCount();
        });

        // Close search results dropdown on outside click
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#mm-audience-members-wrap').length) {
                $('#mm-member-search-results').hide();
            }
        });

        // Insert placeholder into email editor
        $(document).on('click', '.mm-placeholder-pill', function (e) {
            e.preventDefault();
            var ph = $(this).attr('data-placeholder') || '';
            if (!ph) return;

            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('mm_bulk_template') && !tinyMCE.get('mm_bulk_template').isHidden()) {
                tinyMCE.get('mm_bulk_template').execCommand('mceInsertContent', false, ph);
            } else {
                var $textarea = $('#mm_bulk_template');
                var val = $textarea.val() || '';
                var start = $textarea.prop('selectionStart') || val.length;
                var end = $textarea.prop('selectionEnd') || val.length;
                var newVal = val.substring(0, start) + ph + val.substring(end);
                $textarea.val(newVal);
                $textarea.prop('selectionStart', start + ph.length);
                $textarea.prop('selectionEnd', start + ph.length);
                $textarea.focus();
            }
        });

        // Send Bulk Email action
        $(document).on('click', '#mm-send-bulk-email-btn', function (e) {
            e.preventDefault();

            var confirmMsg = (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.strings && matchmakerAdmin.strings.confirm_send)
                ? matchmakerAdmin.strings.confirm_send
                : 'Are you sure you want to queue this bulk email campaign?';

            if (!window.confirm(confirmMsg)) {
                return;
            }

            var $btn     = $(this);
            var $spinner = $('#mm-bulk-email-spinner');
            var $notice  = $('#mm-bulk-email-notice');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $notice.hide().empty();

            var targetMode = $('input[name="mm_target_mode"]:checked').val() || 'criteria';
            var subject    = $('#mm_bulk_subject').val() || '';
            var body       = getEmailBodyContent();

            var postData = {
                action: 'mm_send_bulk_email',
                nonce: getAdminNonce(),
                target_mode: targetMode,
                tier: $('#mm_bulk_tier').val() || '',
                service: $('#mm_bulk_service').val() || '',
                parent_applying: $('#mm_bulk_parent').val() || '',
                member_ids: selectedMemberIds,
                subject: subject,
                template: body
            };

            $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        $notice.html('<div class="notice notice-success inline" style="padding:10px 14px; margin:0;"><p style="margin:0; font-weight:600;">' + escapeHtml(res.data.message) + '</p></div>').fadeIn();
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'Failed to queue bulk emails.';
                        $notice.html('<div class="notice notice-error inline" style="padding:10px 14px; margin:0;"><p style="margin:0; font-weight:600;">' + escapeHtml(msg) + '</p></div>').fadeIn();
                    }
                },
                error: function (xhr, status, error) {
                    $notice.html('<div class="notice notice-error inline" style="padding:10px 14px; margin:0;"><p style="margin:0; font-weight:600;">Request failed: ' + escapeHtml(error) + '</p></div>').fadeIn();
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Save as Default Template action
        $(document).on('click', '#mm-save-default-bulk-email-btn', function (e) {
            e.preventDefault();

            var $btn     = $(this);
            var $spinner = $('#mm-bulk-email-spinner');
            var $notice  = $('#mm-bulk-email-notice');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $notice.hide().empty();

            var subject = $('#mm_bulk_subject').val() || '';
            var body    = getEmailBodyContent();

            $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mm_save_bulk_email_default',
                    nonce: getAdminNonce(),
                    subject: subject,
                    template: body
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        $notice.html('<div class="notice notice-success inline" style="padding:10px 14px; margin:0;"><p style="margin:0; font-weight:600;">' + escapeHtml(res.data.message) + '</p></div>').fadeIn();
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'Failed to save template.';
                        $notice.html('<div class="notice notice-error inline" style="padding:10px 14px; margin:0;"><p style="margin:0; font-weight:600;">' + escapeHtml(msg) + '</p></div>').fadeIn();
                    }
                },
                error: function (xhr, status, error) {
                    $notice.html('<div class="notice notice-error inline" style="padding:10px 14px; margin:0;"><p style="margin:0; font-weight:600;">Request failed: ' + escapeHtml(error) + '</p></div>').fadeIn();
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
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
