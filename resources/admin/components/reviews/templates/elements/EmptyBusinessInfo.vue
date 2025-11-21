<template>
    <div class="wpsr-business-info-wrapper wpsr-empty-business-info">
        <div class="wpsr-business-info">
            <div class="wpsr-business-info-left">
                <div class="wpsr-rating-and-count">
                  <div class="wpsr-rating" v-html="ratingIcon(0)"></div>
                </div>
                <div class="wpsr-no-reviews-message">
                    {{ $t('Be the first to write a review.') }}
                </div>
            </div>

          <div class="wpsr-business-info-right">
            <a
                class="wpsr-write-review"
                :target="customButtonTarget"
                :href="metaStyles.war_btn_source_custom_url"
            >
              {{ metaStyles.custom_write_review_text }}
            </a>
          </div>

        </div>
    </div>
</template>

<script type="text/babel">
import BusinessModalBtn from "./BusinessModalBtn.vue";

export default {
    name: 'EmptyBusinessInfo',
  components: {BusinessModalBtn},
    props: {
        metaStyles: {
          type: Object,
          default: () => ({})
        }
    },
   computed: {
     customButtonTarget() {
       return this.metaStyles.war_btn_source === 'custom_url' &&
       this.metaStyles.war_btn_open_in_new_window === 'true' ? '_blank' : '_self';
     },
   }
}
</script>