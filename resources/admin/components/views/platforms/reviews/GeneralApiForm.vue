<template>
  <div>
    <el-button
        v-if="Object.keys(this.reviewsinfo).length"
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

    <div class="wpsr-accounts-connect-wrapper" :class="Object.keys(reviewsinfo).length ? 'wpsr-mt-20' : ''" v-if="(addNewBusiness || Object.keys(this.reviewsinfo).length === 0)">
      <div class="wpsr-connection-modal-input-wrapper">
        <div class="wpsr-connection-modal-input-heading-wrapper">
          <h4 class="wpsr-connection-modal-input-heading">
            {{ $t('Add Your API Key Here') }}
          </h4>
          <span v-if="message" :class="messageType">{{ message }}</span>
          <p class="wpsr-connection-modal-input-description" v-if="platFormName !== 'facebook'"> If you need help with getting your {{ ucFirst(platFormName) }} API Key, <a :href="apiUrl" target="_blank">Click here</a></p>
        </div>
        <InputPassword
            v-model="localApiKey"
            placeholder="Enter your api key.."
            autocomplete="off"
            :className="platFormName === 'facebook' ? 'wpsr-mb-20' : ''"
        />
      </div>
      <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
        <div class="wpsr-connection-modal-input-heading-wrapper">
          <h4 class="wpsr-connection-modal-input-heading">
            Add Your {{ sourceText }} ID Here
          </h4>
          <span v-if="source_message" :class="source_messageType">{{ source_message }}</span>
          <p class="wpsr-connection-modal-input-description" v-if="platFormName !== 'facebook'">
            <a target="_blank" :href="sourceUrl">Click here</a> to get your {{ ucFirst(platFormName) }} {{ sourceText }} ID.
          </p>
        </div>
        <el-input
            v-model="localSourceId"
            autocomplete="off"
            class="wpsr-input-default"
            :placeholder="'Enter your '+ sourceText+'  ID..'"
        >
          <template #prepend>
            <el-icon><Place /></el-icon>
          </template>
        </el-input>
      </div>
      <div class="wpsr-connection-modal-input-wrapper">
        <div class="wpsr-connection-modal-input-heading-wrapper">
          <h4 v-if="platFormName === 'zomato'" class="wpsr-connection-modal-input-heading">
            {{ $t('Number of reviews:') }}
          </h4>
          <span v-if="number_message" :class="number_messageType">{{ number_message }}</span>
        </div>
        <el-input
            v-if="platFormName === 'zomato'"
            v-model="localCount"
            autocomplete="off"
            :placeholder="'Enter number of reviews'"
        ></el-input>
      </div>
      <SaveAndResetButton
          v-if="platFormName === 'yelp'"
          :platFormName="platFormName"
          :verifyPlatform="verifyPlatform"
          :reviewsinfo="reviewsinfo"
          :isDisable="!(localApiKey && localSourceId) ? true : false"
          @save-reviews="saveReviews"
      />
    </div>
  </div>
</template>
<script type="text/babel">
import InputPassword from '../../../core-ui/editor/InputPassword';
import SaveAndResetButton from './SaveAndResetButton';
import GlobalLineIcon from "../../../pieces/icons/GlobalLineIcon.vue";
export default {
  name: 'GeneralApiForm',
  components: {
    GlobalLineIcon,
    InputPassword,
    SaveAndResetButton
  },
  props: [
    'platFormName',
    'count',
    'apiKey',
    'apiUrl',
    'sourceId',
    'sourceUrl',
    'sourceText',
    'messageType',
    'message',
    'source_messageType',
    'source_message',
    'number_messageType',
    'number_message',
    'reviewsinfo',
    'verifyPlatform'
  ],
  data(){
    return{
      addNewBusiness: false,
      localApiKey: this.apiKey || '',
      localSourceId: this.sourceId || '',
      localCount: this.count || '',
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
    localApiKey: {
      handler(newVal) {
        this.$emit('apiKey', newVal);
      },
      immediate: true
    },
    localSourceId: {
      handler(newVal) {
        this.$emit('sourceId', newVal);
      },
      immediate: true
    },
    localCount: {
      handler(newVal) {
        this.$emit('count', newVal);
      },
      immediate: true
    },
    apiKey(newVal) {
      this.localApiKey = newVal;
    },
    sourceId(newVal) {
      this.localSourceId = newVal;
    },
    count(newVal) {
      this.localCount = newVal;
    }
  },
  mounted() {
    this.addNewBusiness = false;
  }
}
</script>