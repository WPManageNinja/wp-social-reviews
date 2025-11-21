<template>
    <div class="wpsr_template_demo_image_wrapper" :class="{'wpsr_is_disabled': !this.hasPro && isProFeature}" @click="handleClick">
        <span :class="[{'wpsr_selected_template': isSelected}, shouldPaddingNeedToAdd ? 'wpsr_template_image' : 'wpsr_template_image_without_padding']">
            <img :src="imageSrc" :alt="label" :class="{'wpsr_svg_color_filter': isSvg}" />
        </span>
        <div class="wpsr_template_label wpsr-label-small" :class="{'wpsr-disabled-text-color': !this.hasPro && isProFeature}">
            <span>{{ label }}</span>
            <span class="wpsr_label_icon" v-if="isProFeature">
                <ProCrownIcon :height="12" :width="12"/>
            </span>
            
        </div>
    </div>
</template>

<script>
import ProCrownIcon from '../../pieces/icons/ProCrownIcon.vue'
export default {
    name: 'TemplateDemoImage',
    components:{
        ProCrownIcon
    },
    props: {
        imageSrc:{
            type: String,
            required: true
        },
        label:{
            type: String,
            required: true
        },
        isSelected:{
            type:Boolean,
            required:true
        },
        isProFeature:{
            type:Boolean,
            required:true,
        },
        shouldPaddingNeedToAdd:{
            type:Boolean,
            required:false,
            default: true
        }
    },
    emits: ['click'],
    computed: {
        isSvg() {
            return this.imageSrc && this.imageSrc.toLowerCase().endsWith('.svg');
        }
    },
    methods: {
        handleClick(event) {
            event.stopPropagation();
            this.$emit('click');
        }
    }
}
</script>