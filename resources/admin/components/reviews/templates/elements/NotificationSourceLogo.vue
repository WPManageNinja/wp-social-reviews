<template>
  <template v-if="review">
    <span :class="hideLogoClass">{{ $t('On') }}</span>
    <div class="wpsr-review-platform">
      <img v-if="appVars.platforms_cards.some(card => card.platform === review.platform_name)" :class="hideLogoClass" :src="get_platform_icon(review.platform_name, 'small') || null" :alt="review.platform_name">
      <img v-else-if="review.source_id" :src="customLogoSrc(review.source_id, businessInfo)" alt="">
      <span class="wpsr-review-source-title">{{ review.fields ? review.fields.product_name : '' }}</span>
    </div>
  </template>
</template>

<script>
import {reviewsMixin} from '../../../../mixins/reviewsMixin';

export default {
  name: 'NotificationSourceLogo',
  mixins: [reviewsMixin],
  props: {
    review: { type: Object, default: null },
    templateMeta: { type: Object, required: true },
    businessInfo: { type: Object, default: () => ({}) }
  },
  computed: {
    hideLogoClass() {
      const platformName = (this.review && this.review.platform_name) || '';
      const shouldHide =
        this.templateMeta.isPlatformIcon === 'false' ||
        platformName.includes('fluent_forms') ||
        platformName.includes('custom') ||
        (platformName.includes('trust') && this.templateMeta.display_tp_brand === 'false');
      return shouldHide ? 'wpsr-hide-logo' : 'wpsr-show-logo';
    }
  }
}
</script>
