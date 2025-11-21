<template>
  <div>
    <div v-if="feedConfig.dynamic && feedConfig.dynamic.error_message && feedConfig.dynamic.error_message.error_message && feedConfig.dynamic.error_message.error_message.length">
      <ErrorCard :error_message = "feedConfig.dynamic.error_message.error_message"/>
    </div>

    <div class="wpsr-tiktok-feed-wrapper wpsr-tiktok-masonry-activate wpsr-tiktok-feed-template1"
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
                    <div class="wpsr-tt-all-feed wpsr-has-equal-height"
                         :class="[
                           feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-wrapper' : 'wpsr-row',
                           feedConfig.feed_settings.layout_type !== 'carousel' ? 'wpsr-column-gap-'+feedConfig.feed_settings.column_gaps : ''
                      ]"
                    >
                        <div class="wpsr-mb-30" :class="responsiveClasses" v-for="(feed, key) in feedItems" :key="key">
                            <div class="wpsr-tiktok-feed-item wpsr-tt-post wpsr-tiktok-feed-playmode" @click="postDisplayMode($event, feed, feedConfig.feed_settings, key)" :data-post_id="feed.id" :data-user_name="feed.user.name" :data-image_size="feedConfig.feed_settings.post_settings.resolution">
<!--                            <div v-if="feedConfig.feed_settings.source_settings.feed_type === 'user_feed' || feedConfig.feed_settings.source_settings.feed_type === 'single_video_feed_ids'" class="wpsr-tiktok-feed-inner">-->
                                <div class="wpsr-tiktok-feed-inner">
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
                                          <div v-if="feedConfig.feed_settings.post_settings.display_platform_icon === 'true'" class="wpsr-tiktok-icon-outer"></div>
                                          <Statistics
                                              v-if="has_pro"
                                              :feed="feed"
                                              :feed_settings="feedConfig.feed_settings.post_settings"
                                          />
                                          <Author
                                              :feed="feed"
                                              :feed_settings="feedConfig.feed_settings.post_settings"
                                          />
                                        </div>
                                  </div>
                                </div>
                                <div class="wpsr-tiktok-feed-image-hover-over-content">
                                  <div v-if="feedConfig.feed_settings.post_settings.display_platform_icon === 'true'" class="wpsr-tiktok-icon"></div>
                                  <Title
                                      :feed="feed"
                                      :feed_settings="feedConfig.feed_settings.post_settings"
                                      :display_desc="true"
                                  />
                                  <a class="wpsr-tiktok-feed-author-name" v-if="feedConfig.feed_settings.post_settings.display_author_name === 'true'" :href="feed.user.profile_url" target="_blank" rel="nofollow" >{{feed.user.name}}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <swiper-navs v-if="feedConfig.feed_settings.layout_type === 'carousel' && has_pro" :options="feedConfig.feed_settings.carousel_settings"></swiper-navs>
            </div>

            <div v-else-if="feedConfig.dynamic && feedConfig.dynamic.error_message && !feedConfig.dynamic.error_message.error_message">
                <div class="wpsr-tiktok-feed-event-error-box">
                    <p class="wpsr-tiktok-feed-event-error-box-title">
                        Notice!
                    </p>
                    <p class="wpsr-tiktok-feed-event-error-box-message">
                        {{feedConfig.dynamic.error_message}}
                    </p>
                </div>
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
import Footer from "./elements/_footer";
import Statistics from "./elements/_statistics";
import Author from "./elements/_author";
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
            feedItems: [],
        }
    },
    components: {
        Header,
        Photo,
        Title,
        Statistics,
        Author,
        Footer,
        SwiperNavs,
        Popup,
        ErrorCard
    }
}
</script>