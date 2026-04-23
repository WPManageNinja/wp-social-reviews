<template>
  <div class="wpsr-element-box-shadow-wrapper">
    <div class="wpsr-box-shadow-header-row">
      <FeedEditorGroup
          v-if="boxShadow"
          :fieldsMaps="box_shadow_style_settings"
          :modelValue="boxShadow"
          @update:modelValue="newVal => $emit('update:boxShadow', newVal)"
          class="wpsr-editor-edit-item-field"
      />
      <el-button type="default" size="medium" class="wpsr-box-shadow-reset-btn" :title="$t('Back to Default')" @click="resetToDefault">
        <el-icon><Refresh /></el-icon>
      </el-button>
    </div>
    <span :style="show_property ? 'display:block;': 'display:none;'">
      <div class="wpsr-element-box-shadow-inputs">
         <FeedEditorGroup
             v-if="boxShadow"
             :fieldsMaps="box_shadow_color_settings"
             :modelValue="boxShadow"
             @update:modelValue="newVal => $emit('update:boxShadow', newVal)"
             class="wpsr-editor-edit-item-field wpsr-mb-10 wpsr-mt-10"
         />
        <FeedEditorGroup
            v-if="boxShadow"
            :fieldsMaps="box_shadow_dimension_settings"
            :modelValue="boxShadow"
            @update:modelValue="newVal => $emit('update:boxShadow', newVal)"
            class="wpsr-editor-edit-item-field"
        />
      </div>
    </span>
  </div>
</template>

<script>
import FeedEditorGroup from "./EditorGroup";

export default {
  name: 'BoxShadowElement',
  components: {
    FeedEditorGroup
  },
  props: {
    boxShadow: {
      type: Object,
      required: true
    },
    identifier: {
      type: String,
      required: false,
      default: ''
    }
  },
  emits: ['update:boxShadow'],
  data() {
    return {
      show_property: false,
      box_shadow_style_settings: [
        {
          fieldKey: 'box_shadow_style',
          type: 'select',
          title: this.$t('Box Shadow'),
          flex: true,
          options: [
            {
              value: 'none',
              label: this.$t('None')
            },
            {
              value: 'custom',
              label: this.$t('Custom')
            },
          ],
          disabled: !this.has_pro
        },
      ],
      box_shadow_dimension_settings: [
        {
          fieldKey: 'horizontal',
          type: 'number',
          title: this.$t('Horizontal'),
          flex: true,
          placeholder: '0',
          disabled: !this.has_pro
        },
        {
          fieldKey: 'vertical',
          type: 'number',
          title: this.$t('Vertical'),
          flex: true,
          placeholder: '0',
          disabled: !this.has_pro
        },
        {
          fieldKey: 'blur',
          type: 'number',
          title: this.$t('Blur'),
          flex: true,
          placeholder: '0',
          disabled: !this.has_pro
        },
        {
          fieldKey: 'spread',
          type: 'number',
          title: this.$t('Spread'),
          flex: true,
          placeholder: '0',
          disabled: !this.has_pro
        },
        {
          fieldKey: 'inset',
          type: 'select',
          title: this.$t('Inset'),
          flex: true,
          options: [
            { value: 'no', label: this.$t('No') },
            { value: 'yes', label: this.$t('Yes') },
          ],
          disabled: !this.has_pro
        },
      ],
      box_shadow_color_settings: [
        {
          fieldKey: 'color',
          type: 'color_picker',
          title: this.$t('Color'),
          flex: true,
          disabled: !this.has_pro
        },
      ],
    }
  },
  methods: {
    getDefaultBoxShadow() {
      return {
        box_shadow_style: 'none',
        horizontal: '',
        vertical: '',
        blur: '',
        spread: '',
        color: '',
        inset: 'no'
      };
    },
    resetToDefault() {
      this.$emit('update:boxShadow', this.getDefaultBoxShadow());
    }
  },
  watch: {
    boxShadow: {
      handler(val) {
        if (!val || typeof val.box_shadow_style === 'undefined') {
          this.show_property = false;
          return;
        }

        if (val.box_shadow_style === 'custom') {
          this.show_property = true;
        }
        else {
          this.show_property = false;
        }
      },
      deep: true,
      immediate: true
    },
  },
}
</script>

<style scoped>
.wpsr-box-shadow-header-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.wpsr-box-shadow-header-row .wpsr-editor-edit-item-field {
  flex: 1;
}
.wpsr-box-shadow-reset-btn {
  flex-shrink: 0;
}
.wpsr-element-box-shadow-inputs {
  margin-top: 8px;
}
</style>
