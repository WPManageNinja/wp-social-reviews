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
                <div v-if="feedConfig.feed_settings">
                  <div v-html="css"></div>
                </div>
                <el-main :class="feedConfig.feed_settings ? 'wpsr-editor-view-port' : ''" v-loading="loading" element-loading-text="Please wait for a few minutes, feeds are being collected.">
                    <div v-if="feedConfig.feed_settings">
                        <EditorViewInfoBar
                            type=""
                            :templateName="feedConfig.feed_settings.template"
                            :editorInfo="getEditorInfo()"
                            @handleDevice="handleDevice"
                        />
                    </div>

                    <DemoTemplateAlert v-if="feedConfig.feed_settings && feedConfig.feed_settings.display_onboarding_info" />

                    <div>
                        <component
                            :is="component"
                            :configs="feedConfig"
                            :image_settings="image_settings"
                        />
                    </div>
                </el-main>
                <el-aside class="wpsr-editor-sidebar">
                  <UpgradeApp v-if="!has_pro" />
                  <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
                    <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
                      <el-collapse accordion v-model="activeNames">
                        <YoutubeFeedEditor
                            :feed_config="feedConfig.feed_settings"
                            @fetchFeed="fetchVideos"
                            :image_settings="image_settings"
                            @update:feed_config="updateFeedConfig"
                        />
                      </el-collapse>
                    </el-tab-pane>
                    <el-tab-pane label="Connection" name="connection" class="wpsr-element-tab-item">
                        <YoutubeFeedConnection
                            :feed_config="feedConfig.feed_settings"
                            :image_settings="image_settings"
                            @update:feed_config="updateFeedConfig"
                            @fetchFeed="fetchVideos"
                        />
                    </el-tab-pane>
                    <el-tab-pane  name="style" class="wpsr-element-tab-item">
                      <template #label>
                        <span class="wpsr-tab-label-with-icon">
                          Style
                          <ProCrownIcon v-if="!has_pro" :width="16" :height="16" class="wpsr-tab-pro-icon" />
                        </span>
                      </template>
                      <el-collapse accordion v-model="activeNames">
                        <YoutubeStyleEditor
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

<script>
import UpgradeApp from "../../pieces/UpgradeApp";
import YoutubeFeedEditor from './editor-ui/YoutubeFeedEditor';
import YoutubeStyleEditor from './editor-ui/YoutubeStyleEditor';
import Template1 from './../templates/youtube/Template1';
import Template2 from './../templates/youtube/Template2';
import Template3 from './../templates/youtube/Template3';
import {templateMixin} from "../../../mixins/templateMixin";
import {commonMixin} from "../../../mixins/commonMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import {helperStyle} from "../../../mixins/helperStyle";
import EditorHeader from "../../pieces/EditorHeader";
import ResponsiveBar from '../../core-ui/editor/ResponsiveBar';
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';
import { markRaw } from 'vue';
import OnboardWelcomePopup from "../../pieces/OnboardWelcomePopup.vue";
import YoutubeFeedConnection from './editor-ui/YoutubeFeedConnection';
import DemoTemplateAlert from '../../pieces/DemoTemplateAlert.vue';
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

export default {
    name: "YoutubeView",
    mixins: [templateMixin, commonMixin, jsLibraryMixin, helperStyle],
    components: {
        OnboardWelcomePopup,
        EditorHeader,
        Template1,
        Template2,
        Template3,
        YoutubeFeedEditor,
        YoutubeStyleEditor,
        ResponsiveBar,
        EditorViewInfoBar,
        UpgradeToProButton,
        YoutubeFeedConnection,
        DemoTemplateAlert,
        UpgradeApp,
        ProCrownIcon
    },
    data() {
        return {
            loading: false,
            activeNames: ['1'],
            feedConfig: false,
            component: Template1,
            platform_section: 'youtube',
            templates: {
                'template1': markRaw(Template1),
                'template2': markRaw(Template2),
                'template3': markRaw(Template3),
            },
            videos: false,
            counter: 0,
            saving: false,
            activeTab: 'general',
            elements: [],
            style: null,
            tabletStyle: null,
            responsiveBarDevice: 'desktop',
            layoutType: '',
            showWelcomePopup: false,
            isInitialLoad: false,
        }
    },
    methods: {
        updateFeedConfig(newConfig) {
          if (this.feedConfig) {
            this.feedConfig.feed_settings = newConfig;
          }
        },
        handleDevice (val, activeDevice) {
          this.$emit('handleDevice' , val, activeDevice);
          this.handleLayout();
          if (this.feedConfig.feed_settings.layout_type === 'carousel') {
            this.responsiveBarDevice = val;
            this.activeCarousel();
          }
        },
        fetchVideos(val) {
          this.editEditorSettings('youtube');
        },
        handleLayout() {
          let template_type = this.feedConfig.feed_settings.layout_type;
          let that = this;

          if(template_type === 'carousel') {
            setTimeout(function() {
              that.activeCarousel();
            }, 100);
          }
          if(template_type === 'grid') {
            setTimeout(function() {
              that.destroy();
            }, 100);
          }
        },
        setupEventListeners() {
          // Create a callback function for the event
          this.handleDeviceCallback = (val, activeDevice) => {
            this.handleDevice(val, activeDevice);
          };

          // Use the event bus in Vue 3 compatible way
          if (this.$root.$on) {
            // Vue 2 style event bus
            this.$root.$on('handleDeviceResponsive', this.handleDeviceCallback);
          } else {
            // Vue 3 - use a global event bus or emitter library
            // If you have a global event bus like mitt, use it here
            // For example: this.$eventBus.on('handleDeviceResponsive', this.handleDeviceCallback);
            window.addEventListener('handleDeviceResponsive', this.handleDeviceCallback);
          }
        },
        cleanupEventListeners() {
          if (this.handleDeviceCallback) {
            if (this.$root.$off) {
              // Vue 2 style
              this.$root.$off('handleDeviceResponsive', this.handleDeviceCallback);
            } else {
              // Vue 3 style
              // If using a global event bus: this.$eventBus.off('handleDeviceResponsive', this.handleDeviceCallback);
              window.removeEventListener('handleDeviceResponsive', this.handleDeviceCallback);
            }
          }
        },
        getEditorInfo() {
          const count = this.feedConfig?.dynamic?.items?.length || 0;
          const postWord = count === 1 ? 'Youtube post' : 'Youtube posts';
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
                if(this.counter>1) {
                    this.isEditing = true;
                }
            },
            deep:true
        },
        //carousel settings
        'feedConfig.feed_settings.layout_type': {
            handler(val) {
                let layoutType = !this.has_pro ? 'grid' : val;
                this.feedConfig.feed_settings.layout_type = layoutType;
                this.layoutType = layoutType;
                this.handleLayout();
            },
            deep:true
        },
        'feedConfig.feed_settings.template': {
            handler(val) {
                let template = !this.has_pro ? 'template1' : val;
                this.feedConfig.feed_settings.template = template;
                this.component = '';
                this.component = this.templates[template];
                this.handleLayout();
            },
            deep:true,
        },
    },
    mounted() {
        this.getFeedSettings('youtube');
        this.setupEventListeners();
        
        // Show welcome popup if onboarding query param is present
        this.checkAndEditorWelcomePopup();
    },
    beforeUnmount() {
      this.cleanupEventListeners();
    }
}
</script>