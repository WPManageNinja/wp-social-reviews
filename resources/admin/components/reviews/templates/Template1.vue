<template>
<div class="wpsr-all-reviews" :class="reviewsWrapperClasses">
  <div :class="reponsiveClasses" v-for="(review, index) in latestReviews" :key="index">
      <div class="wpsr-review-template wpsr-review-template-one"
           :data-index="index"
           :data-review_platform="review.platform_name"
           :data-media_id="review.review_id"
           :data-source_id="review.source_id"
           :data-image_size="template_meta.resolution"
           :data-product_thumbnail="review.fields && review.fields.product_thumbnail ? review.fields.product_thumbnail[0] : ''"
           :data-product_name="review.fields && review.fields.product_name ? review.fields.product_name : ''"
           :class="review.platform_name ? 'wpsr-review-template-' + review.platform_name : ''"
           :style="template_meta.equal_height === 'true' && template_meta.contentType === 'excerpt' ? { 'height': template_meta.equalHeightLen +'px' }: '' "
      >
          <ReviewerImage
              :image_settings="image_settings"
              :isImage="getReviewerIconImage(review)"
              :reviewerUrl="review.reviewer_url"
              :sourceId="review.source_id"
              :platformName="review.platform_name"
              :imageUrl="review.reviewer_img"
              :mediaUrl="(review.platform_name === 'ai') ? review.reviewer_img : review.media_url"
              :enableExternalLink="externalLink(review)"
              :key="review.reviewer_img"
          />
          <div class="wpsr-review-header">
              <ReviewPlatform
                  :template_meta="template_meta"
                  :platformName="review.platform_name"
                  :customLogo="customLogoSrc(review.source_id, businessInfo)"
              />
              <div class="wpsr-review-info">
                  <ReviewerName
                      :isReviewerName="template_meta.reviewer_name"
                      :reviewerName="review.reviewer_name"
                      :sourceId="review.source_id"
                      :platformName="review.platform_name"
                      :reviewerUrl="review.reviewer_url"
                      :enableExternalLink="externalLink(review)"
                      :enable-verified-badge="shouldShowVerifiedBadge(review)"
                      :verifiedBadgeTooltipText="template_meta.verified_badge_tooltip_text"
                  />
                  <span v-if="review.platform_name !== 'ai'">
                      <ReviewRating
                          :isReviewerRating="template_meta.reviewerrating"
                          :starStyle="template_meta.rating_style"
                          :reviewerRating="review.rating"
                          :platformName="review.platform_name"
                          :recommendationType="review.recommendation_type"
                          :platformsArray="template_meta.platform"
                      />
                      <ReviewTime
                          :isReviewTime="template_meta.timestamp"
                          :reviewTime="review.review_time"
                          :templateMeta="template_meta"
                      />
                  </span>

                  <span v-else>
                      <div class="wpsr-total-review-count" v-html="template_meta.custom_number_of_reviews_text.replace('{total_reviews}', `<span>${Number(total_rating).toLocaleString()}</span>`)">
                      </div>
                      <ReviewRating
                          :isReviewerRating="template_meta.reviewerrating"
                          :starStyle="template_meta.rating_style"
                          :reviewerRating="average_rating"
                          :platformName="review.platform_name"
                          :recommendationType="review.recommendation_type"
                          :platformsArray="template_meta.platform"
                      />
                  </span>
              </div>
          </div>
          <ReviewTitle :template_meta="template_meta" :review="review"></ReviewTitle>
          <ReviewBody
              :review="review"
              :template_meta="template_meta"
              @expandcontent="expandContent"
          />
      </div>
  </div>
</div>
</template>

<script type="text/babel">
import ReviewerImage from './elements/Image';
import ReviewPlatform from './elements/PlatformIcon';
import ReviewerName from './elements/ReviewerName';
import ReviewTime from './elements/ReviewTime';
import ReviewRating from './elements/ReviewerRating';
import ReviewerText from './elements/ReviewerText';
import BusinessInfo from './elements/BusinessInfo';
import ReviewTitle from "./elements/ReviewTitle";
import ReviewBody from "./elements/ReviewBody";
import {reviewsMixin} from '../../../mixins/reviewsMixin';

export default {
    props: ['template_meta', 'reviews', 'image_settings', 'average_rating', 'total_rating', 'reviewsWrapperClasses', 'businessInfo'],
    mixins: [reviewsMixin],
    components: {
        ReviewerImage,
        ReviewPlatform,
        ReviewerName,
        ReviewTime,
        ReviewRating,
        ReviewerText,
        BusinessInfo,
        ReviewTitle,
        ReviewBody
    },
    data() {
        return {
            latestReviews: this.reviews,
        };
    },
    methods: {
        expandContent(val) {
            this.$emit('contentExpand',true);
        },
    },
    watch: {
        reviews: {
            deep: true,
            handler(newVal) {
                this.latestReviews = [...newVal];
            },
        },
    }
}
</script>