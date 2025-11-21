<template>
  <div class="wpsr-advance-settings-main">
    <div
      v-if="
        hasPermission(['wpsn_reviews_platforms_settings', 'wpsn_full_access'])
      "
    >
      <!-- Loading Skeleton -->
      <div v-if="loading" class="wpsr-general-settings-wrapper">
        <div class="wpsr-settings-container">
          <WpsrSkeleton :rows="12" />
        </div>
      </div>

      <!-- Content -->
      <div
        v-else-if="!loading"
        class="wpsr-advance-settings-inner"
      >
        <div v-if="
          has_pro ||
            (!has_pro &&
              (reviewPlatform === 'google' || reviewPlatform === 'airbnb'))
        ">
        <div
          class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center"
        >
          <h2 class="wpsr-gs-header-title">{{ platformTitle }}</h2>
          <el-button
            v-if="allGlobalSettings"
            @click.prevent="updateSettings"
            type="success"
            size="default"
            class="wpsr_primary_btn"
          >
            {{ $t("Save Settings") }}
          </el-button>
        </div>

        <div class="wpsr-general-settings-wrapper">
          <div v-if="allGlobalSettings">
            <div class="wpsr-settings-container wpsr-mb-20">
              <div class="wpsr-settings-header">
                <h2 class="wpsr-feed-settings-title">
                  Synchronization
                </h2>
              </div>
              <div class="wpsr-feed-settings-content">
                <div v-if="reviewPlatform !== 'fluent-forms'" class="wpsr-setting-row">
                  <div class="wpsr-setting-left">
                    <h3 class="wpsr-setting-title">Auto Synchronization</h3>
                    <p class="wpsr-setting-description">Automatically sync reviews from the platform to your website.</p>
                  </div>
                  <div class="wpsr-setting-right">
                    <el-switch
                      v-model="allGlobalSettings.global_settings.auto_syncing"
                      active-color="#5c8df6"
                      inactive-color="#b7b7b9"
                      active-value="true"
                      inactive-value="false"
                    >
                    </el-switch>
                  </div>
                </div>
                <div v-if="reviewPlatform === 'fluent-forms'" class="wpsr-setting-row">
                  <div class="wpsr-setting-left">
                    <h3 class="wpsr-setting-title">Review/Testimonial must be manually approved</h3>
                    <p class="wpsr-setting-description">If you enable this, then Fluent Forms reviews/testimonial need approval before a review/testimonial appears on your website.</p>
                  </div>

                  <div class="wpsr-setting-right">
                    <el-switch
                      v-model="
                        allGlobalSettings.global_settings.manually_review_approved
                      "
                      active-color="#5c8df6"
                      inactive-color="#b7b7b9"
                      active-value="true"
                      inactive-value="false"
                    >
                    </el-switch>
                  </div>
                </div>
                <div class="wpsr-setting-row" v-if="allGlobalSettings.global_settings.auto_syncing === 'true' && reviewPlatform !== 'fluent-forms'">
                  <div class="wpsr-setting-left">
                    <h3 class="wpsr-setting-title">Check for New Reviews Every</h3>
                    <p class="wpsr-setting-description">Set the frequency for checking for new reviews from the platform.</p>
                  </div>
                  <div class="wpsr-setting-right">
                    <el-select
                      v-model="validExpirationValue"
                      @change="updateExpirationValue"
                      class="wpsr-text-input wpsr-select"
                      placeholder="Select"
                      size="large"
                    >
                      <el-option
                        v-for="item in filterDisplayOptions(cache_times)"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                      >
                      </el-option>
                    </el-select>
                  </div>
                </div>
              </div>
            </div>

            <div
              class="wpsr-settings-container"
              v-if="reviewPlatform === 'woocommerce'"
            >
              <div class="wpsr-setting-section wpsr-mb-20">
                <!-- WooCommerce Batch Import Section -->
                <WooCommerceBatchImport
                  :import-loading="importLoading"
                  :import-progress="importProgress"
                  :show-import-started-message="showImportStartedMessage"
                  :import-started-message="importStartedMessage"
                  :import-progress-interval="importProgressInterval"
                  :should-show-progress="shouldShowProgress"
                  @trigger-import="triggerWooImport"
                  @restart-import="restartWooImport"
                  @check-progress="checkImportProgress"
                />
              </div>
            </div>
            <div
              class="wpsr-settings-container"
              v-if="reviewPlatform === 'woocommerce'"
            >
              <div class="wpsr-mb-20">
                <!-- WooCommerce specific settings -->
                <div>
                  <div class="wpsr-settings-header">
                    <h2 class="wpsr-feed-settings-title">
                      {{ $t("Customize WooCommerce Reviews Layout") }}
                    </h2>
                  </div>
                  <div class="wpsr-feed-settings-content">
                    <div class="wpsr-setting-row">
                      <div class="wpsr-setting-left">
                        <h3 class="wpsr-setting-title">
                          Make WP Social Ninja Your Main Review Hub
                        </h3>
                        <span class="wpsr-setting-description"
                          >Use WP Social Ninja as the primary interface for
                          managing all reviews</span
                        >
                      </div>
                      <div class="wpsr-setting-right">
                        <el-switch
                          v-model="
                            allGlobalSettings.global_settings
                              .use_social_ninja_primary
                          "
                          active-color="#5c8df6"
                          inactive-color="#b7b7b9"
                          active-value="yes"
                          inactive-value="no"
                        >
                        </el-switch>
                      </div>
                    </div>

                    <span v-if="allGlobalSettings.global_settings.use_social_ninja_primary === 'yes'">
                      <div class="wpsr-setting-row">
                        <div class="wpsr-setting-left">
                          <h3 class="wpsr-setting-title">
                            Hide the Review Total
                          </h3>
                          <span class="wpsr-setting-description"
                            >Hide total number of reviews in WooCommerce product
                            tabs</span
                          >
                        </div>
                        <div class="wpsr-setting-right">
                          <el-switch
                            v-model="
                              allGlobalSettings.global_settings.hide_reviews_count
                            "
                            active-color="#5c8df6"
                            inactive-color="#b7b7b9"
                            active-value="yes"
                            inactive-value="no"
                          >
                          </el-switch>
                        </div>
                      </div>

                      <div class="wpsr-setting-row">
                        <div class="wpsr-setting-left">
                          <h3 class="wpsr-setting-title">Hide Ratings from Product Listings</h3>
                          <span class="wpsr-setting-description">Hide the rating count from your WooCommerce listed product.</span>
                        </div>
                        <div class="wpsr-setting-right">
                          <el-switch
                            v-model="allGlobalSettings.global_settings.hide_product_list_rating_count"
                            active-color="#5c8df6"
                            inactive-color="#b7b7b9"
                            active-value="yes"
                            inactive-value="no"
                          >
                          </el-switch>
                        </div>
                      </div>
                    
                      <div class="wpsr-setting-row">
                        <div class="wpsr-setting-left">
                          <h3 class="wpsr-setting-title">Hide Review Headline</h3>
                          <span class="wpsr-setting-description"
                            >Hide the default WooCommerce review title/headline at
                            the top of the reviews widget.</span
                          >
                        </div>
                        <div class="wpsr-setting-right">
                          <el-switch
                            v-model="
                              allGlobalSettings.global_settings.hide_reviews_title
                            "
                            active-color="#5c8df6"
                            inactive-color="#b7b7b9"
                            active-value="yes"
                            inactive-value="no"
                          >
                          </el-switch>
                        </div>
                      </div>

                      <div class="wpsr-setting-row">
                        <div class="wpsr-setting-left">
                          <h3 class="wpsr-setting-title">Display Location</h3>
                          <span class="wpsr-setting-description"
                            >Choose where to display the review widget on your
                            product page.</span
                          >
                        </div>
                        <div class="wpsr-setting-right">
                          <el-select
                            class="wpsr-text-input"
                            v-model="
                              allGlobalSettings.global_settings
                                .reviews_widgets_placement
                            "
                            placeholder="Select placement"
                            size="small"
                            clearable
                            filterable
                          >
                            <el-option
                              label="Display Inside Reviews Tab"
                              value="display_inside_reviews_tab"
                            >
                            </el-option>
                            <el-option
                              label="Display Outside Tabs"
                              value="display_outside_tabs"
                            >
                            </el-option>
                          </el-select>
                        </div>
                      </div>

                      <div class="wpsr-setting-row">
                        <div class="wpsr-setting-left">
                          <h3 class="wpsr-setting-title">Review Form Style</h3>
                          <span class="wpsr-setting-description"
                            >Select the visual style for review forms and
                            displays.</span
                          >
                        </div>
                        <div class="wpsr-setting-right">
                          <el-select
                            class="wpsr-text-input"
                            v-model="
                              allGlobalSettings.global_settings.reviews_form
                            "
                            placeholder="Select form type"
                            size="small"
                            clearable
                            filterable
                          >
                            <el-option
                              label="Default WooCommerce form"
                              value="woocommerce"
                            >
                            </el-option>
                            <el-option
                              label="Inherit from WP Social Ninja"
                              value="social_ninja"
                            >
                            </el-option>
                          </el-select>
                        </div>
                      </div>

                      <div class="wpsr-setting-row">
                        <div class="wpsr-setting-left">
                          <h3 class="wpsr-setting-title">Review Template</h3>
                          <span class="wpsr-setting-description"
                            >Choose the template for displaying reviews.</span
                          >
                        </div>
                        <div class="wpsr-setting-right">
                          <el-select
                            class="wpsr-text-input"
                            v-model="
                              allGlobalSettings.global_settings.selected_template
                            "
                            placeholder="Select template"
                            size="small"
                            filterable
                            clearable
                          >
                            <el-option
                              v-for="template in availableTemplates"
                              :key="template.value"
                              :label="template.label"
                              :value="template.value"
                            >
                            </el-option>
                          </el-select>
                        </div>
                      </div>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wpsr-general-settings-wrapper" v-if="!allGlobalSettings">
            <div class="wpsr-setting-content-area">
              <div class="wpsr-connection-upgrade-to-pro">
                <div class="wpsr-connection-upgrade-message wpsr-align-center">
                  <h2 class="wpsr-integration-title">{{ $t('Platform Configuration') }}</h2>
                  <div class="wpsr-integration-message">
                    <p>{{ $t(`To access ${reviewPlatform.charAt(0).toUpperCase() + reviewPlatform.slice(1)} settings, please configure your connection first`)}}</p>
                    <router-link to="/" class="wpsr-upgrade-btn wpsr-extend-btn">{{ $t('Configure Connection') }}</router-link>
                  </div>
                </div>
            </div>
            </div>
          </div>

          <el-button
            v-if="allGlobalSettings"
            @click.prevent="updateSettings"
            size="default"
            class="wpsr_primary_btn"
          >
            {{ $t("Save Settings") }}
          </el-button>
        </div>
        </div>
        <div v-else-if="!loading && !(has_pro || (!has_pro && (reviewPlatform === 'google' || reviewPlatform === 'airbnb')))">
          <div class="wpsr-general-settings-wrapper">
            <div class="wpsr-settings-container">
              <div class="wpsr-setting-content-area">
                <div class="wpsr-connection-upgrade-to-pro">
                  <h2>
                    {{
                      $t(`Please configure your ${reviewPlatform} connection first`)
                    }}
                  </h2>
                  <p class="pro_dialog">
                    Upgrade to WP Social Ninja Pro and unlock all the features.
                  </p>
                  <UpgradeToProButton classes="wpsr_buy_pro_btn_inline" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <PermissionsNotification
      v-if="
        !hasPermission(['wpsn_reviews_platforms_settings', 'wpsn_full_access'])
      "
    />
  </div>
</template>
<script type="text/babel">
import { settingsMixin } from "../../../../mixins/settingsMixin";
import UpgradeToProButton from "../../../views/advertise/UpgradeToProButton";
import PermissionsNotification from "../../advertise/PermissionsNotification";
import WooCommerceBatchImport from "./WooCommerceBatchImport";

export default {
  name: "ReviewSettings",
  mixins: [settingsMixin],
  components: {
    UpgradeToProButton,
    PermissionsNotification,
    WooCommerceBatchImport,
  },
  data() {
    return {
      loading: false,
      allGlobalSettings: false,
      reviewPlatform: "",
      platformTitle: "Settings",
      availableTemplates: [],
      shouldShowProgress: false,
      showImportStartedMessage: false,
      importStartedMessage: null,
      importLoading: false,
      importProgress: null,
      importProgressInterval: null,
      cache_times: [
        {
          value: 60 * 60,
          label: this.$t("1 Hour"),
          hide: false,
        },
        {
          value: 60 * 60 * 6,
          label: this.$t("6 Hour"),
          hide: false,
        },
        {
          value: 86400,
          label: this.$t("1 Day"),
          hide: false,
        },
        {
          value: 172800,
          label: this.$t("2 Days"),
          hide: false,
        },
        {
          value: 259200,
          label: this.$t("3 Days"),
          hide: false,
        },
        {
          value: 604800,
          label: this.$t("1 Week"),
          hide: true,
        },
        {
          value: 1209600,
          label: this.$t("2 Weeks"),
          hide: true,
        },
        {
          value: 2592000,
          label: this.$t("1 Month"),
          hide: true,
        },
        {
          value: 31536000,
          label: this.$t("1 Year"),
          hide: true,
        },
      ],
    };
  },
  computed: {
    validExpirationValue() {
      if (!this.allGlobalSettings || !this.allGlobalSettings.global_settings) {
        return null;
      }

      const currentValue = this.allGlobalSettings.global_settings.expiration;
      const availableOptions = this.filterDisplayOptions(this.cache_times);

      // Check if current value is in the available options
      const isValidOption = availableOptions.some(option => option.value === currentValue);

      if (isValidOption) {
        return currentValue;
      }

      // If current value is not valid for this platform, return the first available option
      return availableOptions.length > 0 ? availableOptions[0].value : null;
    }
  },
  methods: {
    updateExpirationValue(value) {
      if (this.allGlobalSettings && this.allGlobalSettings.global_settings) {
        this.allGlobalSettings.global_settings.expiration = value;
      }
    },
    filterDisplayOptions(items) {
      const platform = this.reviewPlatform;

      return items.filter((item) => {
        if (platform === "tripadvisor") {
          // Hide 1 Hour and 6 Hours options for this platform
          if (
            item.value === 60 * 60 ||
            item.value === 60 * 60 * 6
          ) {
            return false;
          }
        }

        return true;
      });
    },
    getSettings(platform) {
      if (
        this.has_pro ||
        (!this.has_pro && (platform === "google" || platform === "airbnb"))
      ) {
        platform = platform === "fluent-forms" ? "fluent_forms" : platform;
        this.get(platform);
      }
    },
    updateSettings() {
      // Validation for WooCommerce - check if template is selected when using Social Ninja as primary
      if (
        this.reviewPlatform === "woocommerce" &&
        this.allGlobalSettings.global_settings.use_social_ninja_primary ===
          "yes" &&
        !this.allGlobalSettings.global_settings.selected_template
      ) {
        this.handleError("Please select a template.");
        return;
      }

      let platform = this.reviewPlatform;
      platform = platform === "fluent-forms" ? "fluent_forms" : platform;
      this.update(platform, this.allGlobalSettings);
    },

    /**
     * Trigger WooCommerce reviews import
     */
    async triggerWooImport() {
      this.importLoading = true;

      try {
        const response = await this.$post(
          "pro/settings/woocommerce/import-reviews"
        );

        if (response && response.success) {
          this.showImportStartedMessage = true;
          this.shouldShowProgress = true;
          this.importStartedMessage = response.message;
          // Start checking progress
          this.startProgressCheck();
        } else if (response.failure_reason) {
          this.handleError(response.failure_reason);
        } else {
          this.handleError(
            response.message || "Failed to start sync. Please try again."
          );
        }
      } catch (error) {
        this.handleError("Failed to start sync. Please try again.");
      } finally {
        this.importLoading = false;
      }
    },

    /**
     * Check import progress
     */
    async checkImportProgress(onPageLoad = false) {
      if (!this.has_pro) return;

      try {
        const response = await this.$get(
          "pro/settings/woocommerce/import-progress"
        );

        if (response && response.success) {
          this.importProgress = response.data;

          if (!this.importProgress.completed) {
            this.shouldShowProgress = true;
          }

          // If import is completed or failed, stop the interval
          if (this.importProgress.completed || this.importProgress.failed) {
            clearInterval(this.importProgressInterval);
            this.importProgressInterval = null;
            if (!onPageLoad) {
              if (this.importProgress.completed) {
                this.handleSuccess(
                  response.message || "Import completed successfully."
                );
              } else if (this.importProgress.failed) {
                this.handleError(
                  this.importProgress.failure_reason || "Import failed."
                );
              }
            }
          }
        } else {
          console.warn("Failed to get import progress:", response.message);
        }
      } catch (error) {
        console.error("Progress check error:", error);
      }
    },

    /**
     * Start checking progress periodically
     */
    startProgressCheck() {
      if (this.importProgressInterval) {
        return;
      }
      // Check progress immediately
      this.checkImportProgress();

      // Use shorter interval for more real-time updates
      this.importProgressInterval = setInterval(() => {
        this.checkImportProgress();
      }, 3000); // Changed from 3000ms to 1000ms
    },

    /**
     * Stop progress checking
     */
    stopProgressCheck() {
      if (this.importProgressInterval) {
        clearInterval(this.importProgressInterval);
        this.importProgressInterval = null;
      }
    },

    /**
     * Restart WooCommerce reviews import
     */
    async restartWooImport() {
      try {
        const response = await this.$post(
          "pro/settings/woocommerce/restart-import-reviews"
        );

        if (response && response.success) {
          this.showImportStartedMessage = true;
          this.shouldShowProgress = true;
          this.importStartedMessage = response.message;
          // Reset progress and start checking
          this.importProgress = null;
          this.startProgressCheck();
        } else {
          this.handleError(
            response.message || "Failed to restart sync. Please try again."
          );
        }
      } catch (error) {
        this.handleError("Failed to restart sync. Please try again.");
      }
    },
  },
  watch: {
    $route(to, from) {
      // Stop any ongoing progress check when leaving WooCommerce settings
      if (from.name && from.name.includes("woocommerce")) {
        this.stopProgressCheck();
      }

      let routePath = to.name;
      let platform = routePath.replace("-settings", "");
      this.platformTitle =
        platform === "tp"
          ? this.ucFirst(this.appVars.tp_slug) + " Settings"
          : to.meta.title;
      platform = platform === "tp" ? this.appVars.tp_slug : platform;
      this.reviewPlatform = platform;
      this.getSettings(platform);

      // Reset import progress when switching platforms
      this.importProgress = null;

      // Check for ongoing import if switching to WooCommerce
      if (platform === "woocommerce") {
        this.checkImportProgress(true);
      }
    },
    reviewPlatform(val) {
      this.reviewPlatform = val;

      // If switching to TripAdvisor and current expiration is 1 Hour or 6 Hours, reset to 1 Day
      if (val === "tripadvisor" && this.allGlobalSettings && this.allGlobalSettings.global_settings) {
        const currentExpiration = this.allGlobalSettings.global_settings.expiration;
        if (currentExpiration === 60 * 60 || currentExpiration === 60 * 60 * 6) {
          this.updateExpirationValue(86400); // 1 Day
        }
      }
    },
  },
  mounted() {
    let routePath = this.$route.name;
    let platform = routePath.replace("-settings", "");
    this.platformTitle =
      platform === "tp"
        ? this.ucFirst(this.appVars.tp_slug) + " Settings"
        : this.$route.meta.title;
    platform = platform === "tp" ? this.appVars.tp_slug : platform;
    this.reviewPlatform = platform;
    this.getSettings(platform);

    // Check for ongoing import on mount (only for WooCommerce)
    if (platform === "woocommerce") {
      this.checkImportProgress(true);
    }
  },

  beforeDestroy() {
    // Clean up interval when component is destroyed
    this.stopProgressCheck();
  },
};
</script>
