<template>
  <div>
    <div v-if="hasPermission(['wpsn_feeds_platforms_settings', 'wpsn_full_access'])">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="wpsr-general-settings-wrapper">
        <div class="wpsr-settings-container">
          <WpsrSkeleton :rows="10" />
        </div>
      </div>

      <!-- Content -->
      <div v-else-if="!loading && is_enabled_platform">
        <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
          <h2 class="wpsr-gs-header-title">{{platformTitle}}</h2>
          <el-button
              @click.prevent="update(feedPlatform, allGlobalSettings)"
              type="success"
              size="default"
              class="wpsr_primary_btn"
          >
                {{ $t('Save Settings')}}
          </el-button>
        </div>

        <div class="wpsr-general-settings-wrapper">
          <div class="wpsr-settings-container">
            <div class="wpsr-settings-header">
              <h2 class="wpsr-feed-settings-title">{{ $t('Feed Settings') }}</h2>
              <p class="wpsr-feed-settings-description">{{ $t('Configure the settings for your') }} {{ feedPlatform }} {{ $t('feed.') }}</p>
            </div>

            <div class="wpsr-feed-settings-content" v-if="allGlobalSettings && allGlobalSettings.global_settings">
              <!-- Check New Feeds Every Section -->
              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t('Check New Feeds Every') }}</h3>
                  <p class="wpsr-setting-description">
                    {{ $t('Your') }} {{ feedPlatform }} {{ $t('feeds are temporarily cached by our plugin in your WordPress database.') }}
                    {{ $t('You can choose how long the feeds cached, If you select \'1 Hour\' then the plugin will') }}
                    {{ $t('clear cache after that length of time and retrieve new feeds again.') }}
                  </p>
                </div>
                <div class="wpsr-setting-right">
                  <el-select v-model="allGlobalSettings.global_settings.expiration" class="wpsr-text-input wpsr-select" placeholder="Select" size="default">
                    <el-option
                        v-for="(item, key) in filterCheckItems(cache_times)"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                    >
                    </el-option>
                  </el-select>
                </div>
              </div>

              <!-- Clear Cache Section -->
              <div class="wpsr-setting-row">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t('Clear Cache') }}</h3>
                  <p class="wpsr-setting-description">
                    {{ $t('If you are experiencing an issue with the plugin not updating your') }} {{ feedPlatform }} {{ $t('feed automatically,') }}
                    {{ $t('clicking this button will clear all ') }} {{ feedPlatform }} {{ $t('cached feeds data and retrieve new feeds.') }}
                  </p>
                </div>
                <div class="wpsr-setting-right">
                  <el-button class="wpsr-clear-btn" size="default" @click="clearCache">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M8 0.5C12.1423 0.5 15.5 3.85775 15.5 8C15.5 12.1423 12.1423 15.5 8 15.5C3.85775 15.5 0.5 12.1423 0.5 8H2C2 11.3135 4.6865 14 8 14C11.3135 14 14 11.3135 14 8C14 4.6865 11.3135 2 8 2C5.9375 2 4.118 3.04025 3.03875 4.625H5V6.125H0.5V1.625H2V3.5C3.368 1.6775 5.54675 0.5 8 0.5ZM8.75 4.25V7.68875L11.1823 10.121L10.121 11.1823L7.25 8.30975V4.25H8.75Z" fill="#525866"/>
                    </svg>

                    <span class="wpsr-btn-text">{{ $t('Clear Feeds Cache') }}</span>
                  </el-button>
                </div>
              </div>

              <!-- Optimize Images Section -->
              <div class="wpsr-feed-setting-border wpsr-pb-20" v-if="feedPlatform === 'instagram' || feedPlatform === 'facebook_feed' || feedPlatform === 'tiktok' || feedPlatform === 'youtube'">
                <div class="wpsr-setting-row-no-border">
                  <div class="wpsr-setting-left">
                    <h3 class="wpsr-setting-title">{{ $t('Optimize Images') }}</h3>
                    <p class="wpsr-setting-description">
                      {{ $t('Enable this option to generate and optimized images in multiple sizes using your WordPress local storage for improved performance') }}{{ feedPlatform !== 'tiktok' && feedPlatform !== 'youtube' ? '' : '.' }}
                      <span v-if="feedPlatform !== 'tiktok' && feedPlatform !== 'youtube'">
                        {{ $t('and only the first image will display for') }}
                        <span v-if="feedPlatform === 'facebook_feed'">{{ $t('multiple images.') }}</span>
                        <span v-if="feedPlatform === 'instagram'">{{ $t('carousel posts.') }}</span>
                      </span>
                    </p>
                  </div>
                  <div class="wpsr-setting-right">
                    <el-switch
                        v-model="allGlobalSettings.global_settings.optimized_images"
                        active-color="#5525D7"
                        inactive-color="#b7b7b9"
                        active-value="true"
                        inactive-value="false"
                        class="wpsr-optimize-switch"
                    >
                    </el-switch>
                  </div>
                </div>

                <div class="wpsr-ml-20">
                    <p class="wpsr-pt-10 wpsr-mb-0">
                      <strong>{{ $t('Note: ') }}</strong>
                      <span v-html="$t('To configure <strong>GDPR Compliance</strong> and <strong>Image Format</strong> settings, go to ')"></span>
                      <router-link to="/settings/advance-settings" class="wpsr-gdpr-link">{{ $t('Advanced Settings') }}</router-link>
                    </p>
                </div>
              </div>

              <!-- Reset Local Images Section -->
              <div class="wpsr-setting-row" v-if="feedPlatform === 'instagram' || feedPlatform === 'facebook_feed' || feedPlatform === 'tiktok' || feedPlatform === 'youtube'">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t('Reset Local Images') }}</h3>
                  <p class="wpsr-setting-description">
                    {{ $t('This will clear all images associated with this platform') }}
                    <span v-if="feedPlatform !== 'tiktok' && feedPlatform !== 'youtube'">
                      {{ $t('and only the first image will display for') }}
                      <span v-if="feedPlatform === 'facebook_feed'">{{ $t('multiple images.') }}</span>
                      <span v-if="feedPlatform === 'instagram'">{{ $t('carousel posts.') }}</span>
                    </span>
                  </p>
                </div>
                <div class="wpsr-setting-right">
                  <RemoveConfirm
                      @confirm="resetData"
                      message="Would you like to delete all the local images associated with this platform?"
                      submit_button_type="text"
                      button_text="Reset Image Storage"
                      :displayRefreshLeft="'trash'"
                      class="wpsr-reset-btn"
                  >
                    <template #trigger>
                      <el-button type="primary" size="default" class="wpsr-reset-local-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                          <path d="M10 2.5C14.1423 2.5 17.5 5.85775 17.5 10C17.5 14.1423 14.1423 17.5 10 17.5C5.85775 17.5 2.5 14.1423 2.5 10H4C4 13.3135 6.6865 16 10 16C13.3135 16 16 13.3135 16 10C16 6.6865 13.3135 4 10 4C7.9375 4 6.118 5.04025 5.03875 6.625H7V8.125H2.5V3.625H4V5.5C5.368 3.6775 7.54675 2.5 10 2.5ZM10.75 6.25V9.68875L13.1823 12.121L12.121 13.1823L9.25 10.3098V6.25H10.75Z" fill="#525866"/>
                        </svg>
                        {{ $t('Reset') }}
                      </el-button>
                    </template>
                  </RemoveConfirm>
                </div>
              </div>

              <!-- Clear Twitter Cards Section -->
              <div class="wpsr-setting-row" v-if="feedPlatform === 'twitter'">
                <div class="wpsr-setting-left">
                  <h3 class="wpsr-setting-title">{{ $t('Clear X (Twitter) Cards') }} {{ !has_pro ? $t('(Pro)') : ''}}</h3>
                  <p class="wpsr-setting-description">{{ $t('This will clear all links cached data that have X (Twitter) Cards.') }}</p>
                </div>
                <div class="wpsr-setting-right">
                  <el-button
                      @click.prevent="clearTwitterCard"
                      size="default"
                      :disabled="!has_pro"
                      class="wpsr-twitter-clear-btn wpsr-clear-btn"
                  >
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M8 0.5C12.1423 0.5 15.5 3.85775 15.5 8C15.5 12.1423 12.1423 15.5 8 15.5C3.85775 15.5 0.5 12.1423 0.5 8H2C2 11.3135 4.6865 14 8 14C11.3135 14 14 11.3135 14 8C14 4.6865 11.3135 2 8 2C5.9375 2 4.118 3.04025 3.03875 4.625H5V6.125H0.5V1.625H2V3.5C3.368 1.6775 5.54675 0.5 8 0.5ZM8.75 4.25V7.68875L11.1823 10.121L10.121 11.1823L7.25 8.30975V4.25H8.75Z" fill="#525866"/>
                    </svg>
                    <span class="wpsr-btn-text">{{ $t('Clear X (Twitter) Cards')}}</span>
                  </el-button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <div class="wpsr-general-settings-wrapper" v-else-if="!loading && !is_enabled_platform">
      <div class="wpsr-setting-content-area">
        <div class="wpsr-connection-upgrade-to-pro">
            <div class="wpsr-connection-upgrade-message">
              <h2>{{ $t(`Please configure your ${ feedPlatform === 'twitter' ? 'X (Twitter)' : capitalizedPlatform(feedPlatform) } connection first`) }}</h2>
              <p>{{ $t('Connect your account to start displaying feeds and unlock all the features.') }}</p>
              <router-link to="/" class="wpsr-upgrade-btn wpsr-extend-btn">
                {{ $t('Configure Connection') }}
              </router-link>
            </div>
          </div>
      </div>
    </div>
    

    <PermissionsNotification v-if="!hasPermission(['wpsn_feeds_platforms_settings', 'wpsn_full_access'])" />
    </div>
  </div>
</template>

<script type="text/babel">
import {settingsMixin} from "../../../../mixins/settingsMixin";
import InputPassword from "../../../core-ui/editor/InputPassword";
import RemoveConfirm from "../../../core-ui/editor/RemoveConfirm";
import PermissionsNotification from "../../advertise/PermissionsNotification";
export default {
  name: "FeedSettings",
  components: {
    RemoveConfirm,
    PermissionsNotification,
    InputPassword,
  },
  mixins: [settingsMixin],
  data() {
    return {
      isLoading: false,
      feedPlatform: '',
      allGlobalSettings: false,
      is_enabled_platform: false,
      isSaveButtonDisabled: false,
      loading: false,
      platformTitle: 'Settings',
      cache_times:[
        // {
        //   value: 300,
        //   label: this.$t('5 Minutes'),
        //   hide: false
        // },
        {
          value: 60*60,
          label: this.$t('1 Hour'),
          hide: false
        },
        {
          value: 60*60*6,
          label: this.$t('6 Hour'),
          hide: false
        },
        {
          value: 86400,
          label: this.$t('1 Day'),
          hide: false
        },
        {
          value: 172800,
          label: this.$t('2 Days'),
          hide: false
        },
        {
          value: 259200,
          label: this.$t('3 Days'),
          hide: false
        },
        {
          value: 604800,
          label: this.$t('1 Week'),
          hide: true
        },
        {
          value: 1209600,
          label: this.$t('2 Weeks'),
          hide: true
        },
        {
          value: 2592000,
          label: this.$t('1 Month'),
          hide: true
        },
        // {
        //   value: 31536000,
        //   label: this.$t('1 Year'),
        //   hide: true
        // }
      ]
    }
  },
  computed: {

  },
  methods: {
    capitalizedPlatform() {
      return this.feedPlatform.split('_').map(word => {
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
      }).join(' ');
    },
    resetData() {
      this.$del('settings/reset-images', {
        platform: this.feedPlatform,
      }).then(response => {
        if (response) {
          this.handleSuccess(response.message);
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    },

    clearCache() {
      this.$del('settings', {
        platform: this.feedPlatform
      }).then(response => {
        if(response.data) {
          this.handleSuccess(response.data.message);
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    },

    clearTwitterCard() {
      this.$del('settings/twitter-card').then(response => {
        if (response) {
          this.handleSuccess(response.message);
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    },

    check_condition(item) {
      const platform = this.feedPlatform;

      if(platform === 'twitter' && item.value < 604800) {
        return false;
      }

      if(platform === 'tiktok' && item.value < 86400) {
        return false;
      }

      return (!item.hide && (platform === 'instagram' || platform === 'facebook_feed')) || (platform !== 'instagram' && platform !== 'facebook_feed')
    },
    filterCheckItems(items) {
    const platform = this.feedPlatform;

    return items.filter(item => {
      if (platform === 'twitter' && item.value < 604800) {
        return false;
      }

      if (platform === 'tiktok' && item.value < 86400) {
        return false;
      }

      return (!item.hide && (platform === 'instagram' || platform === 'facebook_feed')) || 
             (platform !== 'instagram' && platform !== 'facebook_feed');
    });
  }
  },
  watch: {
    '$route'(to, from) {
      let routePath = to.name;
      let platform  = routePath.replace("-settings", "");
      platform  = platform.replace("-feed", "_feed");
      this.platformTitle = to.meta.title;
      this.feedPlatform = platform;
      this.get(platform);
    },
  },
  mounted() {
    let routePath = this.$route.name;
    let platform  = routePath.replace("-settings", "");
    platform  = platform.replace("-feed", "_feed");
    this.platformTitle = this.$route.meta.title;
    this.feedPlatform = platform;
    this.get(platform);
  }
}
</script>