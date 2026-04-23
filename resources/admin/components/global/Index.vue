<template>
    <div class="wpsr_social_review_global">
        <div class="wpsr_social_review_global_main_nav">
            <div class="wpsr-admin-logo">
                <SocialNinjaIcon />
                <span v-if="has_pro">Pro</span>
            </div>

            <div class="wpsr-menu-items">
            <template v-for="menuItem in topMenus" :key="menuItem.route">
                <!-- Menu item with dropdown children -->
                <div
                    v-if="isValidMenuItem(menuItem) && menuItem.route !== 'advance-settings' && menuItem.children"
                    class="wpsr-menu-dropdown"
                >
                    <router-link
                        :to="{ name: menuItem.route }"
                        :class="getMenuItemClasses(menuItem)"
                        active-class="wpsr-tab-active"
                        exact-path
                        class="wpsr-menu-dropdown-trigger"
                    >
                        {{ getMenuTitle(menuItem) }}
                        <svg class="wpsr-menu-dropdown-arrow" width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </router-link>
                    <div class="wpsr-menu-dropdown-content">
                        <router-link
                            v-for="child in menuItem.children"
                            :key="child.route"
                            :to="{ name: child.route }"
                            class="wpsr-menu-dropdown-item"
                            active-class="wpsr-tab-active"
                        >
                            <span>{{ child.title }}</span>
                            <span v-if="child.isNew" class="wpsr-menu-keyword">New</span>
                            <ProCrownIcon v-if="child.isPro && !has_pro" :width="12" :height="12" class="wpsr-menu-pro-icon" />
                        </router-link>
                    </div>
                </div>
                <!-- Regular menu item -->
                <router-link
                    v-else-if="isValidMenuItem(menuItem) && menuItem.route !== 'advance-settings'"
                    :to="{ name: menuItem.route }"
                    :class="getMenuItemClasses(menuItem)"
                    active-class="wpsr-tab-active"
                    exact-path
                >
                    <span>{{ getMenuTitle(menuItem) }}</span>
                    <span v-if="menuItem.isNew" class="wpsr-menu-keyword">New</span>
                    <ProCrownIcon v-if="isProFeature(menuItem) && !has_pro" :width="14" :height="14" class="wpsr-menu-pro-icon" />
                </router-link>
            </template>

            <!-- Upgrade to Pro Button in Main Menu -->
            <UpgradeToProButton v-if="!has_pro" :btn_label="$t('Upgrade Now')" class="wpsr-main-menu-upgrade-btn" />
            </div>

            <div class="wpsr-nav-right">
                <!-- Settings Icon -->
                <el-tooltip
                    effect="dark"
                    content="Settings"
                    placement="bottom"
                >
                    <router-link
                        :to="{ name: 'advance-settings' }"
                        class="wpsr-settings-icon"
                        active-class="wpsr-icon-active"
                    >
                        <el-icon size="18">
                            <Setting />
                        </el-icon>
                    </router-link>
                </el-tooltip>

                <!-- Color Mode Toggle -->
                <ColorMode/>
            </div>
        </div>

        <DashboardNotices
            v-if="displayNotice || displayOptInNotice || displayProUpdateNotice || displayNewsletter"
            :displayNotice="displayNotice"
            :displayOptInNotice="displayOptInNotice"
            :displayProUpdateNotice="displayProUpdateNotice"
            :displayNewsletter="displayNewsletter"
            :userData="userData"
        />

<!--        <div id="wpsr-notice-bar">-->
<!--            <span class="wpsr-notice-bar-message">We added REST API and completely refactor our plugin files for better performance. If you update our plugin, Please re-configure your all connected platform and re-create your template. If you face any issues, open a support ticket <a href="https://wpmanageninja.com/support-tickets/" target="_blank">here</a>. Our support team is always here to help you.</span>-->
<!--            <button type="button" class="dismiss" title="Dismiss this message." data-page="overview">-->
<!--            </button>-->
<!--        </div>-->
        <router-view></router-view>
    </div>
</template>

<script type="text/babel">
import UpgradeToProButton from '../views/advertise/UpgradeToProButton';
import DashboardNotices from '../views/DashboardNotices';
import ColorMode from "../../components/pieces/ColorMode";
import SocialNinjaIcon from '../pieces/icons/SocialNinjaIcon.vue';
import ProCrownIcon from '../pieces/icons/ProCrownIcon.vue';
import { Setting } from '@element-plus/icons-vue';
import { applyFilters } from '@wordpress/hooks';

    let userDataFields = {
      name: '',
      email: ''
    };

    export default {
        name: 'Index',
        components: {
          UpgradeToProButton,
          DashboardNotices,
          ColorMode,
          SocialNinjaIcon,
          ProCrownIcon,
          Setting
        },
        data() {
            return {
                topMenus: [],
                displayNotice: false,
                displayOptInNotice: false,
                displayProUpdateNotice: false,
                displayNewsletter: false,
                userData: {...userDataFields},
            }
        },
        methods: {
            isValidMenuItem(menuItem) {
                return menuItem && menuItem.permission && this.hasPermission(menuItem.permission); // Always return true to show all menu items
            },
            getMenuItemClasses(menuItem) {
                const classes = [];

                if (this.$route.fullPath.includes('/settings') && menuItem.route === 'twitter-settings') {
                    classes.push('wpsr-tab-active', 'router-link-active', 'wpsr-tab', 'router-link-exact-active');
                }

                if (!this.$route.name.includes('platforms') &&
                    ['templates', 'reviews', 'chat-widgets', 'custom-sources', 'testimonials'].includes(menuItem.route)) {
                    classes.push('router-link-exact-active');
                }

                // Keep "Reviews" tab active when on review-forms routes
                if (menuItem.route === 'reviews' && this.$route.name && this.$route.name.startsWith('review-form')) {
                    classes.push('wpsr-tab-active', 'router-link-active', 'router-link-exact-active');
                }

                return classes;
            },
            getMenuTitle(menuItem) {
                return menuItem.title;
            },
            isProFeature(menuItem) {
                return menuItem.isPro === true;
            },
            setTopMenu() {
                this.topMenus = applyFilters('wpsr_top_level_menu', [
                    {
                        route: 'platforms',
                        title: 'Platforms',
                        permission: ['wpsn_manage_platforms', 'wpsn_full_access'],
                    },
                    {
                        route: 'reviews',
                        title: 'Reviews',
                        permission: ['wpsn_manage_reviews', 'wpsn_full_access'],
                        children: [
                            { route: 'reviews', title: 'All Reviews' },
                            { route: 'review-forms', title: 'Review Forms', isPro: true, isNew: true },
                        ]
                    },
                    {
                        route: 'testimonials',
                        title: 'Testimonials',
                        permission: ['wpsn_manage_testimonials', 'wpsn_full_access'],
                        isPro: true,
                    },
                    {
                        route: 'templates',
                        title: 'Templates',
                        permission: ['wpsn_manage_templates', 'wpsn_full_access'],
                    },
                    {
                        route: 'notifications',
                        title: 'Notification Popups',
                        permission: ['wpsn_manage_notification_popup', 'wpsn_full_access'],
                        isPro: true,
                    },
                    {
                        route: 'chat-widgets',
                        title: 'Chat Widgets',
                        permission: ['wpsn_manage_chat_widgets'],
                    },
                    {
                      route: 'custom-sources',
                      title: 'Custom Sources',
                      permission: ['wpsn_full_access'],
                      isPro: true,
                    },
                    {
                      route: 'support',
                      title: 'Support',
                      permission: ['wpsn_manage_chat_widgets', 'wpsn_full_access'],
                    },
                    {
                        route: 'advance-settings',
                        title: 'Settings',
                        permission: ['wpsn_feeds_platforms_settings', 'wpsn_reviews_platforms_settings', 'wpsn_shoppable_settings', 'wpsn_translation_settings', 'wpsn_license_settings', 'wpsn_feeds_advance_settings', 'wpsn_full_access'],
                    }
                ]);
            },
            getEnabledPlatforms() {
              this.$get('platforms/dashboard-notices')
                  .then(response => {
                    if(response){
                      this.displayNotice = response.data.displayNotice !== undefined ? response.data.displayNotice : false;
                      this.displayProUpdateNotice = response.data.displayProUpdateNotice !== undefined ? response.data.displayProUpdateNotice : false;
                      this.displayOptInNotice = response.data.displayOptInNotice;
                      this.displayNewsletter = response.data.displayNewsletter;
                      this.userData = response.data.userData;
                    }
                  }).catch(errors => {
                this.handleError(errors);
              }).always(() => {

              });
            },
          // removeNoticeBar(){
          //     jQuery('#wpsr-notice-bar .dismiss').click(function(e) {
          //         e.preventDefault();
          //         jQuery('#wpsr-notice-bar').remove();
          //     });
          // },
        },
        mounted() {
            this.setTopMenu();
            this.getEnabledPlatforms();
            // this.removeNoticeBar();
            // Theme initialization
        }
    }
</script>
