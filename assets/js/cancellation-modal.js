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

            var confirmBtn = document.getElementById('mm-cancel-modal-confirm-btn');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Cancelling...';
            }

            if (pendingAction && pendingAction.type === 'form') {
                var targetForm = pendingAction.target;
                
                // Append hidden fields to the target PMPro cancellation form
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

                // Submit without re-triggering interceptor
                targetForm.setAttribute('data-mm-confirmed', 'true');
                targetForm.submit();
            } else if (pendingAction && pendingAction.type === 'link') {
                var url = new URL(pendingAction.target, window.location.origin);
                url.searchParams.set('mm_cancellation_reason', reasonVal);
                url.searchParams.set('mm_cancellation_details', detailsVal);
                
                // Submit via POST form or navigation
                var postForm = document.createElement('form');
                postForm.method = 'POST';
                postForm.action = url.href;

                var rInput = document.createElement('input');
                rInput.type = 'hidden';
                rInput.name = 'mm_cancellation_reason';
                rInput.value = reasonVal;
                postForm.appendChild(rInput);

                var dInput = document.createElement('input');
                dInput.type = 'hidden';
                dInput.name = 'mm_cancellation_details';
                dInput.value = detailsVal;
                postForm.appendChild(dInput);

                document.body.appendChild(postForm);
                postForm.submit();
            } else {
                closeModal();
            }
        });

        // Intercept cancel links on account page
        function attachLinkInterceptors() {
            var cancelLinks = document.querySelectorAll('a[href*="cancel"], a[href*="membership_cancel"], .pmpro_actionlink-cancel');
            cancelLinks.forEach(function (link) {
                if (link.getAttribute('data-mm-cancel-bound') === 'true') return;
                
                // Skip if disabled by active services blockade
                if (link.style.display === 'none' || link.classList.contains('pmpro-base-cancel-disabled')) return;

                link.setAttribute('data-mm-cancel-bound', 'true');
                link.addEventListener('click', function (e) {
                    var href = link.getAttribute('href') || '';
                    if (!href || href === '#' || href.indexOf('javascript:') === 0) return;
                    
                    e.preventDefault();
                    e.stopPropagation();
                    openModal({ type: 'link', target: href });
                });
            });

            // Intercept cancel form on cancel confirmation page
            var cancelForms = document.querySelectorAll('form[action*="cancel"], form#pmpro_cancel_form, form.pmpro_form');
            cancelForms.forEach(function (cf) {
                if (cf.getAttribute('data-mm-cancel-form-bound') === 'true') return;
                
                // Check if this form contains cancel inputs/buttons
                var hasCancelSubmit = cf.querySelector('input[name="membership_cancel"], input[name="cancel"], button[name="membership_cancel"], .pmpro_btn-cancel, input[value*="Cancel"]');
                if (!hasCancelSubmit) return;

                cf.setAttribute('data-mm-cancel-form-bound', 'true');
                cf.addEventListener('submit', function (e) {
                    if (cf.getAttribute('data-mm-confirmed') === 'true') return;
                    
                    e.preventDefault();
                    e.stopPropagation();
                    openModal({ type: 'form', target: cf });
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
