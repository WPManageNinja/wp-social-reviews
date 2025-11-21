<template>
    <div class="wpsr-section-header">
      <h4>{{ title }}</h4>
      <div class="wpsr-select-all-controls" v-if="type !== 'chats'">
        <el-checkbox
          :indeterminate="isIndeterminate"
          :model-value="allChecked"
          @change="toggleAll"
        >
          {{ allChecked ? $t('All Enabled') : isIndeterminate ? $t('Some Enabled') : $t('None Enabled') }}
        </el-checkbox>
      </div>
    </div>
    <div class="wpsr-drawer-section">
      <div v-for="(enabled, key) in options" :key="key" :class="{'wpsr-drawer-option-enabled': enabled}" class="wpsr-drawer-option">
        <label :for="'switch-' + type + '-' + key" class="wpsr-option-label">
          <div class="wpsr-platform-info">
            <ChatWidgetSectionIcon class="wpsr-mr-10" v-if="key === 'allinone'"/>
            <img v-else :src="getPlatformIcon(key, 'small')" alt="" class="image">
            {{ formatPlatformName(key) }}
          </div>
          <el-switch
              :id="'switch-' + type + '-' + key"
              v-model="localOptions[key]"
              @change="updateOption(key, $event)"
          />
        </label>
      </div>
    </div>
</template>

<script>
import { ElSwitch, ElCheckbox } from 'element-plus';
import { PlatformIconMixin } from '../../../mixins/PlatformIconMixin';
import ChatWidgetSectionIcon from "../../pieces/icons/ChatWidgetSectionIcon.vue";

export default {
  name: 'PlatformSection',
  mixins: [PlatformIconMixin],
  components: {
    ChatWidgetSectionIcon,
    ElSwitch,
    ElCheckbox
  },
  props: {
    title: {
      type: String,
      required: true
    },
    options: {
      type: Object,
      required: true
    },
    type: {
      type: String,
      required: true,
      validator: value => ['feeds', 'reviews', 'chats'].includes(value)
    }
  },
  emits: ['update', 'select-all'],
  data() {
    return {
      localOptions: {},
      platforms: window.WPSocialReviewsAdmin.platforms_cards,
    };
  },
  watch: {
    options: {
      handler(newVal) {
        this.localOptions = { ...newVal };
      },
      deep: true,
      immediate: true
    }
  },
  computed: {
    allChecked() {
      const values = Object.values(this.options);
      return values.length && values.every(Boolean);
    },
    isIndeterminate() {
      const values = Object.values(this.options);
      return values.some(Boolean) && !this.allChecked;
    }
  },
  methods: {
    // getPlatformIcon(size, platform) {
    //   let platformName = platform === 'facebook_feed' ? 'facebook' : platform === 'booking' ? 'booking.com' : platform;
    //   let data = [
    //       ...this.platforms,
    //   { platform: 'facebook_feed', platform_title: 'Facebook', image: this.assets_url+'/images/icon/icon-facebook.png'},
    //   { platform: 'instagram', platform_title: 'Instagram', image: this.assets_url+'/images/icon/icon-instagram.png' },
    //   { platform: 'twitter', platform_title: 'Twitter', image: this.assets_url+'/images/icon/icon-twitter.png' },
    //   { platform: 'tiktok', platform_title: 'TikTok', image: this.assets_url+'/images/icon/icon-tiktok.png' },
    //   { platform: 'youtube', platform_title: 'YouTube', image: this.assets_url+'/images/icon/icon-youtube.png' },
    //   { platform: 'allinone', platform_title: 'All-in-One Chat Widget', image: this.assets_url+'/images/icon/chats.png' },
    //   ];
    //   const matched = data.find(item => item.platform === platformName);
    //   return matched ? matched.image : '';
    // },

    updateOption(key, value) {
      this.$emit('update', this.type, key, value);
    },
    selectAll(value) {
      // Emit select-all event with the value (true for enable all, false for disable all)
      this.$emit('select-all', value);
    },
    toggleAll(value) {
      this.$emit('select-all', value);
    },
    formatPlatformName(key) {
      const platformNames = {
        google: 'Google Business Profile',
        airbnb: 'Airbnb',
        yelp: 'Yelp',
        tripadvisor: 'Tripadvisor',
        amazon: 'Amazon',
        aliexpress: 'AliExpress',
        'booking.com': 'Booking.com',
        facebook: 'Facebook Reviews',
        woocommerce: 'WooCommerce',
        trustpilot: 'Trustpilot',
        instagram: 'Instagram',
        youtube: 'YouTube',
        twitter: 'X (Twitter)',
        tiktok: 'TikTok',
        facebook_feed: 'Facebook',
        allinone: 'All-in-One Chat Widget',
      };

      return platformNames[key] || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
    }
  }
};
</script>