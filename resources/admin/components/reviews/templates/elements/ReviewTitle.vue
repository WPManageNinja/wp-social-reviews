<template>
  <h3
      class="wpsr-review-title"
      v-if="template_meta.display_review_title === 'true' && review.review_title && review.review_title.length && !review.review_title.includes(review.platform_name+'_') && review.platform_name !== 'ai'"
  >
    {{ review.review_title }}
  </h3>
</template>

<script>
export default {
  props: ['template_meta', 'review'],
  name: "ReviewTitle"
}
</script>