<template>
  <div class="wpsr-element-spacing-wrapper">
    <div class="wpsr-element-responsive-spacing">
      <span class="wpsr-element-spacing-title wpsr-element-label">
        {{label}}
      </span>
      <ResponsiveBar
          :field="{}"
          type="dropdown"
          :activeDevice="activeDevice"
          @handleDevice="handleDevice"
          @handleDeviceResponsive="handleDeviceResponsive"
      />
    </div>

    <div class="wpsr-element-spacing-input-wrapper wpsr-element-spacing_input_wrapper_full">
      <ul class="wpsr-element-spacing-dimensions">
        <template v-if="spacing">
          <li v-for="(item, index) in spacing" :key="index">
            <template v-if="typeof item === 'object'">
              <input v-model="spacing[index][device]" type="number" placeholder="" @input="handleClick($event)" :disabled="!has_pro"/>
              <label>{{index}}</label>
            </template>
          </li>
          <li>
          <span
              @click="has_pro && handleLinkable()"
              class="wpsr-element-spacing-linked wpsr-element-spacing_item_linkable"
              :class="(spacing.linked == 'yes') ? 'wpsr-element-spacing_item_linked' : ''"
              :style="!has_pro ? 'pointer-events: none; opacity: 0.6;' : ''"
          >
              <span v-if="spacing.linked == 'yes'" class="dashicons dashicons-editor-unlink"></span>
              <span v-else class="dashicons dashicons-admin-links"></span>
          </span>
          </li>
        </template>
      </ul>
    </div>
  </div>
</template>

<script type="text/babel">
import FeedEditorGroup from "./EditorGroup";
import ResponsiveBar from './ResponsiveBar';

export default {
  name: 'SpacingElement',
  props: {
    spacing: {
      type: Object,
      required: true
    },
    label: {
      type: String,
      required: true
    }
  },
  emits: ['update:spacing'],
  components: {
    FeedEditorGroup,
    ResponsiveBar
  },
  data() {
    return {
      activeBtn: 'btn1',
      activeDevice: 'dashicons-desktop',
      device: 'desktop',
      active_value: '',
      device_changed: false,
    }
  },
  methods: {
    handleDevice(val, activeDevice) {
      this.device = val;
      this.activeDevice = activeDevice;
      this.device_changed = true;
      this.active_value = undefined;
    },
    handleDeviceResponsive(val, activeDevice) {
      // This method is needed to handle the event from ResponsiveBar
      this.device = val;
      this.activeDevice = activeDevice;
      this.device_changed = true;
      this.active_value = undefined;
    },
    handleLinkable() {
      if (!this.has_pro) return;
      if (this.active_value !== undefined) {
        this.handleValue(this.active_value);
      }
      if (this.spacing.linked == 'yes') {
        this.spacing.linked = 'no';
      } else {
        this.spacing.linked = 'yes';
      }
      this.$emit('update:spacing', this.spacing);
    },
    handleClick(e) {
      this.active_value = e.target.value;
      if(this.spacing.linked == 'yes') {
        this.handleValue(e.target.value);
      }
      this.$emit('update:spacing', this.spacing);
    },
    handleValue(value) {
      this.spacing.top[this.device] = value;
      this.spacing.right[this.device] = value;
      this.spacing.bottom[this.device] = value;
      this.spacing.left[this.device] = value;
    },
  },
  mounted() {
    this.$root.$on('handleDeviceResponsive', (val, activeDevice) => {
      this.handleDevice(val, activeDevice);
    });
  },
  beforeDestroy() {
    this.$root.$off('handleDeviceResponsive');
  },
}
</script>

<style scoped>
.el-dropdown i{
  font-size: 14px;
  width: 100%;
  height: 100%;
  cursor: pointer;
  color: #a4afb7;
  margin-top: 4px;
}

.el-dropdown i:hover{
  color: #1DA1F1;
}
.el-dropdown-menu {
  padding: 5px 0;
}
.el-dropdown-menu__item {
  line-height: 30px;
  padding: 0 5px;
  color: #606266;
}
.el-dropdown-menu__item i {
  font-size: 16px;
  padding-top: 6px;
  margin-right: 0px;
}
</style>