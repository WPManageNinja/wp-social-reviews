<template>
    <h3 v-if="feed.description && feed_settings.display_description === 'true'">
      <a :href="'https://www.facebook.com'+feed.permalink_url" class="wpsr-fb-feed-video-playmode wpsr-feed-link" target="_blank" rel="nofollow">
      {{$trimWords(feed.description, 10, true)}}
      </a>
    </h3>
</template>
<script>
export default {
  name: "facebook_feed_title",
  props: ['feed', 'feed_settings'],
  data() {
    return {}
  }
}
</script>