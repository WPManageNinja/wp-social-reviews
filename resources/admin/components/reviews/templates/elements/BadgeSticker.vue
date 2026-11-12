<template>
  <!-- Mirrors Pro's badge-layouts/sticker.php: quality label, big rating, stars, review count, platform logo. -->
  <div class="wpsr-reviews-badge-wrapper-inner">
    <div v-if="isSquare" class="wpsr-sticker-top">
      <span v-if="qualityLabel" class="wpsr-sticker-quality">{{ qualityLabel }}</span>
      <span v-if="rating" class="wpsr-sticker-rating">{{ rating }}</span>
    </div>
    <template v-else>
      <span v-if="qualityLabel" class="wpsr-sticker-quality">{{ qualityLabel }}</span>
      <span v-if="rating" class="wpsr-sticker-rating">{{ rating }}</span>
    </template>
    <div class="wpsr-sticker-bottom">
      <span v-if="rating" class="wpsr-rating" aria-hidden="true" v-html="ratingIcon(rating)"></span>
      <span v-if="reviewsCountText" class="wpsr-sticker-summary">
        {{ reviewsCountText }}
        <span v-if="isSquare" class="wpsr-sticker-check" aria-hidden="true"></span>
      </span>
      <div v-if="platformIcons.length" class="wpsr-sticker-platform-logos">
        <img v-for="platform in platformIcons" :key="platform.name" class="wpsr-sticker-platform-logo" :src="platform.icon" :alt="platform.name">
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "BadgeSticker",
  props: {
    businessInfo: { type: Object, required: true },
    badgeSettings: { type: Object, required: true },
    rating: { type: String, default: '' },
    reviewsCountText: { type: String, default: '' },
    platformIcons: { type: Array, default: () => [] }
  },
  computed: {
    isSquare() {
      return this.badgeSettings.template === 'badge6';
    },
    qualityLabel() {
      return this.ratingQualityLabel(this.rating);
    }
  }
}
</script>
