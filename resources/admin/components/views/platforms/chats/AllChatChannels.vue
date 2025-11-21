<template>
  <div class="wpsr-all-chat-channels">
    <el-input
      v-model="searchProxy"
      placeholder="Search channels"
      clearable
      class="wpsr-text-input wpsr-search-social-channels"
    >
      <template #prefix>
        <el-icon class="el-input__icon"><search /></el-icon>
      </template>
    </el-input>
    <div class="wpsr-channel-grid wpsr-scrollbar wpsr-channels-flex-grid" :style="gridStyle">
      <div
        v-for="channel in filteredChannels"
        :key="channel.id"
        class="wpsr-social-channel-card wpsr-channel-item"
        :style="itemStyle"
        @click="selectChannel(channel)"
      >
        <div class="wpsr-social-channel-card-inner"
          :class="{ 'wpsr-channel-selected': isSelected(channel) }">
          <div :class="['wpsr-channel-image-wrapper', channel.name]">
            <img
              :src="channel.icon"
              :alt="channel.name"
              :class="['wpsr-social-channel-icon']"
            />
          </div>
          <el-icon
            v-if="isSelected(channel)"
            class="wpsr-channel-selected-icon"
          >
            <CircleCheckFilled style="width: 16px; height: 16px;" />
          </el-icon>
          <span v-else-if="channel.pro && !has_pro" class="wpsr-pro-crown-icon">
            <ProCrownIcon />
          </span>
          <div class="wpsr-channel-info">
            <span class="wpsr-channel-title wpsr-label-small wpsr-heading-text">{{ channel.displayName }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Search, CircleCheckFilled } from '@element-plus/icons-vue'
import ProCrownIcon from '../../../pieces/icons/ProCrownIcon.vue'

export default {
  name: 'AllChatChannels',
  components: {
    ProCrownIcon,
    Search,
    CircleCheckFilled
  },
  props: {
    channels: {
      type: Array,
      required: true
    },
    saving: {
      type: Boolean,
      default: false
    },
    channelColumns: {
      type: Number,
      default: 4
    }

  },
  data() {
    return {
      searchProxy: this.search,
      selectedChannel: null
    }
  },
  computed: {
    filteredChannels() {
      if (!this.searchProxy) return this.channels
      return this.channels.filter(channel =>
        channel.name.toLowerCase().includes(this.searchProxy.toLowerCase())
      )
    },
    gridStyle() {
      return {
        gap: '12px'
      }
    },
    itemStyle() {
      const columns = this.channelColumns || 4
      const gapSize = 12
      const totalGaps = (columns - 1) * gapSize
      const itemWidth = `calc(${100 / columns}% - ${totalGaps / columns}px)`
      
      return {
        flex: `0 0 ${itemWidth}`,
        marginRight: '0',
        marginBottom: '0'
      }
    }
  },
  watch: {
    search(val) {
      this.searchProxy = val
    }
  },
  methods: {
    isSelected(channel) {
      return channel.name === this.selectedChannel?.name;
    },
    selectChannel(channel){

      if (!this.has_pro && channel.pro) {
        this.$emit('toggle-channel', channel)
        return;
      }
      this.selectedChannel = channel;
      this.$emit('toggle-channel', channel)
    }
  }
}
</script>
