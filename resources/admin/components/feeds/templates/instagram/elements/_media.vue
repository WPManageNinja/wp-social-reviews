<template>
    <div class="wpsr-ig-post-media" :class="(image_settings.optimized_images  === 'true' && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''">
        <img class="wpsr-ig-post-img" :class="get_image_class(feed.media_url,image_settings,'instagram')" v-if="feed.media_type === 'IMAGE' || feed.media_type === 'oembed'"
             :src="feed.media_url ? feed.media_url : feed.default_media"
             :alt="feed.caption ? feed.caption : ''"
        >
        <video class="wpsr-ig-post-video" v-if="feed.media_type === 'VIDEO'" :poster="feed.thumbnail_url" @play="markPlaying(true)" @pause="markPlaying(false)" @ended="markPlaying(false)" v-bind="settings.post_settings.display_mode === 'inline' && feed.media_url && feed.media_url.includes('.mp4') ? objectOfAttrs : ''">
          <source :src="feed.media_url ? feed.media_url : feed.default_media" type="video/mp4">
        </video>
        <div v-if="feed.media_name !== 'IMAGE'" class="wpsr-ig-post-type-icon" :class="'wpsr-ig-post-type-' + feed.media_name"></div>
    </div>
</template>

<script type="text/babel">
import {commonMixin} from "../../../../../mixins/commonMixin";
export default {
    name: 'instagram_media',
    props: ['feed','settings','image_settings'],
    mixins: [commonMixin],
    data() {
      return {
        objectOfAttrs: {
          reload: 'auto',
          controls: 'true'
        }
      }
    },
    methods: {
      // The card owns the overlay, so the playing state has to be flagged on it.
      markPlaying(playing) {
        const post = this.$el.closest('.wpsr-ig-post');
        if (post) {
          post.classList.toggle('wpsr-ig-post-playing', playing);
        }
      }
    }
}
</script>