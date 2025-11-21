<template>
  <div class="wpsr-editor-error-notices-wrapper" v-if="Object.keys(errorNotice).length">
    <div class="wpsr-editor-error-notices-total-log">
      <div class="wpsr-notices-total-log">
        <span class="wpsr-notices-dot-icon"></span>
        {{ totalErrorCount }} {{ $t('Error Logs') }}
      </div>
      <el-button @click="showDialog = !showDialog" class="wpsr_default_btn wpsr-label-small">{{ $t('See All') }}</el-button>
    </div>
    <el-dialog
        v-model="showDialog"
        width="50%"
        class="wpsr-modal wpsr-connection-modal"
    >
      <template #title>
        <span>{{ $t('Error Logs') }} <strong>{{ totalErrorCount }}</strong></span>
      </template>
      
      <div class="wpsr-error-dialog-content" :class="totalErrorCount >= 3 ? 'wpsr-scrollbar' : ''">
        <div class="wpsr-editor-error-notices">
          <div class="wpsr-editor-error-notice wpsr-editor-error-notice-body" v-for="(notice, index) in allNotices" :key="index">
            <el-icon><Warning /></el-icon>
            <div class="wpsr-editor-error-notice-body-inner">
              <h3 class="wpsr-notice-main-title wpsr-heading-text" v-if="notice.main_title">{{notice.main_title}}</h3>
              <h4 class="wpsr-notice-title" v-if="notice.error_title">
                {{notice.error_title}}
                <span v-if="notice.sub_title">{{notice.sub_title}}</span>
              </h4>
              <p class="wpsr-notice-message" v-if="notice.error_message">{{notice.error_message}}<a v-if="notice.direction_url" target="_blank" :href="notice.direction_url">{{notice.direction_url_text}}</a></p>
              <p class="wpsr-notice-url-text-without-url" v-if="!notice.direction_url">{{notice.direction_url_text}}</p>
            </div>
          </div>
        </div>
      </div>
      
      <template #footer>
        <div class="wpsr-d-flex wpsr-jc-end">
            <RemoveConfirm
                @confirm="resetErrorLog"
                :message="$t('Would you like to clear all the errors stored in the error log?')"
                :submit_button_type="'text'"
                :button_text="$t('Reset Error Logs')"
                :displayRefreshLeft=true
            ></RemoveConfirm>
        </div>
      </template>
    </el-dialog>
    

    <div class="wpsr-editor-error-notices" :class="totalErrorCount >= 1 ? 'has-multiple-notices' : ''">
      <div class="wpsr-editor-error-notice">
        <div v-if="firstNotice" class="wpsr-editor-error-notice-body">
          <el-icon><Warning /></el-icon>
          <div class="wpsr-editor-error-notice-body-inner">
            <h3 class="wpsr-notice-main-title wpsr-heading-text" v-if="firstNotice.main_title">{{ firstNotice.main_title }}</h3>
            <h4 class="wpsr-notice-title" v-if="firstNotice.error_title">
              {{ firstNotice.error_title }}
              <span v-if="firstNotice.sub_title">{{ firstNotice.sub_title }}</span>
            </h4>
            <p class="wpsr-notice-message" v-if="firstNotice.error_message">
              {{ firstNotice.error_message }}
              <a v-if="firstNotice.direction_url" target="_blank" :href="firstNotice.direction_url">{{ firstNotice.direction_url_text }}</a>
            </p>
            <p class="wpsr-notice-url-text-without-url" v-if="!firstNotice.direction_url">{{ firstNotice.direction_url_text }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">

import RemoveConfirm from "../core-ui/editor/RemoveConfirm.vue";

export default {
  name: 'ErrorNotice',
  components: {RemoveConfirm},
  props: ['errorNotice'],
  emits: ['update:errorNotice'],
  data() {
    return {
      showDialog: false,
    }
  },
  methods:{
    openDialog() {
      this.showDialog = true
    },
    resetErrorLog(){
      this.$del('settings/reset-error-log').then(response => {
        if (response) {
          this.handleSuccess(response.message);
          this.$emit('update:errorNotice', {});
          this.showDialog = false;
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    },
  },
  computed: {
    allNotices() {
      // Flatten all notices from all platforms into a single array
      const notices = [];
      Object.values(this.errorNotice).forEach(platform => {
        if (platform && typeof platform === 'object') {
          Object.values(platform).forEach(notice => {
            notices.push(notice);
          });
        }
      });
      return notices;
    },
    firstNotice() {
      // Get the first notice from the flattened array
      return this.allNotices[0] || null;
    },
    totalErrorCount() {
      return this.allNotices.length;
    }
  }
}
</script>