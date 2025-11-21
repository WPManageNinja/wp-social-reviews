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
        <i class="el-icon-loading"></i>
        <div class="wpsr-import-status-text">
          <p>
            Product reviews are syncing… Please wait, this may take a little time.
          </p>
        </div>
      </div>


      <div class="wpsr-import-status wpsr-mb-15" v-if="(importProgress && importProgress.completed) && !importLoading">
        <i class="el-icon-circle-check"></i>
        <div class="wpsr-import-status-text">
          <h3>Sync Complete</h3>
          <span>Last sync on {{importProgress.updated_at}}</span>
          <p>Successfully synchronized {{numberWithCommas(importProgress.total)}} reviews</p>
        </div>
      </div>
    </div>
  
  </div>


    

<!--    <div v-if="importProgressInterval" class="wpsr-import-progress wpsr-mt-15">-->
<!--      <div class="wpsr-progress-header" v-if="importProgress">-->
<!--        <div class="wpsr-progress-header-label">-->
<!--          <span>Product Reviews</span>-->
<!--          <span>{{ importProgress.percentage }}% complete</span>-->
<!--        </div>-->
<!--        <div>-->
<!--          <el-progress-->
<!--            :percentage="importProgress.percentage"-->
<!--            :show-text="false"-->
<!--          />-->
<!--        </div>-->

<!--        &lt;!&ndash; Enhanced progress details &ndash;&gt;-->
<!--        <div class="wpsr-progress-details">-->
<!--          <p class="wpsr-progress-stats">-->
<!--            <span v-if="showImportStartedMessage">{{ importStartedMessage }}</span> {{ numberWithCommas(importProgress.processed) }} of {{ numberWithCommas(importProgress.total) }} WooCommerce reviews processed.-->
<!--            <span v-if="importProgress.speed > 0" class="wpsr-speed">-->
<!--              ({{ importProgress.speed }}/sec)-->
<!--            </span>-->
<!--          </p>-->

<!--          <p v-if="importProgress.eta && !importProgress.completed" class="wpsr-eta">-->
<!--            Estimated time remaining: {{ formatTime(importProgress.eta) }}-->
<!--          </p>-->
<!--        </div>-->
<!--      </div>-->
<!--    </div>-->
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
    /**
     * Trigger WooCommerce reviews import
     */
    triggerWooImport() {
      this.$emit('trigger-import');
    },
    
    /**
     * Check import progress
     */
    checkImportProgress() {
      this.$emit('check-progress');
    },

    /**
     * Restart WooCommerce reviews import
     */
    restartWooImport() {
      this.$emit('restart-import');
    },

    /**
     * Format time in seconds to human readable format
     */
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
