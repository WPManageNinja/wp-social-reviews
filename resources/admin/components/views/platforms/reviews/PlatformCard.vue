<template>
  <div :class="['wpsr-platform', { 'wpsr-platform--connection': connectionMode }, platform.platform === 'google' ? 'wpsr-platform-google' : '']"
       :style="connectionMode ? connectionCardStyle : {}">
    <div v-if="connectionMode" class="wpsr-platform-connection-row" @click="socialSettingModal">
      <img :src="isDarkMode && platform.dark_mode_image ?  platform.dark_mode_image : platform.image" class="image wpsr-platform-connection-icon">
      <span class="wpsr-label-mini wpsr-platform-connection-title">
        {{ platform.platform_title }}
        <span v-if="isPlatformEnabled" class="wpsr-platform-connection-enabled">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 14C4.6862 14 2 11.3138 2 8C2 4.6862 4.6862 2 8 2C11.3138 2 14 4.6862 14 8C14 11.3138 11.3138 14 8 14ZM7.4018 10.4L11.6438 6.1574L10.7954 5.309L7.4018 8.7032L5.7044 7.0058L4.856 7.8542L7.4018 10.4Z" fill="#23A682"/>
          </svg>
        </span>
        <span v-if="!has_pro && platform.platform !== 'google' && platform.platform !== 'airbnb'" class="wpsr-platform-connection-pro">
          <ProCrownIcon />
        </span>
      </span>
      <span class="wpsr-platform-connection-gear" @click.stop="socialSettingModal">
        <el-icon class="el-icon-setting"><Setting /></el-icon>
      </span>
    </div>
    <template v-else>
      <div class="wpsr-platform-body">
        <div effect="dark" content="Set Your Configuration" title="Set Your Configuration" :hide-after="1000" placement="top"></div>
        <div class="wpsr-platform-info" @click="socialSettingModal">
          <img :class="{ 'wpsr-dark-mode-image': isDarkModeActive }" :src="platformImage" class="image">
          <div class="wpsr-platform-status-wrapper">
            <div v-if="isPlatformEnabled" class="wpsr-platform-status-tag">
              <el-icon><Check/></el-icon>
            </div>
            <div v-if="!isPlatformEnabled && (has_pro || platform.platform === 'google' || platform.platform === 'airbnb')" class="wpsr-platform-status-tag">
              <el-icon class="icon-ban"></el-icon>
            </div>
            <div v-if="!has_pro && platform.platform !== 'google' && platform.platform !== 'airbnb'">
              <ProCrownIcon/>
            </div>
          </div>
        </div>
      </div>
      <div class="wpsr-platform-footer">
        <h3 class="wpsr-platform-name">{{ platform.platform_title }}</h3>
        <div class="wpsr-platform-icon">
          <el-icon class="el-icon-setting" @click="socialSettingModal"><Setting /></el-icon>
        </div>
      </div>
    </template>
  </div>
</template>

<script type="text/babel">

import {DarkModeMixin} from '../../../../mixins/DarkModeMixin';
import ProCrownIcon from "../../../pieces/icons/ProCrownIcon.vue";
export default {
  name: 'PlatformCard',
  components: {
   ProCrownIcon
  },
  mixins: [DarkModeMixin],
  props: [
      'platform',
      'is_enabled_platform',
      'connectionMode',
      'isFirst',
      'isLast',
      'isDarkModeActive',
  ],
  computed: {
    platformImage() {
      if (this.isDarkModeActive && this.platform.dark_mode_image) {
        return this.platform.dark_mode_image;
      }
      return this.platform.image;
    },
    isPlatformEnabled() {
      if (typeof this.is_enabled_platform === 'boolean') {
        return this.is_enabled_platform;
      }
      if (typeof this.is_enabled_platform === 'object' && this.is_enabled_platform !== null) {
        return this.is_enabled_platform[this.platform.platform];
      }
      return false;
    },
    connectionCardStyle() {
      let style = {
        'border-radius': '0',
        'border-bottom': 'var(--wpsr-primary-border, #F9FAFB)',
        'background': 'var(--wpsr-primary-bg, #F9FAFB)',
        'display': 'flex',
        'align-items': 'center',
        'cursor': 'pointer',
        'transition': 'background 0.2s',
      };
      if (this.isFirst) style['border-top-left-radius'] = style['border-top-right-radius'] = '16px';
      if (this.isLast) {
        style['border-bottom'] = 'none';
        style['border-bottom-left-radius'] = style['border-bottom-right-radius'] = '16px';
      }
      return style;
    }
  },
  methods: {
    socialSettingModal(){
      this.$emit('social-setting-modal');
    }
  }
}
</script>
