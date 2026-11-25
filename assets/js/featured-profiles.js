/**
 * Featured Profiles — Frontend Application & Member Portal Toggle Interactions
 *
 * @package Matchmaker
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // =========================================================================
        // 1. "Apply for a Match" CTA Button Interaction
        // =========================================================================
        $(document).on('click', '.mm-btn-apply-match', function (e) {
            e.preventDefault();

            var $btn = $(this);
            if ($btn.prop('disabled') || $btn.hasClass('is-loading')) {
                return;
            }

            var config = window.mmFeaturedConfig || {};
            if (!config.is_logged_in) {
                window.location.href = config.login_url || '/login/';
                return;
            }

            var targetUserId = $btn.data('target-id');
            var postId       = $btn.data('post-id');

            if (!confirm('Are you sure you want to apply for a match with this member?')) {
                return;
            }

            var originalText = $btn.html();
            $btn.addClass('is-loading').prop('disabled', true).html('⏳ ' + (config.i18n ? config.i18n.submitting : 'Submitting...'));

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mm_apply_featured_match',
                    nonce: config.apply_nonce,
                    target_user_id: targetUserId,
                    post_id: postId
                },
                success: function (res) {
                    if (res && res.success) {
                        var msg = res.data && res.data.message ? res.data.message : (config.i18n ? config.i18n.success : 'Application sent!');
                        $btn.replaceWith(
                            $('<span class="mm-featured-badge mm-badge-pending" style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b;font-weight:600;padding:8px 16px;border-radius:8px;display:inline-block;font-size:13px;"></span>')
                                .text('⏳ ' + msg)
                        );
                    } else {
                        var errMsg = res && res.data && res.data.message ? res.data.message : (config.i18n ? config.i18n.error_generic : 'Unable to apply.');
                        alert(errMsg);
                        $btn.removeClass('is-loading').prop('disabled', false).html(originalText);
                    }
                },
                error: function () {
                    alert(config.i18n ? config.i18n.error_generic : 'Connection error. Please try again.');
                    $btn.removeClass('is-loading').prop('disabled', false).html(originalText);
                }
            });
        });

        // =========================================================================
        // 2. Member Portal: Featured Profile Toggle Card
        // =========================================================================
        var $toggleCard = $('#mm-featured-portal-card');
        if ($toggleCard.length) {
            var saveFeaturedSettings = function () {
                var config       = window.mmFeaturedConfig || {};
                var visibility   = $('input[name="mm_featured_visibility_mode"]:checked').val() || 'anonymous';
                var displayName  = $.trim($('#mm-featured-display-name-input').val() || '');
                var $statusText  = $('#mm-featured-save-status');

                $statusText.text(config.i18n ? config.i18n.saving_settings : 'Saving...').css('color', '#64748b').show();

                $.ajax({
                    url: config.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'mm_toggle_featured_profile',
                        nonce: config.toggle_nonce,
                        visibility_mode: visibility,
                        display_name: displayName
                    },
                    success: function (res) {
                        if (res && res.success) {
                            var msg = res.data && res.data.message ? res.data.message : (config.i18n ? config.i18n.saved_success : 'Saved!');
                            $statusText.text(msg).css('color', '#059669');
                            setTimeout(function () {
                                $statusText.fadeOut(600);
                            }, 3500);

                            if (res.data && res.data.is_approved) {
                                $('#mm-featured-approved-badge').show();
                                $('#mm-featured-review-badge').hide();
                                $('#mm-featured-review-notice').removeClass('is-pending').addClass('is-approved');
                            } else {
                                $('#mm-featured-approved-badge').hide();
                                $('#mm-featured-review-badge').show();
                                $('#mm-featured-review-notice').removeClass('is-approved').addClass('is-pending');
                            }

                            if (visibility === 'anonymous') {
                                $('#mm-featured-anon-name-wrap').slideDown(150);
                            } else {
                                $('#mm-featured-anon-name-wrap').slideUp(150);
                            }
                        } else {
                            var err = res && res.data && res.data.message ? res.data.message : 'Error saving settings.';
                            $statusText.text(err).css('color', '#dc2626');
                        }
                    },
                    error: function () {
                        $statusText.text('Network error. Unable to save.').css('color', '#dc2626');
                    }
                });
            };

            $(document).on('click', '.mm-visibility-card', function (e) {
                if ($(e.target).closest('input, button, a, label').length) {
                    return;
                }
                var $radio = $(this).find('input[type="radio"]');
                if (!$radio.is(':checked')) {
                    $radio.prop('checked', true).trigger('change');
                }
            });

            $('input[name="mm_featured_visibility_mode"]').on('change', function () {
                var mode = $(this).val();
                $('.mm-visibility-card').removeClass('is-active');
                $(this).closest('.mm-visibility-card').addClass('is-active');

                if (mode === 'anonymous') {
                    $('#mm-featured-anon-name-wrap').slideDown(150);
                } else {
                    $('#mm-featured-anon-name-wrap').slideUp(150);
                }
                saveFeaturedSettings();
            });

            $('#mm-featured-save-name-btn').on('click', function (e) {
                e.preventDefault();
                saveFeaturedSettings();
            });

            $('#mm-featured-display-name-input').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveFeaturedSettings();
                }
            });
        }

        // =========================================================================
        // 3. Standalone Lightbox for Featured Profile Galleries
        // =========================================================================
        var lightboxGallery = [];
        var lightboxIndex   = 0;

        function ensureLightboxDom() {
            var existing = document.getElementById('mm-lightbox-modal');
            if (existing) {
                if (existing.parentNode !== document.body) {
                    document.body.appendChild(existing);
                }
                return;
            }
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
            var rawPhotos = (el && (el.getAttribute('data-mm-photos') || (el.closest && el.closest('[data-mm-photos]') && el.closest('[data-mm-photos]').getAttribute('data-mm-photos')))) || '';

            if (rawPhotos) {
                var photoUrls = [];
                try {
                    if (rawPhotos.trim().charAt(0) === '[') {
                        photoUrls = JSON.parse(rawPhotos);
                    } else {
                        photoUrls = rawPhotos.split(',');
                    }
                } catch (e) {
                    photoUrls = rawPhotos.split(',');
                }
                var seenUrls = {};
                for (var p = 0; p < photoUrls.length; p++) {
                    var u = (photoUrls[p] || '').trim();
                    if (u && !seenUrls[u]) {
                        seenUrls[u] = true;
                        lightboxGallery.push({
                            src: u,
                            alt: (el && el.getAttribute('alt')) || ('Photo ' + (lightboxGallery.length + 1))
                        });
                    }
                }
            }

            if (lightboxGallery.length === 0) {
                var siblings = [];
                if (gallery) {
                    siblings = Array.prototype.slice.call(document.querySelectorAll('[data-mm-lightbox="' + gallery + '"]'));
                } else if (el) {
                    var parent = el.closest('.mm-featured-gallery-section, .mm-featured-card, .mm-card');
                    if (parent) {
                        siblings = Array.prototype.slice.call(parent.querySelectorAll('img'));
                    } else {
                        siblings = [el];
                    }
                }

                var seenUrls2 = {};
                siblings.forEach(function (node) {
                    var s = node.getAttribute('src') || node.getAttribute('data-src') || node.src || '';
                    if (s && !seenUrls2[s]) {
                        seenUrls2[s] = true;
                        lightboxGallery.push({
                            src: s,
                            alt: node.getAttribute('alt') || 'Photo'
                        });
                    }
                });
            }

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

        // Expose globally
        if (!window.MM_openLightbox) {
            window.MM_openLightbox = openLightbox;
        }
        if (!window.MM_closeLightbox) {
            window.MM_closeLightbox = closeLightbox;
        }

        ensureLightboxDom();

        // Delegated click listeners for lightbox triggers & modal controls
        $(document).on('click', '.mm-lightbox-trigger, [data-mm-lightbox]', function (e) {
            e.preventDefault();
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

        $(document).on('click', '#mm-lightbox-target-img, #mm-lightbox-zoom-toggle', function (e) {
            e.stopPropagation();
            var targetImg = document.getElementById('mm-lightbox-target-img');
            if (targetImg) {
                targetImg.classList.toggle('is-zoomed');
            }
        });

        $(document).on('click', '#mm-lightbox-modal', function (e) {
            if (e.target === this || e.target.classList.contains('mm-lightbox-container')) {
                closeLightbox();
            }
        });

        $(document).on('keydown', function (e) {
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
    });
})(jQuery);

