<template>
  <!-- Mirrors Pro's badge-layouts/avatar-stack.php: reviewer photos, "93+" bubble, average with five stars, platform icons and count. -->
  <div class="wpsr-reviews-badge-wrapper-inner">
    <div v-if="avatars.length" class="wpsr-badge-avatars" aria-hidden="true">
      <img v-for="(avatar, index) in avatars" :key="index" :src="avatar" alt="" width="40" height="40">
      <span v-if="moreReviews" class="wpsr-badge-avatars-more">{{ moreReviews }}</span>
    </div>
    <div class="wpsr-rating-and-count">
      <div v-if="rating" class="wpsr-total-rating">
        {{ rating }}
        <span class="wpsr-rating" aria-hidden="true" v-html="ratingIcon(rating)"></span>
      </div>
      <div class="wpsr-total-reviews">
        <img v-for="platform in platformIcons" :key="platform.name" class="wpsr-badge-platform-icon" :src="platform.icon" :alt="platform.name" width="16" height="16">
        <span v-if="reviewsCountText">{{ reviewsCountText }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "BadgeAvatarStack",
  props: {
    businessInfo: { type: Object, required: true },
    badgeSettings: { type: Object, required: true },
    reviews: { type: Array, default: () => [] },
    rating: { type: String, default: '' },
    reviewsCountText: { type: String, default: '' },
    platformIcons: { type: Array, default: () => [] }
  },
  computed: {
    avatars() {
      // Same source as the cards' Image element.
      return this.reviews
          .filter(review => review.reviewer_img)
          .map(review => (review.platform_name !== 'ai' && review.media_url) || review.reviewer_img)
          .slice(0, 4);
    },
    moreReviews() {
      const remaining = (parseInt(this.businessInfo.total_rating) || 0) - this.avatars.length;
      if (!this.avatars.length || remaining < 1) {
        return '';
      }
      // Upper-cased to match the PHP Helper::shortNumberFormat() the front end uses ("1.2K+").
      return String(this.shortNumberFormat(remaining)).toUpperCase() + '+';
    }
  }
}
</script>
