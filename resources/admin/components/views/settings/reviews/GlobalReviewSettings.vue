<template>
  <div>
    <!-- Loading Skeleton -->
    <div v-if="loading" class="wpsr-general-settings-wrapper">
      <div class="wpsr-settings-container">
        <WpsrSkeleton :rows="12" />
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="!loading && advance_settings">
      <div
        class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center"
      >
        <h2 class="wpsr-gs-header-title">{{ $t("Global Review Settings") }}</h2>
        <el-button
          @click="saveAdvanceSettings"
          type="success"
          size="default"
          class="wpsr_primary_btn"
        >
          {{ $t("Save Settings") }}
        </el-button>
      </div>

      <div class="wpsr-general-settings-wrapper">
        <div class="wpsr-settings-container wpsr-feed-global-settings-wrapper">
          <div class="wpsr-settings-header">
            <h2 class="wpsr-feed-settings-title">
              {{ $t("Reviews Image Optimization (Recommended)") }}
            </h2>
            <p class="wpsr-feed-settings-description">
              {{ $t("Configure the settings for your reviews image optimization.") }}
            </p>
          </div>
          <div class="wpsr-feed-settings-content">
            <div class="wpsr-feed-setting-border wpsr-pb-20">
              <div class="wpsr-setting-row-no-border">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t("Optimize Images") }}</h3>
                  <p class="wpsr-setting-description">
                    {{
                      $t(
                        'Enable this option to generate and optimized images in multiple sizes using your WordPress local storage for improved performance.'
                      )
                    }}
                  </p>
                </div>
                <div class="wpsr-setting-right">
                <el-switch
                    v-model="advance_settings.review_optimized_images"
                    active-color="#5c8df6"
                    inactive-color="#b7b7b9"
                    active-value="true"
                    inactive-value="false"
                    >
                  </el-switch>
                </div>
              </div>

              <div class="wpsr-ml-20">
                  <p class="wpsr-pt-10 wpsr-mb-0">
                    <strong>{{ $t('Note: ') }}</strong>
                    <span v-html="$t('To configure <strong>GDPR Compliance</strong> and <strong>Image Format</strong> settings, go to ')"></span>
                    <router-link to="/settings/advance-settings" class="wpsr-gdpr-link">{{ $t('Advanced Settings') }}</router-link>
                  </p>
              </div>
            </div>
            

            <div class="wpsr-setting-row">
              <div class="wpsr-setting-left">
                <h3 class="wpsr-setting-title">
                  {{ $t("Reset Local Images ") }}
                </h3>
                <p class="wpsr-setting-description">
                  {{
                    $t(
                      'This will clear all images stored locally for the Reviews platform.'
                    )
                  }}
                </p>
              </div>
              <div class="wpsr-setting-right">
                <RemoveConfirm
                    @confirm="resetData"
                    message="Would you like to delete all the local images associated with this platform?"
                    submit_button_type="text"
                    :displayRefreshLeft="'trash'"
                    button_text="Reset Image Storage"
                ></RemoveConfirm>
              </div>
            </div>
          </div>
        </div>

        <div class="wpsr-settings-container wpsr-feed-global-settings-wrapper">
          <div class="wpsr-settings-header">
            <h2 class="wpsr-feed-settings-title">
              {{ $t("Review Publishing Settings") }}
            </h2>
            <p class="wpsr-feed-settings-description">
              {{ $t("Choose how and when reviews are published on your site.") }}
            </p>
          </div>

          <div class="wpsr-feed-settings-content">
            <div class="wpsr-setting-row">
              <div class="wpsr-setting-left">
                <h3 class="wpsr-setting-title">
                  {{ $t("Review Publishing Mode") }}
                  <el-tooltip placement="right" effect="dark">
                    <template #content>
                      <div class="wpsn-tooltip-content">
                        <p><strong>Publish Automatically</strong>: Reviews are published immediately without review.</p>
                        <p><strong>Require Approval</strong>: All reviews go to the moderation queue for manual approval.</p>
                        <p><strong>Conditional</strong>: Apply rules to decide if reviews are published automatically or sent to moderation.</p>
                      </div>
                    </template>
                    <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                  </el-tooltip>
                </h3>
              </div>
              <div class="wpsr-setting-right">
                <el-select
                    v-model="advance_settings.review_publish_mode"
                    class="wpsr-text-input"
                    placeholder="Select"
                >
                  <el-option
                      v-for="item in reviewsPublishTypeOptions"
                      :key="item.value"
                      :label="item.label + (item.is_pro && !has_pro ? ' (Pro)' : '')"
                      :value="item.value"
                      :disabled="item.is_pro && !has_pro"
                  >
                  </el-option>
                </el-select>
              </div>
            </div>

            <div v-if="advance_settings.review_publish_mode === 'conditional'">
              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">
                    {{ $t("Conditional Publishing Rules") }}
                  </h3>
                  <p class="wpsr-feed-settings-description">Automatically decide which reviews are published and which ones require moderation based on your rules.</p>
                </div>
              </div>

              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">
                    {{ $t("Minimum Rating Required") }}
                  </h3>
                  <p class="wpsr-text-muted">{{ $t('Reviews with a rating below this value will be sent to moderation instead of being published automatically.') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-select v-model="advance_settings.conditional_rules.min_rating" class="wpsr-text-input" placeholder="Select minimum rating" size="mini">
                    <el-option
                        v-for="rating in ratingOptions"
                        :key="rating.value"
                        :label="rating.label"
                        :value="rating.value"
                    >
                    </el-option>
                  </el-select>
                </div>
              </div>

              <!-- Blocked Keywords Rule -->
              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">
                    {{ $t("Blocked/Spam Words") }}
                  </h3>
                  <p class="wpsr-text-muted">{{ $t('Add keywords that should trigger spam detection. Reviews containing any of these words will be marked as spam automatically.') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-input
                      class="wpsr-text-input wpsr-input-border"
                      v-model="advance_settings.conditional_rules.blocked_keywords"
                      type="textarea"
                      :rows="3"
                      :placeholder="$t('Enter keywords separated by commas (e.g. scam, fake, spam)')"
                      size="mini"
                  />
                </div>
              </div>

              <!-- Verified Purchase Rule -->
<!--              <div class="wpsr-setting-row">-->
<!--                <div class="wpsr-setting-left">-->
<!--                  <h3 class="wpsr-setting-title">-->
<!--                    {{ $t("Verified Purchase Required") }}-->
<!--                  </h3>-->
<!--                  <p class="wpsr-text-muted">{{ $t('Only publish reviews from verified purchases automatically.') }}</p>-->
<!--                </div>-->
<!--                <div class="wpsr-setting-right">-->
<!--                  <el-switch-->
<!--                      v-model="advance_settings.conditional_rules.require_verified_purchase"-->
<!--                      active-color="#5c8df6"-->
<!--                      inactive-color="#b7b7b9"-->
<!--                      active-value="true"-->
<!--                      inactive-value="false"-->
<!--                  >-->
<!--                  </el-switch>-->
<!--                </div>-->
<!--              </div>-->

              <!-- Minimum Review Length Rule -->
              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">
                    {{ $t("Minimum Review Length") }}
                  </h3>
                  <p class="wpsr-text-muted">{{ $t('Set the minimum number of characters required. Shorter reviews will be sent for moderation.') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-input-number
                      v-model="advance_settings.conditional_rules.min_review_length"
                      :min="0"
                      :max="1000"
                      :step="10"
                      size="mini"
                      placeholder=""
                  />
                  <span class="wpsr-ml-10">{{ $t('characters') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <NativeFormCaptchaSettings v-if="has_pro && canManageCaptchaSettings" />

        <div
          class="wpsr-settings-container" 
          id="wpsr-ai-review-summarizer-api-settings"
        >
          <div class="wpsr-settings-header">
            <h2 class="wpsr-feed-settings-title">
              {{ $t("AI Review Summarizer API settings") }}
            </h2>
            <p class="wpsr-feed-settings-description">
              {{
                $t(
                  "To use the AI Review Summarizer feature, you need to have an API key from whichever AI platform you plan to use."
                )
              }}
            </p>
          </div>

          <div class="wpsr-feed-settings-content">

            <div class="wpsr-setting-row">
                            <div class="wpsr-setting-left">
                <h3 class="wpsr-setting-title">{{ $t("Enable AI Review Summarizer") }}</h3>
                <p class="wpsr-setting-description">
                  {{
                    $t(
                      'Enable this option to use AI-powered review summarization feature. When disabled, the AI settings will be hidden but your configuration will be preserved.'
                    )
                  }}
                </p>
              </div>
              <div class="wpsr-setting-right">
                <el-switch
                    v-model="advance_settings.ai_review_summarizer_enabled"
                    active-color="#5c8df6"
                    inactive-color="#b7b7b9"
                    active-value="true"
                    inactive-value="false"
                    @change="onAISummarizerToggle"
                    >
                </el-switch>
              </div>
            </div>

            <div 
              v-if="advance_settings.ai_review_summarizer_enabled === 'true'" 
              class="wpsr-ai-configuration">
              <div class="wpsr-setting-row">
                  <div class="wpsr-setting-left">
                    <h3 class="wpsr-setting-title">{{ $t("AI Platform") }}</h3>
                    <p class="wpsr-setting-description">
                      {{
                        $t(
                          'Select the AI platform'
                        )
                      }}
                  </p>
                </div>
                <div class="wpsr-setting-right">
                  <el-select v-model="advance_settings.ai_platform" class="wpsr-text-input" @change="aiProviderChanged" placeholder="Select">
                    <el-option
                      v-for="(platform, platformKey) in available_ai_platforms"
                      :key="platformKey"
                      :label="platform"
                      :value="platformKey"
                      >
                    </el-option>
                  </el-select>
                </div>
              </div>

              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">
                    {{ $t("Model") }}
                  </h3>
                  <p class="wpsr-setting-description">
                    {{
                      $t(
                        'Select an AI model you want to use.'
                      )
                    }}
                  </p>
                </div>
                <div class="wpsr-setting-right">
                  <el-select v-if="advance_settings.ai_platform === 'Deepseek'" class="wpsr-text-input" v-model="advance_settings.selected_model" clearable placeholder="Select">
                      <el-option
                          v-for="(model, modelKey) in deepseek_supported_models "
                          :key="modelKey"
                          :label="model"
                          :value="modelKey">
                      </el-option>
                    </el-select>

                    <el-select v-if="advance_settings.ai_platform === 'OpenRouter'" class="wpsr-text-input" v-model="advance_settings.selected_model" clearable placeholder="Select">
                      <el-option
                          v-for="(model, modelKey) in open_router_supported_models"
                          :key="modelKey"
                          :label="model"
                          :value="modelKey">
                      </el-option>
                    </el-select>

                    <el-select v-if="advance_settings.ai_platform === 'OpenAI'" class="wpsr-text-input" v-model="advance_settings.selected_model" clearable placeholder="Select">
                      <el-option
                          v-for="(model, modelKey) in open_ai_supported_models"
                          :key="modelKey"
                          :label="model"
                          :value="modelKey">
                      </el-option>
                    </el-select>
                </div>
              </div>

              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t("API key") }}</h3>
                  <p class="wpsr-setting-description">
                    {{
                      $t(
                        'API key provided by your selected platform.'
                      )
                    }}
                  </p>
                </div>
                <div class="wpsr-setting-right">
                  <el-input class="wpsr-text-input wpsr-input-border" v-model="advance_settings.ai_api_key" type="password" placeholder="API Key" show-password/>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import RemoveConfirm from "../../../core-ui/editor/RemoveConfirm";
import NativeFormCaptchaSettings from "./NativeFormCaptchaSettings.vue";
import { InfoFilled } from "@element-plus/icons-vue";
import { settingsMixin } from "../../../../mixins/settingsMixin";

export default {
  name: "GlobalReviewSettings",
  mixins: [settingsMixin],
  components: {
    RemoveConfirm,
    NativeFormCaptchaSettings,
    InfoFilled,
  },
  data() {
    return {
      loading: false,
      advance_settings: null,
      gdpr_options: [
        {
          label: "No",
          value: "false",
        },
        {
          label: "Yes",
          value: "true",
        },
      ],
      optimize_image_format_options: [
        {
          label: "JPG",
          value: "jpg",
        },
        {
          label: "WebP",
          value: "webp",
        },
      ],
      available_ai_platforms: [],
      open_ai_supported_models: [],
      open_router_supported_models: [],
      deepseek_supported_models: [],
      reviewsPublishTypeOptions: [
        { value: 'auto', label: 'Publish Automatically' },
        { value: 'manually', label: 'Require Approval' },
        { value: 'conditional', label: 'Auto Publish with Filters', is_pro: true },
      ],
      ratingOptions: [
        { value: 1, label: 'At least 1 stars' },
        { value: 2, label: 'At least 2 stars' },
        { value: 3, label: 'At least 3 stars' },
        { value: 4, label: 'At least 4 stars' },
        { value: 5, label: '5 Stars Only' },
      ],
    };
  },
  computed: {
    canManageCaptchaSettings() {
      return this.hasPermission(['wpsn_reviews_platforms_settings', 'wpsn_full_access']);
    }
  },

  methods: {
    resetData() {
      this.$del("settings/reset-images", {
        platform: "reviews",
      })
        .then((response) => {
          if (response) {
            this.handleSuccess(response.message);
          }
        })
        .catch((errors) => {
          this.handleError(errors);
        })
        .always(() => {});
    },
    saveAdvanceSettings() {
      this.loading = true;
      this.$post("settings/advance-settings", {
        advance_settings: this.advance_settings,
      })
        .then((response) => {          
          if (response) {
            this.handleSuccess(response.message);
            this.getAdvanceSettings();
          }
        })
        .catch((error) => {
          this.handleError(error);
        })
        .always(() => {
          this.loading = false;
        });
    },
    getAdvanceSettings() {
      this.loading = true;
      this.$get("settings/advance-settings")
        .then((response) => {
          if (response) {
            this.advance_settings = response.advance_settings;
            // Reset conditional (Auto Publish with Filters) to auto when not Pro
            if (!this.has_pro && this.advance_settings?.review_publish_mode === 'conditional') {
              this.advance_settings.review_publish_mode = 'auto';
            }
            this.available_ai_platforms =
              response.ai_summarizer_settings_options.available_ai_platforms;
            this.open_ai_supported_models =
              response.ai_summarizer_settings_options.open_ai_supported_models;
            this.open_router_supported_models =
              response.ai_summarizer_settings_options.open_router_supported_models;
            this.deepseek_supported_models =
              response.ai_summarizer_settings_options.deepseek_supported_models;
          }
        })
        .catch((error) => {
          this.handleError(error);
        })
        .always(() => {
          this.loading = false;
        });
    },
    aiProviderChanged(value) {
      this.advance_settings.selected_model = null;
      this.advance_settings.ai_api_key = null;
    },
    onAISummarizerToggle(value) {
      // If AI summarizer is disabled, show a warning for existing users with configurations
      if (value === false || value === 'false') {
        const hasExistingConfig = this.advance_settings.ai_platform || 
                                  this.advance_settings.selected_model || 
                                  this.advance_settings.ai_api_key;
        
        // if (hasExistingConfig) {
        // }
        // If no existing config, no need for confirmation
      }
    },
  },

  mounted() {
    this.getAdvanceSettings();
  },
};
</script>
