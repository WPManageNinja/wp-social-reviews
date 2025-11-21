<template>
  <div>
    <div v-if="configs.dynamic && configs.dynamic.error_message && configs.dynamic.error_message.error_message && configs.dynamic.error_message.error_message.length">
      <ErrorCard :error_message = "configs.dynamic.error_message.error_message"/>
    </div>
    <div v-if="configs.dynamic.items && configs.dynamic.items.length" class="wpsr-yt-feed-wrapper wpsr-yt-feed-template3" :class="[configs.feed_settings.layout_type === 'carousel' ? 'wpsr-youtube-slider-activate' : '', 'wpsr-yt-feed-template-'+this.$route.params.template_id]">
        <div class="wpsr-container">
            <Header
                v-if="configs.dynamic.header && configs.dynamic.header && ( configs.feed_settings.source_settings.feed_type !== 'search_feed' && configs.feed_settings.source_settings.feed_type !== 'single_video')"
                :header="configs.dynamic.header"
                :layout_type="configs.feed_settings.layout_type"
                :settings="configs.feed_settings.header_settings"
                :subscribe_button_settings="configs.feed_settings.subscribe_button_settings"
                :columnGaps="configs.feed_settings.column_gaps"
            />
            <div class="wpsr-row" v-if="configs.feed_settings.video_settings.play_mode === 'gallery' && configs.dynamic.items && configs.dynamic.items[0]">
                <div class="wpsr-col-12">
                    <div class="wpsr-yt-video wpsr-yt-video-player-gallery">
                        <iframe :src="'https://www.youtube.com/embed/' + [ configs.dynamic.items[0].snippet.resourceId && configs.dynamic.items[0].snippet.resourceId.videoId ? configs.dynamic.items[0].snippet.resourceId.videoId : configs.dynamic.items[0].id ] + '?autoplay=0'" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                </div>
            </div>
            <div
                :class="configs.feed_settings.layout_type === 'carousel' && has_pro ? 'wpsr-yt-feed-wrapper-inner' : ''"
                v-if="configs.dynamic.items && configs.dynamic.items.length"
            >
              <div :class="[configs.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-container' : '']">
                <div class="wpsr-yt-all-feed" :class="[configs.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-wrapper' : 'wpsr-row', configs.feed_settings.layout_type !== 'carousel' ? 'wpsr-column-gap-'+configs.feed_settings.column_gaps : '']">
                    <div :class="responsiveClasses" v-for="( feed,index ) in configs.dynamic.items" :key="index">
                      <video-item
                          @open-popup="setCurrentFeed(feed)"
                          :feed = "feed"
                          :configs = "configs"
                          :index="index"
                          :image_settings="image_settings"
                      >
                      </video-item>
                    </div>
                </div>
              </div>
              <swiper-navs v-if="configs.feed_settings.layout_type === 'carousel' && has_pro" :options="configs.feed_settings.carousel_settings"></swiper-navs>
            </div>
            <div v-else>
                <img class="wpsr-placeholder-image" :src="assets_url+ '/images/template/youtube-placeholder-template.png'" alt="Placeholder Image">
            </div>
            <Footer 
                v-if="configs.dynamic.header && ( configs.feed_settings.source_settings.feed_type !== 'search_feed' && configs.feed_settings.source_settings.feed_type !== 'single_video')"
                :header="configs.dynamic.header"
                :settings="configs.feed_settings.subscribe_button_settings"
            />
            <Popup
                v-if="selectedFeed && configs.feed_settings.video_settings.play_mode === 'popup'"
                :feed="selectedFeed"
                :header="configs.dynamic.header"
                :settings="configs.feed_settings.popup_settings ? configs.feed_settings.popup_settings : '' "
                :image_settings="image_settings"
                :configs = "configs"
            />
        </div>
    </div>
  </div>
</template>

<script type="text/babel">
import Header from './elements/_header';
import Footer from './elements/_footer';
import SwiperNavs from "../../../core-ui/editor/SwiperNavs";
import VideoItem from "./elements/_video-item";
import Popup from "./elements/_popup";
import {ytMixin} from "../../../../mixins/ytMixin";
import ErrorCard from "../ErrorCard.vue";

export default {
    inheritAttrs: false,
    props: ['configs', 'image_settings'],
    mixins: [ytMixin],
    components: {
      ErrorCard,
        Header,
        Footer,
        SwiperNavs,
        VideoItem,
        Popup
    }
}
</script>