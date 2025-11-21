<template>
  <div>
    <div v-if="products.length">
      <el-button
          v-if="Object.keys(reviewsinfo).length"
          style="width: 100%"
          v-model="addNewBusiness"
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
          <el-select class="wpsr-text-input wpsr-select-input-field" style="width: 100%;" v-model="selectedProductId" @change="onProductChange" filterable="true" clearable placeholder="Select a product" size="large">
            <el-option
                v-for="(product, key) in products"
                :key="key"
                :label="product.post_title"
                :value="product.ID"
            >
            </el-option>
          </el-select>
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
    <div v-else class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
      <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
      <p>Please ensure that you have the <strong>WooCommerce</strong> plugin installed, and check if your WooCommerce products are listed in your dashboard.</p>
    </div>
  </div>
</template>
<script>
import SaveAndResetButton from './SaveAndResetButton';
export default {
  name: 'WoocommerceForm',
  components: {
    SaveAndResetButton
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
    products: {
      type: Array,
      default: () => []
    },
  },
  emits: ['update:sourceId', 'save-reviews'],
  data(){
    return{
      type: 'text',
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
      // Reset selectedProductId after saving reviews
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