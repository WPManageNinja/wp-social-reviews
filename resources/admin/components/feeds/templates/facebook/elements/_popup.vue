<template>
  <div class="wpsr-feed-popup-overlay wpsr-fb-feed-popup">
    <div class="wpsr-feed-popup-box-wraper">
      <div class="wpsr-feed-popup-box-wraper-inner"
           :class="[settings.popup_settings.display_sidebar === 'false' ? 'wpsr-feed-popup-box-no-sidebar' : '', feed_type ? 'wpsr-feed-'+feed_type : '']"
      >
        <div v-if="settings.popup_settings.display_next_prev_arrows === 'true'">
          <div v-if="!albumFeedLength && currentIndex !== 0" @click="getPreviousFeed" class="wpsr-prev-btn"><span class="icon-angle-left"></span></div>
          <div v-if="albumFeedLength && currentIndex !== 0" @click="getPreviousFeed" class="wpsr-prev-btn"><span class="icon-angle-left"></span></div>

          <div v-if="!albumFeedLength && currentIndex !== (feedLength - 1)" @click="getNextFeed" class="wpsr-next-btn"><span class="icon-angle-right"></span></div>
          <div v-if="albumFeedLength && currentIndex !== (albumFeedLength - 1)" @click="getNextFeed" class="wpsr-next-btn"><span class="icon-angle-right"></span></div>
        </div>

        <div class="wpsr-feed-popup-close-btn"></div>
        
        <!-- Video popup for album attachments -->
        <div v-if="isVideoPopup" class="wpsr-optimize-image-wrapper wpsr-video-popup-wrapper">
          <VideoPlayer
            :videoSrc="videoPopupUrl"
            :videoId="'wpsr-feed-popup-video'"
            :videoKey="videoPopupUrl"
            :controls="true"
            :preload="'metadata'"
            :containerClass="'wpsr-feed-popup-video-wrapper'"
            :videoClass="'wpsr-feed-popup-video-player'"
            :autoPlay="false"
            :muted="false"
            @error="handleVideoError"
            @canplay="handleVideoCanPlay"
          />
        </div>
        
        <!-- Original video feed handling -->
        <div :class="'wpsr-iframe-'+currentIndex" class="wpsr-optimize-image-wrapper" v-if="(feed_type === 'video_feed' || feed_type === 'video_playlist_feed') && !isVideoPopup">
            <!-- Show video player when optimized images are off and it's a video -->
            <VideoPlayer
              v-if="image_settings.optimized_images !== 'true' && feed.media_type"
              :videoSrc="getVideoFeedUrl()"
              :videoId="'wpsr-feed-video-' + currentIndex"
              :videoKey="getVideoFeedUrl()"
              :controls="true"
              :preload="'metadata'"
              :containerClass="'wpsr-feed-popup-video-wrapper'"
              :videoClass="'wpsr-feed-popup-video-player'"
              :autoPlay="false"
              :muted="false"
              @error="handleVideoError"
              @canplay="handleVideoCanPlay"
            />
            <!-- Show clickable thumbnail when optimized images are on -->
            <a v-else-if="feed.media_type" :href="`https://www.facebook.com/${feed.permalink_url}?autoplay=1`" target="_blank">
              <img v-if="feed.media_url" :src="feed.media_url" alt="">
              <div class="wpsr-fb-feed-video-play">
                <div class="wpsr-fb-feed-video-play-icon"></div>
              </div>
            </a>
            <VideoPlayer
              v-else-if="(feed_type === 'video_feed' || feed_type === 'video_playlist_feed') && !feed.media_url && feed.format && !feed.media_url"
              :videoSrc="feed.media_url ? feed.media_url : feed.source"
              :videoId="'wpsr-feed-popup-video'"
              :videoKey="feed.source"
              :controls="true"
              :preload="'metadata'"
              :containerClass="'wpsr-feed-popup-video-wrapper'"
              :videoClass="'wpsr-feed-popup-video-player'"
              :autoPlay="false"
              :muted="false"
              @error="handleVideoError"
              @canplay="handleVideoCanPlay"
            />
        </div>
        <div class="wpsr-feed-popup-media" v-if="feed_type !== 'video_feed' && (feed.attachments || feed.images || feed.cover || feed.photos)">
          <div class="wpsr-feed-popup-photo-media" v-if="feed.attachments && feed.attachments.data && ((feed.status_type === 'added_video' && feed.attachments.data[0].url) || feed.attachments.data[0].type === 'video_inline')">
            <!-- Show video player when optimized images are off and it's a video -->
            <VideoPlayer
              v-if="image_settings.optimized_images !== 'true' && (feed.status_type === 'added_video' || feed.attachments.data[0].type === 'video_inline') && getTimelineVideoUrl()"
              :videoSrc="getTimelineVideoUrl()"
              :videoId="'wpsr-timeline-video-' + currentIndex"
              :videoKey="getTimelineVideoUrl()"
              :controls="true"
              :preload="'metadata'"
              :containerClass="'wpsr-feed-popup-video-wrapper'"
              :videoClass="'wpsr-feed-popup-video-player'"
              :autoPlay="true"
              :muted="false"
              @error="handleVideoError"
              @canplay="handleVideoCanPlay"
            />
            <!-- Show iframe when no direct video URL available -->
            <div v-else-if="image_settings.optimized_images !== 'true' && (feed.status_type === 'added_video' || feed.attachments.data[0].type === 'video_inline') && !getTimelineVideoUrl()" class="wpsr-iframe-video-container" style="position: relative; width: 100%; aspect-ratio: 16/9; background:#000; overflow:hidden;">
              <iframe 
                :id="'wpsr-timeline-iframe-' + currentIndex" 
                :src="`https://www.facebook.com/plugins/video.php?href=${feed.attachments.data[0].url}?autoplay=1`" 
                allowtransparency="true" 
                allowfullscreen="true" 
                frameborder="0" 
                scrolling="no" 
                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture" 
                style="position:absolute; top:0; left:0; width: 100%; height: 100%;">
              </iframe>
            </div>
            <!-- Show clickable thumbnail when optimized images are on -->
            <a v-else-if="feed.media_type === 'IMAGE' || feed.media_type === null" :href="`${feed.permalink_url}?autoplay=1`" target="_blank">
              <img v-if="feed.media_url || feed.full_picture" :src="feed.media_url ? feed.media_url : feed.full_picture" alt="">
              <div class="wpsr-fb-feed-video-play">
                <div class="wpsr-fb-feed-video-play-icon"></div>
              </div>
            </a>
          </div>
          <div class="wpsr-feed-popup-photo-media" v-else>
            <!-- photo feed start -->
              <img v-if="image_settings.optimized_images !== 'true' && feed_type === 'photo_feed' && (feed.images && feed.images[2] ? feed.images[2].source : feed.images[1].source)" :src="feed.images && feed.images[2] ? feed.images[2].source : feed.images[1].source" :alt="feed.name"/>
              <img v-if="image_settings.optimized_images === 'true' && feed_type === 'photo_feed' && feed.media_url" :src="feed.media_url" :alt="feed.name"/>
            <!-- photo feed end -->

            <!-- timeline feed start -->
              <img v-if="image_settings.optimized_images !== 'true' && feed_type === 'timeline_feed' && feed.status_type !== 'added_video' && feed.attachments && feed.attachments.data && feed.attachments.data[0].type !== 'album' && feed.attachments.data[0].media.image.src" :src="feed.attachments && feed.attachments.data && feed.attachments.data[0].media.image.src ? feed.attachments.data[0].media.image.src : feed.full_picture" :alt="feed.message"/>
              <img v-if="image_settings.optimized_images === 'true' && feed_type === 'timeline_feed' && feed.media_url" :src="feed.media_url" :alt="feed.message"/>
            <!-- timeline feed end -->

            <!-- event feed start -->
              <img v-if="image_settings.optimized_images === 'true' && feed_type === 'event_feed' && (feed.media_url || feed.cover && feed.cover.source )" :src="feed.media_url ? feed.media_url : ''" :alt="feed.name"/>
              <img v-if="image_settings.optimized_images === 'false' && feed_type === 'event_feed' && (feed.media_url || feed.cover && feed.cover.source )" :src="(feed.cover && feed.cover.source) ? feed.cover.source : ''" :alt="feed.name"/>
            <!-- event feed end -->

            <!-- album feed start -->
              <img v-if="feed_type === 'album_feed' && (feed.media_url || feed.source)" :src="feed.media_url ? feed.media_url : feed.source" :alt="feed.name"/>
            <!-- album feed end -->

            <!-- single album feed start -->
              <img v-if="image_settings.optimized_images !== 'true' && feed_type === 'single_album_feed' && (feed.images.length > 0 && feed.images[0].source)" :src="feed.images.length > 0 ? feed.images[0].source : ''" :alt="feed.name"/>
              <img v-if="image_settings.optimized_images === 'true' && feed_type === 'single_album_feed' && (feed.images.length > 0 && feed.images[0].source)" :src="feed.media_url ? feed.media_url : ''" :alt="feed.name"/>
            <!-- single album feed end -->

            <div class="wpsr-feed-popup-carousel-wrapper swiper-container" v-if="image_settings.optimized_images !== 'true' && feed.attachments && feed.attachments.data && feed.attachments.data[0].type === 'album' && feed.attachments.data[0].subattachments">
              <div class="swiper-wrapper">
                <div class="swiper-slide" v-for="(item, index) in feed.attachments.data[0].subattachments.data" :key="index">
                  <!-- Handle Images -->
                  <div v-if="item.type === 'photo' && item.media.image && item.media.image.src !== ''" class="wpsr-feed-popup-image-container">
                    <img :src="item.media.image.src" :alt="feed.message" class="wpsr-feed-popup-carousel-image">
                  </div>
                  
                  <!-- Handle Videos -->
                  <div v-if="item.type === 'video'" class="wpsr-feed-popup-video-container">
                    <VideoPlayer
                      :videoSrc="getCarouselVideoUrl(item)"
                      :videoId="'wpsr-carousel-video-' + index"
                      :videoKey="getCarouselVideoUrl(item)"
                      :controls="true"
                      :preload="'metadata'"
                      :containerClass="'wpsr-feed-popup-carousel-video-container'"
                      :videoClass="'wpsr-feed-popup-carousel-video'"
                      :autoPlay="false"
                      :muted="false"
                      @error="handleVideoError"
                      @canplay="handleVideoCanPlay"
                    />
                  </div>
                </div>
              </div>
              <div class="wpsr-swiper-prev-next wpsr-swiper-next icon-arrow-circle-o-right"></div>
              <div class="wpsr-swiper-prev-next wpsr-swiper-prev icon-arrow-circle-o-left"></div>
              <div class="wpsr-swiper-pagination swiper-pagination"></div>
            </div>
          </div>
        </div>

        <div class="wpsr-feed-popup-box-content" v-if="settings.popup_settings.display_sidebar === 'true'">
          <div class="wpsr-feed-popup-user-info" v-if="account">
            <a v-if="settings.popup_settings.display_profile_photo === 'true'" class="wpsr-feed-popup-user-photo" :href="'https://www.facebook.com/'+account.id" target="_blank" :title="account.name">
              <img :src="feed.user_avatar ? feed.user_avatar : account.picture.data.url" />
            </a>
            <div class="wpsr-feed-popup-group">
              <div class="wpsr-feed-popup-user-name" v-if="settings.popup_settings.display_username === 'true'">
                <a :href="'https://www.facebook.com/'+account.id" target="_blank" :title="account.name">{{ account.name }}</a>
              </div>
              <div class="wpsr-feed-popup-date" v-if="settings.popup_settings.display_date === 'true' && feed.created_time">
                <time>{{ feed.created_time }}</time>
              </div>
            </div>
          </div>
          <Statistics
              v-if="has_pro && settings.popup_settings.display_likes_count === 'true'"
              :feed="feed"
              :feed_settings="settings.post_settings"
          />
          <div class="wpsr-feed-popup-comments-wrapper" v-if="feed_type !== 'video_feed'">
            <div class="wpsr-feed-popup-comments-wrapper-inner">
              <div class="wpsr-feed-popup-comment" v-if="feed.name || feed.message">
                <div class="wpsr-feed-popup-comment-inner">
                  <div class="wpsr-feed-popup-comment-text" v-if="settings.popup_settings.display_caption === 'true' && feed_type !== 'event_feed' ">
                    <p v-if="feed.name" v-html="facebookTextValidate(feed.name)"></p>
                    <p v-if="feed.message" v-html="facebookTextValidate(feed.message)"></p>
                  </div>
                  <EventPopup
                      v-if="feed_type === 'event_feed'"
                      :facebookTextValidate="facebookTextValidate"
                      :feed="feed"
                      :settings="settings"
                      :feed_type="feed_type"
                  />
                </div>
              </div>
              <div v-if="settings.popup_settings.display_comments === 'true' && feed.comments">
                <div class="wpsr-feed-popup-comment" v-for="(comment, index) in feed.comments['data']" :key="index">
                  <div class="wpsr-feed-popup-comment-inner wpsr-feed-popup-comment-list">
                    <div class="wpsr-feed-popup-comment-text wpsr-feed-popup-comment-item">
                      <img class="wpsr-feed-popup-comment-user-avatar" v-if="image_settings.optimized_images !== 'true' && image_settings.has_gdpr !== 'true' && comment.from.picture && settings.popup_settings.display_comments_user_picture === 'true'" :src="comment.from.picture.data.url" :alt="comment.from.name">
                      <div class="wpsr-feed-popup-comment-group">
                         <span>{{comment.from.name}}</span>
                         <p v-html="nl2br(comment.message)"></p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wpsr-feed-popup-view-post-cta" v-if="feed_type !== 'video_feed' && settings.popup_settings.display_cta_btn === 'true'">
            <a v-if="feed_type !== 'event_feed'" :href="feed.attachments && feed.attachments.data && feed.attachments.data[0].url ? feed.attachments.data[0].url : feed.link" target="_self">
              <span class="wpsr-icon icon-facebook-square"></span>
              <span>{{ $t('View on Facebook') }}</span>
            </a>
            <a v-if="feed_type === 'event_feed'"  :href="'https://www.facebook.com/events/'+feed.id" target="_blank" ref="nofollow">
              <span class="wpsr-icon icon-facebook-square"></span>
              <span>{{ $t('View on Facebook') }}</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">
import EventPopup from "./_event_popup";
import Statistics from "./_statistics";
import VideoPlayer from "./_video";
export default {
  name: 'facebook_feed_popup_box',
  components: {EventPopup, Statistics, VideoPlayer},
  props: ['feed', 'settings', 'account', 'currentIndex', 'feedLength', 'albumFeedLength', 'feed_type', 'image_settings'],
  data() {
    return {
      isVideoPopup: false,
      videoPopupUrl: null
    };
  },
      watch: {
      feed: {
        handler(feed) {
          // Reset video popup when feed changes
          this.resetVideoPopup();
          
          if(feed.attachments && feed.attachments.data && feed.attachments.data[0].subattachments) {
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
      const swiper = new Swiper('.wpsr-feed-popup-carousel-wrapper', {
        slidesPerView: 1,
        slidesPerGroup: 1,
        pagination: {
          el: '.wpsr-swiper-pagination',
          clickable: true
        },
        navigation: {
          nextEl: '.wpsr-swiper-next',
          prevEl: '.wpsr-swiper-prev',
        },
        on: {
          slideChange: function() {
            // Pause all videos when slide changes
            const allVideos = document.querySelectorAll('.wpsr-feed-popup-carousel-video');
            allVideos.forEach(video => {
              if (!video.paused) {
                video.pause();
              }
            });
            
            // Play video if current slide contains a video
            const activeSlide = this.slides[this.activeIndex];
            const video = activeSlide.querySelector('.wpsr-feed-popup-carousel-video');
            if (video) {
              // Add a small delay to ensure the slide transition is complete
              setTimeout(() => {
                video.play().catch(e => {
                });
              }, 300);
            }
          }
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

      jQuery('.wpsr-feed-popup-loader').addClass('wpsr-popup-loading');

      // Pause all videos when popup closes
      this.pauseAllVideos();

      let hasIframe = jQuery("#wpsr-feed-popup-video-iframe");
      if ( hasIframe.length !== 0 && ( (closeBtn && closeBtn.length ) || ( wrapperInner && wrapperInner.length) || (wrapperInner && wrapperInner.length && wrapperInner.find('.wpsr-feed-popup-box-content').children().length) ) ) {
        jQuery('.wpsr-feed-popup-overlay').removeClass('wpsr-feed-popup-open');
        hasIframe.attr("src", hasIframe.attr("src").replace("autoplay=1", "autoplay=0"));
      }

      jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper').removeClass('wpsr-feed-popup-scrollbar');
      let commentBoxHeight = jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper-inner').innerHeight();

      if( commentBoxHeight && commentBoxHeight > 450 ){
        jQuery('.wpsr-feed-popup-overlay').find('.wpsr-feed-popup-comments-wrapper').addClass('wpsr-feed-popup-scrollbar');
      }

      if ( (closeBtn && closeBtn.length ) || ( wrapperInner && wrapperInner.length ) || (wrapperInner && wrapperInner.length && wrapperInner.find('.wpsr-feed-popup-box-content').children().length ) ) {
        jQuery('.wpsr-feed-popup-overlay').removeClass('wpsr-feed-popup-open');
      }
    },
    facebookTextValidate(feed) {
      let hashTagUrl = 'https://facebook.com/hashtag/';
      feed = this.escapeHtml(feed);
      feed = this.generateURLsFromText(feed);
      feed = this.generateURLsFromHashTag(feed, hashTagUrl);
      return this.nl2br(feed);
    },
    
    // Handle video popup from photo component
    handleVideoPopup(videoData) {
  
      // Only show video popup if we have valid video data and URL
      if (videoData && videoData.displayMode === 'popup' && videoData.url) {
      
        this.isVideoPopup = true;
        this.videoPopupUrl = videoData.url;
        
        // Hide other media content when showing video
        this.$nextTick(() => {          
          // Add error handling for video loading
          const videoElement = this.$el.querySelector('#wpsr-feed-popup-video');
          if (videoElement) {
            videoElement.onerror = (e) => {
              this.handleVideoError();
            };
          }
        });
      } else {
        this.resetVideoPopup();
      }
    },

    handleVideoError(error) {
      this.$emit('video-error', error);
      this.resetVideoPopup();
    },

    handleVideoCanPlay() {
      this.$emit('video-canplay');
    },
    
    resetVideoPopup() {
      this.isVideoPopup = false;
      this.videoPopupUrl = null;
      
      this.$nextTick(() => {
        const videoContainer = this.$el.querySelector('.wpsr-video-popup-wrapper');
        const mediaContainer = this.$el.querySelector('.wpsr-feed-popup-media');
        
        if (videoContainer) {
          videoContainer.style.display = 'none';
        }
        if (mediaContainer) {
          mediaContainer.style.display = 'block';
        }
      });
    },
    
    // Get video URL for carousel items
    getCarouselVideoUrl(item) {
      if (!item) return null;
      
      // Priority order: media.source > target.url > url > media.video.source > media.video.hd_quality > media.video.sd_quality
      return (item.media && item.media.source) || 
             (item.target && item.target.url) || 
             item.url || 
             (item.media && item.media.video && item.media.video.source) || 
             (item.media && item.media.video && item.media.video.hd_quality) || 
             (item.media && item.media.video && item.media.video.sd_quality) || 
             null;
    },

    // Get timeline video URL
    getTimelineVideoUrl() {
      if (!this.feed || !this.feed.attachments || !this.feed.attachments.data || !this.feed.attachments.data[0]) {
        return null;
      }
      const attachment = this.feed.attachments.data[0];
      
      // Priority order: media.source > media.video.source > media.url
      // Only return direct video URLs that work with <video> tag
      const directVideoUrl = (attachment.media && attachment.media.source) || 
                            (attachment.media && attachment.media.video && attachment.media.video.source);
      
      if (directVideoUrl) {
        return directVideoUrl;
      }
      
      // If no direct video URL, return null to trigger iframe fallback
      return null;
    },

    // Get video feed URL
    getVideoFeedUrl() {
      if (!this.feed) return null;
      
      // Priority order: media_url > source > default_media
      return this.feed.media_url || 
             this.feed.source || 
             this.feed.default_media || 
             null;
    },

    // Pause all videos when popup closes
    pauseAllVideos() {
      // Pause all carousel videos
      const carouselVideos = document.querySelectorAll('.wpsr-feed-popup-carousel-video');
      carouselVideos.forEach(video => {
        if (!video.paused) {
          video.pause();
        }
      });

      // Pause main popup video
      const popupVideo = document.querySelector('#wpsr-feed-popup-video');
      if (popupVideo && !popupVideo.paused) {
        popupVideo.pause();
      }

      // Pause timeline video player
      const timelineVideos = document.querySelectorAll('.wpsr-feed-popup-video-player');
      timelineVideos.forEach(video => {
        if (!video.paused) {
          video.pause();
        }
      });

      // Pause VideoPlayer components
      const videoPlayerComponents = this.$el.querySelectorAll('.wpsr-video-container');
      videoPlayerComponents.forEach(container => {
        const video = container.querySelector('video');
        if (video && !video.paused) {
          video.pause();
        }
      });

      // Pause any iframe videos
      const iframeVideos = document.querySelectorAll('iframe[src*="video"]');
      iframeVideos.forEach(iframe => {
        if (iframe.src.includes('autoplay=1')) {
          iframe.src = iframe.src.replace('autoplay=1', 'autoplay=0');
        }
      });
    }
  },
  mounted() {
    document.addEventListener('click', this.closePopUpBox);
  },
  beforeDestroy() {
    // Pause all videos when component is destroyed
    this.pauseAllVideos();
    document.removeEventListener('click', this.closePopUpBox);
  }
}
</script>