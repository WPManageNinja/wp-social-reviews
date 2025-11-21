<template>
    <div v-if="shouldShowHeader"
         class="wpsr-business-info wpsr-template-1"
         :class="businessInfoWrapperClasses"
    >
        <div class="wpsr-business-info-left wpsr-template-1-left">
          <div v-if="filteredPlatforms.length >= 0" class="wpsr-business-info-paltforms wpsr-template-1-platforms">
            <div v-for="(platform, index ) in filteredPlatforms" :key="index" class="wpsr-template-1-platform-item">
              <img v-if="platform.logo && platform.logo !== ''" :src="platform.logo" alt="">
              <div v-else-if="shouldShowPlatformIcon(platform)">
                <img :src="get_platform_icon(platform.platform_name, 'small')" :alt="platform.platform_name">
              </div>
            </div>
            <span v-if="shouldShowTitle" class="wpsr-business-title wpsr-template-1-title">
              {{ headerTitle }}
            </span>
          </div>

          <div v-else class="wpsr-business-info-logo wpsr-template-1-logo">
            <img v-if="businessInfo.logo && businessInfo.platform_name !== 'custom'" :src="businessInfo.logo" alt="">
            <img v-else-if="shouldShowSinglePlatformIcon && businessInfo.platform_name !== 'custom'" :src="get_platform_icon(businessInfo.platform_name, 'default')" :alt="businessInfo.platform_name">
            <span v-if="shouldShowTitle" class="wpsr-business-title wpsr-template-1-title">
              {{ headerTitle }}
            </span>
          </div>
          <div class="wpsr-rating-and-count wpsr-template-1-rating">
             <span v-if="shouldShowRating" class="wpsr-total-rating wpsr-template-1-total-rating">
                {{ formattedRating }}
              </span>
            <div v-if="shouldShowRatingStars"
                 class="wpsr-rating wpsr-template-1-stars"
                 v-html="ratingIcon(formattedRating)">
            </div>
            <div v-if="shouldShowReviewCount"
                 class="wpsr-total-reviews wpsr-template-1-reviews"
                 v-html="formattedReviewCount">
            </div>
          </div>
        </div>
        <business-modal-btn v-if="shouldShowWriteReview"
            class="wpsr-template-1-btn"
            :businessInfo="businessInfo"
            :reviews="reviews"
            :metaStyles="metaStyles"
            :btnText="metaStyles.custom_write_review_text"
        />
    </div>
</template>

<script type="text/babel">
import BusinessModalBtn from '../elements/BusinessModalBtn';
import { ReviewsBusinessInfoMixin } from "../../../../mixins/ReviewsBusinessInfoMixin";
import { reviewsHeaderMixin } from "../../../../mixins/reviewsHeaderMixin";

export default {
    props: ['businessInfo', 'reviews', 'metaStyles'],
    mixins: [ReviewsBusinessInfoMixin, reviewsHeaderMixin],
    components: {
      BusinessModalBtn,
    },
    methods: {
        // Override the template-specific classes method for Template1
        getTemplateSpecificClasses(baseClasses) {
            return baseClasses; // Template1 uses the base classes as-is
        }
    }
}
</script>