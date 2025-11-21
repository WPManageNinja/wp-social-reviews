<template>
  <div class="wpsr-tab-connected-channel-accounts" :class="{'wpsr-enable-scrollbar': isScrollable && !isEditable}" v-loading="loading">
    <li
        v-if="channels && channel.name"
        v-for="(channel, key) in channels"
        :key="key"
        class="wpsr-tab-connected-channel-account-info"
        style="display: block"
        :class="[channel.name, isEditable && editIndex === key ? 'active' : '']"
    >
      <div class="wpsr-channel-credentials">
        <div class="wpsr-channel-icon">
          <img :src="getPlatformIcon(channel.name)" alt="">
        </div>
        <div class="wpsr-channel-credentials-lebel" @click="editChannel(key)">
          <span class="wpsr-channel-name wpsr-label-small wpsr-heading-text" v-if="channel.label">{{ channel.label }}</span>
          <span v-if="channel.template" class="wpsr-channel-credential">{{ channel.template.label }}</span>
        </div>
        <div class="wpsr-actions wpsr-jc-center">
          <remove @on-confirm="removeChannel(key)"></remove>
        </div>
      </div>

      <div v-if="(editIndex === key && isEditable) && !openPlatformModal" class="wpsr-social-channels-popup-wrapper">
        <social-wall-channel-modal
            :platforms="platforms"
            :selectedPlatform="selectedPlatform"
            :socialWallTemplates="socialWallTemplates"
            :editPlatformTemplate="editPlatformTemplate"
            @selectWallChannel="selectWallChannel"
            @save="saveChannel"
            @close="closeModal"
        />
      </div>
    </li>
  </div>
</template>

<script>
import Remove from '../core-ui/editor/RemoveConfirm';
import SocialWallChannelModal from '../pieces/SocialWallChannelModal';

export default {
  name: "SocialWallChannelList",
  components: {
    Remove,
    SocialWallChannelModal
  },
  data() {
    return {
      editIndex: null,
      editOpenPlatformModal: false,
      isEditable: false,
    }
  },
  props: [ 'channels', 'loading', 'assets_url', 'openPlatformModal', 'platforms', 'selectedPlatform', 'socialWallTemplates', 'editPlatformTemplate' ],
  methods: {
    editChannel(key) {
      if(this.editIndex !== key) {
        this.editIndex = key;
        this.editOpenPlatformModal = true;
        this.isEditable = true;
      } else {
        this.editOpenPlatformModal = false;
        this.editIndex = null;
        this.isEditable = false;
      }

      this.$emit('edit', {index: key, editOpenPlatformModal: this.editOpenPlatformModal});
    },
    removeChannel(key) {
      this.$emit('remove', key);
    },
    saveChannel() {
      this.$emit('save', this.selectedPlatform);
    },
    closeModal() {
      this.isEditable = false;
      this.editIndex = null;
      this.$emit('closeModal');
    },
    selectWallChannel(key) {
      this.$emit('selectWallChannel', key);
    },
    getPlatformIcon(platform) {
      platform = platform === 'facebook_feed' ? 'facebook-small' : platform;
      return this.assets_url + '/images/icon/icon-' + platform + '.png';
    }
  },
  computed: {
    isScrollable() {
      return this.channels && this.channels.length > 6;
    },
  },
  watch: {
    openPlatformModal(isOpenModal) {
      if(isOpenModal){
        this.isEditable = false;
        this.editIndex = null;
      }
    }
  },
  mounted() {

  },
}
</script>