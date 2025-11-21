<template>
    <div class="wpsr_editor_edit_item-group" :class="`wpsr_editor_inside_gap_${sectionGap}`">
        <div
            class="wpsr_editor_edit_item"
            :class="{ 'wpsr_editor_edit_item--hidden': typeof field.condition !== 'undefined' && !add_condition(field) }"
            v-for="(field, key) in fieldsMaps" :key="key" >
            <editor-element
                    v-if="field.responsive && (typeof field.condition === 'undefined' || (add_condition(field)))"
                    :title="field.title"
                    :field="field"
                    :sync_value="getFieldValue(field.fieldKey, true)"
                    :activeDevice="activeDevice"
                    :isTypograpy="isTypograpy"
                    :proGroup="proGroup"
                    @update:sync_value="(val) => handleValueUpdate(field.fieldKey, val, true)"
                    @button_click="handleClick"
                    @handleDevice="handleDevice"
                    @on-change="handleFieldChange"
                    @update:color="(val) => handleValueUpdate(field.fieldKey, val, true)"
            >
            </editor-element>

            <editor-element
                    v-else-if="typeof field.condition === 'undefined' || (add_condition(field))"
                    :title="field.title"
                    :field="field"
                    :sync_value="getFieldValue(field.fieldKey)"
                    :isTypograpy="isTypograpy"
                    :proGroup="proGroup"
                    @update:sync_value="(val) => handleValueUpdate(field.fieldKey, val)"
                    @button_click="handleClick"
                    @on-change="handleFieldChange"
                    @update:color="(val) => handleValueUpdate(field.fieldKey, val)"
            ></editor-element>
        </div>
    </div>
</template>
<script>
import EditorElement from './EditorElement';

export default {
    name: 'EditorGroup',
    props: {
        fieldsMaps: {
            type: Array,
            required: true
        },
        modelValue: {
            type: Object,
            required: true
        },
        sectionGap:{
            type: Number,
            default: 16
        },
        proGroup: {
            type: Boolean,
            default: false
        },
        /**
         * Controls dropdown teleportation behavior in EditorElement.
         *
         * Element Plus (Vue 3) Issue:
         * When using el-select inside el-popover, the dropdown options can cause the popover
         * to close unexpectedly. This happens because Element Plus teleports the dropdown
         * options to the document body by default, which breaks the popover's click detection.
         *
         * Version Differences:
         * - Vue 2 + Element UI: Popover stays open with trigger="manual" and v-model
         * - Vue 3 + Element Plus: More challenging due to stricter popper/focus management
         *
         * Solution:
         * - Set false when using dropdowns inside popovers to disable teleportation
         * - Set true (default) for normal dropdown behavior elsewhere
         *
         * @see https://element-plus.org/en-US/component/select.html#select-attributes (teleported prop)
         */
        isTypograpy: {
            type: Boolean,
            default: true
        }
    },
    emits: ['update:modelValue', 'on-change', 'button_click', 'handleDevice', 'input'],
    data () {
        return {
            device: 'desktop',
            activeDevice: 'dashicons-desktop',
            isMobileValueChanged: false,
            userInteraction: false,
            hasUserGivenValue: false,
            totalPostsNumberInteraction: false,
            internalValue: null,
            isUpdating: false,
            lastEmittedValue: null
        }
    },
    components:{
        EditorElement
    },
    created() {
        this.initializeValue();
    },
    methods: {
        initializeValue() {
            if (this.modelValue !== undefined && this.modelValue !== null) {
                this.internalValue = JSON.parse(JSON.stringify(this.modelValue));
                this.lastEmittedValue = JSON.parse(JSON.stringify(this.modelValue));
            } else {
                this.internalValue = {};
                this.lastEmittedValue = {};
            }
        },
        getFieldValue(fieldKey, isResponsive = false) {
            if (!fieldKey) return null;

            // Ensure internalValue is initialized
            if (!this.internalValue) {
                this.internalValue = {};
            }

            if (isResponsive) {
                if (!this.internalValue[fieldKey]) {
                    this.internalValue[fieldKey] = {};
                }
                return this.internalValue[fieldKey][this.device] !== undefined
                    ? this.internalValue[fieldKey][this.device]
                    : null;
            }

            // Get the value from internalValue
            const value = this.internalValue[fieldKey];

            // For select/dropdown fields
            if (typeof value === 'object' && value !== null) {
                if ('value' in value) {
                    return value.value;
                }
                return value;
            }

            // For wp_editor fields, ensure we return the content
            if (this.fieldsMaps.find(field => field.fieldKey === fieldKey && field.type === 'wp_editor')) {
                return value !== undefined ? value : '';
            }

            return value !== undefined ? value : '';
        },
        handleValueUpdate(fieldKey, newValue, isResponsive = false) {
            if (this.isUpdating || !fieldKey) return;

            this.isUpdating = true;

            // Ensure internalValue is initialized
            if (!this.internalValue) {
                this.internalValue = {};
            }

            const updatedValue = JSON.parse(JSON.stringify(this.internalValue || {}));

            if (isResponsive) {
                if (!updatedValue[fieldKey]) {
                    updatedValue[fieldKey] = {};
                }
                updatedValue[fieldKey] = {
                    ...updatedValue[fieldKey],
                    [this.device]: newValue
                };
            } else {
                // Handle different types of values
                if (typeof newValue === 'object' && newValue !== null) {
                    if ('value' in newValue) {
                        updatedValue[fieldKey] = newValue.value;
                    } else {
                        updatedValue[fieldKey] = newValue;
                    }
                } else {
                    // For wp_editor fields, ensure we store the content
                    if (this.fieldsMaps.find(field => field.fieldKey === fieldKey && field.type === 'wp_editor')) {
                        updatedValue[fieldKey] = newValue || '';
                    } else {
                        updatedValue[fieldKey] = newValue;
                    }
                }
            }

            // Update internal value immediately
            this.internalValue = updatedValue;

            // Only emit update:modelValue for v-model compatibility
            this.$emit('update:modelValue', updatedValue);

            setTimeout(() => {
                this.isUpdating = false;
            }, 0);
        },
        handleInputChange(fieldKey, newValue, isResponsive = false) {
            // Only update the internal value without emitting
            if (!this.internalValue) {
                this.internalValue = {};
            }

            if (isResponsive) {
                if (!this.internalValue[fieldKey]) {
                    this.internalValue[fieldKey] = {};
                }
                this.internalValue[fieldKey] = {
                    ...this.internalValue[fieldKey],
                    [this.device]: newValue
                };
            } else {
                // Handle different types of values
                if (typeof newValue === 'object' && newValue !== null) {
                    if ('value' in newValue) {
                        this.internalValue[fieldKey] = newValue.value;
                    } else {
                        this.internalValue[fieldKey] = newValue;
                    }
                } else {
                    this.internalValue[fieldKey] = newValue;
                }
            }

            // Emit input event for v-model
            this.$emit('input', this.internalValue);
        },
        handleFieldChange() {
            this.$emit('on-change');
        },
        handleDesktopMobileValue(val, fieldsMaps) {
          if (!fieldsMaps) return;

          const key = fieldsMaps.some(field => field.fieldKey === 'totalReviewsNumber')
              ? 'totalReviewsNumber'
              : 'total_posts_number';


          let totalPostsNumberObject = fieldsMaps.find((obj) => {
            return obj.fieldKey === key;
          });

          // stop activate tablet input field for total posts number or total Reviews Number
          if(val === 'tablet' && totalPostsNumberObject !== undefined && totalPostsNumberObject.fieldKey === key) {
            this.device = 'desktop';
            this.activeDevice  = 'dashicons-desktop';
          }

          if (val === 'mobile' && totalPostsNumberObject) {
            if (this.hasUserGivenValue === true) return;
            if (this.isMobileValueChanged === false && this.userInteraction === true) {
              // Ensure the property exists before accessing it
              if (this.value && this.value[key]) {
                if (this.value[key].desktop !== undefined) {
                  this.value[key].mobile = this.value[key].desktop;
                }
              }
            }
          }
        },
        checkStatus() {
          let mobileValue = this.value && this.value.total_posts_number && this.value.total_posts_number.mobile;
          if(mobileValue && mobileValue !== 0){
            this.hasUserGivenValue = true;
          }
        },
        add_condition(field) {
            if (field.condition) {
                const conditions = Array.isArray(field.condition) ? field.condition : [field.condition];
                let overallResult = true;

                for (let i = 0; i < conditions.length; i++) {
                    const condition = conditions[i];
                    let operator = condition.operator || '==';

                    if (condition.data) {
                        if (operator === '==') {
                            if (condition.data[condition.key] !== condition.selector) {
                                overallResult = false;
                                break;
                            }
                        } else if (operator === '!=') {
                            if (condition.data[condition.key] === condition.selector) {
                                overallResult = false;
                                break;
                            }
                        } else if (operator === 'includes') {
                            let selector_arr = condition.selector;
                            if (!selector_arr.includes(condition.data[condition.key])) {
                                overallResult = false;
                                break;
                            }
                        }
                    } else {
                        if (operator === '==') {
                            if (this.internalValue[condition.key] !== condition.selector) {
                                overallResult = false;
                                break;
                            }
                        } else if (operator === '!=') {
                            if (this.internalValue[condition.key] === condition.selector) {
                                overallResult = false;
                                break;
                            }
                        } else if (operator === 'multiple') {
                            let result = true;
                            condition.terms.forEach((term) => {
                                result &= (this.internalValue[term.key] === term.selector);
                            });
                            if (!result) {
                                overallResult = false;
                                break;
                            }
                        } else if (operator === 'isTrue') {
                            if (this.internalValue[condition.key] !== true) {
                                overallResult = false;
                                break;
                            }
                        } else {
                            let selector_arr = condition.selector;
                            if (!selector_arr.includes(this.internalValue[condition.key])) {
                                overallResult = false;
                                break;
                            }
                        }
                    }
                }

                return overallResult;
            }

            return true;
        },
        handleClick(val) {
            this.$emit('button_click', val);
        },
        handleDevice(val, activeDevice) {
            this.device = val;
            this.activeDevice = activeDevice;
            this.$emit('handleDevice', val, activeDevice);
        },
    },
    mounted() {
        // In Vue 3, we need to use a different approach for global events
        // If you have a global event bus, use that instead
        if (this.$root) {
            this.$root.$on && this.$root.$on('handleDeviceResponsive', (val, activeDevice) => {
                this.handleDevice(val, activeDevice);
            });

            this.$root.$on && this.$root.$on('totalPostsNumberInteraction', (val) => {
                this.totalPostsNumberInteraction = val;
            });
        }

        this.checkStatus();
    },
    beforeUnmount() {
        // Clean up event listeners in Vue 3
        if (this.$root && this.$root.$off) {
            this.$root.$off('handleDeviceResponsive');
            this.$root.$off('totalPostsNumberInteraction');
        }
    },
    watch: {
        modelValue: {
            handler(newVal) {
                if (newVal && !this.isUpdating) {
                    const newValueStr = JSON.stringify(newVal);
                    const currentValueStr = JSON.stringify(this.lastEmittedValue);

                    if (newValueStr !== currentValueStr) {
                        this.lastEmittedValue = JSON.parse(JSON.stringify(newVal));
                        this.internalValue = JSON.parse(JSON.stringify(newVal));
                    }
                }
            },
            deep: true,
            immediate: true
        },
        internalValue: {
            handler(newVal) {
                if (newVal && JSON.stringify(newVal) !== JSON.stringify(this.value)) {
                    this.$emit('input', newVal);
                }
            },
            deep: true
        },
        'internalValue.total_posts_number.desktop': {
            handler: function (newVal, oldVal) {
                this.userInteraction = true;
            },
            deep: true
        },
        'internalValue.total_posts_number.mobile': {
            handler: function (val) {
                if (this.totalPostsNumberInteraction) {
                    this.isMobileValueChanged = true;
                }
            },
            deep: true
        },
        'device': {
            handler: function (val) {
                this.handleDesktopMobileValue(val, this.fieldsMaps);
            },
            deep: true
        }
    }
}
</script>