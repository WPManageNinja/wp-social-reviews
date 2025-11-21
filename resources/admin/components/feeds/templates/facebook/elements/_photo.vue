<template>
  <div class="wpsr-fb-feed-image" :class="(has_pro && image_settings.optimized_images === 'true' && isLoading && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''">
    <a v-if="feed_type === 'timeline_feed' && feed_settings.display_media === 'true' && feed.attachments && feed.attachments.data" :href="feed.permalink_url" class="wpsr-feed-link" target="_blank" rel="nofollow">
      <span v-if="!has_pro" class="wpsr-fb-media-placeholder-icon">
        <div v-if="feed.status_type !== 'added_video'" ><i class="icon-picture-o"></i> {{$t('Photo')}}</div>
        <div v-if="feed.status_type === 'added_video'"><i class="icon-video-camera"></i> {{$t('Video')}}</div>
      </span>
      <div v-if="image_settings.optimized_images !== 'true'" :class="has_pro && feed.attachments && feed.attachments.data[0].type === 'album' ? 'wpsr-fb-feed-image-album-layout-'+feed.attachments.data[0].subattachments.data.length : ''">
        <!-- Handle main attachment video -->
        <a v-if="has_pro && feed.attachments && feed.attachments.data[0].type === 'video'" class="wpsr-fb-feed-video-thumbnail wpsr-fb-feed-video-playmode">
          <img v-if="feed.attachments && feed.attachments.data[0].media && feed.attachments.data[0].media.image.src" :src="feed.attachments.data[0].media.image.src" :alt="feed.message"/>
          <div class="wpsr-fb-feed-video-play">
            <div class="wpsr-fb-feed-video-play-icon"></div>
          </div>
        </a>

        <!-- Handle main attachment image -->
        <img class="fb-post-media-primary" v-else-if="has_pro && feed.attachments && feed.default_media" :src="feed.attachments && feed.attachments.data[0].media && feed.attachments.data[0].media.image.src && feed.attachments.data[0].type !== 'native_templates' ? feed.attachments.data[0].media.image.src : feed.full_picture" :alt="feed.message"/>
        <!-- display album images -->
        <div v-if="has_pro && feed.attachments && feed.attachments.data[0].type === 'album' && feed.attachments.data[0].subattachments" class="wpsr-fb-feed-image-album" >
          <template v-for="(data, index) in attachments(feed.attachments.data[0])" :key="index">
          <span class="wpsr-fb-feed-image-album-item" v-if="index > 0">
            <!-- Handle Images -->
            <img v-if="data.type === 'photo' && data.media.image" :src="data.media.image.src" :alt="feed.message" class="wpsr-fb-post-img"/>

            <!-- Handle Videos -->
            <a v-if="data.type === 'video'" class="wpsr-fb-feed-video-playmode">
              <img v-if="data.media.image" :src="data.media.image.src" :alt="feed.message" class="wpsr-fb-feed-video-thumbnail wpsr-fb-post-img"/>
              <div class="wpsr-fb-feed-video-play wpsr-fb-feed-video-play-album">
                <div class="wpsr-fb-feed-video-play-icon"></div>
              </div>
            </a>
            <span v-if="index === 2 && (feed.attachments.data[0].subattachments.data.length) !== 3" class="wpsr-fb-feed-image-more"><span>+{{(feed.attachments.data[0].subattachments.data.length - 1) - 3}}</span></span>
          </span>
          </template>
        </div>
      </div>
      <div v-if="has_pro === true && image_settings.optimized_images === 'true'">
        <img v-if="feed.default_media" :src="feed.media_url" :alt="feed.message" class="wpsr-fb-post-img fb-post-media-primary" :class="get_image_class(feed.media_url, image_settings)"/>
      </div>
      <div v-if="has_pro && feed_settings.display_play_icon === 'true' &&  (feed.status_type === 'added_video' || feed.attachments.data[0].type === 'video_inline')" class="wpsr-fb-feed-video-play" :class="image_settings.optimized_images === 'true' ? 'wpsr-hide' : ''">
        <div class="wpsr-fb-feed-video-play-icon"></div>
      </div>
    </a>

    <a v-if="has_pro && feed_type === 'photo_feed' && feed.images" :href="feed.link" class="wpsr-feed-link" target="_blank" rel="nofollow">
      <img v-if="image_settings.optimized_images !== 'true'" :src="feed.images && feed.images[2] ? feed.images[2].source : feed.images[0].source" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
      <img v-if="image_settings.optimized_images === 'true'" :src="feed.media_url" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
    </a>

    <a v-if="has_pro && (feed_type === 'video_feed' || feed_type === 'video_playlist_feed') && feed.permalink_url && feed.format" :href="getFacebookUrl(feed.permalink_url)" class="wpsr-fb-feed-video-preview wpsr-fb-feed-video-playmode wpsr-feed-link" target="_blank" rel="nofollow">
      <img v-if="image_settings.optimized_images !== 'true'" :src="feed.format ? feed.format[1].picture : feed.format[0].picture" :alt="feed.description" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
      <img v-if="image_settings.optimized_images === 'true'" :src="feed.media_url" :alt="feed.description" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>

      <span v-if="feed.length && feed_settings.display_duration === 'true'" class="wpsr-fb-feed-video-duration">
          {{ secondsToMinutes(feed.length) }}
      </span>
      <div v-if="feed_settings.display_play_icon === 'true'" class="wpsr-fb-feed-video-play" :class="image_settings.optimized_images === 'true' ? 'wpsr-hide' : ''">
        <div class="wpsr-fb-feed-video-play-icon"></div>
      </div>
    </a>
    
    <a v-if="has_pro && feed_type === 'album_feed' && feed.photos.data" :href="feed.from.link" class="wpsr-feed-link"  target="_blank" rel="nofollow">
      <img v-if="image_settings.optimized_images !== 'true'" :src="images && images.source" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
      <img v-if="image_settings.optimized_images === 'true'" :src="getImageUrl()" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(getImageUrl(), image_settings)"/>
    </a>
  </div>
</template>

<script>
import { commonMixin } from '../../../../../mixins/commonMixin';
export default {
  mixins: [commonMixin],
  props: ['feed', 'feed_type', 'feed_settings' , 'images', 'image_settings'],
  data() {
  return {
      isLoading: true,
    };
  },
  methods: {
    secondsToMinutes(time){
      let hours = Math.floor(time / 3600);
      let minutes = Math.floor((time % 3600) / 60);
      let seconds = Math.floor(time % 60);

      let value = "";
      if (hours > 0) {
        value += "" + hours + ":" + (minutes < 10 ? "0" : "");
      }
      value += "" + minutes + ":" + (seconds < 10 ? "0" : "");
      value += "" + seconds;

      // return like "M:S" or "HH:MM:SS" or "HH"MM:SS"
      return value;
    },
    attachments(feed) {
      if (feed.subattachments && feed.subattachments.data) {
        let data = feed.subattachments.data;
        if (data.length >= 1) {
          return data.length > 3 ? data.slice(1, 4) : data;
        }
      }
    },
    getImageUrl(){
      let upload_url = this.appVars.upload_url;
      let image_format = this.appVars.image_format
      let image = null;
      if(this.feed_settings){
        image = upload_url + '/facebook_feed/' +  this.feed.page_id + '/' + this.images.id + '_' + this.feed_settings.resolution + '.'+ image_format;
      }
      
      this.isLoading = false
      return image;
    },
    getFacebookUrl(permalink_url){
      return 'https://www.facebook.com' + permalink_url;
    },
  }
}
</script>