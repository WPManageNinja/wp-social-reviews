export const reviewsHeaderMixin = {
    data() {
        return {
            // Template-specific data can be overridden in components
        }
    },
    computed: {
        shouldShowHeader() {
            return this.has_pro && this.metaStyles.show_header === 'true' && Object.keys(this.businessInfo.platforms).length;
        },
        
        businessInfoWrapperClasses() {
            const baseClasses = [
                this.isDisplayBlock ? 'wpsr-display-block' : '',
                this.firstPlatformName && this.businessInfo.total_platforms === 1 ? this.firstPlatformName : 'wpsr-has-multiple-reviews-platform',
                this.businessInfo.platforms && this.businessInfo.total_platforms === 1 ? this.getPlatformName(this.businessInfo.platforms) : ''
            ];
            
            // Template-specific classes can be added by overriding this computed property
            return this.getTemplateSpecificClasses(baseClasses);
        },
        
        isDisplayBlock() {
            return this.metaStyles.templateType === 'badge' ||
                this.metaStyles.templateType === 'notification';
        },
        
        firstPlatformName() {
            const firstKey = Object.keys(this.businessInfo.platforms)[0];
            return firstKey ? this.businessInfo.platforms[firstKey].platform_name : '';
        },
        
        hasMultipleBusinesses() {            
            return this.businessInfo.total_business > 1 && this.hasSelectedPlatformMultipleBusiness(this.businessInfo.platforms);
        },
        
        shouldShowSinglePlatformIcon() {
            let platformName = this.getPlatformNameForSinglePlatform();

            if(platformName) {
                return this.show_platform_icon(platformName, this.metaStyles);
            }
        },
        
        filteredPlatforms() {
            return this.filterPlatformName(this.businessInfo.platforms);
        },
        
        shouldShowTitle() {
            return this.metaStyles.display_header_business_name === true &&
                this.metaStyles.platform.length;
        },
        
        headerTitle() {
            if (this.metaStyles.custom_title_text.length) {
                return this.metaStyles.custom_title_text;
            }
            return this.metaStyles.platform.length > 1 ? this.$t('Overall Rating') : this.$t('Rating');
        },
        
        shouldShowRating() {
            return this.businessInfo.average_rating &&
                this.metaStyles.display_header_rating === true &&
                Number(this.businessInfo.average_rating);
        },
        
        formattedRating() {
            return Number(this.businessInfo.average_rating).toFixed(1);
        },
        
        shouldShowRatingStars() {
            return this.shouldShowRating &&
                !(this.metaStyles.platform.length === 1 && this.firstPlatformName === 'booking.com');
        },
        
        shouldShowReviewCount() {
            return this.metaStyles.display_header_reviews === true &&
                this.businessInfo.total_rating;
        },
        
        formattedReviewCount() {
            return this.metaStyles.custom_number_of_reviews_text.replace(
                '{total_reviews}',
                `<span>${Number(this.businessInfo.total_rating).toLocaleString()}</span>`
            );
        },
        
        shouldShowWriteReview() {
            return this.metaStyles.display_header_write_review === true &&
                this.businessInfo;
        },
        
        // Template2-specific computeds (can be used by Template2 or other templates)
        isBookingComOnly() {
            return this.metaStyles.platform.length === 1 && 
                   this.firstPlatformName === 'booking.com';
        },
        
        shouldShowRatingBreakdown() {
            // Check if we have pre-calculated rating breakdown from backend
            if (this.businessInfo.rating_breakdown && this.businessInfo.rating_breakdown.length > 0) {
                return this.businessInfo.total_rating && this.businessInfo.average_rating;
            }
            
            // Fallback to checking filtered reviews (legacy behavior)
            return this.businessInfo.total_rating && 
                   this.businessInfo.average_rating && 
                   this.reviews && this.reviews.length > 0;
        },
        
        bookingPointRanges() {
            return [
                { label: '9-10', min: 9, max: 10 },
                { label: '7-8', min: 7, max: 8.9 },
                { label: '5-6', min: 5, max: 6.9 },
                { label: '3-4', min: 3, max: 4.9 },
                { label: '1-2', min: 1, max: 2.9 }
            ];
        },
        
        // Debug helper to check if rating breakdown data is available
        hasPreCalculatedRatingBreakdown() {
            return this.businessInfo.rating_breakdown && this.businessInfo.rating_breakdown.length > 0;
        }
    },
    
    methods: {
        // Template-specific class customization hook
        getTemplateSpecificClasses(baseClasses) {
            // Default implementation returns base classes
            // Can be overridden in components for template-specific styling
            return baseClasses;
        },
        
        // Rating breakdown methods (primarily for Template2 but can be used by others)
        getStarCount(starRating) {
            // First check if we have pre-calculated rating breakdown from backend
            if (this.businessInfo.rating_breakdown && this.businessInfo.rating_breakdown.length > 0) {
                const starBreakdown = this.businessInfo.rating_breakdown.find(item => 
                    item.type === 'star_rating' && item.star === starRating
                );
                return starBreakdown ? starBreakdown.count : 0;
            }
            
            // Fallback to calculating from filtered reviews (legacy behavior)
            if (!this.reviews || !this.reviews.length) {
                return 0;
            }
            
            return this.reviews.filter(review => {
                let rating = parseFloat(review.rating);
                
                // If this is a booking.com review in a mixed platform setup, normalize it to 5-star scale
                if (review.platform_name === 'booking.com' && !this.isBookingComOnly) {
                    rating = rating / 2;
                }
                
                const roundedRating = Math.floor(rating);
                return roundedRating === starRating;
            }).length;
        },
        
        getStarPercentage(starRating) {
            // First check if we have pre-calculated rating breakdown from backend
            if (this.businessInfo.rating_breakdown && this.businessInfo.rating_breakdown.length > 0) {
                const starBreakdown = this.businessInfo.rating_breakdown.find(item => 
                    item.type === 'star_rating' && item.star === starRating
                );
                if (starBreakdown) {
                    // Convert percentage string (e.g., "17.4%") to number
                    const percentage = starBreakdown.percentage;
                    return typeof percentage === 'string' ? 
                        parseFloat(percentage.replace('%', '')) : percentage;
                }
                return 0;
            }
            
            // Fallback to calculating from filtered reviews (legacy behavior)
            const starCount = this.getStarCount(starRating);
            const totalReviews = this.businessInfo.total_rating || this.reviews.length;
            
            if (!totalReviews || totalReviews === 0) {
                return 0;
            }
            
            return Math.round((starCount / totalReviews) * 100);
        },
        
        getBookingCount(minRating, maxRating) {
            // First check if we have pre-calculated rating breakdown from backend
            if (this.businessInfo.rating_breakdown && this.businessInfo.rating_breakdown.length > 0) {
                const bookingBreakdown = this.businessInfo.rating_breakdown.find(item => 
                    item.type === 'booking_range' && item.min === minRating && item.max === maxRating
                );
                return bookingBreakdown ? bookingBreakdown.count : 0;
            }
            
            // Fallback to calculating from filtered reviews (legacy behavior)
            if (!this.reviews || !this.reviews.length) {
                return 0;
            }
            
            return this.reviews.filter(review => {
                const rating = parseFloat(review.rating);
                return rating >= minRating && rating <= maxRating;
            }).length;
        },
        
        getBookingPercentage(minRating, maxRating) {
            // First check if we have pre-calculated rating breakdown from backend
            if (this.businessInfo.rating_breakdown && this.businessInfo.rating_breakdown.length > 0) {
                const bookingBreakdown = this.businessInfo.rating_breakdown.find(item => 
                    item.type === 'booking_range' && item.min === minRating && item.max === maxRating
                );
                if (bookingBreakdown) {
                    // Convert percentage string (e.g., "17.4%") to number
                    const percentage = bookingBreakdown.percentage;
                    return typeof percentage === 'string' ? 
                        parseFloat(percentage.replace('%', '')) : percentage;
                }
                return 0;
            }
            
            // Fallback to calculating from filtered reviews (legacy behavior)
            const count = this.getBookingCount(minRating, maxRating);
            const totalReviews = this.businessInfo.total_rating || this.reviews.length;
            
            if (totalReviews === 0) {
                return 0;
            }
            
            return Math.round((count / totalReviews) * 100);
        },
        
        getStarIcons(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    stars += '<span class="wpsr-star-filled">★</span>';
                } else {
                    stars += '<span class="wpsr-star-empty">☆</span>';
                }
            }
            return stars;
        },
        
        // Platform icon display helper
        shouldShowPlatformIcon(platform) {
            if (!platform || !platform.platform_name) {
                return false;
            }
            return this.show_platform_icon(platform.platform_name, this.metaStyles);
        },
        
        // Helper method to get platform name for single platform scenarios
        getPlatformNameForSinglePlatform() {
            if (this.businessInfo.platform_name && this.businessInfo.platform_name.trim() !== '') {
                return this.businessInfo.platform_name;
            }
            
            if (this.businessInfo.platforms && Object.keys(this.businessInfo.platforms).length > 0) {
                const firstKey = Object.keys(this.businessInfo.platforms)[0];
                if (firstKey && this.businessInfo.platforms[firstKey]) {
                    const platformName = this.businessInfo.platforms[firstKey].platform_name;
                    if (platformName && platformName.trim() !== '') {
                        return platformName;
                    }
                }
            }

            if (this.metaStyles.platform && this.metaStyles.platform.includes('woocommerce')) {
                return 'woocommerce';
            }
            
            return null;
        }
    }
};