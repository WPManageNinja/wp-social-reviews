<template>
    <div v-if="feed.user" class="wpsr-tiktok-feed-author-avatar-wrapper">
        <img v-if="feed_settings.display_author_photo === 'true'" class="wpsr-tiktok-feed-author-avatar" :src="feed.user_avatar ? feed.user_avatar : feed.user.profile_image_url" :alt="feed.user.name" >
        <div class="wpsr-feed-avatar-right">
          <a class="wpsr-tiktok-feed-author-name" v-if="feed_settings.display_author_name === 'true'" :href="feed.user.profile_url" target="_blank" rel="nofollow" >{{feed.user.name}}</a>
          <Date
              :feed="feed"
              :feed_settings="feed_settings"
          />
        </div>
    </div>
</template>
<script>
import Date from "./_date";

export default {
  name: "author",
  components: {
    Date
  },
  props: ['feed', 'feed_settings'],
  data() {
    return {}
  },
  methods: {},
}
</script>