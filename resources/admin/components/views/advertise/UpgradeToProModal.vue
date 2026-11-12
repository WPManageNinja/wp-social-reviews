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
import { wpsrUpgradeUrl } from '../../../utils/upgradeUrl';

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
      return this.effectivePromotionInfo?.subtitle ||
             this.getPlatformSpecificConfig('heading') ||
             'Unlock Pro Features';
    },

    getFeatureDescription() {
      return this.effectivePromotionInfo?.description ||
             this.getPlatformSpecificConfig('description') ||
             'Get access to all premium features and take your social media to the next level.';
    },

    getPlatformSpecificConfig(property) {

      if (!this.platform || !this.feature_type) {
        return this.specificFeatureConfig.general.default[property] || null;
      }

      const platformConfig = this.specificFeatureConfig[this.platform];
      if (!platformConfig || !platformConfig[this.feature_type]) {
        return this.specificFeatureConfig.general.default[property] || null;
      }


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
      // Any live offer is already in the URL as utm_id, so no separate click tracking.
      window.open(this.getBuyUrl(), '_blank');
    },

    /**
     * Most specific placement first: each PHP-built URL's utm_content names the locked feature.
     * Not routed through getPlatformSpecificConfig(), whose general.default fallback would
     * shadow the platform promotion.
     */
    getBuyUrl() {
      // 1. The exact platform + feature the user just hit, e.g. instagram/shoppable.
      if (this.platform && this.feature_type) {
        const featureUrl = this.specificFeatureConfig?.[this.platform]?.[this.feature_type]?.pro_purchase_url;
        if (featureUrl) {
          return featureUrl;
        }
      }

      // 2. A locked reviews platform, e.g. yelp.
      if (this.platform) {
        const platformPromotion = this.getPromotionForPlatform(this.platform);
        if (platformPromotion?.pro_purchase_url) {
          return platformPromotion.pro_purchase_url;
        }
      }

      // 3. A caller-supplied promotion.
      if (this.promotionInfo?.pro_purchase_url) {
        return this.promotionInfo.pro_purchase_url;
      }

      // 4. Unnamed lock: still attributable, just less precisely.
      return this.specificFeatureConfig?.pro_purchase_url ||
             this.upgradeBtnConfig?.pro_purchase_url ||
             wpsrUpgradeUrl('upgrade_page');
    }
  }
}
</script>
