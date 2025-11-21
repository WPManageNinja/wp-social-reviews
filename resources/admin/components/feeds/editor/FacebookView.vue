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
          <EventError v-if="shouldShowEventsError()"/>
          <FeedSteps v-if="!shouldShowEventsError() && shouldShowFeedFetchingSteps() && !userCameFromOnboarding()" />
          <div v-if="userCameFromOnboarding() || (!shouldShowFeedFetchingSteps() && !shouldShowEventsError())">
              <component
                  :is="component"
                  :feedConfig="feedConfig"
                  :image_settings="image_settings"
              />
          </div>
        </el-main>

        <el-aside class="wpsr-editor-sidebar">
          <UpgradeApp v-if="!has_pro" />
          <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
            <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
              <el-collapse accordion v-model="activeNames">
                <FacebookFeedEditor
                    :feed_config="feedConfig.feed_settings"
                    :pages="Object.values(pages)"
                    :image_settings="image_settings"
                    @fetchFeed="fetchVideos"
                    @update:feed_config="updateFeedConfig"
                />
              </el-collapse>
            </el-tab-pane>
            <el-tab-pane label="Connection" name="connection" class="wpsr-element-tab-item">
              <FacebookFeedConnection
                ref="facebookConnection"
                :feed_config="feedConfig.feed_settings"
                :image_settings="image_settings"
                @update:feed_config="updateFeedConfig"
                @fetchFeed="fetchVideos"
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
                <FacebookStyleEditor
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
import FacebookFeedEditor from './editor-ui/FacebookFeedEditor';
import EditorHeader from "../../pieces/EditorHeader";
import Template1 from './../templates/facebook/Template1';
import Template2 from "../templates/facebook/Template2";
import {templateMixin} from "../../../mixins/templateMixin";
import {commonMixin} from "../../../mixins/commonMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import FacebookStyleEditor from "./editor-ui/FacebookStyleEditor";
import ResponsiveBar from '../../core-ui/editor/ResponsiveBar';
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import {helperStyle} from "../../../mixins/helperStyle";
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';
import EventError from '../templates/facebook/elements/EventError.vue';
import FeedSteps from '../templates/facebook/elements/FeedSteps.vue';
import { markRaw } from 'vue';
import OnboardWelcomePopup from "../../pieces/OnboardWelcomePopup.vue";
import FacebookFeedConnection from './editor-ui/FacebookFeedConnection';
import DemoTemplateAlert from "../../pieces/DemoTemplateAlert.vue";
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

export default {
  name: "FacebookView",
  mixins: [templateMixin, commonMixin, jsLibraryMixin, helperStyle],
  components: {
    DemoTemplateAlert,
    OnboardWelcomePopup,
    Template1,
    Template2,
    FacebookFeedEditor,
    EditorHeader,
    FacebookStyleEditor,
    ResponsiveBar,
    EditorViewInfoBar,
    UpgradeToProButton,
    EventError,
    FeedSteps,
    FacebookFeedConnection,
    UpgradeApp,
    ProCrownIcon
  },
  data() {
    return {
      loading: false,
      activeNames: ['1'],
      feedConfig: false,
      component: '',
      videos: false,
      templates : {
        'template1' : markRaw(Template1),
        'template2' : markRaw(Template2),
      },
      layoutType: '',
      counter: 0,
      pages: [],
      platform_section: 'facebook_feed',
      activeTab: 'general',
      elements: [],
      style: null,
      responsiveBarDevice: 'desktop',
      showWelcomePopup: false,
      isInitialLoad: false
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
      this.layoutType = this.feedConfig.feed_settings.layout_type;
      this.handleLayout();
      if (this.layoutType === 'carousel') {
        this.responsiveBarDevice = val;
        this.activeCarousel();
      }
    },
    shouldShowFeedFetchingSteps(){
      if(this.feedConfig && this.feedConfig.feed_settings.source_settings.feed_type === 'event_feed') {
        return this.feedConfig.feed_settings.source_settings.selected_accounts_for_events &&
        this.feedConfig.feed_settings.source_settings.selected_accounts_for_events.length === 0;
      } else{
        return this.feedConfig.feed_settings.source_settings.selected_accounts &&
        this.feedConfig.feed_settings.source_settings.selected_accounts.length === 0;
      }
    },
    userCameFromOnboarding() {
      return this.$route.query.onboarding === 'true';
    },
    fetchVideos(val) {
      this.editEditorSettings(this.platform_section);
    },
    handleLayout() {
      let that = this;
      if(this.layoutType === 'carousel') {
        this.loading = true;
        setTimeout(function() {
          that.activeCarousel();
        }, 500);
      }

      if(this.layoutType === 'grid' || this.layoutType === 'timeline') {
        this.loading = true;
        setTimeout(function() {
          that.destroy();
        }, 500);
      }

      if(this.layoutType === 'masonry') {
        this.runMasonry();
      }
    },
    runMasonry() {
      let that = this;
      this.loading = true;
      let containerClass = '.wpsr-facebook-masonry-activate', feedWrapperClass = '.wpsr-fb-all-feed';
      setTimeout(function() {
        that.activeMasonry(containerClass, feedWrapperClass);
      }, 500);
    },
    shouldShowEventsError() {

      let hasFacebookEventsConnection = Object.values(this.pages).filter((page) => {
        return page.is_event_enabled == true;
      }).length > 0;


      return this.feedConfig && this.feedConfig.feed_settings.source_settings.feed_type === 'event_feed' && !hasFacebookEventsConnection;
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

      const itemsCount = this.feedConfig && this.feedConfig.dynamic && Array.isArray(this.feedConfig.dynamic.items)
        ? this.feedConfig.dynamic.items.length
        : 0;
      return 'Showing ' + itemsCount + ' Facebook Posts';
    },

    handleConfigurationParameter() {
      let configuration = null;
      const queryString = window.location.search;
      const urlParams = new URLSearchParams(queryString);
      configuration = urlParams.get('configuration');
      
      // Extract Facebook-specific parameters
      let platform = urlParams.get('platform');
      let oauth_token = urlParams.get('access_token');
      let auth_code = urlParams.get('auth_code');
      
      if (!configuration) {
        const hash = window.location.hash;
        const hashQuery = hash.split('?')[1];
        if (hashQuery) {
          const hashParams = new URLSearchParams(hashQuery);
          configuration = hashParams.get('configuration');
          
          // Also check hash params for Facebook parameters
          if (!oauth_token && !auth_code) {
            platform = hashParams.get('platform');
            oauth_token = hashParams.get('access_token');
            auth_code = hashParams.get('auth_code');
          }
        }
      }
      
      if(configuration && platform === 'facebook_feed'){
        // Switch to connection tab and open modal
        this.activeTab = 'connection';
        
        // Use nextTick to ensure the connection component is rendered
        this.$nextTick(() => {
          // Find the FacebookFeedConnection component and trigger modal
          const connectionComponent = this.$refs.facebookConnection;
          if (connectionComponent) {
            connectionComponent.facebookSettingModal = true;
            connectionComponent.verificationData = oauth_token || auth_code;
          }
        });
      }
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
    'feedConfig.feed_settings.layout_type' : {
      handler(val) {
        this.layoutType = val;
        this.handleLayout();
      },
      deep:true
    },
    'feedConfig.feed_settings.template': {
      handler(val) {
        let template = val
        this.feedConfig.feed_settings.template = template;
        this.component = '';
        this.component = this.templates[val];
        this.handleLayout();
      },
      deep:true,
    },
    'feedConfig.feed_settings.header_settings.account_to_show': {
      handler(newval, oldval) {
        if(oldval && oldval !== newval) {
          this.editEditorSettings('facebook_feed');
        }
      },
      deep:true
    },
    'feedConfig.feed_settings.source_settings.feed_type': {
      handler(val) {
        if(val === 'album_feed' && this.layoutType === 'carousel') {
          this.feedConfig.feed_settings.layout_type = 'grid';
        }
      },
      deep:true
    },
  },
  mounted() {
    let that = this;
    this.getFeedSettings(this.platform_section);

    this.setupEventListeners();

    // Show welcome popup if onboarding query param is present
    this.checkAndEditorWelcomePopup();

    // Handle configuration parameter to open connection modal
    this.handleConfigurationParameter();

    setTimeout(function() {
      that.showMoreLess();
    },100);
  },
  beforeUnmount() {
    // Clean up event listener
    this.cleanupEventListeners();
  }
}
</script>