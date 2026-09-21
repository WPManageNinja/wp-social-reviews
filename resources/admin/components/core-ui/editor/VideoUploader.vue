<template>
  <div class="wpsr-video-uploader">
    <el-input
        class="wpsr-input-default wpsr-border-all-around"
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        size="small"
        @focus="isEditing = true"
        @blur="isEditing = false"
        @update:model-value="value => $emit('update:modelValue', value)"
    />

    <div class="wpsr-video-or"><span>{{ $t('or') }}</span></div>

    <div class="wpsr-video-upload-row">
      <button
          type="button"
          class="wpsr-photo-upload-btn"
          :disabled="disabled"
          @click="initUploader"
      >
        {{ $t('Upload') }}
      </button>
      <span class="wpsr-video-upload-hint">{{ $t('Pick an MP4, WebM or MOV file from your media library.') }}</span>
    </div>
  </div>
</template>

<script type="text/babel">
import { markRaw } from 'vue';
import { wpsrIsHttpUrl } from '../../../utils/videoUrl';

export default {
  name: 'VideoUploader',
  props: {
    modelValue: {
      type: String,
      default: ''
    },
    placeholder: {
      type: String,
      default: 'Paste a YouTube, Vimeo or direct file link'
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  // The form owns the message and the Save button, so it needs the verdict too.
  emits: ['update:modelValue', 'update:invalid'],
  data() {
    return {
      frame: null,
      // While the field has focus the error stays away, or it would fire mid-typing.
      isEditing: false
    }
  },
  computed: {
    showUrlError() {
      return !this.isEditing && !!this.modelValue && !wpsrIsHttpUrl(this.modelValue);
    }
  },
  methods: {
    /**
     * Open the media library, restricted to what the player renders
     *
     * The type list matches WPSR_VIDEO_FILE_PATTERN; plain 'video' is wider.
     */
    initUploader() {
      if (this.disabled || !window.wp || !window.wp.media) {
        return;
      }

      if (!this.frame) {
        this.frame = markRaw(window.wp.media({
          title: this.$t('Select or Upload Video'),
          button: {text: this.$t('Use this video')},
          library: {type: ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-m4v']},
          multiple: false
        }));

        this.frame.on('select', () => {
          const selected = this.frame.state().get('selection').first();
          const attachment = selected ? selected.toJSON() : {};

          if (attachment.url) {
            this.isEditing = false;
            this.$emit('update:modelValue', attachment.url);
          }
        });
      }

      this.frame.open();
    }
  },
  watch: {
    showUrlError: {
      handler(invalid) {
        this.$emit('update:invalid', invalid);
      },
      immediate: true
    }
  },
  /** Releases the form: a field that never became valid must not block save. */
  beforeUnmount() {
    this.$emit('update:invalid', false);

    if (this.frame) {
      this.frame.off();
      this.frame = null;
    }
  }
}
</script>
