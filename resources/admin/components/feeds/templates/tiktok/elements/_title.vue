<template>
  <div v-if="display_desc && feed.text && feed_settings.display_description === 'true'" class="wpsr-feed-description-link">
    <p class="wpsr-feed-description-text" v-html="this.$trimWords(tiktokTextValidate(feed.text), feed_settings.content_length, true)"></p>
  </div>
</template>
<script>
import Date from "./_date";

export default {
  name: "tiktok_feed_title",
  components: {
    Date
  },
  props: ['feed', 'feed_settings', 'display_desc'],
  data() {
    return {
      hideStatistics: false,
    }
  },
  methods: {
    tiktokTextValidate(feed) {
      let hashTagUrl = 'https://www.tiktok.com/tag/';
      feed = this.escapeHtml(feed);
      feed = this.generateURLsFromText(feed);
      feed = this.generateURLsFromHashTag(feed, hashTagUrl);
      return this.nl2br(feed);
    }
  },
}
</script>