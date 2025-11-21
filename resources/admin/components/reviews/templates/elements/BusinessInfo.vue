<template>
    <div>
      <!-- Show empty state when total_rating is 0 -->
      <EmptyBusinessInfo
          v-if="shouldShowEmptyState"
          :metaStyles="metaStyles"
      >
      </EmptyBusinessInfo>

      <!-- Show normal template when there are reviews -->
      <component
          v-else
          :is="selectedTemplate"
          :businessInfo="businessInfo"
          :reviews="reviews"
          :metaStyles="metaStyles"
          :hasActiveFilter="hasActiveFilter"
          :activeFilterData="activeFilterData"
          @rating-filter="handleRatingFilter"
      />
    </div>
</template>

<script type="text/babel">
import Template1 from '../headerTemplates/Template1';
import Template2 from '../headerTemplates/Template2';
import EmptyBusinessInfo from './EmptyBusinessInfo.vue';

export default {
    props: ['businessInfo', 'reviews', 'metaStyles', 'hasActiveFilter', 'activeFilterData'],
    components: {
        Template1,
        Template2,
        EmptyBusinessInfo
    },
    computed: {
        selectedTemplate() {
            // Check if metaStyles has a headerTemplate property
            // Default to Template1 if not specified
            const templateType = this.metaStyles?.header_template?.toLowerCase() || 'template1';

            switch(templateType) {
                case 'template2':
                    return 'Template2';
                case 'template1':
                default:
                    return 'Template1';
            }
        },
        shouldShowEmptyState() {
            // Show empty state when total_rating is 0 or less
            return !this.businessInfo.total_rating || this.businessInfo.total_rating <= 0;
        }
    },
    methods: {
        handleRatingFilter(filterData) {
            // Pass the rating filter event up to the parent component
            this.$emit('rating-filter', filterData);
        }
    }
}
</script>