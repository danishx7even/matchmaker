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
            var $btn = $('#mm-export-pool-csv-btn');
            if ($btn.length && $btn.attr('data-ajax-url')) {
                return $btn.attr('data-ajax-url');
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
            var $btn = $('#mm-export-pool-csv-btn');
            if ($btn.length && $btn.attr('data-nonce')) {
                return $btn.attr('data-nonce');
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
        function doExportPoolCsv(e) {
            if (e && e.preventDefault) {
                e.preventDefault();
            }

            var $btn     = $('#mm-export-pool-csv-btn');
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
        }

        $(document).on('click', '#mm-export-pool-csv-btn', doExportPoolCsv);
        window.mmTriggerPoolExport = doExportPoolCsv;

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

        window.mmUpdateRecipientCount = updateRecipientCount;

        window.mmToggleTargetMode = function (mode) {
            if (mode === 'members') {
                $('#mm-audience-criteria-wrap').hide();
                $('#mm-audience-members-wrap').show();
            } else {
                $('#mm-audience-members-wrap').hide();
                $('#mm-audience-criteria-wrap').show();
            }
            updateRecipientCount();
        };

        // Toggle audience targeting mode
        $(document).on('change click', 'input[name="mm_target_mode"]', function () {
            var mode = $(this).val();
            window.mmToggleTargetMode(mode);
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

        /* =============================================================
           Responsive Image Lightbox Module
           ============================================================= */
        var lightboxGallery = [];
        var lightboxIndex   = 0;

        function ensureLightboxDom() {
            var existing = document.getElementById('mm-lightbox-modal');
            if (existing) {
                // If modal already exists but is NOT a direct child of body, move it to body
                // This prevents position:fixed from being clipped by #wpwrap overflow
                if (existing.parentNode !== document.body) {
                    document.body.appendChild(existing);
                }
                return;
            }
            // Create fresh and append directly to body so position:fixed works globally
            var modal = document.createElement('div');
            modal.id = 'mm-lightbox-modal';
            modal.className = 'mm-lightbox-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-label', 'Photo Preview');
            modal.innerHTML = [
                '  <div class="mm-lightbox-container">',
                '    <button type="button" class="mm-lightbox-close" aria-label="Close Preview">&times;</button>',
                '    <button type="button" class="mm-lightbox-nav mm-lightbox-prev" aria-label="Previous Image">&#10094;</button>',
                '    <button type="button" class="mm-lightbox-nav mm-lightbox-next" aria-label="Next Image">&#10095;</button>',
                '    <div class="mm-lightbox-img-wrap">',
                '      <img src="" alt="" class="mm-lightbox-img" id="mm-lightbox-target-img">',
                '    </div>',
                '    <div class="mm-lightbox-footer">',
                '      <span class="mm-lightbox-counter" id="mm-lightbox-counter">1 / 1</span>',
                '      <button type="button" class="mm-lightbox-zoom-toggle" id="mm-lightbox-zoom-toggle" title="Toggle Zoom">&#128269;</button>',
                '    </div>',
                '  </div>'
            ].join('');
            document.body.appendChild(modal);
        }


        function updateLightboxView() {
            if (!lightboxGallery.length || lightboxIndex < 0 || lightboxIndex >= lightboxGallery.length) {
                return;
            }
            var item = lightboxGallery[lightboxIndex];
            var $img = $('#mm-lightbox-target-img');
            $img.removeClass('is-zoomed');
            $img.attr('src', item.src);
            $img.attr('alt', item.alt || 'Photo');

            $('#mm-lightbox-counter').text((lightboxIndex + 1) + ' / ' + lightboxGallery.length);

            if (lightboxGallery.length > 1) {
                $('.mm-lightbox-nav').show();
            } else {
                $('.mm-lightbox-nav').hide();
            }
        }

        function openLightbox(clickedEl) {
            ensureLightboxDom();
            lightboxGallery = [];
            lightboxIndex   = 0;

            var $el = $(clickedEl);
            if ($el.is('div') || $el.is('span') || $el.is('aside') || $el.is('figure')) {
                var $innerImg = $el.find('img').first();
                if ($innerImg.length) {
                    $el = $innerImg;
                }
            }
            var src = $el.attr('src') || $el.data('src') || $el.prop('src') || '';
            var gallery = $el.attr('data-mm-lightbox') || '';

            var $container = $el.closest('.mm-photos-grid, .az-about-photo, .main-photo-frame, .candidate-hero-block, .matched-profile-summary-box, .mm-card');
            var $siblings;

            if (gallery) {
                $siblings = $('[data-mm-lightbox="' + gallery + '"]');
            } else if ($container.length) {
                $siblings = $container.find('img');
            } else {
                $siblings = $el;
            }

            $siblings.each(function () {
                var s = $(this).attr('src') || $(this).data('src') || $(this).prop('src') || '';
                if (s) {
                    lightboxGallery.push({
                        src: s,
                        alt: $(this).attr('alt') || 'Photo'
                    });
                }
            });

            if (lightboxGallery.length === 0 && src) {
                lightboxGallery.push({ src: src, alt: 'Photo' });
            }

            for (var i = 0; i < lightboxGallery.length; i++) {
                if (lightboxGallery[i].src === src) {
                    lightboxIndex = i;
                    break;
                }
            }

            updateLightboxView();
            var modalEl = document.getElementById('mm-lightbox-modal');
            if (modalEl) {
                document.body.classList.add('mm-lightbox-open');
                modalEl.style.setProperty('display', 'flex', 'important');
                modalEl.classList.add('is-active');
            }
        }

        function closeLightbox() {
            var modalEl = document.getElementById('mm-lightbox-modal');
            if (modalEl) {
                modalEl.classList.remove('is-active');
                modalEl.style.setProperty('display', 'none', 'important');
            }
            document.body.classList.remove('mm-lightbox-open');
            var $img = $('#mm-lightbox-target-img');
            if ($img.length) {
                $img.removeClass('is-zoomed').attr('src', '');
            }
        }

        function nextLightboxImage() {
            if (lightboxGallery.length <= 1) return;
            lightboxIndex = (lightboxIndex + 1) % lightboxGallery.length;
            updateLightboxView();
        }

        function prevLightboxImage() {
            if (lightboxGallery.length <= 1) return;
            lightboxIndex = (lightboxIndex - 1 + lightboxGallery.length) % lightboxGallery.length;
            updateLightboxView();
        }

        // Expose globally for instant inline or shortcode invocation
        window.MM_openLightbox = openLightbox;
        window.MM_closeLightbox = closeLightbox;

        // Initialize DOM structure early
        ensureLightboxDom();

        // Delegated click listener specifically for profile photos
        $(document).on('click', '.mm-photos-grid img, .az-about-photo img, .main-photo-frame img, .candidate-hero-block img, .matched-profile-summary-box img, .mm-card img, .mm-lightbox-trigger, [data-mm-lightbox]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openLightbox(this);
        });

        $(document).on('click', '.mm-lightbox-close', function (e) {
            e.preventDefault();
            closeLightbox();
        });

        $(document).on('click', '.mm-lightbox-next', function (e) {
            e.preventDefault();
            e.stopPropagation();
            nextLightboxImage();
        });

        $(document).on('click', '.mm-lightbox-prev', function (e) {
            e.preventDefault();
            e.stopPropagation();
            prevLightboxImage();
        });

        $(document).on('click', '#mm-lightbox-modal', function (e) {
            if (e.target === this || $(e.target).hasClass('mm-lightbox-container')) {
                closeLightbox();
            }
        });

        $(document).on('click', '#mm-lightbox-target-img, #mm-lightbox-zoom-toggle', function (e) {
            e.stopPropagation();
            $('#mm-lightbox-target-img').toggleClass('is-zoomed');
        });

        $(document).on('keydown', function (e) {
            var $modal = $('#mm-lightbox-modal');
            if ($modal.length && $modal.hasClass('is-active')) {
                if (e.key === 'Escape') {
                    closeLightbox();
                } else if (e.key === 'ArrowRight') {
                    nextLightboxImage();
                } else if (e.key === 'ArrowLeft') {
                    prevLightboxImage();
                }
            }
        });

        /* =============================================================
           System File Logs Live Interactive Module
           ============================================================= */
        function getFileLogsNonce() {
            var elNonce = $('#mm-log-switcher-group').attr('data-nonce');
            if (elNonce) return elNonce;
            if (typeof matchmakerAdmin !== 'undefined' && matchmakerAdmin.file_logs_nonce) {
                return matchmakerAdmin.file_logs_nonce;
            }
            return getAdminNonce();
        }

        var currentLogType = 'info';
        var cachedRawLog   = '';

        function renderLogLines(rawContent) {
            var $wrapper = $('#mm-log-lines-wrapper');
            if (!$wrapper.length) return;

            if (!rawContent || !rawContent.trim().length) {
                $wrapper.html('<div style="color:#64748b; font-style:italic; padding:20px 0; text-align:center;">Log file is currently empty.</div>');
                return;
            }

            var lines = rawContent.split('\n');
            var filter = ($('#mm-log-filter-input').val() || '').toLowerCase();
            var html = '';

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i];
                if (!line || !line.trim().length) continue;

                if (filter && line.toLowerCase().indexOf(filter) === -1) {
                    continue;
                }

                var color = '#f8fafc';
                if (line.indexOf('[ERROR]') !== -1) {
                    color = '#f87171';
                } else if (line.indexOf('[WARNING]') !== -1) {
                    color = '#fbbf24';
                } else if (line.indexOf('[INFO]') !== -1) {
                    color = '#38bdf8';
                } else if (line.indexOf('[DEBUG]') !== -1) {
                    color = '#c084fc';
                }

                html += '<div class="mm-log-line" style="color:' + color + '; border-bottom:1px solid rgba(255,255,255,0.03); padding:2px 0;">' + escapeHtml(line) + '</div>';
            }

            if (!html.length && filter) {
                html = '<div style="color:#64748b; font-style:italic; padding:20px 0; text-align:center;">No log lines matched the search filter "' + escapeHtml(filter) + '".</div>';
            }

            $wrapper.html(html);

            if ($('#mm-log-auto-scroll').is(':checked')) {
                var container = document.getElementById('mm-log-terminal-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        }

        function loadFileLog(type, maxLines) {
            var $overlay = $('#mm-log-loading-overlay');
            if ($overlay.length) $overlay.css('display', 'flex');

            $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mm_get_file_log',
                    nonce: getFileLogsNonce(),
                    log_type: type,
                    max_lines: maxLines || ($('#mm-log-max-lines').val() || 300)
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success && res.data) {
                        cachedRawLog = res.data.content || '';
                        $('#mm-log-meta-path').text(res.data.path || '');
                        $('#mm-log-meta-size').text(res.data.size || '0 B');
                        $('#mm-log-meta-lines').text(res.data.lines || 0);
                        $('#mm-log-meta-mtime').text(res.data.mtime || 'Never');
                        renderLogLines(cachedRawLog);

                        var $dlBtn = $('#mm-btn-download-log');
                        if ($dlBtn.length) {
                            var currentHref = $dlBtn.attr('href') || '';
                            $dlBtn.attr('href', currentHref.replace(/log_type=[a-z]+/, 'log_type=' + type));
                        }
                    }
                },
                complete: function () {
                    if ($overlay.length) $overlay.hide();
                }
            });
        }

        $(document).on('click', '.mm-log-type-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var type = $btn.attr('data-log-type') || 'info';
            currentLogType = type;

            $('.mm-log-type-btn').css({ background: 'transparent', color: '#64748b', boxShadow: 'none' }).removeClass('active-log-btn');
            $btn.css({ background: '#CC723F', color: '#fff', boxShadow: '0 1px 2px rgba(0,0,0,0.1)' }).addClass('active-log-btn');

            loadFileLog(type);
        });

        $(document).on('input', '#mm-log-filter-input', function () {
            renderLogLines(cachedRawLog);
        });

        $(document).on('change', '#mm-log-max-lines', function () {
            loadFileLog(currentLogType, $(this).val());
        });

        $(document).on('click', '#mm-btn-refresh-log', function (e) {
            e.preventDefault();
            loadFileLog(currentLogType);
        });

        $(document).on('click', '#mm-btn-clear-log', function (e) {
            e.preventDefault();
            if (!window.confirm('Are you sure you want to completely clear ' + currentLogType + '.log? This action cannot be undone.')) {
                return;
            }

            var $overlay = $('#mm-log-loading-overlay');
            if ($overlay.length) $overlay.css('display', 'flex');

            $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mm_clear_file_log',
                    nonce: getFileLogsNonce(),
                    log_type: currentLogType
                },
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        cachedRawLog = '';
                        $('#mm-log-meta-size').text('0 B');
                        $('#mm-log-meta-lines').text('0');
                        $('#mm-log-meta-mtime').text('Just now');
                        renderLogLines('');
                    } else {
                        alert((res && res.data && res.data.message) ? res.data.message : 'Failed to clear log.');
                    }
                },
                complete: function () {
                    if ($overlay.length) $overlay.hide();
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
