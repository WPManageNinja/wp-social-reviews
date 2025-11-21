<template>
  <div >
  <div class="wpsr_editor" v-if="hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])">
    <div class="wpsr_editor_wrapper wpsr-global-settings">
      <el-container class="wpsr-tools-settings-collapse-container">
        <el-aside width="290px" class="wpsr-tools-settings-collapse-aside">
          <div class="wpsr-tools-settings">
            <router-link v-for="menuItem in sidebarMenus" :key="menuItem.route" active-class="wpsr-tab-active" exact :class="['wpsr-tab']" :to="{ name: menuItem.route }">
              <svg v-if="menuItem.iconComponent === 'export'" width="16" height="14" viewBox="0 0 16 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8.75 5.5H12.5L8 10L3.5 5.5H7.25V0.25H8.75V5.5ZM2 12.25H14V7H15.5V13C15.5 13.1989 15.421 13.3897 15.2803 13.5303C15.1397 13.671 14.9489 13.75 14.75 13.75H1.25C1.05109 13.75 0.860322 13.671 0.71967 13.5303C0.579018 13.3897 0.5 13.1989 0.5 13V7H2V12.25Z" fill="#525866"/>
              </svg>
              <svg v-if="menuItem.iconComponent === 'import'" width="16" height="14" viewBox="0 0 16 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 12.25H14V7H15.5V13C15.5 13.1989 15.421 13.3897 15.2803 13.5303C15.1397 13.671 14.9489 13.75 14.75 13.75H1.25C1.05109 13.75 0.860322 13.671 0.71967 13.5303C0.579018 13.3897 0.5 13.1989 0.5 13V7H2V12.25ZM8.75 4.75V10H7.25V4.75H3.5L8 0.25L12.5 4.75H8.75Z" fill="#525866"/>
              </svg>
              <span class="wpsr-menu-title">{{ menuItem.title }} <ProCrownIcon v-if="!has_pro" :width="16" :height="16" class="pro-crown-icon" /></span>
            </router-link>
          </div>
        </el-aside>
        <el-main>
          <router-view></router-view>
        </el-main>
      </el-container>
    </div>
  </div>
    <PermissionsNotification v-if="!hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])" />
  </div>
</template>

<script type="text/babel">
import PermissionsNotification from "./advertise/PermissionsNotification.vue";
import ProCrownIcon from '../pieces/icons/ProCrownIcon';
export default {
  name: 'Tools',
  components: {PermissionsNotification, ProCrownIcon},
  data() {
    return {
      sidebarMenus: [],
    }
  },

  methods: {
    sidebarMenu() {
      this.sidebarMenus = this.applyFilters('wpsr_sidebar_menu', [
        {
          route: 'export',
          title: 'Export',
          icon: 'icon-export',
          iconComponent: 'export'
        },
        {
          route: 'import',
          title: 'Import',
          icon: 'icon-import',
          iconComponent: 'import'
        }
      ])
    }
  },

  mounted() {
    this.sidebarMenu();
  }
}
</script>