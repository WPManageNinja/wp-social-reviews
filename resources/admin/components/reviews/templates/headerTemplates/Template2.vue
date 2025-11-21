<template>
    <div class="wpsr-business-info-wrapper wpsr-template-2-wrapper">
        <div v-if="shouldShowHeader"
            class="wpsr-business-info wpsr-template-2"
            :class="businessInfoWrapperClasses"
        >
            <!-- Title on top -->
            <span v-if="shouldShowTitle" class="wpsr-business-title wpsr-reviews-header-template-2-title">
                {{ headerTitle }}
            </span>
            
            <!-- Three Column Layout -->
            <div class="wpsr-reviews-header-template-2-three-columns">
                <!-- Column 1: Overall Rating and Review Count -->
                <div class="wpsr-reviews-header-template-2-column-1">
                    <div class="wpsr-rating-and-count wpsr-reviews-header-template-2-overall-rating-wrapper">
                        <!-- Platform Icons/Logo -->
                        <div v-if="hasMultipleBusinesses" class="wpsr-business-info-paltforms wpsr-reviews-header-template-2-platforms">
                            <div v-for="(platform, index ) in filteredPlatforms"
                                :key="index"
                                class="wpsr-reviews-header-template-2-platform-item"
                                v-if="shouldShowPlatformIcon(platform)">
                                <img :src="get_platform_icon(platform.platform_name, 'small')" :alt="platform.platform_name">
                            </div>
                        </div>

                        <div v-else-if="shouldShowSinglePlatformIcon" class="wpsr-business-info-logo wpsr-reviews-header-template-2-logo">
                            <img :src="get_platform_icon(businessInfo.platform_name, 'default')" :alt="businessInfo.platform_name">
                        </div>

                        <!-- Row 1: Star Rating and Overall Score -->
                        <div class="wpsr-reviews-header-template-2-overall-rating">
                            <div v-if="shouldShowRatingStars"
                                class="wpsr-rating wpsr-reviews-header-template-2-stars"
                                v-html="ratingIcon(formattedRating)">
                            </div>
                            <span v-if="shouldShowRating" class="wpsr-total-rating wpsr-reviews-header-template-2-total-rating">
                                {{ formattedRating }} out of 5
                            </span>
                        </div>
                        
                        <!-- Row 2: Based on Reviews Count -->
                        <div v-if="shouldShowReviewCount" class="wpsr-total-reviews wpsr-reviews-header-template-2-review-count" v-html="formattedReviewCount"></div>
                    </div>
                </div>

                <!-- Column 2: Star Rating Breakdown -->
                <div class="wpsr-reviews-header-template-2-column-2" v-if="shouldShowRatingBreakdown">
                    
                    <div class="wpsr-reviews-header-template-2-star-breakdown">
                        <!-- For booking.com, show point ranges instead of stars -->
                        <div v-if="isBookingComOnly">
                            <div v-for="range in bookingPointRanges" 
                                :key="range.label" 
                                class="wpsr-reviews-header-template-2-star-row wpsr-clickable-rating-bar"
                                :class="{
                                    'wpsr-active-progressbar-filter': isActiveBookingFilter(range.min, range.max),
                                    'wpsr-disabled-rating-bar': getBookingCount(range.min, range.max) === 0
                                }"
                                @click="getBookingCount(range.min, range.max) > 0 && handleRatingFilter('booking', range.min, range.max)">
                                <div class="wpsr-rating wpsr-reviews-header-template-2-star-label">
                                    <span class="wpsr-reviews-header-template-2-point-range">{{ range.label }}</span>
                                </div>
                                <div class="wpsr-reviews-header-template-2-progress-bar">
                                    <div class="wpsr-reviews-header-template-2-progress-fill" 
                                        :style="{width: getBookingPercentage(range.min, range.max) + '%'}">
                                    </div>
                                </div>
                                <div class="wpsr-reviews-header-template-2-star-count">
                                    {{ getBookingCount(range.min, range.max) }}
                                </div>
                            </div>
                        </div>
                        <!-- For other platforms, show traditional star breakdown -->
                        <div v-else>
                            <div v-for="star in [5, 4, 3, 2, 1]" 
                                :key="star" 
                                class="wpsr-reviews-header-template-2-star-row"
                                :class="[
                                    'wpsr-clickable-rating-bar',
                                    { 
                                        'wpsr-active-progressbar-filter': isActiveStarFilter(star),
                                        'wpsr-disabled-rating-bar': getStarCount(star) === 0
                                    }
                                ]"
                                >
                                <div class="wpsr-rating wpsr-reviews-header-template-2-star-label">
                                    <div class="wpsr-reviews-header-template-2-star-icons" v-html="ratingIcon(star)"></div>
                                </div>
                                <div class="wpsr-reviews-header-template-2-progress-bar">
                                    <div class="wpsr-reviews-header-template-2-progress-fill" 
                                        :style="{width: getStarPercentage(star) + '%'}">
                                    </div>
                                </div>
                                <div class="wpsr-reviews-header-template-2-star-count">
                                    {{ getStarCount(star) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Clear filter button (only show when filter is active) -->
                    <div v-if="hasActiveFilter" class="wpsr-reviews-header-template-2-clear-filter">
                        <p class="wpsr-clear-filter" @click="clearRatingFilter">
                            {{$t('See All reviews')}}
                        </p>
                    </div>
                </div>

                <!-- Column 3: Write Review Button -->
                <div class="wpsr-reviews-header-template-2-column-3">
                    <business-modal-btn v-if="shouldShowWriteReview"
                        class="wpsr-reviews-header-template-2-btn"
                        :businessInfo="businessInfo"
                        :reviews="reviews"
                        :metaStyles="metaStyles"
                        :btnText="metaStyles.custom_write_review_text"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import BusinessModalBtn from '../elements/BusinessModalBtn';
import { ReviewsBusinessInfoMixin } from "../../../../mixins/ReviewsBusinessInfoMixin";
import { reviewsHeaderMixin } from "../../../../mixins/reviewsHeaderMixin";

export default {
    props: ['businessInfo', 'reviews', 'metaStyles', 'hasActiveFilter', 'activeFilterData'],
    mixins: [ReviewsBusinessInfoMixin, reviewsHeaderMixin],
    components: {
      BusinessModalBtn,
    },
    methods: {
        // Override the template-specific classes method for Template2
        getTemplateSpecificClasses(baseClasses) {
            return [
                'wpsr-template-2-wrapper',
                this.isDisplayBlock ? 'wpsr-display-block wpsr-template-2-block' : '',
                this.firstPlatformName && this.businessInfo.total_platforms === 1 ? this.firstPlatformName + ' wpsr-template-2-single' : 'wpsr-has-multiple-reviews-platform wpsr-template-2-multiple',
                this.businessInfo.platforms && this.businessInfo.total_platforms === 1 ? this.getPlatformName(this.businessInfo.platforms) + ' wpsr-template-2-platform' : ''
            ];
        },

        handleRatingFilter(type, minRating, maxRating) {
            // Emit event to parent components to handle filtering
            this.$emit('rating-filter', {
                type: type, // 'star' or 'booking'
                minRating: minRating,
                maxRating: maxRating,
                filterType: 'rating' // This will help distinguish from future sorting events
            });
        },

        clearRatingFilter() {
            // Emit clear filter event
            this.$emit('rating-filter', {
                type: 'clear',
                filterType: 'clear'
            });
        },

        isActiveStarFilter(star) {
            return this.activeFilterData && 
                   this.activeFilterData.type === 'star' &&
                   this.activeFilterData.minRating === star &&
                   this.activeFilterData.maxRating === star;
        },

        isActiveBookingFilter(minRating, maxRating) {
            return this.activeFilterData && 
                   this.activeFilterData.type === 'booking' &&
                   this.activeFilterData.minRating === minRating &&
                   this.activeFilterData.maxRating === maxRating;
        }
    }
}
</script>