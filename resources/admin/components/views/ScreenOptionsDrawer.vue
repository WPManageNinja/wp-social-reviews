<template>
  <transition name="slide-drawer">
    <div v-if="visible" class="wpsr-screen-options-drawer">
      <!-- Header -->
      <div class="wpsr-drawer-header">
        <h2>{{$t('Screen Options')}}</h2>
        <button class="wpsr-drawer-close" @click="closeDrawer">
          <el-icon><Close /></el-icon>
        </button>
      </div>

      <!-- Loading indicator -->
      <div v-if="isLoading" class="wpsr-drawer-loading">
        <el-icon class="is-loading"><Loading /></el-icon>
        <span>Loading options...</span>
      </div>

      <!-- Content (only show when not loading and no errors) -->
      <template v-if="!isLoading">
        <div class="wpsr-drawer-content">
          <!-- Social Feeds Section -->
          <platform-section
              :title="$t('Social Feeds')"
              :options="localScreenOptions.feeds"
              type="feeds"
              @update="handleOptionChange"
              @select-all="selectAll('feeds', $event)"
          />

          <!-- Business Reviews Section -->
          <platform-section
              :title="$t('Business Reviews')"
              :options="localScreenOptions.reviews"
              type="reviews"
              @update="handleOptionChange"
              @select-all="selectAll('reviews', $event)"
          />

          <!-- Social Chats Section -->
          <platform-section
              :title="$t('Social Chats')"
              :options="localScreenOptions.chats"
              type="chats"
              @update="handleOptionChange"
          />
        </div>
      </template>
    </div>
  </transition>
</template>

<script>
import { ElIcon } from 'element-plus';
import { Close, Loading, Warning } from '@element-plus/icons-vue';
import PlatformSection from './drawer/PlatformSection.vue';

export default {
  name: 'ScreenOptionsDrawer',
  components: {
    ElIcon,
    Close,
    Loading,
    Warning,
    PlatformSection
  },
  props: {
    visible: {
      type: Boolean,
      required: true
    },
    screenOptions: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'update'],
  data() {
    return {
      localScreenOptions: {
        feeds: {
          instagram: true,
          youtube: true,
          facebook_feed: true,
          twitter: true,
          tiktok: true
        },
        reviews: {
          google: true,
          airbnb: true,
          yelp: true,
          tripadvisor: true,
          amazon: true,
          aliexpress: true,
          'booking.com': true,
          facebook: true,
          woocommerce: true,
          trustpilot: true
        },
        chats: {
          allinone: true
        }
      },
      isLoading: false
    };
  },
  watch: {
    screenOptions: {
      handler(newVal) {
        // Update local options when parent options change
        this.localScreenOptions = JSON.parse(JSON.stringify(newVal));
      },
      immediate: true,
      deep: true
    }
  },
  created() {
    // Initialize local options from props
    this.localScreenOptions = JSON.parse(JSON.stringify(this.screenOptions));
    // No need to fetch platform statuses here since we're getting them from props
  },
  mounted() {
    this.isLoadingShouldShow();
  },
  methods: {
    handleOptionChange(type, key, value) {
      // Update the local option
      this.localScreenOptions[type][key] = value;
      // Emit the update event with the entire updated options object
      this.emitUpdate();
    },

    selectAll(type, value) {
      // Set all platforms in the specified type to the given value
      Object.keys(this.localScreenOptions[type]).forEach(key => {
        this.localScreenOptions[type][key] = value;
      });
      // Emit the update event
      this.emitUpdate();
    },

    emitUpdate() {
      // Create a deep copy to avoid reference issues when sending back to parent
      const updatedOptions = JSON.parse(JSON.stringify(this.localScreenOptions));
      this.$emit('update', updatedOptions);
    },
    closeDrawer() {
      this.isLoadingShouldShow();
      this.$emit('close');
    },
    isLoadingShouldShow() {
      this.isLoading = true;
      setTimeout(() => {
        this.isLoading = false;
      }, 900);
    }
  }
};
</script>