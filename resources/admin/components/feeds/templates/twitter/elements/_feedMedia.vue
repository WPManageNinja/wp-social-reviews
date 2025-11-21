<template>
  <div :class="`wpsr-feed-${feed.media[0].type}`" v-if="feed.media && feed.media.length">
    <div :class="`wpsr-media-box-one wpsr-media-item-${parseInt(feed.media.length / 2)}`" v-if="feed.media && feed.media.length > 1">
      <div class="wpsr-tweet-media" v-for="(media, index) in feed.media">
        <span v-if="index < parseInt(feed.media.length / 2)">
          <render-media :feed = "feed" :media="media" :feed_settings="feed_settings"></render-media>
        </span>
      </div>
    </div>

    <div :class="`wpsr-media-box-two wpsr-media-item-${(feed.media.length - parseInt(feed.media.length / 2))}`" v-if="feed.media">
      <div class="wpsr-tweet-media" v-for="(media, index) in feed.media">
        <span v-if="index >= parseInt(feed.media.length / 2)">
          <render-media :feed = "feed" :media="media" :feed_settings="feed_settings"></render-media>
        </span>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">
import renderMedia from "./_renderMedia.vue";

export default {
  props: ['feed', 'feed_settings'],
  components: {
    renderMedia
  }
}
</script>