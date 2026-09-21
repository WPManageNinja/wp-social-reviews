<template>
  <div>
    <div v-if="feedConfig.dynamic.error_message && feedConfig.dynamic.error_message.length">
      <ErrorCard :error_message="feedConfig.dynamic.error_message" />
    </div>
    <!-- No masonry/timeline layout classes: the mosaic ignores layout_type, as header.php does. -->
    <div class="wpsr-fb-feed-wrapper wpsr-fb-feed-template4"
         :class="[
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
            v-if="feedConfig.dynamic && feedConfig.dynamic.items && !hasAlbumFeed"
        >
          <p style="font-size: 18px" v-if="feedConfig.dynamic.items.length === 0 && feedConfig.feed_settings.source_settings.feed_type !== 'event_feed'">{{$t("Posts are not available!")}}</p>
          <div>
            <div
                class="wpsr-fb-all-feed"
                :class="feedContainerClasses"
            >
              <div
                  :class="getItemWrapperClass()"
                  v-for="(feed, key) in feedItems" :key="key"
              >

                <div class="wpsr-fb-feed-item wpsr-fb-post wpsr-fb-overlay-card" :data-post_id="getPostId(feed, feedConfig.feed_type)" :data-user_name="feed.page_id" :data-image_size="feedConfig.feed_settings.post_settings.resolution">
                  <div v-if="feedConfig.feed_settings.source_settings.feed_type === 'timeline_feed'" class="wpsr-fb-feed-inner">

                    <template v-if="hasMedia(feed)">
                      <div class="wpsr-fb-feed-content-wrapper wpsr-fb-feed-playmode"
                           role="button" tabindex="0" :aria-label="$t('Open post')"
                           @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)"
                           @keydown="wpsrActivateCard($event, feed, key)">
                        <Photo
                            :feed="feed"
                            :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                            :feed_settings="feedConfig.feed_settings.post_settings"
                            :image_settings="image_settings"
                        />
                      </div>
                      <span class="wpsr-fb-overlay-scrim"></span>
                      <OverlayAuthor :feed="feed" :feed_settings="feedConfig.feed_settings.post_settings"/>
                      <div class="wpsr-fb-overlay-hover" v-if="hasMessage(feed)">
                        <OverlayText :feed="feed" :feed_settings="feedConfig.feed_settings.post_settings" :show_summary="false"/>
                      </div>
                    </template>

                    <!-- Facebook posts without media get the white text card instead. -->
                    <div v-else class="wpsr-fb-feed-content-wrapper wpsr-fb-feed-playmode"
                         role="button" tabindex="0" :aria-label="$t('Open post')"
                         @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)"
                         @keydown="wpsrActivateCard($event, feed, key)">
                      <OverlayText
                          :feed="feed"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />
                    </div>
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'photo_feed'" class="wpsr-fb-feed-inner">
                    <div class="wpsr-fb-feed-content-wrapper wpsr-fb-feed-playmode"
                         role="button" tabindex="0" :aria-label="$t('Open post')"
                         @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)"
                         @keydown="wpsrActivateCard($event, feed, key)">
                      <Photo
                          :feed="feed"
                          :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />
                    </div>
                    <span class="wpsr-fb-overlay-scrim"></span>
                    <OverlayAuthor :feed="feed" :feed_settings="feedConfig.feed_settings.post_settings"/>
                    <div class="wpsr-fb-overlay-hover" v-if="hasMessage(feed)">
                      <OverlayText :feed="feed" :feed_settings="feedConfig.feed_settings.post_settings" :show_summary="false"/>
                    </div>
                  </div>
                  <div v-else-if="has_pro && (feedConfig.feed_settings.source_settings.feed_type === 'video_feed' || feedConfig.feed_settings.source_settings.feed_type === 'video_playlist_feed')" class="wpsr-fb-feed-inner">
                    <div class="wpsr-fb-feed-content-wrapper wpsr-fb-feed-playmode"
                         role="button" tabindex="0" :aria-label="$t('Open post')"
                         @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)"
                         @keydown="wpsrActivateCard($event, feed, key)">
                      <Videos
                          :feed="feed"
                          :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />
                    </div>
                    <span class="wpsr-fb-overlay-scrim"></span>
                    <OverlayAuthor :feed="feed" :feed_settings="feedConfig.feed_settings.post_settings"/>
                    <div class="wpsr-fb-overlay-hover" v-if="hasMessage(feed)">
                      <OverlayText :feed="feed" :feed_settings="feedConfig.feed_settings.post_settings" :show_summary="false"/>
                    </div>
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'event_feed'" class="wpsr-fb-feed-inner">
                    <div class="wpsr-fb-feed-content-wrapper wpsr-fb-feed-playmode"
                         role="button" tabindex="0" :aria-label="$t('Open event')"
                         @click="postDisplayMode($event, feed, feedConfig.feed_settings, key, image_settings)"
                         @keydown="wpsrActivateCard($event, feed, key)">
                      <Event
                          :feed="feed"
                          :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                          :feed_settings="feedConfig.feed_settings.post_settings"
                          :image_settings="image_settings"
                      />
                    </div>
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'album_feed'" class="wpsr-fb-feed-inner"
                       role="button" tabindex="0" :aria-label="$t('Open album')"
                       @click="handleAlbumClick(feed)"
                       @keydown="wpsrActivateAlbum($event, feed)">
                    <Albums
                        :feed="feed"
                        :feed_type="feedConfig.feed_settings.source_settings.feed_type"
                        :feed_settings="feedConfig.feed_settings.post_settings"
                        :image_settings="image_settings"
                        v-if="!hasAlbumFeed"
                    />
                  </div>
                  <div v-else-if="has_pro && feedConfig.feed_settings.source_settings.feed_type === 'single_album_feed' && feed.images" class="wpsr-fb-feed-inner">
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
import URLSummaryCard from "./elements/_url_summary_card";
import Description from "./elements/_description";
import Photo from "./elements/_photo";
import Videos from "./elements/_videos";
import AuthorLinks from "./elements/_author_links";
import Footer from "./elements/_footer";
import Popup from "./elements/_popup";
import Albums from "./elements/_albums";
import Album from "./elements/_album";
import SingleAlbumElement from "./elements/_single_album_element";
import {fbFeedMixin} from "../../../../mixins/fbFeedMixin";
import Event from "./elements/_event";
import Date from "./elements/_date";
import OverlayAuthor from "./elements/_overlay_author";
import OverlayText from "./elements/_overlay_text";
import EventError from "./elements/EventError";
import ErrorCard from "../ErrorCard";

export default {
  inheritAttrs: false,
  props: ['image_settings', 'feedConfig'],
  mixins: [fbFeedMixin],
  components: {
    Event,
    Header,
    URLSummaryCard,
    Photo,
    Videos,
    Description,
    AuthorLinks,
    Footer,
    Popup,
    Albums,
    Album,
    EventError,
    ErrorCard,
    SingleAlbumElement,
    Date,
    OverlayAuthor,
    OverlayText
  },
  methods: {
    // Enter on an inner link already fires a bubbling click, so only take keys aimed at the card
    // itself or it activates twice. A pointer click lands on that link and the browser follows it,
    // so reproduce both halves: run the handler, then follow whatever href it left behind.
    wpsrActivateCard(event, feed, key) {
      if (!this.wpsrIsCardActivation(event)) {
        return;
      }
      event.preventDefault();
      this.postDisplayMode(event, feed, this.feedConfig.feed_settings, key, this.image_settings);
      const wpsrLink = event.currentTarget.querySelector('a.wpsr-feed-link[href]');
      if (wpsrLink) {
        wpsrLink.click();
      }
    },
    wpsrActivateAlbum(event, feed) {
      if (!this.wpsrIsCardActivation(event)) {
        return;
      }
      event.preventDefault();
      this.handleAlbumClick(feed);
    },
    wpsrIsCardActivation(event) {
      return event.target === event.currentTarget && (event.key === 'Enter' || event.key === ' ');
    },
    // Mirrors template4.php: an empty message must not flash a blank panel over the photo on hover.
    hasMessage(feed) {
      // _description.vue renders on === 'true', so anything looser opens an empty panel.
      if (this.feedConfig.feed_settings.post_settings.display_description !== 'true') {
        return false;
      }
      return !!(feed && typeof feed.message === 'string' && feed.message.trim());
    },
    // A shared story has a link card, not a photo, so it also takes the text card.
    hasMedia(feed) {
      if (this.feedConfig.feed_settings.post_settings.display_media !== 'true') {
        return false;
      }
      if (!feed || feed.status_type === 'shared_story') {
        return false;
      }
      return !!(feed.attachments && feed.attachments.data && feed.attachments.data.length);
    }
  }
}
</script>
