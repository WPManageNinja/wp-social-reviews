<template>
    <div v-if="has_pro && styles_config && default_styles && default_styles.styles" class="wpsr-style-reset-bar">
        <span class="wpsr-style-reset-bar-label">{{ $t('Customize each section below') }}</span>
        <RemoveConfirm
            submit_button_type="text"
            :displayRefreshLeft="true"
            :button_text="$t('Reset All')"
            :message="$t('Reset every style on this template to its default? Nothing changes on your site until you click Save.')"
            @confirm="resetStyles"
        />
    </div>
</template>

<script type="text/babel">
import RemoveConfirm from './RemoveConfirm.vue';

export default {
    name: 'StyleResetButton',
    components: {
        RemoveConfirm
    },
    props: {
        styles_config: {
            type: Object,
            default: null
        },
        default_styles: {
            type: Object,
            default: null
        }
    },
    methods: {
        resetStyles() {
            if (!this.styles_config || !this.default_styles || !this.default_styles.styles) {
                return;
            }
            this.styles_config.styles = JSON.parse(JSON.stringify(this.default_styles.styles));
        }
    }
}
</script>
