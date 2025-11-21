<template>
    <div class="wpsr-business-info-right">
      <div class="wpsr-write-review-modal-wrapper">
        <a
            class="wpsr-write-review"
            v-if="shouldShowSingleBusinessButton"
            target="_blank"
            :href="firstPlatformUrl"
        >
          {{ btnText }}
        </a>

        <a
            class="wpsr-write-review"
            v-if="shouldShowCustomUrlButton"
            :target="customButtonTarget"
            :href="customButtonUrl"
        >
          {{ btnText }}
        </a>
        <template v-if="shouldShowMultipleBusinessButton">
        <a class="wpsr-write-review-modal-btn" @click.prevent="toggleReviewModal">
          {{ btnText }}
        </a>
        <div class="wpsr-write-review-modal" :class="{ active: isModalActive }">
          <p>Where would you like to leave a review?</p>
          <div class="wpsr-business-info-paltforms-url">
            <a v-for="(platform, index ) in availablePlatforms" :key="index" :href="platform.url" target="_blank">
              <img v-if="platform.logo && platform.logo !== ''" :src="platform.logo" alt="">
              <img v-else-if="shouldShowPlatformIcon(platform)" :src="getPlatformIconUrl(platform)" :alt="platform.platform_name">
              <div class="wpsr-paltforms-url">
                <span class="wpsr-platform" v-if="platform.name">{{ formatPlatformName(platform.name) }}</span>
                <span class="wpsr-url">{{platform.url}}</span>
              </div>
            </a>
          </div>
        </div>
        </template>

      </div>
    </div>
</template>

<script type="text/babel">
import { ReviewsBusinessInfoMixin } from "../../../../mixins/ReviewsBusinessInfoMixin";

export default {
  props:['businessInfo', 'reviews', 'metaStyles', 'btnText'],
  mixins: [ReviewsBusinessInfoMixin],
  data() {
    return {
      isModalActive: false
    }
  },
  computed: {
    shouldShowSingleBusinessButton() {
      return this.metaStyles.add_custom_war_btn_url === 'false' && this.isSingleBusiness;
    },
    shouldShowCustomUrlButton() {
      return this.metaStyles.add_custom_war_btn_url === 'true';
    },
    shouldShowMultipleBusinessButton() {
      return this.metaStyles.add_custom_war_btn_url === 'false' && this.isMultipleBusiness;
    },
    customButtonTarget() {
      return this.metaStyles.war_btn_source === 'custom_url' &&
      this.metaStyles.war_btn_open_in_new_window === 'true' ? '_blank' : '_self';
    },
    customButtonUrl() {
      return this.metaStyles.war_btn_source === 'custom_url'
          ? this.metaStyles.war_btn_source_custom_url
          : 'javascript:void(0)';
    },
    availablePlatforms() {
      return this.allPlatforms(this.businessInfo.platforms)
          .filter(platform => platform.url && platform.url.length);
    }
  },
  methods: {
    toggleReviewModal() {
      this.isModalActive = !this.isModalActive;
    },
  },
  beforeDestroy() {
    // Cleanup modal state when component is destroyed
    this.isModalActive = false;
  }
}
</script>