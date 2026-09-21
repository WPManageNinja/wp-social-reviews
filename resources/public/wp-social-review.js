import { appendReadMoreButton, carouselOptions, openPopupWindow, animateTextTypingEffect, processAISummaryList, wpsrInitInjectedPlayers, wpsrInitMasonry, wpsrWatchMasonryHeights } from './helper.js';
import TwitterCard from './twitter-card.js';

jQuery(document).ready(function ($) {
    const FeedPaginator = {
        init() {
            let that = this;

            $('.wpsr-loader').addClass('wpsr-loading');
            setTimeout(function(){
                jQuery('.wpsr-loader').removeClass('wpsr-loading');
            }, 500);

            $('.wpsr_more').on('click keypress', function (e) {
                e.preventDefault();
                that.fetchData($(this))
            });

            if (window.wpsr_ajax_params && window.wpsr_ajax_params.has_pro) {
                this.initPro();
            }

            $('.wpsr-social-wall-tab').on('click keypress', function (e) {
                e.preventDefault();
                let platform = $(this).data("platform");

                $('.wpsr-social-wall-tab').removeClass('wpsr-active');
                $(this).addClass('wpsr-active');

                $('.wpsr-social-wall-tab-content-wrapper .wpsr-social-wall-content')
                    .removeClass('wpsr-active')
                    .addClass('wpsr-deactivate');

                $('.wpsr-social-wall-tab-content-wrapper .wpsr-social-wall-content.wpsr-'+platform)
                    .removeClass('wpsr-deactivate')
                    .addClass('wpsr-active');


                // Add loading state
                const $activeContent = $(`.wpsr-social-wall-content.wpsr-${platform}.wpsr-active .wpsr_content`);
                $activeContent.append(`
                    <div class="wpsr-loader wpsr-loading">
                        <div class="wpsr-spinner-animation"></div>
                    </div>
                `);

                let masonrySelector = $('.wpsr-twitter-masonry, .wpsr-instagram-masonry-activate, .wpsr-facebook-feed-masonry-activate, .wpsr-tiktok-feed-masonry-activate');
                that.activeMasonryLayout(masonrySelector);

                setTimeout(() => {
                    $activeContent.find('.wpsr-loader').removeClass('wpsr-loading');
                }, 500);
            });
        },

        /**
         * Per-feed request state (lock, sequence token, requested vs applied order/rating/search), kept on the
         * feed's own element so two feeds on one page never block each other.
         */
        feedState($holder) {
            if (!$holder || !$holder.length) {
                return null;
            }

            let state = $holder.data('wpsrFeedState');

            if (!state) {
                const $toolbar = $holder.find('.wpsr-reviews-sort-toolbar').first();
                const rating = parseInt($toolbar.data('rating'), 10);

                const order = $toolbar.length ? String($toolbar.data('order') || '') : '';
                const exactRating = isNaN(rating) ? -1 : rating;

                state = {
                    seq: 0,
                    inflight: false,
                    xhr: null,
                    order: order,
                    rating: exactRating,
                    // Not read from the toolbar: the server never renders an applied search, so '' is correct.
                    search: '',
                    // The server rendered the page with these, so they are applied.
                    appliedOrder: order,
                    appliedRating: exactRating,
                    appliedSearch: '',
                    // What an open toolbar request is fetching; a load-more leaves it null, never changing it.
                    pendingKey: null,
                    // The one query that failed, so retrying exactly it gets past the toolbar's no-op guard.
                    failedKey: null
                };

                $holder.data('wpsrFeedState', state);
            }

            return state;
        },

        // templateType is passed in, not read from the load-more button, which a feed may not have.
        maybeMasonryFeed(templateType, $feedsWrap) {
            const column = $feedsWrap.data("column");

            if (templateType === 'masonry') {
                const $feedsElement = $feedsWrap.find('.wpsr_feeds');
                if ($feedsElement.length) {
                    // Only when an instance exists; a feed that rendered empty has none yet.
                    if ($feedsElement.data('masonry')) {
                        $feedsElement.masonry('reloadItems');
                    }
                    if (typeof $feedsElement.imagesLoaded === 'function') {
                        $feedsElement.imagesLoaded(function () {
                            if (typeof $feedsElement.masonry === 'function') {
                                $feedsElement.masonry({
                                    itemSelector: '.wpsr-col-' + column + '',
                                });
                            }
                        });
                    } else {
                        if (typeof $feedsElement.masonry === 'function') {
                            $feedsElement.masonry({
                                itemSelector: '.wpsr-col-' + column + '',
                            });
                        }
                    }

                    // The appended cards are new to the observer set up at page load.
                    wpsrWatchMasonryHeights($feedsElement, '.wpsr-col-' + column);
                }
            }
        },

        // The Swiper instance for one feed, or null. Swiper 8 hangs itself off its container element.
        sliderInstance($feedsWrap) {
            const container = $feedsWrap.find('.swiper-container').first()[0];

            return (container && container.swiper) ? container.swiper : null;
        },

        // tabindex is an attribute, not a delegated handler, so it must be re-applied after every slide swap.
        applySlideTabindex($feedsWrap) {
            const settings = $feedsWrap.data('slider_settings');

            if (settings && settings.autoplay === 'true') {
                $feedsWrap.find('.swiper-container .swiper-slide').attr('tabindex', '0');
            }
        },

        // Stop or start autoplay around a re-fetch. Test params.autoplay.enabled: params.autoplay is truthy on every slider.
        setSliderAutoplay($feedsWrap, running) {
            const swiper = this.sliderInstance($feedsWrap);

            if (!swiper || !swiper.autoplay || !swiper.params.autoplay
                || !swiper.params.autoplay.enabled) {
                return;
            }

            if (running) {
                swiper.autoplay.start();
            } else {
                swiper.autoplay.stop();
            }
        },

        // Re-measure a slider after its slides were replaced, and rewind: the old offset may not exist now.
        relayoutSlider($feedsWrap) {
            const swiper = this.sliderInstance($feedsWrap);

            if (!swiper) {
                return;
            }

            swiper.update();
            swiper.slideTo(0, 0);
            this.applySlideTabindex($feedsWrap);
        },
        activeMasonryLayout(masonrySelector){
            if(masonrySelector.length){
                masonrySelector.each(function () {
                    let id = '#' + $(this).attr('id');
                    let $element = $(id);
                    let column = $(this).data("column");

                    // Check if element exists before calling imagesLoaded
                    if ($element.length && typeof $element.imagesLoaded === 'function') {
                        $element.imagesLoaded(function () {
                            const $masonryContainer = $element.find('.wpsr-twitter-all-tweets, .wpsr-ig-all-feed, .wpsr-fb-all-feed, .wpsr-tt-all-feed');
                            if ($masonryContainer.length && typeof $masonryContainer.masonry === 'function') {
                                $masonryContainer.masonry({
                                    itemSelector: '.wpsr-col-' + column,
                                });
                            }
                        });
                    } else {
                        const $masonryContainer = $element.find('.wpsr-twitter-all-tweets, .wpsr-ig-all-feed, .wpsr-fb-all-feed, .wpsr-tt-all-feed');
                        if ($masonryContainer.length && typeof $masonryContainer.masonry === 'function') {
                            $masonryContainer.masonry({
                                itemSelector: '.wpsr-col-' + column,
                            });
                        }
                    }
                });
            }
        },
        /**
         * Append the next page of feed items
         *
         * parseHTML, not $(): a plain-message response would be read as a selector.
         */
        fetchData($btn) {
            let templateId = $btn.data("template_id"),
                total = parseInt($btn.data("total")),
                prev_page = parseInt($btn.data("page")),
                platform = $btn.data("platform"),
                feed_id = $btn.data("feed_id"),
                feed_type = $btn.data("feed_type"),
                // Product scope, or the server falls back to every product in the store.
                wpsrProductId = $btn.data("product_id");
            
            // Use backend-resolved paginate (wp_is_mobile), same as total_posts_number
            let paginate = parseInt($btn.data("paginate"));

            // A searched total is only what has been seen, so the server's answer decides there instead.
            if ($btn.data('has_more') !== true && prev_page * paginate >= total) {
                $btn.hide();
                return false;
            }

            // Per-feed lock, so two feeds from the same template no longer block each other.
            const $stateWrap = $btn.closest('.wpsr-feed-wrap');
            const state = this.feedState($stateWrap.length ? $stateWrap : $btn);

            if (state.inflight) {
                return false;
            }

            state.inflight = true;

            // Captured, not bumped: a load-more continues the query already on screen.
            const seq = state.seq;
            let page = prev_page + 1;

            $btn.prop('disabled', true).addClass('wpsr-spinner-loading');
            $btn.closest('.wpsr-feed-wrap').find('.wpsr_feeds').addClass('wpsr-loading-feeds');

            const params = {
                action: 'wpsr_get_more_feeds',
                page: page,
                template_id: templateId,
                platform: platform,
                feed_type: feed_type,
                feed_id: feed_id,
                product_id: wpsrProductId || 0,
                security: wpsr_ajax_params.wpsr_nonce
            };

            // Carry the live query: the rendered attributes lag a sort, and a feed can search with no dropdown.
            if (platform === 'reviews' && (state.order || state.search)) {
                if (state.order && state.order !== 'random') {
                    params.order = state.order;
                }

                params.rating = state.rating;
                params.search = state.search;
                // Lets the server skip the COUNT; honoured on page > 1 only.
                params.total_reviews = total;
            }

            const wpsrXhr = jQuery.get(wpsr_ajax_params.ajax_url, params);

            // Published so a sort started mid-flight can abort this page.
            state.xhr = wpsrXhr;

            wpsrXhr.then(response => {
                    if (seq !== state.seq) {
                        return;
                    }

                    let $feedsWrap = $btn.closest('.wpsr-feed-wrap');
                    if (response.content.trim()) {
                        let $appended = $($.parseHTML(response.content, document, true));
                        if(feed_type === 'album_feed') {
                            $feedsWrap.find(`#wpsr-album-feed-${feed_id}`).append($appended);
                        } else {
                            $feedsWrap.find('.wpsr_feeds').append($appended);
                        }

                        try {
                            wpsrInitInjectedPlayers($appended, templateId);
                        } catch (e) {
                            console.error('wpsr: could not init injected players', e);
                        }
                    } else {
                        $btn.hide();
                    }

                    if (platform == 'instagram' || platform == 'twitter' || platform == 'facebook_feed' || platform == 'tiktok' || platform == 'reviews') {
                        $feedsWrap = $btn.closest('.wpsr-feed-wrap')
                        this.maybeMasonryFeed($btn.data('template_type'), $feedsWrap);
                    }

                    // A searched feed has no real total to count against; the server answers directly instead.
                    const wpsrHasMore = (typeof response.has_more === 'boolean') ? response.has_more : null;

                    if (wpsrHasMore === false || (wpsrHasMore === null && page * paginate >= total)) {
                        $btn.hide();
                    }

                    $btn.data('page', page).data('has_more', wpsrHasMore);

                    appendReadMoreButton(wpsr_ajax_params.read_more, wpsr_ajax_params.read_less);
                })
                .catch(errors => {
                    // A superseded request is not an error; the token identifies aborts exactly, unlike jqXHR.status.
                    if (seq !== state.seq) {
                        return;
                    }

                    console.error(errors);
                })
                .always(() => {
                    // A superseded page must not release the lock the newer request holds.
                    if (seq !== state.seq) {
                        return;
                    }

                    state.inflight = false;
                    state.xhr = null;
                    $btn.removeClass('wpsr-spinner-loading').prop('disabled', false);
                    $btn.closest('.wpsr-feed-wrap').find('.wpsr_feeds').removeClass('wpsr-loading-feeds');
                });
        },

        initPro() {
            // sliders
            const carousels = $(".wpsr-reviews-layout-slider, .wpsr-twitter-carousel, .wpsr-youtube-slider-activate, .wpsr-instagram-slider-activate, .wpsr-facebook-feed-slider-activate, .wpsr-tiktok-feed-slider-activate");
            if(carousels.length) {
                let swipedUp = window.Swiper;
                let hasEpro = !!window.elementorFrontend;
                carousels.each(function (index, target) {
                    let $this = $(this);
                    let id = '#' + $(this).attr('id');
                    let sliderSettings = $(this).data("slider_settings");
                    let sliderOptions = carouselOptions(sliderSettings, id, index, $this);
                    let swiper = null
                    if(hasEpro) {
                        setTimeout(() => {
                            if(window.elementorFrontend && window.elementorFrontend.utils && window.elementorFrontend.utils.swiper) {
                                swipedUp = window.elementorFrontend.utils.swiper;
                            }
                        }, 100);
                        swiper = new swipedUp(id + ' .swiper-container', sliderOptions);
                    } else {
                        swiper = new swipedUp(id + ' .swiper-container', sliderOptions);
                    }

                    $('.wpsr-feed-wrap').addClass('wpsr-slider-before-init');

                    setTimeout(() => {
                        $('.wpsr-ig-post-media, .wpsr-yt-video-preview .wpsr-animated-background, .wpsr-tiktok-feed-image .wpsr-animated-background, .wpsr-fb-feed-image .wpsr-animated-background').removeClass('wpsr-animated-background');
                    }, 2000);

                    if (sliderSettings.autoplay === 'true' && swiper) {

                        // improve accessibility
                        let container = $(id).find('.swiper-container');

                        // Set tabindex="0" for all slides initially
                        FeedPaginator.applySlideTabindex($this);


                        // Pause autoplay when the container gains focus
                        container.on('focusin', function () {
                            swiper.autoplay.stop();
                        });

                        // Resume autoplay when the container loses focus
                        container.on('focusout mouseleave', function () {
                            swiper.autoplay.start();
                        });

                        // Delegated from the container, which the toolbar never replaces, unlike the slides.
                        // Pause autoplay when a slide gains focus
                        container.on('focusin', '.swiper-slide *', function (e) {
                            swiper.autoplay.stop();
                        });

                        //
                        // // Resume autoplay when a slide loses focus
                        // container.on('focusout blur', '.swiper-slide', function () {
                        //     swiper.autoplay.start();
                        // });

                        // Handle keyboard navigation for slides
                        container.on('keydown', '.swiper-slide', function (e) {
                            if (e.key === 'ArrowLeft') {
                                swiper.slidePrev();
                            } else if (e.key === 'ArrowRight') {
                                swiper.slideNext();
                            }
                        });
                    }
                });
            }

            //activate reviews masonry layout
            $('.wpsr-active-masonry-layout').each((index, element) => {
                wpsrInitMasonry($(element));
            });

            //activate masonry layout
            let masonrySelector = $('.wpsr-twitter-masonry, .wpsr-instagram-masonry-activate, .wpsr-facebook-feed-masonry-activate, .wpsr-tiktok-feed-masonry-activate');
            this.activeMasonryLayout(masonrySelector);

            // show hide write a review modal box (delegated for drawer support)
            $(document).on("click", '.wpsr-write-review-modal-btn', function (e) {
                e.preventDefault();
                let templateId = $(this).parents('.wpsr-reviews-wrapper').attr('id');
                $('#' + templateId).find('.wpsr-write-review-modal').toggleClass("active");
            });

            $(document).on("click", '.wpsr-reviews-form-popup-trigger', function (e) {
                e.preventDefault();


                let businessInfoWrapper = $(this).closest('.wpsr-business-info-wrapper');
                if (businessInfoWrapper.length && businessInfoWrapper.hasClass('wpsr-woocommerce-reviews-form')) {
                    return;
                }

                // Use closest() to find the overlay within the same wrapper context,
                // avoiding duplicate ID issues when shortcode output appears in both
                // the page and a drawer
                var $wrapper = $(this).closest('.wpsr-reviews-wrapper');
                var $overlay = $wrapper.find('.wpsr-reviews-form-popup-overlay');

                // If inside a drawer, move overlay to body so it escapes overflow clipping
                if ($overlay.closest('.wpsr-product-review-drawer').length) {
                    $overlay.data('wpsr-original-parent', $overlay.parent());
                    $overlay.appendTo('body');
                }

                $overlay.toggleClass("wpsr_has_fluent_forms_shortcode");
            });

            $(document).on('click', '.wpsr-reviews-form-popup-overlay .wpsr-popup-close', function (e) {
                var $overlay = $(this).closest('.wpsr-reviews-form-popup-overlay');
                $overlay.removeClass('wpsr_has_fluent_forms_shortcode');

                // Move overlay back to its original parent if it was relocated
                var $originalParent = $overlay.data('wpsr-original-parent');
                if ($originalParent && $originalParent.length) {
                    $overlay.appendTo($originalParent);
                    $overlay.removeData('wpsr-original-parent');
                }
            });

            $(document).on('click', function (e) {
                if ($(e.target).has(".active").length) {
                    $('.wpsr-write-review-modal').removeClass('active');
                }
            });

            // open fb feed share url in popup window
            $('.wpsr-fb-feed-btn-share').on('click', function (e){
                e.preventDefault();
                let url = $(this).attr('href');
                openPopupWindow('https://facebook.com/sharer/sharer.php?u='+url);
            });

        }
    };

    // Published for the sort toolbar's separate bundle, which cannot import from this file. Swiper stays on this side.
    window.wpsrFeedApi = {
        feedState: ($holder) => FeedPaginator.feedState($holder),
        relayoutMasonry: (templateType, $wrap) => FeedPaginator.maybeMasonryFeed(templateType, $wrap),
        relayoutSlider: ($wrap) => FeedPaginator.relayoutSlider($wrap),
        setSliderAutoplay: ($wrap, running) => FeedPaginator.setSliderAutoplay($wrap, running),
        appendReadMoreButton: appendReadMoreButton
    };

    FeedPaginator.init();

    appendReadMoreButton(wpsr_ajax_params.read_more, wpsr_ajax_params.read_less);
    processAISummaryList($);
    animateTextTypingEffect('.wpsr-ai-review-summary p', 7);

    //twitter infinite scroll
    let $twitter_scope = $('.wpsr-twitter-standard, .wpsr-twitter-masonry');
    if ($twitter_scope.hasClass('wpsr-twitter-infinite-scroll-active')) {
        let windowHeight50 = $(window).outerHeight() / 1.25;
        $(window).scroll(function () {
            if (($(window).scrollTop() + windowHeight50) >= ($twitter_scope.find('.wpsr-twitter-tweet:last').offset().top)) {
                const $btn = $twitter_scope.find('.wpsr-twitter-loadmore');
                if ($btn.is(':visible') && !$btn.prop('disabled')) {
                    $twitter_scope.find('.wpsr-twitter-loadmore').trigger('click');
                }
            }
        });
    }

    // generate twitter cards
    TwitterCard.init();

    $('.wpsr-yt-prev-next .wpsr-pagi-prev, .wpsr-yt-prev-next .wpsr-pagi-next').on('click', function (e) {
        e.preventDefault();
        let that = $(this),
            wrap = that.closest('.wpsr-yt-prev-next'),
            pageNum = parseInt(wrap.data('pagenum')),
            templateId = parseInt(wrap.data('template-id')),
            totalPage = parseInt(wrap.data('total')),
            paginate = parseInt(wrap.data("paginate")),
            parent = $('#wpsr-yt-feed-' + templateId);

        if (!pageNum) {
            pageNum = 1;
        }

        if (that.hasClass('wpsr-pagi-prev')) {
            if (that.hasClass('wpsr-link-disable')) {
                return;
            } else {
                pageNum = pageNum - 1;

                if (pageNum <= 0) {
                    pageNum = 1;
                }
                wrap.data('pagenum', pageNum);
                wrap.find('.wpsr-pagi-prev, .wpsr-pagi-next').removeClass('wpsr-link-disable');
                if (pageNum <= 1) {
                    $(this).addClass('wpsr-link-disable');
                }
            }
        }

        if (that.hasClass('wpsr-pagi-next')) {
            if (that.hasClass('wpsr-link-disable')) {
                return;
            } else {
                pageNum++;
                wrap.data('pagenum', pageNum);
                wrap.find('.wpsr-pagi-prev, .wpsr-pagi-next').removeClass('wpsr-link-disable');
                if (pageNum === Math.ceil(totalPage / paginate)) {
                    $(this).addClass('wpsr-link-disable');
                }
            }
        }

        let data = {
            'action': 'wpsr_get_more_feeds',
            'page': pageNum,
            'template_id': templateId,
            platform: 'youtube',
            security: wpsr_ajax_params.wpsr_nonce
        };

        $.get(wpsr_ajax_params.ajax_url, data)
            .then(response => {
                parent.find('.wpsr-yt-all-feed').html(response.content);
            })
            .catch(errors => {
                console.error(errors);
            })
            .always(() => {

            });
    });

    $(document).on("click keypress", ".wpsr_read_more, .wpsr_read_less", function () {
        $(this).closest(".wpsr_add_read_more").toggleClass("wpsr_show_less_content wpsr_show_more_content");
        if (
            $(this).closest('.wpsr-row').hasClass('wpsr-active-masonry-layout')
            || $(this).closest('.wpsr-review-fixed-height-wrap').hasClass('wpsr-active-masonry-layout')
            || $(this).closest('.wpsr-feed-wrap').hasClass('wpsr-facebook-feed-masonry-activate')
        ) {
            let column = $('.wpsr-active-masonry-layout, .wpsr-facebook-feed-masonry-activate').data("column");
            $('.wpsr-active-masonry-layout').masonry({
                itemSelector: '.wpsr-col-' + column + '',
            });

            $('.wpsr-facebook-feed-masonry-activate').find('.wpsr-fb-all-feed').masonry({
                itemSelector: '.wpsr-col-' + column + '',
            });

            $('.wpsr-tiktok-feed-masonry-activate').find('.wpsr-tt-all-feed').masonry({
                itemSelector: '.wpsr-col-' + column + '',
            });
        }
    });

    //open tweet actions on popup window
    let popup_params = `scrollbars=no,status=no,location=no,toolbar=no,menubar=no,
    width=800,height=500,left=100,top=100`;
    let tweet_actions = $('.wpsr-tweet-actions').data('actions');

    if( tweet_actions === 'popup' ){
        $('.wpsr-tweet-actions').children('a').each(function (){
            let $this = $(this);
            let url = $this.attr('href');
            $this.on('click', function(event) {
                event.preventDefault();
                open(url, '_blank', popup_params);
            });
        });
    }

    // show/hide reviews badge template
    $('.wpsr-reviews-badge-btn').on('click', function (e) {
        let display_mode = $(this).data('display_mode');

        if(display_mode === 'popup' || display_mode === 'form_shortcode_id' || display_mode === 'native_form' || display_mode === 'none') {
            e.preventDefault();
            if(display_mode === 'form_shortcode_id' || display_mode === 'native_form'){
                let templateId = $(this).data('badge_id');
                $('.wpsr-reviews-badge-'+templateId+' .wpsr-reviews-form-popup-overlay').toggleClass("wpsr_has_fluent_forms_shortcode");
            }
            if(display_mode === 'popup') {
                let templateId = $(this).data('badge_id');
                $('#wpsr-reviews-grid-'+templateId+',#wpsr-reviews-badge-'+templateId).toggleClass('active');
            }
        }
    });

    // hide badge popup template on close btn click
    $('.wpsr-popup-close').on('click', function (e) {
        e.preventDefault();
        $(this).parents().find('.wpsr-reviews-layout-badge, .wpsr-reviews-badge-btn').removeClass('active');
        $('.wpsr-write-review-modal').removeClass('active');
    });

    // hide badge popup template on document click
    $(document).on('click', function (e) {
        if ($(e.target).has(".active").length) {
            $('.wpsr-reviews-layout-badge, .wpsr-reviews-badge-btn').removeClass('active');
            $('.wpsr-write-review-modal').removeClass('active');
        }
    });

    // Generic product review drawer: open on star rating click
    $(document).on('click', '[data-wpsr-action="open-review-drawer"]', function (e) {
        e.preventDefault();
        var target = $(this).data('wpsr-drawer-target');
        var $drawer = target ? $(target) : $('.wpsr-product-review-drawer').first();
        if ($drawer.length) {
            $drawer.addClass('active');
        }
    });

    // Generic product review drawer: close on close button click
    $(document).on('click', '.wpsr-product-review-drawer .wpsr-popup-close', function (e) {
        e.preventDefault();
        $(this).closest('.wpsr-product-review-drawer').removeClass('active');
    });

    // Generic product review drawer: close on click outside
    $(document).on('click', function (e) {
        var $drawers = $('.wpsr-product-review-drawer.active');
        if (!$drawers.length) return;
        if ($(e.target).closest('[data-wpsr-action="open-review-drawer"]').length) return;
        if (!$(e.target).closest('.wpsr-product-review-drawer').length) {
            $drawers.removeClass('active');
        }
    });

    $('.wpsr-woocommerce-context.wpsr-woocommerce-reviews-form .woocommerce .wpsr-write-review').on('click', function (e) {
        e.preventDefault();
        // Scroll to WooCommerce reviews form
        let $wooReviewsForm = $('#review_form_wrapper');
        
        if ($wooReviewsForm.length) {
            $('html, body').animate({
                scrollTop: $wooReviewsForm.offset().top - 100
            }, 800);
        } 
    });

    // Handle WooCommerce review link redirects when default form is hidden
    if (typeof window.wpsrWooSettings !== 'undefined' && window.wpsrWooSettings.reviewsForm !== 'woocommerce') {
        // Handle review count/rating link clicks
        var reviewLinks = document.querySelectorAll('a[href*="#reviews"], .woocommerce-review-link, .star-rating a, .woocommerce-product-rating a');

        reviewLinks.forEach(function(link) {
            // Skip links that open the review drawer — they have their own handler
            if (link.hasAttribute('data-wpsr-action')) return;
            link.addEventListener("click", function(e) {
                e.preventDefault();

                if (window.wpsrWooSettings.reviewsWidgetPlacement === 'display_outside_tabs') {
                    // If reviews are displayed outside tabs, scroll to that section
                    var reviewsSection = document.querySelector(".wpsr-reviews-outside-tabs");
                    if (reviewsSection) {
                        reviewsSection.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                } else {
                    // If reviews are inside tabs, activate the reviews tab
                    var reviewsTab = document.querySelector(".reviews_tab a, li[role='tab'] a[href*='reviews']");
                    if (reviewsTab) {
                        reviewsTab.click();
                        setTimeout(function() {
                            var tabPanel = document.querySelector(".wpsr-reviews-wrapper");
                            if (tabPanel) {
                                tabPanel.scrollIntoView({ behavior: "smooth", block: "start" });
                            }
                        }, 150);
                    }
                }
            });
        });
    }

    // Anchors with no href carry tabindex instead, so Enter/Space must act like a click.
    // YouTube's 'youtube' play mode adds an href on first click; from then on the browser handles it.
    $(document).on('keydown', '.wpsr-ig-playmode[tabindex], .wpsr-yt-video-playmode[tabindex]', function (e) {
        if (this.hasAttribute('href')) {
            return;
        }
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    // Media events do not bubble, so these listen on the capture phase.
    document.addEventListener('play', function (e) {
        let video = e.target;
        if (!video.classList || !video.classList.contains('wpsr-ig-post-video')) {
            return;
        }
        document.querySelectorAll('.wpsr-ig-post-video').forEach(function (other) {
            if (other !== video) {
                other.pause();
            }
        });
        let post = video.closest('.wpsr-ig-post');
        if (post) {
            post.classList.add('wpsr-ig-post-playing');
        }
    }, true);

    ['pause', 'ended'].forEach(function (eventName) {
        document.addEventListener(eventName, function (e) {
            let video = e.target;
            if (!video.classList || !video.classList.contains('wpsr-ig-post-video')) {
                return;
            }
            let post = video.closest('.wpsr-ig-post');
            if (post) {
                post.classList.remove('wpsr-ig-post-playing');
            }
        }, true);
    });

    $(document).on('click', '.wpsr-ig-playmode', function (e) {
        let playMode = $(this).data('playmode');
        let feedId = $(this).data('index');

        if (playMode !== 'inline') {
            return;
        }

        let clickedVideo = $('#wpsr-video-play-' + feedId).closest('.wpsr-ig-post').find('video').get(0);
        if (!clickedVideo) {
            return;
        }
        
        if (e.target === clickedVideo) {
            return;
        }

        e.preventDefault(); // prevent default if it’s a link/button

        if (clickedVideo.paused) {
            // Some browsers require the play() to be called after a user interaction
            clickedVideo.play().catch((err) => {
            });
        } else {
            clickedVideo.pause();
        }
    });
});
