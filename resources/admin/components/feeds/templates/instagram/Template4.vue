<template>
    <div>
      <div v-if="configs.dynamic && configs.dynamic.error_message && configs.dynamic.error_message.length">
        <ErrorCard :error_message="configs.dynamic.error_message"/>
      </div>
        <div class="wpsr-ig-feed-wrapper wpsr-instagram-slider-activate wpsr-ig-feed-template4" :class="'wpsr-column-gap-'+configs.feed_settings.column_gaps+' wpsr-ig-feed-template-'+$route.params.template_id+' wpsr-ig-scheme-'+(configs.feed_settings.color_scheme || 'light')+(configs.feed_settings.layout_type === 'list' ? ' wpsr-instagram-list-activate' : '')+(configs.feed_settings.layout_type === 'masonry' ? ' wpsr-instagram-masonry-activate' : '')">
            <div class="wpsr-container">
              <Header
                    v-if="configs.dynamic && configs.dynamic.header"
                    :connected_ids="configs.connected_ids"
                    :header="configs.dynamic.header"
                    :settings="configs.feed_settings"
                    :account_to_show="configs.feed_settings.header_settings.account_to_show"
                    :user_avatar="configs.dynamic && configs.dynamic.header && configs.dynamic.header.user_avatar"
                />
                <div
                    :class="configs.feed_settings.layout_type === 'carousel' && has_pro ? 'wpsr-ig-feed-wrapper-inner' : ''"
                    v-if="configs.dynamic && configs.dynamic.items"
                >
                  <div :class="[isCarouselLayout ? 'swiper-container' : '']">
                      <div :class="feedContainerClasses">
                          <div
                              :class="getItemWrapperClass()"
                              v-for="(feed, index) in configs.dynamic.items"
                              :key="index"
                          >
                            <div v-if="feed && (feed.media_url || feed.default_media || feed.thumbnail_url)" class="wpsr-ig-post wpsr-ig-post-pin-card" :class="['wpsr-image-ratio-'+configs.feed_settings.post_settings.aspect_ratio]" @click="setCurrentFeed(feed,index, configs.feed_settings, image_settings)" :data-post_id="feed.id" :data-user_name="feed.username" :data-image_size="configs.feed_settings.post_settings.resolution" :data-index="index">
                                  <!-- Kept outside the anchor: a link may not contain buttons. -->
                                  <div class="wpsr-shoppable-icon_wrap" v-if="has_pro && configs.feed_settings.shoppable_settings.enable_shoppable === 'true'">
                                     <button type="button" class="wpsr-shoppable-icon wpsr-shoppable-icon-add"
                                           v-if="!(configs.feed_settings.shoppable_settings.shoppable_feeds[feed.username] && configs.feed_settings.shoppable_settings.shoppable_feeds[feed.username][feed.id])"
                                           :aria-label="$t('Add promotional URL')"
                                           @click.prevent.stop="$emit('add_selected_shoppable_feed', feed)">
                                      <el-icon><Link /></el-icon>
                                      Add
                                    </button>
                                    <div v-else>
                                      <button type="button" class="wpsr-shoppable-icon" :aria-label="$t('Edit promotional URL')" @click.prevent.stop="$emit('edit_shoppable_feed', feed)">
                                       <el-icon><EditPen /></el-icon>
                                      Edit
                                      </button>
                                        <button type="button" class="wpsr-shoppable-icon" :aria-label="$t('Remove promotional URL')" @click.prevent.stop="$emit('remove_shoppable_feed', feed)">
                                         <el-icon><Delete /></el-icon>
                                         Delete
                                      </button>
                                    </div>
                                  </div>
                                  <a class="wpsr-ig-playmode" :href="getFeedHref(feed)" :tabindex="getFeedTabindex(feed)" :role="getFeedRole(feed)" @keydown="activateFeedOnKey" :target="getFeedTarget(feed)" @click="postDisplayMode($event, feed, configs.feed_settings)">
                                      <Media :feed="feed" :settings="configs.feed_settings" :image_settings="image_settings" :id="'wpsr-video-play-'+index"/>

                                      <div class="wpsr-ig-post-pin-play" aria-hidden="true" v-if="feed.media_type === 'VIDEO'">
                                          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 5.14v13.72L19 12 8 5.14Z"/></svg>
                                      </div>

                                      <div class="wpsr-ig-post-pin-icon">
                                          <InstagramIcon :width="22" :height="22" />
                                      </div>

                                      <div class="wpsr-ig-post-pin-overlay" v-if="Object.keys(feed).length > 6">
                                          <div class="wpsr-ig-post-pin-user" v-if="configs.dynamic.header">
                                              <img v-if="configs.dynamic.header.avatar && configs.dynamic.header.avatar.local_avatar" class="wpsr-ig-post-overlay-avatar" :src="configs.dynamic.header.avatar.local_avatar" :alt="feed.username" />
                                              <img v-else-if="configs.dynamic.header.user_avatar" class="wpsr-ig-post-overlay-avatar" :src="configs.dynamic.header.user_avatar" :alt="feed.username" />
                                              <div class="wpsr-ig-post-overlay-user-details">
                                                  <span class="wpsr-ig-post-overlay-username">{{ feed.username }}</span>
                                                  <span class="wpsr-ig-post-overlay-time" v-if="feed.timestamp">{{ timeAgo(feed.timestamp) }}</span>
                                              </div>
                                          </div>

                                          <div class="wpsr-ig-post-pin-hover">
                                              <div class="wpsr-ig-post-pin-hover-content">
                                                  <Caption :feed="feed" :settings="configs.feed_settings"/>
                                                  <Statistics :feed="feed" :settings="configs.feed_settings"/>
                                              </div>
                                          </div>
                                      </div>

                                      <div v-if="feed.shoppable_options && feed.shoppable_options.show_shoppable && configs.feed_settings.shoppable_settings.display_shoppable_icon === 'true'" class="wpsr-ig-shoppble-icon-top">
                                          <img :src="assets_url+'/images/svg/shoppable-icon.svg'" alt="shopping bag">
                                      </div>
                                  </a>
                              </div>
                          </div>
                      </div>
                  </div>
                  <swiper-navs v-if="configs.feed_settings.layout_type === 'carousel' && has_pro" :options="configs.feed_settings.carousel_settings"></swiper-navs>
                </div>
                <div v-else>
                  <img class="wpsr-placeholder-image" :src="assets_url+ '/images/template/instagram-placeholder-template.png'" alt="Placeholder Image">
                </div>
                <Footer
                    :connected_ids="configs.connected_ids"
                    :settings="configs.feed_settings"
                    :account_to_show="configs.feed_settings.header_settings.account_to_show"
                />
            </div>

            <Popup
              v-if="configs.feed_settings.post_settings.display_mode === 'popup'"
              :feed="selectedFeed"
              :user_avatar="popupUserAvatar"
              :feedLength="popupFeedLength"
              :currentIndex="currentIndex"
              :settings="configs.feed_settings"
              :connected_ids="configs.connected_ids"
              :account_to_show="configs.feed_settings.header_settings.account_to_show"
              @on-previous="getPreviousFeed"
              @on-next="getNextFeed"
            />
        </div>
    </div>
</template>
<script>
import Media from './elements/_media.vue';
import Statistics from './elements/_statistics.vue';
import Caption from './elements/_caption.vue';
import Header from './elements/_header';
import Popup from './elements/_popup.vue';
import Footer from './elements/_footer';
import SwiperNavs from "../../../core-ui/editor/SwiperNavs";
import {popupMixin} from "../../../../mixins/popupMixin";
import {igMixin} from "../../../../mixins/igMixin";
import ErrorCard from "../ErrorCard";
import InstagramIcon from "../../../pieces/icons/InstagramIcon";

export default {
  inheritAttrs: false,
  name: 'instagram_template4',
  props: ['configs','image_settings'],
  mixins: [popupMixin, igMixin],
  components: {
    Media,
    Statistics,
    Caption,
    Header,
    Popup,
    Footer,
    SwiperNavs,
    ErrorCard,
    InstagramIcon
  },
  watch: {
    'configs.feed_settings.post_settings.display_mode': {
      handler: function (newVal, oldVal) {
        if (newVal !== oldVal){
          this.resetSelectedFeed();
        }
      },
      deep: true
    }
  }
}
</script>
