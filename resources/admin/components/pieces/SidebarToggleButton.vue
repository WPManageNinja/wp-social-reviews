<template>
    <el-tooltip
        effect="dark"
        :content="minimized ? $t('Show settings') : $t('Hide settings')"
        placement="left"
    >
        <button
            type="button"
            class="wpsr-editor-sidebar-toggle wpsr_icon_button"
            :class="{ 'wpsr-editor-sidebar-toggle--minimized': minimized }"
            :aria-label="minimized ? $t('Show settings') : $t('Hide settings')"
            @click="$emit('toggle')"
        >
            <SidebarToggleIcon class="wpsr-editor-sidebar-toggle-icon" />
        </button>
    </el-tooltip>
</template>
<script>
import SidebarToggleIcon from './icons/SidebarToggleIcon';
export default {
    name: 'SidebarToggleButton',
    components: { SidebarToggleIcon },
    props: {
        minimized: {
            type: Boolean,
            default: false
        }
    },
    emits: ['toggle']
}
</script>
