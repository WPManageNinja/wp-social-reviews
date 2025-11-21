import {carouselOptions, ensureSwiperHeight} from "../components/helper";

export const jsLibraryMixin = {
    data() {
        return {
            carousel: null,
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
            
            containerSelectors.forEach(selector => {
                const container = document.querySelector(selector);
                if (container) {
                    // Remove attributes/classes from all descendant elements
                    const descendants = container.querySelectorAll('*');
                    descendants.forEach(el => {
                        el.classList.remove('swiper-container');
                        el.removeAttribute('role');
                        el.removeAttribute('aria-label');
                        el.removeAttribute('style');
                    });
                }
            });
        },
        destroy() {
            if (this.carousel !== null) {
                this.carousel.destroy(true, true);
                this.carousel = null;
            }

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

            // Use a longer timeout to ensure DOM is fully rendered
            setTimeout(() => {
                const swiperContainer = document.querySelector('.swiper-container');
                if (swiperContainer) {
                    // Ensure container has proper structure before initializing
                    const slides = swiperContainer.querySelectorAll('.swiper-slide');
                    if (slides.length > 0) {
                        // Ensure slides have proper height before initialization
                        ensureSwiperHeight();
                        
                        this.carousel = new Swiper('.swiper-container', options);
                        
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
            }, 150);
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