<template>
  <div class="wp_vue_editor_wrapper">
    <textarea v-if="hasWpEditor"
          class="wp_vue_editor"
          :id="editor_id">{{ modelValue }}
    </textarea>
    <textarea v-else
          class="wp_vue_editor wp_vue_editor_plain"
          v-model="plain_content"
          @click="updateCursorPos">
    </textarea>
  </div>
</template>

<script>
import { DarkModeMixin } from '../../../mixins/DarkModeMixin';

export default {
  name: 'wp_editor',
  mixins: [DarkModeMixin],
  emits: ['update:modelValue'],
  props: {
    editor_id: {
      type: String,
      default() {
        return 'wp_editor_' + Date.now() + parseInt(Math.random() * 1000);
      }
    },
    modelValue: {
      type: String,
      default() {
        return '';
      }
    },
    height: {
      type: Number,
      default() {
        return 200;
      }
    },
    extra_style: {
      default() {
        return ''
      }
    },
    media_buttons: {
      type: Boolean,
      default: true
    },
    quick_tags: {
      type: Boolean,
      default: true
    },
    toolbar: {
      type: String,
      default: 'link,blockquote,alignleft,aligncenter,alignright,underline,strikethrough,forecolor,removeformat,codeformat,outdent,indent'
    }
  },
  data() {
    return {
      showButtonDesigner: false,
      hasWpEditor: (!!window.wp.editor && !!wp.editor.autop) || !!window.wp.oldEditor,
      editor: window.wp.oldEditor || window.wp.editor,
      plain_content: this.modelValue,
      cursorPos: (this.modelValue) ? this.modelValue.length : 0,
      app_ready: false,
      buttonInitiated: false,
      currentEditor: false,
      isPopupVisible: false,
      editorInstance: null,
      lastCursorPosition: null,
      isInternalUpdate: false
    }
  },
  watch: {
    modelValue: {
      handler(newVal) {
        // Prevent infinite loops from internal updates
        if (this.isInternalUpdate) {
          return;
        }
        
        if (this.hasWpEditor && this.editorInstance) {
          const editor = this.editorInstance;
          
          // Only update if editor is in visual mode and visible
          if (!editor.isHidden()) {
            const bookmark = editor.selection.getBookmark(2, true);
            if (typeof editor.setContent === 'function') {
              editor.setContent(newVal);
            } else if (typeof tinyMCE !== 'undefined' && tinyMCE.get(this.editor_id)) {
              tinyMCE.get(this.editor_id).setContent(newVal);
            }
            if (bookmark) {
              editor.selection.moveToBookmark(bookmark);
            }
          } else {
            // In text/code mode, update the textarea value directly
            jQuery('#' + this.editor_id).val(newVal);
          }
        } else {
          this.plain_content = newVal;
        }
      },
      immediate: true
    },
    plain_content() {
      this.$emit('update:modelValue', this.plain_content);
    }
  },
  methods: {
    initEditor() {
      this.checkDarkMode();
      wp.editor.remove(this.editor_id);
      const that = this;
      wp.editor.initialize(this.editor_id, {
        mediaButtons: this.media_buttons,
        quicktags: this.quick_tags,
        tinymce: {
          height : that.height,
          toolbar1: this.toolbar,
          forced_root_block: '',
          content_style: this.isDarkMode ? '*{color:white;} body { background: #11171d; }' : '',
          setup(editor) {
            that.editorInstance = editor;
            editor.on('change', function () {
              that.isInternalUpdate = true;
              that.changeContentEvent();
              that.isInternalUpdate = false;
            });

            // Store cursor position on selection change
            editor.on('SelectionChange', function() {
              that.lastCursorPosition = editor.selection.getBookmark(2, true);
            });

            // Handle focus events
            editor.on('focus', function() {
              if (that.lastCursorPosition) {
                editor.selection.moveToBookmark(that.lastCursorPosition);
              }
            });

            editor.on('init', function(){
              const container = editor.getContainer();
              if (container) {
                container.style.border = 'none';
                container.style.borderRadius = '12px';
                container.style.overflow = 'hidden'; // Optional, to clip iframe corners
              }
              // Set initial cursor position at end if content exists
              if (that.modelValue) {
                editor.selection.select(editor.getBody(), true);
                editor.selection.collapse(false);
              }
            });

            // Handle keydown events
            editor.on('KeyDown', function(e) {
              // Update cursor position on key events
              that.lastCursorPosition = editor.selection.getBookmark(2, true);
            });
          }
        },
      });
      jQuery('#'+this.editor_id).on('change input', function() {
        that.isInternalUpdate = true;
        that.changeContentEvent();
        that.isInternalUpdate = false;
      });
    },
    changeContentEvent() {
      let content = '';
      const editor = tinyMCE.get(this.editor_id);
      if (editor && !editor.isHidden()) {
        // Visual mode - TinyMCE editor
        const bookmark = editor.selection.getBookmark(2, true);
        content = editor.getContent();
        content = content.replace(/\r?\n/g, '');
        this.$emit('update:modelValue', content);
        // Restore selection after content update
        editor.selection.moveToBookmark(bookmark);
        editor.focus();
      } else {
        // Text/Code mode - textarea
        content = jQuery('#' + this.editor_id).val();
        this.$emit('update:modelValue', content);
      }
    },
    updateCursorPos() {
      var cursorPos = jQuery('.wp_vue_editor_plain').prop('selectionStart');
      this.cursorPos = cursorPos;
    },
    onDarkModeChange(newDarkMode) {
      // Reinitialize editor with new theme when dark mode changes
      setTimeout(() => {
        this.initEditor();
      }, 100);
    }
  },
  mounted() {
    this.checkDarkMode();
    this.initEditor();
    this.app_ready = true;
  },
  beforeUnmount() {
    if (this.editor_id) {
      wp.editor.remove(this.editor_id);
    }
  }
}
</script>