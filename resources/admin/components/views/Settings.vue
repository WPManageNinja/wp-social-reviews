<template>
  <div class="wpsr_editor">
    <div class="wpsr_editor_wrapper wpsr-global-settings" :class="'wpsr-global-settings-collapse-'+activeNames">
      <el-container class="wpsr-global-settings-container">
        <el-aside width="290px" class="wpsr-global-settings-collapse-aside">
          <el-collapse class="wpsr-global-settings-collapse" v-model="activeNames" accordion @change="onCollapseItem">
            <template v-for="[key, menu] in filteredMenus" :key="key">
              <!-- Collapsible menu items with routes -->
              <el-collapse-item v-if="menu.routes && menu.routes.length > 1" :name="key">
                <template #title="{ isActive }">
                  <span class="wpsr-settings-title-row">
                    <component :is="getMenuIcon(key)" :width="16" :height="16" class="menu-icon" />
                    <span class="wpsr-settings-title-text wpsr-label-small">{{ menu.title }}</span>
                  </span>
                  <span>
                    <svg v-if="!isActive" width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M5.00005 3.87852L8.71255 0.166016L9.77305 1.22652L5.00005 5.99952L0.227051 1.22652L1.28755 0.166016L5.00005 3.87852Z" fill="#99A0AE"/>
                    </svg>
                    <svg v-else width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M5.00005 2.121L1.28755 5.8335L0.227051 4.773L5.00005 0L9.77305 4.773L8.71255 5.8335L5.00005 2.121Z" fill="#0E121B"/>
                    </svg>
                  </span>
                </template>
                <div class="wpsr-sub-menu-items">
                  <router-link v-for="(menuItem, itemKey) in menu.routes" :key="itemKey" active-class="wpsr-tab-active" exact :class="['wpsr-tab']" :to="{ name: menuItem.route }">
                    <img :src="getMenuItemIcon(menuItem)" alt="" class="sub-menu-icon">
                    <span class="wpsr-menu-title wpsr-label-small">{{ truncateTitle(menuItem.title) }}</span>
                  </router-link>
                </div>
              </el-collapse-item>
              
              <!-- Non-collapsible menu items with single route -->
              <div v-else-if="menu.routes && menu.routes.length === 1" :class="['wpsr-single-menu-item', { 'wpsr-single-menu-item-active': $route && $route.name === menu.routes[0].route }]">
                <router-link active-class="wpsr-tab-active" exact :class="['wpsr-single-tab']" :to="{ name: menu.routes[0].route }">
                  <component :is="getMenuIcon(key)" :width="16" :height="16" class="menu-icon" />
                  <span class="wpsr-menu-title wpsr-label-small">{{ menu.title }} </span>
                  <ProCrownIcon v-if="!has_pro && (key === 'get_reviews_via_qr_code' || key === 'shoppable_by_hashtags')" :width="16" :height="16" class="pro-crown-icon" />
                </router-link>
              </div>
            </template>
          </el-collapse>
        </el-aside>
        <el-main class="wpsr-global-settings-main">
          <router-view></router-view>
        </el-main>
      </el-container>
    </div>
  </div>
</template>

<script type="text/babel">
import { applyFilters } from '@wordpress/hooks';
import GeneralSettingsIcon from '../pieces/icons/GeneralSettingsIcon';
import FeedIcon from '../pieces/icons/FeedIcon';
import ReviewsIcon from '../pieces/icons/ReviewsIcon';
import QrCodeIcon from '../pieces/icons/QrCodeIcon';
import ShoppableIcon from '../pieces/icons/ShoppableIcon';
import AdvancedSettingsIcon from '../pieces/icons/AdvancedSettingsIcon';
import ProCrownIcon from '../pieces/icons/ProCrownIcon';
import {PlatformIconMixin} from '../../mixins/PlatformIconMixin';
import {DarkModeMixin} from '../../mixins/DarkModeMixin';
export default {
  name: 'Settings',
  mixins: [PlatformIconMixin, DarkModeMixin],
  components: {
    GeneralSettingsIcon,
    FeedIcon,
    ReviewsIcon,
    QrCodeIcon,
    ShoppableIcon,
    AdvancedSettingsIcon,
    ProCrownIcon
  },
  data() {
    return {
      sidebarMenus: {
        'general': {
          'title': this.$t('General Settings'),
          'permission' : ['administrator', 'wpsn_translation_settings', 'wpsn_license_settings', 'wpsn_full_access'],
          'routes': [
            {
              route: 'translation-management',
              title: 'Translation Settings',
              permission : ['wpsn_translation_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-translation'+'.svg'
            },
            {
              route: 'license-management',
              title: 'License Settings',
              permission : ['wpsn_license_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-license'+'.svg'
            },
            {
              route: 'management-settings',
              title: 'Managers',
              permission : ['administrator'],
              icon: this.assets_url+'/images/icon/'+'icon-manager'+'.svg'
            }
          ]
        },
        'feeds': {
          'title': this.$t('Feeds Platforms'),
          'permission' : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
          'routes': [
            {
              route: 'global-feeds-settings',
              title: 'Global Feeds Settings',
              permission : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'global-settings'+'.svg'
            },
            {
              route: 'twitter-settings',
              title: 'X (Twitter) Settings',
              permission : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-twitter'+'.png'
            },
            {
              route: 'youtube-settings',
              title: 'YouTube Settings',
              permission : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-youtube'+'.png',
            },
            {
              route: 'instagram-settings',
              title: 'Instagram Settings',
              permission : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-instagram'+'.png'
            },
            {
              route: 'facebook-feed-settings',
              title: 'Facebook Settings',
              permission : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-facebook-small'+'.png'
            },
            {
              route: 'tiktok-settings',
              title: 'TikTok Settings',
              permission : ['wpsn_feeds_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-tiktok'+'.png'
            }
          ]
        },
        'reviews': {
          'title': this.$t('Reviews Platforms'),
          'permission' : ['wpsn_reviews_platforms_settings', 'wpsn_full_access'],
          'routes': [
            {
              route: 'global-reviews-settings',
              title: 'Global Reviews Settings',
              permission : ['wpsn_reviews_platforms_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'global-settings'+'.svg'
            },
            ...applyFilters('wpsr_reviews_sidebar_menu', this.appVars.reviews_settings_platforms)
          ],
        },
        'get_reviews_via_qr_code': {
          'title': this.$t('Get Reviews via QR Code'),
          'permission' : ['wpsn_manage_qr_codes', 'wpsn_full_access'],
          'routes': [
            {
              route: 'get-reviews',
              title: this.$t('Get Reviews via QR Code'),
              permission : ['wpsn_manage_qr_codes', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/get_a_review.png'
            },
          ]
        },
        'shoppable_by_hashtags' : {
          'title': this.$t('Shoppable by Hashtags'),
          'permission' : ['wpsn_shoppable_settings', 'wpsn_full_access'],
          'routes': [
            {
              route: 'shoppable-by-hashtags',
              title: this.$t('Shoppable by Hashtags'),
              permission : ['wpsn_shoppable_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-shoppable'+'.png'
            },
          ]
        },
        'advance': {
          'title': this.$t('Advanced Settings'),
          'permission' : ['wpsn_feeds_advance_settings', 'wpsn_full_access'],
          'routes': [
            {
              route: 'advance-settings',
              title: 'Advanced Settings',
              permission : ['wpsn_feeds_advance_settings', 'wpsn_full_access'],
              icon: this.assets_url+'/images/icon/'+'icon-translation'+'.png'
            }
          ]
        }
      },
      reviewsSidebarMenus: [],
      feedsSidebarMenus: [],
      generalSidebarMenus: [],
      activeNames: "feeds"
    }
  },

  computed: {
    filteredMenus() {
      return Object.entries(this.sidebarMenus).filter(([key, menu]) => {
        return menu;
      });
    }
  },

  methods: {
    truncateTitle(title) {
      if (!title) return '';
      return title.length > 20 ? title.substring(0, 20) + '...' : title;
    },
    getMenuIcon(menuKey) {
      const iconMap = {
        'general': 'GeneralSettingsIcon',
        'feeds': 'FeedIcon', 
        'reviews': 'ReviewsIcon',
        'get_reviews_via_qr_code': 'QrCodeIcon',
        'shoppable_by_hashtags': 'ShoppableIcon',
        'advance': 'AdvancedSettingsIcon'
      };
      
      return iconMap[menuKey] || '';
    },
    getMenuItemIcon(menuItem) {
      // If dark mode is active and dark_mode_image is available, use it
      if (this.isDarkMode && menuItem.dark_mode_image) {
        return menuItem.dark_mode_image;
      }

      // Check if the icon was generated by getPlatformIcon and needs dark mode handling
      if (this.isDarkMode && menuItem.icon && typeof menuItem.icon === 'string') {
        // Extract platform name from the icon path to regenerate with dark mode
        const iconMatch = menuItem.icon.match(/icon-([^-]+)-small\.png$/);
        if (iconMatch) {
          const platform = iconMatch[1];
          // Use getPlatformIcon to get the correct dark mode icon
          return this.getPlatformIcon(platform, 'small');
        }
      }

      // Otherwise use the regular icon
      return menuItem.icon;
    },
    checkActiveNames() {
      let cur_route = this.$route.name;
      let data = this.sidebarMenus;
      let that = this;
      Object.keys(data).forEach(function(key) {
        let routes = data[key].routes;
        if (Array.isArray(routes)) {
          routes.forEach((router) => {
            if(router && router.route === cur_route) {
              that.activeNames = key;
            }
          })
        }
      });
    },
    onCollapseItem() {
      if(this.sidebarMenus[this.activeNames] &&
         this.sidebarMenus[this.activeNames].routes &&
         this.sidebarMenus[this.activeNames].routes.length > 1 &&
         this.sidebarMenus[this.activeNames].routes[0] &&
         this.$route.name !== this.sidebarMenus[this.activeNames].routes[0].route) {
        this.$router.push({'name' : this.sidebarMenus[this.activeNames].routes[0].route});
      }
    }
  },

  mounted() {
    this.checkActiveNames();
  }
}
</script>
