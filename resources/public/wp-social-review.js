import { appendReadMoreButton, carouselOptions, openPopupWindow, animateTextTypingEffect, processAISummaryList } from './helper.js';
import TwitterCard from './twitter-card.js';

jQuery(document).ready(function ($) {
    const FeedPaginator = {
        fetchingId: '',

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

            if (window.wpsr_ajax_params.has_pro) {
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

        maybeMasonryFeed($btn, $feedsWrap, data) {
            const templateType = $btn.data('template_type');

            const column = $feedsWrap.data("column");

            if (templateType === 'masonry') {
                $feedsWrap.find('.wpsr_feeds').masonry('reloadItems')
                if (typeof $feedsWrap.find('.wpsr_feeds').imagesLoaded === 'function') {
                    $feedsWrap.find('.wpsr_feeds').imagesLoaded(function () {
                        if (typeof $feedsWrap.find('.wpsr_feeds').masonry === 'function') {
                            $feedsWrap.find('.wpsr_feeds').masonry({
                                itemSelector: '.wpsr-col-' + column + '',
                            });
                        }
                    });
                } else {
                    if (typeof $feedsWrap.find('.wpsr_feeds').masonry === 'function') {
                        $feedsWrap.find('.wpsr_feeds').masonry({
                            itemSelector: '.wpsr-col-' + column + '',
                        });
                    }
                }
            }
        },
        activeMasonryLayout(masonrySelector){
            if(masonrySelector.length){
                masonrySelector.each(function () {
                    let id = '#' + $(this).attr('id');
                    let column = $(this).data("column");

                    if (typeof $(id).imagesLoaded === 'function') {
                        $(id).imagesLoaded(function () {
                            if (typeof $(id).find('.wpsr-twitter-all-tweets, .wpsr-ig-all-feed, .wpsr-fb-all-feed, .wpsr-tt-all-feed').masonry === 'function') {
                                $(id).find('.wpsr-twitter-all-tweets, .wpsr-ig-all-feed, .wpsr-fb-all-feed, .wpsr-tt-all-feed').masonry({
                                    itemSelector: '.wpsr-col-' + column,
                                });
                            }
                        });
                    } else {
                        if (typeof $(id).find('.wpsr-twitter-all-tweets, .wpsr-ig-all-feed, .wpsr-fb-all-feed, .wpsr-tt-all-feed').masonry === 'function') {
                            $(id).find('.wpsr-twitter-all-tweets, .wpsr-ig-all-feed, .wpsr-fb-all-feed, .wpsr-tt-all-feed').masonry({
                                itemSelector: '.wpsr-col-' + column,
                            });
                        }
                    }
                });
            }
        },
        fetchData($btn) {
            let templateId = $btn.data("template_id"),
                total = parseInt($btn.data("total")),
                prev_page = parseInt($btn.data("page")),
                platform = $btn.data("platform"),
                paginate = parseInt($btn.data("paginate")),
                feed_id = $btn.data("feed_id"),
                feed_type = $btn.data("feed_type");

            if (prev_page * paginate >= total) {
                $btn.hide();
                return false;
            }

            if (templateId == this.fetchingId) {
                return false;
            }

            this.fetchingId = templateId;
            let page = prev_page + 1;

            $btn.prop('disabled', true).addClass('wpsr-spinner-loading');
            $btn.closest('.wpsr-feed-wrap').find('.wpsr_feeds').addClass('wpsr-loading-feeds');

            jQuery.get(wpsr_ajax_params.ajax_url, {
                action: 'wpsr_get_more_feeds',
                page: page,
                template_id: templateId,
                platform: platform,
                feed_type: feed_type,
                feed_id: feed_id,
            }).then(response => {
                    let $feedsWrap = $btn.closest('.wpsr-feed-wrap');
                    if (response.content.trim()) {
                        if(feed_type === 'album_feed') {
                            $feedsWrap.find(`#wpsr-album-feed-${feed_id}`).append(response.content);
                        } else {
                            $feedsWrap.find('.wpsr_feeds').append(response.content);
                        }
                    } else {
                        $btn.hide();
                    }

                    if (platform == 'instagram' || platform == 'twitter' || platform == 'facebook_feed' || platform == 'tiktok' || platform == 'reviews') {
                        $feedsWrap = $btn.closest('.wpsr-feed-wrap')
                        this.maybeMasonryFeed($btn, $feedsWrap, response.content);
                    }

                    if (page * paginate >= total) {
                        $btn.hide();
                    }

                    $btn.data('page', page);

                    appendReadMoreButton(wpsr_ajax_params.read_more, wpsr_ajax_params.read_less);
                })
                .catch(errors => {
                    console.error(errors);
                })
                .always(() => {
                    $btn.removeClass('wpsr-spinner-loading').prop('disabled', false);
                    $btn.closest('.wpsr-feed-wrap').find('.wpsr_feeds').removeClass('wpsr-loading-feeds');
                    this.fetchingId = '';
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
                        container.find('.swiper-slide').attr('tabindex', '0');


                        // Pause autoplay when the container gains focus
                        container.on('focusin', function () {
                            swiper.autoplay.stop();
                        });

                        // Resume autoplay when the container loses focus
                        container.on('focusout mouseleave', function () {
                            swiper.autoplay.start();
                        });

                        // Pause autoplay when a slide gains focus
                        container.find('.swiper-slide *').on('focusin', function (e) {
                            swiper.autoplay.stop();
                        });

                        //
                        // // Resume autoplay when a slide loses focus
                        // container.find('.swiper-slide').on('focusout blur', function () {
                        //     swiper.autoplay.start();
                        // });

                        // Handle keyboard navigation for slides
                        container.find('.swiper-slide').on('keydown', function (e) {
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
            const reviewsMasonry = $('.wpsr-active-masonry-layout');

            if(reviewsMasonry.length) {
                const reviewsColumn = reviewsMasonry.data("column");
                if (typeof reviewsMasonry.imagesLoaded === 'function') {
                    reviewsMasonry.imagesLoaded(function () {
                        if (typeof reviewsMasonry.masonry === 'function') {
                            reviewsMasonry.masonry({
                                itemSelector: '.wpsr-col-' + reviewsColumn + '',
                            });
                        }
                    });
                } else {
                    if (typeof reviewsMasonry.masonry === 'function') {
                        reviewsMasonry.masonry({
                            itemSelector: '.wpsr-col-' + reviewsColumn + '',
                        });
                    }
                }
            }

            //activate masonry layout
            let masonrySelector = $('.wpsr-twitter-masonry, .wpsr-instagram-masonry-activate, .wpsr-facebook-feed-masonry-activate, .wpsr-tiktok-feed-masonry-activate');
            this.activeMasonryLayout(masonrySelector);

            // show hide write a review modal box
            $('.wpsr-write-review-modal-btn').on("click", function (e) {
                e.preventDefault();
                let templateId = $(this).parents('.wpsr-reviews-wrapper').attr('id');
                $('#' + templateId).find('.wpsr-write-review-modal').toggleClass("active");
            });

            $('.wpsr-reviews-form-popup-trigger').on("click", function (e) {
                e.preventDefault();


                let businessInfoWrapper = $(this).closest('.wpsr-business-info-wrapper');
                if (businessInfoWrapper.length && businessInfoWrapper.hasClass('wpsr-woocommerce-reviews-form')) {
                    return;
                }

                let templateId = $(this).parents('.wpsr-reviews-wrapper').attr('id');
                $('#' + templateId).find('.wpsr-reviews-form-popup-overlay').toggleClass("wpsr_has_fluent_forms_shortcode");
            });

            $('.wpsr-reviews-form-popup-overlay .wpsr-popup-close').on('click', function (e) {
                $('.wpsr-reviews-form-popup-overlay').removeClass('wpsr_has_fluent_forms_shortcode');
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
            platform: 'youtube'
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

        if(display_mode === 'popup' || display_mode === 'form_shortcode_id' || display_mode === 'none') {
            e.preventDefault();
            if(display_mode === 'form_shortcode_id'){
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

    $('.wpsr-woocommerce-context.wpsr-woocommerce-reviews-form .wpsr-write-review').on('click', function (e) {
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

    $(document).on('click', '.wpsr-ig-playmode', function (e) {
        let playMode = $(this).data('playmode');
        let feedId = $(this).data('index');

        if (playMode === 'inline') {
            e.preventDefault(); // prevent default if it’s a link/button
            let $clickedPost = $('#wpsr-video-play-' + feedId).closest('.wpsr-ig-post');
            let clickedVideo = $clickedPost.find('video').get(0);

            // Pause all other videos
            $('.wpsr-ig-post-video').each(function () {
                let videoEl = $(this).get(0);
                let $parentPost = $(this).closest('.wpsr-ig-post');
                if ($parentPost[0] !== $clickedPost[0] && videoEl) {
                    videoEl.pause();
                }
            });

            // Play clicked video
            if (clickedVideo) {
                // Some browsers require the play() to be called after a user interaction
                clickedVideo.play().catch((err) => {
                    console.log('Video play was prevented by browser:', err);
                });
            }
        }
    });
});