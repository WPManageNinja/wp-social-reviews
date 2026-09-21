const SIDEBAR_MIN_WIDTH = 320;
const SIDEBAR_MAX_WIDTH = 640;

export const editorSidebarMixin = {
  data() {
    return {
      wpsrSidebarMinimized: false,
      wpsrSidebarWidth: 390,
      wpsrSidebarResizing: false,
      _wpsrSidebarResizeStartX: 0,
      _wpsrSidebarResizeStartWidth: 0
    }
  },
  computed: {
    wpsrSidebarMinWidth() {
      return SIDEBAR_MIN_WIDTH;
    },
    wpsrSidebarMaxWidth() {
      return SIDEBAR_MAX_WIDTH;
    }
  },
  methods: {
    toggleEditorSidebar() {
      this.wpsrSidebarMinimized = !this.wpsrSidebarMinimized;

      this.$nextTick(() => {
        if (typeof window !== 'undefined') {
          window.dispatchEvent(new Event('resize'));
        }
      });
    },
    startSidebarResize(event) {
      if (this.wpsrSidebarMinimized) {
        return;
      }

      this._wpsrSidebarResizeStartX = event.clientX;
      this._wpsrSidebarResizeStartWidth = this.wpsrSidebarWidth;
      this.wpsrSidebarResizing = true;

      document.addEventListener('mousemove', this._handleSidebarResizeMove);
      document.addEventListener('mouseup', this._handleSidebarResizeEnd);
    },
    stepSidebarResize(delta) {
      if (this.wpsrSidebarMinimized) {
        return;
      }

      this.wpsrSidebarWidth = Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, this.wpsrSidebarWidth + delta));
    },
    _handleSidebarResizeMove(event) {
      const delta = this._wpsrSidebarResizeStartX - event.clientX;
      const nextWidth = this._wpsrSidebarResizeStartWidth + delta;

      this.wpsrSidebarWidth = Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, nextWidth));
    },
    _handleSidebarResizeEnd() {
      this.wpsrSidebarResizing = false;

      document.removeEventListener('mousemove', this._handleSidebarResizeMove);
      document.removeEventListener('mouseup', this._handleSidebarResizeEnd);
    }
  },
  beforeUnmount() {
    document.removeEventListener('mousemove', this._handleSidebarResizeMove);
    document.removeEventListener('mouseup', this._handleSidebarResizeEnd);
  }
}
