<template>
  <div class="wpsr-fb-feed-author" v-if="account">
    <div class="wpsr-fb-feed-author-avatar" v-if="Object.entries(account).length && feed_settings.display_author_photo === 'true'">
      <a v-if="account.picture" target="_blank" :href="account.link" rel="nofollow noopener">
        <img :src="feed.user_avatar ? feed.user_avatar : account.picture.data.url" :alt="account.name" width="40" height="40">
      </a>
    </div>
    <div class="wpsr-fb-feed-author-info">
      <a target="_blank" rel="nofollow" :href="account.link" class="wpsr-fb-feed-author-name" v-if="Object.entries(account).length && feed_settings.display_author_name === 'true'">
        <span>{{ account.name }}️</span>
      </a>
      <span class="wpsr-fb-feed-story" v-if="feed.story && feed.story.length && (feed.status_type === 'added_photos' || feed.status_type === 'mobile_status_update')">
        {{ getPostStory(feed) }}
      </span>
      <Date
          :feed="feed"
          :feed_settings="feed_settings"
      />
    </div>
    <a v-if="feed_settings.display_platform_icon === 'true'" target="_blank" :href="feed_type === 'video_feed' ? 'https://www.facebook.com'+feed.permalink_url : feed.permalink_url" class="wpsr-fb-feed-platform">
      <i class="icon-facebook-square"></i>
    </a>
  </div>
</template>

<script>
import Date from "./_date";
export default {
  name: "author_links",
  props: ['account' ,'feed', 'feed_type', 'feed_settings'],
  components: {
    Date
  },
  methods: {
    getPostStory(feed) {
      if(feed.story) {
        let index = feed.story.indexOf("updated");
        let word = feed.story.substr(index);
        return this.ucFirst(word);
      }
    }
  }
}
</script>