<template>
    <div :class="getFieldClass(field)">
        <span v-if="field.type === 'switch' || field.type  === 'number' || field.type  === 'select' || field.type === 'classic_number'"
            class="wpsr-editor-inside-left wpsr-input-field-label"
            :class="[
                field.type != 'textarea' ? 'wpsr-element-center' : 'wpsr-align-top',
                field.disabled ? 'wpsr-element-label' : ''
            ]">

          <span class="wpsr-label-with-tooltip">
            {{title}}
            <ProCrownIcon v-if="!field.tooltip && field.disabled && !proGroup" />
            <template v-if="field.coming_soon"></template>
            <template v-else>
                <span v-if="!field.disabled"></span>
            </template>
            <span v-if="field.tooltip" class="wpsr-tooltip-icon">
                <ProCrownIcon v-if="field.disabled && !proGroup" />
                <el-tooltip
                    :class="field.tooltip ? 'wpsr-tooltip-position' : ''"
                    :raw-content="true"
                    effect="dark"
                    placement="top-start"
                    :content="field.tooltipText"
                >
                    <el-icon><InfoFilled /></el-icon>
                </el-tooltip>
            </span>
          </span>


          <ResponsiveBar
              v-if="field.responsive"
              type="dropdown"
              :field="field"
              :activeDevice="activeDevice"
              @handleDevice="handleDevice"
          />
        </span>

        <span v-else-if="field.type === 'divider'" class="wpsr-editor-sidebar-section-divider"></span>
        <span v-else-if="field.type === 'section_title'" class="wpsr-editor-sidebar-section-title wpsr-label-small">
            {{ title }}
        </span>

        <span v-else-if="field.type === 'wp_editor'" class="wpsr-wp-editor-label wpsr-label-small">
                        <template v-if="field.coming_soon"></template>
                        <template v-else>
                            <span v-if="field.disabled === false">{{ title }}<ProCrownIcon v-if="field.disabled && !proGroup"/></span>
                            <span v-else>{{ title }}</span>
                        </template>
        </span>

        <div v-else-if="field.type !== 'image_select'" class="wpsr-editor-inside-left wpsr-input-field-label" 
            :class="[
                    (field.type != 'textarea' && field.type != 'multiple_select') ? 'wpsr-element-center' : 'wpsr-align-top',
                    field.disabled ? 'wpsr-element-label' : ''
                ]"
            >
            <span class="wpsr-label-with-tooltip">
                <span>
                    <template v-if="field.coming_soon"></template>
                    <template v-else>
                        <span>
                            {{ title }} 
                            <ProCrownIcon v-if="!field.tooltip && field.disabled && !proGroup" />
                        </span>
                    </template>
                </span>
            <span v-if="field.tooltip || field.note" :class=" field.type ==='textarea' ? 'wpsr-label-tooltip' : 'wpsr-tooltip-icon'">
                <ProCrownIcon v-if="field.disabled && !proGroup" />
                <el-tooltip
                    :raw-content="true"
                    effect="dark"
                    placement="top-start"
                    :content="(field.tooltip ? field.tooltipText : field.note)"
                >
                    <el-icon ><InfoFilled /></el-icon>
                </el-tooltip>
            </span>
          </span>
        </div>

        <div
            v-if="field.type === 'text' || field.type === 'textarea'"
            class="wpsr-editor-inside-right"
        >
            <el-input v-if="field.type === 'text'"
                class="wpsr-text-input wpsr-editor-text-input"
                type="text"
                v-model="localValue"
                :placeholder="field.placeholder ? field.placeholder : ''"
                :disabled="field.disabled"
            >
            </el-input>

            <el-input v-if="field.type === 'textarea'"
                type="textarea"
                class="wpsr-text-input wpsr-editor-text-input"
                v-model="localValue"
                :rows="6"
                :placeholder="field.placeholder ? field.placeholder : ''"
                :autosize="field.autosize ? field.autosize : {}"
                :disabled="field.disabled"
            >
            </el-input>
        </div>


        <!-- <div
      v-if="field.type === 'text' || field.type === 'textarea'"
      :class="['wpsr-editor-row', field.type === 'textarea' ? 'wpsr-editor-row--textarea' : '']"
    >
      <div class="wpsr-editor-label">
        {{ field.coming_soon ? '' : field.disabled === false ? title + ' (Pro)' : title }}
        <el-tooltip v-if="field.tooltip" class="item" effect="dark" placement="right-start">
          <template #content><div v-html="field.tooltipText"></div></template>
          <i class="el-icon-info"></i>
        </el-tooltip>
      </div>
      <div class="wpsr-editor-input">
        <el-input
          v-if="field.type === 'text'"
          type="text"
          v-model="localValue"
          :placeholder="field.placeholder ? field.placeholder : ''"
          :disabled="(field.disabled === false) ? true : false"
        />
        <el-input
          v-if="field.type === 'textarea'"
          type="textarea"
          v-model="localValue"
          :rows="3"
          :placeholder="field.placeholder ? field.placeholder : ''"
          :autosize="field.autosize ? field.autosize : {}"
          :disabled="(field.disabled === false) ? true : false"
        />
      </div>
    </div> -->

        <input v-if="field.type === 'classic_number'"
            type="number"
            v-model="localValue"
            @input="handleInputChange"
            :min="field.min"
            :max="field.max"
            :placeholder="field.placeholder ? field.placeholder : ''"
            :disabled="(field.disabled === false) ? true : false"
            class="wpsr-classic-number wpsr-editor-inside-right"
        >
        <!-- <el-input-number v-if="field.type  === 'number'"
            :min="field.min"
            :max="field.min"
            :precision="field.precision"
            :step="field.step"
            v-model="localValue"
            :disabled="(field.disabled === false) ? true : false"
        >
        </el-input-number> -->
        <div v-if="field.type  === 'number'" class="wpsr-reviews-number wpsr-editor-inside-right">
            <el-input-number
              v-model="localValue"
              :min="field.min"
              :max="field.max"
              :precision="field.precision"
              :step="field.step"
              controls-position="right"
              size="large"
              @change="handleChange"
              :disabled="field.disabled"
              class="wpsr-text-input"
            />
            <div class="wpsr-group-input">
              <el-button
                class="wpsr-editor-minus-btn"
                @click="localValue = Math.max(field.min !== undefined ? field.min : 0, (localValue || 0) - (field.step || 1))"
                :disabled="field.disabled"
              > <MinusIcon width="16" height="16" />
            </el-button>
              <el-button
                class="wpsr-editor-plus-btn"
                @click="localValue = field.max !== undefined ? Math.min(field.max, (localValue || 0) + (field.step || 1)) : ((localValue || 0) + (field.step || 1))"
                :disabled="field.disabled"
              ><PlusIcon width="16" height="16"/></el-button>
            </div>
        </div>

        <wp-editor v-if="field.type === 'wp_editor'"
            v-model="localValue"
            :editor_id="field.fieldKey"
            :height="field.settings?.editor_height || 200"
            :media_buttons="field.settings?.media_buttons || false"
            :toolbar="field.settings?.tinymce?.toolbar || 'formatselect,bold,italic,bullist,numlist,link'"
        >
        </wp-editor>

        <photo-uploader
            v-if="field.type === 'image'"
            enable_clear="yes"
            design_mode="horizontal"
            v-model="localValue"
            :disabled="field.disabled"
        >
        </photo-uploader>

        <image-select class="wpsr-editor-edit-item-field"
          :headerTitle="field.title"
          :class="field.class ? field.class : ''"
          :showSeeMoreButton="field.options && field.options.length < 3 ? false : (field.hasOwnProperty('shouldShowSeeMoreButton') ? field.shouldShowSeeMoreButton : true)"
          v-if="field.type === 'image_select'"
          v-model="localValue"
          :options="field.options"
        >
        </image-select>

        <el-time-picker
            v-if="field.type === 'time_picker'"
            arrow-control
            v-model="localValue"
            format="hh:mm:ss A"
            value-format="hh:mm:ss A"
            placeholder="Set your time"
            class="wpsr-editor-inside-right">
        </el-time-picker>

        <el-date-picker
            v-if="field.type === 'date_picker'"
            v-model="localValue"
            :picker-options="pickerOptions"
            type="date"
            :disabled="field.disabled"
            placeholder="Pick a day"
            class="wpsr-editor-inside-right">
        </el-date-picker>

        <el-switch
            v-if="field.type === 'switch'"
            v-model="localValue"
            active-color="#5c8df6"
            inactive-color="#b7b7b9"
            :active-value="field.active_value"
            :inactive-value="field.inactive_value"
            :disabled="field.disabled"
            class="wpsr-editor-inside-right"
        >
        </el-switch>

        <div v-if="field.type === 'select'" class="wpsr-editor-inside-right">
            <el-select
                v-if="field.type === 'select'"
                v-model="localValue"
                placeholder="Select"
                size="small"
                :filterable="field.isFilterable ? true : false"
                :teleported="isTypograpy ? true : false"
                :popper-append-to-body="isTypograpy ? true : false"
                class="wpsr-text-input"
            >
                <el-option
                    v-for="item in field.options"
                        :key="item.value"
                        :label="item.coming_soon ? item.label : item.disabled === true ? item.label + ' (Pro)' : item.label"
                        :value="item.value"
                        :disabled="item.disabled"
                >
                </el-option>
            </el-select>
        </div>

        <el-tabs v-model="localValue" class="wpsr-element-tab-wrapper wpsr-element-editor-tab wpsr-editor-inside-right" v-if="field.type === 'tab_select'">
          <el-tab-pane
              v-for="(item, key) in field.options"
              :key="key"
              :label="item.label"
              :name="item.value"
              :disabled="(item.disabled === false) ? true : false"
          ></el-tab-pane>
        </el-tabs>


        <div class="wpsr-editor-inside-right" v-if="field.type === 'multiple_select'">
            <el-select
                v-model="localValue"
                placeholder="Select"
                size="large"
                :disabled="field.disabled"
                multiple
                collapse-tags
                collapse-tags-tooltip
                :filterable="field.isFilterable ? true : false"
                class="wpsr-text-input"
            >
            <el-option
                v-for="(item, key) in field.options"
                :key="key"
                :label="item.disabled === true ? item[field.label] + ' (Pro)' : item[field.label]"
                :value="item[field.value]"
                :disabled="item.disabled"
            >
          </el-option>
        </el-select>
        </div>


        <div class="wpsr-editor-inside-right" v-if="field.type === 'dynamic_select'">
        <el-select
            v-model="localValue"
            placeholder="Select"
            size="small"
            :multiple="field.multiple"
            class="wpsr-text-input"
        >
          <el-option
              v-for="(item, key) in field.options"
              :key="key"
              :label="item.disabled === true ? item[field.label] + ' (Pro)' : item[field.label]"
              :value="item[field.value]"
              :disabled="item.disabled">
          </el-option>
        </el-select>
        </div>

        <div class="wpsr_color_picker wpsr-editor-inside-right" v-if="field.type === 'color_picker'">
            <el-color-picker
                v-model="localValue"
                @active-change="logColor"
                :predefine="predefineColors"
                show-alpha
                :disabled="field.disabled"
            >
            </el-color-picker>
            <!-- <span class="wpsr_color_value" :style="{backgroundColor: localValue}"></span> -->
        </div>

        <div class="wpsr_checkbox" v-if="field.type === 'checkbox_group'">
            <el-checkbox
                :indeterminate="isIndeterminate"
                v-model="checkAll"
                @change="handleCheckAllChange"
            >
                Check all
            </el-checkbox>
            <div style="margin: 10px 0;"></div>

            <el-checkbox-group v-model="localValue" @change="handleCheckChange">
                <el-checkbox v-for="item in field.options" :label="item" :key="item">
                    {{ item }}
                </el-checkbox>
            </el-checkbox-group>
        </div>

        <div style="align-items: center" class="wpsr-check-box-single wpsr-mb-10" v-if="field.type === 'checkbox'">
          <el-checkbox v-model="localValue" @change="$emit('on-change')">
            <h3 class="wpsr-sub-heading">{{ field.label }}</h3>
          </el-checkbox>
          <el-tooltip style="margin-left: 10px" v-if="field.checkboxTooltip" class="item" effect="dark" placement="right-start">
            <template #content><div v-html="field.checkboxTooltipText"></div></template>
            <i class="el-icon-info"></i>
          </el-tooltip>
        </div>

        <el-button
            v-if="field.type === 'button'"
            type="primary"
            size="small"
            @click="clickOnButton"
            class="wpsr-editor-inside-right"
        >
            {{ field.text }}
        </el-button>
    </div>
</template>

<script type="text/babel">
import WpEditor from './WpEditor';
import PhotoUploader from './PhotoUploader';
import ImageSelect from "./ImageSelect";
import ResponsiveBar from './ResponsiveBar';
import PlusIcon from '../../pieces/icons/PlusIcon';
import MinusIcon from '../../pieces/icons/MinusIcon';
import ProCrownIcon from '../../pieces/icons/ProCrownIcon.vue';

export default {
    props: {
        title: String,
        field: Object,
        sync_value: {
            type: [String, Number, Boolean, Array, Object],
            default: null
        },
        activeDevice: {
            type: String,
            default: 'dashicons-desktop'
        },
        isTypograpy: {
            type: Boolean,
            required: true
        },
        proGroup: {
            type: Boolean,
            default: false
        }
    },

    emits: ['update:modelValue', 'on-change', 'button_click', 'handleDevice', 'update:sync_value', 'update:color'],

    data() {
        return {
            localValue: null,
            checkAll: false,
            isIndeterminate: true,
            imageUrl: '',
            predefineColors: [
                '#ffffff',
                '#000000',
                '#ff4500',
                '#ff8c00',
                '#ffd700',
                '#90ee90',
                '#00ced1',
                '#1e90ff',
                '#c71585',
            ],
            pickerOptions: {
                disabledDate(time) {
                    return time.getTime() > Date.now();
                },
            }
        }
    },

    computed: {
        computedValue: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            }
        }
    },

    components: {
        WpEditor,
        PhotoUploader,
        ImageSelect,
        ResponsiveBar,
        PlusIcon,
        MinusIcon,
        ProCrownIcon
    },

    created() {
        this.initializeValue();
    },

    methods: {
        getFieldClass(field){


            if(field.type === 'text' || field.type === 'textarea'){
                return 'wpsr-filters-row';
            } else if(field.type === 'switch' || field.flex){
                return 'wpsr-settings-switch';
            } else if(field.type === 'wp_editor'){
                return 'wpsr-field-flex-column wpsr-wp-editor'
            } else if(field.type === 'multiple_select' || field.type === 'dynamic_select'){
                return 'wpsr-settings-select';
            } else if(field.type === 'checkbox_group' && field.display === 'grid'){
                return 'wpsr-filters-row-grid';
            }

            return 'wpsr-filters-row';
        },
        initializeValue() {
            this.localValue = this.sync_value;
        },

        handleInput(val) {
            // This method is kept for backward compatibility
            this.localValue = val;
            this.$emit('update:sync_value', val);
            if (this.field.event === 'on_change') {
                this.$emit('on-change');
            }
        },

        handleInputChange(e) {
            let val = Number(e.target.value);
            if(this.field.max && val > this.field.max) {
                val = this.field.max;
                this.$notify({
                    title: 'Disable',
                    message: 'Max limit is '+this.field.max,
                    type: 'error',
                    offset: 80
                });
            }
            if (this.activeDevice === 'dashicons-smartphone'){
                let field = this.field;
                if (field.fieldKey === 'total_posts_number'){
                    if (this.$root && this.$root.$emit) {
                        this.$root.$emit('totalPostsNumberInteraction', true);
                    }
                }
            }
            this.localValue = val;
            this.$emit('update:sync_value', val);
            if (this.field.event === 'on_change') {
                this.$emit('on-change');
            }
        },

        handleChange(val) {
            this.localValue = val;
            this.$emit('update:sync_value', val);
            if (this.field.event === 'on_change') {
                this.$emit('on-change');
            }
        },

        handleCheckAllChange(value) {
            this.localValue = value ? this.field.options : [];
            this.isIndeterminate = false;
            this.$emit('update:sync_value', this.localValue);
        },

        handleCheckChange(value) {
            let checkedCount = value.length;
            this.checkAll = checkedCount === this.field.options.length;
            this.isIndeterminate = checkedCount > 0 && checkedCount < this.field.options.length;
            this.$emit('update:sync_value', value);
        },

        setAccount(val) {
            if (!this.has_pro && val.length > 1) {
                this.localValue = [];
                this.localValue.push(val[0]);
                this.$emit('update:sync_value', this.localValue);
                this.handlePro();
            }
        },

        setValue(val) {
            this.localValue = val;
            this.$emit('update:sync_value', val);
        },

        clickOnButton() {
            this.$emit('button_click', this.localValue);
        },

        updateColor() {
            this.$emit('update:sync_value', this.localValue);
            this.$emit('update:color', this.localValue);
        },

        logColor(color) {
            this.localValue = color;
            this.$emit('update:sync_value', color);
            this.$emit('update:color', color);
        },

        handleDevice(val, activeDevice) {
            this.$emit('handleDevice', val, activeDevice);
        },

        handleSelectChange(val) {
            this.$emit('update:modelValue', val);
            if (this.field.event === 'on_change') {
                this.$emit('on-change');
            }
        },

        handleMultipleSelectChange(val) {
            if (this.field.pro_handler) {
                this.setAccount(val);
            } else {
                this.$emit('update:modelValue', val);
            }
        },

        handleDynamicSelectChange(val) {
            this.$emit('update:modelValue', val);
            if (this.field.event === 'on_change') {
                this.$emit('on-change');
            }
        }
    },

    watch: {
        sync_value: {
            handler(newVal) {
                this.localValue = newVal;
                if (this.field.type === 'checkbox_group') {
                    let checkedCount = newVal ? newVal.length : 0;
                    this.checkAll = checkedCount === this.field.options.length;
                    this.isIndeterminate = checkedCount > 0 && checkedCount < this.field.options.length;
                }
            },
            immediate: true,
            deep: true
        },
        localValue: {
            handler(newVal) {
                this.$emit('update:sync_value', newVal);
            },
            deep: true
        }
    }
}
</script>