import {carouselOptions, ensureSwiperHeight} from "../components/helper";

export const jsLibraryMixin = {
    data() {
        return {
            carousel: null,
            carouselInitTimer: null,
            wpsrMasonry: null,
        }
    },
    methods: {
        cleanSwiperStyles(){
            // DOM cleanup for Swiper
            const containerSelectors = [
                '.wpsr-all-reviews',
                '.wpsr-ig-all-feed',
                '.wpsr-fb-all-feed',
                '.wpsr-tt-all-feed',
                '.wpsr-yt-all-feed',
                '.wpsr-twitter-all-tweets'
            ];
            
            // Only the elements Swiper writes to; the container's swiper-container class is Vue-bound.
            const swiperOwnedSelector = '.swiper-wrapper, .swiper-slide, .swiper-pagination, .swiper-pagination-bullet, .swiper-button-next, .swiper-button-prev';

            containerSelectors.forEach(selector => {
                const container = document.querySelector(selector);
                if (container) {
                    container.querySelectorAll(swiperOwnedSelector).forEach(el => {
                        el.removeAttribute('role');
                        el.removeAttribute('aria-label');
                        el.removeAttribute('style');
                    });
                }
            });
        },
        destroy() {
            // A pending init would otherwise build a second Swiper that this.carousel never tracks.
            clearTimeout(this.carouselInitTimer);
            this.carouselInitTimer = null;

            if (this.carousel !== null) {
                this.carousel.destroy(true, true);
                this.carousel = null;
            }
            // observer:true makes Swiper re-read its slides once Vue swaps their class, so its own cleanup misses them.
            (this.wpsrCarouselSlideEls || []).forEach(el => el.removeAttribute('style'));
            this.wpsrCarouselSlideEls = [];

            if (this.wpsrMasonry !== null) {
                this.wpsrMasonry.destroy();
                this.wpsrMasonry = null;
            }
            this.cleanSwiperStyles();
            this.loading = false;
        },
        activeCarousel() {
            if (!this.has_pro) {
                this.loading = false;
                return;
            }

            this.destroy();

            let carouselSettings =  typeof this.template_meta !== 'undefined' ? this.template_meta.carousel_settings : '';
            if(!carouselSettings) {
                carouselSettings = this.feedConfig && this.feedConfig.feed_settings && this.feedConfig.feed_settings.carousel_settings !== undefined ? this.feedConfig.feed_settings.carousel_settings : {};
            }
            if(this.responsiveBarDevice){
                carouselSettings.responsiveBar = this.responsiveBarDevice;
            }

            let options = carouselOptions(carouselSettings);

            // A macrotask runs after Vue's pending DOM patch; updateAutoHeight below covers images still loading.
            this.carouselInitTimer = setTimeout(() => {
                this.carouselInitTimer = null;
                const swiperContainer = document.querySelector('.swiper-container');
                if (swiperContainer) {
                    // Ensure container has proper structure before initializing
                    const slides = swiperContainer.querySelectorAll('.swiper-slide');
                    if (slides.length > 0) {
                        // Ensure slides have proper height before initialization
                        ensureSwiperHeight();
                        
                        this.carousel = new Swiper('.swiper-container', options);
                        this.wpsrCarouselSlideEls = Array.from(slides);
                        
                        // Force height recalculation after initialization
                        if (this.carousel && this.carousel.autoHeight) {
                            setTimeout(() => {
                                this.carousel.updateAutoHeight();
                                // Double check height after update
                                ensureSwiperHeight();
                            }, 100);
                        }
                    }
                }
                this.loading = false;
            }, 0);
        },
        activeMasonry(containerClass, feedWrapperClass) {
            if (!this.has_pro) {
                this.loading = false;
                return;
            }
            let columns = typeof this.template_meta !== 'undefined'  ? this.template_meta.responsive_column_number.desktop : '';
            if(!columns) {
                columns = this.feedConfig.feed_settings.responsive_column_number.desktop;
            }
            if (this.carousel !== null) {
                this.carousel.destroy(true, true);
                this.carousel = null;
                (this.wpsrCarouselSlideEls || []).forEach(el => el.removeAttribute('style'));
                this.wpsrCarouselSlideEls = [];
                this.cleanSwiperStyles();
            }
            

            if (this.wpsrMasonry !== null) {
                this.wpsrMasonry.destroy();
                this.wpsrMasonry = null;
            }

            let id = jQuery(containerClass);
            let that = this;
            let el = document.querySelector(containerClass + " " + feedWrapperClass);

            //let column = jQuery('.wpsr-active-masonry-layout').data("column");
            id.imagesLoaded(function () {
                that.wpsrMasonry = new Masonry(el, {
                    itemSelector: '.wpsr-col-'+columns,
                });
            });

            this.loading = false;
        },
    }
}