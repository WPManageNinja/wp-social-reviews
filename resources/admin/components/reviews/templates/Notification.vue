<template>
    <a class="wpsr-reviews-notification-card-wrapper wpsr-notification-active"
         :class="[
             template_meta && template_meta.notification_settings ? 'wpsr-'+template_meta.notification_settings.notification_position : '',
             template_meta && template_meta.notification_settings && template_meta.notification_settings.display_mode !== 'none' ? 'wpsr-enable-cursor' : '',
             filteredReviews.length ? 'wpsr-reviews-platform-'+filteredReviews[0].platform_name : ''
         ]"
         data-index="0"
         v-if="filteredReviews.length"
        :href="template_meta && template_meta.notification_settings ? (template_meta.notification_settings.display_mode === 'page' ? template_meta.notification_settings.url : template_meta.notification_settings.custom_url) : '#'"
        target="_blank"
    >
      <div class="wpsr-reviews-notification-card">
        <div class="wpsr-notification-image-wrapper">
          <div class="wpsr-reviewer-image" v-if="template_meta && template_meta.reviewer_image === 'true'">
            <img :src="filteredReviews[0].reviewer_img ? filteredReviews[0].reviewer_img : assets_url+'/images/template/review-template/placeholder-image.png'">
          </div>
        </div>
        <div class="wpsr-notification-content-wrapper">
          <div class="wpsr-review-header">
            <span class="reviewer-name">{{ filteredReviews[0].reviewer_name + ' ' }}</span>
            <p v-if="template_meta && template_meta.notification_settings && template_meta.notification_settings.custom_notification_text && template_meta.notification_settings.custom_notification_text.length" 
               v-html="template_meta.notification_settings.custom_notification_text.replace('{review_rating}', `<span class='review-rating'>${parseFloat(filteredReviews[0].rating)}</span>`)">
            </p>
          </div>
          <div class="wpsr-notification-body">
            <div class="wpsr-rating-wrapper">
             
                <div class="wpsr-rating" v-html="ratingIcon(filteredReviews[0].rating)"></div>
          
            </div>
            <span :class="hideLogo()">
              {{ $t('On') }}
            </span>
            <div class="wpsr-review-platform">
              <img v-if="filteredReviews[0] && appVars.platforms_cards.some(card => card.platform === filteredReviews[0].platform_name)" :class="hideLogo()" :src="get_platform_icon(filteredReviews[0].platform_name, 'small')" :alt="filteredReviews[0].platform_name">
              <img v-else-if="filteredReviews[0] && filteredReviews[0].source_id" :src="customLogoSrc(filteredReviews[0].source_id, businessInfo)" alt="">
              <span class="wpsr-review-source-title">{{ filteredReviews[0] && filteredReviews[0].fields ? filteredReviews[0].fields.product_name : ''}}</span>
            </div>
          </div>
          <div class="wpsr-notification-footer" v-if="template_meta && template_meta.notification_settings && template_meta.notification_settings.display_date === 'true'">
            <span class="review-time">{{ formatDate(filteredReviews[0].review_time) }}</span>&nbsp;
          </div>
        </div>
      </div>
      <span class="wpsr-close" v-if="template_meta && template_meta.notification_settings && template_meta.notification_settings.display_close_button === 'true'">
          <svg viewBox="0 0 16 16" style="fill: rgb(255, 255, 255);">
            <path d="M3.426 2.024l.094.083L8 6.586l4.48-4.479a1 1 0 011.497 1.32l-.083.095L9.414 8l4.48 4.478a1 1 0 01-1.32 1.498l-.094-.083L8 9.413l-4.48 4.48a1 1 0 01-1.497-1.32l.083-.095L6.585 8 2.106 3.522a1 1 0 011.32-1.498z"></path>
          </svg>
        </span>
    </a>
</template>

<style lang="scss">

</style>

<script type="text/babel">
import moment from 'moment';
import {reviewsMixin} from '../../../mixins/reviewsMixin';

export default {
  name: 'Notification',
  mixins: [reviewsMixin],
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
      filteredReviews: []
    }
  },
  created() {
    // Filter out AI platform reviews
    this.filteredReviews = this.reviews.filter(review =>
      review && (!review.platform_name || !review.platform_name.includes('ai'))
    );
  },
  methods: {
    formatDate(date) {
      if (!date) return '';
      return moment(date).fromNow();
    },
    hideLogo() {
      const platformName = this.current_platform_name || '';
      const shouldHide = 
        this.template_meta.isPlatformIcon === 'false' || 
        platformName.includes('fluent_forms') || 
        platformName.includes('custom') || 
        (platformName.includes('trust') && this.template_meta.display_tp_brand === 'false');
      return shouldHide ? 'wpsr-hide-logo' : 'wpsr-show-logo';
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

        let that = this;
        this.intervalHandle = setInterval(function () {
          if(that.count % 2 === 1) {
            that.index++;
          }
          that.count++;
          if( that.reviews.length === that.index || that.reviews.length < that.index){
            that.index = 0;
          }
          let element = that.findElement(that.index);
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

          parent.attr('data-index', that.index);

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
      
      if(this.template_meta.isPlatformIcon == 'true' && review_platform && !review_platform.includes('fluent_forms') && !review_platform.includes('custom')) {
         if(review_platform.includes('trust') && this.template_meta.display_tp_brand === 'false') {
           this.togglePlatformIcon(element, platform_icon, 'wpsr-show-logo', 'wpsr-hide-logo');
         } else {
           this.togglePlatformIcon(element, platform_icon, 'wpsr-hide-logo', 'wpsr-show-logo');
         }
      } else {
        this.togglePlatformIcon(element, platform_icon, 'wpsr-show-logo', 'wpsr-hide-logo');
      }
    },
    togglePlatformIcon(element, platform_icon, class1, class2) {
      let spanEl = jQuery('.wpsr-notification-body span');
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