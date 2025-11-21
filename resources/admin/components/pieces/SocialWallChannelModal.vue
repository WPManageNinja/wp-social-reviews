<template>
  <div class="wpsr-repeater-fields-popup">

    <div class="wpsr-social-channels-bg"></div>
    <div class="wpsr-social-channel-form-wrapper">
      <h3 class="wpsr-channel-label">Select a Platform</h3>
      <el-select v-model="selectedPlatform.name" filterable placeholder="Select Platform" size="large" @change="selectWallChannel(selectedPlatform)">
        <el-option
            v-for="(channel, key) in platforms"
            :key="key"
            :label="channel.label"
            :value="channel.name"
            :selectWallChannel="() => selectWallChannel(channel)"
        />
      </el-select>
      <div class="wpsr-social-channel-form wpsr-mt-20">
        <h3 class="wpsr-channel-label">Select a Feed Template</h3>
        <span>
          <router-link
              v-if="selectedPlatform.template"
              target="_blank"
              :to="{ name: 'edit-'+this.selectedPlatform.name.replace('_', '-')+'-template', params: { template_id: this.selectedPlatform.template.value } }"
          >
          {{ $t('Edit Template') }}
        </router-link>
        </span>
        <el-select class="wpsr-mb-20" v-model="selectedPlatform.template" filterable placeholder="Select template" size="large">
          <el-option
              v-for="template in socialWallTemplates"
              :key="template.value"
              :label="template.label"
              :value="template"
          />
        </el-select>
        <h3 class="wpsr-channel-label">Platform Name:</h3>
        <el-input
            v-model="selectedPlatform.label"
            type="text"
            size="default"
            :placeholder="selectedPlatform.label"
            @keyup.enter.native="saveChannel"
        />
      </div>
      <div class="wpsr-social-channel-save" v-if="selectedPlatform">
        <el-button class="wpsr-channels-modal-cancel" @click="closeModal">{{ $t('Cancel') }}</el-button>
        <el-button
            :disabled="isSaveButtonDisabled"
            type="normal"
            size="small"
            @click="saveChannel"
        >
          {{ editPlatformTemplate ? $t('Update') : $t('Save') }}
        </el-button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "SocialWallChannelModal",
  props: ['selectedPlatform', 'modalVisible', 'socialWallTemplates', 'editPlatformTemplate', 'platforms', 'openPlatformModal'],
  computed: {
    isSaveButtonDisabled() {
      return !this.selectedPlatform.template || this.selectedPlatform.template.value === 0;
    }
  },
  methods: {
    closeModal() {
      this.$emit('close');
    },
    saveChannel() {
      this.$emit('save', this.selectedPlatform);
    },
    selectWallChannel(key) {
      this.$emit('selectWallChannel', key);
    },
  }
}
</script>