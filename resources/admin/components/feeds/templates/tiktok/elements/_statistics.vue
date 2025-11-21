<template>
    <div class="wpsr-tiktok-feed-statistics" :class="isRemoveSpace() ? 'wpsr-remove-white-space': ''" v-if="has_pro">
      <div class="wpsr-tiktok-feed-reactions" >
        <a v-if="feed.user" target="_blank" :href="'https://www.tiktok.com/@' + feed.user.name + '/video/' + feed.id" class="wpsr-tiktok-feed-reaction-wrapeer">
          <div v-if="feed_settings.display_likes_count === 'true'" class="wpsr-tiktok-feed-reactions-icon-like wpsr-tiktok-feed-reactions-icon"></div>
          <div v-if="feed_settings.display_likes_count === 'true'" class="wpsr-tiktok-feed-reaction-count">{{ shortNumberFormat(getTotalReactions(feed)) }}</div>
        </a>

        <a v-if="feed.user" target="_blank" :href="'https://www.tiktok.com/@' + feed.user.name + '/video/' + feed.id" class="wpsr-tiktok-feed-reaction-wrapeer">
          <div v-if="feed_settings.display_comments_count === 'true'" class="wpsr-tiktok-feed-reactions-icon-comment wpsr-tiktok-feed-reactions-icon"></div>
          <div v-if="feed_settings.display_comments_count === 'true'" class="wpsr-tiktok-feed-reaction-count">{{ shortNumberFormat(feed.statistics.comment_count) }}</div>
        </a>

        <a v-if="feed.user" target="_blank" :href="'https://www.tiktok.com/@' + feed.user.name + '/video/' + feed.id" class="wpsr-tiktok-feed-reaction-wrapeer">
          <div v-if="feed_settings.display_views_count === 'true'" class="wpsr-tiktok-feed-reactions-icon-play wpsr-tiktok-feed-reactions-icon"></div>
          <div v-if="feed_settings.display_views_count === 'true'" class="wpsr-tiktok-feed-reaction-count">{{ shortNumberFormat(feed.statistics.view_count) }}</div>
        </a>
      </div>
    </div>
</template>

<script>
export default {
  name: "statistics",
  props: ['feed', 'feed_settings'],
  methods: {
    getTotalReactions(feed) {
      let sum = 0;
      sum += feed.statistics.like_count ? feed.statistics.like_count : null;
      return sum;
    },
    isRemoveSpace(){
      return this.feed_settings.display_likes_count === 'false' && this.feed_settings.display_comments_count === 'false' && this.feed_settings.display_views_count === 'false';
    },
  },
  watch: {
    feed_settings: {
      handler: function (val) {
        this.isRemoveSpace();
      },
      deep: true
    }
  }
}
</script>