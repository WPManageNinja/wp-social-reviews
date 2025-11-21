<template>
    <div>
        <div v-if="configs.dynamic.error_message && configs.dynamic.error_message.length">
            <ErrorCard :error_message = "configs.dynamic.error_message"/>
        </div>
        
        <div :class="'wpsr-ig-feed-template-'+$route.params.template_id" class="wpsr-ig-feed-wrapper wpsr-instagram-slider-activate wpsr-instagram-masonry-activate wpsr-ig-feed-template1">
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
                            :class="responsiveClasses"
                            v-for="(feed, index) in configs.dynamic.items"
                            :key="index"
                        >
                            <div class="wpsr-ig-post" :class="['wpsr-image-ratio-'+configs.feed_settings.post_settings.aspect_ratio]" @click="setCurrentFeed(feed,index, configs.feed_settings, image_settings)" :data-post_id="feed.id" :data-user_name="feed.username" :data-image_size="configs.feed_settings.post_settings.resolution" :data-index="index">
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
                                        <i class="el-icon-link" ></i>
                                        Add
                                      </span>
                                      <div v-else>
                                          <span class="wpsr-shoppable-icon" @click.prevent.stop="$emit('edit_shoppable_feed', feed)">
                                            <i class="el-icon-edit"></i>
                                            Edit
                                          </span>
                                          <span class="wpsr-shoppable-icon" @click.prevent.stop="$emit('remove_shoppable_feed', feed)">
                                             <i class="el-icon-delete"></i>
                                             Delete
                                          </span>
                                      </div>
                                    </div>
                                    <Media :feed="feed" :settings="configs.feed_settings" :image_settings="image_settings" :id="'wpsr-video-play-'+index"/>
                                    <div class="wpsr-ig-post-info" v-if="Object.keys(feed).length > 6">
                                        <a v-if="configs.feed_settings.post_settings.display_mode !== 'popup' && feed.shoppable_options && feed.shoppable_options.show_shoppable && feed.shoppable_options.url_settings.text.length"
                                           class="wpsr-shoppable-button" type="button" :href="feed.shoppable_options.url_settings.url"
                                           :target="feed.shoppable_options && feed.shoppable_options.url_settings.open_in_new_tab ? '_blank' : '_self'"
                                        >
                                          {{feed.shoppable_options.url_settings.text}}
                                        </a>

                                        <Statistics v-if="has_pro && !(configs.feed_settings.post_settings.display_mode !== 'popup' && feed.shoppable_options && feed.shoppable_options.show_shoppable)" :feed="feed" :settings="configs.feed_settings"/>

                                        <Caption v-if="!(configs.feed_settings.post_settings.display_mode !== 'popup' && feed.shoppable_options && feed.shoppable_options.show_shoppable)" :feed="feed" :settings="configs.feed_settings"/>

                                        <div class="wpsr-ig-icon">
                                            <svg viewBox="0 0 24 24" width="24" height="24">
                                                <path d="M17.1,1H6.9C3.7,1,1,3.7,1,6.9v10.1C1,20.3,3.7,23,6.9,23h10.1c3.3,0,5.9-2.7,5.9-5.9V6.9C23,3.7,20.3,1,17.1,1z
                                                    M21.5,17.1c0,2.4-2,4.4-4.4,4.4H6.9c-2.4,0-4.4-2-4.4-4.4V6.9c0-2.4,2-4.4,4.4-4.4h10.3c2.4,0,4.4,2,4.4,4.4V17.1z"></path>
                                                <path d="M16.9,11.2c-0.2-1.1-0.6-2-1.4-2.8c-0.8-0.8-1.7-1.2-2.8-1.4c-0.5-0.1-1-0.1-1.4,0C10,7.3,8.9,8,8.1,9S7,11.4,7.2,12.7
                                                    C7.4,14,8,15.1,9.1,15.9c0.9,0.6,1.9,1,2.9,1c0.2,0,0.5,0,0.7-0.1c1.3-0.2,2.5-0.9,3.2-1.9C16.8,13.8,17.1,12.5,16.9,11.2z
                                                     M12.6,15.4c-0.9,0.1-1.8-0.1-2.6-0.6c-0.7-0.6-1.2-1.4-1.4-2.3c-0.1-0.9,0.1-1.8,0.6-2.6c0.6-0.7,1.4-1.2,2.3-1.4
                                                    c0.2,0,0.3,0,0.5,0s0.3,0,0.5,0c1.5,0.2,2.7,1.4,2.9,2.9C15.8,13.3,14.5,15.1,12.6,15.4z"></path>
                                                <path d="M18.4,5.6c-0.2-0.2-0.4-0.3-0.6-0.3s-0.5,0.1-0.6,0.3c-0.2,0.2-0.3,0.4-0.3,0.6s0.1,0.5,0.3,0.6c0.2,0.2,0.4,0.3,0.6,0.3
                                                    s0.5-0.1,0.6-0.3c0.2-0.2,0.3-0.4,0.3-0.6C18.7,5.9,18.6,5.7,18.4,5.6z"></path>
                                            </svg>
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
              :user_avatar="configs.dynamic && configs.dynamic.header && configs.dynamic.header.avatar ? configs.dynamic.header.avatar.local_avatar : (configs.dynamic && configs.dynamic.header && configs.dynamic.header.user_avatar ? configs.dynamic.header.user_avatar : '')"
              :feedLength="configs.dynamic.items ? configs.dynamic.items.length : 0"
              :connected_ids="configs.connected_ids"
              :account_to_show="configs.feed_settings.header_settings.account_to_show"
              :currentIndex="currentIndex"
              :settings="configs.feed_settings"
              @on-previous="getPreviousFeed"
              @on-next="getNextFeed"
          />
        </div>
    </div>
</template>

<script type="text/babel">
import Media from './elements/_media';
import Statistics from './elements/_statistics';
import Caption from './elements/_caption';
import Header from './elements/_header';
import Popup from './elements/_popup';
import Footer from './elements/_footer';
import SwiperNavs from "../../../core-ui/editor/SwiperNavs";
import {popupMixin} from "../../../../mixins/popupMixin";
import {igMixin} from "../../../../mixins/igMixin";
import ErrorCard from "../ErrorCard";
export default {
    inheritAttrs: false,
    name: 'instagram_template1',
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
    watch :{
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