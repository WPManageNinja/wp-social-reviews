<template>
  <div class="wpsr-element-border-wrapper">
    <FeedEditorGroup
        v-if="border"
        :fieldsMaps="border_settings"
        :modelValue="border"
        @update:modelValue="newVal => $emit('update:border', newVal)"
        class="wpsr-editor-edit-item-field"
    />
    <span :style="show_property ? 'display:block;': 'display:none;'">
      <SpacingElement :label="'Width'" v-if="border" :spacing="border" />
      <FeedEditorGroup
          v-if="border"
          :fieldsMaps="border_color_settings"
          :modelValue="border"
          @update:modelValue="newVal => $emit('update:border', newVal)"
          class="wpsr-editor-edit-item-field"
      />
    </span>
  </div>
</template>

<script>
import FeedEditorGroup from "./EditorGroup";
import SpacingElement from "./SpacingElement";
export default {
  name: 'BorderElement',
  props: {
    border: {
      type: Object,
      required: true
    },
    identifier: {
      type: String,
      required: false,
      default: ''
    }
  },
  emits: ['update:border'],
  components: {
    FeedEditorGroup,
    SpacingElement
  },
  data() {
    return {
      show_property: false,
      border_settings: [
        {
          fieldKey: 'border_style',
          type: 'select',
          title: this.$t('Border Type'),
          flex: true,
          options: [
            {
              value: 'solid',
              label: this.$t('Solid')
            },
            {
              value: 'double',
              label: this.$t('Double')
            },
            {
              value: 'dotted',
              label: this.$t('Dotted')
            },
            {
              value: 'dashed',
              label: this.$t('Dashed')
            },
            {
              value: 'groove',
              label: this.$t('Groove')
            },
            {
              value: '',
              label: this.$t('None')
            },
          ],
          disabled: !this.has_pro
        },
      ],
      border_color_settings: [
        {
          fieldKey: 'border_color',
          type: 'color_picker',
          title: this.$t('Color'),
          flex : true,
          disabled: !this.has_pro
        },
      ],
    }
  },
  watch: {
    border: {
      handler(val) {
        if (val.border_style !== '') {
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