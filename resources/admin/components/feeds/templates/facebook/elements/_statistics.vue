<template>
    <div class="wpsr-fb-feed-statistics" v-if="has_pro && (feed_settings.display_likes_count === 'true' || feed_settings.display_comments_count === 'true')">
      <div class="wpsr-fb-feed-reactions" v-if="has_pro && getTotalReactions(feed) && feed_settings.display_likes_count === 'true'">
        <div v-if="feed.like && feed.like.summary.total_count" class="wpsr-fb-feed-reactions-icon-like wpsr-fb-feed-reactions-icon"></div>
        <div v-if="feed.love && feed.love.summary.total_count" class="wpsr-fb-feed-reactions-icon-love wpsr-fb-feed-reactions-icon"></div>
        <div v-if="feed.wow && feed.wow.summary.total_count" class="wpsr-fb-feed-reactions-icon-wow wpsr-fb-feed-reactions-icon"></div>
        <div v-if="feed.sad && feed.sad.summary.total_count" class="wpsr-fb-feed-reactions-icon-sad wpsr-fb-feed-reactions-icon"></div>
        <div v-if="feed.angry && feed.angry.summary.total_count" class="wpsr-fb-feed-reactions-icon-angry wpsr-fb-feed-reactions-icon"></div>
        <div class="wpsr-fb-feed-reaction-count">{{ shortNumberFormat(getTotalReactions(feed)) }}</div>
      </div>

      <div class="wpsr-fb-feed-comments-count" v-if="has_pro && feed.comments && feed.comments.summary.total_count && feed_settings.display_comments_count === 'true'">
        {{ shortNumberFormat(feed.comments.summary.total_count) }} {{$t('Comments')}}
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
      sum += feed.like ? feed.like.summary.total_count : null;
      sum += feed.love ? feed.love.summary.total_count : null;
      sum += feed.wow ? feed.wow.summary.total_count : null;
      sum += feed.haha ? feed.haha.summary.total_count : null;
      sum += feed.sad ? feed.sad.summary.total_count : null;
      sum += feed.angry ? feed.angry.summary.total_count : null;
      return sum;
    }
  }
}
</script>