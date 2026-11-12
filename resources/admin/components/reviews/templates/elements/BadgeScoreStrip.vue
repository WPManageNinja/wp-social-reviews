<template>
  <!-- Mirrors Pro's badge-layouts/score-strip.php: quality label, stars, rating summary, platform icon. -->
  <div class="wpsr-reviews-badge-wrapper-inner">
    <span v-if="qualityLabel" class="wpsr-score-strip-quality">{{ qualityLabel }}</span>
    <span v-if="rating" class="wpsr-rating" aria-hidden="true" v-html="ratingIcon(rating)"></span>
    <span v-if="summaryText" class="wpsr-score-strip-summary">{{ summaryText }}</span>
    <img v-for="platform in platformIcons" :key="platform.name" class="wpsr-badge-platform-icon" :src="platform.icon" :alt="platform.name">
  </div>
</template>

<script>
export default {
  name: "BadgeScoreStrip",
  props: {
    businessInfo: { type: Object, required: true },
    badgeSettings: { type: Object, required: true },
    rating: { type: String, default: '' },
    reviewsCountText: { type: String, default: '' },
    platformIcons: { type: Array, default: () => [] }
  },
  computed: {
    qualityLabel() {
      return this.ratingQualityLabel(this.rating);
    },
    summaryText() {
      return [this.rating, this.reviewsCountText].filter(Boolean).join(' ');
    }
  }
}
</script>
