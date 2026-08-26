/**
 * Matchmaker Frontend Form — matchmaking-form.js
 * Handles: custom selects, multi-selects, file previews, 2-step navigation, AJAX submit.
 * ajaxUrl is passed via wp_localize_script as window.mmfData.ajaxUrl
 */
(function () {
    'use strict';

    var form = document.getElementById('matchmaking_form');
    if (!form) return;

    var heading1 = document.querySelector('.elementor-element-90afd43');
    var heading2 = document.querySelector('.elementor-element-675d31c');

    /* -------------------------------------------------------
       Close all open custom selects except the given one
    ------------------------------------------------------- */
    function closeAllExcept(except) {
        form.querySelectorAll('.custom-select-wrapper.open').forEach(function (w) {
            if (w !== except) w.classList.remove('open');
        });
    }

    /* -------------------------------------------------------
       Single-select custom dropdowns
    ------------------------------------------------------- */
    form.querySelectorAll('.custom-select-wrapper:not(.custom-multiselect-wrapper)').forEach(function (wrapper) {
        var select  = wrapper.querySelector('select');
        var display = wrapper.querySelector('.custom-select-display');
        var options = wrapper.querySelectorAll('.custom-select-option');
        if (!select || !display) return;

        display.addEventListener('click', function (e) {
            e.stopPropagation();
            closeAllExcept(wrapper);
            wrapper.classList.toggle('open');
        });

        options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                if (opt.classList.contains('disabled')) return;
                var idx = parseInt(opt.getAttribute('data-index'), 10);
                select.selectedIndex = idx;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                options.forEach(function (o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                display.textContent = opt.textContent;
                display.classList.toggle('placeholder', select.options[idx] && select.options[idx].value === '');
                wrapper.classList.remove('open');
            });
        });
    });

    /* -------------------------------------------------------
       Multi-select custom dropdowns
    ------------------------------------------------------- */
    form.querySelectorAll('.custom-multiselect-wrapper').forEach(function (wrapper) {
        var select          = wrapper.querySelector('select');
        var display         = wrapper.querySelector('.custom-select-display');
        var placeholderText = display ? (display.getAttribute('data-placeholder') || display.textContent) : '';
        var checkboxes      = wrapper.querySelectorAll('input[type="checkbox"]');
        if (!select || !display) return;

        display.addEventListener('click', function (e) {
            e.stopPropagation();
            closeAllExcept(wrapper);
            wrapper.classList.toggle('open');
        });

        function updateDisplay() {
            var selected = Array.prototype.filter.call(select.options, function (o) { return o.selected; });
            if (selected.length === 0) {
                display.textContent = placeholderText;
                display.classList.add('placeholder');
                display.removeAttribute('title');
            } else {
                var text = selected.map(function (o) { return o.text; }).join(', ');
                display.textContent = text;
                display.setAttribute('title', text);
                display.classList.remove('placeholder');
            }
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('click', function (e) { e.stopPropagation(); });
            cb.addEventListener('change', function () {
                var idx = parseInt(cb.getAttribute('data-index'), 10);
                if (select.options[idx]) {
                    select.options[idx].selected = cb.checked;
                }
                select.dispatchEvent(new Event('change', { bubbles: true }));
                updateDisplay();
            });
        });

        updateDisplay();
    });

    /* Close all on outside click */
    document.addEventListener('click', function () {
        form.querySelectorAll('.custom-select-wrapper.open').forEach(function (w) {
            w.classList.remove('open');
        });
    });

    /* -------------------------------------------------------
       Photo file preview
    ------------------------------------------------------- */
    form.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var box  = input.closest('.elementor-field-group');
            var file = input.files && input.files[0];
            if (!box || !file) return;
            var old = box.querySelector('.upload-preview-img');
            if (old) old.remove();
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'upload-preview-img';
                box.appendChild(img);
                box.classList.add('has-preview');
            };
            reader.readAsDataURL(file);
        });
    });

    /* -------------------------------------------------------
       2-Step Navigation
    ------------------------------------------------------- */
    var steps      = form.querySelectorAll('.e-form__step');
    var indicators = form.querySelectorAll('.e-form__indicators__indicator');

    function goToStep(stepNumber) {
        steps.forEach(function (s) {
            s.classList.toggle('elementor-hidden', s.getAttribute('data-step') != stepNumber);
        });
        indicators.forEach(function (ind) {
            var n = ind.getAttribute('data-step-indicator');
            ind.classList.toggle('e-form__indicators__indicator--state-active', n == stepNumber);
            ind.classList.toggle('e-form__indicators__indicator--state-inactive', n != stepNumber);
        });
        if (heading1) heading1.style.display = (stepNumber == 1) ? '' : 'none';
        if (heading2) heading2.style.display = (stepNumber == 2) ? '' : 'none';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (heading2) heading2.style.display = 'none';

    var nextBtn = form.querySelector('[data-direction="next"]');
    var prevBtn = form.querySelector('[data-direction="previous"]');

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            var step1    = form.querySelector('.e-form__step[data-step="1"]');
            var required = step1 ? step1.querySelectorAll('[name="form_fields[full_name]"], [name="form_fields[email]"]') : [];
            for (var i = 0; i < required.length; i++) {
                if (!required[i].value.trim()) {
                    required[i].focus();
                    showMessage('Please fill in your name and email before continuing.', 'error');
                    return;
                }
            }
            showMessage('', '');
            goToStep(2);
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            goToStep(1);
        });
    }

    /* -------------------------------------------------------
       Status message helper
    ------------------------------------------------------- */
    var msgBox = form.querySelector('.mmf-form-message');
    function showMessage(text, type) {
        if (!msgBox) return;
        if (!text) {
            msgBox.style.display = 'none';
            msgBox.textContent   = '';
            return;
        }
        msgBox.textContent = text;
        msgBox.className   = 'mmf-form-message ' + type;
    }

    /* -------------------------------------------------------
       AJAX Form Submission
    ------------------------------------------------------- */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('is-loading');
        }
        showMessage('', '');

        var formData = new FormData(form);
        formData.append('action', 'mmf_submit_form');

        var ajaxUrl = (window.mmfData && window.mmfData.ajaxUrl)
            ? window.mmfData.ajaxUrl
            : '/wp-admin/admin-ajax.php';

        fetch(ajaxUrl, {
            method:      'POST',
            body:        formData,
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                showMessage(
                    data.data && data.data.message ? data.data.message : 'Profile updated successfully!',
                    'success'
                );
                var redirectTo = form.getAttribute('data-redirect');
                if (redirectTo) {
                    window.setTimeout(function () {
                        window.location.href = redirectTo;
                    }, 600);
                    return;
                }
            } else {
                showMessage(
                    data.data && data.data.message ? data.data.message : 'Something went wrong. Please try again.',
                    'error'
                );
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-loading');
            }
        })
        .catch(function () {
            showMessage('Network error. Please try again.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-loading');
            }
        });
    });

}());
