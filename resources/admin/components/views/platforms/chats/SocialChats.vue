<template>
  <el-col :span="6">
    <div
      class="wpsr-platform wpsr-platform-social-chat"
      @click="openChannelDialog"
      tabindex="0"
      @keydown.enter.space="openChannelDialog"
      role="button"
      aria-pressed="showChatChannelDialog"
    >
      <div class="wpsr-platform-info">
        <span class="wpsr-platform-icon wpsr-platform-info-upper-section">
          <img :src="assets_url+'/images/icon/icon-allinone-small.png'" alt="">
        </span>
        <span class="wpsr-platform-info-bottom-section">
          <span class="wpsr-text-section">
            <h3 class="wpsr-platform-title wpsr-label-small wpsr-heading-text">{{ $t('All-in-One Chat Widget') }}</h3>
            <p class="wpsr-platform-description wpsr-paragraph-small wpsr-body-regular-color">{{ $t('Connect with your visitors through multiple chat widgets.') }}</p>
          </span>
          <el-button
            @click.stop="openChannelDialog"
            class="wpsr_default_btn wpsr-label-small"
          >
            {{ $t('Choose Channel') }}
          </el-button>
        </span>
      </div>
    </div>

    <el-dialog
      v-model="showChatChannelDialog"
      width="50%"
      class="wpsr-modal wpsr-connection-modal"
    >
      <template #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">{{ $t('Choose a channel') }}</h4>
        </div>
      </template>
      <AllChatChannels
        :key="dialogKey"
        :channels="channels"
        :selectedChannel="selectedChannel"
        :search="search"
        :saving="saving"
        @update:search="search = $event"
        @toggle-channel="toggleChannel"
        @add-new-chat-widget="addNewChatWidget"
      />
      <template #footer>
        <span class="dialog-footer">
          <el-button
            class="wpsr_default_btn"
            @click="closeChannelDialog"
          >
            {{ $t("Cancel") }}
          </el-button>
          <el-button
            class="wpsr_primary_btn"
            v-loading="saving"
            :disabled="saving || !selectedChannel || !selectedChannel.name"
            @click="addNewChatWidget()"
          >
            {{ $t("Create a Chat Widget") }}
          </el-button>
        </span>
      </template>
    </el-dialog>
  </el-col>
</template>

<script>
import {tableMixin} from "../../../../mixins/tableMixin";
import { Search, StarFilled, CircleCheckFilled } from '@element-plus/icons-vue'
import AllChatChannels from './AllChatChannels.vue'
import ProCrownIcon from '../../../pieces/icons/ProCrownIcon.vue'
export default {
  name: 'SocialChats',
  mixins: [tableMixin],
  components: {
    ProCrownIcon,
    AllChatChannels
  },
  data() {
    return {
      showChatChannelDialog: false,
      search: '',
      selectedChannel: null,
      platform: 'chat',
      endpoint: 'chat-widgets',
      channels: [
      ],
      dialogKey: 0,
      saving: false
    }
  },
  computed: {
    filteredChannels() {
      if (!this.search) return this.channels
      return this.channels.filter(channel =>
        channel.name.toLowerCase().includes(this.search.toLowerCase())
      )
    }
  },
  methods: {
    openChannelDialog() {
      this.selectedChannel = null
      this.search = ''
      this.dialogKey += 1
      this.showChatChannelDialog = true
    },
    closeChannelDialog() {
      this.showChatChannelDialog = false
      this.selectedChannel = null
    },
    selectChannel(channel) {
      // Handle channel selection
      this.closeChannelDialog()
      this.$emit('channel-selected', channel)
    },
    isSelected(channel) {
      return this.selectedChannels.some(c => c.id === channel.id)
    },
    toggleChannel(channel) {
      if(!this.has_pro && channel.pro) {
        this.handleError({
          message: this.$t('You need to upgrade to Pro to use this channel.'),
          type: 'warning'
        });
        return;
      }

      this.selectedChannel = channel;
      
    },
    addNewChatWidget(){
      // Collect selected channel IDs
      const selectedChannelIds = this.selectedChannel.name;
      // Call addNewItem from tableMixin, passing platform and selected channels
      this.addNewItem({
        platform: this.platform,
        channels: selectedChannelIds
      });
    }
  },
  mounted: function (){
    this.channels = this.appVars.chat_channels;
  }
}
</script>
