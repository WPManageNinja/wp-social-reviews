<template>
  <p class="wpsr-fb-feed-content wpsr_add_read_more wpsr_show_less_content" v-if="feed.message && feed_settings.display_description === 'true'"
     v-html="facebookTextValidate(this.$addReadMoreBtn(feed.message, feed_settings.content_length, ''))"
  >
  </p>
</template>

<script>
export default {
  name: "description",
  props: ['feed', 'feed_settings'],
  methods: {
    facebookTextValidate(feed) {
      let hashTagUrl = 'https://facebook.com/hashtag/';
      feed = this.generateURLsFromText(feed);
      feed = this.generateURLsFromHashTag(feed, hashTagUrl);
      return this.nl2br(feed);
    },
  }
}
</script>