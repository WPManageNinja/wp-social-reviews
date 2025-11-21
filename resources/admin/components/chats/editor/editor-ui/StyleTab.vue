<template>
    <div class="wpsr-chat-style-tab">
        <EditorGroup
            :fieldsMaps="dynamicStyleFields"
            :modelValue="chat_config.styles"
            @update:modelValue="updateStyles"
        />
    </div>
</template>

<script>

import EditorGroup from './../../../core-ui/editor/EditorGroup.vue';
export default {
    name: 'StyleTab',
    props: {
        chat_config: {
        type: Object,
        required: true
        }
    },
    components: {
        EditorGroup
    },
    computed: {
        dynamicStyleFields() {
            let baseFields = [
            {
                fieldKey: 'header_color',
                type: 'color_picker',
                title: this.$t('Header Background Color'),
                flex: true,
            },
            {
                fieldKey: 'header_title_color',
                type: 'color_picker',
                title: this.$t('Header Title Color'),
                flex: true,
            },
            {
                fieldKey: 'header_caption_color',
                type: 'color_picker',
                title: this.$t('Header Caption Color'),
                flex: true,
            },
            {
                fieldKey: 'channel_icon_bg_color',
                type: 'color_picker',
                title: this.$t('Channel Icon Background Color'),
                flex: true,
            },
            {
                fieldKey: 'widget_icon_bg_color',
                type: 'color_picker',
                title: this.$t('Bubble Button Background Color'),
                flex: true,
            },
            {
                fieldKey: 'close_button_color',
                type: 'color_picker',
                title: this.$t('Close Button Color'),
                flex: true,
            },
            ];

            if (this.chat_config.chat_button.prefilled_message === 'true') {
            baseFields.push(
                {
                    fieldKey: 'message_background_color',
                    type: 'color_picker',
                    title: this.$t('Message Background Color'),
                    flex: true,
                },
                {
                    fieldKey: 'message_text_color',
                    type: 'color_picker',
                    title: this.$t('Message Text Color'),
                    flex: true,
                },
                {
                    fieldKey: 'send_button_icon_color',
                    type: 'color_picker',
                    title: this.$t('Prefilled Send Button Color'),
                    flex: true,
                },
                {
                    fieldKey: 'send_button_bg_color',
                    type: 'color_picker',
                    title: this.$t('Prefilled Send Button Background Color'),
                    flex: true,
                }
            );
            }
            return baseFields;
        },
    },
    methods:{
        updateStyles(newVal) {
            const updatedConfig = {
            ...this.chat_config,
            styles: newVal
            };
            this.$emit('update:chat_config', updatedConfig);
        },
    }
}
</script>