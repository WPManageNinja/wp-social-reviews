<template>
  <p v-if="feed_settings.display_description === 'true' && (feed.status_type === 'share' || feed.status_type === 'shared_story')" v-html="facebookTextValidate(feed.message)"></p>
</template>

<script>
export default {
  name: "feed",
  props: ['feed', 'feed_settings']
}
</script>