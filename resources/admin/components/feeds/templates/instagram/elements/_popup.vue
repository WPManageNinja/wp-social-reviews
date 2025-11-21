<template>
    <div class="wpsr-feed-popup-overlay" :class="'wpsr-ig-feed-popup-box-'+this.$route.params.template_id">
        <div class="wpsr-feed-popup-box-wraper">
            <div class="wpsr-feed-popup-box-wraper-inner" :class="settings.popup_settings.display_sidebar === 'false' ? 'wpsr-feed-popup-box-no-sidebar' : ''">

              <div v-if="settings.popup_settings.display_next_prev_arrows === 'true'">
                <div v-if="currentIndex !== 0" @click="getPreviousFeed" class="wpsr-prev-btn"><span class="icon-angle-left"></span></div>
                <div v-if="currentIndex !== (feedLength - 1)" @click="getNextFeed" class="wpsr-next-btn"><span class="icon-angle-right"></span></div>
              </div>

                <div class="wpsr-feed-popup-close-btn"></div>
                <div class="wpsr-feed-popup-media wpsr-ig-feed-popup-media">
                    <div class="wpsr-feed-popup-carousel-wrapper swiper-container" v-if="feed.children && feed.has_carousel">
                      <div class="swiper-wrapper">
                          <div class="swiper-slide" v-for="(feed, index) in feed.children ? feed.children.data : ''" :key="index">
                              <img v-if="feed.media_type === 'IMAGE'" :src="feed.media_url ? feed.media_url : feed.default_media" alt="">
                              <video id="wpsr-feed-popup-video" v-if="feed && feed.media_type === 'VIDEO'" playsinline="" autoplay=true preload="auto" :src="feed.media_url ? feed.media_url : feed.default_media" v-bind="!feed.media_url ? (feed.default_media.includes('video_dashinit') ? videoAttrs : ''): ''" :poster="feed.thumbnail_url">
                                  <source :src="feed.media_url ? feed.media_url : feed.default_media" type="video/mp4">
                              </video>
                          </div>
                      </div>
                      <div class="wpsr-swiper-prev-next wpsr-swiper-next icon-arrow-circle-o-right"></div>
                      <div class="wpsr-swiper-prev-next wpsr-swiper-prev icon-arrow-circle-o-left"></div>
                      <div class="wpsr-swiper-pagination swiper-pagination"></div>
                    </div>

                    <a :href="feed.permalink" v-if="!feed.has_carousel &&feed.media_name === 'VIDEO' && feed.media_type === 'IMAGE'" target="_blank">
                      <img :src="feed.media_url ? feed.media_url : feed.default_media" alt="">
                    </a>

                    <div v-if="!feed.has_carousel &&feed.media_name === 'VIDEO' && feed.media_type === 'IMAGE'" class="wpsr-ig-post-type-icon" :class="'wpsr-ig-post-type-' + feed.media_name"></div>

                    <img v-else-if="!feed.has_carousel && (feed.media_name === 'IMAGE' || feed.media_name === 'CAROUSEL_ALBUM' )  && (feed.media_type === 'IMAGE')" :src="feed.media_url ? feed.media_url : feed.default_media" alt="">
                    <video id="wpsr-feed-popup-video" v-if="feed && feed.media_type === 'VIDEO' && feed.media_url.includes('.mp4')" playsinline="" controls autoplay=true preload="none" :src="feed.media_url ? feed.media_url : feed.default_media" v-bind="(!feed.media_url && feed.default_media) ? (feed.default_media.includes('video_dashinit') ? videoAttrs : '') : ''" :poster="feed.thumbnail_url">
                        <source :src="feed.media_url ? feed.media_url : feed.default_media" type="video/mp4">
                    </video>
                    <div class="wpsr-ig-no-video" v-if="feed && (feed.media_name === 'oembed' || feed.media_name === 'VIDEO') && !feed.media_url.includes('.mp4')">
                      <a :href="feed.permalink" target="_blank">
                        <img class="wpsr-ig-thumbnail" :src="feed.media_url" alt="">
                      </a>
                      <div class="wpsr-ig-notice">
                        <strong>{{ $t('This message is only visible to admins.') }}</strong> 
                        {{ $t(feed.permissions_error
                          ? 'This video cannot be embedded. It may be private or embedding has been disabled by the content owner.'
                          : 'This video is unavailable. It may contain copyrighted content and can only be viewed on Instagram.') }}
                      </div>
                    </div>
                </div>
                <div class="wpsr-feed-popup-box-content" v-if="settings.popup_settings.display_sidebar === 'true'">
                    <div class="wpsr-feed-popup-user-info" v-if="settings.source_settings.feed_type !== 'hashtag_feed' && (settings.popup_settings.display_profile_photo === 'true' || settings.popup_settings.display_username === 'true')" >
                        <a v-if="settings.popup_settings.display_profile_photo === 'true'" class="wpsr-feed-popup-user-photo" :href="'https://www.instagram.com/'+feed.username" target="_blank" :title="feed.username">
                            <img v-if="settings.header_settings.custom_profile_photo" :src="settings.header_settings.custom_profile_photo" />
                            <img v-else-if="feed.user_avatar  && feed.user_avatar.length" :src="feed.user_avatar">
                            <img v-else :src="assets_url + '/images/template/review-template/placeholder-image.png'" />
                        </a>
                        <div class="wpsr-feed-popup-user-name" v-if="feed.username && settings.popup_settings.display_username === 'true'">
                            <a :href="'https://www.instagram.com/'+feed.username" target="_blank" :title="feed.username">{{feed.username}}</a>
                        </div>
                    </div>
                    <div class="wpsr-feed-popup-user-info" v-if="settings.source_settings.feed_type === 'hashtag_feed'">
                      <p class="wpsr-feed-popup-user-name" v-html="formatText(settings.source_settings.hash_tags)"></p>
                    </div>
                    <div class="wpsr-feed-popup-comments-wrapper">
                      <div class="wpsr-feed-popup-comments-wrapper-inner">
                          <div class="wpsr-feed-popup-comment" v-if="feed.caption">
                              <div class="wpsr-feed-popup-comment-inner">
                                  <div class="wpsr-feed-popup-comment-text" v-if="settings.popup_settings.display_caption === 'true'">
                                      <a v-if="feed.username" :href="'https://www.instagram.com/'+feed.username" target="_blank" :title="feed.username">{{feed.username}}</a>
                                      <p v-html="formatText(feed.caption)"></p>
                                  </div>
                                  <div class="wpsr-feed-popup-comment-meta" v-if="settings.popup_settings.display_date === 'true' && feed.timestamp">
                                      <time :datetime="feed.timestamp" :title="feed.timestamp | wpsrDateFormat('MMM DD YY','time_from_now')">{{ feed.timestamp | wpsrDateFormat('MMM DD YY','time_from_now') }}</time>
                                  </div>
                              </div>
                          </div>
                          <div class="wpsr-feed-popup-comment" v-for="(comment, index) in feed.comments" :key="index" v-if="feed.comments">
                            <div class="wpsr-feed-popup-comment-inner">
                                <div class="wpsr-feed-popup-comment-text">
                                    <a :href="'https://www.instagram.com/'+comment.username" target="_blank" :title="comment.username">{{comment.username}}</a>
                                    <p v-html="formatText(comment.text)"></p>
                                </div>
                                <div class="wpsr-feed-popup-comment-meta" v-if="settings.popup_settings.display_date === 'true' && comment.timestamp">
                                    <time :datetime="comment.timestamp" :title="comment.timestamp | wpsrDateFormat('MMM DD YY','time_from_now')">{{ comment.timestamp | wpsrDateFormat('MMM DD YY','time_from_now') }}</time>
                                </div>
                            </div>
                        </div>
                      </div>
                    </div>
                    <div class="wpsr-feed-popup-statistics-date" v-if="settings.popup_settings.display_date === 'true' && feed.timestamp">
                        <span v-if="typeof feed.like_count !== 'undefined'">{{ numberWithCommas(feed.like_count) }} {{ ' ' }} {{ $t('Likes') }}</span>
                        <a :href="feed.permalink" target="_blank">
                            <time :datetime="feed.timestamp" :title="feed.timestamp | wpsrDateFormat('MMM DD YY','time_from_now')">{{ ' ' }} {{ feed.timestamp | wpsrDateFormat('MMM DD YY','time_from_now') }}
                            </time>
                        </a>
                    </div>

                    <div class="wpsr-feed-popup-view-post-cta">
                        <a v-if="feed.shoppable_options && feed.shoppable_options.show_shoppable"
                           class="wpsr-popup-shoppable-btn"
                           :href="feed.shoppable_options.url_settings.url"
                           :target="feed.shoppable_options.url_settings.open_in_new_tab ? '_blank' : ''"
                        >
                          {{ feed.shoppable_options.url_settings.text}}
                        </a>

                        <a :href="feed.permalink" target="_self" v-else-if="settings.popup_settings.display_cta_btn === 'true' && !(feed.shoppable_options && feed.shoppable_options.show_shoppable)">
                            <span class="icon-instagram"></span>
                            <span>{{ $t('View on Instagram') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import {igMixin} from "../../../../../mixins/igMixin";

export default {
    name: 'instagram_popup_box',
    props: ['feed', 'user_avatar', 'settings', 'connected_ids', 'account_to_show', 'currentIndex', 'feedLength'],
    mixins: [igMixin],
    data() {
      return {
        videoAttrs: {
          controls: 'true',
          autoplay: 'true',
        }
      }
    },
    watch: {
      feed: {
        handler(val) {
          if(val.children) {
            let that = this;
            setTimeout(function(){
              that.activeCarousel();
            }, 500);
          }
        },
        deep: true
      }
    },
    methods: {
        activeCarousel() {
            new Swiper('.wpsr-feed-popup-carousel-wrapper', {
              slidesPerView: 1,
              slidesPerGroup: 1,
              pagination: {
                el: '.wpsr-swiper-pagination',
                clickable: true
              },
              navigation: {
                nextEl: '.wpsr-swiper-next',
                prevEl: '.wpsr-swiper-prev',
              }
            });
        },
        getNextFeed() {
            this.$emit('on-next');
        },
        getPreviousFeed() {
            this.$emit('on-previous');
        },
        closePopUpBox(e) {
          let closeBtn = jQuery(e.target).closest('.wpsr-feed-popup-close-btn');
          let wrapperInner = jQuery(e.target).has('.wpsr-feed-popup-box-wraper-inner');

          jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper').removeClass('wpsr-feed-popup-scrollbar');
          let commentBoxHeight = jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper-inner').innerHeight();

          if( commentBoxHeight && commentBoxHeight > 450 ){
            jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper').addClass('wpsr-feed-popup-scrollbar');
          }

          if ( (closeBtn && closeBtn.length ) || ( wrapperInner && wrapperInner.length ) || (wrapperInner && wrapperInner.length && wrapperInner.find('.wpsr-feed-popup-box-content').children().length ) ) {
            jQuery('.wpsr-feed-popup-overlay').removeClass('wpsr-feed-popup-open');
          }

          let video = jQuery("#wpsr-feed-popup-video");
          if(video.length) {
            if ((closeBtn && closeBtn.length) || (wrapperInner && wrapperInner.length) || (wrapperInner && wrapperInner.length)) {
              video[0].pause();
            }
          }
        }
    },
    mounted() {
      document.addEventListener('click', this.closePopUpBox);
    }
}
</script>