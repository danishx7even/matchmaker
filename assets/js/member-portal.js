/**
 * Matchmaker Member Portal — member-portal.js
 * Handles: tab switching, dynamic AJAX tab reloading, 5-state match view navigation,
 * back navigation arrow, Heartbeat API polling (15s), instant bell badge clearing, and toast alerts.
 */
(function () {
    'use strict';

    var lastKnownUnreadCount = -1;
    var toastShownThisSession = false;
    var matchesTabActive = false;
    var stepHistory = [1];

    window.MM_Portal = {

        /**
         * Switch main dashboard tabs (Profile, Matches) with dynamic AJAX reloading.
         */
        switchTab: function (tabName) {
            var tabs   = document.querySelectorAll('.nav-tab[data-tab]');
            var panels = document.querySelectorAll('.portal-tab-panel');

            tabs.forEach(function (t) {
                t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
            });

            panels.forEach(function (p) {
                p.style.display = (p.id === 'mm-tab-' + tabName) ? 'block' : 'none';
            });

            matchesTabActive = (tabName === 'matches');

            if (matchesTabActive) {
                MM_Portal.markNotificationsRead();
            }

            // Dynamically reload tab content via AJAX
            MM_Portal.reloadTabAJAX(tabName);
        },

        /**
         * Fetch updated tab HTML content via AJAX
         */
        reloadTabAJAX: function (tabName, targetStep, page) {
            var panel = document.getElementById('mm-tab-' + tabName);
            if (!panel) return;

            // Completely replace panel content with prominent circular loader while fetching data
            panel.innerHTML = '<div class="mm-tab-loader">' +
                '<div class="mm-tab-spinner"></div>' +
                '<div class="mm-tab-loader-text">Loading...</div>' +
                '</div>';

            var data = new FormData();
            data.append('action', 'mm_reload_tab_content');
            data.append('tab', tabName);
            if (page) {
                data.append('page', page);
            }
            data.append('nonce', (window.mmPortalData && window.mmPortalData.nonce) ? window.mmPortalData.nonce : '');

            var ajaxUrl = (window.mmPortalData && window.mmPortalData.ajaxUrl)
                ? window.mmPortalData.ajaxUrl
                : '/wp-admin/admin-ajax.php';

            return fetch(ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (resData) {
                if (resData.success && resData.data && resData.data.html) {
                    panel.innerHTML = resData.data.html;

                    // Re-bind or navigate step if matches tab
                    if (tabName === 'matches') {
                        if (targetStep) {
                            MM_Portal.navigateStep(targetStep);
                        } else {
                            stepHistory = [1];
                        }
                    }
                } else {
                    panel.innerHTML = '<div class="az-card" style="text-align:center; padding:40px 20px; color:#C2410C;">' +
                        '<p style="font-weight:600; margin-bottom:12px;">Failed to load tab content.</p>' +
                        '<button type="button" class="btn btn-primary" data-mm-action="switch-tab" data-tab="' + tabName + '">Retry</button>' +
                        '</div>';
                }
                return resData;
            })
            .catch(function (err) {
                panel.innerHTML = '<div class="az-card" style="text-align:center; padding:40px 20px; color:#C2410C;">' +
                    '<p style="font-weight:600; margin-bottom:12px;">Network error while loading tab content.</p>' +
                    '<button type="button" class="btn btn-primary" data-mm-action="switch-tab" data-tab="' + tabName + '">Retry</button>' +
                    '</div>';
                throw err;
            });
        },

        /**
         * Navigate between 5-state match views (#step-1 to #step-5)
         */
        navigateStep: function (stepNumber) {
            var views = document.querySelectorAll('.view-state');
            views.forEach(function (v) {
                v.classList.remove('active');
            });

            var target = document.getElementById('step-' + stepNumber);
            if (target) {
                target.classList.add('active');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                stepHistory.push(stepNumber);
            }

            if (stepNumber === 2 || stepNumber === 5) {
                MM_Portal.markNotificationsRead();
            }
        },

        /**
         * Go back to previous step view
         */
        goBackStep: function () {
            if (stepHistory.length > 1) {
                stepHistory.pop(); // remove current
                var prevStep = stepHistory[stepHistory.length - 1];
                
                var views = document.querySelectorAll('.view-state');
                views.forEach(function (v) {
                    v.classList.remove('active');
                });

                var target = document.getElementById('step-' + prevStep);
                if (target) {
                    target.classList.add('active');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
            }

            // Fallback to step 1
            var defaultTarget = document.getElementById('step-1');
            if (defaultTarget) {
                document.querySelectorAll('.view-state').forEach(function (v) {
                    v.classList.remove('active');
                });
                defaultTarget.classList.add('active');
                stepHistory = [1];
            }
        },

        /**
         * Submit AJAX match response (accept / decline)
         */
        submitResponse: function (matchId, responseAction) {
            if (!matchId || !responseAction) return;

            // Show loading state on clicked button
            var clickedBtn = document.querySelector('[data-mm-action="submit-response"][data-decision="' + responseAction + '"]');
            var originalHtml = '';
            if (clickedBtn) {
                originalHtml = clickedBtn.innerHTML;
                clickedBtn.disabled = true;
                clickedBtn.textContent = (responseAction === 'accept') ? 'Processing...' : 'Declining...';
            }

            var data = new FormData();
            data.append('action', 'mm_submit_match_response');
            data.append('match_id', matchId);
            data.append('response_action', responseAction);
            data.append('nonce', (window.mmPortalData && window.mmPortalData.nonce) ? window.mmPortalData.nonce : '');

            var ajaxUrl = (window.mmPortalData && window.mmPortalData.ajaxUrl)
                ? window.mmPortalData.ajaxUrl
                : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (resData) {
                if (resData.success) {
                    MM_Portal.markNotificationsRead();
                    var nextStep = (resData.data && resData.data.next_step) ? resData.data.next_step : 1;
                    // Always reload the matches tab content via AJAX so the view displays fresh updated data from DB
                    MM_Portal.reloadTabAJAX('matches', nextStep);
                } else {
                    if (clickedBtn) {
                        clickedBtn.disabled = false;
                        clickedBtn.innerHTML = originalHtml || 'Submit';
                    }
                    alert(resData.data && resData.data.message ? resData.data.message : 'An error occurred.');
                }
            })
            .catch(function () {
                if (clickedBtn) {
                    clickedBtn.disabled = false;
                    clickedBtn.innerHTML = originalHtml || 'Submit';
                }
                alert('Network error. Please try again.');
            });
        },

        /**
         * Mark all unread notifications as read
         */
        markNotificationsRead: function () {
            var bellBadge = document.querySelector('.mm-bell-badge');
            if (bellBadge) {
                bellBadge.textContent = '0';
                bellBadge.classList.add('mm-hidden');
                bellBadge.style.display = 'none';
            }

            var tabBadge = document.querySelector('.mm-tab-badge');
            if (tabBadge) {
                tabBadge.textContent = '0';
                tabBadge.classList.add('mm-hidden');
                tabBadge.style.display = 'none';
            }

            lastKnownUnreadCount = 0;
            toastShownThisSession = true;

            var data = new FormData();
            data.append('action', 'mm_mark_notifications_read');
            data.append('nonce', (window.mmPortalData && window.mmPortalData.nonce) ? window.mmPortalData.nonce : '');

            var ajaxUrl = (window.mmPortalData && window.mmPortalData.ajaxUrl)
                ? window.mmPortalData.ajaxUrl
                : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).catch(function () {});
        },

        showToast: function () {
            var toast = document.getElementById('mm-toast-box');
            if (!toast) return;

            toast.classList.remove('mm-toast-hidden');
            toast.classList.add('mm-toast-visible');
            toastShownThisSession = true;

            window.clearTimeout(window._mmToastTimer);
            window._mmToastTimer = window.setTimeout(function () {
                MM_Portal.closeToast();
            }, 7000);
        },

        closeToast: function () {
            var toast = document.getElementById('mm-toast-box');
            if (!toast) return;
            toast.classList.remove('mm-toast-visible');
            toast.classList.add('mm-toast-hidden');
        },

        /**
         * Initialize Event Link Modal in DOM if not already present
         */
        initEventLinkModal: function () {
            var existingModal = document.getElementById('mm-event-link-modal');
            if (existingModal) return existingModal;

            var modalHtml = [
                '<div id="mm-event-link-modal" class="mm-modal-overlay" aria-hidden="true" role="dialog" aria-labelledby="mm-event-modal-title">',
                '    <div class="mm-modal-dialog">',
                '        <div class="mm-modal-header">',
                '            <h3 id="mm-event-modal-title" class="mm-modal-title">',
                '                <span class="mm-modal-title-icon">',
                '                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">',
                '                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>',
                '                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>',
                '                    </svg>',
                '                </span>',
                '                Event Link',
                '            </h3>',
                '            <button type="button" class="mm-modal-close-btn" id="mm-modal-close-btn" aria-label="Close modal">&times;</button>',
                '        </div>',
                '        <div class="mm-modal-body">',
                '            <div class="mm-modal-event-name" id="mm-event-modal-name" style="display:none;"></div>',
                '            <div class="mm-event-link-container">',
                '                <input type="text" readonly class="mm-event-link-input" id="mm-event-link-val" value="" placeholder="Loading link...">',
                '                <div class="mm-event-link-actions">',
                '                    <a href="#" target="_blank" rel="noopener noreferrer" class="mm-modal-icon-btn mm-btn-open-link" id="mm-event-open-link-btn" title="Go to Link" aria-label="Go to Link">',
                '                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">',
                '                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
                '                            <polyline points="15 3 21 3 21 9"></polyline>',
                '                            <line x1="10" y1="14" x2="21" y2="3"></line>',
                '                        </svg>',
                '                        <span class="mm-btn-tooltip">Go to Link</span>',
                '                    </a>',
                '                    <a href="#" role="button" class="mm-modal-icon-btn mm-btn-copy-link" id="mm-event-copy-link-btn" title="Copy Link" aria-label="Copy Link">',
                '                        <svg id="mm-copy-icon-svg" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">',
                '                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>',
                '                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>',
                '                        </svg>',
                '                        <span class="mm-btn-tooltip" id="mm-event-copy-tooltip">Copy Link</span>',
                '                    </a>',
                '                </div>',
                '            </div>',
                '            <p class="mm-modal-hint" id="mm-event-modal-hint">Click the external icon to open the link or copy it directly.</p>',
                '        </div>',
                '    </div>',
                '</div>'
            ].join('\n');

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            var modal = document.getElementById('mm-event-link-modal');

            // Close on overlay click
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    MM_Portal.closeEventLinkModal();
                }
            });

            // Close on close button click
            var closeBtn = document.getElementById('mm-modal-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    MM_Portal.closeEventLinkModal();
                });
            }

            // Copy button click
            var copyBtn = document.getElementById('mm-event-copy-link-btn');
            if (copyBtn) {
                copyBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    MM_Portal.copyEventLink();
                });
            }

            // Escape key closes modal
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    MM_Portal.closeEventLinkModal();
                }
            });

            return modal;
        },

        /**
         * Open the custom Event Link modal and record the click.
         *
         * @param {number} eventId Event post ID.
         * @param {string} [initialLink] Immediate link if available in DOM.
         * @param {string} [initialTitle] Event title if available.
         */
        openEventLinkModal: function (eventId, initialLink, initialTitle) {
            var modal = MM_Portal.initEventLinkModal();
            if (!modal) return;

            var linkInput = document.getElementById('mm-event-link-val');
            var openBtn = document.getElementById('mm-event-open-link-btn');
            var nameEl = document.getElementById('mm-event-modal-name');
            var hintEl = document.getElementById('mm-event-modal-hint');

            // Populate initial state
            if (initialTitle) {
                nameEl.textContent = initialTitle;
                nameEl.style.display = 'block';
            } else {
                nameEl.style.display = 'none';
            }

            var cleanInitial = (initialLink && initialLink !== '#' && !initialLink.startsWith('javascript:')) ? initialLink : '';
            if (cleanInitial) {
                linkInput.value = cleanInitial;
                if (openBtn) {
                    openBtn.href = cleanInitial;
                    openBtn.style.pointerEvents = 'auto';
                    openBtn.style.opacity = '1';
                }
                if (hintEl) hintEl.textContent = 'Click the external icon to open the link or copy it directly.';
            } else {
                linkInput.value = 'Loading event link...';
                if (openBtn) {
                    openBtn.href = '#';
                    openBtn.style.pointerEvents = 'none';
                    openBtn.style.opacity = '0.5';
                }
                if (hintEl) hintEl.textContent = 'Retrieving event join details...';
            }

            // Open modal immediately
            modal.classList.add('mm-modal-active');
            modal.setAttribute('aria-hidden', 'false');

            // Record click and fetch full link via AJAX
            if (eventId && eventId > 0) {
                var ajaxUrl = (window.mmPortalData && window.mmPortalData.ajaxUrl)
                    ? window.mmPortalData.ajaxUrl
                    : '/wp-admin/admin-ajax.php';
                var nonce = (window.mmPortalData && window.mmPortalData.nonce)
                    ? window.mmPortalData.nonce
                    : '';

                var data = new FormData();
                data.append('action', 'mm_track_event_click');
                data.append('event_id', eventId);
                data.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    keepalive: true
                })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json && json.success && json.data) {
                        var resolvedLink = json.data.event_link || cleanInitial || '';
                        if (resolvedLink) {
                            linkInput.value = resolvedLink;
                            if (openBtn) {
                                openBtn.href = resolvedLink;
                                openBtn.style.pointerEvents = 'auto';
                                openBtn.style.opacity = '1';
                            }
                            if (hintEl) hintEl.textContent = 'Click the external icon to open the link or copy it directly.';
                        } else {
                            linkInput.value = 'Link not available yet';
                            if (openBtn) {
                                openBtn.href = '#';
                                openBtn.style.pointerEvents = 'none';
                                openBtn.style.opacity = '0.5';
                            }
                            if (hintEl) hintEl.textContent = 'The link for this event will be published closer to the scheduled time.';
                        }

                        if (json.data.event_title && !initialTitle) {
                            nameEl.textContent = json.data.event_title;
                            nameEl.style.display = 'block';
                        }
                    }
                })
                .catch(function () {
                    if (!cleanInitial) {
                        linkInput.value = 'Link not available yet';
                    }
                });
            }
        },

        /**
         * Close Event Link Modal
         */
        closeEventLinkModal: function () {
            var modal = document.getElementById('mm-event-link-modal');
            if (modal) {
                modal.classList.remove('mm-modal-active');
                modal.setAttribute('aria-hidden', 'true');
            }
        },

        /**
         * Copy Event Link to Clipboard
         */
        copyEventLink: function () {
            var linkInput = document.getElementById('mm-event-link-val');
            if (!linkInput || !linkInput.value || linkInput.value.indexOf('http') === -1) {
                return;
            }

            var url = linkInput.value.trim();
            var copyBtn = document.getElementById('mm-event-copy-link-btn');
            var tooltip = document.getElementById('mm-event-copy-tooltip');
            var iconSvg = document.getElementById('mm-copy-icon-svg');

            var showCopiedState = function () {
                if (copyBtn) copyBtn.classList.add('mm-copied');
                if (tooltip) tooltip.textContent = 'Copied! ✓';
                if (iconSvg) {
                    iconSvg.innerHTML = '<polyline points="20 6 9 17 4 12"></polyline>';
                }

                setTimeout(function () {
                    if (copyBtn) copyBtn.classList.remove('mm-copied');
                    if (tooltip) tooltip.textContent = 'Copy Link';
                    if (iconSvg) {
                        iconSvg.innerHTML = '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>';
                    }
                }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(showCopiedState).catch(function () {
                    // Fallback
                    linkInput.select();
                    document.execCommand('copy');
                    showCopiedState();
                });
            } else {
                linkInput.select();
                document.execCommand('copy');
                showCopiedState();
            }
        },

        /**
         * Track "Join Event" click and record click asynchronously in the background.
         *
         * @param {number} eventId Event post ID.
         */
        trackEventClick: function (eventId) {
            if (!eventId || eventId <= 0) {
                return;
            }

            var ajaxUrl = (window.mmPortalData && window.mmPortalData.ajaxUrl)
                ? window.mmPortalData.ajaxUrl
                : '/wp-admin/admin-ajax.php';
            var nonce = (window.mmPortalData && window.mmPortalData.nonce)
                ? window.mmPortalData.nonce
                : '';

            var data = new FormData();
            data.append('action', 'mm_track_event_click');
            data.append('event_id', eventId);
            data.append('nonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        }
    };

    /* Document Ready & Global Initialization */
    function initMemberPortal() {

        // Global Event Delegation Listener for JS Redirections and Actions (replacing inline onclick)
        document.addEventListener('click', function (e) {
            // 1. JS Redirect handling
            var redirectBtn = e.target.closest('[data-mm-redirect]');
            if (redirectBtn) {
                e.preventDefault();
                var targetUrl = redirectBtn.getAttribute('data-mm-redirect');
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
                return;
            }

            // 2. Dynamic Action handling
            var actionBtn = e.target.closest('[data-mm-action]');
            if (actionBtn) {
                e.preventDefault();
                var action = actionBtn.getAttribute('data-mm-action');

                if (action === 'close-toast') {
                    MM_Portal.closeToast();
                } else if (action === 'navigate-step') {
                    var stepNum = parseInt(actionBtn.getAttribute('data-step'), 10);
                    if (stepNum) {
                        MM_Portal.navigateStep(stepNum);
                    }
                } else if (action === 'goback-step') {
                    MM_Portal.goBackStep();
                } else if (action === 'switch-tab') {
                    var tab = actionBtn.getAttribute('data-tab');
                    if (tab) {
                        MM_Portal.switchTab(tab);
                    }
                } else if (action === 'submit-response') {
                    var matchId = actionBtn.getAttribute('data-match-id');
                    var decision = actionBtn.getAttribute('data-decision');
                    if (matchId && decision) {
                        MM_Portal.submitResponse(matchId, decision);
                    }
                } else if (action === 'paginate-events') {
                    var pageNum = parseInt(actionBtn.getAttribute('data-page'), 10) || 1;
                    MM_Portal.reloadTabAJAX('events', null, pageNum);
                }
                return;
            }

            // 3. Event "Join Event" Click Tracking — Open custom Event Link modal popup & save click data
            var joinBtn = e.target.closest('.join-btn, #join-btn, [data-event-action="join"], .mm-event-action-btn');
            if (joinBtn) {
                e.preventDefault();

                var eventCard = joinBtn.closest('[data-event-id]') || joinBtn.querySelector('[data-event-id]');
                var eventId = eventCard ? parseInt(eventCard.getAttribute('data-event-id'), 10) : 0;

                if (!eventId) {
                    var directId = joinBtn.getAttribute('data-event-id');
                    if (directId) {
                        eventId = parseInt(directId, 10);
                    }
                }

                // Resolve immediate link from dataset or child link
                var cardWithLink = joinBtn.closest('[data-event-link]') || joinBtn;
                var initialLink = cardWithLink ? cardWithLink.getAttribute('data-event-link') : '';
                if (!initialLink) {
                    var linkEl = joinBtn.querySelector('a') || (joinBtn.tagName === 'A' ? joinBtn : null);
                    if (linkEl) {
                        initialLink = linkEl.getAttribute('href') || linkEl.href || '';
                    }
                }

                // Resolve event title if available
                var titleEl = eventCard ? eventCard.querySelector('.mm-event-card-title, h2, h3, .elementor-heading-title') : null;
                var initialTitle = titleEl ? titleEl.textContent.trim() : '';

                MM_Portal.openEventLinkModal(eventId, initialLink, initialTitle);
            }
        });

        // 1. Tab click listeners (re-click triggers AJAX reload)
        document.querySelectorAll('.nav-tab[data-tab]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var tab = btn.getAttribute('data-tab');
                MM_Portal.switchTab(tab);
            });
        });

        // 2. Bell icon click listener
        var bellWrapper = document.querySelector('.mm-bell-wrapper');
        if (bellWrapper) {
            bellWrapper.addEventListener('click', function () {
                MM_Portal.switchTab('matches');
            });
        }

        // 3. Initial check
        var initialBadge = document.querySelector('.mm-bell-badge');
        if (initialBadge && !initialBadge.classList.contains('mm-hidden')) {
            var initialCount = parseInt(initialBadge.textContent, 10) || 0;
            if (initialCount > 0) {
                lastKnownUnreadCount = initialCount;
                if (!matchesTabActive) {
                    MM_Portal.showToast();
                }
            } else {
                lastKnownUnreadCount = 0;
            }
        } else {
            lastKnownUnreadCount = 0;
        }

        // 4. WordPress Heartbeat API — 15s interval for Member Portal
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('heartbeat-send', function (e, data) {
                data['mm_poll_notifications'] = true;
            });

            jQuery(document).on('heartbeat-tick', function (e, data) {
                if (typeof data['matchmaker_unread_count'] === 'undefined') return;

                var count = parseInt(data['matchmaker_unread_count'], 10) || 0;

                if (matchesTabActive) {
                    if (count > 0) {
                        MM_Portal.markNotificationsRead();
                    }
                    lastKnownUnreadCount = 0;
                    return;
                }

                var bBadge = document.querySelector('.mm-bell-badge');
                if (bBadge) {
                    bBadge.textContent = count;
                    bBadge.classList.toggle('mm-hidden', count <= 0);
                    bBadge.style.display = count > 0 ? 'flex' : 'none';
                }

                var tBadge = document.querySelector('.mm-tab-badge');
                if (tBadge) {
                    tBadge.textContent = count;
                    tBadge.style.display = count > 0 ? 'inline-block' : 'none';
                    tBadge.classList.toggle('mm-hidden', count <= 0);
                }

                if (count > 0 && count > lastKnownUnreadCount && !toastShownThisSession) {
                    MM_Portal.showToast();
                }

                if (count === 0) {
                    toastShownThisSession = false;
                }

                lastKnownUnreadCount = count;
            });
        }

        // 5. Responsive Lightbox for Member Portal Profile Photos
        var lightboxGallery = [];
        var lightboxIndex   = 0;

        function ensureLightboxDom() {
            if (!document.getElementById('mm-lightbox-modal')) {
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
        }

        function updateLightboxView() {
            if (!lightboxGallery.length || lightboxIndex < 0 || lightboxIndex >= lightboxGallery.length) return;
            var item = lightboxGallery[lightboxIndex];
            var img = document.getElementById('mm-lightbox-target-img');
            if (img) {
                img.classList.remove('is-zoomed');
                img.src = item.src;
                img.alt = item.alt || 'Photo';
            }
            var counter = document.getElementById('mm-lightbox-counter');
            if (counter) {
                counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxGallery.length;
            }
            var navs = document.querySelectorAll('.mm-lightbox-nav');
            navs.forEach(function (n) {
                n.style.display = lightboxGallery.length > 1 ? 'flex' : 'none';
            });
        }

        function openLightbox(el) {
            ensureLightboxDom();
            lightboxGallery = [];
            lightboxIndex = 0;

            if (el && el.tagName !== 'IMG') {
                var innerImg = el.querySelector('img');
                if (innerImg) {
                    el = innerImg;
                }
            }

            var src = (el && (el.getAttribute('src') || el.getAttribute('data-src') || el.src)) || '';
            var gallery = (el && el.getAttribute('data-mm-lightbox')) || '';
            var siblings = [];

            if (gallery) {
                siblings = Array.prototype.slice.call(document.querySelectorAll('[data-mm-lightbox="' + gallery + '"]'));
            } else if (el) {
                var parent = el.closest('.az-about-photo, .mm-photos-grid, .main-photo-frame, .candidate-hero-block, .matched-profile-summary-box, .mm-card');
                if (parent) {
                    siblings = Array.prototype.slice.call(parent.querySelectorAll('img'));
                } else {
                    siblings = [el];
                }
            }

            siblings.forEach(function (node) {
                var s = node.getAttribute('src') || node.getAttribute('data-src') || node.src || '';
                if (s) {
                    lightboxGallery.push({
                        src: s,
                        alt: node.getAttribute('alt') || 'Photo'
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
            var modal = document.getElementById('mm-lightbox-modal');
            if (modal) {
                document.body.classList.add('mm-lightbox-open');
                modal.style.setProperty('display', 'flex', 'important');
                modal.classList.add('is-active');
            }
        }

        function closeLightbox() {
            var modal = document.getElementById('mm-lightbox-modal');
            if (modal) {
                modal.classList.remove('is-active');
                modal.style.setProperty('display', 'none', 'important');
            }
            document.body.classList.remove('mm-lightbox-open');
            var img = document.getElementById('mm-lightbox-target-img');
            if (img) {
                img.classList.remove('is-zoomed');
                img.src = '';
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

        // Expose globally for instant inline / shortcode usage
        window.MM_Portal.openLightbox = openLightbox;
        window.MM_Portal.closeLightbox = closeLightbox;
        window.MM_openLightbox = openLightbox;
        window.MM_closeLightbox = closeLightbox;

        ensureLightboxDom();

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.az-about-photo img, .mm-photos-grid img, .main-photo-frame img, .candidate-hero-block img, .candidate-square-thumb, .matched-profile-summary-box img, .mm-card img, .mm-lightbox-trigger, [data-mm-lightbox]');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                openLightbox(trigger);
                return;
            }

            if (e.target.closest('.mm-lightbox-close')) {
                e.preventDefault();
                closeLightbox();
                return;
            }

            if (e.target.closest('.mm-lightbox-next')) {
                e.preventDefault();
                e.stopPropagation();
                nextLightboxImage();
                return;
            }

            if (e.target.closest('.mm-lightbox-prev')) {
                e.preventDefault();
                e.stopPropagation();
                prevLightboxImage();
                return;
            }

            if (e.target.closest('#mm-lightbox-target-img, #mm-lightbox-zoom-toggle')) {
                e.stopPropagation();
                var targetImg = document.getElementById('mm-lightbox-target-img');
                if (targetImg) {
                    targetImg.classList.toggle('is-zoomed');
                }
                return;
            }

            var modal = document.getElementById('mm-lightbox-modal');
            if (modal && modal.classList.contains('is-active') && (e.target === modal || e.target.classList.contains('mm-lightbox-container'))) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', function (e) {
            var modal = document.getElementById('mm-lightbox-modal');
            if (modal && modal.classList.contains('is-active')) {
                if (e.key === 'Escape') {
                    closeLightbox();
                } else if (e.key === 'ArrowRight') {
                    nextLightboxImage();
                } else if (e.key === 'ArrowLeft') {
                    prevLightboxImage();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMemberPortal);
    } else {
        initMemberPortal();
    }

}());
