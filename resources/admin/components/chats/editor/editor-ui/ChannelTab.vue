<template>
  <div class="wpsr-social-channels-tab">
    <div class="wpsr-option-selector-popup-header">
        <span>
            <ManageIcon />
            <span class="wpsr-label-small wpsr-heading-text">
                {{ $t('Manage Channels') }}
            </span>
        </span>
    </div>


    <OptionSelectorPopup 
      :title="edit_channel && selected_channel ? $t('Edit') + ' ' + selected_channel.displayName + ' '+ $t('Connection') : (selected_channel?.displayName || '') + ' ' + $t('Connection')"
      :visible="channelForm"
      @update:visible="channelForm = $event"
    >
      <template #content>
          <div class="wpsr-social-channel-form">
            <div class="wpsr-form-element">
              <p class="wpsr-input-label wpsr-label-small">{{ selected_channel.title }}</p>
              <p v-if="selected_channel.name === 'fluent_forms' && !has_fluent_form"><strong>Notice:</strong> Before you use this feature you need to install the Fluent Forms plugin first.</p>
              <el-input
                v-model="selected_channel.credential"
                :placeholder="selected_channel.placeholder"
                class="wpsr-text-input wpsr-social-channel-credential-input"
              />
              <div class="wpsr-input-notes-wrapper" v-if="selected_channel.description">
                <InfoIcon />
                <p class="wpsr-label-mini wpsr-input-notes wpsr-heading-text">
                  {{ selected_channel.description }}</p>
              </div>
            </div>
            <div class="wpsr-form-element">
              <p class="wpsr-input-label wpsr-label-small">{{ $t("Label") }}</p>
              <el-input
                v-model="selected_channel.label"
                :placeholder="selected_channel.name"
                class="wpsr-text-input wpsr-social-channel-credential-input"
              />
            </div>

            <el-button class="wpsr_primary_light_btn wpsr_btn_fullwidth" @click="saveChannel">
              {{ edit_channel ? $t('Update') : $t('Save') }}
            </el-button>
          </div>
      </template>
    </OptionSelectorPopup>


    <div class="wpsr-social-connected-channel-accounts"
         :class="channels && channels.length > 4 ? 'wpsr-enable-scrollbar' : ''">

        <div class="wpsr-draggable-channels">
            <div
                v-for="(channel, index) in chat_config.channels"
                :key="channel.id"
                class="wpsr-social-connected-channel-account-info"
                :class="channel.name"
                draggable="true"
                @dragstart="onDragStart(index)"
                @dragover.prevent="onDragOver(index)"
                @drop="onDrop(index)"
                @dragend="onDragEnd"
                :style="{ opacity: dragIndex === index ? 0.5 : 1 }"
            >
                <div class="wpsr-channel-icon">
                    <img v-if="channel.name !== 'fluent_forms'" :src="assets_url+'/images/svg/'+channel.name+'.svg'" alt="">
                    <img v-if="channel.name === 'fluent_forms'" :src="assets_url+'/images/svg/fluent_forms_official.svg'" alt="">
                </div>
                <div class="wpsr-channel-credentials">
                    <span v-if="channel.displayName" class="wpsr-channel-name wpsr-label-small wpsr-heading-text" >{{ channel.displayName }}</span>
                    <span v-else class="wpsr-channel-name wpsr-label-small wpsr-heading-text">{{ channel.label }}</span>
                    <span class="wpsr-channel-credential wpsr-paragraph-extra-small">
                      {{ channel.credential && channel.credential.trim() !== '' ? channel.credential : $t("No credentials present.") }}
                    </span>
                </div>
                <div class="wpsr-actions">
                    <span class="wpsr-action-btn-wrapper">
                      <el-button class="wpsr_mini wpsr_light_edit wpsr_default_btn wpsr_icon_btn" @click.stop="() => editChannel(channel)">
                        <EditPenIcon />
                      </el-button>
                    </span>
                    <span class="wpsr-action-btn-wrapper">
                      <Remove @confirm="removeChannel(channel)" platform="chats"/>
                    </span>
                </div>
            </div>

            <OptionSelectorPopup 
              :title="$t('All Channels')"
              :visible="channelModal"
              @update:visible="channelModal = $event"
            >
              <template #trigger>
                <el-button class="wpsr_primary_light_btn wpsr_btn_fullwidth" @click="channelModal = true">
                  <PlusIcon fill="#5525D9" stroke="#5525D9"/>
                  {{ $t("Add New Channel") }}
                </el-button>
              </template>
              <template #content>
                <AllChatChannels 
                  :channels="allChannels"
                  :selected-channels="[]"
                  :search="search"
                  :saving="false"
                  :has-pro="has_pro"
                  :channel-columns="2"
                  @update:search="search = $event"
                  @toggle-channel="selectChannel"
                />
              </template>
            </OptionSelectorPopup>
        </div>
    </div>
  </div>
</template>

<script>
import EditPenIcon from "../../../pieces/icons/EditPenIcon.vue";
import InfoIcon from "../../../pieces/icons/InfoIcon.vue";
import TrashcanIcon from "../../../pieces/icons/TrashcanIcon.vue";
import ManageIcon from "../../../pieces/icons/ManageIcon.vue";
import AllChatChannels from "../../../views/platforms/chats/AllChatChannels.vue";
import OptionSelectorPopup from "../../../core-ui/editor/optionSelectorPopup.vue";
import PlusIcon from "../../../pieces/icons/PlusIcon.vue";
import Remove from "../../../core-ui/editor/RemoveConfirm.vue";
import { watch } from "vue";

export default {
  name: "ChannelTab",

  props: {
    channels: {
      type: Array,
      required: true
    },
    assets_url: {
      type: String,
      required: true
    },
    chat_config:{
      type: Object,
      required: true
    },
    pre_selected_channels:{
      type: Array,
      required: false,
      default: []
    }
  },
  components: {
    EditPenIcon,
    InfoIcon,
    TrashcanIcon,
    ManageIcon,
    AllChatChannels,
    OptionSelectorPopup,
    PlusIcon,
    Remove
  },
  data() {
    return {
      channelModal: false,
      channelForm: false,
      selected_channel: null,
      edit_channel: false,
      channel_index: null,
      has_pro: typeof this.appVars !== 'undefined' ? !!this.appVars.has_pro : false,
      has_fluent_form: typeof this.appVars !== 'undefined' ? !!this.appVars.has_fluent_form : false,
      dragIndex: null,
      dropIndex: null,
      search: '',
    };
  },
  methods: {
    addNewChannel() {
      this.channelModal = !this.channelModal;
    },
    selectChannel(channel){
      if ((!this.has_pro && channel.name !== 'messenger' && channel.name !== 'whatsapp')) {
        this.handlePro();
        return;
      }
      this.channelForm = true;
      this.channelModal = false;
      this.selected_channel = JSON.parse(JSON.stringify(channel));
      this.edit_channel = false;
      this.channel_index = null;
    },
    editChannel(channel) {
      this.channelForm = true;
      this.channelModal = false;
      this.selected_channel = JSON.parse(JSON.stringify(channel));
      this.edit_channel = true;
      this.channel_index = channel.id;
    },
    saveChannel() {
        if (this.selected_channel && this.selected_channel.credential) {
            if (this.selected_channel.name === 'skype') {
                let credential = 'skype:' + this.selected_channel.credential + '?chat';
                this.selected_channel.credential = credential;
            }

            if (this.selected_channel.name === 'slack') {
                let is_url = this.isUrl(this.selected_channel.credential);
                let credential = is_url ? this.selected_channel.credential : 'https://' + this.selected_channel.credential + '.slack.com/';
                this.selected_channel.credential = credential;
            }

            if (this.edit_channel && this.channel_index !== null) {
                this.chat_config.channels[this.channel_index] = this.selected_channel;
            } else {
                this.chat_config.channels.push(this.selected_channel);
            }

            this.saveChannelData();
            this.channelForm = false;
            this.edit_channel = false;
            this.channel_index = null;
        } else {
            let message = this.$t('Please filled the field correctly!!');
            this.handleError(message);
        }
    },
    saveChannelData() {
      this.setDataIndex();
      const updatedConfig = {
        ...this.chat_config,
        channels: this.chat_config.channels
      };
      this.$emit('update:chat_config', updatedConfig);
    },
    setDataIndex(){
      this.chat_config.channels = this.chat_config.channels.map((channel, index) => ({
        ...channel,
        id: index,
      }));
    },
    removeChannel(data) {
      const index = data.id;
      this.chat_config.channels.splice(index, 1);
      this.saveChannelData();
      this.setDataIndex();
    },
    cancelForm() {
      this.channelForm = false;
      this.edit_channel = false;
      this.selected_channel = null;
      this.channel_index = null;
    },
    openDeletePopup(index) {
      this.$set(this.deletePopupVisible, index, true);
    },
    fillUpPreSelectedChannels() {
        if (this.pre_selected_channels && this.pre_selected_channels.length > 0) {

            let channel = this.allChannels.find(c => c.name === this.pre_selected_channels[0]);
            this.chat_config.channels.push({ ...channel });

            this.setDataIndex();
            const updatedConfig = {
                ...this.chat_config,
                channels: this.chat_config.channels
            };
            this.$emit('update:chat_config', updatedConfig);
            this.chat_config.channels.length > 0 && this.editChannel(this.chat_config.channels[0]);
        }
    },
    // --- Drag & Drop methods ---
    onDragStart(index) {
      this.dragIndex = index;
    },
    onDragOver(index) {
      this.dropIndex = index;
    },
    onDrop(index) {
      if (this.dragIndex === null || this.dragIndex === index) return;
      const moved = this.chat_config.channels.splice(this.dragIndex, 1)[0];
      this.chat_config.channels.splice(index, 0, moved);
      this.setDataIndex();
      this.saveChannelData();
      this.dragIndex = null;
      this.dropIndex = null;
    },
    onDragEnd() {
      this.dragIndex = null;
      this.dropIndex = null;
    },
  },
  watch:{
    'chat_config.channels': {
        handler(newVal, oldVal) {
        },
        deep: true
    },
  },
  mounted() {
    this.allChannels = this.appVars.chat_channels;
    this.setDataIndex();
    this.fillUpPreSelectedChannels();
  }
};
</script>
