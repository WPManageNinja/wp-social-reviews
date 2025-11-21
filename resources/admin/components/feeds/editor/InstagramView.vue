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
                            @add_selected_shoppable_feed="addSelectedShoppableFeed"
                            @edit_shoppable_feed="editShoppableFeed"
                            @remove_shoppable_feed="removeShoppableFeed"
                        />
                    </div>

                    <el-dialog
                        :before-close="handleCloseShoppable"
                        title="Add Promotional URL"
                        v-model="show_shoppable_popup"
                        :append-to-body="true"
                        width="30%"
                        class="wpsr-modal wpsr-connection-modal"
                    >
                      <ShoppableModal
                          :shoppable_fields="shoppable_fields"
                          :post_types="post_types"
                          :posts="posts"
                          @add_template_settings="addShoppableTemplateSettings"
                          @close_shoppable_modal="show_shoppable_popup = false"
                          @on_post_source_change="onPostSourceChange"
                          :errors="errors"
                      />
                    </el-dialog>
                </el-main>
                <el-aside class="wpsr-editor-sidebar">
                  <UpgradeApp v-if="!has_pro" />
                  <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
                    <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
                      <el-collapse accordion v-model="activeNames">
                        <InstagramFeedEditor
                            :feed_config="feedConfig.feed_settings"
                            :image_settings="image_settings"
                            :connected_ids="Object.values(feedConfig.connected_ids)"
                            :header_connected_ids="Object.values(feedConfig.header_connected_ids)"
                            @update:feed_config="updateFeedConfig"
                            @fetchFeed="fetchVideos"
                        />
                      </el-collapse>
                    </el-tab-pane>

                    <el-tab-pane label="Connection" name="connection" class="wpsr-element-tab-item">
                      <InstagramFeedConnection
                        ref="instagramConnection"
                        :feed_config="feedConfig.feed_settings"
                        :image_settings="image_settings"
                        :connected_ids="Object.values(feedConfig.connected_ids)"
                        :header_connected_ids="Object.values(feedConfig.header_connected_ids)"
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
                        <InstagramStyleEditor
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
import InstagramFeedEditor from './editor-ui/InstagramFeedEditor';
import InstagramFeedConnection from './editor-ui/InstagramFeedConnection';
import Template1 from './../templates/instagram/Template1';
import Template2 from './../templates/instagram/Template2';
import {templateMixin} from "../../../mixins/templateMixin";
import {commonMixin} from "../../../mixins/commonMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import EditorHeader from "../../pieces/EditorHeader";
import ResponsiveBar from '../../core-ui/editor/ResponsiveBar';
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import {helperStyle} from "../../../mixins/helperStyle";
import InstagramStyleEditor from "./editor-ui/InstagramStyleEditor";
import ShoppableModal from "../../pieces/ShoppableModal";
import Errors from "../../../errors/Errors";
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';
import { markRaw } from 'vue';
import OnboardWelcomePopup from "../../pieces/OnboardWelcomePopup.vue";
import DemoTemplateAlert from "../../pieces/DemoTemplateAlert.vue";
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

let fields = {
  'source_type': 'custom_url',
  'url_settings': {
    'url': '',
    'url_title': '',
    'id' : null,
    'open_in_new_tab': true,
    'text': 'Buy Now'
  },
};

export default {
    name: "InstagramView",
    mixins: [templateMixin, commonMixin, jsLibraryMixin, helperStyle],
    components: {
      DemoTemplateAlert,
      InstagramStyleEditor,
      InstagramFeedConnection,
      EditorHeader,
      Template1,
      Template2,
      InstagramFeedEditor,
      ResponsiveBar,
      EditorViewInfoBar,
      ShoppableModal,
      UpgradeToProButton,
      OnboardWelcomePopup,
      UpgradeApp,
      ProCrownIcon
    },

    data() {
        return {
            prevLink: '',
            posts: [],
            image_settings: [],
            loading: false,
            activeNames: ['1'],
            feedConfig: false,
            component: Template1,
            platform_section: 'instagram',
            templates : {
                'template1' : markRaw(Template1),
                'template2' : markRaw(Template2),
            },
            videos: false,
            layoutType: '',
            counter: 0,
            isEditing: false,
            saving: false,
            activeTab: 'general',
            style: null,
            elements: [],
            styleConfig: null,
            //shoppableal options
            show_shoppable_popup: false,
            selectedFeed: null,
            shoppable_fields: {...fields},
            search_options: [],
            post_types: [],
            post_type_default: {
              'name': 'custom_url',
              'title': 'Custom URL'
            },
            errors: new Errors(),
            responsiveBarDevice: 'desktop',
            has_pro: this.$root.appVars?.has_pro || false,
            handleDeviceCallback: null,
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

      getPostsByPostType(source_type){
        let tempId = this.shoppable_fields.url_settings.id;
        this.shoppable_fields.url_settings.id = null;
        this.$get(`shoppable/posts`, {
          postType: source_type
        }).then(response => {
          if( response ) {
            this.posts = response.posts.splice(1);
            let that = this;
            setTimeout(function() {
              that.shoppable_fields.url_settings.id = tempId;
            }, 50);
          }
        }).catch(errors => {
          this.handleError(errors);
        }).always(() => {
        });
      },

      addSelectedShoppableFeed(feed) {
        this.errors.record({});
        this.show_shoppable_popup = true;
        this.shoppable_fields = JSON.parse(JSON.stringify(fields));
        this.selectedFeed = feed;
      },

      editShoppableFeed(feed) {
        this.errors.record({});
        this.show_shoppable_popup = true;

        this.selectedFeed = feed;
        if (this.feedConfig.feed_settings.shoppable_settings.shoppable_feeds[feed.username]) {
          if (this.feedConfig.feed_settings.shoppable_settings.shoppable_feeds[feed.username][feed.id]) { //if already added this feed then remove it
            this.shoppable_fields = this.feedConfig.feed_settings.shoppable_settings.shoppable_feeds[feed.username][feed.id];
          }
        }

        let source_type = this.shoppable_fields.source_type;
        this.getPostsByPostType(source_type);
      },

      removeShoppableFeed(feed) {
        delete this.feedConfig.feed_settings.shoppable_settings.shoppable_feeds[feed.username][feed.id];
        this.updateEditorSettings('instagram');
      },

      handleCloseShoppable() {
        this.shoppable_fields = {...fields};
        this.show_shoppable_popup = false;
        this.selectedFeed = null;
      },

      //add feed to shoppable
      addShoppableTemplateSettings() {
        let template_id = this.$route.params.template_id;
        this.saving = true;
        this.$put(`shoppable/template-settings/${template_id}`, {
          platform: 'instagram',
          shoppable_fields: JSON.stringify(this.shoppable_fields),
          settings: JSON.stringify(this.feedConfig),
          feed: JSON.stringify(this.selectedFeed)
        }).then(response => {
          if (response.data) {
            this.show_shoppable_popup = false;
            this.getFeedSettings('instagram');
          }
        }).catch((errors) => {
          //this.handleError(errors);
          this.errors.record(errors.responseJSON);
        }).always(() => {
          this.saving = false;
        });
      },

      handleDevice(val, activeDevice) {
        this.$emit('handleDevice', val, activeDevice);
        this.layoutType = this.feedConfig.feed_settings.layout_type;
        this.handleLayout();
        if (this.layoutType === 'carousel') {
          this.responsiveBarDevice = val;
          this.activeCarousel();
        }
      },

      fetchVideos() {
        this.editEditorSettings('instagram');
      },

      handleLayout() {
        let that = this;
        if (this.layoutType === 'carousel') {
          this.loading = true;
          setTimeout(function() {
            that.activeCarousel();
          }, 500);
        }

        if (this.layoutType === 'grid') {
          this.loading = true;
          setTimeout(function() {
            that.destroy();
          }, 500);
        }

        if (this.layoutType === 'masonry') {
          this.runMasonry();
        }
      },

      runMasonry() {
        let that = this;
        this.loading = true;
        let containerClass = '.wpsr-instagram-masonry-activate', feedWrapperClass = '.wpsr-ig-all-feed';
        setTimeout(function() {
          that.activeMasonry(containerClass, feedWrapperClass);
        }, 500);
      },

      onPostSourceChange(){
        if(this.shoppable_fields.source_type !== this.selectedFeed.shoppable_options.url_settings.source_type){
          this.shoppable_fields.url_settings.id = null;
          this.shoppable_fields.url_settings.url = '';
          this.shoppable_fields.url_settings.url_title = '';
        }
        this.getPostsByPostType(this.shoppable_fields.source_type);
        this.shoppable_fields.link = this.selectedFeed.shoppable_options.post_type === this.shoppable_fields.source_type ? this.selectedFeed.shoppable_options.link : '';
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
          const postWord = count === 1 ? 'Instagram post' : 'Instagram posts';
          return `Showing ${count} ${postWord}`;

      },

      handleConfigurationParameter() {
        let configuration = null;
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        configuration = urlParams.get('configuration');
        
        // Extract Instagram-specific parameters
        let platform = urlParams.get('platform');
        let credentials_type = urlParams.get('credentialsType');
        let oauth_token = platform === 'google' ? urlParams.get('code') : urlParams.get('access_token');
        let expires_in = urlParams.get('expires_in');
        let auth_code = urlParams.get('auth_code');
        
        if (!configuration) {
            const hash = window.location.hash;
            const hashQuery = hash.split('?')[1];
            if (hashQuery) {
                const hashParams = new URLSearchParams(hashQuery);
                configuration = hashParams.get('configuration');
                
                // Also check hash params for Instagram parameters
                if (!oauth_token && !auth_code) {
                    platform = hashParams.get('platform');
                    oauth_token = hashParams.get('access_token');
                    auth_code = hashParams.get('auth_code');
                    credentials_type = hashParams.get('credentialsType');
                    expires_in = hashParams.get('expires_in');
                }
            }
        }
        
        if(configuration && platform === 'instagram'){
            // Switch to connection tab and open modal
            this.activeTab = 'connection';
            
            // Use nextTick to ensure the connection component is rendered
            this.$nextTick(() => {
                // Find the InstagramFeedConnection component and trigger modal
                const connectionComponent = this.$refs.instagramConnection;
                if (connectionComponent) {
                    connectionComponent.instagramSettingModal = true;
                    connectionComponent.verificationData = oauth_token || auth_code;
                    connectionComponent.credentialsType = credentials_type;
                    connectionComponent.expiresIn = expires_in;
                    
                    // Also trigger the businessAccountsModal if it's a business account
                    if (credentials_type === 'business') {
                        // Wait a bit more for the Instagram component to be fully rendered
                        setTimeout(() => {
                            const instagramComponent = connectionComponent.$refs.instagramConfig;
                            if (instagramComponent) {
                                // Wait for business accounts to be loaded
                                const checkBusinessAccounts = () => {
                                    if (instagramComponent.businessAccounts && Object.keys(instagramComponent.businessAccounts).length > 0) {
                                        instagramComponent.businessAccountsModal = true;
                                    } else {
                                        setTimeout(checkBusinessAccounts, 500);
                                    }
                                };
                                checkBusinessAccounts();
                            }
                        }, 500);
                    }
                }
            });
        }
      },
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
            if (this.counter > 1) {
              this.isEditing = true;
            }
          },
          deep: true
        },
        'feedConfig.feed_settings.post_settings.display_mode': {
          handler(newval, oldval) {
            if(oldval && (((oldval != 'popup') && newval === 'popup') || ((oldval != 'instagram') && newval === 'instagram'))) {
              if (this.feedConfig.feed_settings.shoppable_settings.enable_shoppable === 'true' || this.feedConfig.feed_settings.shoppable_settings.include_shoppable_by_hashtags === 'true') {
                this.editEditorSettings('instagram');
              }
            }
          },
          deep: true
        },
        // 'feedConfig.feed_settings.filters.total_posts_number': {
        //   handler(val) {
        //     if (val && this.isEditing) {
        //       console.log(newVal, oldVal);
        //       return;
        //       this.editEditorSettings('instagram');
        //     }
        //   },
        //   deep: true
        // },
        'feedConfig.feed_settings.layout_type': {
            handler(val) {
                this.layoutType = val;
                this.handleLayout();
            },
            deep: true
        },
        'feedConfig.feed_settings.template': {
            handler(val) {
              let template = val
                this.feedConfig.feed_settings.template = template;
                this.component = '';
                this.component = this.templates[val];
                this.handleLayout();
            },
            deep: true,
        },
        'feedConfig.feed_settings.header_settings.account_to_show': {
          handler(newVal, oldVal) {
            if(newVal && oldVal && JSON.stringify(newVal) !== JSON.stringify(oldVal)) {
              this.editEditorSettings('instagram');
            }
          },
          deep: true
        },
        // 'feedConfig.feed_settings.source_settings.account_ids': {
        //   handler(newVal, oldVal) {
        //     console.log('account_ids changed in parent:', newVal);
        //     if (newVal && oldVal && JSON.stringify(newVal) !== JSON.stringify(oldVal)) {
        //       // Account IDs have changed, may need to refresh data
        //       this.editEditorSettings('instagram');
        //     }
        //   },
        //   deep: true
        // }
    },

    mounted() {
        let that = this;
        this.getFeedSettings('instagram');
        this.setupEventListeners();

        // Show welcome popup if onboarding query param is present
        this.checkAndEditorWelcomePopup();

        // Handle configuration parameter to open connection modal
        this.handleConfigurationParameter();

        setTimeout(function() {
            that.showMoreLess();
        }, 100);
    },

    beforeUnmount() {
        this.cleanupEventListeners();
    }
}
</script>