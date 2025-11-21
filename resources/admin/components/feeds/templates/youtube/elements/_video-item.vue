<template>
  <div>
    <div class="wpsr-yt-video"
         @click="$emit('open-popup')"
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
  }
}
</script>