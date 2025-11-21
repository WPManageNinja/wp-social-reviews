<template>
    <div class="wpsr-review-content" v-if="!isAiSummary && isReviewerText === 'true' && reviewerText && reviewerText.length">
        <p v-if="reviewerText && contentType === 'excerpt'" class="wpsr_add_read_more wpsr_show_less_content"
        v-html="$addReadMoreBtn(reviewerText, contentLength, contentLanguage)">
        </p>
        <p class="wpsr-review-full-content" v-else v-html="$safeContentLanguage(reviewerText, contentLanguage)"></p>

      <ReviewImages
          v-if="hasReviewImages"
          :reviewImages="processedReviewImages"
          :templateId="review ? review.template_id : null"
          :reviewId="review ? review.id : null"
          :reviewerName="review ? review.reviewer_name : ''"
          :reviewText="review ? review.reviewer_text : ''"
          :rating="review ? review.rating : null"
          :reviewTime="review ? review.review_time : ''"
          :platformName="review ? review.platform_name : ''"
      />
    </div>
    <div class="wpsr-review-content" v-else-if="isAiSummary && isReviewerText === 'true' && reviewerText && reviewerText.length">
        <p class="animated-reviewer-text" :class="isAiReadmoreEnabled ? 'wpsr_add_read_more wpsr_show_less_content' : 'wpsr-review-full-content'">
            <span v-if="isTextTypingAnimationEnabled">{{ animatedText }}</span>
            <span v-else>{{ displayText }}</span>
            <span v-if="shouldShowReadMore()">
                <span class='wpsr_add_read_more_slice_content'> {{hiddencontent}} </span>
                <span class='wpsr_read_more'>{{this.$t('Read More')}}</span>
                <span class='wpsr_read_less'>{{ this.$t('Read Less')}}</span>
            </span>
        </p>
    </div>
</template>

<script type="text/babel">
import debounced from "lodash/debounce";
import ReviewImages from "./ReviewImages.vue";

export default {
  props: {
      isReviewerText: String,
      reviewerText: String,
      contentType: String,
      contentLength: Number,
      contentLanguage: String,
      isAiSummary: Boolean,
      isAiReadmoreEnabled: Boolean,
      isTextTypingAnimationEnabled: Boolean,
      reviewFields: Array,
      review: Object
  },
  components:{
    ReviewImages
  },
  data() {
        return {
            animatedText: '',
            displayText: '',
            hiddencontent: '',
            animationInterval: null,
            aiSummary : '',
            isAnimating: false
        }
    },
    computed: {
      hasReviewImages() {
        return this.processedReviewImages && this.processedReviewImages.length > 0;
      },

      processedReviewImages() {
        if (!this.reviewFields || this.reviewFields.review_images === undefined) {
          return [];
        }

        // Handle direct array format like ["url1", "url2"]
        if (Array.isArray(this.reviewFields.review_images)) {
          return this.reviewFields.review_images;
        }

        if (Array.isArray(this.reviewFields)) {
          return this.reviewFields.review_images;
        }

        if (typeof this.reviewFields.review_images === "string" && this.reviewFields.review_images.trim() !== "") {
          return this.reviewFields.review_images.split(",").map((img) => img.trim());
        }

        return [];
      }
    },
    methods: {
        animateReviewerText() {
            if (this.isAnimating) return;

            this.isAnimating = true;
            this.animatedText = '';

            let textToAnimate = this.reviewerText;
            if (this.isAiReadmoreEnabled) {
                const words = this.reviewerText.split(' ');
                textToAnimate = words.slice(0, this.contentLength).join(' ');
                this.hiddencontent = words.slice(this.contentLength).join(' ');
            }
            
            let index = 0;
            this.animationInterval = setInterval(() => {
                if (index < textToAnimate.length) {
                    this.animatedText += textToAnimate[index];
                    index++;
                } else {
                    clearInterval(this.animationInterval);
                    this.isAnimating = false;
                }
            }, 5);
        },

        renderGenericReviewerText() {
            if (this.isAiReadmoreEnabled) {
                const words = this.reviewerText.split(' ');
                this.displayText = words.slice(0, this.contentLength).join(' ');
                this.hiddencontent = words.slice(this.contentLength).join(' ');
            } else {
                this.displayText = this.reviewerText;
            }
        },

        shouldShowReadMore() {
            return this.isAiReadmoreEnabled &&
                   this.reviewerText.split(' ').length > this.contentLength;
        },

        renderSummary() {
            if (this.isAiSummary) {
                if (this.isTextTypingAnimationEnabled) {
                    this.animateReviewerText();
                } else {
                    this.renderGenericReviewerText();
                }
            }
        },

        cleanup() {
            if (this.animationInterval) {
                clearInterval(this.animationInterval);
            }
        }
    },
    mounted() {
        this.renderSummary();
    },
    beforeUnmount() {
        this.cleanup();
    },
    watch: {
        contentLength: {
            handler() {
                if (!this.isAnimating) {
                    debounced(this.renderSummary, 300)();
                }
            }
        },
        reviewerText: {
            handler() {
                this.renderSummary();
            }
        },
        isTextTypingAnimationEnabled: {
            handler() {
                this.renderSummary();
            }
        }
    }
}
</script>
