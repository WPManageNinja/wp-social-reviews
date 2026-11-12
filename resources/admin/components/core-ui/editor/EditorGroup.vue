<template>
    <div class="wpsr_editor_edit_item-group" :class="`wpsr_editor_inside_gap_${sectionGap}`">
        <div
            class="wpsr_editor_edit_item"
            :class="{ 'wpsr_editor_edit_item--hidden': typeof field.condition !== 'undefined' && !add_condition(field) }"
            v-for="(field, key) in visibleFields" :key="key" >
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
         * Pass false inside a popover. A teleported el-select dropdown breaks the
         * popover's click detection and closes it.
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
            countMobileFollowsDesktop: null,
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
    computed: {
        // Pro-locked fields and options are flagged disabled (fields, options) or pro (image options).
        visibleFields() {
            if (!this.hide_pro_features) {
                return this.fieldsMaps;
            }

            const isLocked = item => !!item && (item.disabled === true || item.pro === true);

            return this.fieldsMaps
                .filter(field => !isLocked(field))
                .map(field => Array.isArray(field.options)
                    ? { ...field, options: field.options.filter(option => !isLocked(option)) }
                    : field);
        }
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

            if (!this.internalValue) {
                this.internalValue = {};
            }

            if (isResponsive) {
                if (!this.internalValue[fieldKey]) {
                    this.internalValue[fieldKey] = {};
                }
                const value = this.internalValue[fieldKey][this.device];
                
                if (value === undefined) {
                    if (fieldKey === 'paginate_number') {
                        const old = this.internalValue['paginate'];
                        const fromOld = old !== undefined
                            ? (typeof old === 'object' ? old[this.device] : old)
                            : undefined;
                        return fromOld !== undefined ? fromOld : 6;
                    }
                    return null;
                }
                return value;
            }

            const value = this.internalValue[fieldKey];

            if (typeof value === 'object' && value !== null) {
                if ('value' in value) {
                    return value.value;
                }
                return value;
            }

            if (this.fieldsMaps.find(field => field.fieldKey === fieldKey && field.type === 'wp_editor')) {
                return value !== undefined ? value : '';
            }

            return value !== undefined ? value : '';
        },
        handleValueUpdate(fieldKey, newValue, isResponsive = false, trackCountEdit = true) {
            if (this.isUpdating || !fieldKey) return;

            this.isUpdating = true;

            if (!this.internalValue) {
                this.internalValue = {};
            }

            if (trackCountEdit && isResponsive && fieldKey === this.countFieldKey()) {
                const count = this.internalValue[fieldKey] || {};
                if (this.device === 'mobile') {
                    this.countMobileFollowsDesktop = false;
                } else if (this.countMobileFollowsDesktop === null) {
                    // Saved settings always carry a mobile count, so only one that still matches desktop is treated as unset.
                    this.countMobileFollowsDesktop = String(count.mobile) === String(count.desktop);
                }
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
                if (typeof newValue === 'object' && newValue !== null) {
                    if ('value' in newValue) {
                        updatedValue[fieldKey] = newValue.value;
                    } else {
                        updatedValue[fieldKey] = newValue;
                    }
                } else {
                    if (this.fieldsMaps.find(field => field.fieldKey === fieldKey && field.type === 'wp_editor')) {
                        updatedValue[fieldKey] = newValue || '';
                    } else {
                        updatedValue[fieldKey] = newValue;
                    }
                }
            }

            this.internalValue = updatedValue;

            this.$emit('update:modelValue', updatedValue);

            setTimeout(() => {
                this.isUpdating = false;
            }, 0);
        },
        handleInputChange(fieldKey, newValue, isResponsive = false) {
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

            this.$emit('input', this.internalValue);
        },
        handleFieldChange() {
            this.$emit('on-change');
        },
        countFieldKey() {
          const field = (this.fieldsMaps || []).find(item => item.fieldKey === 'totalReviewsNumber' || item.fieldKey === 'total_posts_number');
          return field ? field.fieldKey : null;
        },
        handleDesktopMobileValue(val) {
          const key = this.countFieldKey();
          if (!key) return;

          // These counts have no tablet value, so tablet falls back to desktop.
          if (val === 'tablet') {
            this.device = 'desktop';
            this.activeDevice  = 'dashicons-desktop';
          }

          const count = this.internalValue && this.internalValue[key];
          if (val === 'mobile' && this.countMobileFollowsDesktop === true && count && count.desktop !== undefined && String(count.mobile) !== String(count.desktop)) {
            this.handleValueUpdate(key, count.desktop, true, false);
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
        if (this.$root) {
            this.$root.$on && this.$root.$on('handleDeviceResponsive', (val, activeDevice) => {
                this.handleDevice(val, activeDevice);
            });
        }
    },
    beforeUnmount() {
        if (this.$root && this.$root.$off) {
            this.$root.$off('handleDeviceResponsive');
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
                if (newVal && JSON.stringify(newVal) !== JSON.stringify(this.modelValue)) {
                    this.$emit('input', newVal);
                }
            },
            deep: true
        },
        'device': {
            handler: function (val) {
                this.handleDesktopMobileValue(val);
            },
            deep: true
        }
    }
}
</script>