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
        reloadTabAJAX: function (tabName) {
            var panel = document.getElementById('mm-tab-' + tabName);
            if (!panel) return;

            panel.style.opacity = '0.6';

            var data = new FormData();
            data.append('action', 'mm_reload_tab_content');
            data.append('tab', tabName);
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
                panel.style.opacity = '1';
                if (resData.success && resData.data && resData.data.html) {
                    panel.innerHTML = resData.data.html;

                    // Re-bind tab event listeners if needed
                    if (tabName === 'matches') {
                        stepHistory = [1];
                    }
                }
            })
            .catch(function () {
                panel.style.opacity = '1';
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
                    if (responseAction === 'decline') {
                        // Immediately reload the matches tab content via AJAX to display hand-curated queue card
                        MM_Portal.reloadTabAJAX('matches');
                    } else if (resData.data && resData.data.next_step) {
                        MM_Portal.navigateStep(resData.data.next_step);
                    }
                } else {
                    alert(resData.data && resData.data.message ? resData.data.message : 'An error occurred.');
                }
            })
            .catch(function () {
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
        }
    };

    /* Document Ready Initialization */
    document.addEventListener('DOMContentLoaded', function () {

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
                }
                return;
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

        // 4. WordPress Heartbeat API — 15s interval
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
    });

}());
