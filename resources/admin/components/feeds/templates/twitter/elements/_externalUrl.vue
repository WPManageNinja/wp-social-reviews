<template>
  <div>
    <div v-for="external_url in external_urls">
      <div class="wpsr-tweet-mp3-frame" v-if="get_platform(external_url.expanded_url) === 'spotify'">
        <iframe :src="get_source(external_url.expanded_url)" type="text/html"  frameborder="0" width="100%" webkitAllowFullScreen mozallowfullscreen allowfullscreen></iframe>
      </div>

      <div class="wpsr-tweet-mp3-frame" v-if="get_platform(external_url.expanded_url) === 'soundcloud'">
        <iframe :src="get_source(external_url.expanded_url)" type="text/html"  frameborder="0" width="100%" webkitAllowFullScreen mozallowfullscreen allowfullscreen></iframe>
      </div>

      <div class="wpsr-tweet-video-frame" v-if="get_platform(external_url.expanded_url) === 'youtube'">
        <iframe class="wpsr-tweet-video-frame-render" type="text/html" :src="get_source(external_url.expanded_url)" frameborder="0" ebkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>
      </div>

      <div class="wpsr-tweet-video-frame" v-if="get_platform(external_url.expanded_url) === 'vimeo'">
        <iframe class="wpsr-tweet-video-frame-render" type="text/html" :src="get_source(external_url.expanded_url)" frameborder="0" ebkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">
export default {
  props: ['external_urls'],

  methods: {
    get_platform(external_media_url) {
      if(external_media_url.includes('soundcloud.com')) {
        return 'soundcloud';
      }

      if(external_media_url.includes('vimeo')) {
        return 'vimeo';
      }

      if(external_media_url.includes('spotify')) {
        return 'spotify';
      }

      if (external_media_url.includes('youtu.be') || external_media_url.includes('youtube.com/watch') || external_media_url.includes('youtube.com/embed')) {
        return 'youtube';
      }
    },

    get_source(external_media_url) {
      if(external_media_url.includes('spotify')) {
        return external_media_url.replace('.com', '.com/embed');
      }

      if(external_media_url.includes('soundcloud')) {
        let soundcloud_url = external_media_url.split("?");
        return 'https://w.soundcloud.com/player/?url=' + soundcloud_url[0] + '&amp;auto_play=false&amp;hide_related=true&amp;show_comments=false&amp;show_user=true&amp;show_reposts=false&amp;visual=false';
      }

      if (external_media_url.includes('youtu.be') || external_media_url.includes('youtube.com/watch') || external_media_url.includes('youtube.com/embed')) {
        const pattern = /^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/watch\?.+&v=))([\w-]{11})(?:.+)?$/;
        const matches = external_media_url.match(pattern);
        if (matches) {
          const video_id = matches[1];
          return `https://www.youtube.com/embed/${video_id}?autoplay=0`;
        }
      }

      if (external_media_url.includes('vimeo')) {
        if (external_media_url.includes('staffpicks') > 0) {
          const parsed_url = new URL(external_media_url);
          const video_id = parsed_url.pathname.replace(/\D/g, '');
          return `https://player.vimeo.com/video/${video_id}?autoplay=0`;
        } else {
          const video_id = parseInt(new URL(external_media_url).pathname.substr(1));
          return `https://player.vimeo.com/video/${video_id}?autoplay=0`;
        }
      }
    },
  }
}
</script>