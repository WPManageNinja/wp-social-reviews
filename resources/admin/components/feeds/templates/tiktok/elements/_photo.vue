<template>
  <div class="wpsr-tt-post-media" :class="(image_settings.optimized_images  === 'true' && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''">
  
    <a
        v-if="feed.media.url"
        :href="feed.media.url"
        class="wpsr-tiktok-feed-video-preview wpsr-tiktok-feed-video-playmode wpsr-feed-link"
       target="_blank"
       rel="nofollow"
    >
      <img v-if="image_settings.optimized_images !== 'true'" :src="feed.default_media" :alt="feed.text" class="wpsr-tt-post-img" :class="get_image_class(feed.media.preview_image_url, image_settings)"/>
      <img v-if="image_settings.optimized_images === 'true'" :src="feed.media_url" :alt="feed.text" class="wpsr-tt-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
    </a>
  </div>
</template>

<script>
import {commonMixin} from "../../../../../mixins/commonMixin";
export default {
  props: ['feed', 'feed_type', 'feed_settings' , 'image_settings'],
  mixins: [commonMixin]
}
</script>