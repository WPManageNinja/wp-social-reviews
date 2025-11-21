<template>
  <div>
    <div class="wpsr-fb-feed-image" :class="(image_settings.optimized_images === 'true' && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''">
      <a :href="feed.link" class="wpsr-feed-link" target="_blank" rel="nofollow">
        <img v-if="image_settings.optimized_images !== 'true'" :src="feed.cover_photo && feed.cover_photo.source" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
        <img v-else :src="feed.media_url" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
      </a>
    </div>
    <div class="wpsr-fb-albums-feed-info">
      <h4 class="wpsr-fb-feed-album-name" v-if="feed.name">
        {{ feed.name }}
      </h4>
      <span class="wpsr-fb-feed-album-count" v-if="feed.photos">
        {{ shortNumberFormat(feed.photos.data.length )}} Photos
      </span>
    </div>
  </div>
</template>

<script>
import { commonMixin } from '../../../../../mixins/commonMixin';
export default {
  mixins: [commonMixin],
  props: ['feed', 'feed_type', 'feed_settings','image_settings'],
}
</script>