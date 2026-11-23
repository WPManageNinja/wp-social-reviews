<template>
  <div v-if="hasPermission(['wpsn_full_access'])">
    <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
      <h2 class="wpsr-gs-header-title">{{ $t('Data Cleanup') }}</h2>
    </div>

    <div class="wpsr-general-settings-wrapper">
      <div class="wpsr-settings-container">
        <div class="wpsr-setting-section wpsr-mb-24">
          <div class="wpsr-settings-header">
            <h3 class="wpsr-feed-settings-title">{{ $t('Manage Data') }}</h3>
          </div>
          <div class="wpsr-feed-settings-content">
            <div class="wpsr-setting-row">
              <div class="wpsr-setting-left">
                <h3 class="wpsr-setting-title">{{ $t('Delete all Platform Data?') }}</h3>
                <p class="wpsr-setting-description">
                  {{ $t('Pressing this button will permanently erase all data from Facebook, Instagram and other platforms including connected accounts, optimized images, and cached posts.') }}
                </p>
              </div>
              <div class="wpsr-setting-right">
                <RemoveConfirm
                  @confirm="deletePlatformData"
                  :message="$t('Would you like to delete all the Facebook, Instagram and other platforms data including all connected accounts, optimize images, cached posts?')"
                  submit_button_type="text"
                  :displayRefreshLeft="'trash'"
                  :button_text="$t('Delete All')"
                ></RemoveConfirm>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import RemoveConfirm from '../../../core-ui/editor/RemoveConfirm';

export default {
  name: 'DataCleanup',
  components: {
    RemoveConfirm
  },
  methods: {
    deletePlatformData() {
      this.$del('settings/delete-all-data')
        .then(response => {
          if (response) {
            this.handleSuccess(response.message);
          }
        })
        .catch(error => this.handleError(error));
    }
  }
};
</script>
