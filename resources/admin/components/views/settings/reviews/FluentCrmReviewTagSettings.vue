<template>
  <div v-if="hasFluentCrm" class="wpsr-settings-container wpsr-feed-global-settings-wrapper">
    <div class="wpsr-settings-header">
      <h2 class="wpsr-feed-settings-title">
        {{ $t("FluentCRM Review Tagging") }}
      </h2>
      <p class="wpsr-feed-settings-description">
        {{ $t("Automatically tag a customer's FluentCRM contact once their WooCommerce or FluentCart review is approved, so you can branch a FluentCRM automation on it (e.g. a thank-you coupon vs. a reminder).") }}
      </p>
    </div>

    <div class="wpsr-feed-settings-content">
      <div class="wpsr-feed-setting-border wpsr-pb-20">
        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t("Enable Review Tagging") }}</h3>
            <p class="wpsr-setting-description">
              {{ $t('When enabled, approved reviews will tag the matching FluentCRM contact.') }}
            </p>
          </div>
          <div class="wpsr-setting-right">
            <el-switch
              v-model="settings.enabled"
              active-color="#5c8df6"
              inactive-color="#b7b7b9"
            >
            </el-switch>
          </div>
        </div>

        <div v-if="settings.enabled" class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t("Tag Name") }}</h3>
            <p class="wpsr-setting-description">
              {{ $t('The FluentCRM tag applied to the contact. Reference this exact tag name in your FluentCRM automation condition.') }}
            </p>
          </div>
          <div class="wpsr-setting-right">
            <el-select
              v-model="settings.tag_name"
              class="wpsr-text-input"
              filterable
              :placeholder="$t('Select a tag')"
            >
              <el-option
                v-for="tag in tagOptions"
                :key="tag"
                :label="tag"
                :value="tag"
              />
            </el-select>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Save Settings') }}</h3>
          </div>
          <div class="wpsr-setting-right">
            <el-button
              class="wpsr_primary_btn"
              type="success"
              :loading="saving"
              :disabled="!isDirty"
              @click="saveSettings"
            >
              {{ $t('Save Settings') }}
            </el-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FluentCrmReviewTagSettings',
  data() {
    return {
      settings: {
        enabled: false,
        tag_name: 'review_submitted',
      },
      savedSettings: {
        enabled: false,
        tag_name: 'review_submitted',
      },
      hasFluentCrm: false,
      availableTags: [],
      saving: false,
    };
  },
  computed: {
    tagOptions() {
      const tags = [...this.availableTags];
      if (this.settings.tag_name && !tags.includes(this.settings.tag_name)) {
        tags.unshift(this.settings.tag_name);
      }
      return tags;
    },
    isDirty() {
      return this.settings.enabled !== this.savedSettings.enabled
        || this.settings.tag_name !== this.savedSettings.tag_name;
    },
  },
  methods: {
    loadSettings() {
      this.$get('pro/settings/fluentcrm-review-tag')
        .then((response) => {
          if (response && response.data) {
            this.hasFluentCrm = !!response.data.has_fluentcrm;
            this.availableTags = response.data.tags || [];
            this.settings = {
              enabled: response.data.enabled,
              tag_name: response.data.tag_name,
            };
            this.savedSettings = { ...this.settings };
          }
        })
        .catch((error) => {
          this.handleError(error);
        });
    },
    saveSettings() {
      this.saving = true;
      this.$post('pro/settings/fluentcrm-review-tag', {
        enabled: this.settings.enabled,
        tag_name: this.settings.tag_name,
      })
        .then((response) => {
          if (response) {
            this.settings = {
              enabled: response.data.enabled,
              tag_name: response.data.tag_name,
            };
            this.savedSettings = { ...this.settings };
            this.handleSuccess(response.message);
          }
        })
        .catch((error) => {
          this.handleError(error);
        })
        .always(() => {
          this.saving = false;
        });
    },
  },
  mounted() {
    this.loadSettings();
  },
};
</script>
