<template>
  <div class="wpsr-settings-container wpsr-feed-global-settings-wrapper">
    <div class="wpsr-settings-header">
      <h2 class="wpsr-feed-settings-title">
        {{ $t("Native Form CAPTCHA Settings") }}
      </h2>
      <p class="wpsr-feed-settings-description">
        {{ $t("Configure and validate CAPTCHA keys once for all native review forms.") }}
      </p>
    </div>

    <div class="wpsr-feed-settings-content">
      <div class="wpsr-feed-setting-border wpsr-pb-20" style="margin-bottom: 20px;">
        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ captchaProvider.label }}</h3>
            <p class="wpsr-setting-description">
              {{ captchaProvider.description }}
              <a
                :href="captchaProvider.docs_url"
                target="_blank"
                rel="noopener noreferrer"
              >
                {{ $t('Learn more') }}
              </a>
            </p>
            <p class="wpsr-text-muted" style="margin-top: 8px;">
              <strong :style="{ color: getCaptchaStatusColor() }">{{ getCaptchaStatusLabel() }}</strong>
            </p>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Site Key') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Enter your Cloudflare Turnstile site key.') }}</p>
          </div>
          <div class="wpsr-setting-right">
            <el-input
              v-model="captchaSettings.site_key"
              class="wpsr-text-input wpsr-input-border"
              :placeholder="$t('Site Key')"
            />
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Secret Key') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Enter your Cloudflare Turnstile secret key. It will be stored securely.') }}</p>
          </div>
          <div class="wpsr-setting-right" style="display: flex; flex-direction: column;">
            <el-input
              v-model="captchaSettings.secret_key"
              class="wpsr-text-input wpsr-input-border"
              type="password"
              show-password
              :placeholder="getSecretKeyPlaceholder()"
            />
            <p
              v-if="captchaSettings.is_configured && !captchaSettings.secret_key"
              class="wpsr-text-muted"
              style="margin: 8px 0 0;"
            >
              {{ $t('A secret key is already saved. Leave this field empty to keep the existing key.') }}
            </p>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Theme') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Choose how the Turnstile widget should appear on native forms.') }}</p>
          </div>
          <div class="wpsr-setting-right">
            <el-select
              v-model="captchaSettings.theme"
              class="wpsr-text-input"
            >
              <el-option :label="$t('Theme: Auto')" value="auto" />
              <el-option :label="$t('Theme: Light')" value="light" />
              <el-option :label="$t('Theme: Dark')" value="dark" />
            </el-select>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Appearance') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Choose how the Turnstile widget should appear on native forms.') }}</p>
          </div>
          <div class="wpsr-setting-right">
            <el-select
              v-model="captchaSettings.appearance"
              class="wpsr-text-input"
            >
              <el-option :label="$t('Managed')" value="always" />
              <el-option :label="$t('Non-interactive')" value="execute" />
              <el-option :label="$t('Invisible')" value="interaction-only" />
            </el-select>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Validation Preview') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Complete the preview challenge before saving to validate your keys.') }}</p>
          </div>
          <div class="wpsr-setting-right">
            <div
              :id="getCaptchaContainerId()"
              class="wpsr-captcha-admin-widget"
            ></div>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Validation Status') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Review the latest validation feedback before saving your configuration.') }}</p>
          </div>
          <div class="wpsr-setting-right">
            <p
              class="wpsr-text-muted"
              :style="{ color: captchaMessage.type === 'error' ? '#b91c1c' : '#166534' }"
              style="margin: 0;"
            >
              {{ captchaMessage.message }}
            </p>
          </div>
        </div>

        <div class="wpsr-setting-row">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Save Settings') }}</h3>
            <p class="wpsr-setting-description">{{ $t('Validate the preview and save this Turnstile configuration for all native forms, or disconnect it completely.') }}</p>
          </div>
          <div class="wpsr-setting-right">
          <el-button
            class="wpsr_default_btn"
            :disabled="!captchaSettings.is_configured || isCaptchaBusy"
            :loading="captchaClearing"
            @click="clearCaptchaSettings"
            style="margin-right: 12px;"
          >
            {{ $t('Disconnect') }}
          </el-button>
          <el-button
            class="wpsr_primary_btn"
            type="success"
            :disabled="isCaptchaBusy"
            :loading="captchaLoading"
            @click="saveCaptchaSettings"
          >
            {{ $t('Validate & Save') }}
          </el-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NativeFormCaptchaSettings',
  data() {
    return {
      captchaProvider: {
        type: 'turnstile',
        label: 'Turnstile',
        description: 'Cloudflare Turnstile challenge for native review forms.',
        docs_url: 'https://www.cloudflare.com/en-gb/application-services/products/turnstile/',
      },
      captchaSettings: {
        site_key: '',
        secret_key: '',
        is_configured: false,
        theme: 'auto',
        appearance: 'always'
      },
      captchaSecretTouched: false,
      captchaMessage: {
        type: '',
        message: ''
      },
      captchaLoading: false,
      captchaClearing: false,
      captchaWidgetId: null,
      captchaToken: '',
      captchaRenderTimer: null,
      captchaScriptPromise: null,
      lastCaptchaPreviewConfig: '',
    };
  },
  computed: {
    isCaptchaBusy() {
      return this.captchaLoading || this.captchaClearing;
    }
  },
  methods: {
    loadSettings() {
      this.$get('pro/settings/review-form-captcha')
        .then((response) => {
          if (response && response.data) {
            this.captchaSettings = { ...this.captchaSettings, ...(response.data || {}) };
            this.captchaSecretTouched = false;
            this.$nextTick(() => {
              this.renderTurnstileWidget();
            });
          }
        })
        .catch((error) => {
          this.handleError(error);
        });
    },
    getCaptchaContainerId() {
      return 'wpsr-captcha-admin-turnstile';
    },
    getCaptchaStatusLabel() {
      return this.captchaSettings.is_configured ? this.$t('Configured') : this.$t('Not Configured');
    },
    getCaptchaStatusColor() {
      return this.captchaSettings.is_configured ? '#16a34a' : '#b91c1c';
    },
    getSecretKeyPlaceholder() {
      if (this.captchaSettings.secret_key) {
        return this.$t('Secret Key');
      }

      return this.captchaSettings.is_configured ? '••••••••••••••••' : this.$t('Secret Key');
    },
    setCaptchaMessage(message, status = 'success') {
      this.captchaMessage = {
        type: status,
        message
      };
    },
    getCaptchaPreviewConfig() {
      return JSON.stringify({
        sitekey: this.captchaSettings.site_key || '',
        theme: this.captchaSettings.theme || 'auto',
        // The preview always renders as interactive in admin.
        appearance: 'always'
      });
    },
    scheduleTurnstileWidgetRender(force = false) {
      if (this.captchaRenderTimer) {
        clearTimeout(this.captchaRenderTimer);
      }

      this.resetCaptchaWidget();

      this.captchaRenderTimer = setTimeout(() => {
        this.captchaRenderTimer = null;
        this.renderTurnstileWidget(force);
      }, 350);
    },
    loadExternalScript(id, src) {
      if (this.captchaScriptPromise) {
        return this.captchaScriptPromise;
      }

      this.captchaScriptPromise = new Promise((resolve, reject) => {
        const appendScript = () => {
          const script = document.createElement('script');
          script.id = id;
          script.src = src;
          script.async = true;
          script.defer = true;
          script.addEventListener('load', () => {
            script.dataset.loaded = 'true';
            delete script.dataset.failed;
            resolve();
          }, { once: true });
          script.addEventListener('error', () => {
            script.dataset.failed = 'true';
            reject();
          }, { once: true });
          document.head.appendChild(script);
        };

        let existing = document.getElementById(id);
        if (existing) {
          if (
            existing.dataset.loaded === 'true' ||
            (window.turnstile && typeof window.turnstile.render === 'function')
          ) {
            existing.dataset.loaded = 'true';
            resolve();
            return;
          }

          existing.remove();
          appendScript();
          return;
        }

        appendScript();
      }).finally(() => {
        this.captchaScriptPromise = null;
      });

      return this.captchaScriptPromise;
    },
    renderTurnstileWidget(force = false) {
      const siteKey = this.captchaSettings.site_key;
      const container = document.getElementById(this.getCaptchaContainerId());
      const previewConfig = this.getCaptchaPreviewConfig();
      if (!container || !siteKey) {
        if (container) {
          container.innerHTML = '';
        }
        this.captchaWidgetId = null;
        this.captchaToken = '';
        this.lastCaptchaPreviewConfig = '';
        return;
      }

      if (!force && this.lastCaptchaPreviewConfig === previewConfig && this.captchaWidgetId !== null) {
        this.resetCaptchaWidget();
        return;
      }

      this.loadExternalScript('wpsr-admin-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit')
        .then(() => {
          if (!window.turnstile || typeof window.turnstile.render !== 'function') {
            return;
          }

          container.innerHTML = '<div></div>';
          this.lastCaptchaPreviewConfig = previewConfig;
          this.captchaWidgetId = window.turnstile.render(container.firstChild, {
            sitekey: siteKey,
            theme: this.captchaSettings.theme || 'auto',
            // Keep the admin preview interactive regardless of the saved frontend appearance.
            appearance: 'always',
            callback: (token) => {
              this.captchaToken = token || '';
            },
            'expired-callback': () => {
              this.captchaToken = '';
            },
            'error-callback': () => {
              this.captchaToken = '';
            }
          });
        })
        .catch(() => {
          this.setCaptchaMessage(this.$t('Could not load Turnstile widget.'), 'error');
        });
    },
    getCaptchaToken() {
      let token = this.captchaToken;

      if (!token && window.turnstile && this.captchaWidgetId !== null && typeof window.turnstile.getResponse === 'function') {
        token = window.turnstile.getResponse(this.captchaWidgetId) || '';
      }

      if (!token) {
        const response = document.querySelector(`#${this.getCaptchaContainerId()} [name="cf-turnstile-response"]`);
        token = response?.value || '';
      }

      if (!token) {
        throw new Error(this.$t('Please complete the Turnstile challenge before saving.'));
      }

      return token;
    },
    resetCaptchaWidget() {
      if (window.turnstile && this.captchaWidgetId !== null) {
        window.turnstile.reset(this.captchaWidgetId);
      }
      this.captchaToken = '';
    },
    async clearCaptchaSettings() {
      if (!this.captchaSettings.is_configured || this.isCaptchaBusy) {
        return;
      }

      this.captchaClearing = true;

      try {
        const response = await this.$del('pro/settings/review-form-captcha', {
          type: this.captchaProvider.type
        });

        if (response && response.data) {
          this.captchaSettings = {
            site_key: '',
            secret_key: '',
            is_configured: false,
            theme: 'auto',
            appearance: 'always',
            ...response.data
          };
          this.setCaptchaMessage(response.message || this.$t('Turnstile settings cleared successfully.'));
          this.$nextTick(() => {
            const container = document.getElementById(this.getCaptchaContainerId());
            if (container) {
              container.innerHTML = '';
            }
            this.captchaWidgetId = null;
            this.captchaToken = '';
          });
        }
      } catch (error) {
        const message = error?.message || error?.data?.message || this.$t('Turnstile settings could not be cleared.');
        this.setCaptchaMessage(message, 'error');
      } finally {
        this.captchaClearing = false;
      }
    },
    async saveCaptchaSettings() {
      if (this.isCaptchaBusy) {
        return;
      }

      const settings = this.captchaSettings;
      if (!settings.site_key) {
        this.setCaptchaMessage(this.$t('Site key is required.'), 'error');
        return;
      }

      this.captchaLoading = true;
      this.setCaptchaMessage(this.$t('Complete the Turnstile challenge to validate these keys.'), 'success');

      try {
        const token = this.getCaptchaToken();
        const payload = {
          token
        };

        payload.site_key = settings.site_key;
        payload.theme = settings.theme;
        payload.appearance = settings.appearance;

        if (this.captchaSecretTouched || settings.secret_key) {
          payload.secret_key = settings.secret_key;
        }

        const response = await this.$post('pro/settings/review-form-captcha', {
          type: this.captchaProvider.type,
          settings: JSON.stringify(payload)
        });

        if (response && response.data) {
          this.captchaSettings = {
            ...this.captchaSettings,
            ...response.data,
            secret_key: ''
          };
          this.captchaSecretTouched = false;
          this.setCaptchaMessage(response.message || this.$t('Turnstile settings validated and saved successfully.'), 'success');
          this.$nextTick(() => {
            this.scheduleTurnstileWidgetRender(true);
          });
        }
      } catch (error) {
        const message = error?.message || error?.data?.message || this.$t('Captcha settings could not be saved.');
        this.setCaptchaMessage(message, 'error');
      } finally {
        this.captchaLoading = false;
        this.resetCaptchaWidget();
      }
    },
  },
  mounted() {
    this.loadSettings();
  },
  beforeUnmount() {
    if (this.captchaRenderTimer) {
      clearTimeout(this.captchaRenderTimer);
    }
  },
  watch: {
    'captchaSettings.secret_key'() {
      this.captchaSecretTouched = true;
    },
    'captchaSettings.site_key'() {
      this.scheduleTurnstileWidgetRender();
    },
    'captchaSettings.theme'() {
      this.scheduleTurnstileWidgetRender();
    },
    'captchaSettings.appearance'() {
      this.scheduleTurnstileWidgetRender();
    }
  }
};
</script>
