<template>
  <div>
      <el-form label-position="top">
        <div class="wpsr-shoppable-promo-modal">
          <div class="wpsr-d-flex wpsr-jc-between wpsr-flex-column">
            <div class="wpsr-modal-field-group wpsr-mb-15">
              <label class="wpsr-field-label">{{$t('Select Source')}}</label>
              <el-select class="wpsr-modal-select wpsr-select-input-field" v-model="shoppable_fields.source_type" placeholder="Select" size="large" @change="$emit('on_post_source_change')">
                <el-option
                    v-for="(item, index) in post_types"
                    :key="index"
                    :label="item.title"
                    :value="item.name">
                </el-option>
              </el-select>
            </div>
            <div v-if="shoppable_fields.source_type.length" class="wpsr-modal-field-group wpsr-mb-15">
              <label v-if="shoppable_fields.source_type === 'custom_url'" class="wpsr-field-label">{{$t('URL')}}</label>
              <label v-else class="wpsr-field-label">{{$t('Link To')}}</label>

              <el-select
                  v-if="shoppable_fields.source_type !== 'custom_url'"
                  v-model="shoppable_fields.url_settings.id"
                  class="wpsr-modal-select wpsr-select-input-field"
                  size="large"
                  filterable
                  clearable
                  allow-create
                  default-first-option
                  placeholder="Select or start typing...">
                <el-option
                    v-for="(item, index) in posts"
                    :key="index"
                    :label="item.title"
                    :value="item.id">
                </el-option>
              </el-select>
              <error-view :errors="errors" :field="'id'" />

              <el-input v-if="shoppable_fields.source_type === 'custom_url'" type="text" size="small" placeholder="Enter your URL" v-model="shoppable_fields.url_settings.url" class="wpsr-modal-input wpsr-input-default wpsr-text-input wpsr-input-default wpsr-border-all-around"></el-input>

              <error-view :errors="errors" :field="'url'" />
            </div>

            <div class="wpsr-modal-field-group wpsr-mb-15">
              <el-checkbox v-model="shoppable_fields.url_settings.open_in_new_tab" class="wpsr-modal-checkbox">{{$t('Open in a new tab')}}</el-checkbox>
            </div>

            <div class="wpsr-modal-field-group">
              <label class="wpsr-field-label">Button Text</label>
              <el-input
                  class="wpsr-modal-input wpsr-input-default wpsr-text-input wpsr-input-default wpsr-border-all-around"
                  placeholder="Button Text"
                  v-model="shoppable_fields.url_settings.text"
                  size="small">
              </el-input>
            </div>
          </div>
        </div>
       </el-form>
       
       <div class="wpsr-shoppable-promo-footer dialog-footer">
         <el-button type="default" class="wpsr_default_btn" @click="$emit('close_shoppable_modal')">{{ $t('Cancel') }}</el-button>
         <el-button type="primary" class="wpsr_primary_btn" @click="$emit('add_template_settings')">
           {{ $t('Apply') }}
         </el-button>
       </div>
  </div>
</template>

<script>
import ErrorView from './../../errors/errorView'

export default {
  name: 'ShoppableModal',
  components: {
    ErrorView
  },
  props: {
    shoppable_fields: {
      type: Object,
      required: true
    },
    post_types: {
      type: Array,
      default: () => []
    },
    posts: {
      type: Array,
      default: () => []
    },
    errors: {
      type: Object,
      default: () => ({})
    }
  },
  emits: ['on_post_source_change', 'add_template_settings']
}
</script>