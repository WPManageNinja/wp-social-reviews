<template>
    <span class="wpsr-fb-feed-time" v-if="feed_settings.display_date === 'true'">
      {{ feed.created_time }}
    </span>
</template>

<script>
export default {
  name: "facebook_feed_date",
  props: ['feed', 'feed_settings'],
  data(){
    return{}
  }
}
</script>