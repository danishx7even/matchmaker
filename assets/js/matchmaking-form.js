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
       Single-select custom dropdown binder
    ------------------------------------------------------- */
    function initSingleSelect(wrapper) {
        var select      = wrapper.querySelector('select');
        var display     = wrapper.querySelector('.custom-select-display');
        var searchInput = wrapper.querySelector('.custom-select-search-input');
        var options     = wrapper.querySelectorAll('.custom-select-option');
        if (!select || !display) return;

        function filterOptions() {
            var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
            var visibleCount = 0;
            options.forEach(function (opt) {
                var txt = (opt.textContent || '').toLowerCase();
                if (!q || txt.indexOf(q) !== -1) {
                    opt.style.display = '';
                    visibleCount++;
                } else {
                    opt.style.display = 'none';
                }
            });
            var noRes = wrapper.querySelector('.custom-select-no-results');
            if (visibleCount === 0) {
                if (!noRes) {
                    noRes = document.createElement('div');
                    noRes.className = 'custom-select-no-results';
                    noRes.textContent = 'No matches found';
                    var optsWrap = wrapper.querySelector('.custom-select-options');
                    if (optsWrap) optsWrap.appendChild(noRes);
                }
                noRes.style.display = 'block';
            } else if (noRes) {
                noRes.style.display = 'none';
            }
        }

        display.onclick = function (e) {
            e.stopPropagation();
            var willOpen = !wrapper.classList.contains('open');
            closeAllExcept(wrapper);
            if (willOpen) {
                wrapper.classList.add('open');
                if (searchInput) {
                    searchInput.value = '';
                    filterOptions();
                    setTimeout(function () {
                        searchInput.focus();
                    }, 50);
                }
            } else {
                wrapper.classList.remove('open');
            }
        };

        if (searchInput) {
            searchInput.onclick = function (e) { e.stopPropagation(); };
            searchInput.onmousedown = function (e) { e.stopPropagation(); };
            searchInput.onkeydown = function (e) {
                e.stopPropagation();
                if (e.key === 'Escape') {
                    wrapper.classList.remove('open');
                }
            };
            searchInput.oninput = function () {
                filterOptions();
            };
        }

        options.forEach(function (opt) {
            opt.onclick = function () {
                if (opt.classList.contains('disabled')) return;
                var idx = parseInt(opt.getAttribute('data-index'), 10);
                select.selectedIndex = idx;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                options.forEach(function (o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                display.textContent = opt.textContent;
                display.classList.toggle('placeholder', select.options[idx] && select.options[idx].value === '');
                wrapper.classList.remove('open');
            };
        });
    }

    form.querySelectorAll('.custom-select-wrapper:not(.custom-multiselect-wrapper)').forEach(function (wrapper) {
        initSingleSelect(wrapper);
    });

    /* -------------------------------------------------------
       Dynamic Custom Select Updater Helper
    ------------------------------------------------------- */
    function updateCustomSelect(fieldName, items, selectedValue, defaultPlaceholder) {
        var select = (form ? form.querySelector('select[name="form_fields[' + fieldName + ']"]') : null)
            || document.getElementById('form-field-' + fieldName)
            || document.querySelector('select[name="form_fields[' + fieldName + ']"]')
            || document.querySelector('select[name="' + fieldName + '"]');
        if (!select) return;
        var wrapper = select.closest('.custom-select-wrapper');
        if (!wrapper) return;
        var display = wrapper.querySelector('.custom-select-display');
        var optionsContainer = wrapper.querySelector('.custom-select-options');
        if (!display || !optionsContainer) return;

        var selectHtml = '';
        var optionsHtml = '';
        var matchedIndex = 0;
        var isFound = false;
        var isSearchable = items.length > 5;

        if (isSearchable) {
            optionsContainer.classList.add('has-search');
            optionsHtml += '<div class="custom-select-search-wrap"><input type="text" class="custom-select-search-input" placeholder="Search..." autocomplete="off"></div>';
        } else {
            optionsContainer.classList.remove('has-search');
        }

        items.forEach(function (item, idx) {
            var isPh = /^select\b/i.test(item);
            var optVal = isPh ? '' : item;
            var isSelected = false;

            if (selectedValue && selectedValue !== '' && item.toLowerCase() === selectedValue.toLowerCase()) {
                isSelected = true;
                matchedIndex = idx;
                isFound = true;
            } else if (!isFound && idx === 0) {
                matchedIndex = 0;
            }

            var disAttr = isPh ? ' disabled' : '';
            var selAttr = isSelected ? ' selected' : '';
            selectHtml += '<option value="' + optVal.replace(/"/g, '&quot;') + '"' + disAttr + selAttr + '>' + item + '</option>';

            var selClass = isSelected ? ' selected' : '';
            var disClass = isPh ? ' disabled' : '';
            optionsHtml += '<div class="custom-select-option' + selClass + disClass + '" data-index="' + idx + '">' + item + '</div>';
        });

        select.innerHTML = selectHtml;
        select.selectedIndex = matchedIndex;
        optionsContainer.innerHTML = optionsHtml;

        var currentItem = items[matchedIndex] || defaultPlaceholder || '';
        display.textContent = currentItem;
        var isCurrentPh = /^select\b/i.test(currentItem) || (select.options[matchedIndex] && select.options[matchedIndex].value === '');
        display.classList.toggle('placeholder', isCurrentPh);

        initSingleSelect(wrapper);
    }

    /* -------------------------------------------------------
       Dynamic Location Cascading (Country -> State -> City)
    ------------------------------------------------------- */
    var hierarchyData = window.__mm_hierarchy || null;

    function loadHierarchy(callback) {
        if (hierarchyData) {
            if (callback) callback(hierarchyData);
            return;
        }
        var url = (window.mmfData && window.mmfData.hierarchyUrl) ? window.mmfData.hierarchyUrl : '';
        if (url) {
            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    hierarchyData = data;
                    window.__mm_hierarchy = data;
                    if (callback) callback(hierarchyData);
                })
                .catch(function (err) {
                    console.warn('Matchmaker: could not load location hierarchy JSON', err);
                });
        }
    }

    loadHierarchy(function (data) {
        // Setup Step 1 Cascading
        var userCountrySel = form.querySelector('select[name="form_fields[user_country]"]') || form.querySelector('select[name="form_fields[user_location]"]');
        var userStateSel   = form.querySelector('select[name="form_fields[user_state]"]');
        var userCitySel    = form.querySelector('select[name="form_fields[user_city]"]');

        if (userCountrySel) {
            userCountrySel.addEventListener('change', function () {
                var cVal = userCountrySel.value.trim();
                var states = ['Select state'];
                if (cVal && data[cVal] && typeof data[cVal] === 'object') {
                    var sKeys = Object.keys(data[cVal]).sort(function (a, b) {
                        return a.localeCompare(b, undefined, { sensitivity: 'base' });
                    });
                    states = states.concat(sKeys);
                }
                updateCustomSelect('user_state', states, '', 'Select state');
                updateCustomSelect('user_city', ['Select city'], '', 'Select city');
            });
        }

        if (userStateSel) {
            userStateSel.addEventListener('change', function () {
                var cVal = userCountrySel ? userCountrySel.value.trim() : '';
                var sVal = userStateSel.value.trim();
                var cities = ['Select city'];
                if (cVal && sVal && data[cVal] && data[cVal][sVal] && Array.isArray(data[cVal][sVal])) {
                    var cKeys = data[cVal][sVal].slice().sort(function (a, b) {
                        return a.localeCompare(b, undefined, { sensitivity: 'base' });
                    });
                    cities = cities.concat(cKeys);
                }
                updateCustomSelect('user_city', cities, '', 'Select city');
            });
        }

        // Setup Step 2 Cascading (Preferences)
        var prefCountrySel = form.querySelector('select[name="form_fields[pref_country]"]') || form.querySelector('select[name="form_fields[pref_location]"]');
        var prefStateSel   = form.querySelector('select[name="form_fields[pref_state]"]');
        var prefCitySel    = form.querySelector('select[name="form_fields[pref_city]"]');

        if (prefCountrySel) {
            prefCountrySel.addEventListener('change', function () {
                var pcVal = prefCountrySel.value.trim();
                var prefStates = ['Any State'];
                if (pcVal && pcVal !== 'Any Country' && data[pcVal] && typeof data[pcVal] === 'object') {
                    var psKeys = Object.keys(data[pcVal]).sort(function (a, b) {
                        return a.localeCompare(b, undefined, { sensitivity: 'base' });
                    });
                    prefStates = prefStates.concat(psKeys);
                }
                updateCustomSelect('pref_state', prefStates, 'Any State', 'Any State');
                updateCustomSelect('pref_city', ['Any City'], 'Any City', 'Any City');
            });
        }

        if (prefStateSel) {
            prefStateSel.addEventListener('change', function () {
                var pcVal = prefCountrySel ? prefCountrySel.value.trim() : '';
                var psVal = prefStateSel.value.trim();
                var prefCities = ['Any City'];
                if (pcVal && pcVal !== 'Any Country' && psVal && psVal !== 'Any State' && data[pcVal] && data[pcVal][psVal] && Array.isArray(data[pcVal][psVal])) {
                    var pcKeys = data[pcVal][psVal].slice().sort(function (a, b) {
                        return a.localeCompare(b, undefined, { sensitivity: 'base' });
                    });
                    prefCities = prefCities.concat(pcKeys);
                }
                updateCustomSelect('pref_city', prefCities, 'Any City', 'Any City');
            });
        }
    });

    /* -------------------------------------------------------
       Gender-Based Modesty & Preferred Modesty Synchronization
    ------------------------------------------------------- */
    var modestyConfig = {
        female: {
            self: ['Select preference', 'Full Veil', 'Abaya & Hijab', 'Hijab Only', 'Modest', 'Modern / Casual'],
            pref: ['Select preference', 'Full Veil', 'Abaya & Hijab', 'Hijab Only', 'Modest', 'Modern / Casual', 'No Preference']
        },
        male: {
            self: ['Select preference', 'Traditional', 'Conservative', 'Moderate', 'Casual Modern', 'Trend-focused'],
            pref: ['Select preference', 'Traditional', 'Conservative', 'Moderate', 'Casual Modern', 'Trend-focused', 'No Preference']
        }
    };

    function getUserGender() {
        var checked = (form ? form.querySelector('input[type="radio"][name="form_fields[user_gender]"]:checked') : null)
            || document.querySelector('input[type="radio"][name="form_fields[user_gender]"]:checked')
            || document.querySelector('input[type="radio"][name="user_gender"]:checked');
        if (checked && checked.value) {
            return checked.value.trim().toLowerCase();
        }
        return 'female';
    }

    function getPrefGender() {
        var checked = (form ? form.querySelector('input[type="radio"][name="form_fields[pref_gender]"]:checked') : null)
            || document.querySelector('input[type="radio"][name="form_fields[pref_gender]"]:checked')
            || document.querySelector('input[type="radio"][name="pref_gender"]:checked');
        if (checked && checked.value) {
            return checked.value.trim().toLowerCase();
        }
        var uG = getUserGender();
        return (uG === 'male') ? 'female' : 'male';
    }

    function syncModestyOptionsForUserGender(userGender) {
        var gKey = (userGender && userGender.toLowerCase() === 'male') ? 'male' : 'female';
        var select = document.getElementById('form-field-user_modesty') || (form ? form.querySelector('select[name="form_fields[user_modesty]"]') : null);
        var currentVal = select ? select.value : '';
        var validOptions = modestyConfig[gKey].self;
        var isValid = validOptions.some(function (opt) {
            return opt.toLowerCase() === currentVal.toLowerCase() && !/^select\b/i.test(opt);
        });
        if (!isValid) {
            currentVal = '';
        }
        updateCustomSelect('user_modesty', validOptions, currentVal, 'Select preference');
    }

    function syncModestyOptionsForPrefGender(prefGender) {
        var gKey = (prefGender && prefGender.toLowerCase() === 'male') ? 'male' : 'female';
        var select = document.getElementById('form-field-pref_modesty') || (form ? form.querySelector('select[name="form_fields[pref_modesty]"]') : null);
        var currentVal = select ? select.value : '';
        var validOptions = modestyConfig[gKey].pref;
        var isValid = validOptions.some(function (opt) {
            return opt.toLowerCase() === currentVal.toLowerCase() && !/^select\b/i.test(opt);
        });
        if (!isValid) {
            currentVal = '';
        }
        updateCustomSelect('pref_modesty', validOptions, currentVal, 'Select preference');
    }

    function handleUserGenderChange(newGender) {
        var g = (newGender || '').trim().toLowerCase();
        if (!g) return;
        syncModestyOptionsForUserGender(g);

        // Auto-switch preferred partner gender to the opposite
        var oppositeGender = (g === 'male') ? 'female' : 'male';
        var prefRadios = form ? form.querySelectorAll('input[type="radio"][name="form_fields[pref_gender]"], input[type="radio"][name="pref_gender"]') : [];
        prefRadios.forEach(function (r) {
            if (r.value.toLowerCase() === oppositeGender) {
                r.checked = true;
            } else {
                r.checked = false;
            }
        });
        syncModestyOptionsForPrefGender(oppositeGender);
    }

    function handlePrefGenderChange(newPrefGender) {
        var g = (newPrefGender || '').trim().toLowerCase();
        if (!g) return;
        syncModestyOptionsForPrefGender(g);
    }

    // Direct event listeners on radio buttons & option items
    form.querySelectorAll('input[type="radio"][name="form_fields[user_gender]"], input[type="radio"][name="user_gender"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (this.checked) handleUserGenderChange(this.value);
        });
        radio.addEventListener('click', function () {
            if (this.checked) handleUserGenderChange(this.value);
        });
    });

    form.querySelectorAll('input[type="radio"][name="form_fields[pref_gender]"], input[type="radio"][name="pref_gender"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (this.checked) handlePrefGenderChange(this.value);
        });
        radio.addEventListener('click', function () {
            if (this.checked) handlePrefGenderChange(this.value);
        });
    });

    // Form-level delegated listener for dynamic changes or custom radio clicks
    form.addEventListener('change', function (e) {
        var target = e.target;
        if (!target) return;
        var name = target.getAttribute('name') || '';
        if (name === 'form_fields[user_gender]' || name === 'user_gender') {
            if (target.checked) handleUserGenderChange(target.value);
        } else if (name === 'form_fields[pref_gender]' || name === 'pref_gender') {
            if (target.checked) handlePrefGenderChange(target.value);
        }
    });

    // Option wrapper click fallback (e.g. clicking Elementor span/label)
    form.addEventListener('click', function (e) {
        var opt = e.target.closest('.elementor-field-option');
        if (opt) {
            var radio = opt.querySelector('input[type="radio"]');
            if (radio) {
                setTimeout(function () {
                    if (radio.checked) {
                        var name = radio.getAttribute('name') || '';
                        if (name === 'form_fields[user_gender]' || name === 'user_gender') {
                            handleUserGenderChange(radio.value);
                        } else if (name === 'form_fields[pref_gender]' || name === 'pref_gender') {
                            handlePrefGenderChange(radio.value);
                        }
                    }
                }, 10);
            }
        }
    });

    // jQuery event binding if jQuery is active
    if (window.jQuery) {
        window.jQuery(form).on('change click', 'input[name="form_fields[user_gender]"], input[name="user_gender"]', function () {
            if (this.checked) handleUserGenderChange(this.value);
        });
        window.jQuery(form).on('change click', 'input[name="form_fields[pref_gender]"], input[name="pref_gender"]', function () {
            if (this.checked) handlePrefGenderChange(this.value);
        });
    }

    // Run initial sync on script load
    handleUserGenderChange(getUserGender());
    handlePrefGenderChange(getPrefGender());

    /* -------------------------------------------------------
       Multi-select custom dropdowns
    ------------------------------------------------------- */
    form.querySelectorAll('.custom-multiselect-wrapper').forEach(function (wrapper) {
        var select          = wrapper.querySelector('select');
        var display         = wrapper.querySelector('.custom-select-display');
        var searchInput     = wrapper.querySelector('.custom-select-search-input');
        var placeholderText = display ? (display.getAttribute('data-placeholder') || display.textContent) : '';
        var checkboxes      = wrapper.querySelectorAll('input[type="checkbox"]');
        var checkOptions    = wrapper.querySelectorAll('.custom-select-checkbox-option');
        if (!select || !display) return;

        function filterMultiOptions() {
            var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
            var visibleCount = 0;
            checkOptions.forEach(function (lbl) {
                var txt = (lbl.textContent || '').toLowerCase();
                if (!q || txt.indexOf(q) !== -1) {
                    lbl.style.display = 'flex';
                    visibleCount++;
                } else {
                    lbl.style.display = 'none';
                }
            });
            var noRes = wrapper.querySelector('.custom-select-no-results');
            if (visibleCount === 0) {
                if (!noRes) {
                    noRes = document.createElement('div');
                    noRes.className = 'custom-select-no-results';
                    noRes.textContent = 'No matches found';
                    var optsWrap = wrapper.querySelector('.custom-select-options');
                    if (optsWrap) optsWrap.appendChild(noRes);
                }
                noRes.style.display = 'block';
            } else if (noRes) {
                noRes.style.display = 'none';
            }
        }

        display.addEventListener('click', function (e) {
            e.stopPropagation();
            var willOpen = !wrapper.classList.contains('open');
            closeAllExcept(wrapper);
            if (willOpen) {
                wrapper.classList.add('open');
                if (searchInput) {
                    searchInput.value = '';
                    filterMultiOptions();
                    setTimeout(function () {
                        searchInput.focus();
                    }, 50);
                }
            } else {
                wrapper.classList.remove('open');
            }
        });

        if (searchInput) {
            searchInput.addEventListener('click', function (e) { e.stopPropagation(); });
            searchInput.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            searchInput.addEventListener('keydown', function (e) {
                e.stopPropagation();
                if (e.key === 'Escape') {
                    wrapper.classList.remove('open');
                }
            });
            searchInput.addEventListener('input', function () {
                filterMultiOptions();
            });
        }

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

    function validateStep(stepNumber) {
        var step = form.querySelector('.e-form__step[data-step="' + stepNumber + '"]');
        if (!step) return true;

        var requiredInputs = step.querySelectorAll('input[type="text"], input[type="email"], input[type="date"], select, textarea');
        for (var i = 0; i < requiredInputs.length; i++) {
            var input = requiredInputs[i];
            if (input.type === 'file' || input.classList.contains('custom-select-search-input') || input.hasAttribute('data-ignore-validation') || !input.name || input.disabled) {
                continue;
            }
            if (input.tagName && input.tagName.toLowerCase() === 'select' && input.multiple) {
                if ((!input.selectedOptions || input.selectedOptions.length === 0) && !input.value) {
                    input.focus();
                    var label = input.closest('.elementor-field-group') ? input.closest('.elementor-field-group').querySelector('.elementor-field-label') : null;
                    var fieldName = label ? label.textContent.replace('*', '').trim() : 'field';
                    showMessage('Please provide your ' + fieldName + ' before proceeding.', 'error');
                    return false;
                }
                continue;
            }
            var val = input.value ? input.value.trim() : '';
            if (!val || val === '' || /^select\b/i.test(val)) {
                input.focus();
                var label = input.closest('.elementor-field-group') ? input.closest('.elementor-field-group').querySelector('.elementor-field-label') : null;
                var fieldName = label ? label.textContent.replace('*', '').trim() : 'field';
                showMessage('Please provide your ' + fieldName + ' before proceeding.', 'error');
                return false;
            }
        }

        // Validate Radio buttons (e.g. user_gender / pref_gender)
        var radioGroups = step.querySelectorAll('.elementor-field-subgroup');
        for (var r = 0; r < radioGroups.length; r++) {
            var checked = radioGroups[r].querySelector('input[type="radio"]:checked');
            if (!checked) {
                var rLabel = radioGroups[r].closest('.elementor-field-group').querySelector('.elementor-field-label');
                var rName = rLabel ? rLabel.textContent.replace('*', '').trim() : 'required option';
                showMessage('Please select your ' + rName + '.', 'error');
                return false;
            }
        }

        // Step 1: Validate all 3 photos
        if (stepNumber == 1) {
            for (var p = 1; p <= 3; p++) {
                var pInput = form.querySelector('[name="form_fields[user_photo' + p + ']"]');
                var pBox = pInput ? pInput.closest('.elementor-field-group') : null;
                var hasFile = pInput && pInput.files && pInput.files.length > 0;
                var hasPreview = pBox && pBox.classList.contains('has-preview');
                if (!hasFile && !hasPreview) {
                    showMessage('Photo ' + p + ' is mandatory. Please provide all 3 profile photos.', 'error');
                    return false;
                }
            }
        }

        // Step 2: Validate Age & Height Range min < max
        if (stepNumber == 2) {
            if (!checkAgeRange(false)) {
                return false;
            }
            if (!checkHeightRange(false)) {
                return false;
            }
        }

        return true;
    }

    /* -------------------------------------------------------
       Preferred Age & Height Min / Max Range Validation
    ------------------------------------------------------- */
    var ageMinSelect = form.querySelector('[name="form_fields[preferred_age_min]"]');
    var ageMaxSelect = form.querySelector('[name="form_fields[preferred_age_max]"]');
    var heightMinSelect = form.querySelector('[name="form_fields[preferred_height_min]"]');
    var heightMaxSelect = form.querySelector('[name="form_fields[preferred_height_max]"]');

    function checkAgeRange(silent) {
        if (!ageMinSelect || !ageMaxSelect) return true;
        var minWrap = ageMinSelect.closest('.custom-select-wrapper');
        var maxWrap = ageMaxSelect.closest('.custom-select-wrapper');
        var aMin = parseInt(ageMinSelect.value, 10);
        var aMax = parseInt(ageMaxSelect.value, 10);

        if (!isNaN(aMin) && !isNaN(aMax) && aMin >= aMax) {
            if (minWrap) minWrap.classList.add('has-error');
            if (maxWrap) maxWrap.classList.add('has-error');
            if (!silent) {
                showMessage('Preferred Maximum Age must be higher than Minimum Age.', 'error');
            }
            return false;
        } else {
            if (minWrap) minWrap.classList.remove('has-error');
            if (maxWrap) maxWrap.classList.remove('has-error');
            return true;
        }
    }

    function checkHeightRange(silent) {
        if (!heightMinSelect || !heightMaxSelect) return true;
        var minWrap = heightMinSelect.closest('.custom-select-wrapper');
        var maxWrap = heightMaxSelect.closest('.custom-select-wrapper');
        var minIdx  = heightMinSelect.selectedIndex;
        var maxIdx  = heightMaxSelect.selectedIndex;

        if (minIdx > 0 && maxIdx > 0 && minIdx >= maxIdx) {
            if (minWrap) minWrap.classList.add('has-error');
            if (maxWrap) maxWrap.classList.add('has-error');
            if (!silent) {
                showMessage('Preferred Maximum Height must be higher than Minimum Height.', 'error');
            }
            return false;
        } else {
            if (minWrap) minWrap.classList.remove('has-error');
            if (maxWrap) maxWrap.classList.remove('has-error');
            return true;
        }
    }

    if (ageMinSelect && ageMaxSelect) {
        var onAgeChange = function () {
            if (ageMinSelect.value && ageMaxSelect.value) {
                var valid = checkAgeRange(false);
                if (valid && msgBox && msgBox.textContent.indexOf('Age') !== -1) {
                    showMessage('', '');
                }
            } else {
                checkAgeRange(true);
                if (msgBox && msgBox.textContent.indexOf('Age') !== -1) {
                    showMessage('', '');
                }
            }
        };
        ageMinSelect.addEventListener('change', onAgeChange);
        ageMaxSelect.addEventListener('change', onAgeChange);
    }

    if (heightMinSelect && heightMaxSelect) {
        var onHeightChange = function () {
            if (heightMinSelect.value && heightMaxSelect.value) {
                var valid = checkHeightRange(false);
                if (valid && msgBox && msgBox.textContent.indexOf('Height') !== -1) {
                    showMessage('', '');
                }
            } else {
                checkHeightRange(true);
                if (msgBox && msgBox.textContent.indexOf('Height') !== -1) {
                    showMessage('', '');
                }
            }
        };
        heightMinSelect.addEventListener('change', onHeightChange);
        heightMaxSelect.addEventListener('change', onHeightChange);
    }

    /* -------------------------------------------------------
       Real-Time Error Dismissal on Field Input / Selection
    ------------------------------------------------------- */
    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        function handleFieldInput() {
            var val = el.value ? el.value.trim() : '';
            var isPlaceholder = (!val || /^select\b/i.test(val));

            if (!isPlaceholder || (el.type === 'radio' && el.checked)) {
                var group = el.closest('.elementor-field-group');
                var wrap = el.closest('.custom-select-wrapper');
                if (group) group.classList.remove('has-error');
                if (wrap && el !== ageMinSelect && el !== ageMaxSelect && el !== heightMinSelect && el !== heightMaxSelect) {
                    wrap.classList.remove('has-error');
                }

                // If this field was causing the error message, clear it immediately
                if (msgBox && msgBox.style.display !== 'none') {
                    var label = group ? group.querySelector('.elementor-field-label') : null;
                    var fieldName = label ? label.textContent.replace('*', '').trim() : '';
                    if (fieldName && msgBox.textContent.indexOf(fieldName) !== -1) {
                        showMessage('', '');
                    }
                }
            }
        }

        el.addEventListener('input', handleFieldInput);
        el.addEventListener('change', handleFieldInput);
    });

    // Clear photo error on upload
    form.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (msgBox && msgBox.style.display !== 'none' && msgBox.textContent.indexOf('Photo') !== -1) {
                var allUploaded = true;
                for (var p = 1; p <= 3; p++) {
                    var pInput = form.querySelector('[name="form_fields[user_photo' + p + ']"]');
                    var pBox = pInput ? pInput.closest('.elementor-field-group') : null;
                    var hasFile = pInput && pInput.files && pInput.files.length > 0;
                    var hasPreview = pBox && pBox.classList.contains('has-preview');
                    if (!hasFile && !hasPreview) {
                        allUploaded = false;
                        break;
                    }
                }
                if (allUploaded) {
                    showMessage('', '');
                }
            }
        });
    });

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (!validateStep(1)) {
                return;
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
            msgBox.innerHTML     = '';
            msgBox.className     = 'mmf-form-message';
            return;
        }
        var icon = (type === 'error') ? '⚠️' : '✅';
        msgBox.innerHTML = '<div class="mmf-alert-content">' +
            '<span class="mmf-alert-icon">' + icon + '</span>' +
            '<span class="mmf-alert-text">' + text + '</span>' +
            '</div>';
        msgBox.className = 'mmf-form-message ' + type;
        msgBox.style.display = 'block';
        msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /* -------------------------------------------------------
       AJAX Form Submission
    ------------------------------------------------------- */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!validateStep(1) || !validateStep(2)) {
            return;
        }

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
                var redirectTo = (data.data && data.data.redirect_url)
                    ? data.data.redirect_url
                    : form.getAttribute('data-redirect');

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
