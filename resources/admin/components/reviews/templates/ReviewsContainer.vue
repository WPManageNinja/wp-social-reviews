<template>
  <div class="wpsr-container" v-if="template_meta"
       :class="[
      template_meta.show_header === 'true' && !template_meta.platform.includes('testimonial') ? 'wpsr-fixed-height' : '',
      template_meta.equal_height === 'true' && template_meta.contentType === 'excerpt' ? 'wpsr-has-equal-height' : '',
      template_meta.contentType === 'content_in_scrollbar' ? 'wpsr-reviews-content-length-deactive' : '',
      'wpsr-reviews-template-'+template_meta.template,
      has_pro && template_meta.templateType === 'grid' && template_meta.layout_style === 'feature_grid' ? 'wpsr-reviews-layout-style-feature_grid' : '',
      marqueePaused ? 'wpsr-marquee-paused' : ''
    ]"
       :style="'max-width:' +template_meta.template_width"
  >
    <div v-if="errors" v-for="(error, index ) in errors" :key="index">
      <div class="wpsr-feed-error-box">
        <p v-if="error.error_message" v-html="error.error_message"></p>
        <p v-if="error.admin_only" v-html="error.admin_only"></p>
      </div>
    </div>

    <div v-if="infos" v-for="(info, index ) in infos" :key="index">
      <InfoCard :info="info"/>
    </div>
    <BusinessInfo v-if="businessInfo && reviews && template_meta && !template_meta.platform.includes('testimonial')"
        :class="template_meta.layout_style === 'score_slider' ? 'wpsr-business-info-wrapper' : ''"
        :businessInfo="businessInfo"
        :reviews="reviews"
        :metaStyles="template_meta"
        :hasActiveFilter="!!currentFilter"
        :activeFilterData="currentFilter"
        @rating-filter="handleRatingFilter"
    />
    <!-- Where the frontend header view prints the real toolbar. -->
    <VisitorToolbarPreview v-if="showVisitorToolbarPreview" :template_meta="template_meta" />
    <div :class="[template_meta.templateType === 'slider' && has_pro ? 'wpsr-reviews-slider-wrapper-inner' : '']">
      <div class="wpsr-active-masonry-layout wpsr-reviews-slider" :data-column="template_meta?.responsive_column_number?.desktop || 1" :class="template_meta.templateType === 'slider' && has_pro ? 'swiper-container' : (template_meta.show_header === 'true' && !template_meta.platform.includes('testimonial') ? 'wpsr-row' : '')" :style="'height:' +template_meta.template_height">
          <template v-if="isMarqueePreview">
            <div v-for="(row, rowIndex) in marqueeRows" :key="'marquee-row-' + rowIndex"
                 class="wpsr-marquee-row wpsr-marquee-ready" :data-direction="rowIndex ? 'right' : 'left'">
              <div class="wpsr-marquee-track" :style="marqueeTrackStyle(row)">
                <component
                    v-for="copy in marqueeCopies(row)"
                    :key="'marquee-copy-' + copy"
                    :aria-hidden="copy > 1 ? 'true' : null"
                    :is="templates[template_meta.template]"
                    v-bind="skinProps"
                    :reviews="row"
                    @expandContent="expandContent"
                />
              </div>
            </div>
            <button type="button" class="wpsr-marquee-toggle" :aria-pressed="marqueePaused ? 'true' : 'false'"
                    :aria-label="marqueePaused ? $t('Play scrolling reviews') : $t('Pause scrolling reviews')"
                    @click="marqueePaused = !marqueePaused">
              <i :class="marqueePaused ? 'icon-play' : 'icon-pause'" aria-hidden="true"></i>
            </button>
          </template>
          <component
              v-else
              :is="templates[template_meta.template]"
              v-bind="skinProps"
              :reviews="filteredReviews"
              @expandContent="expandContent"
          />
          <div v-if="has_pro && template_meta?.notification_settings?.display_read_all_reviews_btn === 'true' && Object.keys(businessInfo).length && template_meta.templateType === 'notification'"
               class="wpsr-reviews-footer-cta"
          >
            <a :href="template_meta?.notification_settings?.read_all_reviews_btn_url" target="_blank" rel="nofollow">{{$t('Read all reviews')}}</a>
          </div>
      </div>
      <swiper-navs v-if="template_meta.templateType === 'slider' && has_pro" :options="template_meta.carousel_settings"></swiper-navs>
    </div>

    <!-- Load More Button -->
    <div v-if="shouldShowLoadMore" class="wpsr-load-more-container" style="text-align: center; margin-top: 20px;">
      <button
        @click="handleLoadMore"
        :disabled="loadMoreLoading"
        class="wpsr-reviews-loadmore wpsr_more"
        :style="{ opacity: loadMoreLoading ? 0.6 : 1, cursor: loadMoreLoading ? 'not-allowed' : 'pointer' }"
      >
        <span v-if="!loadMoreLoading">{{ template_meta.load_more_button_text || 'Load More' }}</span>
        <span v-else>Loading...</span>
      </button>
    </div>
  </div>
</template>

<script type="text/babel">
import Template1 from '../templates/Template1';
import Template2 from '../templates/Template2';
import Template3 from '../templates/Template3';
import Template4 from '../templates/Template4';
import Template5 from '../templates/Template5';
import Template6 from '../templates/Template6';
import Template7 from '../templates/Template7';
import Template8 from '../templates/Template8';
import Template9 from '../templates/Template9';
import Template10 from '../templates/Template10';
import Testimonial1 from '../templates/testimonial/Testimonial1';
import Testimonial2 from '../templates/testimonial/Testimonial2';
import ErrorCard from "../../feeds/templates/ErrorCard";
import InfoCard from '../../feeds/templates/InfoCard.vue';

import BusinessInfo from './elements/BusinessInfo';

import SwiperNavs from "../../core-ui/editor/SwiperNavs";
import {reviewsMixin} from '../../../mixins/reviewsMixin';
import ReviewBody from './elements/ReviewBody.vue';
import VisitorToolbarPreview from './VisitorToolbarPreview.vue';
import { wpsrToolbarActive } from '../../../utils/visitorToolbar';
import { markRaw } from 'vue';

export default {
  props:['businessInfo', 'template_meta', 'template', 'reviews', 'errors', 'infos', 'image_settings', 'hasMoreReviews', 'loadMoreLoading'],
  mixins: [reviewsMixin],
  data() {
    return {
      templates: {
        'grid1' : markRaw(Template1),
        'grid2' : markRaw(Template2),
        'grid3' : markRaw(Template3),
        'grid4' : markRaw(Template4),
        'grid5' : markRaw(Template5),
        'grid6' : markRaw(Template6),
        'grid7' : markRaw(Template7),
        'grid8' : markRaw(Template8),
        'grid9' : markRaw(Template9),
        'grid10' : markRaw(Template10),
        'testimonial1' : markRaw(Testimonial1),
        'testimonial2' : markRaw(Testimonial2),
      },
      currentFilter: null,
      filteredReviews: [],
      marqueePaused: false
    }
  },
  mounted() {
    window.addEventListener('scroll', this.onScroll);
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.onScroll);
  },
  computed:{
    reviewsWrapperClasses(){
      if(this.template_meta?.ai_summary?.enabled === 'true' && (this.template_meta.templateType === 'grid' || this.template_meta.templateType === 'notification')){
        return ['wpsr-ai-summary-enabled'];
      } else if(this.template_meta?.ai_summary?.enabled === 'true' && this.template_meta.templateType !== 'slider'){
        return ['wpsr-ai-summary-enabled-'+this.template_meta.templateType];
      }

      return [this.template_meta.templateType === 'slider' && this.has_pro ? 'swiper-wrapper wpsr-ai-summary-enabled-slider' : (this.template_meta.show_header === 'true' ? 'wpsr-review-fixed-height-wrap' : 'wpsr-row')];
    },
    skinProps() {
      return {
        template: this.template_meta.template,
        template_meta: this.template_meta,
        image_settings: this.image_settings,
        businessInfo: this.businessInfo,
        average_rating: this.businessInfo.average_rating,
        total_rating: this.businessInfo.total_rating,
        reviewsWrapperClasses: this.reviewsWrapperClasses
      };
    },
    isMarqueePreview() {
      return this.has_pro && this.template_meta.templateType === 'marquee';
    },
    // Same split as Helper::marqueeRows(): alternating reviews, one row under four.
    marqueeRows() {
      const reviews = this.filteredReviews || [];
      if (reviews.length < 4) {
        return [reviews];
      }
      return [reviews.filter((review, index) => index % 2 === 0), reviews.filter((review, index) => index % 2 === 1)];
    },
    shouldShowLoadMore() {
      const shouldShow = this.template_meta &&
             this.hasMoreReviews &&
             this.template_meta.templateType !== 'slider' &&
             this.template_meta.templateType !== 'marquee' &&
             this.reviews && this.reviews.length > 0;
      return shouldShow;
    },
    // Same condition as the frontend bar, so the preview never shows a control visitors won't get.
    showVisitorToolbarPreview() {
      return wpsrToolbarActive(this.template_meta, this.has_pro);
    }
  },
  components: {
    Template1,
    Template2,
    Template3,
    Template4,
    Template5,
    Template6,
    Template7,
    Template8,
    Template9,
    Template10,
    Testimonial1,
    Testimonial2,
    BusinessInfo,
    SwiperNavs,
    ErrorCard,
    ReviewBody,
    InfoCard,
    VisitorToolbarPreview
  },
  methods: {
    marqueeCardWidth() {
      return parseInt(this.template_meta?.marquee_settings?.card_width, 10) || 360;
    },
    // Copies of the row so half the track is at least a preview row wide (assumed 1200px) and the loop never shows a gap.
    marqueeCopies(row) {
      const setWidth = Math.max(1, row.length) * (this.marqueeCardWidth() + 20);
      return Math.max(1, Math.ceil(1200 / setWidth)) * 2;
    },
    marqueeTrackStyle(row) {
      const pxPerSecond = parseInt(this.template_meta?.marquee_settings?.speed, 10) || 35;
      const halfWidth = row.length * (this.marqueeCardWidth() + 20) * (this.marqueeCopies(row) / 2);
      return {
        '--wpsr-marquee-card-width': this.marqueeCardWidth() + 'px',
        '--wpsr-marquee-duration': (halfWidth / pxPerSecond).toFixed(2) + 's'
      };
    },
    expandContent(val) {
      this.$emit('contentExpand',true);
    },

    handleRatingFilter(filterData) {
      if (filterData.filterType === 'clear') {
        this.resetFilters();
        this.$emit('rating-filter', filterData);
        return;
      }

      this.currentFilter = filterData;

      this.applyFiltersAndSort();
      this.$emit('rating-filter', filterData);
    },

    /**
     * filterData: { type: 'star'|'booking'|'clear', minRating, maxRating, filterType: 'rating'|'sort'|'clear' }
     */
    applyFiltersAndSort() {
      let filtered = [...(this.reviews || [])];

      if (this.currentFilter && this.currentFilter.filterType === 'rating') {
        filtered = this.filterByRating(filtered, this.currentFilter);
      }

      this.filteredReviews = filtered;
    },

    filterByRating(reviews, filterData) {
      return reviews.filter(review => {
        const rating = parseFloat(review.rating || 0);

        if (filterData.type === 'booking') {
          return rating >= filterData.minRating && rating <= filterData.maxRating;
        } else {
          return rating >= filterData.minRating && rating <= filterData.maxRating;
        }
      });
    },

    sortReviews(reviews, sortData) {
      const sorted = [...reviews];

      switch(sortData.sortBy) {
        case 'newest':
          return sorted.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        case 'oldest':
          return sorted.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        case 'highest_rating':
          return sorted.sort((a, b) => parseFloat(b.rating || 0) - parseFloat(a.rating || 0));
        case 'lowest_rating':
          return sorted.sort((a, b) => parseFloat(a.rating || 0) - parseFloat(b.rating || 0));
        case 'most_helpful':
          return sorted.sort((a, b) => (b.helpful_count || 0) - (a.helpful_count || 0));
        default:
          return sorted;
      }
    },

    resetFilters() {
      this.currentFilter = null;
      this.filteredReviews = [...(this.reviews || [])];
    },

    handleSort(sortData) {
      this.currentFilter = sortData;
      this.applyFiltersAndSort();
      this.$emit('sort-change', sortData);
    },
    handleLoadMore() {
      this.$emit('load-more');
    },
    onScroll() {
      if (this.loadMoreLoading || !this.shouldShowLoadMore || this.template_meta.pagination_type === 'load_more') return;

      const scrollPosition = window.scrollY + window.innerHeight;
      const bottomOffset = document.documentElement.offsetHeight - 100;

      if (scrollPosition >= bottomOffset) {
        this.handleLoadMore();
      }
    },
  },

  watch: {
    reviews: {
      immediate: true,
      handler(newReviews) {
        this.filteredReviews = [...(newReviews || [])];

        if (this.currentFilter) {
          this.applyFiltersAndSort();
        }
      }
    }
  }
}
</script>