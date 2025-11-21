<template>
  <div class="wpsr-connection-platform">
    <span class="wpsr-platform-tab-content">
      {{$t('Click a review platform below to connect your account and get started.')}}
    </span>

    <div class="wpsr-connection-wrapper">
      <ReviewsPlatforms
        v-if="platformActiveTab === 'reviews'"
        :displayTitle="false"
        :column="12"
        :filteredPlatforms="connectionFilteredPlatforms"
        :configurationInfo="configurationInfo"
        :connectionMode="true"
        @update:reviewsShowSettingModal="updateReviewsShowSettingModal"
    />
    </div>
  </div>
</template>

<script type="text/babel">
import ReviewsPlatforms from "../../../views/platforms/reviews/ReviewsPlatforms";

export default {
  name: "ReviewsConnections",
  components: {
    ReviewsPlatforms,
  },
  props: {
    filteredPlatforms: {
      type: Array,
      required: true,
    },
    platformActiveTab: {
      type: String,
      required: true,
    },
    configurationInfo: {
      type: Object,
      required: true,
    }
  },
  data() {
    return {
      searchQuery: '',
      message: '',
    }
  },
  computed: {
    connectionFilteredPlatforms() {
      if (!this.searchQuery) {
        this.message = '';
        return this.filteredPlatforms;
      }

      const query = this.searchQuery.toLowerCase();
      const filtered = this.filteredPlatforms.filter(platform =>
          platform.platform_title?.toLowerCase().includes(query)
      );

      // if not a match return, no platform found text
      if (filtered.length === 0) {
        this.message = 'Platform not found!';
      } else {
        this.message = '';
      }

      return filtered;
    },
  },

  methods: {
      updateReviewsShowSettingModal(val) {
        this.$emit('update:activeTab', 'connection');
      }
  },
}
</script>