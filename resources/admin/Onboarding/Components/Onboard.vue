<template>
    <div class="wpsr-onboarding-container">
      <div class="wpsr-onboarding-top">
          <span class="wpsr-right-align">
            <a class="wpsr-help-url" href="https://wpsocialninja.com/docs/" target="_blank">Need Help?</a>
             <ColorMode/>
          </span>
      </div>
      <div class="wpsr-onboarding-container-inner">
        <div class="wpsr-onboarding-header">
          <div class="wpsr-onboarding-logo">
            <SocialNinjaIcon
              :show-name="true"
              :name-color="isDarkMode ? '#FFFFFF' : '#5B2CD4'"
            />
          </div>
          <div class="wpsr-progress-text">
            Step {{ displayStep }} of {{ totalSteps }}
          </div>
        </div>
        <!-- Progress Bar -->
        <div class="wpsr-progress-bar" v-if="settings.show_progress_bar">
            <div class="wpsr-progress-track">
                <div 
                    class="wpsr-progress-fill" 
                    :style="{ width: progressPercentage + '%' }"
                ></div>
            </div>
        </div>


        <!-- Loading State -->
        <div v-if="loading" class="wpsr-loading-state">
            <div class="wpsr-loading-spinner"></div>
            <p>{{ $t('Loading your setup...') }}</p>
        </div>

        <!-- Saving State -->
        <div v-else-if="saving" class="wpsr-saving-state">
            <div class="wpsr-loading-spinner"></div>
            <h3>{{ savingText }}</h3>
            <p>{{ $t('Please wait while we configure your settings...') }}</p>
        </div>

        <!-- Onboarding Steps -->
        <div v-else class="wpsr-onboarding-steps">
            <!-- Step 1: Welcome & Basic Info -->
            <div v-if="currentStep === 1" class="wpsr-step">
                <div class="wpsr-step-header">
                    <h3 v-if="!is_quick_builder">{{ getCurrentStepData.title }}</h3>
                    <h3 v-if="is_quick_builder">{{ getCurrentStepData.secondary_title }}</h3>
                    <p>{{ getCurrentStepData.description }}</p>
                </div>
                <div class="wpsr-step-content">
                  <div class="wpsr-platform-section">
                    <div class="wpsr-platform-grid wpsr-platform-platforms-types">
                      <div
                          v-for="option in platformTypes"
                          :key="option.value"
                          class="wpsr-platform-card"
                          :class="{ 'selected': formData.platform_types === option.value }"
                          @click="handlePlatformSelection(option, 'platform_types')"
                      >
                        <div class="wpsr-platform-grid-title">
                          <h4 class="wpsr-platform-name-large" v-if="option.name">{{ option.name }}</h4>
                          <div v-if="option.pro && !this.has_pro" class="wpsr-pro-crown-icon" style="padding-bottom: 14px;">
                            <ProCrownIcon/>
                          </div>
                        </div>

                       <div class="wpsr-platform-icon" style="min-height: 134px;">
                         <img class="wpsr-platform-icon-large" v-if="option.icon" :src="getPlatformIcon(option.value, 'large', option.icon)" :alt="option.name">
                       </div>
                        <span v-if="formData.platform_types === option.value" class="wpsr-checkmark">
                          <el-icon><CircleCheckFilled /></el-icon>
                        </span>

                      </div>
                    </div>
                  </div>
                </div>
            </div>

            <!-- Step 2: Platform Selection -->
            <div v-else-if="currentStep === 2" class="wpsr-step">
                <div class="wpsr-step-header">
                  <h3>{{ getCurrentStepData.title }}</h3>
                  <p>{{ getCurrentStepData.description }}</p>
                </div>

                <div class="wpsr-step-content" :class="reviewPlatforms.length > 9 || feedPlatforms.length > 9 ? 'wpsr-scrollbar' : ''">
                    <!-- Review Platforms -->
                    <div class="wpsr-platform-section" v-if="isReviewsPlatform">
                        <div class="wpsr-platform-grid">
                            <div
                                v-for="option in reviewPlatforms"
                                :key="option.value"
                                class="wpsr-platform-card wpsr-platform-card-small"
                                :class="{ 'selected': formData.review_platforms === option.value }"
                                @click="handlePlatformSelection(option, 'review_platforms')"
                            >
                                <img class="wpsr-platform-icon-small" :src="getPlatformIcon(option.value, 'small', option.icon)" :alt="option.name">
                                <h4 class="wpsr-platform-name-small">{{ option.name }}</h4>
                                <span v-if="formData.review_platforms === option.value" class="wpsr-checkmark">
                                  <el-icon><CircleCheckFilled /></el-icon>
                                </span>
                                <div v-if="option.pro && !this.has_pro" class="wpsr-pro-crown-icon">
                                  <ProCrownIcon/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Feeds -->
                    <div class="wpsr-platform-section" v-if="isFeedsPlatform">
                        <div class="wpsr-platform-grid">
                            <div
                                v-for="option in feedPlatforms"
                                :key="option.value"
                                class="wpsr-platform-card wpsr-platform-card-small"
                                :class="{ 'selected': formData.feed_platforms === option.value }"
                                @click="handlePlatformSelection(option, 'feed_platforms')"
                            >
                                <img class="wpsr-platform-icon-small" :src="getPlatformIcon(option.value, 'small', option.icon)" :alt="option.name">
                                <h4 class="wpsr-platform-name-small">{{ option.name }}</h4>
                                <span v-if="formData.feed_platforms === option.value" class="wpsr-checkmark">
                                  <el-icon><CircleCheckFilled /></el-icon>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Platforms -->
                    <div class="wpsr-platform-section" v-if="isChatPlatform">
                        <div class="wpsr-platform-grid">
                            <div
                                v-for="option in chatPlatforms"
                                :key="option.id"
                                class="wpsr-platform-card wpsr-platform-card-small wpsr-platform-chat-card"
                                :class="[{ 'selected': formData.chat_platforms === option.id }]"
                                @click="handlePlatformSelection(option, 'chat_platforms')"
                            >
                                <div class="wpsr-platform-chats-icon" :class="option.name">
                                  <img class="wpsr-platform-icon-small" :src="getPlatformIcon(option.name, 'small', option.icon)" :alt="option.displayName">
                                </div>
                                <h4 class="wpsr-platform-name-small">{{ option.displayName }}</h4>
                                <span v-if="formData.chat_platforms === option.id" class="wpsr-checkmark">
                                  <el-icon><CircleCheckFilled /></el-icon>
                                </span>
                                <div v-if="option.pro && !this.has_pro" class="wpsr-pro-crown-icon">
                                  <ProCrownIcon/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Post type -->
            <div v-else-if="currentStep === 3 && !skipsPostTypeStep" class="wpsr-step">
                <div class="wpsr-step-header">
                  <h3>{{ getCurrentStepData.title }}</h3>
                  <p>{{ getCurrentStepData.description }}</p>
                </div>

                <div class="wpsr-step-content" :class="availablePostTypeOptions.length > 9 ? 'wpsr-scrollbar' : ''">
                  <div class="wpsr-platform-section">
                    <div class="wpsr-platform-grid wpsr-platform-type-card">
                      <div
                        v-for="option in availablePostTypeOptions"
                        :key="option.value"
                        class="wpsr-platform-card"
                        :class="{ selected: formData.post_type === option.value, pro: option.pro && !this.has_pro }"
                        @click="handlePlatformSelection(option, 'post_type')"
                      >
                       <div class="wpsr-platform-grid-title">
                         <h4 class="wpsr-platform-name-small">{{ option.title }}</h4>
                         <div v-if="option.pro && !this.has_pro" class="wpsr-pro-crown-icon">
                           <ProCrownIcon/>
                         </div>
                       </div>
                        <div class="wpsr-post-type-desc">{{ option.description }}</div>
                        <span v-if="formData.post_type === option.value" class="wpsr-checkmark">
                            <el-icon><CircleCheckFilled /></el-icon>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
            </div>

          <!-- Step 4: choose template -->
          <div v-else-if="currentStep === 4 || (currentStep === 3 && skipsPostTypeStep)" class="wpsr-step">
            <div class="wpsr-step-header">
              <h3>{{ getCurrentStepData.title }}</h3>
              <p>{{ getCurrentStepData.description }}</p>
            </div>
            <div class="wpsr-step-content" :class="availableTemplateOptions.length > 9 ? 'wpsr-scrollbar' : ''">
              <div class="wpsr-platform-section">
                <div class="wpsr-platform-grid wpsr-grid-column-4">
                  <div
                      v-for="option in availableTemplateOptions"
                      :key="option.title"
                      @click="handlePlatformSelection(option, 'template')"
                  >
                    <div class="wpsr-platform-card wpsr-platform-card-outer" :class="{ selected: objectsEqual(formData.template, option.value), pro: option.pro && !this.has_pro }">
                      <div class="wpsr-platform-icon">
                        <img class="wpsr-platform-icon-large" v-if="option.icon" :src="option.icon" :alt="option.title">
                      </div>
                      <span v-if="formData.template === option.value" class="wpsr-checkmark">
                        <el-icon><CircleCheckFilled /></el-icon>
                      </span>
                    </div>
                    <div class="wpsr-platform-grid-title">
                      <h4 class="wpsr-platform-name-small">{{ option.title }}</h4>
                      <div v-if="option.pro && !this.has_pro" class="wpsr-pro-crown-icon">
                        <ProCrownIcon/>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
      <!-- Navigation -->
      <div class="wpsr-navigation">

        <!-- Skip Button -->
        <div class="wpsr-navigation-left">
          <button
              v-if="settings.show_skip_button && !is_quick_builder"
              @click="skipOnboarding"
              class="wpsr-skip-button"
              :disabled="saving"
          >
            {{ settings.skip_button_text }}
          </button>
        </div>

        <div class="wpsr-navigation-right">
          <button
              v-if="settings.show_back_button && currentStep > 1"
              @click="previousStep"
              class="wpsr-btn wpsr-btn-secondary"
              :disabled="saving"
          >
            {{ settings.back_button_text }}
          </button>
          <button
              v-if="currentStep < totalSteps"
              @click="nextStep"
              class="wpsr-btn wpsr-btn-primary"
              :disabled="!canProceed || saving"
          >
            {{ settings.next_button_text }}
          </button>
          <button
              v-if="currentStep === totalSteps"
              @click="completeOnboarding"
              class="wpsr-btn wpsr-btn-primary"
              :disabled="saving"
          >
            {{ settings.complete_button_text }}
          </button>
        </div>

      </div>
    </div>
    <el-dialog
        v-model="showUpgradeModal"
        width="55%"
        class="wpsr-modal wpsr-upgrade-modal-wrapper"
    >
        <UpgradeToProModal
            v-if="showUpgradeModal"
            :platform="selectedPlatform"
            :feature_type="selectedFeatureType"
            @close="showUpgradeModal = false"
        />
    </el-dialog>
</template>

<script>

import ProCrownIcon from "../../components/pieces/icons/ProCrownIcon.vue";
import UpgradeToProModal from "../../components/views/advertise/UpgradeToProModal.vue";
import { CircleCheckFilled } from '@element-plus/icons-vue';
import ColorMode from "../../components/pieces/ColorMode.vue";
import SocialNinjaIcon from "../../components/pieces/icons/SocialNinjaIcon.vue";
import { PlatformIconMixin } from '../../mixins/PlatformIconMixin';
export default {
    name: 'Onboard',
    mixins: [PlatformIconMixin],
    data() {
        return {
            loading: true,
            saving: false,
            has_pro: this.appVars?.has_pro || false,
            savingText: '',
            currentStep: 1,
            formData: {
                platform_name: '',
                review_platforms: '',
                platform_types: '',
                post_type: '',
                template: [],
                feed_platforms: '',
                chat_platforms: '',
                subscribe_to_newsletter: 'no',
                share_data: 'no'
            },
            platformTypes: [],
            reviewPlatforms: [],
            feedPlatforms: [],
            chatPlatforms: [],
            postTypeOptionsByPlatform: null,
            templateOptionsByPlatform: null,
            // Platform configuration for centralized management
            platformConfig: {},
            steps: [],
            settings: [],
            is_quick_builder: false,
            showUpgradeModal: false,
            selectedFeatureType: 'default',
            selectedPlatform: 'general',
            // Image preloading
            preloadedImages: new Set(),
            imagePreloadQueue: [],
        }
    },
    components: {
      ColorMode,
      ProCrownIcon,
      UpgradeToProModal,
      CircleCheckFilled,
      SocialNinjaIcon
    },
    computed: {
        currentPlatformConfig() {
            return this.platformConfig[this.formData.platform_types] || this.platformConfig.feeds || {};
        },

        getCurrentStepData() {
          const stepIndex = this.currentStep - 1;
          if(this.skipsPostTypeStep && this.currentStep === 3) {
            return this.steps[stepIndex + 1] || {};
          }
          return this.steps[stepIndex] || {};
        },
        isReviewsPlatform() {
            return this.formData.platform_types === 'reviews' || this.formData.platform_types === 'notifications';
        },
        isChatPlatform() {
            return this.formData.platform_types === 'chats';
        },
        isFeedsPlatform() {
            return this.formData.platform_types === 'feeds';
        },
        skipsPostTypeStep() {
            return this.currentPlatformConfig.skipsPostType;
        },
        progressPercentage() {
            if (!this.formData.platform_types) {
                return 0;
            }
            return (this.displayStep / this.totalSteps) * 100;
        },
        totalSteps() {
          return this.currentPlatformConfig.totalSteps || 4; // Default to 4 steps if not defined
        },
        canProceed() {
            switch (this.currentStep) {
                case 1:
                    return !!this.formData.platform_types;
                case 2:
                    return this.validateStep2Selection();
                case 3:
                    // Only for feeds platform
                    return this.skipsPostTypeStep ? true : !!this.formData.post_type;
                case 4:
                    return !!this.formData.template;
                default:
                    return true;
            }
        },
        displayStep() {
            return this.currentStep;
        },
        selectedFeedPlatform() {
            return this.formData.feed_platforms;
        },
        availablePostTypeOptions() {
            if (this.isReviewsPlatform) {
                return this.postTypeOptionsByPlatform['reviews'] || [];
            } else if (this.isChatPlatform) {
                return this.postTypeOptionsByPlatform['chats'] || [];
            } else {
                const platform = this.selectedFeedPlatform;
                return this.postTypeOptionsByPlatform[this.formData.platform_types][platform] || [];
            }
        },
        availableTemplateOptions() {
            if (this.isReviewsPlatform) {
                return this.templateOptionsByPlatform['reviews'] || [];
            } else if (this.isChatPlatform) {
                return this.templateOptionsByPlatform['chats'] || [];
            } else {
                const platform = this.selectedFeedPlatform;
                return this.templateOptionsByPlatform[this.formData.platform_types][platform] || [];
            }
        }
    },
    watch: {
        // Watch for changes in step or platform selection to trigger image preloading
        currentStep(newStep) {
            if (newStep === 3) {
                // Preload template images when approaching step 4
                this.$nextTick(() => {
                    this.preloadTemplateImages();
                });
            }
        },
        formData: {
            handler(newFormData, oldFormData) {
                // Preload images when platform selections change
                if (
                    newFormData.platform_types !== oldFormData.platform_types ||
                    newFormData.review_platforms !== oldFormData.review_platforms ||
                    newFormData.feed_platforms !== oldFormData.feed_platforms ||
                    newFormData.chat_platforms !== oldFormData.chat_platforms
                ) {
                    this.$nextTick(() => {
                        this.preloadTemplateImages();
                    });
                }
            },
            deep: true
        }
    },
    mounted() {
      this.loadOnboardingData();
    },
    methods: {
      async getConfig() {
            try {
                const response = await this.$get('onboarding/config');
                if (response && response.data) {
                  this.platformConfig = response.data.platform_config || {};
                  this.steps = response.data.steps || [];
                  this.settings = response.data.settings || {};
                  this.is_quick_builder = this.appVars && this.appVars.is_quick_builder;
                }
            } catch (error) {
                console.error('Failed to load platform configuration:', error);
            }
        },
        async loadOnboardingData() {
            try {
                // Load config first
                await this.getConfig();

                const response = await this.$get('onboarding');
                if (response && response.data) {
                    this.platformTypes = response.data.platform_types;
                    this.reviewPlatforms = response.data.review_platforms;
                    this.feedPlatforms = response.data.feed_platforms;
                    this.chatPlatforms = response.data.chat_platforms.chat_channels;
                    this.templateOptionsByPlatform = response.data.templates;
                    this.postTypeOptionsByPlatform = response.data.feed_types;
                    
                    // Start preloading platform icons immediately
                    this.preloadPlatformIcons();
                }
            } catch (error) {
                console.log('No saved onboarding data found');
            } finally {
                this.loading = false;
            }
        },
        preloadImage(src) {
            return new Promise((resolve, reject) => {
                if (this.preloadedImages.has(src)) {
                    resolve();
                    return;
                }

                const img = new Image();
                img.onload = () => {
                    this.preloadedImages.add(src);
                    resolve();
                };
                img.onerror = () => {
                    console.warn(`Failed to preload image: ${src}`);
                    reject();
                };
                img.src = src;
            });
        },
        async preloadImages(imageUrls) {
            const promises = imageUrls
                .filter(url => url && !this.preloadedImages.has(url))
                .map(url => this.preloadImage(url));
            
            try {
                await Promise.allSettled(promises);
            } catch (error) {
                console.warn('Some images failed to preload:', error);
            }
        },
        preloadPlatformIcons() {
            // Preload platform type icons (both light and dark)
            const platformIconUrls = this.platformTypes
                .flatMap(platform => [
                    platform.icon,
                    this.getPlatformIcon(platform.value, platform.icon)
                ])
                .filter(icon => icon);

            // Preload review platform icons (both light and dark)
            const reviewIconUrls = this.reviewPlatforms
                .flatMap(platform => [
                    platform.icon,
                    this.getPlatformIcon(platform.value, platform.icon)
                ])
                .filter(icon => icon);

            // Preload feed platform icons (both light and dark)
            const feedIconUrls = this.feedPlatforms
                .flatMap(platform => [
                    platform.icon,
                    this.getPlatformIcon(platform.value, platform.icon)
                ])
                .filter(icon => icon);

            // Preload chat platform icons (both light and dark)
            const chatIconUrls = this.chatPlatforms
                .flatMap(platform => [
                    platform.icon,
                    this.getPlatformIcon(platform.name, 'small', platform.icon)
                ])
                .filter(icon => icon);

            const allPlatformIcons = [
                ...platformIconUrls,
                ...reviewIconUrls,
                ...feedIconUrls,
                ...chatIconUrls
            ];

            // Remove duplicates
            const uniqueIcons = [...new Set(allPlatformIcons)];
            this.preloadImages(uniqueIcons);
        },
        preloadTemplateImages() {
            if (!this.templateOptionsByPlatform) return;

            let templateOptions = [];

            // Get template options based on current platform selection
            if (this.isReviewsPlatform) {
                templateOptions = this.templateOptionsByPlatform['reviews'] || [];
            } else if (this.isChatPlatform) {
                templateOptions = this.templateOptionsByPlatform['chats'] || [];
            } else if (this.isFeedsPlatform) {
                const platform = this.selectedFeedPlatform;
                if (platform && this.templateOptionsByPlatform[this.formData.platform_types]) {
                    templateOptions = this.templateOptionsByPlatform[this.formData.platform_types][platform] || [];
                }
            }

            // Extract image URLs from template options
            const templateImageUrls = templateOptions
                .map(template => template.icon)
                .filter(icon => icon);

            if (templateImageUrls.length > 0) {
                this.preloadImages(templateImageUrls);
            }
        },
        validateStep2Selection() {
          if (this.isReviewsPlatform) {
            return !!this.formData.review_platforms;
          } else if (this.isFeedsPlatform) {
            return !!this.formData.feed_platforms;
          } else if (this.isChatPlatform) {
            return !!this.formData.chat_platforms;
          }
          return !!this.formData.review_platforms || !!this.formData.feed_platforms || !!this.formData.chat_platforms;
        },
        nextStep() {
          if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.resetIrrelevantData();
            
            // Preload template images when reaching step 3 (preparing for step 4)
            if (this.currentStep === 3) {
                this.$nextTick(() => {
                    this.preloadTemplateImages();
                });
            }
          }
          this.saveProgress();
        },
        previousStep() {
            if (this.currentStep > 1) {
              this.currentStep--;
              this.resetIrrelevantData();
            }
        },
        resetIrrelevantData() {
        // Reset data based on current step and platform type
        if (this.currentStep === 1) {
          // Reset all platform selections when back to step 1
          this.formData.review_platforms = '';
          this.formData.feed_platforms = '';
          this.formData.chat_platforms = '';
          this.formData.post_type = '';
          this.formData.template = [];
          this.formData.platform_name = '';
        } else if (this.currentStep === 2) {
          // Reset platform-specific selections when platform type changes
          if (this.isReviewsPlatform) {
            this.formData.feed_platforms = '';
            this.formData.chat_platforms = '';
          } else if (this.isFeedsPlatform) {
            this.formData.review_platforms = '';
            this.formData.chat_platforms = '';
          } else if (this.isChatPlatform) {
            this.formData.review_platforms = '';
            this.formData.feed_platforms = '';
          }
          this.formData.post_type = '';
          this.formData.template = [];
          this.formData.platform_name = '';
        } else if (this.currentStep === 3) {
          // Reset template when post type might change
          this.formData.template = [];
        }
      },
        saveProgress() {
          this.formData.platform_name = this.formData.review_platforms || this.formData.feed_platforms || this.formData.chat_platforms || '';
        },
        async saveOnboardingWithTemplateId(templateId) {
          try {
            const dataWithTemplateId = {
              ...this.formData,
              template_id: templateId
            };

            await this.$post('onboarding', dataWithTemplateId);
          } catch (error) {
            console.error('Failed to save onboarding data with template ID:', error);
          }
        },

        async completeOnboarding() {
            this.saving = true;
            this.savingText = this.$t('Completing your setup...');

            try {
                // Set post_type for reviews platform to get demo content we need to set this
                if (this.isReviewsPlatform) {
                  this.formData.post_type = this.formData.platform_types;
                }

                // Create template/widget based on platform type
                const response = await this.createPlatformTemplate();

                if (response && response.template_id) {
                    // Save onboarding data with the created template ID
                    await this.saveOnboardingWithTemplateId(response.template_id);

                    const routeConfig = this.buildRouteConfig(response.template_id);

                  // Remove quick_builder parameter before navigation
                  const currentUrl = new URL(window.location.href);
                  if (currentUrl.searchParams.has('quick-builder')) {
                    currentUrl.searchParams.delete('quick-builder');
                    window.history.replaceState({}, '', currentUrl.toString());
                  }

                  // this.$emit('completed', routeConfig);

                  // Emit global event for parent app
                  window.dispatchEvent(new CustomEvent('onboarding-completed', {
                    detail: routeConfig
                  }));
                }
            } catch (error) {
                this.$notify.error(this.$t('Failed to complete setup. Please try again.'));
                console.error('Onboarding completion error:', error);
            } finally {
                this.saving = false;
            }
        },

        async createPlatformTemplate() {
            const endpoint = this.currentPlatformConfig.endpoint;
            return await this.$post(endpoint, {
                platform: this.formData.platform_name,
                onboarding: true,
            });
        },

        buildRouteConfig(templateId) {
            const config = this.currentPlatformConfig;
            let routeConfig = {
                params: {},
                query: { onboarding: true }
            };

            if (this.isChatPlatform) {
                routeConfig.name = config.routePrefix;
                routeConfig.params = { widget_id: templateId };
                routeConfig.query = { channels: this.formData.chat_platforms };
            } else if (this.isFeedsPlatform) {
                const routePlatform = this.getRoutePlatformName();
                routeConfig.name = `edit-${routePlatform}-template`;
                routeConfig.params = { template_id: templateId };
            } else {
                routeConfig.name = config.routePrefix;
                routeConfig.params = { template_id: templateId };
            }

            return routeConfig;
        },

        getRoutePlatformName() {
            return this.formData.platform_name === 'facebook_feed'
                ? 'facebook-feed'
                : this.formData.platform_name;
        },

        async skipOnboarding() {
            if (confirm(this.$t('Are you sure you want to skip the setup? You can always complete it later from the settings.'))) {
                this.saving = true;
                this.savingText = this.$t('Skipping setup...');

                try {
                    await this.$post('onboarding/skip');
                    window.location.reload();
                } catch (error) {
                    this.$notify.error(this.$t('Failed to skip setup'));
                } finally {
                    this.saving = false;
                }
            }
        },
        objectsEqual(a, b) {
          if (typeof a !== 'object' || typeof b !== 'object' || !a || !b) return false;
          const aKeys = Object.keys(a);
          const bKeys = Object.keys(b);
          if (aKeys.length !== bKeys.length) return false;
          for (let key of aKeys) {
            if (a[key] !== b[key]) return false;
          }
          return true;
        },
        handlePlatformSelection(option, type) {
            // Handle Pro features - show upgrade modal
            if (option.pro && !this.has_pro) {
                this.handleProFeatureSelection(option, type);
                return;
            }
            // Handle normal selection
            this.handleNormalSelection(option, type);
        },
        handleProFeatureSelection(option, type) {
            const proFeatureMap = {
                platform_types: () => {
                    if (option.value === 'notifications') {
                        this.selectedPlatform = 'general';
                        this.selectedFeatureType = 'notifications';
                    }
                },
                chat_platforms: () => {
                  if (this.formData.platform_types === 'chats') {
                    this.selectedPlatform = 'general';
                    this.selectedFeatureType = 'social_chats';
                  }
                },
                review_platforms: () => {
                  if (this.formData.platform_types === 'reviews') {
                    this.selectedPlatform = option.value;
                    this.selectedFeatureType = 'reviews';
                  }
                },
                post_type: () => {
                  if (this.formData.platform_types === 'feeds') {
                    this.selectedFeatureType = option.value;
                    this.selectedPlatform = this.formData.feed_platforms;
                  }
                },
                template: () => {
                    this.selectedFeatureType = 'template';
                    this.selectedPlatform = 'general';
                }
            };

            const handler = proFeatureMap[type];
            if (handler) {
                handler();
            }

            this.showUpgradeModal = true;
        },

        handleNormalSelection(option, type) {
            const selectionMap = {
                platform_types: () => {
                    this.formData.platform_types = option.value;
                },
                review_platforms: () => {
                    this.formData.review_platforms = option.value;
                },
                feed_platforms: () => {
                    this.formData.feed_platforms = option.value;
                },
                chat_platforms: () => {
                    this.formData.chat_platforms = option.id;
                },
                post_type: () => {
                    this.formData.post_type = option.value;
                },
                template: () => {
                    this.formData.template = option.value;
                }
            };

            const handler = selectionMap[type];
            if (handler) {
                handler();
                
                // Trigger template image preloading for platform selections
                if (['platform_types', 'review_platforms', 'feed_platforms', 'chat_platforms'].includes(type)) {
                    this.$nextTick(() => {
                        this.preloadTemplateImages();
                    });
                }
            }
        }
    }
}
</script>
