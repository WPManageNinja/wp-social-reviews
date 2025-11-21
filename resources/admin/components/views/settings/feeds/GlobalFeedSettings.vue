<template>
  <div>
    <!-- Loading Skeleton -->
    <div v-if="loading" class="wpsr-general-settings-wrapper">
      <div class="wpsr-settings-container">
        <WpsrSkeleton :rows="10" />
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="!loading && advance_settings">
        <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
          <h2 class="wpsr-gs-header-title">{{ $t('Global Feed Settings') }}</h2>
          <el-button
              @click="saveAdvanceSettings"
              type="success"
              size="default"
              class="wpsr_primary_btn"
          >
            {{ $t('Save Settings')}}
          </el-button>
        </div>
  
        <div class="wpsr-general-settings-wrapper">
          <div class="wpsr-settings-container">
            <div class="wpsr-settings-header">
              <h2 class="wpsr-feed-settings-title">{{ $t('Feed Issue Email Reports') }}</h2>
              <p class="wpsr-feed-settings-description">
                If the <strong>Instagram</strong> and <strong>Facebook</strong> feeds are down due to a critical issue, we'll seamlessly switch to a cached version and notify you based on these settings. <a href="https://wpsocialninja.com/docs/instagram-feed-issue-email-report-social-feeds-wp-social-ninja/" target="_blank">Read documentation</a>.
              </p>
            </div>
            <div class="wpsr-feed-settings-content">
              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t('Enable Email Reports') }}</h3>
                  <p class="wpsr-setting-description">{{ $t('If the feed is down due to a critical issue, we will notify you based on these settings.') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-switch
                        v-model="advance_settings.email_report.status"
                        active-color="#5c8df6"
                        inactive-color="#b7b7b9"
                        active-value="true"
                        inactive-value="false"
                    >
                  </el-switch>
                </div>
              </div>
              <div v-if="advance_settings.email_report.status === 'true'" class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t('Deliver Reports Every') }}</h3>
                  <p class="wpsr-setting-description">{{ $t('Pick any day for your weekly email summary') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-select v-model="advance_settings.email_report.sending_day" class="wpsr-text-input wpsr-select" :placeholder="$t('Select')" size="default">
                    <el-option
                        v-for="(sendDay, dayKey) in sending_days"
                        :key="dayKey"
                        :label="sendDay"
                        :value="dayKey">
                    </el-option>
                  </el-select>
                </div>
              </div>
              <div v-if="advance_settings.email_report.status === 'true'" class="wpsr-setting-row-no-border">
                <div class="wpsr-setting-left">
                    <h3 class="wpsr-setting-title">{{ $t('Recipients') }}</h3>
                    <p class="wpsr-setting-description">{{ $t('Please specify who will receive the email reports.') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-input
                      class="wpsr-text-input wpsr-input-border"
                      :placeholder="$t('Recipient Email Address')"
                      v-model="advance_settings.email_report.recipients">
                  </el-input>
                </div>
              </div>
              <p class="wpsr-ml-20 wpsr-pb-20"><strong>Note:</strong> Currently, this feature is available for <strong>Instagram</strong> and <strong>Facebook</strong> feeds. More platforms rolling out near future.</p>
            </div>
          </div>
          <el-button class="wpsr_primary_btn" type="success" size="default" @click="saveAdvanceSettings">Save Settings</el-button>
        </div>
    </div>
  </div>
</template>
  
  <script>
  import RemoveConfirm from '../../../core-ui/editor/RemoveConfirm';
  import { InfoFilled } from '@element-plus/icons-vue';
  export default {
    name: "GlobalFeedSettings",
    components: {
      RemoveConfirm,
      InfoFilled
    },
    data() {
      return {
        loading: false,
        advance_settings: null,
        gdpr_options: [
          {
            label: 'No',
            value: 'false'
          },
          {
            label: 'Yes',
            value: 'true'
          },
        ],
        optimize_image_format_options: [
          {
            label: 'JPG',
            value: 'jpg'
          },
          {
            label: 'WebP',
            value: 'webp'
          },
        ],
        sending_days:{
          Mon: 'Monday',
          Tue: 'Tuesday',
          Wed: 'Wednesday',
          Thu: 'Thursday',
          Fri: 'Friday',
          Sat: 'Saturday',
          Sun: 'Sunday'
        },
        available_ai_platforms:[],
        open_ai_supported_models: [],
        open_router_supported_models: [],
        deepseek_supported_models: [],
      }
    },
  
    methods: {
      resetData() {
        this.$del('settings/reset-images', {
          platform: 'reviews',
        }).then(response => {
          if (response) {
            this.handleSuccess(response.message);
          }
        }).catch((errors) => {
          this.handleError(errors);
        }).always(() => {
  
        });
      },
      saveAdvanceSettings() {
        this.loading = true;
        this.$post('settings/advance-settings', {
          advance_settings: this.advance_settings,
        }).then(response => {
          if (response) {
            this.handleSuccess(response.message);
            this.getAdvanceSettings();
          }
        }).catch(error => {
          this.handleError(error);
        }).always(() => {
          this.loading = false;
        });
      },
      getAdvanceSettings() {
        this.loading = true;
        this.$get('settings/advance-settings')
            .then(response => {
              if (response) {
                this.advance_settings = response.advance_settings;
              }
            }).catch(error => {
              this.handleError(error);
            }).always(() => {
              this.loading = false;
            });
      },
    },
  
    mounted() {
      this.getAdvanceSettings();
    }
  }
  </script>