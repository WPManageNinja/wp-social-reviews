function carouselOptions(sliderSettings) {
    let width = document.getElementsByClassName("wpsr-editor-view-port");
    let widthValue = width[0].offsetWidth;
    let windowWidth = window.innerWidth;
    let responsiveBarDeviceWidth = windowWidth - widthValue;
    
    // Ensure sliderSettings is an object
    if (!sliderSettings || typeof sliderSettings !== 'object') {
        sliderSettings = {};
    }
    
    let options = {
        // loop: true,
        // loopFillGroupWithBlank: true,
        // centeredSlides: true,
        // autoHeight: true, // review readmore overlaps
        slidesPerView: sliderSettings.slides_to_show ? parseInt(sliderSettings.slides_to_show) : 5,
        speed:1000,
        direction: 'horizontal',
        spaceBetween: sliderSettings.spaceBetween ? sliderSettings.spaceBetween : 20,
        observer: true, // Enables observer to watch for changes
        observeParents: true, // Watch for changes in parent elements
        watchSlidesProgress: true, // Watch for slide progress changes
        breakpoints: {
            // when responsive icon is mobile

            [320 + responsiveBarDeviceWidth ] : {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.mobile : 1,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.mobile : 1,
            },
            [480 + responsiveBarDeviceWidth]: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.mobile : 1,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.mobile : 1,
            },
            [640 + responsiveBarDeviceWidth ]: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.tablet : 2,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.tablet : 2,
            },
            [768 + responsiveBarDeviceWidth]: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.tablet : 2,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.tablet : 2,
            },
            [990 + responsiveBarDeviceWidth]: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.desktop : sliderSettings.slides_to_show,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.desktop : sliderSettings.slides_to_scroll,
            },
        }
    };

    if(sliderSettings.autoplay && sliderSettings.autoplay === 'true'){
        options['autoplay'] = {
            delay: parseInt(sliderSettings.autoplay_speed),
            disableOnInteraction: false,
            pauseOnMouseEnter: true
        }
    }
    if(sliderSettings.navigation && (sliderSettings.navigation === 'dot' || sliderSettings.navigation === 'both')){
        options['pagination'] = {
            el: '.wpsr-swiper-pagination',
            clickable: true
        }
    }
    if(sliderSettings.navigation && (sliderSettings.navigation === 'arrow' || sliderSettings.navigation === 'both')){
        options['navigation'] = {
            nextEl: '.wpsr-swiper-next',
            prevEl: '.wpsr-swiper-prev',
        }
    }
    return options;
}

// Utility function to ensure swiper slides have proper height
function ensureSwiperHeight() {
    const swiperContainer = document.querySelector('.swiper-container');
    if (swiperContainer) {
        const slides = swiperContainer.querySelectorAll('.swiper-slide');
        slides.forEach(slide => {
            if (slide.offsetHeight === 0) {
                // Set a minimum height if height is 0
                slide.style.minHeight = '200px';
            }
        });
    }
}

export {carouselOptions, ensureSwiperHeight};