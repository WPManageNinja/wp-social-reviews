<template>
    <span class="wpsr-tiktok-feed-time" v-if="feed_settings.display_date === 'true' && has_pro">
      {{ wpsrDateFormat(feed.created_at * 1000, 'MMMM DD YYYY') }}
    </span>
</template>

<script>
export default {
  name: "tiktok_feed_date",
  props: ['feed', 'feed_settings'],
}
</script>