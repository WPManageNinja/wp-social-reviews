<template>
  <div>
    <div v-if="hasFluentCart">
      <!-- Add More Product Button - Only visible when there are already connected products -->
      <el-button
          v-if="Object.keys(reviewsinfo).length"
          style="width: 100%"
          :class="{'display-form': !!addNewBusiness}"
          class="wpsr_primary_btn_outline"
          type="success"
          :disabled="!has_pro ? true : false"
          @click.prevent="addBusiness">
        <el-icon size="18"><Link /></el-icon>
        {{ $t('Add More Product') }} {{ !has_pro ? $t(' (Pro)') : ''}}
      </el-button>

      <div class="wpsr-accounts-connect-wrapper wpsr-mt-20" v-if="(addNewBusiness || Object.keys(reviewsinfo).length === 0)">
        <div class="wpsr-connection-modal-input-wrapper">
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <h4 class="wpsr-connection-modal-input-heading">
              {{$t('Select a product to fetch reviews')}}
            </h4>
          </div>
          <AsyncMultipleSelect
              v-model="selectedProductId"
              searchRoute="pages/search"
              :multiple="false"
              :includeEverywhere="false"
              :extraParams="{ post_type: 'fluent-products' }"
              class="wpsr-text-input wpsr-select-input-field"
              style="width: 100%;"
              @update:modelValue="onProductChange"
          />
        </div>
        <SaveAndResetButton
            :platFormName="platFormName"
            :verifyPlatform="verifyPlatform"
            :reviewsinfo="reviewsinfo"
            :isDisable="!selectedProductId"
            @save-reviews="saveReviews"
        />
      </div>
    </div>
    <div v-if="!hasFluentCart" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
      <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
      <p>Please ensure that the <strong>FluentCart</strong> plugin is installed and activated, then check whether your FluentCart products are listed in your dashboard.</p>
    </div>
  </div>
</template>
<script>
import SaveAndResetButton from './SaveAndResetButton';
import AsyncMultipleSelect from '../../../core-ui/editor/AsyncMultipleSelect';
export default {
  name: 'FluentCartForm',
  components: {
    SaveAndResetButton,
    AsyncMultipleSelect
  },
  props: {
    sourceId: {
      type: [String, Number],
      default: ''
    },
    verifyPlatform: {
      type: Boolean,
      default: false
    },
    platFormName: {
      type: String,
      default: ''
    },
    reviewsinfo: {
      type: Object,
      default: () => ({})
    },
    hasFluentCart: {
      type: Boolean,
      default: false
    },
  },
  emits: ['update:sourceId', 'save-reviews'],
  data(){
    return{
      addNewBusiness: false,
      selectedProductId: this.sourceId
    }
  },
  watch: {
    sourceId(newValue) {
      this.selectedProductId = newValue;
    }
  },
  methods:{
    saveReviews(){
      this.$emit('save-reviews');
      this.selectedProductId = '';
    },
    addBusiness(){
      this.addNewBusiness = !this.addNewBusiness;
    },
    onProductChange(newValue) {
      this.$emit('update:sourceId', newValue);
    }
  },

  mounted() {
    this.addNewBusiness = false;
  }
}
</script>
