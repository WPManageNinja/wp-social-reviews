<template>
  <div class="wpsr-element-spacing-wrapper wpsr-element-label wpsr-slider-element-wrapper">
    <div class="wpsr-element-responsive-spacing">
      <span class="wpsr-element-spacing-title wpsr-element-label">
        {{title}}
      </span>
      <ResponsiveBar
          :field="{}"
          type="dropdown"
          :activeDevice="activeDevice"
          @handleDevice="handleDevice"
          @handleDeviceResponsive="handleDeviceResponsive"
      />
    </div>

    <div v-for="(item, index) in spacing" :key="index" class="wpsr-element-slider-wrapper">
      <template v-if="typeof item === 'object'" >
        <div class="wpsr-reviews-number wpsr-editor-inside-right">
          <el-input-number
              v-model="spacing[index][device]"
              :min="min"
              :max="max"
              controls-position="right"
              size="small"
              @change="handleValueChange"
              class="wpsr-text-input"
              :disabled="!has_pro"
          />
        </div>
        <div class="wpsr-runway-slider" style="width: 70px;">
          <el-slider size="small" :min="min" :max="max" v-model="spacing[index][device]" @change="handleValueChange" :disabled="!has_pro"></el-slider>
        </div>
      </template>
    </div>
  </div>
</template>

<script type="text/babel">
import FeedEditorGroup from "./EditorGroup";
import ResponsiveBar from './ResponsiveBar';

export default {
  name: 'SliderElement',
  props: {
    spacing: {
      type: Object,
      required: true
    },
    slider: {
      type: [Object, Boolean],
      required: true
    },
    title: {
      type: String,
      required: true
    },
    max: {
      type: Number,
      default: 100
    },
    min: {
      type: Number,
      default: 0
    }
  },
  emits: ['update:spacing'],
  components: {
    FeedEditorGroup,
    ResponsiveBar
  },
  data() {
    return {
      activeDevice: 'dashicons-desktop',
      activeBtn: 'btn1',
      device: 'desktop',
      value: 0,
    }
  },
  methods: {
    handleDevice(val, activeDevice) {
      this.device = val;
      this.activeDevice = activeDevice;
    },
    handleDeviceResponsive(val, activeDevice) {
      // This method is needed to handle the event from ResponsiveBar
      this.device = val;
      this.activeDevice = activeDevice;
    },
    handleValueChange() {
        this.$emit('update:spacing', this.spacing);
    },
    decrement(index, device) {
        if (!this.has_pro) return;
        let currentValue = this.spacing[index][device];
        if (typeof currentValue !== 'number') {
            currentValue = this.min;
        }
        const newValue = Math.max(this.min, currentValue - 1);
        this.spacing[index][device] = newValue;
        this.$emit('update:spacing', this.spacing);
    },
    increment(index, device) {
        if (!this.has_pro) return;
        let currentValue = this.spacing[index][device];
        if (typeof currentValue !== 'number') {
            currentValue = this.min;
        }
        const newValue = Math.min(this.max, currentValue + 1);
        this.spacing[index][device] = newValue;
        this.$emit('update:spacing', this.spacing);
    }
  },
  mounted() {
    this.$root.$on('handleDeviceResponsive', (val, activeDevice) => {
      this.handleDevice(val, activeDevice);
    });
  },
  beforeUnmount() {
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