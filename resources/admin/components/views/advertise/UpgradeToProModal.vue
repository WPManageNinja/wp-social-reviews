<template>
  <div class="wpsr-upgrade-modal">
    <ReviewsPlatformNotice v-if="platform === 'amazon'" :platFormName="platform"/>
    <!-- Main Content Area -->
    <div class="wpsr-upgrade-content">
      <!-- Left Side - Preview Image/Video -->
      <div class="wpsr-upgrade-preview">
        <!-- Video Preview -->
        <div v-if="getPreviewVideo()" class="wpsr-video-container">
          <iframe
                  class="wpsr-preview-video"
                  width="580"
                  height="300"
                  :src="getPreviewVideo()"
                  frameborder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowfullscreen>
          </iframe>
        </div>

        <!-- Image Preview -->
        <img
          v-else-if="getPreviewImage()"
          :src="getPreviewImage()"
          :alt="getFeatureTitle()"
          class="wpsr-preview-image"
        />

        <!-- Placeholder -->
        <div v-else class="wpsr-preview-placeholder">
          <div class="wpsr-preview-icon">📱</div>
          <p>{{ getFeatureTitle() }}</p>
        </div>
      </div>

      <!-- Right Side - Content -->
      <div class="wpsr-upgrade-details">
        <h2 class="wpsr-upgrade-title">{{ getFeatureTitle() }}</h2>
        <p class="wpsr-upgrade-description">{{ getFeatureDescription() }}</p>
        <!-- Offer specific content -->
        <div v-if="hasActiveOffer || promotionInfo?.offer_active" class="wpsr-offer-highlight">
          <div class="wpsr-offer-timer" v-if="getOfferText()">
            <span class="wpsr-promo-icon">🎉</span>
            {{ getOfferText() }}
          </div>
        </div>
      </div>
    </div>

    <!-- Features List -->
    <div class="wpsr-features-section">
      <h3 class="wpsr-features-title">Unlock the Pro Features</h3>
      <div class="wpsr-features-grid">
        <div
          v-for="(feature, index) in getFeaturesList()"
          :key="index"
          class="wpsr-feature-item"
        >
          <span class="wpsr-feature-check"><el-icon><Check /></el-icon></span>
          <span class="wpsr-feature-text">{{ feature }}</span>
        </div>
      </div>
    </div>

    <!-- Upgrade Button -->
    <div class="wpsr-upgrade-footer">
      <button
        class="wpsr-upgrade-btn"
        :class="{
          'wpsr-offer-active': hasActiveOffer || promotionInfo?.offer_active,
          'wpsr-pulsing': hasActiveOffer || promotionInfo?.offer_active
        }"
        @click="upgradeNow"
      >
        <span class="wpsr-crown-icon"><ProCrownIcon/></span>
        {{ getUpgradeButtonText() }}
      </button>

      <p v-if="hasActiveOffer || promotionInfo?.offer_active" class="wpsr-offer-note">
        Limited time offer - Don't miss out!
      </p>
    </div>
  </div>
</template>
<script type="text/babel">
import offerMixin from '../../../mixins/offerMixin';
import ProCrownIcon from '../../pieces/icons/ProCrownIcon.vue';
import { Check } from '@element-plus/icons-vue';
import ReviewsPlatformNotice from "../../pieces/ReviewsPlatformNotice.vue";

export default {
  name: 'UpgradeToProModal',
  mixins: [offerMixin],
  components: {
    ReviewsPlatformNotice,
    Check,
    ProCrownIcon
  },
  props: {
    promotionInfo: {
      type: Object,
      default: () => ({})
    },
    platform: {
      type: String,
      default: ''
    },
    feature_type: {
      type: String,
      default: ''
    }
  },
  computed: {
    // Get the effective promotion info (props > platform-specific > default)
    effectivePromotionInfo() {
      if (Object.keys(this.promotionInfo).length > 0) {
        return this.getEnhancedPromotion(this.promotionInfo);
      }

      if (this.platform) {
        const platformPromotion = this.getPromotionForPlatform(this.platform);
        if (platformPromotion) {
          return platformPromotion;
        }
      }

      return this.getEnhancedPromotion({
        title: this.specificFeatureConfig.heading,
        description: this.specificFeatureConfig.description,
        features: this.specificFeatureConfig.bullets,
        pro_purchase_url: this.specificFeatureConfig.pro_purchase_url
      });
    }
  },
  methods: {
    getFeatureTitle() {
      // Priority: effective promotion > platform-specific config > default
      return this.effectivePromotionInfo?.subtitle ||
             this.getPlatformSpecificConfig('heading') ||
             'Unlock Pro Features';
    },

    getFeatureDescription() {
      // Priority: effective promotion > platform-specific config > default
      return this.effectivePromotionInfo?.description ||
             this.getPlatformSpecificConfig('description') ||
             'Get access to all premium features and take your social media to the next level.';
    },

    // Helper method to get platform and feature type specific configuration
    getPlatformSpecificConfig(property) {

      if (!this.platform || !this.feature_type) {
        return this.specificFeatureConfig.general.default[property] || null;
      }

      const platformConfig = this.specificFeatureConfig[this.platform];
      if (!platformConfig || !platformConfig[this.feature_type]) {
        return this.specificFeatureConfig.general.default[property] || null;
      }


      // Return the specific property for the feature type
      if (!platformConfig) {
        return this.specificFeatureConfig.general.default[property] || null;
      }

      const featureConfig = platformConfig[this.feature_type];
      return featureConfig?.[property] || this.specificFeatureConfig.general.default[property] || null;
    },

    getFeaturesList() {
      return this.effectivePromotionInfo?.features ||
          this.getPlatformSpecificConfig('bullets') ||
             [
               'All premium templates',
               'Advanced customization',
               'Priority support',
               'Regular updates',
               'Unlimited usage'
             ];
    },

    getPreviewImage() {
      return this.effectivePromotionInfo?.img ||
             this.getPlatformSpecificConfig('img');
    },

    getPreviewVideo() {
      return this.effectivePromotionInfo?.video || this.getPlatformSpecificConfig('video');
    },

    getDiscountPercentage() {
      if (this.effectivePromotionInfo?.offer_active && this.effectivePromotionInfo?.discount_percentage) {
        return this.effectivePromotionInfo.discount_percentage;
      }
      return this.hasActiveOffer ? this.upgradeBtnConfig.discount_percentage : 0;
    },

    getOfferText() {
      if (this.effectivePromotionInfo?.offer_text) {
        return this.effectivePromotionInfo.offer_text;
      }
      return this.hasActiveOffer ?
        'Discover exciting new features now!' :
        'Limited time special pricing!';
    },

    getUpgradeButtonText() {
      if (this.effectivePromotionInfo?.button_text) {
        return this.effectivePromotionInfo.button_text;
      }
      return this.upgradeBtnConfig.text || 'Upgrade to Pro';
    },

    upgradeNow() {
      const url = this.getBuyUrl();

      // Track the click
      if (this.hasActiveOffer) {
        this.trackOfferClick(this.activeOffer.id, `upgrade_modal_${this.platform || 'default'}`);
      }

      window.open(url, '_blank');
    },

    getBuyUrl() {
      // Priority: promotion URL > offer URL > config URL > default
      return this.effectivePromotionInfo?.pro_purchase_url ||
          this.getPlatformSpecificConfig('pro_purchase_url') ||
             this.upgradeBtnConfig.pro_purchase_url ||
             'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade';
    }
  }
}
</script>
