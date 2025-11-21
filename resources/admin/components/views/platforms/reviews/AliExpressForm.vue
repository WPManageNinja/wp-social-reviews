<template>
  <div>
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
      {{ $t('Add More Business') }} {{ !has_pro ? $t(' (Pro)') : ''}}
    </el-button>

    <div class="wpsr-accounts-connect-wrapper" :class="Object.keys(reviewsinfo).length ? 'wpsr-mt-20' : ''" v-if="(addNewBusiness || Object.keys(reviewsinfo).length === 0)">
      <div class="wpsr-connection-modal-input-wrapper">
        <div class="wpsr-connection-modal-input-heading-wrapper">
          <h4 class="wpsr-connection-modal-input-heading">
            {{ $t('Add Your Product ID Here') }}
          </h4>
          <p class="wpsr-connection-modal-input-description">Copy the full business ID/product ID from url and paste it into the input field to display your reviews. [e.g. https://www.aliexpress.com/item/1005003652462987.html here 1005003652462987 is your product ID.]</p>
        </div>
        <div class="wpsr-mb-20">
          <el-input
              v-model="localSourceId"
              autocomplete="off"
              placeholder="Enter Your Product  ID.."
              class="wpsr-input-default"
              @input="$emit('sourceId', $event)"
          >
            <template #prepend>
              <el-icon><Handbag /></el-icon>
            </template>
          </el-input>
          <span v-if="source_message" :class="source_messageType">{{ source_message }}</span>
        </div>
      </div>
      <div class="wpsr-connection-modal-input-wrapper">
        <div class="wpsr-connection-modal-input-heading-wrapper">
          <h4 class="wpsr-connection-modal-input-heading">
            {{ $t('Add Your Product Name Here') }}
          </h4>
          <p class="wpsr-connection-modal-input-description">Open your <strong>AliExpress</strong> business/product’s page in your browser. Copy the full business name and paste it into the input field to display your reviews. [e.g. Korean Food Revolution]</p>
        </div>
        <el-input
            v-model="localProductName"
            autocomplete="off"
            placeholder="Enter Your Product Name"
            class="wpsr-input-default"
            @input="$emit('productName', $event)"
        >
          <template #prepend>
            <el-icon><Handbag /></el-icon>
          </template>
        </el-input>
        <span v-if="source_message" :class="source_messageType">{{ source_message }}</span>
      </div>
      <SaveAndResetButton
          :platFormName="platFormName"
          :verifyPlatform="verifyPlatform"
          :reviewsinfo="reviewsinfo"
          :isDisable="!(localProductName && localSourceId) ? true : false"
          @save-reviews="saveReviews"
      />
    </div>

  </div>
</template>
<script>
import { CirclePlus, Remove } from '@element-plus/icons-vue'
import InputPassword from '../../../core-ui/editor/InputPassword.vue';
import SaveAndResetButton from './SaveAndResetButton.vue';

export default {
  name: 'AliExpressForm',
  components: {
    CirclePlus,
    Remove,
    InputPassword,
    SaveAndResetButton
  },
  emits: ['sourceId', 'productName', 'save-reviews'],
  props: [
    'platFormName',
    'productName',
    'apiKey',
    'apiUrl',
    'sourceId',
    'sourceUrl',
    'sourceText',
    'messageType',
    'message',
    'source_messageType',
    'source_message',
    'reviewsinfo',
    'verifyPlatform'
  ],
  data(){
    return{
      addNewBusiness: false,
      localProductName: '',
      localSourceId: '', 
    }
  },
  methods: {
    saveReviews(){
      this.$emit('save-reviews');
    },
    addBusiness(){
      this.addNewBusiness = !this.addNewBusiness;
    },
  },
  watch: {
    productName(newVal) {
      this.localProductName = newVal;
    },
    sourceId(newVal) {
      this.localSourceId = newVal;
    }
  },
  mounted() {
    this.addNewBusiness = false;

    this.localProductName = this.productName || '';
    this.localSourceId = this.sourceId || '';
  }
}
</script>