let $ = jQuery;
let reviewId = null;
let currentImageIndex = 0;
let allReviewImages = [];

export default {
    init: function (e, $this) {
        e.preventDefault();
        reviewId = $($this).data('review-id');
        currentImageIndex = parseInt($($this).data('image-index') || 0, 10);

        // Get all review images from the current review
        this.collectAllReviewImages();

        // Append the feed content to the popup wrapper
        this.appendFeed( $('body'));
    },

    collectAllReviewImages() {
        // Get all review images from the current review
        allReviewImages = [];

        $('[data-review-id="' + reviewId + '"]').each(function(index) {
            allReviewImages.push({
                src: $(this).attr('src'),
                alt: $(this).attr('alt') || 'Review Image',
                index: index
            });
        });
    },

    appendFeed(parent) {
        // If we have images, render and append the HTML
        if (allReviewImages.length > 0) {
            document.body.classList.add('wpsr-feed-popup-active');
            
            let data = this.renderPopupHtml();
            parent.append(data);

            // Initialize pagination if there are multiple images
            if (allReviewImages.length > 1) {
                this.initializePagination();
            }
        }
    },

    initializePagination() {
        let $that = this;
        
        // Handle next button click
        $('body').off('click', '.wpsr-review-next-btn').on('click', '.wpsr-review-next-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $that.navigateImage('next');
        });

        // Handle prev button click
        $('body').off('click', '.wpsr-review-prev-btn').on('click', '.wpsr-review-prev-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $that.navigateImage('prev');
        });

        // Update button states
        this.updatePaginationButtons();
    },

    navigateImage(direction) {
        if (direction === 'next' && currentImageIndex < allReviewImages.length - 1) {
            currentImageIndex++;
        } else if (direction === 'prev' && currentImageIndex > 0) {
            currentImageIndex--;
        }

        // Update the image
        this.updateCurrentImage();
        this.updatePaginationButtons();
    },

    updateCurrentImage() {
        let currentImage = allReviewImages[currentImageIndex];
        if (currentImage) {
            $('.wpsr-review-popup-current-image').attr('src', currentImage.src);
            $('.wpsr-review-popup-image-counter').text((currentImageIndex + 1) + ' / ' + allReviewImages.length);
        }
    },

    updatePaginationButtons() {
        let $prevBtn = $('.wpsr-review-prev-btn');
        let $nextBtn = $('.wpsr-review-next-btn');

        if (currentImageIndex === 0) {
            $prevBtn.addClass('wpsr-link-disable');
        } else {
            $prevBtn.removeClass('wpsr-link-disable');
        }

        if (currentImageIndex === allReviewImages.length - 1) {
            $nextBtn.addClass('wpsr-link-disable');
        } else {
            $nextBtn.removeClass('wpsr-link-disable');
        }
    },

    renderPopupHtml() {
        let currentImage = allReviewImages[currentImageIndex] || allReviewImages[0];
        let imageCounter = allReviewImages.length > 1 ? 
            '<div class="wpsr-review-popup-image-counter">' + (currentImageIndex + 1) + ' / ' + allReviewImages.length + '</div>' : '';

        return `<div class="wpsr-feed-popup-overlay wpsr-review-feed-popup wpsrm-overlay wpsr_content wpsr-feed-popup-open">
            <div class="wpsr-feed-popup-box-wraper">
                <div class="wpsr-feed-popup-box-wraper-inner wpsrm_inner">
                    ${allReviewImages.length > 1 ?
                        `<div class="wpsr-prev-btn wpsr-review-prev-btn"><span class="icon-angle-left"></span></div>
                         <div class="wpsr-next-btn wpsr-review-next-btn"><span class="icon-angle-right"></span></div>` : ''
                    }
                    
                    <div class="wpsr-feed-popup-close-btn wpsrm_close"></div>
                    
                    <div class="wpsr-feed-popup-media wpsr-review-popup-media">
                        <div class="wpsr-review-popup-photo-media">
                            <img class="wpsr-review-popup-current-image" src="${currentImage ? currentImage.src : ''}" alt="${currentImage ? currentImage.alt : 'Review Image'}"/>
                            ${imageCounter}
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }
};