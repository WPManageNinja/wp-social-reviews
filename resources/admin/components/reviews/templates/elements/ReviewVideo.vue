<template>
  <div v-if="showVideo" class="wpsr-testimonial-video">
    <video
        v-if="isFileUrl"
        class="wpsr-testimonial-video-player"
        :src="videoUrl"
        :poster="videoPoster || null"
        controls
        :preload="preload"
        playsinline
    ></video>

    <!-- loading="lazy": a template preview draws every card, and each player is a
         heavy third-party frame. No poster — the provider shows its own. -->
    <div v-else-if="embedUrl" class="wpsr-testimonial-video-embed">
      <iframe
          :src="embedUrl"
          :title="$t('Video preview')"
          loading="lazy"
          referrerpolicy="strict-origin-when-cross-origin"
          allow="clipboard-write; encrypted-media; picture-in-picture; fullscreen"
          allowfullscreen
      ></iframe>
    </div>

    <!-- Any other host plays through Fluent Player, or not at all. -->
    <div v-else class="wpsr-testimonial-video-placeholder">
      <span class="wpsr-testimonial-video-play"></span>
      <span class="wpsr-testimonial-video-note">{{ $t('No preview in the editor') }}</span>
    </div>
  </div>
</template>

<script type="text/babel">
import { wpsrIsHttpUrl, wpsrIsVideoFileUrl, wpsrTrimUrl, wpsrVideoEmbedUrl } from '../../../../utils/videoUrl';

export default {
  name: 'ReviewVideo',
  props: {
    showReviewVideo: {
      type: String,
      default: 'true'
    },
    reviewFields: {
      type: [Object, Array],
      default: () => ({})
    }
  },
  computed: {
    /** wpsrTrimUrl, not .trim(): .trim() leaves the U+200B-U+200D the server strips. */
    videoUrl() {
      const url = this.reviewFields ? this.reviewFields.video_url : '';

      return wpsrIsHttpUrl(url) ? wpsrTrimUrl(url) : '';
    },
    videoPoster() {
      const poster = this.reviewFields ? this.reviewFields.video_poster : '';

      return wpsrIsHttpUrl(poster) ? wpsrTrimUrl(poster) : '';
    },
    isFileUrl() {
      return wpsrIsVideoFileUrl(this.videoUrl);
    },
    // YouTube and Vimeo only; the rest get a watch link on the front end.
    embedUrl() {
      return wpsrVideoEmbedUrl(this.videoUrl);
    },
    /** Same rule as TestimonialVideo::videoTag(): without a poster, pay for a frame. */
    preload() {
      return this.videoPoster !== '' ? 'none' : 'metadata';
    },
    showVideo() {
      return this.showReviewVideo !== 'false' && this.videoUrl !== '';
    }
  }
}
</script>
