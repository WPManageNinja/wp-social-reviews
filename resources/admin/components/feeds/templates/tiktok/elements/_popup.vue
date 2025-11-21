<template>
  <div class="wpsr-feed-popup-overlay wpsr-tiktok-feed-popup">
    <div class="wpsr-feed-popup-box-wraper">
      <div v-if="currentIndex !== 0" @click="getPreviousFeed" class="wpsr-prev-btn"><span class="icon-angle-left"></span></div>
      <div v-if="currentIndex !== (feedLength - 1)" @click="getNextFeed" class="wpsr-next-btn"><span class="icon-angle-right"></span></div>

      <div class="wpsr-feed-popup-close-btn"></div>

      <div class="wpsr-feed-popup-box-wraper-inner"
           :class="[settings.popup_settings.display_sidebar === 'false' ? 'wpsr-feed-popup-box-no-sidebar' : '', feed_type ? 'wpsr-feed-'+feed_type : '']"
      >

        <div class="wpsr-feed-popup-media" v-if="settings.popup_settings.display_video === 'true'" >
          <div v-if="image_settings.has_gdpr === 'true' && image_settings.optimized_images === 'true' && feed.media && feed.media.url">
            <a class="wpsr-feed-popup-preview-image-wrapper" :href="feed.media.url" target="_blank" rel="nofollow">
              <img :src="feed.media_url ? feed.media_url : feed.default_media" alt="TikTok Preview Image" />
              <div class="wpsr-tiktok-feed-video-play">
                <div class="wpsr-tiktok-feed-video-play-icon"></div>
              </div>
            </a>
          </div>
            <div v-else-if="feed.media && feed.media.url">
              <iframe style="visibility: unset; margin: 0px auto;" id="wpsr-feed-popup-video-iframe" :src="'https://www.tiktok.com/embed/v2/'+ feed.id" allowtransparency="true" allowfullscreen="true"  scrolling="no"  :allow="allowValue" >
              </iframe>
            </div>
        </div>

        <div class="wpsr-feed-popup-box-content" v-if="settings.popup_settings.display_sidebar === 'true'">
          <div class="wpsr-feed-popup-user-info" v-if="account">
            <a v-if="settings.popup_settings.display_profile_photo === 'true'" class="wpsr-feed-popup-user-photo" :href="account.profile_deep_link" target="_blank" :title="account.display_name">
              <img :src="account.avatar_url" :alt="account.display_name"/>
            </a>
            <div class="wpsr-feed-popup-group">
              <div class="wpsr-feed-popup-user-name" v-if="settings.popup_settings.display_username === 'true'">
                <a :href="account.profile_deep_link" target="_blank" :title="account.display_name">{{ account.display_name }}</a>
              </div>
              <div class="wpsr-feed-popup-date" v-if="settings.popup_settings.display_date === 'true' && feed.created_at">
                <time :datetime="feed.created_at" :title="wpsrDateFormat(feed.created_at * 1000, 'MMMM DD YYYY')">{{ wpsrDateFormat(feed.created_at * 1000, 'MMMM DD YYYY') }}</time>
              </div>
            </div>
          </div>
          <div class="wpsr-feed-popup-comments-wrapper">
            <div class="wpsr-feed-popup-comments-wrapper-inner">
              <div class="wpsr-feed-popup-comment" v-if="feed.text">
                <div class="wpsr-feed-popup-comment-inner">
                  <div class="wpsr-feed-popup-comment-text" v-if="settings.popup_settings.display_caption === 'true'">
                    <p v-if="feed.text" v-html="tiktokTextValidate(feed.text)"></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wpsr-feed-popup-view-post-cta" v-if="settings.popup_settings.display_cta_btn === 'true' && feed.media && feed.media.url">
            <a :href="'https://www.tiktok.com/@' + account.open_id + '/video/' + feed.id" target="_self">
              <span class="wpsr-icon-tiktok-black"></span>
              <span> {{$t('View on TikTok')}} </span>
            </a>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'tiktok_feed_popup_box',
  props: ['feed', 'settings', 'account', 'currentIndex', 'feedLength', 'feed_type', 'image_settings'],
  methods: {
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

      let hasIframe = jQuery("#wpsr-feed-popup-video-iframe");
      if ( hasIframe.length !== 0 && ( (closeBtn && closeBtn.length ) || ( wrapperInner && wrapperInner.length) || (wrapperInner && wrapperInner.length && wrapperInner.find('.wpsr-feed-popup-box-content').children().length) ) ) {
        jQuery('.wpsr-feed-popup-overlay').removeClass('wpsr-feed-popup-open');
          hasIframe.attr("src", "");
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
    formatDate(date) {
        const unixTimestamp = parseInt(date);
        const dateObject = new Date(unixTimestamp * 1000);
        const dateString = dateObject.toISOString();
        return dateString;
    },
    tiktokTextValidate(feed) {
      let hashTagUrl = 'https://www.tiktok.com/tag/';
      feed = this.escapeHtml(feed);
      feed = this.generateURLsFromText(feed);
      feed = this.generateURLsFromHashTag(feed, hashTagUrl);
      return this.nl2br(feed);
    },
  },
  computed:{
    allowValue() {
      const allow = ['clipboard-write', 'encrypted-media', 'picture-in-picture'];
      return allow.join('; ');
    },
  },
  mounted() {
    document.addEventListener('click', this.closePopUpBox);
  }
}
</script>