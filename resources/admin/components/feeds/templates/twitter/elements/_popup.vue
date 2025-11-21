<template>
    <div class="wpsr-feed-popup-overlay" v-if="feed && settings.advance_settings.tweet_action_target === 'popup'">
        <div class="wpsr-feed-popup-box-wraper" v-if="feed">
            <div class="wpsr-feed-popup-box-wraper-inner">

              <div v-if="settings.popup_settings.display_next_prev_arrows === 'true'">
                <div @click="getPreviousFeed" class="wpsr-prev-btn"><span class="icon-angle-left"></span></div>
                <div @click="getNextFeed" class="wpsr-next-btn"><span class="icon-angle-right"></span></div>
              </div>

              <div class="wpsr-feed-popup-close-btn"></div>

                <div class="wpsr-feed-popup-media wpsr-twitter-feed-popup-media">
                  <div class="wpsr-feed-popup-carousel-wrapper swiper-container" v-if="feed.media && feed.media.length > 1">
                    <div class="swiper-wrapper">
                      <div class="swiper-slide" v-for="(media, index) in feed.media" :key="index">
                        <img v-if="media.type === 'photo'" :src="media.url" alt="">
                        <video id="wpsr-feed-popup-video" v-if="media.type === 'video'" playsinline="" controls autoplay=true preload="auto" :src="feed.media_url">
                          <source :src="media.url" type="video/mp4">
                        </video>
                      </div>
                    </div>
                    <div class="wpsr-swiper-prev-next wpsr-swiper-next icon-arrow-circle-o-right"></div>
                    <div class="wpsr-swiper-prev-next wpsr-swiper-prev icon-arrow-circle-o-left"></div>
                    <div class="wpsr-swiper-pagination swiper-pagination"></div>
                  </div>

                  <a :href="feed.permalink" v-if="feed.media && feed.media.length === 1 && feed.media[0].type === 'photo'" target="_blank">
                    <img :src="feed.media[0].url" alt="">
                  </a>

                  <video id="wpsr-feed-popup-video" v-if="feed.media && feed.media.length === 1 && (feed.media[0].type === 'video' || feed.media[0].type === 'animated_gif')" playsinline="" controls autoplay=true preload="none" :poster="feed.media[0].preview_image_url">
                    <source :src="getVideoLink(feed.media[0])" type="video/mp4">
                  </video>
                </div>

                <div class="wpsr-feed-popup-box-content" v-if="settings.popup_settings.display_sidebar === 'true'">
                    <div class="wpsr-feed-popup-user-info" v-if="feed.user && feed.user.username && ( settings.popup_settings.user_name === 'true' || settings.popup_settings.author_name === 'true' || settings.popup_settings.avatar_image === 'true' )">
                        <a class="wpsr-feed-popup-user-photo" :href="'https://www.twitter.com/'+feed.user.username" target="_blank" :title="feed.user.username" v-if="settings.popup_settings.avatar_image === 'true'">
                            <img :src="feed.user.profile_image_url" />
                        </a>
                        <div class="wpsr-feed-popup-user-name" v-if="feed.user.username && ( settings.popup_settings.user_name === 'true' || settings.popup_settings.author_name === 'true' )">
                            <p v-if="feed.user.name && settings.popup_settings.author_name === 'true'">{{ feed.user.name }}</p>
                            <a v-if="settings.popup_settings.user_name === 'true'" :href="'https://www.twitter.com/' + feed.user.username" target="_blank" :title="feed.user.username">@{{feed.user.username}}</a>
                        </div>
                    </div>
                    <div class="wpsr-feed-popup-comments-wrapper" v-if="settings.popup_settings.tweet_text === 'true'">
                        <div class="wpsr-feed-popup-comment" v-if="feed.user">
                            <div class="wpsr-feed-popup-comment-inner">
                                <div class="wpsr-feed-popup-comment-text">
                                    <tweet :feed="feed"></tweet>
                                </div>
                                <div class="wpsr-feed-popup-comment-meta" v-if="settings.popup_settings.display_date === 'true' && feed.created_at">
                                    <time :datetime="feed.created_at" :title="wpsrDateFormat(feed.created_at)">{{ wpsrDateFormat(feed.created_at) }}</time>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wpsr-feed-popup-statistics-date" v-if="settings.popup_settings.show_reply_action === 'true' || settings.popup_settings.show_retweet_action === 'true' || settings.popup_settings.show_like_action === 'true'">
                        <div class="wpsr-feed-popup-tweet-actions" v-if="feed.user">
                            <a target="_blank" :href="'https://twitter.com/intent/tweet?in_reply_to='+feed.id+'&related='+feed.user.username" class="wpsr-tweet-reply" v-if="settings.popup_settings.show_reply_action === 'true'">
                                <svg viewBox="0 0 24 24"><g><path d="M14.046 2.242l-4.148-.01h-.002c-4.374 0-7.8 3.427-7.8 7.802 0 4.098 3.186 7.206 7.465 7.37v3.828c0 .108.044.286.12.403.142.225.384.347.632.347.138 0 .277-.038.402-.118.264-.168 6.473-4.14 8.088-5.506 1.902-1.61 3.04-3.97 3.043-6.312v-.017c-.006-4.367-3.43-7.787-7.8-7.788zm3.787 12.972c-1.134.96-4.862 3.405-6.772 4.643V16.67c0-.414-.335-.75-.75-.75h-.396c-3.66 0-6.318-2.476-6.318-5.886 0-3.534 2.768-6.302 6.3-6.302l4.147.01h.002c3.532 0 6.3 2.766 6.302 6.296-.003 1.91-.942 3.844-2.514 5.176z"></path></g></svg>
                            </a>
                            <a target="_blank" :href="'https://twitter.com/intent/retweet?tweet_id='+feed.id+'&related='+feed.user.username" class="wpsr-tweet-retweet" v-if="settings.popup_settings.show_retweet_action === 'true'">
                                <svg viewBox="0 0 24 24"><g><path d="M23.77 15.67c-.292-.293-.767-.293-1.06 0l-2.22 2.22V7.65c0-2.068-1.683-3.75-3.75-3.75h-5.85c-.414 0-.75.336-.75.75s.336.75.75.75h5.85c1.24 0 2.25 1.01 2.25 2.25v10.24l-2.22-2.22c-.293-.293-.768-.293-1.06 0s-.294.768 0 1.06l3.5 3.5c.145.147.337.22.53.22s.383-.072.53-.22l3.5-3.5c.294-.292.294-.767 0-1.06zm-10.66 3.28H7.26c-1.24 0-2.25-1.01-2.25-2.25V6.46l2.22 2.22c.148.147.34.22.532.22s.384-.073.53-.22c.293-.293.293-.768 0-1.06l-3.5-3.5c-.293-.294-.768-.294-1.06 0l-3.5 3.5c-.294.292-.294.767 0 1.06s.767.293 1.06 0l2.22-2.22V16.7c0 2.068 1.683 3.75 3.75 3.75h5.85c.414 0 .75-.336.75-.75s-.337-.75-.75-.75z"></path></g></svg>
                                <span v-if="feed.statistics">{{shortNumberFormat(feed.statistics.retweet_count)}}</span>
                            </a>
                            <a target="_blank" :href="'https://twitter.com/intent/like?tweet_id='+feed.id+'&related='+feed.user.username" class="wpsr-tweet-like" v-if="settings.popup_settings.show_like_action === 'true'">
                                <svg viewBox="0 0 24 24"><g><path d="M12 21.638h-.014C9.403 21.59 1.95 14.856 1.95 8.478c0-3.064 2.525-5.754 5.403-5.754 2.29 0 3.83 1.58 4.646 2.73.814-1.148 2.354-2.73 4.645-2.73 2.88 0 5.404 2.69 5.404 5.755 0 6.376-7.454 13.11-10.037 13.157H12zM7.354 4.225c-2.08 0-3.903 1.988-3.903 4.255 0 5.74 7.034 11.596 8.55 11.658 1.518-.062 8.55-5.917 8.55-11.658 0-2.267-1.823-4.255-3.903-4.255-2.528 0-3.94 2.936-3.952 2.965-.23.562-1.156.562-1.387 0-.014-.03-1.425-2.965-3.954-2.965z"></path></g></svg>
                                <span v-if="feed.statistics">{{shortNumberFormat(feed.statistics.like_count)}}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
  import tweet from './_tweet';
  import {getVideoUrl, mediaClass} from '../helper';
  export default {
    name: 'wpsr_instagram_popup_box',
    props: ['feed', 'settings','feedLength','currentIndex'],
    components: {
      tweet,
    },

    data() {
        return {
          videoElement: null,
          paused: null
        }
    },

    watch: {
      feed: {
        handler(val) {
          if(val && val.media && val.media.length > 1) {
            let that = this;
            setTimeout(function() {
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
      getVideoLink(media) {
        this.className = mediaClass(media);
        return getVideoUrl(media);
      },

      getNextFeed() {
          this.$emit('on-next');
      },

      getPreviousFeed() {
          this.$emit('on-previous');
      },

      closePopUpBox(e) {
        if( this.videoElement ) {
          this.pause();
        }
        let closeBtn = jQuery(e.target).closest('.wpsr-feed-popup-close-btn');
        let wrapperInner = jQuery(e.target).has('.wpsr-feed-popup-box-wraper-inner');

        jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper').removeClass('wpsr-feed-popup-scrollbar');
        let commentBoxHeight = jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comment').innerHeight();

        if( commentBoxHeight && commentBoxHeight > 403 ){
          jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper').addClass('wpsr-feed-popup-scrollbar');
        }

        if ( (closeBtn && closeBtn.length ) || ( wrapperInner && wrapperInner.length ) || (wrapperInner && wrapperInner.length && wrapperInner.find('.wpsr-feed-popup-box-content').children().length ) ) {
          jQuery('.wpsr-feed-popup-overlay').removeClass('wpsr-feed-popup-open');
        }
      },

      play() {
        this.videoElement.play();
      },
      pause() {
        this.videoElement.pause();
      },
      updatePaused(event) {
        this.videoElement = event.target;
        this.paused = event.target.paused;
      }
    },
    computed: {
      playing() { return !this.paused; }
    },
    mounted() {
      document.addEventListener('click', this.closePopUpBox);
    }
  }
</script>