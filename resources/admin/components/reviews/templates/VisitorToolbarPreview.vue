<template>
  <!--
    A dead copy of the frontend search/sort/rating bar, so the owner can see where it lands before publishing.
    It runs no queries: the editor renders reviews already in memory, while the real bar re-fetches through the
    public AJAX endpoint. Same classes as the frontend view, so the shared stylesheet keeps the two in step.
  -->
  <el-tooltip
    effect="dark"
    placement="top"
    :content="$t('This search and sort bar works on your live site, not in this editor. It is shown here so you can see where it appears.')"
  >
    <div
      class="wpsr-reviews-sort-toolbar wpsr-reviews-sort-toolbar-preview"
      :data-order="currentOrder"
      data-rating="-1"
    >
      <label v-if="searchEnabled" class="wpsr-reviews-sort-toolbar-field wpsr-reviews-search-field">
        <input
          type="search"
          class="wpsr-reviews-search-input"
          :placeholder="$t('Search reviews, press enter')"
          tabindex="-1"
          disabled
        />
        <svg class="wpsr-reviews-search-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
          <circle cx="7" cy="7" r="5.25" stroke="currentColor" stroke-width="1.5" />
          <path d="M10.9 10.9L14.5 14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
        </svg>
      </label>

      <label v-if="sortOptions.length" class="wpsr-reviews-sort-toolbar-field">
        <select class="wpsr-reviews-sort-select" tabindex="-1" disabled>
          <option
            v-for="option in sortOptions"
            :key="option.value"
            :value="option.value"
            :selected="option.value === currentOrder"
          >{{ option.label }}</option>
        </select>
      </label>

      <label v-if="ratingOptions.length" class="wpsr-reviews-sort-toolbar-field">
        <select class="wpsr-reviews-rating-select" tabindex="-1" disabled>
          <option
            v-for="option in ratingOptions"
            :key="option.value"
            :value="option.value"
            :selected="option.value === -1"
          >{{ option.label }}</option>
        </select>
      </label>
    </div>
  </el-tooltip>
</template>

<script type="text/babel">
import {
  wpsrToolbarSortOptions,
  wpsrToolbarRatingOptions,
  wpsrToolbarCurrentOrder,
  wpsrToolbarSearchEnabled,
} from '../../../utils/visitorToolbar';

export default {
  name: 'VisitorToolbarPreview',
  props: {
    template_meta: {
      type: Object,
      required: true,
    },
  },
  computed: {
    currentOrder() {
      return wpsrToolbarCurrentOrder(this.template_meta);
    },
    sortOptions() {
      return wpsrToolbarSortOptions(this.template_meta, this.$t);
    },
    ratingOptions() {
      return wpsrToolbarRatingOptions(this.template_meta, this.$t);
    },
    searchEnabled() {
      return wpsrToolbarSearchEnabled(this.template_meta);
    },
  },
};
</script>
