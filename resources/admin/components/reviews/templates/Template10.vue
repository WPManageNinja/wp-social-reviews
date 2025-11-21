<template>
<div class="wpsr-all-reviews" :class="reviewsWrapperClasses">
    <div :class="reponsiveClasses" v-for="(review, index) in latestReviews" :key="index">
        <div class="wpsr-review-template wpsr-review-template-ten"
        :data-index="index"
        :data-review_platform="review.platform_name"
        :data-media_id="review.review_id"
        :data-source_id="review.source_id"
        :data-image_size="template_meta.resolution"
        :data-product_thumbnail="review.fields && review.fields.product_thumbnail ? review.fields.product_thumbnail[0] : ''"
        :data-product_name="review.fields && review.fields.product_name ? review.fields.product_name : ''"
        :class="review.platform_name ? 'wpsr-review-template-' + review.platform_name : ''"
        :style="template_meta.equal_height === 'true' ? { 'height': template_meta.equalHeightLen +'px' }: '' "
        >


            <!-- Row 2: Main content with image and info -->
            <div class="wpsr-review-main-content-row">
                <!-- First column: Reviewer image -->
                <div class="wpsr-review-image-column">
                    <ReviewerImage
                        :isImage="getReviewerIconImage(review)"
                        :image_settings="image_settings"
                        :reviewerUrl="review.reviewer_url"
                        :imageUrl="review.reviewer_img"
                        :mediaUrl="(review.platform_name === 'ai') ? review.reviewer_img : review.media_url"
                        :sourceId="review.source_id"
                        :platformName="review.platform_name"
                        :enableExternalLink="externalLink(review)"
                        :key="review.reviewer_img"
                    />
                </div>

                <!-- Second column: Name, rating, and content -->
                <div class="wpsr-review-info-column">
                    <!-- Row 1: Reviewer name -->
                    <div class="wpsr-reviewer-name-row">
                        <ReviewerName
                            :isReviewerName="template_meta.reviewer_name"
                            :reviewerName="review.reviewer_name"
                            :reviewerUrl="review.reviewer_url"
                            :sourceId="review.source_id"
                            :platformName="review.platform_name"
                            :enableExternalLink="externalLink(review)"
                            :enable-verified-badge="shouldShowVerifiedBadge(review)"
                            :verifiedBadgeTooltipText="template_meta.verified_badge_tooltip_text"
                        />

                            <div class="wpsr-review-date-row" v-if="review.platform_name !== 'ai'">
                            <div class="wpsr-review-date-container">
                                <ReviewTime
                                    :isReviewTime="template_meta.timestamp"
                                    :reviewTime="review.review_time"
                                    :templateMeta="template_meta"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Rating -->
                    <div class="wpsr-reviewer-rating-row">
                        <template v-if="review.platform_name !== 'ai'">
                            <ReviewRating
                                :isReviewerRating="template_meta.reviewerrating"
                                :starStyle="template_meta.rating_style"
                                :reviewerRating="review.rating"
                                :platformName="review.platform_name"
                                :recommendationType="review.recommendation_type"
                                :platformsArray="template_meta.platform"
                            />
                        </template>
                        <template v-else>
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
                        </template>
                    </div>

                    <!-- Row 3: Review content -->
                    <div class="wpsr-review-content-row">
                        <ReviewTitle :template_meta="template_meta" :review="review"></ReviewTitle>
                        <ReviewBody
                            :review="review"
                            :template_meta="template_meta"
                            @expandcontent="expandContent"
                        />
                    </div>
                </div>
            </div>

            <!-- Row 3: Platform icon -->
            <!-- <div class="wpsr-review-platform-row">
                <div class="wpsr-review-platform-container">
                    <ReviewPlatform
                        :template_meta="template_meta"
                        :platformName="review.platform_name"
                    />
                </div>
            </div> -->
        </div>
    </div>
<!--                      <img v-if="!Object.entries(reviews).length" class="wpsr-placeholder-image" :src="assets_url+ '/images/icon/placeholder-template.png'" alt="Placeholder Image">-->
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
import {reviewsMixin} from '../../../mixins/reviewsMixin';
import ReviewBody from "./elements/ReviewBody.vue";

export default {
    props: ['businessInfo', 'template_meta', 'reviews', 'image_settings', 'average_rating', 'total_rating', 'reviewsWrapperClasses'],
    mixins: [reviewsMixin],
    components: {
        ReviewBody,
        ReviewerImage,
        ReviewPlatform,
        ReviewerName,
        ReviewTime,
        ReviewRating,
        ReviewerText,
        BusinessInfo,
        ReviewTitle
    },
    data() {
        return {
            latestReviews: this.reviews,
        };
    },
    methods: {
        // Removed getReviewerIconImage, now using from mixin
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
    },
}
</script>