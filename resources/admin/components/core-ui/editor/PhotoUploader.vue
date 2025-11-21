<template>
  <div class="wpsr-editor-inside-right">
    <div class="wpsr-photo-uploader-actions">
      <div class="wpsr-photo-preview" @click="initUploader">
        <img v-if="modelValue" :src="modelValue" alt="Preview" />
        <span v-else class="wpsr-photo-placeholder">
          <DummyImage />
        </span>
      </div>
      <button class="wpsr-photo-upload-btn" type="button" @click="initUploader" :disabled="disabled">
        {{$t('Upload')}}
      </button>
    </div>
  </div>
</template>

<script type="text/babel">
import DummyImage from '../../pieces/icons/DummyImage';

export default {
  name: 'photo_widget',
  components: {
    DummyImage
  },
  props: {
    modelValue: {
      type: String,
      default: ''
    },
    design_mode: {
      type: String,
      default: 'small'
    },
    enable_clear: {
      type: String,
      default: 'yes'
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue'],
  data() {
    return {
      app_ready: false,
      design_mode_name: this.design_mode,
      enable_clear_name: this.enable_clear
    }
  },
  methods: {
    initUploader(event) {
      if (this.disabled) {
        return;
      }

      // Prevent event bubbling to avoid conflicts
      event.preventDefault();
      event.stopPropagation();

      const that = this;
      const send_attachment_bkp = wp.media.editor.send.attachment;

      wp.media.editor.send.attachment = function (props, attachment) {
        that.$emit('update:modelValue', attachment.url);
        wp.media.editor.send.attachment = send_attachment_bkp;
      };

      // Use a more reliable approach for the media uploader
      // Create a temporary button element to ensure proper jQuery targeting
      const tempButton = document.createElement('button');
      tempButton.style.display = 'none';
      document.body.appendChild(tempButton);

      try {
        wp.media.editor.open(jQuery(tempButton));
      } finally {
        // Clean up the temporary element
        setTimeout(() => {
          if (tempButton.parentNode) {
            tempButton.parentNode.removeChild(tempButton);
          }
        }, 100);
      }

      return false;
    },
    clearImage() {
      this.$emit('update:modelValue', '');
    }
  },
  mounted() {
    if (!window.wpActiveEditor) {
      window.wpActiveEditor = null;
    }
    this.app_ready = true;
  }
}
</script>