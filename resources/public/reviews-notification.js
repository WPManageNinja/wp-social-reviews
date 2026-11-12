import Helper from './helper.js';
window.wpsrHelper = Helper;
window.wpsr_notification_popup = [];
let $ = jQuery;
let intervalHandle = null;
let count = 0;
let index = 0;
let assets_url = '';
let assets_url_pro = '';
let custom_platform_logo = '';
let hover_able = 0;
let launched = 0;
let loop_args = null;
let loop_paused = false;

const wpsrDismissKeyPrefix = 'wpsr_notification_dismissed_';

// Visitor-side state: anonymous visitors have no server identity, and storage can throw when blocked.
export function wpsrIsNotificationDismissed(templateId) {
    if (!templateId) {
        return false;
    }
    try {
        return window.sessionStorage.getItem(wpsrDismissKeyPrefix + templateId) === '1';
    } catch (e) {
        return false;
    }
}

export function wpsrRememberNotificationDismissed(templateId) {
    if (!templateId) {
        return;
    }
    try {
        window.sessionStorage.setItem(wpsrDismissKeyPrefix + templateId, '1');
    } catch (e) {
        // Blocked storage only means the close lasts for this page view.
    }
}

export function wpsrPrefersReducedMotion() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// Matches the server-side trim in notification.php (wp_trim_words($text, 16, '…')), so a rotated-in
// review reads the same length as the one rendered on page load.
function wpsrTrimWords(text, maxWords) {
    if (!text) {
        return '';
    }
    let words = text.trim().split(/\s+/);
    return words.length > maxWords ? words.slice(0, maxWords).join(' ') + '…' : words.join(' ');
}

// Review Spotlight's avatar platform-dot letter. Mirrors the map in Pro's notification.php —
// kept in sync by hand, same as the word-trim cap above.
const wpsrPlatformDotLetters = { facebook: 'f' };

function wpsrPlatformDotLetter(platform) {
    return wpsrPlatformDotLetters[platform] || (platform ? platform.charAt(0).toUpperCase() : '');
}

export default {
    init: function () {
        this.setPlatformIconDimensions();
        this.activateNotification();
        this.showPopupModal();
        this.hidePopupModal();
        this.pauseWhileHidden();
        this.bindSpotlightNav();

        // Published for the sort toolbar's separate bundle, like window.wpsrFeedApi.
        window.wpsrNotificationApi = {
            resyncFeed: (templateId) => this.resyncFeed(templateId)
        };
    },

    /**
     * Re-point the floating card's data-index cycle at the feed the toolbar just replaced.
     */
    resyncFeed(templateId) {
        let $wrapper = $('#wpsr-notification-card-' + templateId);

        if (!$wrapper.length) {
            return;
        }

        // Count what the cycle looks up, not the cards, so this survives markup changes.
        let total = $('#wpsr-reviews-grid-' + templateId).find('[data-index]').length;

        // Both attr and data: jQuery caches the first read, so setting one leaves the other stale.
        $wrapper.attr('data-total', total).data('total', total);

        // Back to the start of the new list.
        count = 0;
    },

    /**
     * Stop the rotation in a background tab and pick it up again when the visitor returns.
     */
    pauseWhileHidden() {
        let that = this;

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                // Only a running rotation is paused; a closed card or an open reviews popup has no timer.
                if (intervalHandle) {
                    clearTimeout(intervalHandle);
                    intervalHandle = null;
                    loop_paused = true;
                }
                return;
            }

            if (!loop_paused || !loop_args) {
                return;
            }
            loop_paused = false;
            that.activateLoop(...loop_args, true);
        });
    },
    setPlatformIconDimensions() {
        $('.wpsr-reviews-notification-card-wrapper .wpsr-review-platform img').attr({
            width: 20,
            height: 20,
        });
    },
    showPopupModal() {
        let notificationCard = $(".wpsr-reviews-notification-card");
        notificationCard.each(function (index) {
            let $this = $(this);
            let that = this;
            $this.on('click', function (e) {
                let $notificationWrapper = $this.closest('.wpsr-reviews-notification-card-wrapper');

                let template = $notificationWrapper.data('notification_id');
                let reviewsWrapper = $('#wpsr-reviews-grid-' + template);
                let display_mode = $notificationWrapper.data('display_mode');

                if (display_mode === 'popup' || display_mode == 'none') {
                    e.preventDefault();
                    if(display_mode == 'popup') {
                        reviewsWrapper.removeClass('deactivate');
                        reviewsWrapper.addClass('wpsr-notification-active');
                        $notificationWrapper.addClass('deactivate');

                        let index = $notificationWrapper.attr('data-index');
                        let element = $("#wpsr-reviews-grid-" + template).find('[data-index="' + index + '"]');

                        // The card may be gone: a rating filter can shorten the list this index counts against.
                        if (element.length) {
                            element.addClass('wpsr-highlight');

                            element[0].scrollIntoView({
                                behavior: wpsrPrefersReducedMotion() ? 'auto' : 'smooth',
                                block: 'center',
                            });

                            setTimeout(function () {
                                element.removeClass('wpsr-highlight');
                            }, 2000);
                        }
                        if (intervalHandle) {
                            clearInterval(intervalHandle);
                            intervalHandle = null;
                        }
                    }
                }
            })
        });
    },
    hidePopupModal() {
        let collapse = $('.wpsr-popup-collapse');
        let that = this;

        collapse.each(function() {
            let $element = $(this);
            $element.on('click', function(e) {
                e.preventDefault();
                that.activateNotification();
                let template = $(this).data('notification_id');
                let gridWrapper = $('#wpsr-reviews-grid-' + template);
                let cardWrapper = $('#wpsr-notification-card-' + template);
                gridWrapper.removeClass('wpsr-notification-active');
                gridWrapper.addClass('deactivate');
                cardWrapper.removeClass('deactivate');
                cardWrapper.addClass('wpsr-notification-active');
            });
        });
    },
    activateNotification() {
        let self = $(this);
        if(intervalHandle) {
            clearInterval(intervalHandle);
            intervalHandle = null;
        }
        let notificationCard = $(".wpsr-reviews-notification-card-wrapper");
        
        let that = this;
        launched++;
        notificationCard.each(function(i) {
            let $this = $(this);
            let total_reviews = $this.data('total');
            let template_id = $this.data('notification_id');
            let notification_delay = $this.data('notification_delay');
            let display_date = $this.data('display_date');
            let display_tp_brand = $this.data('display_tp_brand');
            let display_platform_icon = $this.data('display_platform_icon');

            assets_url = $this.data('assets_url');
            assets_url_pro = $this.data('assets_url_pro');
            custom_platform_logo = $this.data('custom_platform_logo');
            if(!notification_delay) {
                notification_delay = 1000;
            }

            let initial_delay = $this.data('initial_delay') ? $this.data('initial_delay') : 4000;
            let delay_for = $this.data('delay_for') ? $this.data('delay_for') : 5000;

            // Closed earlier in this visit: keep it hidden and never start the rotation.
            if (wpsrIsNotificationDismissed(template_id)) {
                $this.removeClass('wpsr-notification-active');
                $this.addClass('deactivate');
                return;
            }

            // Rating Pulse: show once after initial_delay and sit there — no changeNotification/activateLoop cycle.
            if ($this.hasClass('wpsr-reviews-notification3')) {
                $this.find('.wpsr-close').on('click', function (e) {
                    e.preventDefault();
                    $this.removeClass('wpsr-notification-active');
                    $this.addClass('deactivate');
                    wpsrRememberNotificationDismissed(template_id);
                });

                setTimeout(function() {
                    if (!wpsrIsNotificationDismissed(template_id)) {
                        $this.addClass('wpsr-notification-active');
                    }
                }, initial_delay);

                return;
            }

            if(total_reviews) {
                $this.find('.wpsr-close').on('click', function (e) {
                    e.preventDefault();
                    $this.removeClass('wpsr-notification-active');
                    $this.addClass('deactivate');

                    wpsrRememberNotificationDismissed(template_id);
                    if (intervalHandle) {
                        clearTimeout(intervalHandle);
                        intervalHandle = null;
                    }
                    loop_paused = false;
                });

                loop_args = [$this, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews];

                if(launched === 1) {
                    window.wpsr_notification_popup.initial_delay = initial_delay;
                    window.wpsr_notification_popup.delay_between = notification_delay;
                    window.wpsr_notification_popup.delay_for = delay_for;

                    intervalHandle = setTimeout(function() {
                        that.changeNotification($this, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews, count);
                        count++;
                        that.activateLoop($this, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews);
                    }, window.wpsr_notification_popup.initial_delay);
                } else {
                    if(intervalHandle) {
                        clearInterval(intervalHandle);
                        intervalHandle = null;
                    }

                    setTimeout(function() {
                        if(!hover_able && $this.hasClass('wpsr-notification-active')) {
                            $this.removeClass('wpsr-notification-active');
                        }

                        let start_loop = true;
                        that.activateLoop($this, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews, start_loop);
                    }, 1000)
                }

                $this.mouseover(function() {
                    hover_able = 1;
                    $this.addClass('wpsr-visible-card');
                });

                $this.mouseleave(function() {
                    hover_able = 0;
                    $this.removeClass('wpsr-visible-card');
                });
            }
        });
    },

    findElement(template_id, index) {
        let wpsrGrid = $('#wpsr-reviews-grid-' + template_id);
        let element = wpsrGrid.find('[data-index="' + index + '"]');

        // No card at that position: report it instead of returning a blank card.
        if (!element.length) {
            return { 'missing': true, 'review_platform': '' };
        }

        let review_platform = element.data('review_platform') || '';
        // If this is an AI platform, return the platform info so we can skip it
        if (review_platform === 'ai') {
            return {
                'review_platform': review_platform
            };
        }

        let reviewer_image = element.find('.wpsr-reviewer-image img').attr('src');
        let custom_platform_logo = element.find('.wpsr-review-platform img').attr('src');
        let reviewer_name  = element.find('.wpsr-review-info .wpsr-reviewer-name').text();
        let review_rating  = element.find('.wpsr-rating-wrapper').data('rating');
        let review_date    = element.find('.wpsr-review-date').data('time');
        let product_name  = element.data('product_name');
        let product_thumbnail    = element.data('product_thumbnail');
        let reviewer_text  = wpsrTrimWords(element.find('.wpsr-review-content').first().text(), 16);
        // Rendered server-side via Helper::platformDisplayName() — same label everywhere, no JS map to keep in sync.
        let platform_label = element.data('platform_label') || '';

        return {
            'reviewer_image': reviewer_image,
            'custom_platform_logo': custom_platform_logo,
            'reviewer_name': reviewer_name,
            'review_rating': review_rating,
            'review_date': review_date,
            'review_platform': review_platform,
            'platform_label': platform_label,
            'product_name': product_name,
            'product_thumbnail': product_thumbnail,
            'reviewer_text': reviewer_text,
            'platform_icons': wpsrGrid.data('platform_icons'),
        };
    },

    setElement(element, data, display_date, display_tp_brand, display_platform_icon) {
        // Skip displaying notifications for AI platforms
        if (data.review_platform === 'ai') {
            return false;
        }

        // Use custom platform logo if available, otherwise get default platform icon
        let platform_icon = data.custom_platform_logo && data.custom_platform_logo !== ''
            ? data.custom_platform_logo
            : this.get_platform_icon(data.review_platform, data.platform_icons);

        let rating_icon_html = window.wpsrHelper.ratingIcon(data.review_rating);
        element.find('.wpsr-reviewer-image img').attr('src', data.reviewer_image);
        element.find('.wpsr-review-platform img').attr({
            src: platform_icon,
            width: 20,
            height: 20,
        });
        element.find('.wpsr-reviewer-image img').attr('alt', data.reviewer_name);
        element.find('.reviewer-name').text(data.reviewer_name);
        element.find('.review-rating').text(parseFloat(data.review_rating));
        element.find('.wpsr-rating').text(data.review_rating);
        element.find('.wpsr-review-source-title').text(data.product_name);
        // No-op on notification1: it has no .wpsr-notification-quote element to find. Toggle the
        // whole wrapper, not just the text, so rotating into a review with no text also hides the
        // quote icon instead of leaving it next to an empty line.
        element.find('.wpsr-notification-quote').toggle(!!data.reviewer_text);
        element.find('.wpsr-notification-excerpt').text(data.reviewer_text || '');

        if(display_date) {
            if(data.review_date && data.review_date.length) {
                element.find('.review-time').text(data.review_date);
            } else {
                element.find('.review-time').text('');
            }
        }

        if(display_platform_icon && platform_icon && !data.review_platform.includes('custom')) {
            if(data.review_platform.includes('trust') && !display_tp_brand) {
                this.togglePlatformIcon(element, '', data.review_platform, 'wpsr-show-logo', 'wpsr-hide-logo');
            } else {
                this.togglePlatformIcon(element, platform_icon, data.review_platform, 'wpsr-hide-logo', 'wpsr-show-logo');
            }
        }  else {
            this.togglePlatformIcon(element, '', data.review_platform, 'wpsr-show-logo', 'wpsr-hide-logo');
        }

        element.find('.wpsr-rating').html(rating_icon_html);

        // Keeps the platform-dot brand colour (CSS, keyed off this class) in sync with the review
        // actually shown — a stale class from the previous review would otherwise linger, since
        // manual prev/next calls this without going through hideNotification()'s own cleanup.
        element[0].className = element[0].className.replace(/\bwpsr-reviews-platform-\S+/g, '').trim();
        if (data.review_platform) {
            element.addClass('wpsr-reviews-platform-' + data.review_platform);
        }

        // Review Spotlight only, below here — no-op on notification1: none of these selectors exist on it.
        let $dotLetter = element.find('.wpsr-notification-platform-dot-letter');
        if ($dotLetter.length) {
            $dotLetter.text(wpsrPlatformDotLetter(data.review_platform));
        }

        let $source = element.find('.wpsr-notification-source');
        if ($source.length) {
            $source.find('img').attr('src', platform_icon);
            $source.find('.wpsr-notification-source-name').text(data.platform_label);
        }

        let $verified = element.find('.wpsr-verified-review');
        if ($verified.length) {
            let enableVerified = element.attr('data-enable_verified_badge') === 'true';
            $verified.toggle(enableVerified && data.review_platform !== 'ai');
        }

        // Same thresholds as Helper::ratingQualityLabel() / ratingQualityLabel() in WPSocialReviews.js,
        // so the label matches what a badge would show for the same rating.
        let $ratingWord = element.find('.wpsr-notification-rating-word');
        if ($ratingWord.length) {
            let rating = parseFloat(data.review_rating) || 0;
            let word = '';
            if (rating >= 4.5) {
                word = element.attr('data-rating_word_excellent');
            } else if (rating >= 3.9) {
                word = element.attr('data-rating_word_great');
            } else if (rating >= 2.9) {
                word = element.attr('data-rating_word_average');
            } else if (rating >= 2.1) {
                word = element.attr('data-rating_word_poor');
            } else if (rating > 0) {
                word = element.attr('data-rating_word_bad');
            }
            $ratingWord.text(word || '').toggle(!!word);
        }
    },

    activateLoop(element, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews, start_loop) {
        let that = this;

        // Every path into the rotation lands here, including timers armed before the close.
        if (wpsrIsNotificationDismissed(template_id)) {
            return;
        }

        // Re-read: resyncFeed() rewrites this on a filter, and the recursion would carry the old count.
        let current_total = parseInt(element.attr('data-total'), 10);
        current_total = isNaN(current_total) ? total_reviews : current_total;

        loop_args = [element, template_id, display_date, display_tp_brand, display_platform_icon, current_total];

        // >=, not ===: a shrink can leave count past the new end, which an equality test would never catch.
        if (count >= current_total) {
            count = 0
        }

        let delay;
        if(start_loop) {
            delay = window.wpsr_notification_popup.delay_between;
        } else {
            delay = window.wpsr_notification_popup.delay_between + window.wpsr_notification_popup.delay_for;
        }
        start_loop = false;

        intervalHandle = setTimeout(function() {
            that.changeNotification(element, template_id, display_date, display_tp_brand, display_platform_icon, current_total, count);
            count++;
            that.activateLoop(element, template_id, display_date, display_tp_brand, display_platform_icon, current_total, start_loop);
        }, delay);
    },

    // Change notification
    changeNotification(element, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews, index) {
        //show notification for 10 seconds
        let data = this.findElement(template_id, index);

        // Nothing here — sit this tick out; activateLoop() still advances the counter.
        if (data.missing) {
            return;
        }

        // Skip AI platforms
        if (data.review_platform === 'ai') {
            // Move to the next review
            count++;
            if (total_reviews === count) {
                count = 0;
            }
            // Try with the next review
            this.changeNotification(element, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews, count);
            return;
        }

        this.showNotification(element, template_id, display_date, index, display_tp_brand, display_platform_icon);
        let that = this;

        setTimeout(function() {
            that.hideNotification(element, template_id, index);
        }, window.wpsr_notification_popup.delay_for)
    },

    // Show notification
    showNotification(element, template_id, display_date, index, display_tp_brand, display_platform_icon) {
        element.attr('data-index', index);
        let that = this;

        let data = that.findElement(template_id, index);

        if (data.missing) {
            return;
        }

        let platform  = data.review_platform;
        // Skip AI platforms
        if (platform ==='ai') {
            // Move to the next review
            count++;
            return;
        }
        if(!element.hasClass('wpsr-notification-active')) {
            element.addClass('wpsr-notification-active');
            element.addClass('wpsr-reviews-platform-'+platform);
        }

        that.setElement(element, data, display_date, display_tp_brand, display_platform_icon);

        let total = parseInt(element.attr('data-total'), 10);
        if (total) {
            that.updateSpotlightDots(element, index, total);
        }
    },

    // Hide notification
     hideNotification(element, template_id, index) {
         let that = this;
         let data = that.findElement(template_id, index);
         let platform  = data.review_platform;

         if(element.hasClass('wpsr-notification-active')) {
             element.removeClass('wpsr-notification-active');
             element.removeClass('wpsr-reviews-platform-'+platform);
         }

         if(hover_able) {
             element.addClass('wpsr-notification-active');
             element.addClass('wpsr-reviews-platform-'+platform);
         }
    },

    /**
     * No-op on notification1: it has no .wpsr-notification-dots to find. Silently skipped above five
     * reviews too — the dial has no overflow affordance, so it renders no dots rather than a wrong count.
     */
    updateSpotlightDots(element, index, total) {
        let dots = element.find('.wpsr-notification-dots > span');
        if (!dots.length || total > dots.length) {
            return;
        }
        dots.removeClass('active').eq(index).addClass('active');
    },

    /**
     * Prev/next controls on the spotlight layout. A no-op everywhere else: notification1 renders no
     * .wpsr-notification-nav buttons, so this listener never fires for it.
     */
    bindSpotlightNav() {
        let that = this;

        $(document).on('click', '.wpsr-notification-nav', function (e) {
            e.preventDefault();
            e.stopPropagation();

            let $wrapper = $(this).closest('.wpsr-reviews-notification-card-wrapper');
            let template_id = $wrapper.data('notification_id');
            let total = parseInt($wrapper.attr('data-total'), 10) || 0;
            if (!total) {
                return;
            }

            let current = parseInt($wrapper.attr('data-index'), 10) || 0;
            let direction = $(this).hasClass('wpsr-notification-nav-prev') ? -1 : 1;

            // Step past AI reviews in the requested direction: showNotification() no-ops on one
            // (by design, same as the auto-rotation loop), which would otherwise leave the button
            // looking unresponsive instead of landing on the next visible review.
            let next = current;
            let data;
            let attempts = 0;
            do {
                next = (next + direction + total) % total;
                data = that.findElement(template_id, next);
                attempts++;
            } while (data.review_platform === 'ai' && attempts < total);

            if (data.review_platform === 'ai') {
                return;
            }

            let display_date = $wrapper.data('display_date');
            let display_tp_brand = $wrapper.data('display_tp_brand');
            let display_platform_icon = $wrapper.data('display_platform_icon');

            if (intervalHandle) {
                clearTimeout(intervalHandle);
                intervalHandle = null;
            }

            that.showNotification($wrapper, template_id, display_date, next, display_tp_brand, display_platform_icon);
            count = (next + 1) % total;

            // The manual nudge still restarts the auto rotation, same pacing as a normal tick.
            that.activateLoop($wrapper, template_id, display_date, display_tp_brand, display_platform_icon, total, true);
        });
    },

    togglePlatformIcon(element, platform_icon, platform_name, class1, class2) {
        // Only the "on" word: the product title is a span deeper inside the body.
        let spanEl = element.find('.wpsr-notification-body > span');
        let iconEl = element.find('.wpsr-review-platform img');

        if(iconEl.hasClass(class1)) {
            iconEl.removeClass(class1);
            iconEl.addClass(class2);
        }
        // Hide the icon and its "on" word when the file is missing, so no broken image shows.
        iconEl.off('error.wpsr').on('error.wpsr', function () {
            $(this).removeClass('wpsr-show-logo').addClass('wpsr-hide-logo');
            spanEl.removeClass('wpsr-show-logo').addClass('wpsr-hide-logo');
        });
        iconEl.attr({
            src: platform_icon,
            alt: platform_name,
            width: 20,
            height: 20,
        });
        if(spanEl.hasClass(class1)) {
            spanEl.removeClass(class1);
            spanEl.addClass(class2);
        }
    },

    get_platform_icon(platform, platform_icons) {
        // The server sends each platform's icon. A missing or empty value means the platform has no icon.
        if (platform_icons && typeof platform_icons === 'object') {
            return Object.prototype.hasOwnProperty.call(platform_icons, platform) ? platform_icons[platform] : '';
        }
        if(custom_platform_logo){
            return custom_platform_logo;
        }
        let icon_assets_url = platform.includes('trust') ? assets_url_pro : assets_url + '/images/icon/';
        let icon_name = 'icon-' + platform + '-small.png';
        return icon_assets_url + icon_name;
    }
}
