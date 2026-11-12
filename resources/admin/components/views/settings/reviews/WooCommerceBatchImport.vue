<template>
  <div class="wpsr-woo-import-section">

    <div class="wpsr-settings-header">
      <h3 class="wpsr-reviews-settings-title">Synchronize Product Reviews</h3>
    </div>

    <div class="wpsr-setting-row-no-border">
      <div class="wpsr-setting-left">
        Keep your WooCommerce products reviews up-to-date in WP Social Ninja. Synchronization runs smoothly in the background and may take a few moments if you have many reviews.
      </div>
      <div class="wpsr-setting-right">
        <el-button
            class="wpsr_primary_btn"
            :loading="importLoading"
            @click="triggerWooImport"
            type="primary"
            size="small"
            :disabled="importProgress && importProgress.total > 0 && !importProgress.completed"
        >
          {{ importProgressInterval ? 'Syncing...' : 'Start Sync' }}
        </el-button>

        <el-button
            v-if="importProgress && !importProgress.completed && !importProgress.failed"
            class="wpsr_primary_btn"
            @click="restartWooImport"
            type="warning"
            size="small"
        >
          Restart Sync
        </el-button>
      </div>
    </div>
    
    <div class="wpsr-ml-20 wpsr-mr-20 wpsr-pb-5">
      <p class="wpsr-import-description wpsr-pb-20">{{ $t('Need Help?') }}<a href="https://wpsocialninja.com/docs/collect-woocommerce-custom-reviews-with-fluent-forms/" target="_blank"> Check Our WooCommerce Guide</a>.</p>
    
      <div class="wpsr-import-status wpsr-mb-10" v-if="importLoading || importProgressInterval">
        <el-icon><Loading /></el-icon>
        <div class="wpsr-import-status-text">
          <p>
            Product reviews are syncing… Please wait, this may take a little time.
          </p>
        </div>
      </div>


      <div class="wpsr-import-status wpsr-mb-15" v-if="(importProgress && importProgress.completed) && !importLoading">
        <el-icon><CircleCheck /></el-icon>
        <div class="wpsr-import-status-text">
          <h3>Sync Complete</h3>
          <span>Last sync on {{importProgress.updated_at}}</span>
          <p>
            Synchronized {{numberWithCommas(importProgress.total_success_count || 0)}} new reviews<span v-if="importProgress.total_skipped_count"> ({{numberWithCommas(importProgress.total_skipped_count)}} already up to date)</span>
          </p>
        </div>
      </div>
    </div>
  
  </div>
</template>

<script type="text/babel">
export default {
  name: "WooCommerceBatchImport",
  props: {
    importLoading: {
      type: Boolean,
      default: false
    },
    showImportStartedMessage: {
      type: Boolean,
      default: false
    },
    importStartedMessage: {
      type: String,
      default: 'Import has been started. You can monitor the progress below.'
    },
    shouldShowProgress: {
      type: Boolean,
      default: false
    },
    importProgress: {
      type: Object,
      default: null
    },
    importProgressInterval: {
      type: Number,
      default: null
    }
  },
  methods: {
    triggerWooImport() {
      this.$emit('trigger-import');
    },
    
    checkImportProgress() {
      this.$emit('check-progress');
    },

    restartWooImport() {
      this.$emit('restart-import');
    },

    formatTime(seconds) {
      if (seconds < 60) {
        return `${Math.round(seconds)}s`;
      } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.round(seconds % 60);
        return `${minutes}m ${remainingSeconds}s`;
      } else {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
      }
    }
  }
}
</script>
