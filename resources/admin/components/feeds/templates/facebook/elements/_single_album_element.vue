<template>
  <div>
    <div class="wpsr-fb-feed-image wpsr-temp" :class="(image_settings.optimized_images === 'true' && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''">
      <a :href="feed.link" class="wpsr-feed-link" target="_blank" rel="nofollow" @click="postDisplayMode($event, feed, feed_settings, feed_key, image_settings)">
        <img v-if="image_settings.optimized_images !== 'true'" :src="feed.images.length > 0 && feed.images[0].source" :alt="feed.media_name" class="wpsr-fb-post-img wpsr" :class="get_image_class(feed.media_url, image_settings)"/>
        <img v-else :src="getImageUrl()" :alt="feed.media_name" class="wpsr-fb-post-img wpsro" :class="get_image_class(feed.media_url, image_settings)"/>
      </a>
    </div>
  </div>
</template>

<script>
import { commonMixin } from '../../../../../mixins/commonMixin';
export default {
  mixins: [commonMixin],
  props: ['feed', 'feed_type', 'feed_settings','image_settings', 'feed_key'],
  methods:{
    postDisplayMode(e, feed, feed_settings, key, image_settings) {
      this.$emit('postDisplayMode', e, feed, feed_settings, key, image_settings)
    },
    getImageUrl(){
      let upload_url = this.appVars.upload_url;
      let image_format = this.appVars.image_format
      let image = null;
      if(this.feed_settings){
        image = upload_url + '/facebook_feed/' +  this.feed.page_id + '/' + this.feed.id + '_' + this.feed_settings.resolution + '.'+ image_format;
      }
      
      this.isLoading = false
      return image;
    },
  },
  mounted(){
  }
}
</script>