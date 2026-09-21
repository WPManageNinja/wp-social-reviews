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
export default {
    init: function () {
        this.setPlatformIconDimensions();
        this.activateNotification();
        this.showPopupModal();
        this.hidePopupModal();

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
                                behavior: 'smooth',
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

            if(total_reviews) {
                $this.find('.wpsr-close').on('click', function (e) {
                    e.preventDefault();
                    $this.removeClass('wpsr-notification-active');
                    $this.addClass('deactivate');
                });

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
        let element = $('#wpsr-reviews-grid-' + template_id).find('[data-index="' + index + '"]');

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

        return {
            'reviewer_image': reviewer_image,
            'custom_platform_logo': custom_platform_logo,
            'reviewer_name': reviewer_name,
            'review_rating': review_rating,
            'review_date': review_date,
            'review_platform': review_platform,
            'product_name': product_name,
            'product_thumbnail': product_thumbnail,
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
            : this.get_platform_icon(data.review_platform);

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

        if(display_date) {
            if(data.review_date && data.review_date.length) {
                element.find('.review-time').text(data.review_date);
            } else {
                element.find('.review-time').text('');
            }
        }

        if(display_platform_icon && !data.review_platform.includes('custom')) {
            if(data.review_platform.includes('trust') && !display_tp_brand) {
                this.togglePlatformIcon(element, '', data.review_platform, 'wpsr-show-logo', 'wpsr-hide-logo');
            } else {
                this.togglePlatformIcon(element, platform_icon, data.review_platform, 'wpsr-hide-logo', 'wpsr-show-logo');
            }
        }  else {
            this.togglePlatformIcon(element, '', data.review_platform, 'wpsr-show-logo', 'wpsr-hide-logo');
        }

        element.find('.wpsr-rating').html(rating_icon_html);
    },

    activateLoop(element, template_id, display_date, display_tp_brand, display_platform_icon, total_reviews, start_loop) {
        let that = this;

        // Re-read: resyncFeed() rewrites this on a filter, and the recursion would carry the old count.
        let current_total = parseInt(element.attr('data-total'), 10);
        current_total = isNaN(current_total) ? total_reviews : current_total;

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

    togglePlatformIcon(element, platform_icon, platform_name, class1, class2) {
        let spanEl = element.find('.wpsr-notification-body span');
        let iconEl = element.find('.wpsr-review-platform img');

        if(iconEl.hasClass(class1)) {
            iconEl.removeClass(class1);
            iconEl.addClass(class2);
        }
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

    get_platform_icon(platform) {
        if(custom_platform_logo){
            return custom_platform_logo;
        }
        let icon_assets_url = platform.includes('trust') ? assets_url_pro : assets_url + '/images/icon/';
        let icon_name = 'icon-' + platform + '-small.png';
        return icon_assets_url + icon_name;
    }
}
