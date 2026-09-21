import { wpsrUpgradeUrl } from '../utils/upgradeUrl';

export default {
  computed: {
    hasActiveOffer() {
      return this.appVars?.active_offer && this.appVars.active_offer.id;
    },

    activeOffer() {
      return this.appVars?.active_offer || null;
    },

    upgradeBtnConfig() {
      return this.appVars?.upgrade_btn_config || {
        text: 'Upgrade to Pro',
        pro_purchase_url: wpsrUpgradeUrl('upgrade_page'),
        discount_percentage: 0,
        urgency_text: null,
        has_offer: false,
        offer_id: null,
        offer_name: null
      };
    },

    specificFeatureConfig() {
      return this.appVars?.specific_feature_popup_config || {
        heading: 'Unlock Pro Features',
        description: 'Get access to all premium features and take your social media to the next level.',
        bullets: [
          'All premium templates',
          'Advanced customization',
          'Priority support',
          'Ajax Load More Pagination',
          'Regular updates',
          'Unlimited usage'
        ],
        pro_purchase_url: wpsrUpgradeUrl('feature_lock_general'),
        offer_active: false
      };
    },

    platformPromotionConfig() {
      return this.appVars?.platform_promotion_config || {};
    },

    pro_purchase_url() {
      return this.upgradeBtnConfig.pro_purchase_url;
    },
  },

  methods: {
    getOfferBadgeText() {
      if (!this.hasActiveOffer) return '';

      const percentage = this.upgradeBtnConfig.discount_percentage;
      return percentage ? `${percentage}% OFF` : 'Limited Time';
    },
    getOfferUrgencyText() {
      return this.upgradeBtnConfig.urgency_text || 'Limited time offer!';
    },

    //Enhanced promotion handling
    getEnhancedPromotion(basePromotion) {
      if (!this.hasActiveOffer || !basePromotion) {
        return basePromotion;
      }

      // Merge active offer with base promotion. The URL is deliberately left
      // alone: PHP already stamped the live promo onto it as utm_id while keeping
      // the placement intact, so an offer must not swap the whole URL out.
      return {
        ...basePromotion,
        discount_percentage: this.activeOffer.discount_percentage || 0,
        offer_active: true,
        offer_text: this.activeOffer.urgency_text || '',
        button_text: this.activeOffer.button_text || basePromotion.button_text || 'Upgrade to Pro'
      };
    },

    // Get platform-specific promotion
    getPlatformPromotion(platform) {
      return this.platformPromotionConfig[platform] || null;
    },

    // Get promotion by platform with offer enhancement
    getPromotionForPlatform(platform) {
      const basePromotion = this.getPlatformPromotion(platform);
      return this.getEnhancedPromotion(basePromotion);
    },
  }
};
