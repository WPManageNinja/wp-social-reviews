<template>
  <div class="wpsr-platforms-wrapper" v-if="hasPermission(['wpsn_manage_platforms', 'wpsn_full_access'])">

    <!-- notices -->
    <ErrorNotice
        v-if="errorNotice"
        :errorNotice="errorNotice"
        @update:errorNotice="updateErrorNotice"
    />

    <div class="wpsr-platforms-wrapper-inner">
      <!-- New tab navigation with proper icons -->
      <div class="wpsr-platform-tabs">
          <div class="wpsr-tab-buttons">
        <button
          :class="['wpsr-tab-button', { active: activeTab === 'all' }]"
          @click="changeTab('all')"
        >
          <PlatformsIcon/>
          {{$t('All Platforms')}}
        </button>
        <button
            v-if="hasFeedsToShow"
          :class="['wpsr-tab-button', { active: activeTab === 'feeds' }]"
          @click="changeTab('feeds')"
        >
          <LayoutLineIcon/>
          {{$t('Social Feeds')}}
        </button>
        <button
            v-if="hasReviewsToShow"
          :class="['wpsr-tab-button', { active: activeTab === 'reviews' }]"
          @click="changeTab('reviews')"
        >
          <ChatQuoteLineIcon/>
          {{$t('Business Reviews')}}
        </button>
        <button
            v-if="showChatWidget"
          :class="['wpsr-tab-button', { active: activeTab === 'chats' }]"
          @click="changeTab('chats')"
        >
          <ChatLineIcon/>
          {{$t('Social Chats')}}
        </button>
          </div>
          <div class="wpsr-tab-actions">
        <div class="wpsr-search-box" :class="{ 'wpsr-search-active': showSearch }">
          <div class="wpsr-search-icon" @click="toggleSearch">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
          </div>
          <div class="wpsr-search-input-wrapper">
            <el-input
                ref="searchInput"
                v-model="searchQuery"
                style="width: 240px"
                :placeholder="$t('Search Platforms')+'...'"
                clearable
                @keyup.enter="handleSearchEnter"
                @keyup.esc="clearSearch"
                @input="handleSearchInput"
            />
          </div>
        </div>
        <div class="wpsr-options-box" @click="toggleScreenOptions">
          <SettingsLineIcon/>
          <span>{{$t('Screen Options')}}</span>
          </div>
        </div>
      </div>

      <div v-if="isLoading && !isEditorMode" class="wpsr-platforms-loading">
        <div class="wpsr-platforms-skeleton-wrapper">
          <WpsrSkeleton :rows="12" />
        </div>
      </div>

      <!-- Platforms Content -->
      <div v-if="isEditorMode || !isLoading" class="wpsr-all-platforms">


      <!-- social feeds - wrap with v-show -->
      <!-- Add this new section for search results -->
      <div class="wpsr-platforms-group" v-if="(activeTab === 'all' || activeTab === 'feeds') && filteredSocialFeeds.length">
        <el-row>
          <el-col :span="24">
            <div class="wpsr-platform-title-wrapper">
              <h3 class="wpsr-platform-title">{{$t('Social Feeds')}}</h3>
              <WavingHandIcon/>
            </div>
          </el-col>
        </el-row>
        <el-row :gutter="20" class="wpsr-reviews-platforms">
          <el-col :span="6" v-for="feed in filteredSocialFeeds" :key="feed.id">
            <div class="wpsr-platform">
              <div class="wpsr-platform-body">
                <div class="wpsr-platform-info" @click="openFeedModal(feed)">
                  <img :src="getFeedIcon(feed)" alt="" class="image">
                  <div class="wpsr-platform-status-tag">
                    <el-icon v-if="!isFeedEnabled(feed)" class="icon-ban"></el-icon>
                    <el-icon v-if="isFeedEnabled(feed)"><Check /></el-icon>
                  </div>
                </div>
                <div class="wpsr-platform-setting-tabs">
                  <YoutubeConfig
                      v-if="feed.id === 'youtube' && showYoutubeFeedModal"
                      :youtubeModal="showYoutubeFeedModal"
                      @youtube_settings="youtubeFeedModal"
                      @findEnabledPlatforms="getEnabledPlatforms"
                  />
                  <instagram-config
                      v-if="feed.id === 'instagram' && instagramSettingModal"
                      :instagramModal="instagramSettingModal"
                      :access_token.sync="verificationData"
                      :credentials_type="credentialsType"
                      :expires_in="expiresIn"
                      @instagram_settings="instagramFeedModal"
                      @findEnabledPlatforms="getEnabledPlatforms"
                  />
                  <facebook-config
                      v-if="feed.id === 'facebook_feed' && showFacebookFeedModal"
                      :access_token.sync="verificationData"
                      :facebookModal="showFacebookFeedModal"
                      @facebook_settings="facebookFeedModalSettings"
                      @findEnabledPlatforms="getEnabledPlatforms"
                  />
                  <TwitterConfig
                      v-if="feed.id === 'twitter' && showTwitterFeedModal"
                      :twitterModal="showTwitterFeedModal"
                      @twitter_settings="twitterFeedModal"
                      @findEnabledPlatforms="getEnabledPlatforms"
                  />
                  <tiktok-config
                      v-if="feed.id === 'tiktok' && showTiktokFeedModal"
                      :access_token.sync="verificationData"
                      :tiktokModal="showTiktokFeedModal"
                      @tiktok_settings="tiktokFeedModalSettings"
                      @findEnabledPlatforms="getEnabledPlatforms"
                  />
                </div>
              </div>
              <div class="wpsr-platform-footer">
                <h3 class="wpsr-platform-name">{{ feed.name }}</h3>
                <div class="wpsr-platform-icon">
                  <el-icon class="el-icon-setting" @click="openFeedModal(feed)"><Setting /></el-icon>
                </div>
              </div>
            </div>
          </el-col>
          <!-- <el-col :span="6">
            <div class="wpsr-platform">
              <div class="wpsr-platform-body">
                <div class="wpsr-platform-info">
                  <img :src="assets_url+'/images/icon/icon-social-wall.png'" alt="" class="image">
                  <h3>Social Wall</h3>
                </div>
                <div class="wpsr-platform-setting-tabs">
                  <social-wall-config
                      v-if="showSocialWallModal"
                      :socialWallModal="showSocialWallModal"
                      @social_wall_settings="SocialWallModalSettings"
                  >
                  </social-wall-config>
                </div>
              </div>
              <div class="wpsr-platform-footer">
                <div class="wpsr-platform-status-tag">
                  <i v-if="!(is_enabled_platform && is_enabled_platform.social_wall)" class="icon-ban"></i>
                  <i v-if="is_enabled_platform && is_enabled_platform.social_wall" class="el-icon-check"></i>
                  <span v-if="is_enabled_platform && is_enabled_platform.social_wall">{{$t('Enabled')}}</span>
                  <span v-else>{{$t('Disabled')}}</span>
                </div>
                <div class="wpsr-platform-icon">
                  <i class="el-icon-setting" @click="showSocialWallModal=true"></i>
                </div>
              </div>
            </div>
          </el-col> -->
        </el-row>
      </div>
      <!-- social reviews -->
      <ReviewsPlatforms
          v-if="(activeTab === 'all' || activeTab === 'reviews') && !templateId"
          :column="6"
          :filteredPlatforms="filteredPlatforms"
          :activeTab="activeTab"
          :urlParams="urlParams"
          :configurationInfo="configurationInfo"
          :isDarkModeActive="isDarkMode"
          :activeSearchQuery="activeSearchQuery"
      />

      

      <!-- social chats - wrap with v-show -->
      <div class="wpsr-platforms-group" v-if="shouldShowChatWidget">
        <el-row>
            <el-col :span="24">
              <div class="wpsr-platform-title-wrapper">
                <h3 class="wpsr-platform-title">{{$t('Social Chats')}}</h3>
                <ChatWidgetSectionIcon/>
              </div>
            </el-col>
        </el-row>
        <SocialChats/>
      </div>
      </div>
      <!-- Screen Options Drawer -->
      <screen-options-drawer
          ref="drawer"
          :visible="showScreenOptions"
          :screen-options="screenOptions"
          @close="showScreenOptions = false"
          @update="updatePlatformsStatuses"
      />
    </div>
  </div>
  <PermissionsNotification v-if="!hasPermission(['wpsn_manage_platforms', 'wpsn_full_access'])" />
</template>
<script>
import SocialChats from './platforms/chats/SocialChats';
import TwitterConfig from './platforms/feeds/Twitter';
import YoutubeConfig from './platforms/feeds/Youtube';
import InstagramConfig from './platforms/feeds/Instagram';
import FacebookConfig from './platforms/feeds/Facebook'
import TiktokConfig from "./platforms/feeds/Tiktok";
import SocialWallConfig from "./platforms/feeds/SocialWall";
import ErrorNotice from '../pieces/ErrorNotice';
import PermissionsNotification from "./advertise/PermissionsNotification";
import ReviewsPlatforms from './platforms/reviews/ReviewsPlatforms';
import ScreenOptionsDrawer from "./ScreenOptionsDrawer.vue";
import PlatformsIcon from "../pieces/icons/PlatformsIcon.vue";
import ChatQuoteLineIcon from "../pieces/icons/ChatQuoteLineIcon.vue";
import LayoutLineIcon from "../pieces/icons/LayoutLineIcon.vue";
import ChatLineIcon from "../pieces/icons/ChatLineIcon.vue";
import WavingHandIcon from "../pieces/icons/WavingHandIcon.vue";
import ThumbsUpIcon from "../pieces/icons/ThumbsUpIcon.vue";
import SettingsLineIcon from "../pieces/icons/SettingsLineIcon.vue";
import { Loading } from '@element-plus/icons-vue';
import { DarkModeMixin } from '../../mixins/DarkModeMixin';
import ChatWidgetSectionIcon from "../pieces/icons/ChatWidgetSectionIcon.vue";

export default {
    name: "Platforms",
    mixins: [DarkModeMixin],
    components: {
      ChatWidgetSectionIcon,
      ScreenOptionsDrawer,
      PermissionsNotification,
        SocialChats,
        TwitterConfig,
        YoutubeConfig,
        InstagramConfig,
        FacebookConfig,
        TiktokConfig,
        SocialWallConfig,
        ErrorNotice,
        ReviewsPlatforms,
        PlatformsIcon,
        ChatQuoteLineIcon,
        ChatLineIcon,
        LayoutLineIcon,
        WavingHandIcon,
        ThumbsUpIcon,
        SettingsLineIcon,
        Loading
    },
    data() {
      return {
        // Add a loading state
        isLoading: true,
        activeTab: 'all',
        templateId: null,
        searchQuery: '',
        activeSearchQuery: '', // The actual search term used for filtering
        showSearch: false, // Add search visibility state
        showScreenOptions: false,
        updatingScreenOptions: false,
        updateTimeout: null,
        //feed modal settings
        showTwitterFeedModal: false,
        showFacebookFeedModal: false,
        showTiktokFeedModal: false,
        instagramSettingModal: false,
        showYoutubeFeedModal: false,
        showSocialWallModal: false,

        admin_page_url: this.appVars.admin_page_url,
        showSettingModal: false,
        platFormName: '',
        loading: false,
        platforms: window.WPSocialReviewsAdmin.platforms_cards,
        verificationData: '',
        expiresIn: '',
        is_enabled_platform: false,
        errorNotice: [],
        credentialsType : 'oauth',
        platform_type: '',

        // Social feeds configuration
        socialFeeds: [
          { id: 'instagram', name: 'Instagram', icon: 'icon-instagram.png', modalKey: 'instagramSettingModal' },
          { id: 'facebook_feed', name: 'Facebook', icon: 'icon-facebook-small.png', modalKey: 'showFacebookFeedModal' },
          { id: 'youtube', name: 'YouTube', icon: 'icon-youtube.png', modalKey: 'showYoutubeFeedModal' },
          { id: 'tiktok', name: 'TikTok', icon: 'icon-tiktok.png', modalKey: 'showTiktokFeedModal' },
          { id: 'twitter', name: 'X (Twitter)', icon: 'icon-twitter.png', modalKey: 'showTwitterFeedModal' },
        ],
        // Screen options - Source of truth for enabled/disabled platforms
        // This state is passed to ScreenOptionsDrawer and updated via events
        screenOptions: {
          feeds: {
            instagram: true,
            youtube: true,
            facebook_feed: true,
            twitter: true,
            tiktok: true,
          },
          reviews: {
            google: true,
            airbnb: true,
            yelp: true,
            tripadvisor: true,
            amazon: true,
            aliexpress: true,
            facebook: true,
            woocommerce: true,
            trustpilot: true,
            'booking.com': true,
          },
          chats: {
            allinone: true,
          }
        },
        urlParams: null,
        configurationInfo: {},
      }
    },
    computed: {
      // Platform filtering
      filteredPlatforms() {
        if (!this.activeSearchQuery) {
          // Filter by enabled state in screenOptions.reviews
          return this.platforms.filter(platform =>
              this.screenOptions.reviews[platform.platform]
          );
        }

        const query = this.activeSearchQuery.toLowerCase();
        return this.platforms.filter(platform =>
            platform.platform_title?.toLowerCase().includes(query) &&
            this.screenOptions.reviews[platform.platform]
        );
      },

      filteredSocialFeeds() {
        if (!this.activeSearchQuery) {
          // Filter by enabled state in screenOptions.feeds
          return this.socialFeeds.filter(feed => {
            const platformKey = feed.id;
            return this.screenOptions.feeds[platformKey];
          });
        }

        const query = this.activeSearchQuery.toLowerCase();
        return this.socialFeeds.filter(feed => {
          const platformKey = feed.id;
          return feed.name?.toLowerCase().includes(query) &&
              this.screenOptions.feeds[platformKey];
        });
      },

      hasReviewsToShow() {
        // Show feeds tab if any feeds are enabled in screen options, regardless of search
        return Object.values(this.screenOptions.reviews).some(enabled => enabled);
      },

      // Visibility helpers
      showChatWidget() {
        return this.screenOptions.chats.allinone;
      },

      shouldShowChatWidget() {
        // First check if chat widget is enabled
        if (!this.showChatWidget) {
          return false;
        }

        // Check if we're on the right tab
        if (!(this.activeTab === 'all' || this.activeTab === 'chats')) {
          return false;
        }

        // If there's no active search, show the chat widget
        if (!this.activeSearchQuery) {
          return true;
        }

        // If there's a search, only show if it matches chat-related terms
        const searchTerm = this.activeSearchQuery.toLowerCase();
        return searchTerm.includes('chat') ||
               searchTerm.includes('social') ||
               searchTerm.includes('widget') ||
               searchTerm.includes('allinone');
      },

      hasFeedsToShow() {
        // Show feeds tab if any feeds are enabled in screen options, regardless of search
        return Object.values(this.screenOptions.feeds).some(enabled => enabled);
      },

      // Check if we're in editor mode (when templateId exists or route name contains 'edit')
      isEditorMode() {
        return !!this.templateId || (this.$route && this.$route.name && this.$route.name.includes('edit'));
      },
    },

    methods: {
      // Tab and UI methods
      changeTab(tabName) {
        this.isLoading = true;
        this.activeTab = tabName;

        // Simulate loading time and hide loader after a short delay
        setTimeout(() => {
          this.isLoading = false;
        }, 300);
      },
      toggleScreenOptions() {
        this.showScreenOptions = !this.showScreenOptions;

        if (this.showScreenOptions) {
          document.addEventListener("mousedown", this.handleClickOutside);
        } else {
          document.removeEventListener("mousedown", this.handleClickOutside);
        }
      },
      handleClickOutside(event) {
        const drawer = this.$refs.drawer && this.$refs.drawer.$el ? this.$refs.drawer.$el : null;
        if (drawer && !drawer.contains(event.target)) {
          this.showScreenOptions = false;
          document.removeEventListener("mousedown", this.handleClickOutside);
        }
      },
      updatePlatformsStatuses(newOptions) {

        this.screenOptions = newOptions; // Update local state first
        this.updatingScreenOptions = true;

        // Prepare the payload with the correct structure
        const payload = {
          statuses: {
            feeds: { ...newOptions.feeds },
            reviews: { ...newOptions.reviews },
            chats: { ...newOptions.chats }
          }
        };

        // Clear any existing timeout
        if (this.updateTimeout) {
          clearTimeout(this.updateTimeout);
        }

        // Set a new timeout to debounce the API call
        this.updateTimeout = setTimeout(() => {
          this.$post('platforms/update-statuses', payload)
              .then(response => {
                if (response) {}
                this.updatingScreenOptions = false; // Set updating to false on success
              })
              .catch(error => {
                this.handleError(error);
                this.updatingScreenOptions = false; // Set updating to false on error
              });
        }, 300); // 300ms debounce
      },
      fetchPlatformStatuses() {
        this.updatingScreenOptions = true;
        this.isLoading = true; // Ensure loading is true at start
        this.$get('platforms/get-statuses')
            .then(response => {
              if (response && response.statuses) {
                // Get platform statuses from response or use empty objects
                const platformsStatuses = response.statuses.platforms_statuses || {
                  feeds: {},
                  reviews: {},
                  chats: {}
                };

                // Temporarily disable the watch by setting updatingScreenOptions
                this.updatingScreenOptions = true;

                // Merge with our default values to ensure all platforms have values
                this.screenOptions = {
                  feeds: {
                    instagram: true,
                    youtube: true,
                    facebook_feed: true,  // Ensure facebook_feed is included
                    twitter: true,
                    tiktok: true,
                    ...platformsStatuses.feeds
                  },
                  reviews: {
                    google: true,
                    airbnb: true,
                    yelp: true,
                    tripadvisor: true,
                    amazon: true,
                    aliexpress: true,
                    'booking.com': true,
                    facebook: true,
                    woocommerce: true,
                    trustpilot: true,
                    ...platformsStatuses.reviews
                  },
                  chats: {
                    allinone: true,
                    ...platformsStatuses.chats
                  }
                };

                // Use setTimeout to ensure the watch doesn't trigger immediately
                setTimeout(() => {
                  // Mark initial load as complete AFTER setting screenOptions
                  this.initialLoadComplete = true;
                  // Re-enable the watch
                  this.updatingScreenOptions = false;
                  // Set loading to false to show the content
                  this.isLoading = false;
                }, 100);
              } else {
                this.updatingScreenOptions = false;
                this.isLoading = false;
              }
            })
            .catch(error => {
              this.handleError(error);
              this.updatingScreenOptions = false;
              this.isLoading = false;
            });
      },
      getEnabledPlatforms() {
        this.$get('platforms/enabled')
            .then(response => {
              if(response){
                this.is_enabled_platform = response.platforms;
                this.errorNotice = response.notices !== undefined ? response.notices : [];
              }
            }).catch(errors => {
          this.handleError(errors);
        }).always(() => {

        });
      },
      twitterFeedModal(val) {
        this.showTwitterFeedModal = val;
      },
      youtubeFeedModal(val) {
        this.showYoutubeFeedModal = val;
      },
      instagramFeedModal(val) {
        this.instagramSettingModal = val;
      },
      facebookFeedModalSettings(val) {
        this.showFacebookFeedModal = val;
      },
      tiktokFeedModalSettings(val) {
        this.showTiktokFeedModal = val;
      },
      SocialWallModalSettings(val) {
        this.showSocialWallModal = val;
      },
      clearSearch() {
        this.searchQuery = '';
        this.activeSearchQuery = '';
      },
      handleSearchInput() {
        // Auto-show search when typing (but don't filter until Enter is pressed)
        if (this.searchQuery && !this.showSearch) {
          this.showSearch = true;
        }

        // Clear active search if input is empty
        if (!this.searchQuery.trim()) {
          this.activeSearchQuery = '';
        }
      },
      handleSearchEnter() {
        if (!this.searchQuery.trim()) {
          // Clear search if empty
          this.activeSearchQuery = '';
          return;
        }

        // Set the active search query to trigger filtering
        this.activeSearchQuery = this.searchQuery.trim();
      },
      toggleSearch() {
        this.showSearch = !this.showSearch;
      },
      updateErrorNotice(newErrorNotice) {
        this.errorNotice = newErrorNotice;
      },
      getFeedIcon(feed) {
        if (feed.id === 'facebook_feed') {
          return this.isDarkMode ?
            `${this.assets_url}/images/icon/icon-facebook-small-dark.png` :
            `${this.assets_url}/images/icon/icon-facebook-small.png`;
        }
        return `${this.assets_url}/images/icon/${feed.icon}`;
      },
      isFeedEnabled(feed) {
        return this.is_enabled_platform && this.is_enabled_platform[feed.id];
      },
      openFeedModal(feed) {
        const modalKey = feed.modalKey;
        this[modalKey] = true;
      },
      handlePlatformName(platform){
        return platform.replace('_', '-');
      }
    },

    created() {
      const queryString = window.location.search;
      this.urlParams = new URLSearchParams(queryString);
      let platform = this.urlParams.get('platform');
      let credentials_type = this.urlParams.get('credentialsType');
      let oauth_token = platform === 'google' ? this.urlParams.get('code') : this.urlParams.get('access_token');
      let expires_in = this.urlParams.get('expires_in');
      let screen_name = this.urlParams.get('screen_name');
      let auth_code = this.urlParams.get('auth_code');

      if(this.$route.query.open_modal === 'instagram' || (auth_code && platform === 'instagram')) {
        this.instagramSettingModal = true;
      }

      if(auth_code && platform === 'facebook_feed') {
        this.showFacebookFeedModal = true;
      }

      if (oauth_token && platform) {
        this.verificationData = oauth_token;
        this.platFormName = platform;
        if (platform === 'instagram') {
          this.instagramSettingModal = true;
          this.expiresIn = expires_in;
          this.credentialsType = credentials_type;
        } else if (platform === 'facebook_feed') {
          this.showFacebookFeedModal = true;
          // this.expiresIn = expires_in;
        } else if ( platform === 'tiktok'){
          this.showTiktokFeedModal = true;
        }
        let templateId = null;
        const templateIdMatch = window.location.search.match(/&(\d+)&/);
        if (templateIdMatch && templateIdMatch[1]) {
          templateId = templateIdMatch[1];
          this.templateId = templateId;
        }

        if (!templateId) {
          if (platform !== 'instagram' && platform !== 'facebook_feed' && platform !== 'tiktok') {
            this.configurationInfo = {
              configuration: true,
              platform: platform || null,
              oauth_token: oauth_token || null,
              platform_type: 'reviews'
            };
          }
          window.history.pushState({}, null, this.admin_page_url + '#/');
        } else {
          // Navigate to edit template route when templateId is found
          const page = this.urlParams.get('page');
          
          // Build the target URL to match the exact format needed
          const baseUrl = window.location.origin + window.location.pathname;
          const queryParams = new URLSearchParams({
            page: page
          });
          
          // Hash route parameters - these go directly in the hash part
          const hashQueryParams = new URLSearchParams({
            configuration: 'true',
            platform: platform,
            oauth_token: oauth_token,
            credentialsType: credentials_type,
            expires_in: expires_in
          });

          // check platform is feed or reviews
          this.platform_type = ['instagram', 'facebook_feed', 'tiktok'].includes(platform) ? 'feeds' : 'reviews';
          
          let targetUrl = '';

          if(this.platform_type === 'reviews') {
            targetUrl = `${baseUrl}?${queryParams.toString()}#/edit-template/${templateId}?${hashQueryParams.toString()}`;
          } else {
            targetUrl = `${baseUrl}?${queryParams.toString()}#/edit-${this.handlePlatformName(platform)}-template/${templateId}?${hashQueryParams.toString()}`;
          }
          
          // Force URL update using replaceState
          window.history.replaceState({}, '', targetUrl);
          
          // Force page reload to ensure navigation works
          setTimeout(() => {
            window.location.href = targetUrl;
          }, 100);
        }

      } else if (screen_name && screen_name.length) {
        this.showTwitterFeedModal = true;
      } else {
        this.showSettingModal = false;
      }
    },
    mounted() {
      if(this.$route.query.install !== undefined && this.$route.query.install === 'tiktok_feed'){
        this.showTiktokFeedModal = true;
        window.history.pushState({}, null, this.admin_page_url+'#/');
      }

      this.getEnabledPlatforms();
      this.fetchPlatformStatuses();
    },
    watch: {
      screenOptions: {
        handler(newVal, oldVal) {
          // Skip the initial watch trigger that happens on component creation
          if (!this.initialLoadComplete) {
            return;
          }

          // Skip if we're currently updating from an API call
          if (this.updatingScreenOptions) {
            return;
          }

          // Debounce updates to prevent multiple rapid API calls
          if (this.updateTimeout) {
            clearTimeout(this.updateTimeout);
          }

          this.updateTimeout = setTimeout(() => {
            this.updatePlatformsStatuses(newVal);
          }, 300);
        },
        deep: true
      },
      showScreenOptions(val) {
        if (!val) {
          document.removeEventListener("mousedown", this.handleClickOutside);
        }
      },
    }
}
</script>