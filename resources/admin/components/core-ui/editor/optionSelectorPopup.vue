<template>
    <div class="wpsr-full-width">
        <div class="wpsr_element_box">
            <div class="wpsr_element_selection">
                <slot name="trigger">
                    <!-- Default trigger can go here -->
                </slot>
            </div>
        </div>
        <transition name="slide-left">
            <div v-if="showEditor" class="wpsr_editor_overflow">
                <slot name="header">
                    <div class="wpsr-option-selector-popup-header wpsr-channel-config-section-header">
                        <span class="wpsr_pointer" @click="closeEditor">
                            <ArrowLeftIcon />
                            <span class="wpsr-label-small wpsr-heading-text">
                                {{ title || $t('Select an Option') }}
                            </span>
                        </span>
                    </div>
                </slot>
                <div class="wpsr_editor_overflow_content">
                    <slot name="content">
                        <!-- Default content can go here -->
                    </slot>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import ArrowLeftIcon from '../../pieces/icons/ArrowLeftIcon.vue';
export default {
    inheritAttrs: false,
    name: 'OptionSelectorPopup',
    components: {
        ArrowLeftIcon
    },
    props: {
        visible: {
            type: Boolean,
            default: undefined
        },
        modelValue: {
            type: [String, Number],
            required: false
        },
        options: {
            type: Array,
            required: false
        },
        title: {
            type: String,
            default: ''
        }
    },
    emits: ['update:modelValue', 'update:visible'],
    data() {
        return {
            internalShowEditor: false
        }
    },
    computed: {
        showEditor: {
            get() {
                return this.visible !== undefined ? this.visible : this.internalShowEditor;
            },
            set(val) {
                if (this.visible !== undefined) {
                    this.$emit('update:visible', val);
                } else {
                    this.internalShowEditor = val;
                }
            }
        }
    },
    methods: {
        closeEditor() {
            this.showEditor = false;
        },
        selectItem(option) {
            if (!option.pro && !option.coming_soon) {
                this.$emit('update:modelValue', option.key)
            }
        }
    }
}
</script>
