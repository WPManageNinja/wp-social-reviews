<template>
  <div class="wpsr-all-reviews" :class="[template_meta.templateType === 'slider' && has_pro ? 'swiper-wrapper' : 'wpsr-row']">
    <div :class="reponsiveClasses" v-for="(review, index) in reviews" :key="index">
      <div class="wpsr-review-template wpsr-testimonial-template-two"
           :data-index="index"
           :data-review_platform="review.platform_name"
           :data-media_id="review.review_id"
           :data-source_id="review.source_id"
           :data-image_size="template_meta.resolution"
           :class="[ review.platform_name ? 'wpsr-review-template-'+review.platform_name : '']"
           :style="template_meta.equal_height === 'true' ? { 'height': template_meta.equalHeightLen +'px' }: '' "
      >
        <ReviewerImage
            :image_settings="image_settings"
            :isImage="template_meta.reviewer_image"
            :reviewerUrl="review.reviewer_url"
            :imageUrl="review.reviewer_img"
            :sourceId="review.source_id"
            :platformName="review.platform_name"
            :mediaUrl="review.reviewer_img"
            :enableExternalLink="template_meta.enableExternalLink"
        />
        <transition name="fade">
          <ReviewRating
              :isReviewerRating="template_meta.reviewerrating"
              :starStyle="template_meta.rating_style"
              :reviewerRating="review.rating"
              :platformName="review.platform_name"
              :recommendationType="review.recommendation_type"
          />
        </transition>
        <ReviewTime
            :isReviewTime="template_meta.timestamp"
            :reviewTime="review.review_time"
            :templateMeta="template_meta"
        />
        <ReviewTitle :template_meta="template_meta" :review="review"></ReviewTitle>
        <div style="display:block">
          <ReviewerText
              :reviewFields="review.fields"
              :isReviewerText="template_meta.isReviewerText"
              :reviewerText="review.reviewer_text"
              :contentLanguage="template_meta.contentLanguage"
              :contentType="template_meta.contentType"
              :contentLength="template_meta.content_length"
              @expandContent="expandContent"
          />
        </div>
        <div class="wpsr-review-header">
          <div class="wpsr-review-info">
            <ReviewerName
                :isReviewerName="template_meta.reviewer_name"
                :reviewerName="review.reviewer_name"
                :reviewerUrl="review.reviewer_url"
                :sourceId="review.source_id"
                :platformName="review.platform_name"
                :enableExternalLink="template_meta.enableExternalLink"
                :enable-verified-badge="shouldShowVerifiedBadge(review)"
                :verifiedBadgeTooltipText="template_meta.verified_badge_tooltip_text"
            />
            <AuthorPosition
                v-if="review.fields"
                :displayAuthorPosition="template_meta.author_position"
                :displayAuthorCompanyName="template_meta.author_company_name"
                :authorPosition="review.fields.author_position"
                :author_company_name="review.fields.author_company"
            />
            <AuthorWebsiteLogo
                v-if="review.fields"
                :displayWebsiteLogo="template_meta.website_logo"
                :siteLogo="review.fields.author_website_logo"
                :siteUrl="review.fields.author_website_url"
            />
          </div>
        </div>
      </div>
    </div>
    <!--                        <img v-if="!Object.entries(reviews).length" class="wpsr-placeholder-image" :src="assets_url+ '/images/icon/placeholder-template.png'" alt="Placeholder Image">-->
  </div>
</template>

<script type="text/babel">
import ReviewerImage from './../elements/Image';
import ReviewerName from './../elements/ReviewerName';
import ReviewTime from './../elements/ReviewTime';
import AuthorPosition from './../elements/AuthorPosition';
import AuthorWebsiteLogo from './../elements/AuthorWebsiteLogo';
import ReviewRating from './../elements/ReviewerRating';
import ReviewerText from './../elements/ReviewerText';
import ReviewTitle from "../elements/ReviewTitle";
import {reviewsMixin} from "../../../../mixins/reviewsMixin";

export default {
  props:['template_meta', 'reviews', 'image_settings'],
  mixins: [reviewsMixin],
  components: {
    ReviewerText,
    ReviewerImage,
    ReviewerName,
    ReviewTime,
    AuthorPosition,
    AuthorWebsiteLogo,
    ReviewRating,
    ReviewTitle
  },
  methods: {
    expandContent(val) {
      this.$emit('contentExpand',true);
    }
  }
}
</script>