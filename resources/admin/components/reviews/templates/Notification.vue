<template>
    <div class="wpsr-reviews-notification-card-wrapper wpsr-notification-active"
         :class="[
             wpsrNotificationPositionClass,
             template_meta && template_meta.notification_settings && template_meta.notification_settings.display_mode !== 'none' ? 'wpsr-enable-cursor' : '',
             filteredReviews.length ? 'wpsr-reviews-platform-'+filteredReviews[0].platform_name : '',
             template_meta && template_meta.notification_settings ? 'wpsr-reviews-'+template_meta.notification_settings.template : ''
         ]"
         data-index="0"
         v-if="filteredReviews.length"
    >
      <!-- Mirrors Pro notification.php: the link is the inner card so the close button can sit beside it. -->
      <a class="wpsr-reviews-notification-card"
         :href="template_meta && template_meta.notification_settings ? (template_meta.notification_settings.display_mode === 'page' ? template_meta.notification_settings.url : template_meta.notification_settings.custom_url) : '#'"
         target="_blank"
      >
        <template v-if="isSpotlight">
          <div class="wpsr-notification-head">
            <div class="wpsr-notification-image-wrapper">
              <div class="wpsr-reviewer-image" v-if="template_meta && template_meta.reviewer_image === 'true'">
                <img :src="filteredReviews[0].reviewer_img ? filteredReviews[0].reviewer_img : assets_url+'/images/template/review-template/placeholder-image.png'">
                <span class="wpsr-notification-platform-dot" aria-hidden="true">
                  <span class="wpsr-notification-platform-dot-letter">{{ platformDotLetter }}</span>
                </span>
              </div>
            </div>
            <div class="wpsr-notification-content-wrapper">
              <div class="wpsr-review-header">
                <div class="wpsr-notification-nameline">
                  <span class="reviewer-name" dir="auto">{{ filteredReviews[0].reviewer_name }}</span>
                  <span class="wpsr-verified-review wpsr-tooltip"
                        v-if="showVerifiedBadge"
                        :aria-label="verifiedTooltipText"
                        :data-tooltip="verifiedTooltipText">
                    <span class="verified-badge-star"><span class="checkmark"></span></span>
                  </span>
                  <span class="wpsr-notification-rating-word" v-if="ratingWord">{{ ratingWord }}</span>
                </div>
              </div>
              <p class="wpsr-notification-custom-text" v-if="showCustomText"
                 v-html="template_meta.notification_settings.custom_notification_text.replace('{review_rating}', `<span class='review-rating'>${parseFloat(filteredReviews[0].rating)}</span>`)">
              </p>
              <div class="wpsr-notification-meta" v-if="template_meta && template_meta.notification_settings && template_meta.notification_settings.display_date === 'true'">
                <span class="review-time">{{ formatDate(filteredReviews[0].review_time) }}</span>
              </div>
            </div>
          </div>
          <div class="wpsr-notification-quote" v-if="trimmedExcerpt">
            <i class="icon-quote-right" aria-hidden="true"></i>
            <p class="wpsr-notification-excerpt" dir="auto">{{ trimmedExcerpt }}</p>
          </div>
          <div class="wpsr-notification-body" :class="{ 'wpsr-notification-body-spread': sourceDisplay === 'logo_text' }">
            <div class="wpsr-rating-wrapper">
              <div class="wpsr-rating" v-html="ratingIcon(filteredReviews[0].rating)"></div>
            </div>
            <div class="wpsr-notification-source" v-if="sourceDisplay === 'logo_text'">
              <span>{{ $t('on') }}</span>
              <img :src="avatarDotIcon" alt="">
              <span class="wpsr-notification-source-name">{{ sourceLabel }}</span>
            </div>
            <NotificationSourceLogo v-else :review="filteredReviews[0]" :template-meta="template_meta" :business-info="businessInfo" />
          </div>
        </template>
        <template v-else-if="isPulse">
          <div class="wpsr-notification-pulse-score">
            <div class="wpsr-notification-pulse-value">{{ pulseRating }}</div>
            <div class="wpsr-rating-wrapper">
              <div class="wpsr-rating" v-html="ratingIcon(pulseRating)"></div>
            </div>
          </div>
          <div class="wpsr-notification-pulse-body">
            <div class="wpsr-notification-pulse-line1" dir="auto">
              <b>{{ pulseCount.toLocaleString() }} {{ $t('reviews') }}</b>
              {{ $t('on') }}
              <span v-if="pulsePlatformIcons.length" class="wpsr-notification-pulse-platform-icons">
                <img v-for="icon in pulsePlatformIcons" :key="icon.name" :src="icon.icon" :alt="icon.name">
                <span class="wpsr-visually-hidden">{{ pulsePlatformLabel }}</span>
              </span>
              <template v-else>{{ pulsePlatformLabel }}</template>
            </div>
            <div class="wpsr-notification-pulse-line2" v-if="pulseAvatars.length">
              <span class="wpsr-notification-pulse-avatars" aria-hidden="true">
                <img v-for="(avatar, avatarIndex) in pulseAvatars" :key="avatarIndex" :src="avatar" alt="">
              </span>
              {{ $t('from real customers') }}
            </div>
          </div>
        </template>
        <template v-else>
          <div class="wpsr-notification-image-wrapper">
            <div class="wpsr-reviewer-image" v-if="template_meta && template_meta.reviewer_image === 'true'">
              <img :src="filteredReviews[0].reviewer_img ? filteredReviews[0].reviewer_img : assets_url+'/images/template/review-template/placeholder-image.png'">
            </div>
          </div>
          <div class="wpsr-notification-content-wrapper">
            <div class="wpsr-review-header">
              <span class="reviewer-name" dir="auto">{{ filteredReviews[0].reviewer_name + ' ' }}</span>
              <p v-if="showCustomText"
                 v-html="template_meta.notification_settings.custom_notification_text.replace('{review_rating}', `<span class='review-rating'>${parseFloat(filteredReviews[0].rating)}</span>`)">
              </p>
            </div>
            <div class="wpsr-notification-body">
              <div class="wpsr-rating-wrapper">
                <div class="wpsr-rating" v-html="ratingIcon(filteredReviews[0].rating)"></div>
              </div>
              <NotificationSourceLogo :review="filteredReviews[0]" :template-meta="template_meta" :business-info="businessInfo" />
            </div>
            <div class="wpsr-notification-footer" v-if="template_meta && template_meta.notification_settings && template_meta.notification_settings.display_date === 'true'">
              <span class="review-time">{{ formatDate(filteredReviews[0].review_time) }}</span>&nbsp;
            </div>
          </div>
        </template>
      </a>
      <div class="wpsr-notification-nav-group" role="group" :aria-label="$t('Review navigation')" v-if="isSpotlight && filteredReviews.length > 1">
        <button type="button" class="wpsr-notification-nav wpsr-notification-nav-prev" :aria-label="$t('Previous review')" @click="navigate(-1)">
          <i class="icon-angle-left" aria-hidden="true"></i>
        </button>
        <div class="wpsr-notification-dots" aria-hidden="true" v-if="filteredReviews.length <= 5">
          <span v-for="(review, dotIndex) in filteredReviews" :key="dotIndex" :class="{ active: dotIndex === index }"></span>
        </div>
        <button type="button" class="wpsr-notification-nav wpsr-notification-nav-next" :aria-label="$t('Next review')" @click="navigate(1)">
          <i class="icon-angle-right" aria-hidden="true"></i>
        </button>
      </div>
      <button type="button" class="wpsr-close" :aria-label="$t('Close notification')" v-if="template_meta && template_meta.notification_settings && template_meta.notification_settings.display_close_button === 'true'">
          <svg viewBox="0 0 16 16" style="fill: rgb(255, 255, 255);" aria-hidden="true" focusable="false">
            <path d="M3.426 2.024l.094.083L8 6.586l4.48-4.479a1 1 0 011.497 1.32l-.083.095L9.414 8l4.48 4.478a1 1 0 01-1.32 1.498l-.094-.083L8 9.413l-4.48 4.48a1 1 0 01-1.497-1.32l.083-.095L6.585 8 2.106 3.522a1 1 0 011.32-1.498z"></path>
          </svg>
        </button>
    </div>
</template>

<style lang="scss">

</style>

<script type="text/babel">
import moment from 'moment';
import {reviewsMixin} from '../../../mixins/reviewsMixin';
import NotificationSourceLogo from './elements/NotificationSourceLogo.vue';

export default {
  name: 'Notification',
  mixins: [reviewsMixin],
  components: {
    NotificationSourceLogo
  },
  props: {
    template_meta: {
      type: Object,
      default: () => ({
        notification_settings: {
          custom_notification_text: '',
          notification_position: '',
          display_mode: 'none',
          url: '',
          custom_url: '',
          display_date: 'false',
          display_close_button: 'false'
        },
        reviewer_image: 'false',
        isPlatformIcon: 'false',
        display_tp_brand: 'false'
      })
    },
    reviews: {
      type: Array,
      default: () => []
    },
    businessInfo: {
      type: Object,
      default: () => ({})
    },
  },
  data() {
    return {
      intervalHandle: null,
      count: 0,
      index: 0,
      current_platform_name: this.reviews && this.reviews.length ? this.reviews[0].platform_name : '',
      filteredReviews: [],
      // filteredRawIndices[i] is the unfiltered grid index (the DOM's data-index) for
      // filteredReviews[i] — findElement() looks elements up by that raw index.
      filteredRawIndices: []
    }
  },
  computed: {
    // The -rtl.css mirrors these rules with the editor layout, so the side is swapped to keep the chosen corner.
    wpsrNotificationPositionClass() {
      const wpsrPosition = this.template_meta && this.template_meta.notification_settings ? this.template_meta.notification_settings.notification_position : '';
      if (!wpsrPosition) {
        return '';
      }
      const wpsrSide = document.documentElement.dir === 'rtl' ? wpsrPosition.replace(/left|right/, side => side === 'left' ? 'right' : 'left') : wpsrPosition;
      return 'wpsr-' + wpsrSide;
    },
    isSpotlight() {
      return !!(this.template_meta && this.template_meta.notification_settings && this.template_meta.notification_settings.template === 'notification2');
    },
    isPulse() {
      return !!(this.template_meta && this.template_meta.notification_settings && this.template_meta.notification_settings.template === 'notification3');
    },
    // The true aggregate for the configured source(s), not filteredReviews.length (a capped fetch slice).
    pulseRating() {
      return this.businessInfo && this.businessInfo.average_rating ? parseFloat(this.businessInfo.average_rating).toFixed(1) : '0.0';
    },
    pulseCount() {
      return this.businessInfo && this.businessInfo.total_rating ? Number(this.businessInfo.total_rating) : this.filteredReviews.length;
    },
    // The score/count are aggregated across every configured source — only name a single platform
    // when businessInfo confirms the aggregate actually is single-platform, using its own top-level
    // platform_name (same field Badge1.vue's isSinglePlatform branch reads) rather than the first entry
    // of businessInfo.platforms, which holds every selected source, not just the one actually counted.
    pulsePlatformLabel() {
      if (this.businessInfo && this.businessInfo.total_platforms === 1 && this.businessInfo.platform_name) {
        return this.platformDisplayName(this.businessInfo.platform_name);
      }
      const totalPlatforms = this.businessInfo && this.businessInfo.total_platforms;
      return totalPlatforms > 1 ? this.$t('{count} platforms').replace('{count}', totalPlatforms) : this.$t('multiple platforms');
    },
    // Small per-platform logos instead of the text label when the aggregate is multi-platform —
    // see platformIconsFor() in WPSocialReviews.js.
    pulsePlatformIcons() {
      if (!this.businessInfo || this.businessInfo.total_platforms <= 1) {
        return [];
      }
      return this.platformIconsFor(this.businessInfo, this.template_meta?.isPlatformIcon !== 'false', this.template_meta?.display_tp_brand);
    },
    pulseAvatars() {
      const avatars = [];
      for (const review of this.filteredReviews) {
        if (review && review.reviewer_img) {
          avatars.push(review.reviewer_img);
          if (avatars.length === 3) {
            break;
          }
        }
      }
      return avatars;
    },
    trimmedExcerpt() {
      const review = this.filteredReviews[this.index];
      if (!review || !review.reviewer_text) {
        return '';
      }
      const text = String(review.reviewer_text).replace(/<[^>]*>/g, '').trim();
      if (!text) {
        return '';
      }
      const words = text.split(/\s+/);
      return words.length > 16 ? words.slice(0, 16).join(' ') + '…' : text;
    },
    showVerifiedBadge() {
      if (!this.isSpotlight) {
        return false;
      }
      const review = this.filteredReviews[this.index];
      const enabled = !!(this.template_meta && this.template_meta.enable_verified_badge === 'true');
      return enabled && !!review && review.platform_name !== 'ai';
    },
    verifiedTooltipText() {
      return (this.template_meta && this.template_meta.verified_badge_tooltip_text) || this.$t('Verified Customer');
    },
    // Reuses the same badge quality scale as BadgeScoreStrip.vue/BadgeSticker.vue instead of a
    // second one — ratingQualityLabel() is globally mixed in (resources/admin/main.js).
    ratingWord() {
      if (!this.isSpotlight) {
        return '';
      }
      const review = this.filteredReviews[this.index];
      return review ? this.ratingQualityLabel(review.rating) : '';
    },
    // Used by the "Logo + platform name" source row (the avatar corner uses platformDotLetter instead).
    avatarDotIcon() {
      const review = this.filteredReviews[this.index];
      if (!review) {
        return '';
      }
      if (this.appVars && this.appVars.platforms_cards && this.appVars.platforms_cards.some(card => card.platform === review.platform_name)) {
        return this.get_platform_icon(review.platform_name, 'small') || '';
      }
      if (review.source_id) {
        return this.customLogoSrc(review.source_id, this.businessInfo) || '';
      }
      return '';
    },
    // Facebook's own mark is a lowercase f; everything else uses the platform key's first letter.
    platformDotLetter() {
      const review = this.filteredReviews[this.index];
      const platform = review ? review.platform_name || '' : '';
      if (platform === 'facebook') {
        return 'f';
      }
      return platform ? platform.charAt(0).toUpperCase() : '';
    },
    sourceDisplay() {
      return (this.template_meta && this.template_meta.notification_settings && this.template_meta.notification_settings.source_display) || 'logo';
    },
    sourceLabel() {
      const review = this.filteredReviews[this.index];
      return this.platformDisplayName(review ? review.platform_name || '' : '');
    },
    showCustomText() {
      const settings = this.template_meta && this.template_meta.notification_settings;
      if (!settings || settings.display_custom_text === 'false') {
        return false;
      }
      return !!(settings.custom_notification_text && settings.custom_notification_text.length);
    }
  },
  created() {
    this.filterReviews();
  },
  methods: {
    // Filter out AI platform reviews, keeping each entry's original (raw grid) index alongside it.
    filterReviews() {
      const filteredReviews = [];
      const filteredRawIndices = [];
      (this.reviews || []).forEach((review, rawIndex) => {
        if (review && review.platform_name !== 'ai') {
          filteredReviews.push(review);
          filteredRawIndices.push(rawIndex);
        }
      });
      this.filteredReviews = filteredReviews;
      this.filteredRawIndices = filteredRawIndices;
    },
    formatDate(date) {
      if (!date) return '';
      return moment(date).fromNow();
    },
    expandContent(val) {
      this.$emit('contentExpand',true);
    },
    showPopupModal() {
      let that = this;
      jQuery('.wpsr-reviews-notification-card').on('click', function (e) {
        let display_mode = that.template_meta.notification_settings.display_mode;
        if(display_mode === 'popup' || display_mode === 'none') {
          e.preventDefault();
          if(display_mode === 'popup') {
            let reviewsWrapper = jQuery('#wpsr-reviews-grid');
            let notificationWrapper = jQuery('.wpsr-reviews-notification-card-wrapper');

            reviewsWrapper.removeClass('deactivate');
            reviewsWrapper.addClass('wpsr-notification-active');
            notificationWrapper.addClass('deactivate');

            let index = notificationWrapper.attr('data-index');
            let element = jQuery(".wpsr-reviews-layout-notification").find('[data-index="' + index + '"]');
            element.addClass('wpsr-highlight');

            document.querySelector('.wpsr-highlight').scrollIntoView({
              behavior: 'smooth',
              block: 'center',
            });
            setTimeout(function () {
              element.removeClass('wpsr-highlight');
            }, 2000);

            if (that.intervalHandle) {
              clearInterval(that.intervalHandle);
              that.intervalHandle = null;
            }
          }
        }
      });
    },
    hidePopupModal() {
      let that = this;
      jQuery('.wpsr-popup-collapse').on('click', function (e){
        e.preventDefault();
        that.activateNotification();
        let parents = jQuery(this).parents();
        parents.find('#wpsr-reviews-grid').removeClass('wpsr-notification-active');
        parents.find('#wpsr-reviews-grid').addClass('deactivate');
        parents.find('.wpsr-reviews-notification-card-wrapper').removeClass('deactivate');
        parents.find('.wpsr-reviews-notification-card-wrapper').addClass('wpsr-notification-active');
        parents.find('.wpsr-review-template').removeClass('wpsr-highlight');
      });
    },
    activateNotification() {
      if(this.intervalHandle) {
        clearInterval(this.intervalHandle);
        this.intervalHandle = null;
      }
      if(this.reviews && this.reviews.length) {
        let notification_delay = this.template_meta.notification_settings.notification_delay;

        if(!notification_delay) {
          notification_delay = 1000;
        }

        let parent = jQuery('.wpsr-reviews-notification-card-wrapper');
        //stop showing notification on button click
        jQuery('.wpsr-close').on('click', function (e) {
          e.preventDefault();
          jQuery(this).parents().find('.wpsr-reviews-notification-card-wrapper').addClass('deactivate');
        });

        let hover_able = 0;
        parent.mouseover(function() {
          hover_able = 1;
          jQuery(this).addClass('wpsr-visible-card');
        });

        parent.mouseleave(function() {
          hover_able = 0;
          jQuery(this).removeClass('wpsr-visible-card');
        });

        // Rating Pulse is a single static card — no rotation, no auto-hide/reshow cycle.
        if (this.isPulse) {
          return;
        }

        let that = this;
        this.intervalHandle = setInterval(function () {
          if (!that.filteredReviews.length) {
            return;
          }
          if(that.count % 2 === 1) {
            that.index++;
          }
          that.count++;
          if( that.filteredReviews.length === that.index || that.filteredReviews.length < that.index){
            that.index = 0;
          }

          // Same filtered-index -> raw-index mapping as navigate(): findElement() looks elements
          // up by the raw (unfiltered) grid index, but the Spotlight computed fields all read
          // filteredReviews[that.index], so that.index itself must stay in filtered space.
          let rawIndex = that.filteredRawIndices[that.index];
          let element = that.findElement(rawIndex);
          element.index = that.index;
          let platform  = element.review_platform || '';

          if( !hover_able ) {
            parent.toggleClass('wpsr-notification-active');
          }
          if(parent.hasClass('wpsr-notification-active')){
            if(platform) {
              parent.addClass('wpsr-reviews-platform-'+platform);
            }
          } else {
            if(platform) {
              parent.removeClass('wpsr-reviews-platform-'+platform);
            }
          }

          parent.attr('data-index', rawIndex);

          that.setElement(element);
        }, notification_delay);
      }
    },
    findElement(index){
      let element = jQuery(".wpsr-reviews-layout-notification").find('[data-index="' + index + '"]');
      let reviewer_image = '';
      if(this.template_meta.reviewer_image === 'true') {
        reviewer_image = element.find('.wpsr-reviewer-image img').attr('src');
      }
      let reviewer_name = element.find('.wpsr-review-info .wpsr-reviewer-name').text();
      let review_rating = element.find('.wpsr-rating-wrapper').data('rating');
      let review_date   = element.find('.wpsr-review-date').data('time');
      let review_platform   = element.data('review_platform') || '';
      let product_name  = element.data('product_name');
      let product_thumbnail = element.data('product_thumbnail');
      this.current_platform_name = review_platform;

      return {
        'reviewer_image': reviewer_image,
        'reviewer_name': reviewer_name,
        'review_rating': review_rating,
        'review_platform': review_platform,
        'review_date': review_date,
        'product_name': product_name,
        'product_thumbnail': product_thumbnail,
        'index': index
      };
    },
    setElement(element){
      let platform_icon = '';
      const review_platform = element.review_platform || '';
      
      if(review_platform && this.appVars.platforms_cards.some(card => card.platform === review_platform)){
        platform_icon = this.get_platform_icon(review_platform, 'small');//this.assets_url + "/images/icon/icon-"+ element.review_platform +"-small.png";
      } else {
        if(element.index !== undefined && 
           this.filteredReviews && 
           Array.isArray(this.filteredReviews) && 
           element.index >= 0 && 
           element.index < this.filteredReviews.length) {
          const reviewItem = this.filteredReviews[element.index];
          if(reviewItem && reviewItem.source_id) {
            platform_icon = this.customLogoSrc(reviewItem.source_id, this.businessInfo);
          }
        }
      }


      let that = this;
      if(this.template_meta.reviewer_image === 'true') {
        jQuery('.wpsr-reviews-notification-card .wpsr-reviewer-image img').attr('src', element.reviewer_image);
        jQuery('.wpsr-reviews-notification-card .wpsr-product-image img').attr('src', element.product_thumbnail);
      }
      jQuery('.wpsr-reviews-notification-card .reviewer-name').text(element.reviewer_name);
      jQuery('.wpsr-reviews-notification-card .review-rating').text(parseFloat(element.review_rating));
      // jQuery('.wpsr-reviews-notification-card .wpsr-review-platform img').attr('src', platform_icon);
      jQuery('.wpsr-reviews-notification-card .wpsr-rating').html(that.ratingIcon(element.review_rating));

      jQuery('.wpsr-reviews-notification-card .wpsr-review-source-title').text(element.product_name);

      let display_date = that.template_meta.notification_settings.display_date;
      if(display_date === 'true') {
        if(element.review_date && element.review_date.length) {
          jQuery('.wpsr-reviews-notification-card .review-time').text(this.formatDate(element.review_date));
        } else {
          jQuery('.wpsr-reviews-notification-card .review-time').text('');
        }
      }
      
      if(this.template_meta.isPlatformIcon == 'true' && platform_icon && review_platform && !review_platform.includes('fluent_forms') && !review_platform.includes('custom')) {
         if(review_platform.includes('trust') && this.template_meta.display_tp_brand === 'false') {
           this.togglePlatformIcon(element, platform_icon, 'wpsr-show-logo', 'wpsr-hide-logo');
         } else {
           this.togglePlatformIcon(element, platform_icon, 'wpsr-hide-logo', 'wpsr-show-logo');
         }
      } else {
        this.togglePlatformIcon(element, platform_icon, 'wpsr-show-logo', 'wpsr-hide-logo');
      }
    },
    navigate(direction) {
      if (!this.filteredReviews.length) {
        return;
      }
      this.destroyInterval();

      const total = this.filteredReviews.length;
      this.index = (this.index + direction + total) % total;

      // findElement() looks elements up by the raw (unfiltered) grid index, but setElement()
      // indexes back into filteredReviews with element.index — so fetch by raw index, then
      // restore the filtered-space index setElement() expects.
      const rawIndex = this.filteredRawIndices[this.index];
      const element = this.findElement(rawIndex);
      element.index = this.index;
      const platform = element.review_platform || '';
      let parent = jQuery('.wpsr-reviews-notification-card-wrapper');
      if (this.current_platform_name) {
        parent.removeClass('wpsr-reviews-platform-' + this.current_platform_name);
      }
      if (platform) {
        parent.addClass('wpsr-reviews-platform-' + platform);
      }
      parent.attr('data-index', rawIndex);
      this.setElement(element);

      // A manual nudge still restarts the auto rotation from here, same pacing as a normal tick.
      this.activateNotification();
    },
    togglePlatformIcon(element, platform_icon, class1, class2) {
      let spanEl = jQuery('.wpsr-notification-body > span');
      let iconEl = jQuery('.wpsr-reviews-notification-card .wpsr-review-platform img');

      if(iconEl.hasClass(class1)) {
        iconEl.removeClass(class1);
        iconEl.addClass(class2);
      }
      iconEl.attr('src', platform_icon);
      if(spanEl.hasClass(class1)) {
        spanEl.removeClass(class1);
        spanEl.addClass(class2);
      }
    },
    destroyInterval() {
      if (this.intervalHandle) {
        let that = this;
        clearInterval(that.intervalHandle);
        this.intervalHandle = null;
      }
    }
  },
  beforeDestroy: function() {
    this.destroyInterval();
  },
  watch: {
    // The editor mounts this before a new template's reviews are fetched, so re-filter on every change.
    reviews() {
      const wasRendered = this.filteredReviews.length > 0;
      this.filterReviews();
      if (this.index >= this.filteredReviews.length) {
        this.index = 0;
      }
      this.$nextTick(() => {
        // The card element only exists once filteredReviews is non-empty, so bind its click handler now.
        if (!wasRendered && this.filteredReviews.length) {
          this.showPopupModal();
        }
        this.activateNotification();
      });
    },
    'template_meta.notification_settings.notification_delay': {
      handler(delay) {
        this.activateNotification();
      },
      deep: true
    },
    'template_meta.platform': {
      handler(platform) {
        if(platform.length) {
          this.activateNotification();
        } else {
          if (this.intervalHandle) {
            this.destroyInterval();
          }
        }
      },
      deep: true
    }
  },
  mounted() {
    this.showPopupModal();
    this.hidePopupModal();
    this.activateNotification();
  }
}
</script>