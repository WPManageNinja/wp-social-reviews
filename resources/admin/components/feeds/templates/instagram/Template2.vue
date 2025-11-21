<template>
    <div>
      <div v-if="configs && configs.dynamic.error_message.length">
        <ErrorCard :error_message="configs.dynamic.error_message"/>
      </div>
        <div class="wpsr-ig-feed-wrapper wpsr-instagram-slider-activate wpsr-instagram-masonry-activate wpsr-ig-feed-template2" :class="'wpsr-column-gap-'+configs.feed_settings.column_gaps+' wpsr-ig-feed-template-'+$route.params.template_id">
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
                  <div :class="[configs.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-container' : '']">
                      <div class="wpsr-ig-all-feed" :class="[configs.feed_settings.layout_type === 'carousel' && has_pro ? 'swiper-wrapper' : 'wpsr-row', configs.feed_settings.layout_type !== 'carousel' ? 'wpsr-column-gap-'+configs.feed_settings.column_gaps : '']">
                          <div
                              class="wpsr-mb-30"
                              :class="responsiveClasses"
                              v-for="(feed, index) in configs.dynamic.items"
                              :key="index"
                          >
                            <div v-if="feed && (feed.media_url || feed.default_media)" class="wpsr-ig-post" :class="['wpsr-image-ratio-'+configs.feed_settings.post_settings.aspect_ratio]" @click="setCurrentFeed(feed,index, configs.feed_settings, image_settings)" :data-post_id="feed.id" :data-user_name="feed.username" :data-image_size="configs.feed_settings.post_settings.resolution" :data-index="index">
                                  <div class="wpsr-ig-post-media-wrapper">
                                      <a class="wpsr-ig-playmode" :href="
                                        ((configs.feed_settings.shoppable_settings.enable_shoppable === 'true' && feed.shoppable_options && feed.shoppable_options.from === 'template_settings') ||
                                        (configs.feed_settings.shoppable_settings.include_shoppable_by_hashtags === 'true' && feed.shoppable_options && feed.shoppable_options.from === 'include_shoppable_by_hashtags')) &&
                                        feed.shoppable_options && feed.shoppable_options.show_shoppable ?
                                        feed.shoppable_options.url_settings.url : feed.permalink"
                                         :target="feed.shoppable_options && feed.shoppable_options.url_settings.open_in_new_tab ? '_blank' : ''"
                                         @click="postDisplayMode($event, feed, configs.feed_settings)"
                                      >
                                          <div class="wpsr-shoppable-icon_wrap" v-if="has_pro && configs.feed_settings.shoppable_settings.enable_shoppable === 'true'">
                                             <span class="wpsr-shoppable-icon wpsr-shoppable-icon-add"
                                                   v-if="!(configs.feed_settings.shoppable_settings.shoppable_feeds[feed.username] && configs.feed_settings.shoppable_settings.shoppable_feeds[feed.username][feed.id])"
                                                   @click.prevent.stop="$emit('add_selected_shoppable_feed', feed)"
                                                   >
                                              <el-icon><Link /></el-icon>
                                              Add
                                            </span>
                                            <div v-else>
                                              <span class="wpsr-shoppable-icon" @click.prevent.stop="$emit('edit_shoppable_feed', feed)">
                                               <el-icon><EditPen /></el-icon>
                                              Edit
                                              </span>
                                                <span class="wpsr-shoppable-icon" @click.prevent.stop="$emit('remove_shoppable_feed', feed)">
                                                 <el-icon><Delete /></el-icon>
                                                 Delete
                                              </span>
                                            </div>
                                          </div>
                                          <Media :feed="feed" :settings="configs.feed_settings" :image_settings="image_settings" :id="'wpsr-video-play-'+index"/>
                                      </a>
                                  </div>
                                  <a v-if="configs.feed_settings.post_settings.display_mode !== 'popup' && feed.shoppable_options && feed.shoppable_options.show_shoppable"
                                     class="wpsr-shoppable-button" type="button" :href="feed.shoppable_options.url_settings.url"
                                     :target="feed.shoppable_options && feed.shoppable_options.url_settings.open_in_new_tab ? '_blank' : '_self'"
                                  >
                                    {{feed.shoppable_options.url_settings.text}}
                                    <i class="icon-angle-right"></i>
                                  </a>
                                  <div class="wpsr-ig-post-info" v-if="Object.keys(feed).length > 6">
                                      <Statistics v-if="has_pro" :feed="feed" :settings="configs.feed_settings"/>
                                      <Caption :feed="feed" :settings="configs.feed_settings"/>
                                  </div>
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
              :user_avatar="configs.dynamic && configs.dynamic.header && configs.dynamic.header.avatar ? configs.dynamic.header.avatar.local_avatar : configs.dynamic.header.user_avatar"
              :feedLength="configs.dynamic.length"
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

export default {
  inheritAttrs: false,
  name: 'instagram_template2',
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
    ErrorCard
  },
  watch: {
    'configs.feed_settings.post_settings.display_mode': {
      handler: function (newVal, oldVal) {
        if (newVal !== oldVal){
          this.selectedFeed = [];
        }
      },
      deep: true
    }
  }
}
</script>