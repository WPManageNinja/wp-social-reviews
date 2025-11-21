<template>
  <div>
    <div v-if="feedConfig.dynamic.error_message && feedConfig.dynamic.error_message.length">
      <ErrorCard :error_message="feedConfig.dynamic.error_message" />
    </div>
    <div class="wpsr-fb-feed-wrapper wpsr-facebook-masonry-activate wpsr-fb-feed-template2"
         :class="[
             feedConfig.feed_settings.layout_type === 'timeline' ? 'wpsr-fb-feed-layout-standard' : '',
             'wpsr-fb-'+feedConfig.feed_settings.source_settings.feed_type,
             feedConfig.feed_settings.post_settings.equal_height === 'true' ? 'wpsr-has-equal-height' : '',
             'wpsr-fb-feed-template-'+$route.params.template_id,
             generatePhotoAlbumFeedClass
         ]"
         v-if="feedConfig"
    >
      <div class="wpsr-container">
        <Header
            v-if="feedConfig.dynamic && feedConfig.dynamic.header"
            :header_settings="feedConfig.feed_settings.header_settings"
            :account="feedConfig.dynamic.header"
            :settings="feedConfig.feed_settings"
        ></Header>

        <div
            :class="feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'wpsr-fb-feed-wrapper-inner' : ''"
            v-if="feedConfig.dynamic && feedConfig.dynamic.items && !hasAlbumFeed"
        >
          <p style="font-size: 18px" v-if="feedConfig.dynamic.items.length === 0 && feedConfig.feed_settings.source_settings.feed_type !== 'event_feed'">{{$t("Posts are not available!")}}</p>
          <div :class="[feedConfig.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-container' : '']">
            <div
                class="wpsr-fb-all-feed"
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

                <div class="wpsr-fb-feed-item wpsr-fb-post" :data-post_id="getPostId(feed, feedConfig.feed_type)" :data-user_name="feed.page_id" :data-image_size="feedConfig.feed_settings.post_settings.resolution">
                  <div v-if="feedConfig.feed_settings.source_settings.feed_type === 'timeline_feed'" class="wpsr-fb-feed-inner">

                    <div class="wpsr-fb-feed-content-wrapper wpsr-fb-feed-playmode" @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)"
                    >
                      <Photo
                          v-if="feed.status_type !== 'shared_story'"
                          :feed="feed"
                          :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />

                      <URLSummaryCard
                          v-else-if="feed.status_type === 'shared_story' && feed.attachments && feed.attachments.data"
                          :feed="feed"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />
                    </div>

                    <AuthorLinks
                        :feed="feed"
                        :account="feed.from"
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                    />

                    <Description
                        :feed="feed"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                    />

                    <Statistics
                        v-if="has_pro"
                        :feed="feed"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                    />
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'photo_feed'" @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)">
                    <Photo
                        :feed="feed"
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                        :image_settings="image_settings"
                    />
                  </div>
                  <div  v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'video_feed'  || feedConfig.feed_settings.source_settings.feed_type === 'video_playlist_feed'" @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)">
                    <Videos
                        :feed="feed"
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                        :image_settings="image_settings"
                    />
                  </div>
                  <div  v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'event_feed'" class="wpsr-fb-feed-inner" @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)">
                    <Event
                        :feed="feed"
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                        :image_settings="image_settings"
                    />
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'album_feed'" @click="handleAlbumClick(feed)">
                    <Albums
                        :feed="feed"
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                        :image_settings="image_settings"
                        v-if="!hasAlbumFeed"
                    />
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'single_album_feed' && feed.images">
                    <SingleAlbumElement :feed="feed" 
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type" 
                        :feed_settings="feedConfig.feed_settings"
                        :image_settings="image_settings"
                        :feed_key="key"
                        @postDisplayMode="postDisplayMode"
                      />
                  </div>
                </div>

              </div>

            </div>
          </div>
          <swiper-navs v-if="feedConfig.feed_settings.layout_type === 'carousel' && has_pro" :options="feedConfig.feed_settings.carousel_settings"></swiper-navs>
        </div>

        <div class="wpsr-event-error-container" v-if="feedConfig.feed_settings.source_settings.feed_type === 'photo_feed' &&
          ((feedConfig.dynamic && feedConfig.dynamic.items && feedConfig.dynamic.items.length === 0)
            || (feedConfig.dynamic && feedConfig.dynamic.error_message))">
          <div class="wpsr-event-error-message">
            <h2>Facebook Photos API Limitation</h2>
            <p>
              We regret to inform you that due to limitations in accessing Facebook Photos data through the API, we are currently unable to provide a photos feed. As an alternative, you can collect timeline feeds and then apply the following steps:
            
            </p>
            <ol>
              <li>Go to Filters -> Select Display posts with -> Choose Single Photo or Multiple Photos.</li>
              <li>Navigate to Feed Settings -> Adjust the settings under Displayed Elements as needed.</li>
            </ol>
          </div>
        </div>

        <Album
            v-if="has_pro && hasAlbumFeed"
            :feed="selectedAlbumFeed"
            :feedConfig="feedConfig"
            :image_settings="image_settings"
            @handleAlbumBreadCrumbs="handleAlbumBreadCrumbs"
            @breadCrumbs="hasAlbumFeed = false"
            @postDisplayMode="postDisplayMode"
        />

        <Footer
            :account="feedConfig.dynamic.header"
            :settings="feedConfig.feed_settings"
        />
      </div>

      <Popup
          v-if="selectedFeed && feedConfig.feed_settings.post_settings.display_mode === 'popup'"
          :feed="selectedFeed"
          :account="selectedFeed.from"
          :feedLength="feedConfig.dynamic.items ? feedConfig.dynamic.items.length : 0"
          :albumFeedLength="selectedFeed.photos ? selectedFeed.photos.data.length : 0"
          :currentIndex="currentIndex"
          :settings="feedConfig.feed_settings"
          :image_settings="image_settings"
          :feed_type="feedConfig.feed_settings.source_settings.feed_type"
          @on-previous="getPreviousFeed"
          @on-next="getNextFeed"
      />
    </div>
  </div>
</template>

<script>
import Header from './elements/_header';
import Statistics from "./elements/_statistics";
import URLSummaryCard from "./elements/_url_summary_card";
import Description from "./elements/_description";
import Photo from "./elements/_photo";
import Videos from "./elements/_videos";
import AuthorLinks from "./elements/_author_links";
import Footer from "./elements/_footer";
import SwiperNavs from "../../../core-ui/editor/SwiperNavs";
import Popup from "./elements/_popup";
import Albums from "./elements/_albums";
import Album from "./elements/_album";
import SingleAlbumElement from "./elements/_single_album_element";
import {fbFeedMixin} from "../../../../mixins/fbFeedMixin";
import Event from "./elements/_event";
import EventError from "./elements/EventError";
import ErrorCard from "../ErrorCard";

export default {
  inheritAttrs: false,
  props: ['image_settings', 'feedConfig'],
  mixins: [fbFeedMixin],
  components: {
    Event,
    Statistics,
    Header,
    URLSummaryCard,
    Photo,
    Videos,
    Description,
    AuthorLinks,
    Footer,
    SwiperNavs,
    Popup,
    Albums,
    Album,
    EventError,
    ErrorCard,
    SingleAlbumElement
  }
}
</script>