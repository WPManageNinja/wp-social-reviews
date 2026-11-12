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

      <!-- Content -->
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
          ...(this.appVars.tp_slug && this.appVars.tp_slug !== '_not_defined_yet_' ? { [this.appVars.tp_slug]: true } : {}),
          custom_sources: true,
          'fluent-cart': true
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
        this.localScreenOptions = JSON.parse(JSON.stringify(newVal));
      },
      immediate: true,
      deep: true
    }
  },
  created() {
    this.localScreenOptions = JSON.parse(JSON.stringify(this.screenOptions));
  },
  mounted() {
    this.isLoadingShouldShow();
  },
  methods: {
    handleOptionChange(type, key, value) {
      this.localScreenOptions[type][key] = value;
      this.emitUpdate();
    },

    selectAll(type, value) {
      Object.keys(this.localScreenOptions[type]).forEach(key => {
        this.localScreenOptions[type][key] = value;
      });
      this.emitUpdate();
    },

    emitUpdate() {
      // Deep copy so the parent never shares a reference with local state.
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