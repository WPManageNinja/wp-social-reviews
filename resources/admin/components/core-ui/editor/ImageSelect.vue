<template>
    <optionSelectorPopup
            :title="headerTitle"
            v-model:visible="showEditor"
        >
        <template #trigger>
            <div class="wpsr_element_box">
                <div v-if="shouldShowTitle" class="wpsr_images_trigger_header">
                    <span class="wpsr_images_trigger_title wpsr-label-small">{{headerTitle}}</span>
                    <span class="wpsr_images_trigger_header_action">
                        <span v-if="shouldShowBackToDefaultButton && modelValue && modelValue !==''">
                            <el-tooltip 
                                content="Back to default icon"
                                class="item" effect="dark" placement="right-start">
                                <el-button @click="backToDefault" icon="Refresh" size="small" class="wpsr-reset-icon wpsr_default_btn wpsr_icon_btn">
                                </el-button>
                            </el-tooltip>
                        </span>
                        <el-button v-if="showSeeMoreButton && localShowSeeMoreButton" class="wpsr_plain_btn wpsr_btn_with_icon" @click.stop="showEditor = true">
                            <span> {{$t('See All')}}</span>
                            <ArrowRightIcon/>
                        </el-button>
                    </span>
                </div>
                <div class="wpsr_element_selection" v-loading="loading" element-loading-text="">
                    <transition-group name="fade-move" tag="div" class="wpsr_template_images_row">
                        <TemplateDemoImage 
                            v-for="(imgObj, idx) in triggerImages"
                            :key="imgObj.key"
                            :imageSrc="imgObj.img"
                            :shouldPaddingNeedToAdd="shouldPaddingNeedToAdd"
                            :label="getTemplateLabel(imgObj)"
                            :isSelected="imgObj.key === modelValue"
                            :isProFeature="!!(imgObj && imgObj.pro)"
                            @click="() => selectItem(imgObj)"
                        />
                    </transition-group>
                </div>
            </div>
        </template>
        <template #content>
            <div
                class="wpsr_editor_selections"
                :style="{'grid-template-columns': `repeat(${triggerImageCount}, 1fr)`}"
                v-loading="loading"
                element-loading-text=""
            >
                <template v-for="selection in options" :key="selection.key">
                    <TemplateDemoImage 
                        :imageSrc="selection.img"
                        :label="getTemplateLabel(selection)"
                        :isProFeature="!!(selection && selection.pro)"
                        :isSelected="selection.key === modelValue"
                        @click="() => selectItem(selection)"
                    />
                </template>
            </div>
        </template>
    
    </optionSelectorPopup>
</template>

<script>
import { findIndex } from 'lodash'
import optionSelectorPopup from './optionSelectorPopup.vue'
import { ArrowRight, Check } from '@element-plus/icons-vue'
import  TemplateDemoImage  from './TemplateDemoImage.vue'
import ArrowRightIcon from '../../pieces/icons/ArrowRightIcon.vue'
export default {
    name: 'ImageSelect',
    components:{
        optionSelectorPopup,
        ArrowRightIcon,
        TemplateDemoImage,
        Check
    },
    props: {
        modelValue: {
            type: [String, Number],
            required: true
        },
        options: {
            type: Array,
            required: true
        },
        headerTitle: {
            type: String,
            default: 'Select a Template'
        },
        showSeeMoreButton:{
            type: Boolean,
            default: true
        },
        loading: {
            type: Boolean,
            default: false
        }

    },
    emits: ['update:modelValue'],
    data() {
        return {
            showEditor: false,
            shouldPaddingNeedToAdd: true,
            triggerImageCount: 3, // Number of images to show in the trigger,
            shouldShowBackToDefaultButton: false,
            shouldShowTitle: true,
            localShowSeeMoreButton: true
        }
    },
    computed: {
        activeImage() {
            const index = findIndex(this.options, { key: this.modelValue })
            return index !== -1 ? this.options[index] : {}
        },
        triggerImages() {
            const opts = this.options || [];
            const idx = findIndex(opts, { key: this.modelValue });
            const count = this.triggerImageCount;
            if (opts.length === 0 || idx === -1) return [];
            // Calculate start and end indices to always show 'count' images, centered on selected if possible
            let start = idx - Math.floor((count - 1) / 2);
            let end = start + count;
            if (start < 0) {
            end += -start;
            start = 0;
            }
            if (end > opts.length) {
            start -= end - opts.length;
            end = opts.length;
            if (start < 0) start = 0;
            }
            return opts.slice(start, end);
        }
    },
    methods: {
        selectItem(selection) {
            
            if (!selection.pro && !selection.coming_soon) {
                this.$emit('update:modelValue', selection.key)
            }
        },
        getTemplateLabel(selection){
            return selection.title; 
        }, 
        backToDefault() {
            this.$emit('update:modelValue', '');
            this.showEditor = false;
        }
    },
    mounted() {
        if(this.headerTitle === this.$t('Select Bubble Icon')) {
            this.triggerImageCount = 4;
            this.shouldShowBackToDefaultButton = true;
        }
        if(this.headerTitle === this.$t('Layout Type')) {
            this.triggerImageCount = 4;
            this.localShowSeeMoreButton = false;
            this.shouldPaddingNeedToAdd = false;
            this.shouldShowTitle = false;
        }
        
        if(this.options.length <= this.triggerImageCount) {
            this.localShowSeeMoreButton = false;
        }
    }
}
</script>

<style scoped>
.fade-move-enter-active, .fade-move-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-move-enter-from, .fade-move-leave-to {
  opacity: 0;
  transform: translateY(10px) scale(0.98);
}
.fade-move-leave-active {
  position: absolute;
  display: none;
}
</style>