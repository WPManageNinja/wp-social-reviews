<template>
  <div :class="`wpsr-reviews-badge-${this.$route.params.template_id}`">
    <div v-if="hasBusinessInfo"
         class="wpsr-reviews-badge-wrapper"
         :class="badgeWrapperClasses">
      <a id="wpsr-reviews-badge"
         :class="badgeLinkClasses"
         :href="badgeUrl"
         :target="badgeTarget">
        <div class="wpsr-reviews-badge-wrapper-inner">
          <div v-if="shouldShowPlatformSection"
               class="wpsr-business-info-logo">
            <template v-if="isSinglePlatform && this.appVars.platforms_cards.some( card => card.platform === businessInfo.platform_name )">
              <img v-if="shouldShowPlatformIcon(businessInfo, 'badge2')"
                   :src="getPlatformIcon('small')"
                   :alt="businessInfo.platform_name">
              <img v-else-if="shouldShowPlatformIcon(businessInfo, 'badge1')"
                   :src="getPlatformIcon('default')"
                   :alt="businessInfo.platform_name">
            </template>
            <template v-else-if="isSinglePlatform && Object.entries(businessInfo.platforms).length > 0">
              <img :src="getCustomSourcesPlatformIcon(businessInfo)" alt="">
            </template>

            <div v-if="shouldShowMultiplePlatforms" class="wpsr-business-info-paltforms">
              <template v-for="platform in availablePlatforms">
                <img v-if="platform.logo && platform.logo !== ''" :src="platform.logo" alt="">
                <img v-else
                     :key="platform.platform_name"
                     :src="get_platform_icon(platform.platform_name, 'small')"
                     :alt="platform.platform_name">
              </template>
            </div>

            <span v-if="shouldShowBadge1Title" class="wpsr-reviews-badge-title">
                 {{ template_meta.badge_settings.custom_title }}
            </span>
          </div>

          <div class="wpsr-rating-and-count">
            <span v-if="shouldShowBadge2Title"
                  class="wpsr-reviews-badge-title">
                {{ template_meta.badge_settings.custom_title }}
            </span>

            <div v-if="businessInfo.total_rating === undefined || businessInfo.total_rating < 1" class="wpsr-total-rating">
              <div class="wpsr-rating" v-html="ratingIcon(0)"></div>
            </div>

            <div v-if="businessInfo.average_rating" class="wpsr-total-rating">
              {{ formattedRating }}
              <div class="wpsr-rating" v-html="ratingIcon(formattedRating)"></div>
            </div>

            <div v-if="hasReviewsCount"
                 class="wpsr-total-reviews">
              {{ formattedReviewsCount }}
            </div>
          </div>
        </div>
      </a>
    </div>
    <div v-else>
      <h3>{{ $t('Your business info is empty!!') }}</h3>
    </div>
  </div>
</template>

<script>
// import { reviewsMixin } from '../../../mixins/reviewsMixin';
import { ReviewsBusinessInfoMixin } from "../../../mixins/ReviewsBusinessInfoMixin";

export default {
  name: "Badge1",
  props: {
    template_meta: {
      type: Object,
      required: true
    },
    businessInfo: {
      type: Object,
      required: true
    }
  },
  mixins: [ ReviewsBusinessInfoMixin ],
  computed: {
    badgeSettings() {
      return this.template_meta.badge_settings || {};
    },
    badgeWrapperClasses() {
      return [
        this.businessInfo.total_rating === undefined ? 'wpsr-no-reviews' : '',
        this.badgeSettings.display_mode !== 'none' ? 'wpsr-enable-cursor' : '',
        this.isSinglePlatform ? this.businessInfo.platform_name : 'wpsr-has-multiple-reviews-platform',
        `wpsr-reviews-${this.badgeSettings.template}`,
        this.singlePlatformName
      ];
    },
    badgeLinkClasses() {
      return [
        this.badgeSettings.display_mode !== 'none' ? 'wpsr-reviews-badge-btn' : '',
        this.badgeSettings.display_mode === 'none' ? 'wpsr-reviews-badge-html' : '',
        `wpsr-${this.badgeSettings.badge_position}`
      ];
    },
    badgeUrl() {
      const { display_mode, url, custom_url } = this.badgeSettings;
      switch (display_mode) {
        case 'page':
          return url || '#';
        case 'form_shortcode_id':
          return 'javascript:void(0)';
        case 'custom_url':
          return custom_url || '#';
        default:
          return '#';
      }
    },
    badgeTarget() {
      return this.badgeSettings.display_mode === 'form_shortcode_id' ? '_self'
          : (this.badgeSettings.open_in_new_window === 'true' ? '_blank' : '_self');
    },
    shouldShowPlatformSection() {
      return this.badgeSettings.display_platform_icon === 'true' ||
          this.badgeSettings.custom_title.length > 0;
    },
    shouldShowMultiplePlatforms() {
      return !this.isSinglePlatform &&
          this.badgeSettings.display_platform_icon === 'true';
    },
    shouldShowBadge1Title() {
      return this.badgeSettings.custom_title &&
          this.badgeSettings.custom_title.length &&
          this.badgeSettings.template === 'badge1';
    },
    shouldShowBadge2Title() {
      return this.badgeSettings.custom_title &&
          this.badgeSettings.custom_title.length &&
          this.badgeSettings.template === 'badge2';
    },
    formattedRating() {
      return parseFloat(this.businessInfo.average_rating).toFixed(1);
    },
    hasReviewsCount() {
      return this.badgeSettings.custom_num_of_reviews_text &&
          this.badgeSettings.custom_num_of_reviews_text.length;
    },
    formattedReviewsCount() {
      const text = this.badgeSettings.custom_num_of_reviews_text;
      return text.includes('{reviews_count}') && this.businessInfo.total_rating !== undefined
          ? text.replace('{reviews_count}', Number(this.businessInfo.total_rating).toLocaleString())
          : text.replace('{reviews_count}', '');
    }
  },
  methods: {
    showPopupModal() {
      let that = this;
      jQuery('.wpsr-reviews-badge-btn, .wpsr-reviews-badge-html').on('click', function (e) {
        let display_mode = that.template_meta.badge_settings.display_mode;
        if(display_mode === 'popup' || display_mode === 'none') {
          e.preventDefault();
          if (display_mode === 'popup') {
            jQuery('#wpsr-reviews-grid, #wpsr-reviews-badge').toggleClass('active');
          }
        }
      });
    },
    hidePopupModal() {
      jQuery('.wpsr-popup-close').on('click', function (e){
        e.preventDefault();
        jQuery(this).parents().find('.wpsr-reviews-layout-badge, .wpsr-reviews-badge-btn').removeClass('active');
      });
      jQuery('.wpsr_editor_wrapper .el-main').on('click', function (e) {
        if (jQuery(e.target).has(".active").length) {
          jQuery('.wpsr-reviews-layout-badge, .wpsr-reviews-badge-btn').removeClass('active');
        }
      });
    }
  },
  watch: {
    'template_meta.badge_settings.display_mode': {
      handler(val) {
        if(val !== 'popup') {
          jQuery('.wpsr-reviews-layout-badge, .wpsr-reviews-badge-btn').removeClass('active');
        }
      },
      deep: true
    }
  },
  mounted() {
    this.showPopupModal();
    this.hidePopupModal();
  },
}
</script>