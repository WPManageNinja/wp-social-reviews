<template>
  <div class="wpsr_editor" v-if="!fetchingForm">
    <!-- Editor Header -->
    <div class="settings_header">
      <div class="settings_left">
        <div class="wpsr_back_nav">
          <router-link class="wpsr-label-small wpsr-secondary-link" :to="{ name: 'review-forms' }">{{ $t('Review Forms') }}</router-link>
          <span class="wpsr_sep"><ArrowRightIcon /></span>
        </div>
        <span class="section_title wpsr-label-small wpsr-heading-text">{{ form.title || $t('New Review Form') }}</span>
        <el-button class="wpsr_mini wpsr_light_edit wpsr_plain_btn wpsr_icon_button" @click="titleDialogVisible = true">
          <EditPenIcon />
        </el-button>
      </div>
      <div class="settings_right">
        <span v-if="form.id" class="wpsr-copy-inner-text">
          <el-tooltip effect="dark" :content="$t('Click to copy shortcode')" placement="bottom">
            <code class="copy has-icon wpsr-paragraph-small wpsr-heading-text" @click="copyShortcode">
              <CopyDocumentIcon /> [wpsr_review_form id="{{ form.id }}"]
            </code>
          </el-tooltip>
        </span>
        <span class="wpsr_editor_full_screen">
          <el-button class="wpsr_mini wpsr_light_edit wpsr_default_btn wpsr_icon_btn" @click.prevent="handleFullScreen">
            <FullScreenIcon />
          </el-button>
        </span>
        <el-button class="wpsr_secondary_btn" @click.prevent="saveForm" :loading="saving">
          {{ saving ? $t('Saving') : $t('Save') }}
        </el-button>
        <ColorMode />
      </div>
    </div>

    <!-- Editor Body -->
    <div class="wpsr_editor_wrapper">
      <el-container>
        <!-- Main Preview Area -->
        <el-main class="wpsr-editor-view-port">
          <div class="wpsr-review-form-preview">
            <div class="wpsr-review-form-preview-card">
              <!-- Live Form Preview -->
              <div class="wpsr-review-form-preview-inner">
                <h3 v-if="schema.form_title.enabled" class="wpsr-preview-form-title">{{ schema.form_title.text }}</h3>
                <p v-if="schema.form_subtitle.enabled" class="wpsr-preview-form-subtitle">{{ schema.form_subtitle.text }}</p>

                <div v-for="field in editableFields" :key="field.name" class="wpsr-preview-field" :class="{ 'wpsr-preview-field-disabled': !field.enabled }">
                  <template v-if="field.enabled">
                    <label v-if="field.type !== 'privacy_policy' && field.type !== 'content_permission'" class="wpsr-preview-label">
                      {{ field.label }}
                      <span v-if="field.required" class="wpsr-preview-required">*</span>
                    </label>

                    <!-- Rating preview -->
                    <div v-if="field.type === 'rating'" class="wpsr-preview-rating">
                      <span v-for="i in (field.max_stars || 5)" :key="i" class="wpsr-preview-rating-icon" :data-style="schema.rating_icon_style">
                        <template v-if="schema.rating_icon_style === 'star'">&#9733;</template>
                        <template v-else-if="schema.rating_icon_style === 'heart'">&#9829;</template>
                        <template v-else-if="schema.rating_icon_style === 'emoji'">
                          {{ ['&#128544;','&#128528;','&#128522;','&#128516;','&#129321;'][i-1] }}
                        </template>
                        <template v-else-if="schema.rating_icon_style === 'thumbs'">
                          {{ i <= 3 ? '&#128078;' : '&#128077;' }}
                        </template>
                        <template v-else-if="schema.rating_icon_style === 'number'">{{ i }}</template>
                        <template v-else>&#9733;</template>
                      </span>
                    </div>

                    <!-- Textarea preview -->
                    <div v-else-if="field.type === 'textarea'" class="wpsr-preview-textarea">
                      <span class="wpsr-preview-placeholder">{{ field.placeholder }}</span>
                    </div>

                    <!-- Media upload preview -->
                    <div v-else-if="field.type === 'media'" class="wpsr-preview-media">
                      <span class="wpsr-preview-placeholder">{{ $t('Choose files...') }}</span>
                    </div>

                    <!-- Checkbox preview (privacy / content permission) -->
                    <div v-else-if="field.type === 'privacy_policy' || field.type === 'content_permission'" class="wpsr-preview-checkbox">
                      <input type="checkbox" disabled />
                      <span>{{ stripHtml(field.label) }}</span>
                    </div>

                    <!-- Text / Email input preview -->
                    <div v-else class="wpsr-preview-input">
                      <span class="wpsr-preview-placeholder">{{ field.placeholder }}</span>
                    </div>
                  </template>
                </div>

                <!-- Submit button preview -->
                <div class="wpsr-preview-submit">
                  <button class="wpsr-preview-submit-btn" disabled>{{ settings.general.submit_button_text }}</button>
                </div>
              </div>
            </div>
          </div>
        </el-main>

        <!-- Sidebar -->
        <el-aside class="wpsr-editor-sidebar">
          <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
            <!-- General Tab -->
            <el-tab-pane :label="$t('General')" name="general" class="wpsr-element-tab-item">
              <el-collapse accordion v-model="activeNames">

                <!-- Form Display Section -->
                <el-collapse-item name="1">
                  <template #title="{ isActive }">
                    <span class="wpsr-editor-title-row">
                      <ContentIcon class="wpsr-editor-icon" />
                      <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Form Display') }}</span>
                    </span>
                    <span class="wpsr-custom-collapse-arrow">
                      <PlusIcon v-if="!isActive" />
                      <MinusIcon v-else />
                    </span>
                  </template>
                  <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                    <div class="wpsr-settings-switch">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Form Title') }}</span>
                      <el-switch v-model="schema.form_title.enabled" class="wpsr-editor-inside-right" />
                    </div>
                    <div v-if="schema.form_title.enabled" class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Title Text') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-input v-model="schema.form_title.text" size="small" class="wpsr-text-input" />
                      </div>
                    </div>
                    <div class="wpsr-settings-switch">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Subtitle') }}</span>
                      <el-switch v-model="schema.form_subtitle.enabled" class="wpsr-editor-inside-right" />
                    </div>
                    <div v-if="schema.form_subtitle.enabled" class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Subtitle Text') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-input v-model="schema.form_subtitle.text" type="textarea" :rows="1" size="small" class="wpsr-text-input" />
                      </div>
                    </div>
                    <div class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Rating Style') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-select v-model="schema.rating_icon_style" size="small" class="wpsr-text-input">
                          <el-option value="star" :label="'&#9733; ' + $t('Star')" />
                          <el-option value="heart" :label="'&#9829; ' + $t('Heart')" />
                          <el-option value="emoji" :label="'&#128522; ' + $t('Emoji')" />
                          <el-option value="thumbs" :label="'&#128077; ' + $t('Thumbs')" />
                          <el-option value="number" :label="'123 ' + $t('Number')" />
                        </el-select>
                      </div>
                    </div>
                  </div>
                </el-collapse-item>

                <!-- Fields Configuration Section -->
                <el-collapse-item name="2">
                  <template #title="{ isActive }">
                    <span class="wpsr-editor-title-row">
                      <EditPenIcon class="wpsr-editor-icon" />
                      <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Form Fields') }}</span>
                    </span>
                    <span class="wpsr-custom-collapse-arrow">
                      <PlusIcon v-if="!isActive" />
                      <MinusIcon v-else />
                    </span>
                  </template>
                  <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_8">
                    <template v-for="(field, index) in editableFields" :key="field.name">
                      <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                        <div class="wpsr-settings-switch">
                          <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">
                            <span>{{ stripHtml(field.label) }}</span>
                          </span>
                          <el-switch v-model="field.enabled" class="wpsr-editor-inside-right" />
                        </div>

                        <template v-if="field.enabled">
                          <div class="wpsr-filters-row">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Label') }}</span>
                            <div class="wpsr-editor-inside-right">
                              <el-input v-model="field.label" type="textarea" :rows="2" size="small" class="wpsr-text-input" />
                            </div>
                          </div>
                          <div v-if="field.placeholder !== undefined" class="wpsr-filters-row">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Placeholder') }}</span>
                            <div class="wpsr-editor-inside-right">
                              <el-input v-model="field.placeholder" type="textarea" :rows="2" size="small" class="wpsr-text-input" />
                            </div>
                          </div>
                          <div class="wpsr-settings-switch">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Required') }}</span>
                            <el-switch v-model="field.required" class="wpsr-editor-inside-right" />
                          </div>
                          <!-- Privacy Policy URL -->
                          <div v-if="field.type === 'privacy_policy' && field.privacy_policy_url !== undefined" class="wpsr-filters-row">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Policy URL') }}</span>
                            <div class="wpsr-editor-inside-right">
                              <el-input v-model="field.privacy_policy_url" type="textarea" :rows="2" size="small" :placeholder="$t('https://example.com/privacy-policy')" class="wpsr-text-input" />
                            </div>
                          </div>
                          <div v-if="field.maxlength !== undefined" class="wpsr-filters-row">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Max Length') }}</span>
                            <div class="wpsr-reviews-number wpsr-editor-inside-right">
                              <el-input-number v-model="field.maxlength" :min="1" :max="10000" size="small" controls-position="right" class="wpsr-text-input" />
                            </div>
                          </div>
                          <div v-if="field.max_stars !== undefined" class="wpsr-filters-row">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Max Stars') }}</span>
                            <div class="wpsr-reviews-number wpsr-editor-inside-right">
                              <el-input-number v-model="field.max_stars" :min="3" :max="5" size="small" controls-position="right" class="wpsr-text-input" />
                            </div>
                          </div>
                          <div v-if="field.rows !== undefined" class="wpsr-filters-row">
                            <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Rows') }}</span>
                            <div class="wpsr-reviews-number wpsr-editor-inside-right">
                              <el-input-number v-model="field.rows" :min="2" :max="20" size="small" controls-position="right" class="wpsr-text-input" />
                            </div>
                          </div>
                          <!-- Media field extras -->
                          <template v-if="field.type === 'media'">
                            <div class="wpsr-filters-row">
                              <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Max Files') }}</span>
                              <div class="wpsr-reviews-number wpsr-editor-inside-right">
                                <el-input-number v-model="field.max_files" :min="1" :max="10" size="small" controls-position="right" class="wpsr-text-input" />
                              </div>
                            </div>
                            <div class="wpsr-filters-row">
                              <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Max Size (MB)') }}</span>
                              <div class="wpsr-reviews-number wpsr-editor-inside-right">
                                <el-input-number v-model="field.max_size_mb" :min="1" :max="20" size="small" controls-position="right" class="wpsr-text-input" />
                              </div>
                            </div>
                          </template>
                        </template>
                      </div>
                      <div v-if="index < editableFields.length - 1" class="wpsr-divider"></div>
                    </template>
                  </div>
                </el-collapse-item>

                <!-- General Settings Section -->
                <el-collapse-item name="3">
                  <template #title="{ isActive }">
                    <span class="wpsr-editor-title-row">
                      <GeneralSettingsIcon class="wpsr-editor-icon" />
                      <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('General Settings') }}</span>
                    </span>
                    <span class="wpsr-custom-collapse-arrow">
                      <PlusIcon v-if="!isActive" />
                      <MinusIcon v-else />
                    </span>
                  </template>
                  <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                    <div class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Button Text') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-input v-model="settings.general.submit_button_text" size="small" class="wpsr-text-input" />
                      </div>
                    </div>
                    <div class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-align-top wpsr-input-field-label">{{ $t('Success Message') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-input v-model="settings.general.success_message" type="textarea" :rows="2" size="small" class="wpsr-text-input" />
                      </div>
                    </div>
                    <div class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Duplicate Message') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-input v-model="settings.general.duplicate_message" type="textarea" :rows="2" size="small" class="wpsr-text-input" />
                      </div>
                    </div>
                    <div class="wpsr-settings-switch">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Require Login') }}</span>
                      <el-switch v-model="settings.general.require_login" class="wpsr-editor-inside-right" />
                    </div>
                    <div class="wpsr-settings-switch">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('One Per Email') }}</span>
                      <el-switch v-model="settings.general.one_review_per_email" class="wpsr-editor-inside-right" />
                    </div>
                    <div class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Review Target') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-select v-model="settings.general.target_platform" size="small" class="wpsr-text-input">
                          <el-option value="native_form" :label="$t('Native Form (Generic)')" />
                          <el-option value="fluent-cart" :label="$t('Fluent Cart Product')" :disabled="!appVars.hasFluentCart" />
                          <el-option value="woocommerce" :label="$t('WooCommerce Product')" :disabled="!appVars.hasWooCommerce" />
                        </el-select>
                      </div>
                    </div>
                    <div class="wpsr-filters-row">
                      <p v-if="settings.general.target_platform === 'fluent-cart' && !appVars.hasFluentCart" style="color: #e6a23c; font-size: 12px; margin: 4px 0 0;">
                        {{ $t('Fluent Cart is not active. Reviews will fall back to generic mode until reactivated.') }}
                      </p>
                      <p v-else-if="settings.general.target_platform === 'woocommerce' && !appVars.hasWooCommerce" style="color: #e6a23c; font-size: 12px; margin: 4px 0 0;">
                        {{ $t('WooCommerce is not active. Reviews will fall back to generic mode until reactivated.') }}
                      </p>
                    </div>
                  </div>
                </el-collapse-item>

                <!-- Spam Protection Section -->
                <el-collapse-item name="4">
                  <template #title="{ isActive }">
                    <span class="wpsr-editor-title-row">
                      <ShieldCheckLineIcon class="wpsr-editor-icon" />
                      <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Spam Protection') }}</span>
                    </span>
                    <span class="wpsr-custom-collapse-arrow">
                      <PlusIcon v-if="!isActive" />
                      <MinusIcon v-else />
                    </span>
                  </template>
                  <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                    <div class="wpsr-settings-switch">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('Honeypot') }}</span>
                      <el-switch v-model="settings.spam.honeypot" class="wpsr-editor-inside-right" />
                    </div>
                    <div class="wpsr-filters-row">
                      <span class="wpsr-editor-inside-left wpsr-element-center wpsr-input-field-label">{{ $t('CAPTCHA') }}</span>
                      <div class="wpsr-editor-inside-right">
                        <el-select v-model="settings.spam.captcha_type" size="small" class="wpsr-text-input">
                          <el-option :label="$t('None')" value="none" />
                          <el-option :label="$t('Turnstile')" value="turnstile" />
                        </el-select>
                      </div>
                    </div>
                    <template v-if="settings.spam.captcha_type !== 'none'">
                      <div class="wpsr-filters-row">
                        <span class="wpsr-editor-inside-left wpsr-align-top wpsr-input-field-label">{{ $t('Provider Settings') }}</span>
                        <div class="wpsr-editor-inside-right" style="flex: 1; flex-direction: column; align-items: flex-start; gap: 8px; min-width: 0;">
                          <p style="margin: 0; font-size: 12px; color: #6b7280; line-height: 1.6;">
                            {{ $t('Turnstile keys are managed from Global Review Settings.') }}
                          </p>
                          <router-link
                            v-if="canManageCaptchaSettings"
                            :to="{ name: 'global-review-settings' }"
                            class="wpsr-link-button"
                            target="_blank"
                          >
                            {{ $t('Turnstile Settings') }}
                          </router-link>
                        </div>
                      </div>
                    </template>
                  </div>
                </el-collapse-item>

              </el-collapse>
            </el-tab-pane>
          </el-tabs>
        </el-aside>
      </el-container>
    </div>

    <!-- Title Change Dialog -->
    <el-dialog
      v-model="titleDialogVisible"
      :title="$t('Change Form Title')"
      width="30%"
      class="wpsr-modal wpsr-connection-modal"
    >
      <template #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">{{ $t('Change Form Title') }}</h4>
        </div>
      </template>
      <div class="wpsr-connection-modal-body" style="padding: 0;">
        <el-input
          v-model="form.title"
          @keyup.enter="titleDialogVisible = false; saveForm()"
          autocomplete="off"
          class="wpsr-text-input"
        />
      </div>
      <template #footer>
        <span class="dialog-footer wpsr-d-flex wpsr-jc-end">
          <el-button class="wpsr_default_btn" @click="titleDialogVisible = false">{{ $t('Cancel') }}</el-button>
          <el-button class="wpsr_secondary_btn" type="primary" @click="titleDialogVisible = false; saveForm()">{{ $t('Save') }}</el-button>
        </span>
      </template>
    </el-dialog>
  </div>

  <!-- Loading state -->
  <div v-else class="wpsr_editor" v-loading="true" style="min-height: 400px;"></div>
</template>

<script>
import PlusIcon from '../../pieces/icons/PlusIcon.vue';
import MinusIcon from '../../pieces/icons/MinusIcon.vue';
import ContentIcon from '../../pieces/icons/ContentIcon.vue';
import GeneralSettingsIcon from '../../pieces/icons/GeneralSettingsIcon.vue';
import ShieldCheckLineIcon from '../../pieces/icons/ShieldCheckLineIcon.vue';
import ArrowRightIcon from '../../pieces/icons/ArrowRightIcon.vue';
import EditPenIcon from '../../pieces/icons/EditPenIcon.vue';
import CopyDocumentIcon from '../../pieces/icons/CopyDocumentIcon.vue';
import FullScreenIcon from '../../pieces/icons/FullScreenIcon.vue';
import ColorMode from '../../pieces/ColorMode.vue';

export default {
  name: 'ReviewFormEditor',
  components: {
    PlusIcon,
    MinusIcon,
    ContentIcon,
    GeneralSettingsIcon,
    ShieldCheckLineIcon,
    ArrowRightIcon,
    EditPenIcon,
    CopyDocumentIcon,
    FullScreenIcon,
    ColorMode
  },
  data() {
    return {
      activeTab: 'general',
      activeNames: ['1'],
      saving: false,
      fetchingForm: false,
      titleDialogVisible: false,
      form: {
        id: parseInt(this.$route.params.id),
        title: '',
        slug: '',
        status: 'active'
      },
      schema: this.getDefaultSchema(),
      settings: this.getDefaultSettings()
    }
  },
  computed: {
    canManageCaptchaSettings() {
      return this.hasPermission(['wpsn_reviews_platforms_settings', 'wpsn_full_access']);
    },
    editableFields() {
      return this.schema.fields.filter(f => f.type !== 'hidden');
    }
  },
  mounted() {
    this.fetchForm();
    this.checkFullScreen();
  },
  methods: {
    getDefaultSchema() {
      return {
        form_title: { enabled: true, text: 'Leave a Review' },
        form_subtitle: { enabled: true, text: "We'd love to hear about your experience" },
        rating_icon_style: 'star',
        fields: [
          { type: 'text', name: 'reviewer_name', label: 'Your Name', placeholder: 'Your full name', required: true, enabled: true, maxlength: 100 },
          { type: 'email', name: 'reviewer_email', label: 'Email Address', placeholder: 'you@example.com', required: true, enabled: true },
          { type: 'rating', name: 'rating', label: 'Rating', required: true, enabled: true, max_stars: 5, default: 0 },
          { type: 'text', name: 'review_title', label: 'Review Title', placeholder: 'Summarize your experience', required: false, enabled: true, maxlength: 200 },
          { type: 'textarea', name: 'review_text', label: 'Your Review', placeholder: 'Share your experience...', required: true, enabled: true, maxlength: 5000, rows: 5 },
          { type: 'media', name: 'media', label: 'Upload Photos', required: false, enabled: false, max_files: 3, max_size_mb: 5, allowed_types: ['image/jpeg', 'image/png', 'image/webp'] },
          { type: 'privacy_policy', name: 'privacy_accepted', label: 'I agree to the <a href="{privacy_url}" target="_blank">Privacy Policy</a>', required: true, enabled: true, privacy_policy_url: '' },
          { type: 'content_permission', name: 'content_permission', label: 'I grant permission to share my review publicly on this website', required: true, enabled: true },
          { type: 'hidden', name: 'source_url', value: '{{current_url}}' },
          { type: 'hidden', name: 'product_id', value: '' }
        ]
      };
    },
    getDefaultSettings() {
      return {
        general: {
          submit_button_text: 'Submit Review',
          success_message: 'Thank you! Your review has been submitted for moderation.',
          duplicate_message: 'You have already submitted a review.',
          require_login: false,
          one_review_per_email: true,
          target_platform: 'native_form'
        },
        spam: {
          honeypot: true,
          captcha_type: 'none'
        }
      };
    },
    fetchForm() {
      this.fetchingForm = true;
      this.$get(`pro/review-forms/${this.$route.params.id}`)
        .then(response => {
          const formData = response.form;
          this.form = {
            id: formData.id,
            title: formData.title,
            slug: formData.slug,
            status: formData.status
          };
          const savedSchema = typeof formData.schema === 'string' ? JSON.parse(formData.schema) : formData.schema;
          if (savedSchema && savedSchema.fields && savedSchema.fields.length) {
            this.schema = savedSchema;
          }
          const savedSettings = typeof formData.settings === 'string' ? JSON.parse(formData.settings) : formData.settings;
          if (savedSettings && Object.keys(savedSettings).length) {
            const defaults = this.getDefaultSettings();
            this.settings = {
              ...defaults,
              ...savedSettings,
              general: { ...defaults.general, ...(savedSettings.general || {}) },
              spam: { ...defaults.spam, ...(savedSettings.spam || {}) }
            };
          }
        })
        .catch(errors => {
          this.handleError(errors);
        })
        .always(() => {
          this.fetchingForm = false;
        });
    },
    saveForm() {
      this.saving = true;
      const payload = {
        title: this.form.title,
        schema: this.schema,
        settings: this.settings,
        status: this.form.status
      };

      this.$put(`pro/review-forms/${this.form.id}`, payload)
        .then(response => {
          this.handleSuccess(response.message);
        })
        .catch(errors => {
          this.handleError(errors);
        })
        .always(() => {
          this.saving = false;
        });
    },
    stripHtml(str) {
      const div = document.createElement('div');
      div.innerHTML = str;
      return div.textContent || div.innerText || '';
    },
    copyShortcode() {
      const shortcode = `[wpsr_review_form id="${this.form.id}"]`;
      navigator.clipboard.writeText(shortcode).then(() => {
        this.handleSuccess(this.$t('Shortcode copied to clipboard'));
      });
    }
  }
}
</script>

<style scoped>
.wpsr-review-form-preview {
  display: flex;
  justify-content: center;
  padding: 30px 20px;
  min-height: calc(100vh - 120px);
}
.wpsr-review-form-preview-card {
  background: var(--wpsr-primary-bg, #fff);
  border: 1px solid var(--wpsr-primary-border, #E1E4EA);
  border-radius: 8px;
  padding: 32px;
  max-width: 560px;
  width: 100%;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.wpsr-preview-form-title {
  font-size: 22px;
  font-weight: 600;
  color: var(--wpsr-heading-text, #0E121B);
  margin: 0 0 6px;
}
.wpsr-preview-form-subtitle {
  font-size: 14px;
  color: var(--wpsr-primary-text, #525866);
  margin: 0 0 24px;
}
.wpsr-preview-field {
  margin-bottom: 18px;
}
.wpsr-preview-field-disabled {
  opacity: 0.3;
  pointer-events: none;
}
.wpsr-preview-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--wpsr-heading-text, #0E121B);
  margin-bottom: 6px;
}
.wpsr-preview-required {
  color: #f56c6c;
}
.wpsr-preview-input,
.wpsr-preview-textarea,
.wpsr-preview-media {
  background: var(--wpsr-secondary-bg, #F5F7FA);
  border: 1px solid var(--wpsr-primary-border, #E1E4EA);
  border-radius: 6px;
  padding: 8px 12px;
}
.wpsr-preview-textarea {
  min-height: 80px;
}
.wpsr-preview-placeholder {
  color: #a8abb2;
  font-size: 13px;
}
.wpsr-preview-rating {
  display: flex;
  gap: 4px;
}
.wpsr-preview-rating-icon {
  font-size: 22px;
  color: #dcdfe6;
  cursor: default;
}
.wpsr-preview-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  color: var(--wpsr-primary-text, #525866);
}
.wpsr-preview-checkbox input {
  margin-top: 2px;
}
.wpsr-preview-submit {
  width: auto;
  display: inline-block;
  margin: 24px auto 0;
}
.wpsr-preview-submit-btn {
  background: var(--wpsr-primary-btn-bg, #5525D9);
  color: var(--wpsr-primary-btn-text, #FFFFFF);
  border: none;
  border-radius: 6px;
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 500;
  cursor: default;
}
</style>
