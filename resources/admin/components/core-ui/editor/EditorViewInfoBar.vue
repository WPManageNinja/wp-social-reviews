<template>
  <div class="wpsr-editor-view-info-bar wpsr-responsive-bar">
    <span class="wpsr-editor-info wpsr-body-regular wpsr-body-regular-color">{{ editorInfo }}</span>
    <!-- Device Manager Component - handles all device switching logic and UI -->
    <ResponsiveBar 
      :field="field"
      :type="type"
      :active-device="activeDeviceName" 
      @handle-device="onHandleDevice"
      @handle-device-responsive="onHandleDeviceResponsive"
    />
    <span class="wpsr-template-selector">
        <span class="wpsr-template-selector-icon">
            <TemplateIcon
                class="wpsr-editor-icon"
                width="16"
                height="16"
            />
            <span class="wpsr-template-selector-label wpsr-paragraph-small wpsr-heading-text">{{ $t('Template') }}:</span>
        </span>

        <span class="wpsr-label-small wpsr-heading-text">{{ (templateName || 'Default').charAt(0).toUpperCase() + (templateName || 'Default').slice(1) }}</span>
    </span>
  </div>
</template>
<script type="text/babel">
import ResponsiveBar from "./ResponsiveBar.vue";
import ChatEditorAccordionsIconPack from "../../pieces/icons/ChatEditorAccordionsIconPack.vue";
import TemplateIcon from "../../pieces/icons/TemplateIcon.vue";
export default {
  name: 'EditorViewInfoBar',
  components: { 
    ResponsiveBar,
    ChatEditorAccordionsIconPack,
    TemplateIcon
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
      default: ''
    },
    templateName: {
      type: String,
      default: ''
    },
    editorInfo:{
      type: String,
      default: ''
    }
  },
  emits: ['handleDevice', 'handleDeviceResponsive'],
  data() {
    return {
      activeDeviceName: this.activeDevice ? this.activeDevice : 'dashicons-desktop'
    }
  },
  methods: {
    onHandleDevice(deviceType, activeDevice) {
      // this.$emit('handleDevice', deviceType, activeDevice);
    },
    onHandleDeviceResponsive(deviceType, activeDevice) {
      // this.$emit('handleDeviceResponsive', deviceType, activeDevice);
    }
  },
  watch: {
    activeDevice: {
      handler(newVal) {
        this.activeDeviceName = newVal;
      }
    }
  }
}
</script>