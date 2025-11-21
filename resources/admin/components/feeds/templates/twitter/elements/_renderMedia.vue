<template>
  <a class="wpsr-ig-playmode"  @click="postDisplayMode($event, feed, media, feed_settings.advance_settings)">
    <img
        v-if="media.type === 'photo' && feed_settings.advance_settings.show_tweet_image === 'true'"
        :src="media.url" alt=""
    >

    <img
        v-if="media.type === 'video' && feed_settings.advance_settings.show_tweet_video === 'true'"
        :src="media.preview_image_url"
    >

    <video v-else-if="media.type === 'animated_gif' && feed_settings.advance_settings.show_tweet_gif === 'true'" id="wpsr-feed-popup-video" :controls="media.type === 'video' &&  feed_settings.advance_settings.tweet_action_target !== 'popup'" :loop="media.type === 'animated_gif'" :autoplay="media.type === 'animated_gif'"  muted="muted" :poster="media.preview_image_url">
      <source :src="getVideoLink(media)" type="video/mp4" v-if="media.type === 'animated_gif'">
    </video>

    <svg viewBox="0 0 24 24" v-if="media.type === 'video' && feed_settings.advance_settings.show_tweet_video === 'true'">
      <g>
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M16.036 11.58l-6-3.82a.5.5 0 0 0-.77.42v7.64a.498.498 0 0 0 .77.419l6-3.817c.145-.092.23-.25.23-.422s-.085-.33-.23-.42z"></path>
        <path d="M12 22.75C6.072 22.75 1.25 17.928 1.25 12S6.072 1.25 12 1.25 22.75 6.072 22.75 12 17.928 22.75 12 22.75zm0-20C6.9 2.75 2.75 6.9 2.75 12S6.9 21.25 12 21.25s9.25-4.15 9.25-9.25S17.1 2.75 12 2.75z"></path>
      </g>
    </svg>
  </a>
</template>

<script type="text/babel">
import {getVideoUrl, mediaClass} from "../helper";
export default {
  props: ['feed', 'media', 'feed_settings'],
  methods: {
    getVideoLink(media) {
      this.className = mediaClass(media);
      return getVideoUrl(media);
    },
    postDisplayMode(e, feed, media, playmode_settings) {
      let display_mode = playmode_settings.tweet_action_target;
      if(display_mode === 'popup') {
        jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
        jQuery(".wpsr-ig-playmode").removeAttr("href");
      } else {
        let url =  'https://twitter.com/' + feed.user.username + '/status/' + feed.id;
        let postSelector = jQuery('.wpsr-ig-playmode');
        jQuery(postSelector).attr("target", "_blank");
        jQuery(postSelector).attr("href", url);
      }
    },
  }
}
</script>