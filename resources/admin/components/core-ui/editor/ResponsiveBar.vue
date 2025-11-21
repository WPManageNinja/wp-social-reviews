<template>
  <div class="wpsr-device-manager">
    <!-- Responsive Bar UI -->
    <div v-if="type !== 'dropdown'" class="wpsr-responsive-bar-wrapper" :class="{'wpsr-sticky-bar': scrolled}" :device=activeDeviceName>
      <span class="wpsr-responsive-bar-item wpsr-desktop-icon" @click.native="handleDevice('desktop','dashicons-desktop')">
        <el-tooltip class="item" effect="dark" content="Desktop" placement="bottom">
          <DeviceIcon type="desktop" :iconStyle="{cursor: 'pointer'}" :active="activeDeviceName === 'dashicons-desktop'" />
        </el-tooltip>
      </span>
      <span class="wpsr-device-type-divider"></span>
      <span class="wpsr-responsive-bar-item wpsr-tablet-icon" @click.native="handleDevice('tablet','dashicons-tablet')">
        <el-tooltip class="item" effect="dark" content="Tablet" placement="bottom">
          <DeviceIcon type="tablet" :iconStyle="{cursor: 'pointer'}" :active="activeDeviceName === 'dashicons-tablet'" />
        </el-tooltip>
      </span>
      <span class="wpsr-device-type-divider"></span>
      <span class="wpsr-responsive-bar-item wpsr-mobile-icon" @click.native="handleDevice('mobile','dashicons-smartphone')">
        <el-tooltip class="item" effect="dark" content="Mobile" placement="bottom">
          <DeviceIcon type="mobile" :iconStyle="{cursor: 'pointer'}" :active="activeDeviceName === 'dashicons-smartphone'" />
        </el-tooltip>
      </span>
    </div>
    <span class="wpsr-dropdown-device-manager">
        <!-- Dropdown UI -->
        <el-dropdown trigger="click" v-if="type === 'dropdown'" @visible-change="handleDropdownVisibility" ref="dropdown" @command="handleCommand" :hide-on-click="false" :teleported="false">
          <span class="item el-dropdown-link">
            <DeviceIcon :shouldSetFillExplicitly="true" :type="activeDeviceName === 'dashicons-desktop' ? 'desktop' : (activeDeviceName === 'dashicons-tablet' ? 'tablet' : 'mobile')" :iconStyle="{cursor: 'pointer'}" :active="true" />
          </span>
          <template #dropdown>
            <el-dropdown-menu class="wpsr-responsive-icon-wrapper">
              <el-dropdown-item v-if="activeDeviceName !== 'dashicons-desktop' && field.hide_desktop !== true" command="desktop">
                <el-tooltip class="item" effect="dark" content="Desktop" placement="left">
                  <DeviceIcon type="desktop" :shouldSetFillExplicitly="true" :iconStyle="{cursor: 'pointer'}" :active="activeDeviceName === 'dashicons-desktop'" />
                </el-tooltip>
              </el-dropdown-item>
              <el-dropdown-item v-if="activeDeviceName !== 'dashicons-tablet' && field.hide_tablet !== true" command="tablet">
                <el-tooltip class="item" effect="dark" content="Tablet" placement="left">
                  <DeviceIcon type="tablet" :shouldSetFillExplicitly="true" :iconStyle="{cursor: 'pointer'}" :active="activeDeviceName === 'dashicons-tablet'" />
                </el-tooltip>
              </el-dropdown-item>
              <el-dropdown-item v-if="activeDeviceName !== 'dashicons-smartphone' && field.hide_mobile !== true" command="mobile">
                <el-tooltip class="item" effect="dark" content="Mobile" placement="left">
                  <DeviceIcon type="mobile" :shouldSetFillExplicitly="true" :iconStyle="{cursor: 'pointer'}" :active="activeDeviceName === 'dashicons-smartphone'" />
                </el-tooltip>
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
    </span>

  </div>
</template>

<script type="text/babel">
import DeviceIcon from "../../pieces/icons/DeviceIcon.vue";

export default {
  name: 'ResponsiveBar',
  components: {
    DeviceIcon
  },
  props: {
    field: {
      type: Object,
      default: () => ({})
    },
    type: {
      type: String,
      default: ''
    },
    activeDevice: {
      type: String,
      default: 'dashicons-desktop'
    }
  },
  emits: ['handleDevice', 'handleDeviceResponsive'],
  data() {
    return {
      currentDevice: 'desktop',
      activeDeviceName: this.activeDevice,
      scrolled: false,
      dropdownVisible: false
    }
  },
  methods: {
    /**
     * Handle scroll events for sticky bar
     */
    handleScroll() {
      this.scrolled = window.scrollY > 0;
    },
    
    /**
     * Handle dropdown visibility changes
     */
    handleDropdownVisibility(visible) {
      this.dropdownVisible = visible;
    },
    
    /**
     * Handle dropdown command selection
     */
    handleCommand(command) {
      const deviceName = this.mapDeviceCommand(command);
      this.handleDevice(command, deviceName);
      
      // Close only the dropdown
      if (this.$refs.dropdown) {
        this.$refs.dropdown.handleClose();
      }
    },
    
    /**
     * Main device switching handler
     */
    handleDevice(deviceType, activeDevice) {
      this.switchDevice(deviceType, activeDevice);
      
      // Update local state
      this.currentDevice = deviceType;
      this.activeDeviceName = activeDevice;
      
      // Emit events to parent components
      this.$emit('handleDevice', deviceType, activeDevice);
      this.$emit('handleDeviceResponsive', deviceType, activeDevice);
    },

    /**
     * Handle device switching with all the necessary DOM manipulations
     * @param {string} deviceType - The device type (desktop, tablet, mobile)
     * @param {string} activeDeviceName - The active device icon name
     */
    switchDevice(deviceType, activeDeviceName) {
      this.currentDevice = deviceType;
      this.activeDeviceName = activeDeviceName;

      // Update jQuery classes for device switching
      this.updateDeviceClasses(deviceType);
      
      // Update viewport classes
      this.updateViewportClasses(deviceType);
      
      // Emit global event for other components
      this.emitGlobalDeviceEvent(deviceType, activeDeviceName);
    },

    /**
     * Update container classes based on device type
     * @param {string} deviceType - The device type
     */
    updateDeviceClasses(deviceType) {
      if (window.jQuery) {
        const jQuery = window.jQuery;
        const container = jQuery('.wpsr_social_review_admin .el-container');
        
        // Remove all device classes
        container.removeClass('is-desktop-active is-mobile-active is-tablet-active');
        
        // Add current device class
        container.addClass(`is-${deviceType}-active`);
      }
    },

    /**
     * Update viewport classes based on device type
     * @param {string} deviceType - The device type
     */
    updateViewportClasses(deviceType) {
      if (window.jQuery) {
        const jQuery = window.jQuery;
        const editorWrapper = jQuery('.wpsr_social_review_admin .wpsr_editor_wrapper');
        
        editorWrapper.removeClass('wpsr-view-port-active');
        
        if (deviceType !== 'desktop') {
          editorWrapper.addClass('wpsr-view-port-active');
        }
      }
    },

    /**
     * Emit global event for device change
     * @param {string} deviceType - The device type
     * @param {string} activeDeviceName - The active device icon name
     */
    emitGlobalDeviceEvent(deviceType, activeDeviceName) {
      if (window.WPSocialReviewsAdmin) {
        window.WPSocialReviewsAdmin.$emit('handleDeviceResponsive', deviceType, activeDeviceName);
      }
    },

    /**
     * Initialize device state on component mount
     */
    initializeDeviceState() {
      if (window.jQuery) {
        const jQuery = window.jQuery;
        const portClasses = jQuery('.wpsr_social_review_admin .el-container');
        const hasActiveClass = portClasses.hasClass('is-desktop-active') ||
                              portClasses.hasClass('is-mobile-active') ||
                              portClasses.hasClass('is-tablet-active');
        
        if (!hasActiveClass) {
          jQuery('.wpsr_social_review_admin .el-container').addClass('is-desktop-active');
        }
      }
    },

    /**
     * Initialize global event bus
     */
    initializeGlobalEventBus() {
      if (!window.WPSocialReviewsAdmin && window.Vue) {
        window.WPSocialReviewsAdmin = new window.Vue();
      }
    },

    /**
     * Get device map for command mapping
     */
    getDeviceMap() {
      return {
        'desktop': 'dashicons-desktop',
        'tablet': 'dashicons-tablet',
        'mobile': 'dashicons-smartphone'
      };
    },

    /**
     * Map device command to device name
     * @param {string} command - The device command
     * @returns {string} The mapped device name
     */
    mapDeviceCommand(command) {
      const deviceMap = this.getDeviceMap();
      return deviceMap[command] || 'dashicons-desktop';
    }
  },
  watch: {
    activeDevice: {
      handler(newVal) {
        this.activeDeviceName = newVal;
      },
      immediate: true
    }
  },
  mounted() {
    this.handleScroll();
    this.initializeDeviceState();
    this.initializeGlobalEventBus();

    if (window.jQuery) {
      const jQuery = window.jQuery;
      let viewPort = jQuery('.wpsr-editor-view-port');
      viewPort.scroll(() => {
        this.handleScroll();
      });
    }

    window.addEventListener('scroll', this.handleScroll);
  },
  
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
    if (window.jQuery) {
      const jQuery = window.jQuery;
      let viewPort = jQuery('.wpsr-editor-view-port');
      viewPort.off('scroll');
    }
  }
}
</script>