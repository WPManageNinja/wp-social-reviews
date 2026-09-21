<template>
  <div>
    <div class="wpsr-yt-video"
         ref="videoEl"
         @click="handleRowClick"
    >
      <PreviewImage
          :feed="feed"
          :index="index"
          :configs="configs"
          :feed_info="configs.feed_info"
          :image_settings="image_settings"
          @youtube-video-playmode="youtubeVideoPlayMode($event, feed, configs.feed_settings.video_settings.play_mode, index)"
      >
      </PreviewImage>
      <div class="wpsr-yt-video-info">
        <Title
            :feed="feed"
            :index="index"
            :configs="configs"
            :display_title="configs.feed_settings.video_settings.display_title"
            :header="configs.dynamic.header"
            :feed_info="configs.feed_info"
            @youtube-video-playmode="youtubeVideoPlayMode($event, feed, configs.feed_settings.video_settings.play_mode, index)"
        ></Title>

        <Statistics
            v-if="has_pro"
            :feed="feed"
            :settings="configs.feed_settings.video_settings"
            :feed_info="configs.feed_info"
            :index="index"
            :configs="configs"
            @youtube-video-playmode="youtubeVideoPlayMode($event, feed, configs.feed_settings.video_settings.play_mode, index)"
        ></Statistics>

        <Content
            v-if="has_pro"
            :feed="feed"
            :display_description="configs.feed_settings.video_settings.display_description"
        ></Content>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">
import PreviewImage from './_preview-image';
import Statistics from './_statistics';
import Title from './_title.vue';
import Content from './_content.vue';
import {ytMixin} from "../../../../../mixins/ytMixin";
import {commonMixin} from "../../../../../mixins/commonMixin";

export default {
  props: ['feed', 'configs', 'index', 'image_settings'],
  mixins: [ytMixin, commonMixin],
  components: {
    PreviewImage,
    Statistics,
    Title,
    Content
  },
  mounted() {
    // marks the first item as the default "now playing" row; ytMixin toggles the class imperatively, so keep this a DOM write too
    if (this.index === 0 && this.configs.feed_settings.video_settings.play_mode === 'gallery') {
      this.$refs.videoEl.classList.add('wpsr-yt-gallery-active');
    }
  },
  methods: {
    handleRowClick(e) {
      this.$emit('open-popup');
      // template5's whole row looks clickable, but only the thumbnail/title are wired, so forward the rest here
      if (this.configs.feed_settings.video_settings.play_mode === 'gallery' && !e.target.closest('.wpsr-yt-video-playmode')) {
        this.youtubeVideoPlayMode(e, this.feed, 'gallery', this.index);
      }
    }
  }
}
</script>