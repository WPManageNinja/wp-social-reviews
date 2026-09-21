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
        :wpsr-auth-return-url-builder="wpsrBuildAuthReturnUrl"
        @update:reviewsShowSettingModal="updateReviewsShowSettingModal"
    />
    </div>
  </div>
</template>

<script type="text/babel">
import ReviewsPlatforms from "../../../views/platforms/reviews/ReviewsPlatforms";
import { wpsrBuildAuthReturnUrl as buildWpsrAuthReturnUrl } from "../../../../utils";

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
      wpsrBuildAuthReturnUrl(routeName, authPlatform = '', fallbackUrl = '') {
        const templateId = this.$route && this.$route.params ? this.$route.params.template_id : '';
        return buildWpsrAuthReturnUrl(routeName, authPlatform, fallbackUrl || this.appVars.admin_page_url, templateId);
      },
      updateReviewsShowSettingModal(val) {
        this.$emit('update:activeTab', 'connection');
      }
  },
}
</script>
