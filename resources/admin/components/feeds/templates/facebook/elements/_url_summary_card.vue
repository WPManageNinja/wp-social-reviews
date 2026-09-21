<template>
  <div>
    <div class="wpsr-fb-feed-image wpsr-fb-feed-url-summary-card-wrapper" v-for="(attachment, key) in feed.attachments.data" :key="key">
      <div class="wpsr-fb-feed-iframe" v-if="attachment.type === 'video_inline' && attachment.url && wpsrIsEmbeddable(feed)">
        <iframe type="text/html" class="wpsr-fb-feed-url-summary-card wpsr-feed-link" :src="wpsrVideoEmbedUrl(attachment.url, {mute: 0})" allowfullscreen="" frameborder="0" webkitallowfullscreen="" mozallowfullscreen="" title="Video">
        </iframe>
      </div>
      <a v-else-if="attachment.type === 'video_inline' && attachment.url" class="wpsr-fb-feed-video-not-embeddable" :href="attachment.url" target="_blank" rel="nofollow">
        <img v-if="attachment.media && attachment.media.image && attachment.media.image.src" :src="attachment.media.image.src" :alt="attachment.title">
        <div class="wpsr-fb-feed-video-play"><div class="wpsr-fb-feed-video-play-icon"></div></div>
        <span class="wpsr-fb-feed-video-not-embeddable-note">{{ $t('View on Facebook') }}</span>
      </a>
      <div class="wpsr-fb-feed-url-summary-card-title" v-if="typeof feed.message !== 'string'">{{ attachment.title }}</div>

      <a v-if="feed.status_type === 'shared_story'" class="wpsr-fb-feed-url-summary-card" :href="getSiteUrl(attachment, false)" target="_blank" rel="nofollow">
        <div class="wpsr-fb-feed-url-summary-card-inner" v-if="attachment.type !== 'video_inline'">
          <span v-if="has_pro && image_settings.optimized_images !== 'true'">
            <img v-if="feed.full_picture && feed_settings.display_media === 'true'" :src="feed.full_picture"  :alt="feed.from.name">
            <img v-if="!feed.full_picture && attachment.media && attachment.media.image && attachment.media.image.src && feed_settings.display_media === 'true'" :src="attachment.media.image.src"  :alt="feed.from.name">
          </span>
          <span v-else-if="has_pro && image_settings.optimized_images === 'true'">
            <img :src="feed.media_url" :alt="feed.from.name" :class="get_image_class(feed.media_url, image_settings)" class="wpsr-fb-post-img">
          </span>
          <div class="wpsr-fb-feed-url-summary-card-contents" v-if="attachment.type === 'share'">
            <div v-if="attachment.target.url" class="wpsr-fb-feed-url-summary-card-contents-domain">
              {{ getSiteUrl(attachment, true) }}
            </div>
            <div v-if="attachment.title" class="wpsr-fb-feed-url-summary-card-contents-title">
              {{ attachment.title }}
            </div>
            <div v-if="attachment.description" class="wpsr-fb-feed-url-summary-card-contents-description">
              {{$trimWords(attachment.description, 12, true)}}
            </div>
          </div>
        </div>
      </a>
    </div>
  </div>
</template>

<script>
import { commonMixin } from '../../../../../mixins/commonMixin';
import { fbVideoMixin } from '../../../../../mixins/fbVideoMixin';
export default {
  mixins: [commonMixin, fbVideoMixin],
  name: "url_summary_card",
  props: ['feed', 'feed_settings', 'image_settings'],
  methods: {
    getSiteUrl(attachment, domain = false) {
      let url = attachment.target.url;
      let host = '';

      if(url) {
        let site_url = new URL(url).searchParams.get('u');
        if(site_url) {
          host = new URL(site_url).hostname;
        } else {
          if(url.includes('https://www.facebook.com/')) {
            host = 'facebook.com'
          }
        }
        return domain ? host : site_url;
      }
    }
  }
}
</script>