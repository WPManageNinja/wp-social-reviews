<template>
    <div
        class="wpsr-editor-sidebar-resize-handle"
        role="separator"
        tabindex="0"
        aria-orientation="vertical"
        :aria-label="$t('Resize sidebar')"
        :aria-valuemin="min"
        :aria-valuemax="max"
        :aria-valuenow="width"
        @mousedown="$emit('resize-start', $event)"
        @keydown="onKeydown"
    ></div>
</template>
<script>
const KEYBOARD_STEP = 16;

export default {
    name: 'SidebarResizeHandle',
    props: {
        width: {
            type: Number,
            required: true
        },
        min: {
            type: Number,
            required: true
        },
        max: {
            type: Number,
            required: true
        }
    },
    emits: ['resize-start', 'resize-step'],
    methods: {
        onKeydown(event) {
            switch (event.key) {
                case 'ArrowLeft':
                    event.preventDefault();
                    this.$emit('resize-step', KEYBOARD_STEP);
                    break;
                case 'ArrowRight':
                    event.preventDefault();
                    this.$emit('resize-step', -KEYBOARD_STEP);
                    break;
                case 'Home':
                    event.preventDefault();
                    this.$emit('resize-step', this.min - this.width);
                    break;
                case 'End':
                    event.preventDefault();
                    this.$emit('resize-step', this.max - this.width);
                    break;
            }
        }
    }
}
</script>
