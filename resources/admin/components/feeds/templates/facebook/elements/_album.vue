<template>
  <div class="wpsr-fb-feed-album-wrapper" v-if="feed">
    <div v-if="shouldShowBreadCrumbs" class="wpsr-fb-feed-bread-crumbs">
      <span class="wpsr-fb-feed-bread-crumbs-album" @click="handleBreadCrumbs"> Albums </span> > {{feed.name}}
    </div>

    <div class="wpsr-fb-feed-album-header">
      <span v-if="shouldShowAlbumInfo">
            <p class="wpsr-fb-feed-album-name" >{{feed.name}}</p>
            <div class="wpsr-fb-feed-album-info">
              <p class="wpsr-fb-feed-album-photo-count" v-if="feed.photos">
                {{shortNumberFormat(feed.photos.data.length )}} Photo
              </p>
              <p class="wpsr-fb-feed-photo-album-divider"> - </p>
              <p class="wpsr-fb-feed-album-update-count">
                Updated <time :datetime="feed.updated_time" :title="wpsrDateFormat(feed.updated_time, 'MMM DD YY','time_from_now')">{{wpsrDateFormat(feed.updated_time, 'MMM DD YY','time_from_now') }}</time>
              </p>
            </div>
      </span>

      <div :class="[ feedConfig.feed_settings.layout_type !== 'carousel' ? 'wpsr-column-gap-'+feedConfig.feed_settings.column_gaps : '']" class="wpsr-fb-all-feed wpsr-row">
        <div class="wpsr-mb-30" :class="[feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-slide' : 'wpsr-col-' + feedConfig.feed_settings.column_number]" v-for="(images , key) in feed.photos.data">
          <div class="wpsr-fb-feed-item" @click="postDisplayMode($event, images, feedConfig.feed_settings, key, image_settings)">
            <Photo
                :feed="feed"
                :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                :feed_settings="feedConfig.feed_settings.post_settings"
                :image_settings="image_settings"
                :images="images"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>

import Photo from './_photo.vue'
import { fbFeedMixin } from "../../../../../mixins/fbFeedMixin";
export default {
  props: ['feed', 'feedConfig','image_settings'],
  mixins: [fbFeedMixin],
  components: {
    Photo
  },
  methods: {
    postDisplayMode(e, feed, feed_settings, key, image_settings) {
      this.$emit('postDisplayMode', e, feed, feed_settings, key, image_settings)
    },
    handleBreadCrumbs() {
      this.$emit('breadCrumbs', false)
      this.handleAlbumBreadCrumbs()
    }
  },
  computed: {
    shouldShowBreadCrumbs(){
      return !(this.feedConfig.feed_settings.source_settings.feed_type === 'single_album_feed')
    },
    shouldShowAlbumInfo(){
      return !(this.feedConfig.feed_settings.source_settings.feed_type === 'single_album_feed')
    }
  }
}
</script>