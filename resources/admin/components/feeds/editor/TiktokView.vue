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
                            :feedConfig="feedConfig"
                            :translations="translations"
                            :image_settings="image_settings"
                        />
                  </div>
                </el-main>
                <el-aside class="wpsr-editor-sidebar">
                    <UpgradeApp v-if="!has_pro" />
                    <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
                        <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
                            <el-collapse accordion v-model="activeNames">
                                <TiktokFeedEditor
                                    :feed_config="feedConfig.feed_settings"
                                    :pages="Object.values(pages)"
                                    :image_settings="image_settings"
                                    @fetchFeed="fetchVideos"
                                    @update:feed_config="updateFeedConfig"
                                />
                            </el-collapse>
                        </el-tab-pane>
                        <el-tab-pane label="Connection" name="connection" class="wpsr-element-tab-item">
                            <TiktokFeedConnection
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
                                <TiktokStyleEditor
                                    v-if="feedConfig.styles_config"
                                    :feed_config="feedConfig.styles_config"
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
import TiktokFeedEditor from './editor-ui/TiktokFeedEditor';
import EditorHeader from "../../pieces/EditorHeader";
import Template1 from './../templates/tiktok/Template1';
import Template2 from "../templates/tiktok/Template2.vue";
import {templateMixin} from "../../../mixins/templateMixin";
import {commonMixin} from "../../../mixins/commonMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import TiktokStyleEditor from "./editor-ui/TiktokStyleEditor.vue";
import ResponsiveBar from '../../core-ui/editor/ResponsiveBar';
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import {helperStyle} from "../../../mixins/helperStyle";
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';
import { markRaw } from 'vue';
import OnboardWelcomePopup from "../../pieces/OnboardWelcomePopup.vue";
import TiktokFeedConnection from './editor-ui/TiktokFeedConnection';
import DemoTemplateAlert from "../../pieces/DemoTemplateAlert.vue";
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

export default {
    name: "TiktokView",
    mixins: [templateMixin, commonMixin, jsLibraryMixin, helperStyle],
    components: {
        DemoTemplateAlert,
        OnboardWelcomePopup,
        Template1,
        Template2,
        TiktokFeedEditor,
        EditorHeader,
        TiktokStyleEditor,
        ResponsiveBar,
        EditorViewInfoBar,
        UpgradeToProButton,
        TiktokFeedConnection,
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
            translations: [],
            platform_section: 'tiktok',
            activeTab: 'general',
            elements: [],
            style: null,
            responsiveBarDevice: 'desktop',
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
            this.layoutType = this.feedConfig.feed_settings.layout_type;
            this.handleLayout();
            if (this.layoutType === 'carousel') {
                this.responsiveBarDevice = val;
                this.activeCarousel();
            }
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

            if(this.layoutType === 'grid') {
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
          let containerClass = '.wpsr-tiktok-masonry-activate', feedWrapperClass = '.wpsr-tt-all-feed';
          setTimeout(function() {
            that.activeMasonry(containerClass, feedWrapperClass);
          }, 500);
        },
        setupEventListeners() {
          this.handleDeviceCallback = (val, activeDevice) => {
            this.handleDevice(val, activeDevice);
          };
          if (this.$root.$on) {
            this.$root.$on('handleDeviceResponsive', this.handleDeviceCallback);
          } else {
            window.addEventListener('handleDeviceResponsive', this.handleDeviceCallback);
          }
        },
        cleanupEventListeners() {
          if (this.handleDeviceCallback) {
            if (this.$root.$off) {
              this.$root.$off('handleDeviceResponsive', this.handleDeviceCallback);
            } else {
              window.removeEventListener('handleDeviceResponsive', this.handleDeviceCallback);
            }
          }
        },
        getEditorInfo() {
          const count = this.feedConfig?.dynamic?.items?.length || 0;
          const postWord = count === 1 ? 'TikTok post' : 'TikTok posts';
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
                    this.editEditorSettings('tiktok');
                }
            },
            deep:true
        }
    },
    mounted() {
        let that = this;
        this.getFeedSettings(this.platform_section);
        this.setupEventListeners();
        
        // Show welcome popup if onboarding query param is present
        this.checkAndEditorWelcomePopup();
        
        setTimeout(function() {
            that.showMoreLess();
        },100);
    },
    beforeUnmount() {
        this.cleanupEventListeners();
    }
}
</script>