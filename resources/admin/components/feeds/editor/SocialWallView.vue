<template>
  <div class="wpsr_editor" v-if="feedConfig">
    <editor-header
        :title="template_title"
        platform_section="social_wall"
        :route_name="'templates'"
        shortcode_name="wp_social_ninja_wall_feed"
        :route_title="'All Templates'"
        @update-settings="updatedSocialWallSettings"
    ></editor-header>
    <div class="wpsr_editor_wrapper">
      <el-container>
        <div v-if="feedConfig.feed_settings">
          <div v-html="css"></div>
        </div>
        <el-main class="wpsr-mt-50" :class="feedConfig.feed_settings ? 'wpsr-editor-view-port' : ''">
            <div v-if="feedConfig.feed_settings">
                <EditorViewInfoBar
                    type=""
                    :templateName="feedConfig.feed_settings.template"
                    :editorInfo="getEditorInfo()"
                    @handleDevice="handleDevice"
                />
            </div>
            <div class="tabs" v-if="savedSocialWallConfig.social_wall_list && savedSocialWallConfig.social_wall_list.length">
              <button
                  v-for="tab in savedSocialWallConfig.social_wall_list"
                  :key="tab.template.value"
                  :class="['tab', { active: socialWallActiveTab === tab.name && selectedTemplate === tab.template.value }]"
                  @click="setActiveTab(tab.name, tab.template.value)"
              >
                <i :class="tab.icon"></i> {{ tab.label }}
              </button>
            </div>
            <div id="tab-content" :style="loading ? 'height:500px;' : ''" v-loading="loading" element-loading-text="Please wait for a few minutes, feeds are being collected.">
              <component
                  v-for="tab in ['instagram', 'tiktok', 'facebook_feed', 'youtube', 'twitter']"
                  v-if="socialWallActiveTab === tab && feedConfig && feedConfig.feed_settings"
                  :key="tab"
                  :is="component"
                  v-bind="getComponentProps(tab)"
              />
            </div>
        </el-main>

        <el-aside class="wpsr-editor-sidebar">
          <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
            <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
              <el-collapse accordion v-model="activeAccordion">
                <el-collapse-item name="1">
                  <template slot="title">
                    {{ $t('Social Wall') }}
                  </template>
<!--                  <p>Select platform and feed template you want to add to the wall.</p>-->

<!--                  <div v-if="openPlatformModal" class="wpsr-social-channels-popup">-->
<!--                    <div class="wpsr-social-channels-bg"></div>-->
<!--                    <div class="wpsr-social-channels" v-if="selectWallPlatform">-->
<!--                      <div class="wpsr-social-channel-group-header">-->
<!--                        <el-button class="wpsr-channels-modal-cancel" @click="openPlatformModal = false">{{ $t('Cancel') }}</el-button>-->
<!--                        <h3>{{ $t('Add Platform') }}</h3>-->
<!--                      </div>-->
<!--                      <div class="wpsr-social-channel-group" style="height: auto">-->
<!--                        <social-wall-channel-button-->
<!--                            v-for="(channel, key) in platforms"-->
<!--                            :key="key"-->
<!--                            :channel="channel"-->
<!--                            :assets_url="assets_url"-->
<!--                            :selectWallChannel="() => selectWallPlatform(channel)"-->
<!--                        />-->
<!--                      </div>-->
<!--                    </div>-->
<!--                  </div>-->

<!--                  <social-wall-channel-modal-->
<!--                      :modalVisible="platformForm"-->
<!--                      :selectedPlatform="selectedPlatform"-->
<!--                      :socialWallTemplates="socialWallTemplates"-->
<!--                      :editPlatformTemplate="editPlatformTemplate"-->
<!--                      @close="closePlatformModal"-->
<!--                      @save="saveWallPlatform"-->
<!--                  />-->

                  <div class="wpsr-repeater-fields-wrapper">
                  <social-wall-channel-list
                      :modalVisible="true"
                      :platforms="platforms"
                      :openPlatformModal="openPlatformModal"
                      :selectedPlatform="selectedPlatform"
                      :socialWallTemplates="socialWallTemplates"
                      :editPlatformTemplate="editPlatformTemplate"
                      @selectWallChannel="selectWallPlatform"
                      @closeModal="closePlatformModal"
                      @save="saveWallPlatform"

                      :channels="asideSocialWallConfig"
                      :loading="saving"
                      :assets_url="assets_url"
                      @edit="openEditPlatformModal"
                      @remove="removePlatformTemplate"
                  />

                  <div class="wpsr-social-channel-save">
                    <el-button type="normal" size="small" @click="addNewWall" :icon="!openPlatformModal ? 'el-icon-circle-plus-outline' : 'el-icon-remove-outline'">
                      {{ $t('Add to Wall') }}
                    </el-button>
                  </div>

                  <div class="wpsr-repeater-fields" v-if="openPlatformModal && selectedPlatform">
<!--                    <h3 class="wpsr-repeater-fields-section-title">Add new Platform</h3>-->
                    <social-wall-channel-modal
                        :platforms="platforms"
                        :selectedPlatform="selectedPlatform"
                        :socialWallTemplates="socialWallTemplates"
                        :editPlatformTemplate="editPlatformTemplate"
                        @selectWallChannel="selectWallPlatform"
                        @close="closePlatformModal"
                        @save="saveWallPlatform"
                    />
                  </div>
                  </div>
                </el-collapse-item>
              </el-collapse>
            </el-tab-pane>
          </el-tabs>
        </el-aside>
      </el-container>
    </div>
  </div>
</template>
<style>
.tabs {
  display: flex;
  margin: 15px auto;
  justify-content: center;
}
.tab {
  background-color: #f9f9f9;
  border: 1px solid #ccc;
  border-radius: 5px;
  padding: 10px 20px;
  font-size: 14px;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: background-color 0.3s ease;
  margin-right: 5px;
  margin-left: 5px;
}
.tab i {
  margin-right: 8px;
}
.tab:hover {
  background-color: #eee;
}
.tab.active {
  background-color: #000;
  border-color: #000;
  color: #fff;
}
</style>
<script type="text/babel">
import FeedEditorGroup from './../../core-ui/editor/EditorGroup';
import EditorHeader from "../../pieces/EditorHeader";
import {templateMixin} from "../../../mixins/templateMixin";
import {tableMixin} from "../../../mixins/tableMixin";
import {commonMixin} from "../../../mixins/commonMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import ResponsiveBar from '../../core-ui/editor/ResponsiveBar';
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import {helperStyle} from "../../../mixins/helperStyle";
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';

import SocialWallChannelModal from '../../pieces/SocialWallChannelModal';
import SocialWallChannelButton from '../../pieces/SocialWallChannelButton';
import SocialWallChannelList from '../../pieces/SocialWallChannelList';

export default {
  name: "SocialWallView",
  mixins: [templateMixin, tableMixin, commonMixin, jsLibraryMixin, helperStyle],
  components: {
    FeedEditorGroup,
    EditorHeader,
    ResponsiveBar,
    EditorViewInfoBar,
    UpgradeToProButton,
    SocialWallChannelModal,
    SocialWallChannelButton,
    SocialWallChannelList
  },
  data() {
    return {
      saving: false,
      loading: false,
      counter: 0,
      activeAccordion: ['1'],
      activeTab: 'general',
      openPlatformModal: false,
      platformForm: false,
      platformIndex: null,
      component: '',
      selectedPlatform: {},
      editPlatformTemplate: false,
      feedConfig: [],
      asideSocialWallConfig: [],
      savedSocialWallConfig: {
        social_wall_list: []
      },
      data: {
        name: '',
        displayName: '',
        label: '',
        title: this.$t(''),
        template: [],
        icon: '',
      },
      platforms: [
        {
          name: 'facebook_feed',
          displayName: 'Facebook',
          label: 'Facebook',
          title: this.$t('Select your facebook feed template'),
          template: [],
          icon: 'fa fa-facebook',
        },
        {
          name: 'instagram',
          displayName: 'Instagram',
          label: 'Instagram',
          title: this.$t('Select your instagram feed template'),
          template: [],
          icon: 'fa fa-instagram',
        },
        {
          name: 'youtube',
          displayName: 'YouTube',
          label: 'YouTube',
          title: this.$t('Select your youtube feed template'),
          template: [],
          icon: 'fa fa-youtube',
        },
        {
          name: 'tiktok',
          displayName: 'TikTok',
          label: 'TikTok',
          title: this.$t('Select your tiktok feed template'),
          template: [],
          icon: 'fa fa-tiktok',
        },
        {
          name: 'twitter',
          displayName: 'X (Twitter)',
          label: 'X (Twitter)',
          title: this.$t('Select your X (Twitter) feed template'),
          template: [],
          icon: 'fa fa-twitter',
        }
      ],
      selectedTemplate: null,
      templates: {}, // Initialize the templates object here
      translations: [],
      responsiveBarDevice: 'desktop',
      socialWallActiveTab: this.savedSocialWallConfig ? this.savedSocialWallConfig.social_wall_list[0].name : 'instagram',
      socialWallTemplates: [],
      template_title: '',
      layoutType: '',
    }
  },
  methods: {
    loadingInChangeType(val) {
      this.loading = val;
    },
    getComponentProps(tab) {
      const props = {
        configs: this.feedConfig,
        translations: this.translations,
        image_settings: this.image_settings,
      };
      if (tab === 'facebook_feed' || tab === 'tiktok') {
        props.feedConfig = this.feedConfig; // Use feedConfig for facebook_feed and tiktok
      }
      if (tab === 'twitter') {
        props.feedConfigs = this.feedConfig; // Use feedConfigs for twitter
        props.loadingAction = this.loadingInChangeType; // Add loadingAction for twitter
      }
      return props;
    },
    addNewWall() {
      let configLength = this.asideSocialWallConfig.length;
      if(configLength === 0 || this.asideSocialWallConfig[configLength - 1 ].name !== '') {
        this.asideSocialWallConfig.push({...this.data});
      }

      this.selectedPlatform = {...this.data};
      this.openPlatformModal = !this.openPlatformModal;
      this.editPlatformTemplate = false;
    },
    closePlatformModal() {

      this.platformForm = false;
      this.openPlatformModal = false;
      this.editPlatformTemplate = false;

      console.log('platformForm', this.platformForm);
      console.log('openPlatformModal', this.openPlatformModal);
      console.log('editPlatformTemplate', this.editPlatformTemplate);
    },
    selectWallPlatform(channel) {
      this.platformForm = true;
      if(channel) {
        this.selectedPlatform = channel;
        this.getSocialWallSettings(channel.name);
      }
    },
    openEditPlatformModal(data) {
      this.editPlatformTemplate = true;
      this.openPlatformModal = false;
      this.platformIndex = data.index;
      let channel = this.savedSocialWallConfig.social_wall_list[data.index];

       if(data.editOpenPlatformModal){
          this.selectWallPlatform(channel);
       }

      this.loading = false;
    },
    saveWallPlatform(data) {
      console.log('data', data);
      if (this.isChannelInvalid()) {
        this.handleError(this.$t('Please filled the field correctly!!'));
        return;
      }

      if (this.editPlatformTemplate && this.platformIndex !== null) {
        this.updateExistingChannel();
      } else {
        if (this.isDuplicateChannel()) {
          this.handleError('This template already exists');
          return;
        }
        this.asideSocialWallConfig.push(data);
      }

      this.finalizeChannelSave();
    },
    getSocialWallSettings(channel = '')
    {
      //this.loading = true;
      let platform = channel ? channel : 'social_wall';
      this.platform_short = this.getPlatformShortName(platform)
      let feed_id = this.$route.params.template_id;
      this.$get(`templates/meta/social-wall/${feed_id}`, {
        platform: platform,
        postType: 'post'
      }).then(response => {
        if(response.social_wall_settings && response.social_wall_settings.social_wall_list.length > 0){
          this.savedSocialWallConfig = response.social_wall_settings;
          this.asideSocialWallConfig =  [...this.savedSocialWallConfig.social_wall_list];
        }

        console.log('this.asideSocialWallConfig get', this.asideSocialWallConfig);

        this.socialWallTemplates = response.feed_templates;
        this.template_title =  response.template_details.post_title;
      }).catch(error => {
        this.handleError(error);
      }).always(() => {
        setTimeout(() => {
         // this.loading = false;
        }, 500);
      });
    },
    updatedSocialWallSettings() {
      this.saving = true;
      let template_id = this.$route.params.template_id;

      this.savedSocialWallConfig.social_wall_list = this.asideSocialWallConfig.filter((item) => {
        return item.name !== '';
      });

      console.log('this.asideSocialWallConfig', this.asideSocialWallConfig );
      console.log('this.savedSocialWallConfig', this.savedSocialWallConfig.social_wall_list );

      this.$put(`templates/meta/social-wall/${template_id}`, {
        settings: JSON.stringify(this.savedSocialWallConfig),
        platform: 'social_wall'
      }).then(response => {
        if (response.data) {
          if(this.platformIndex !== 0 && !this.platformIndex) {
            this.getSocialWallSettings();
          }
          this.$message({
            message: response.data.message,
            type: 'success',
            offset: 50
          });
        }
      }).catch(error => {
        this.handleError(error);
      }).always(() => {
        this.saving = false;
        if(this.platformIndex !== 0 && !this.platformIndex){
          this.selectedPlatform = {};
        }
        this.platformIndex = null;
        //this.openPlatformModal = false;
      });
    },
    removePlatformTemplate(index) {
      this.asideSocialWallConfig.splice(index, 1);
      this.updatedSocialWallSettings();
    },
    isChannelInvalid() {
      return !this.selectedPlatform || this.selectedPlatform.template.value === 0;
    },
    updateExistingChannel() {
      this.asideSocialWallConfig[this.platformIndex] = this.selectedPlatform;
    },
    isDuplicateChannel() {
      return this.savedSocialWallConfig.social_wall_list.some(
        item => item.template.value === this.selectedPlatform.template.value &&
                 item.name === this.selectedPlatform.name
      );
    },
    finalizeChannelSave() {
      this.updatedSocialWallSettings();
      this.platformForm = false;
    },
    setActiveTab(tabName, templateId) {
      this.socialWallActiveTab = tabName;
      this.selectedTemplate = templateId; // Store the selected template ID
      if(this.savedSocialWallConfig && this.savedSocialWallConfig.social_wall_list && this.savedSocialWallConfig.social_wall_list.length) {
        this.feedConfig = []; // Reset feedConfig before loading new data
        this.getFeedSettings(this.socialWallActiveTab, 'post', templateId);
      }
    },
    handleDevice (val, activeDevice) {
      this.$emit('handleDevice' , val, activeDevice);
    },
    handleLayout() {
      if(this.layoutType === undefined){
        return;
      }
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
      if(this.layoutType === 'masonry') {
        let that = this;
        this.loading = true;

        let activeTab = this.socialWallActiveTab === 'facebook' ? 'facebook_feed' : this.socialWallActiveTab;
        let platform = this.getPlatformShortName(this.socialWallActiveTab);
        const masonryActiveClass = '.wpsr-'+activeTab+'-masonry-activate';
        const allFeedClass = '.wpsr-'+platform+'-all-feed';

        setTimeout(function () {
          that.activeMasonry(masonryActiveClass, allFeedClass);
          that.loading = false;
        }, 500);
      }
    },
    // handleDevice (val, activeDevice) {
    //   this.$emit('handleDevice' , val, activeDevice);
    //   this.layoutType = this.feedConfig.feed_settings.layout_type;
    //   this.handleLayout();
    //   if (this.layoutType === 'carousel') {
    //     this.responsiveBarDevice = val;
    //     this.activeCarousel();
    //   }
    // },
    getEditorInfo() {
      return 'Showing Social Wall editor information';
    }
  },
  watch: {
    'feedConfig': {
      handler(newVal, oldVal) {
        this.feedConfig = newVal;
        this.counter++;

        if(this.counter > 1) {
          this.isEditing = true;
        }
        this.layoutType = newVal.feed_settings && newVal.feed_settings.carousel_settings !== undefined ? newVal.feed_settings.layout_type : '';
        if (newVal !== oldVal && this.layoutType && this.socialWallActiveTab && this.counter <= 1){
          this.handleLayout();
        }
      },
      deep:true
    },
    'feedConfig.feed_settings.layout_type': {
      handler(val) {
        this.layoutType = val;
      },
      deep:true
    },
    'feedConfig.feed_settings.template': {
      handler(val) {
        if (!this.feedConfig || !this.feedConfig.feed_settings) return;
        let template = val;
        this.feedConfig.feed_settings.template = template;
        this.component = '';
        let activeTab = this.socialWallActiveTab === 'facebook' ? 'facebook_feed' : this.socialWallActiveTab;


        // Check if the template exists before assigning
        if (this.templates[activeTab] && this.templates[activeTab][val]) {
            this.component = this.templates[activeTab][val];
        } else if (this.templates['facebook'] && this.templates['facebook'][val]) { 
            this.component = this.templates['facebook'][val]; // Use facebook key if exists
        }
      },
      deep:true,
    },
    'savedSocialWallConfig.social_wall_list': {
      handler(newVal) {
        if (newVal && newVal.length > 0 && !this.platformForm) {
          const initialTab = newVal[0];
          this.setActiveTab(initialTab.name, initialTab.template.value);
        }
      },
      deep:true
    },
  },
  mounted() {
    this.getSocialWallSettings();
      // Dynamically set templates based on platforms
      this.platforms.forEach((platform, index) => {
      let platform_name = platform.name === 'facebook_feed' ? 'facebook' : platform.name;

      // this.$root.$on('handleDeviceResponsive', (val, activeDevice) => {
      //   this.handleDevice(val, activeDevice);
      // });

      if(platform_name){
        this.templates[platform_name] = {};
        const templateNames = ['Template1', 'Template2', 'Template3'];
        templateNames.forEach(template => {
          try {
            this.templates[platform_name][template.toLowerCase()] = require(`../../feeds/templates/${platform_name}/${template}`).default;
          } catch (error) {}
        });
      }
     });
  }
}
</script>