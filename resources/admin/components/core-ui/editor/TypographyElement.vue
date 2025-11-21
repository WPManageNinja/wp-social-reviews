<template>
  <div class="wpsr-element-typography-wrapper">
    <div class="wpsr-element-typography-header wpsr-editor-inside-left">
      <h3>Typography</h3>
      <span>
        <el-tooltip class="item" effect="dark" placement="right-start">
          <template #content>Back to default.</template>
          <el-button @click="$emit('resetMe', identifier)" size="small" class="wpsr-reset-icon">
            <el-icon><Refresh /></el-icon>
          </el-button>
        </el-tooltip>
      </span>
    </div>
    <div class="wpsr-element-typography-body">
      <FeedEditorGroup
        v-if="typography"
        :fieldsMaps="typography_settings"
        :modelValue="typography"
        :isTypograpy="false"
        @update:modelValue="newVal => $emit('update:typography', newVal)"
        class="wpsr-element-typography wpsr-editor-inside-right"
      />
    </div>
    
  </div>
</template>

<script type="text/babel">
import FeedEditorGroup from "./EditorGroup";
export default {
  name: 'TypographyElement',
  props: {
    typography: {
      type: Object,
      required: true
    },
    identifier: {
      type: String,
      required: true
    }
  },
  emits: ['resetMe', 'update:typography'],
  components: {
    FeedEditorGroup
  },
  data() {
    return {
      typography_settings: [
        {
          fieldKey: 'font_size',
          type: 'classic_number',
          title: this.$t('Size'),
          min: 1,
          max: 200,
          flex: true,
          responsive: true,
        },
        {
          fieldKey: 'font_weight',
          type: 'select',
          title: this.$t('Weight'),
          options: [
            {
              value: '100',
              label: this.$t('100')
            },
            {
              value: '200',
              label: this.$t('200')
            },
            {
              value: '300',
              label: this.$t('300')
            },
            {
              value: '400',
              label: this.$t('400')
            },
            {
              value: '500',
              label: this.$t('500')
            },
            {
              value: '600',
              label: this.$t('600')
            },
            {
              value: '700',
              label: this.$t('700')
            },
            {
              value: '800',
              label: this.$t('800')
            },
            {
              value: '900',
              label: this.$t('900')
            },
            {
              value: '',
              label: this.$t('Default')
            },
            {
              value: 'normal',
              label: this.$t('Normal')
            },
            {
              value: 'bold',
              label: this.$t('Bold')
            },
          ]
        },
        {
          fieldKey: 'text_transform',
          type: 'select',
          title: this.$t('Transform'),
          options: [
            {
              value: '',
              label: this.$t('Default')
            },
            {
              value: 'uppercase',
              label: this.$t('Uppercase')
            },
            {
              value: 'lowercase',
              label: this.$t('Lowercase')
            },
            {
              value: 'capitalize',
              label: this.$t('Capitalize')
            },
            {
              value: 'none',
              label: this.$t('Normal')
            },
          ]
        },
        {
          fieldKey: 'font_style',
          type: 'select',
          title: this.$t('Style'),
          options: [
            {
              value: '',
              label: this.$t('Default')
            },
            {
              value: 'normal',
              label: this.$t('Normal')
            },
            {
              value: 'italic',
              label: this.$t('Italic')
            },
            {
              value: 'oblique',
              label: this.$t('Oblique')
            },
          ]
        },
        {
          fieldKey: 'text_decoration',
          type: 'select',
          title: this.$t('Decoration'),
          options: [
            {
              value: '',
              label: this.$t('Default')
            },
            {
              value: 'underline',
              label: this.$t('Underline')
            },
            {
              value: 'overline',
              label: this.$t('Overline')
            },
            {
              value: 'line-through',
              label: this.$t('Line Through')
            },
            {
              value: 'underline overline',
              label: this.$t('Underline Overline')
            },
            {
              value: 'none',
              label: this.$t('None')
            },
          ]
        },
        {
          fieldKey:'line_height',
          type: 'classic_number',
          title:this.$t('Line-Height'),
          min:0,
          max:100,
          flex: true,
          responsive: true,
        },
        {
          fieldKey:'letter_spacing',
          type: 'classic_number',
          title:this.$t('Letter Spacing'),
          min:0,
          max:20,
          flex: true,
          responsive: true,
        },
      ],
    }
  },
}
</script>