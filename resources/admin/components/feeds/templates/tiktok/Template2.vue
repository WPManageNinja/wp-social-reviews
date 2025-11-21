<template>
  <div>
    <div v-if="feedConfig.dynamic && feedConfig.dynamic.error_message && feedConfig.dynamic.error_message.error_message && feedConfig.dynamic.error_message.error_message.length">
      <ErrorCard :error_message = "feedConfig.dynamic.error_message.error_message"/>
    </div>

  <div class="wpsr-tiktok-feed-wrapper wpsr-tiktok-masonry-activate wpsr-tiktok-feed-template2"
       :class="[
           'wpsr-tiktok-'+feedConfig.feed_settings.source_settings.feed_type,
           'wpsr-tiktok-feed-template-'+this.$route.params.template_id
       ]"
       v-if="feedConfig"
  >
    <div class="wpsr-container">
      <Header
          v-if="feedConfig.dynamic && feedConfig.dynamic.header"
          :header_settings="feedConfig.feed_settings.header_settings"
          :account="feedConfig.dynamic.header"
          :settings="feedConfig.feed_settings"
          :translations="translations"
      />
      <div
          :class="feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'wpsr-tiktok-feed-wrapper-inner' : ''"
          v-if="feedConfig.dynamic && feedConfig.dynamic.items"
      >
        <div :class="[feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-container' : '']">
          <div
              class="wpsr-tt-all-feed wpsr-has-equal-height"
              :class="[
                     feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-wrapper' : 'wpsr-row',
                     feedConfig.feed_settings.layout_type !== 'carousel' ? 'wpsr-column-gap-'+feedConfig.feed_settings.column_gaps : ''
                 ]"
          >

            <div
                class="wpsr-mb-30"
                :class="responsiveClasses"
                v-for="(feed, key) in feedItems" :key="key"
            >

              <div class="wpsr-tiktok-feed-item " >
<!--                <div v-if="feedConfig.feed_settings.post_settings.display_platform_icon === 'true'" class="wpsr-tiktok-icon-outer" ></div>-->
                <!--                                <div v-if="feedConfig.feed_settings.source_settings.feed_type === 'user_feed' || feedConfig.feed_settings.source_settings.feed_type === 'specific_videos'" class="wpsr-tiktok-feed-inner">-->
                <div class="wpsr-tiktok-feed-inner">

                  <div class="wpsr-tiktok-feed-content-preview wpsr-tt-post" @click="postDisplayMode($event, feed, feedConfig.feed_settings, key)" :data-post_id="feed.id" :data-user_name="feed.user.name" :data-image_size="feedConfig.feed_settings.post_settings.resolution">
                    <div class="wpsr-tiktok-feed-image">
                      <Photo
                          :feed="feed"
                          :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />
                      <div class="wpsr-tiktok-feed-content-box">
                        <div v-if="feedConfig.feed_settings.post_settings.display_play_icon === 'true'" class="wpsr-tiktok-feed-video-play">
                          <div class="wpsr-tiktok-feed-video-play-icon"></div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="wpsr-tiktok-feed-content-wrapper">
                    <Title
                        :feed="feed"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                        :display_desc="true"
                    />
                    <div class="wpsr-tiktok-feed-content-top-wrapper">
                      <Author
                          :feed="feed"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                      />
                     <a v-if="feedConfig.feed_settings.post_settings.display_platform_icon === 'true'" class="wpsr-tiktok-icon-temp-2" target="_blank" :href="'https://www.tiktok.com/@' + feed.user.name + '/video/' + feed.id"></a>
                    </div>
                    <Statistics
                        v-if="has_pro"
                        :feed="feed"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                    />
                  </div>

                </div>
              </div>

            </div>

            </div>
          </div>
          <swiper-navs v-if="feedConfig.feed_settings.layout_type === 'carousel' && has_pro" :options="feedConfig.feed_settings.carousel_settings"></swiper-navs>
        </div>

        <Footer
            :account="feedConfig.dynamic.header"
            :settings="feedConfig.feed_settings"
        />
      </div>

    <Popup
        v-if="feedConfig.feed_settings.post_settings.display_mode === 'popup'"
        :feed="selectedFeed"
        :account="selectedFeed.from"
        :feedLength="feedConfig.dynamic.items ? feedConfig.dynamic.items.length : 0"
        :currentIndex="currentIndex"
        :settings="feedConfig.feed_settings"
        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
        :image_settings="image_settings"
        @on-previous="getPreviousFeed"
        @on-next="getNextFeed"
    />
  </div>
  </div>
</template>

<script>
import Header from './elements/_header';
import Photo from "./elements/_photo";
import Title from "./elements/_title";
import Author from "./elements/_author";
import Statistics from "./elements/_statistics";
import Footer from "./elements/_footer";
import SwiperNavs from "../../../core-ui/editor/SwiperNavs";
import Popup from "./elements/_popup";
import {tiktokFeedMixin} from "../../../../mixins/tiktokFeedMixin";
import ErrorCard from "../ErrorCard.vue";

export default {
  inheritAttrs: false,
  props: ['feedConfig', 'translations', 'image_settings'],
  mixins: [tiktokFeedMixin],
  data() {
    return {
      selectedFeed: [],
      currentIndex: null,
      feedItems: []
    }
  },
  components: {
    ErrorCard,
    Header,
    Photo,
    Title,
    Author,
    Statistics,
    Footer,
    SwiperNavs,
    Popup,
  },
  methods: {
    checkForSpacing() {
      let feed_settings = this.feedConfig.feed_settings.post_settings;
      let {display_description, display_author_photo, display_author_name, display_date, display_platform_icon} = feed_settings;

      let shouldAddSpacing = (display_description === 'true' || display_author_photo === 'true' || display_author_name === 'true' || display_date === 'true' || display_platform_icon === 'true');
      jQuery('.wpsr-tiktok-feed-content-top-wrapper').toggleClass('wpsr-tiktok-feed-author-avatar-wrapper-remove-spacing', !shouldAddSpacing);
    }

  },
  mounted() {
    this.checkForSpacing();
  },
  watch: {
    feedConfig: {
      handler: function (val, oldVal) {
        this.checkForSpacing();
      },
      deep: true
    }
  }
}
</script>