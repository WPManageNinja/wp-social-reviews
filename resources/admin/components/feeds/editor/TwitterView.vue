<template>
    <div class="wpsr_editor" v-if="feedConfig" :class="{ 'wpsr-blurred': showWelcomePopup }">
        <editor-header
            :title="template_title"
            :platform_section="platform_section"
            :route_name="'templates'"
            :route_title="'All Templates'"
            @update-settings="updateEditorSettings(platform_section)"
        ></editor-header>

        <div class="wpsr_editor_wrapper">
            <el-container>
              <div v-if="has_pro">
                <div v-html="css"></div>
              </div>
                <el-main :class="has_pro ? 'wpsr-editor-view-port' : ''">
                    <div v-if="has_pro && feedConfig.feed_settings">
                        <EditorViewInfoBar
                            type=""
                            :templateName="feedConfig.feed_settings.layout_type"
                            :editorInfo="getEditorInfo()"
                            @handleDevice="handleDevice"
                        />
                    </div>
                    <DemoTemplateAlert v-if="feedConfig.feed_settings && feedConfig.feed_settings.display_onboarding_info"/>
                    <div>
                        <component
                            :is="component"
                            :feedConfigs="feedConfig"
                            @loadingAction="loadingInChangeType"
                        />
                    </div>
                </el-main>
                <el-aside class="wpsr-editor-sidebar">
                  <UpgradeApp v-if="!has_pro" />
                  <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
                    <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
                      <el-collapse accordion v-model="activeNames">
                        <TwitterFeedEditor
                            v-if="feedConfig.feed_settings"
                            :feed_config="feedConfig.feed_settings"
                            :current_api_version="feedConfig.api_version"
                            @fetchFeed="fetchTweets"
                            @update:feed_config="updateFeedConfig"
                        />
                      </el-collapse>
                    </el-tab-pane>
                    <el-tab-pane label="Connection" name="connection" class="wpsr-element-tab-item">
                        <TwitterFeedConnection
                            :feed_config="feedConfig.feed_settings"
                            :image_settings="image_settings"
                            @update:feed_config="updateFeedConfig"
                            @fetchFeed="fetchTweets"
                        />
                    </el-tab-pane>
                    <el-tab-pane name="style" class="wpsr-element-tab-item">
                      <template #label>
                        <span class="wpsr-tab-label-with-icon">
                          Style
                          <ProCrownIcon v-if="!has_pro" :width="16" :height="16" class="wpsr-tab-pro-icon" />
                        </span>
                      </template>
                      <el-collapse accordion v-model="activeNames">
                        <div class="wpsr-styling-divider"/>
                        <TwitterStyleEditor
                            v-if="feedConfig.styles_config"
                            :feed_config="feedConfig.styles_config"
                            :feed_settings="feedConfig.feed_settings"
                            :elements="elements"
                            @templateCss="templateCss"
                        />
                      </el-collapse>
                    </el-tab-pane>
                  </el-tabs>
                </el-aside>
            </el-container>
        </div>
    </div>
    <OnboardWelcomePopup
        v-if="showWelcomePopup"
    />
</template>

<script type="text/babel">
import UpgradeApp from "../../pieces/UpgradeApp.vue";
import Template1 from './../templates/twitter/Template1';
import Template2 from './../templates/twitter/Template2';
import EditorHeader from "../../pieces/EditorHeader";
import TwitterFeedEditor from './editor-ui/TwitterFeedEditor';
import TwitterStyleEditor from './editor-ui/TwitterStyleEditor';
import ResponsiveBar from '../../core-ui/editor/ResponsiveBar';
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import {helperStyle} from "../../../mixins/helperStyle";
import {templateMixin} from "../../../mixins/templateMixin";
import {commonMixin} from "../../../mixins/commonMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';
import { markRaw } from 'vue';
import OnboardWelcomePopup from "../../pieces/OnboardWelcomePopup.vue";
import TwitterFeedConnection from './editor-ui/TwitterFeedConnection';
import DemoTemplateAlert from "../../pieces/DemoTemplateAlert.vue";
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

export default {
    name: "TwitterView",
    mixins: [helperStyle, templateMixin, commonMixin, jsLibraryMixin],
    components: {
      DemoTemplateAlert,
        OnboardWelcomePopup,
        EditorHeader,
        Template1,
        Template2,
        TwitterFeedEditor,
        TwitterStyleEditor,
        ResponsiveBar,
        EditorViewInfoBar,
        UpgradeToProButton,
        TwitterFeedConnection,
        UpgradeApp,
        ProCrownIcon
    },
    data() {
        return {
            loading: false,
            activeNames: ['1'],
            activeTab: 'general',
            feedConfig:false,
            component: Template1,
            platform_section: 'twitter',
            templateId: this.$route.params.template_id,
            feedDialogVisible: false,
            templates : {
              'standard': markRaw(Template1),
              'masonry': markRaw(Template2),
              'carousel': markRaw(Template2),
            },
            counter: 0,
            isEditing: false,
            saving: false,
            elements: [],
            tabletStyle: null,
            style: null,
            responsiveBarDevice: 'desktop',
            showWelcomePopup: false,
            isInitialLoad: false
        }
    },

    methods: {
        getDefaultFeedSettings() {
            return {
                header_settings: {
                    show_header: 'false',
                },
                filters: {},
                advance_settings: {},
                follow_button_settings: {},
                pagination_settings: {},
                additional_settings: {},
                carousel_settings: {},
                popup_settings: {},
            };
        },
        handleDevice (val, activeDevice) {
          this.$emit('handleDevice' , val, activeDevice);
          this.layoutType = this.feedConfig.feed_settings.layout_type;
          this.handleLayout();
          if (this.layoutType === 'carousel') {
            this.responsiveBarDevice = val;
            this.activeCarousel();
          }
        },
        loadingInChangeType(val) {
            this.loading = val;
        },
        fetchTweets(val) {
            let settings    = this.feedConfig.feed_settings.additional_settings;
            let feed_type   = settings.feed_type;
            let hashtag     = settings.hashtag;

            if(this.has_pro === false && (feed_type === 'user_mentions' || feed_type === 'hashtag')) {
                this.handlePro();
            } else {
                if(feed_type) {
                    this.editEditorSettings(this.platform_section);
                } else {
                    let message = 'Please fill the field correctly!';
                    this.handleError(message);
                }
            }
        },
        handleLayout() {
            this.loading = true;
            this.$nextTick(() => {
                // Add a small delay to ensure DOM is properly updated
                setTimeout(() => {
                    if(this.layoutType === 'carousel') {
                        this.activeCarousel();
                    } else if(this.layoutType === 'grid') {
                        this.destroy();
                    } else if(this.layoutType === 'masonry') {
                        this.runMasonry();
                    }
                    // Only set loading to false after layout is processed
                    if(this.layoutType !== 'carousel') {
                        this.loading = false;
                    }
                }, 50);
            });
        },
        runMasonry() {
          this.loading = true;
          const containerClass = '.wpsr-twitter-masonry-activate';
          const feedWrapperClass = '.wpsr-twitter-all-tweets';

          this.$nextTick(() => {
            // This ensures we run this code only after Vue has updated the DOM.
            const container = document.querySelector(containerClass + ' ' + feedWrapperClass);
            if (container) {
                this.activeMasonry(containerClass, feedWrapperClass);
            } else {
                console.warn('Masonry container not found. The layout might not apply correctly.');
                this.loading = false;
            }
          });
        },
        updateFeedConfig(newConfig) {
            if (this.feedConfig) {
                const defaults = this.getDefaultFeedSettings();
                const existingSettings = this.feedConfig.feed_settings || {};
                
                // Create a new merged object
                const mergedSettings = {
                    ...defaults,
                    ...existingSettings,
                    ...newConfig
                };

                // Ensure all nested objects are also deeply merged, not just replaced
                Object.keys(defaults).forEach(key => {
                    if (typeof defaults[key] === 'object' && defaults[key] !== null && !Array.isArray(defaults[key])) {
                        mergedSettings[key] = {
                            ...defaults[key],
                            ...(existingSettings[key] || {}),
                            ...(newConfig[key] || {})
                        };
                    }
                });

                this.feedConfig.feed_settings = mergedSettings;
            }
        },
        preview_posts() {
            if(this.feed && this.feed.posts){
                return this.feed.posts;
            }
            return [];
        },
        has_posts() {
            if(this.feed && this.feed.posts){
                return true;
            }
            return false;
        },
        getEditorInfo() {
            const count = this.feedConfig?.dynamic?.items?.length || 0;
            const postWord = count === 1 ? 'X (Twitter) post' : 'X (Twitter) posts';
            return `Showing ${count} ${postWord}`;
        }
    },
    watch: {
      'loading': {
        handler(val) {
          // Only show popup on initial load, not on subsequent loads
          if (val && !this.isInitialLoad) {
            this.showWelcomePopup = true;
            this.isInitialLoad = true;
          } else if (!val) {
            this.showWelcomePopup = false;
          }
        },
        immediate: true
      },
      'feedConfig': {
        handler(val) {
          this.counter++;
          if(this.counter > 1) {
            this.isEditing = true;
          }
        },
        deep:true
      },
      'feedConfig.feed_settings.layout_type': {
        handler(val) {
          this.layoutType = val;
          this.component  = '';
          this.component  = this.templates[val];
          this.handleLayout();
        },
        deep:true
      }
    },
    mounted() {
        this.getFeedSettings('twitter');

        // Show welcome popup if onboarding query param is present
        this.checkAndEditorWelcomePopup();

        const handleDeviceCallback = (val, activeDevice) => {
            this.handleDevice(val, activeDevice);
        };

        // Store callback reference
        this._handleDeviceCallback = handleDeviceCallback;

        // Use Vue's root event methods
        this.$root.$on('handleDeviceResponsive', handleDeviceCallback);
    },
    beforeUnmount() {
        // Clean up event listener
        if (this._handleDeviceCallback) {
            this.$root.$off('handleDeviceResponsive', this._handleDeviceCallback);
        }
    }
}
</script>