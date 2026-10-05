/**
 * Cancellation Modal Handler
 * Package: Matchmaker
 */

(function () {
    'use strict';

    var pendingAction = null; // { type: 'link'|'form', target: HTMLElement|string }

    function initCancellationModal() {
        var modal = document.getElementById('mm-cancellation-modal');
        var form = document.getElementById('mm-cancellation-modal-form');
        var keepBtn = document.getElementById('mm-cancel-modal-keep-btn');
        var errorEl = document.getElementById('mm-cancellation-error');
        var detailsInput = document.getElementById('mm-cancellation-details-input');

        // Check if we are on PMPro's cancel confirmation page
        var isCancelConfirmationPage = !!document.querySelector('form#pmpro_cancel_form, form[name="pmpro_cancel_form"], .pmpro_cancel, .pmpro_membership_cancel');

        // If on the confirmation page, populate stored or URL cancellation reasons into the PMPro confirmation form
        if (isCancelConfirmationPage) {
            var confirmForm = document.querySelector('form#pmpro_cancel_form, form[name="pmpro_cancel_form"], form.pmpro_form');
            if (confirmForm) {
                var urlParams = new URLSearchParams(window.location.search);
                var reason = urlParams.get('mm_cancellation_reason') || (window.sessionStorage ? sessionStorage.getItem('mm_cancellation_reason') : '') || '';
                var details = urlParams.get('mm_cancellation_details') || (window.sessionStorage ? sessionStorage.getItem('mm_cancellation_details') : '') || '';

                if (reason) {
                    var rField = confirmForm.querySelector('input[name="mm_cancellation_reason"]');
                    if (!rField) {
                        rField = document.createElement('input');
                        rField.type = 'hidden';
                        rField.name = 'mm_cancellation_reason';
                        confirmForm.appendChild(rField);
                    }
                    rField.value = reason;
                }

                if (details) {
                    var dField = confirmForm.querySelector('input[name="mm_cancellation_details"]');
                    if (!dField) {
                        dField = document.createElement('input');
                        dField.type = 'hidden';
                        dField.name = 'mm_cancellation_details';
                        confirmForm.appendChild(dField);
                    }
                    dField.value = details;
                }

                // Clear sessionStorage after binding to the confirmation form
                confirmForm.addEventListener('submit', function () {
                    if (window.sessionStorage) {
                        sessionStorage.removeItem('mm_cancellation_reason');
                        sessionStorage.removeItem('mm_cancellation_details');
                    }
                });
            }

            // On confirmation page, do not attach modal interceptors to prevent double popup
            return;
        }

        if (!modal || !form) return;

        function openModal(actionObj) {
            pendingAction = actionObj;
            modal.style.display = 'flex';
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
            }
            if (detailsInput) {
                detailsInput.value = '';
            }
            var radios = form.querySelectorAll('input[name="mm_cancellation_reason"]');
            radios.forEach(function (r) {
                r.checked = false;
                if (r.closest('.mm-cancel-radio-option')) {
                    r.closest('.mm-cancel-radio-option').classList.remove('selected');
                }
            });
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.style.display = 'none';
            pendingAction = null;
            document.body.style.overflow = '';
        }

        if (keepBtn) {
            keepBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeModal();
            });
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Highlight selected radio option
        var radioOptions = form.querySelectorAll('.mm-cancel-radio-option');
        radioOptions.forEach(function (opt) {
            opt.addEventListener('click', function () {
                radioOptions.forEach(function (o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                var radio = opt.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
                if (errorEl) errorEl.style.display = 'none';
            });
        });

        // Form submission inside modal
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var selectedRadio = form.querySelector('input[name="mm_cancellation_reason"]:checked');
            var reasonVal = selectedRadio ? selectedRadio.value : '';
            var detailsVal = detailsInput ? detailsInput.value.trim() : '';

            if (!reasonVal) {
                if (errorEl) {
                    errorEl.textContent = 'Please select a reason for cancelling.';
                    errorEl.style.display = 'block';
                }
                return;
            }

            if (detailsVal.length < 5) {
                if (errorEl) {
                    errorEl.textContent = 'Please provide details about your cancellation (at least 5 characters).';
                    errorEl.style.display = 'block';
                }
                if (detailsInput) detailsInput.focus();
                return;
            }

            // Save in sessionStorage as reliable backup for the confirmation step
            if (window.sessionStorage) {
                try {
                    sessionStorage.setItem('mm_cancellation_reason', reasonVal);
                    sessionStorage.setItem('mm_cancellation_details', detailsVal);
                } catch (err) {
                    // ignore storage quota errors
                }
            }

            var confirmBtn = document.getElementById('mm-cancel-modal-confirm-btn');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Processing...';
            }

            if (pendingAction && pendingAction.type === 'link') {
                var url = new URL(pendingAction.target, window.location.origin);
                url.searchParams.set('mm_cancellation_reason', reasonVal);
                url.searchParams.set('mm_cancellation_details', detailsVal);

                window.location.href = url.href;
            } else if (pendingAction && pendingAction.type === 'form') {
                var targetForm = pendingAction.target;

                var hReason = targetForm.querySelector('input[name="mm_cancellation_reason"]');
                if (!hReason) {
                    hReason = document.createElement('input');
                    hReason.type = 'hidden';
                    hReason.name = 'mm_cancellation_reason';
                    targetForm.appendChild(hReason);
                }
                hReason.value = reasonVal;

                var hDetails = targetForm.querySelector('input[name="mm_cancellation_details"]');
                if (!hDetails) {
                    hDetails = document.createElement('input');
                    hDetails.type = 'hidden';
                    hDetails.name = 'mm_cancellation_details';
                    targetForm.appendChild(hDetails);
                }
                hDetails.value = detailsVal;

                targetForm.setAttribute('data-mm-confirmed', 'true');
                targetForm.submit();
            } else {
                closeModal();
            }
        });

        // Intercept cancel links strictly on the Account page CTA
        function attachLinkInterceptors() {
            var cancelLinks = document.querySelectorAll('.pmpro_actionlink-cancel, a[href*="membership-cancel"], a[href*="membership_cancel"], a[href*="cancel"]');
            cancelLinks.forEach(function (link) {
                if (link.getAttribute('data-mm-cancel-bound') === 'true') return;

                // Skip if disabled, hidden, or already cancelled
                if (link.style.display === 'none' ||
                    link.classList.contains('pmpro-base-cancel-disabled') ||
                    link.classList.contains('pmpro-sub-cancelled') ||
                    link.getAttribute('data-mm-cancelled') === 'true') {
                    return;
                }

                // Only intercept cancel links inside account wrappers or with cancel actions
                var href = link.getAttribute('href') || '';
                var isAccountCancel = link.classList.contains('pmpro_actionlink-cancel') ||
                    href.indexOf('cancel') !== -1 ||
                    href.indexOf('membership-cancel') !== -1 ||
                    href.indexOf('membership_cancel') !== -1;

                if (!isAccountCancel) return;

                link.setAttribute('data-mm-cancel-bound', 'true');
                link.addEventListener('click', function (e) {
                    var currentHref = link.getAttribute('href') || '';
                    if (!currentHref || currentHref === '#' || currentHref.indexOf('javascript:') === 0) return;

                    // Double check if subscription was marked cancelled
                    if (link.style.display === 'none' || link.getAttribute('data-mm-cancelled') === 'true') {
                        e.preventDefault();
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation();
                    openModal({ type: 'link', target: currentHref });
                });
            });
        }

        attachLinkInterceptors();
        setTimeout(attachLinkInterceptors, 500);
        setTimeout(attachLinkInterceptors, 1500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCancellationModal);
    } else {
        initCancellationModal();
    }
})();
