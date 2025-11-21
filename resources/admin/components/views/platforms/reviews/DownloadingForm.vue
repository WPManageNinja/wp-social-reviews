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
    <div v-if="(addNewBusiness || Object.keys(reviewsinfo).length === 0)">

      <div v-if="platFormName === 'tripadvisor'">

        <el-tabs v-model="credentialsType" class="wpsr-connections-tabs">
          <el-tab-pane name="api_key">
            <template #label>
              <span class="wpsr-connections-tabs-label">
                <el-icon size="18"><Key /></el-icon>
                <div class="wpsr-connections-tabs-label-group">
                  <span>API Key (Recommended)</span>
                  <p>Connect via Token</p>
                </div>
              </span>
            </template>
            <div class="wpsr-accounts-connect-wrapper">
              <div class="wpsr-connection-modal-input-wrapper">
                <div class="wpsr-connection-modal-input-heading-wrapper">
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Add Your API Key Here') }}
                  </h4>
                  <p class="wpsr-connection-modal-input-description"> If you need help with getting your Tripadvisor API Key, <a :href="apiUrl" target="_blank">Click here</a></p>
                </div>
                <span v-if="message" :class="messageType">{{ message }}</span>
                <InputPassword
                    v-model="localApiKey"
                    placeholder="Enter your api key.."
                    autocomplete="off"
                />
              </div>
              <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                <div class="wpsr-connection-modal-input-heading-wrapper">
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Add Your Place ID Here') }}
                  </h4>
                  <p class="wpsr-connection-modal-input-description">
                    <a target="_blank" :href="sourceUrl">Click here</a> to get your {{ ucFirst(platFormName) }} {{ sourceText }} ID.
                  </p>
                </div>
                <span v-if="source_message" :class="source_messageType">{{ source_message }}</span>
                <el-input
                    v-model="localSourceId"
                    autocomplete="off"
                    class="wpsr-input-default"
                    :placeholder="'Enter your Place ID..'"
                >
                  <template #prepend>
                    <el-icon><Place /></el-icon>
                  </template>
                </el-input>
              </div>
            </div>
          </el-tab-pane>
          <el-tab-pane name="business_url">
            <template #label>
              <span class="wpsr-connections-tabs-label">
                <i class="wpsr-icon"><GlobalLineIcon/></i>
                <div class="wpsr-connections-tabs-label-group">
                  <span>Business URL( Deprecated Soon)</span>
                  <p>Connect via URL</p>
                </div>
              </span>
            </template>
          </el-tab-pane>
        </el-tabs>
      </div>

      <div class="wpsr-accounts-connect-wrapper" :class="Object.keys(reviewsinfo).length && !credentialsType ? 'wpsr-mt-20' : ''" v-if="credentialsType === 'business_url'">
        <div class="wpsr-connection-modal-input-wrapper">
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <h4 class="wpsr-connection-modal-input-heading">
              {{ $t('Enter your business URL to get your reviews:') }}
            </h4>
            <p class="wpsr-connection-modal-input-description">Open your business/product's page on
              <strong v-if="platFormName !== 'booking.com'">{{platFormName}}.com</strong>
              <strong v-if="platFormName === 'booking.com'">{{platFormName}}</strong>
              in your browser. Copy the link and paste it into the input field to display your reviews. [e.g.:{{exampleURL}}]</p>
          </div>
          <el-input
              :type="type"
              v-model="localValue"
              @keyup.enter="saveReviews"
              class="wpsr-input-default"
              placeholder="Please enter your business url"
          >
            <template #prepend>
              <GlobalLineIcon/>
            </template>
          </el-input>
        </div>
      </div>

      <div class="wpsr-accounts-connect-wrapper wpsr-mt-20" v-if="(platFormName === 'tripadvisor' && credentialsType === 'api_key') || (platFormName === 'booking.com')">
        <div class="wpsr-connection-modal-input-wrapper">
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <h4 class="wpsr-connection-modal-input-heading">
              {{ $t('Select Language') }}
            </h4>
          </div>
          <el-select class="wpsr-select-field-primary" style="width: 100%;" v-model="language" @change="updateLanguage" filterable placeholder="Select Language" size="default">
            <el-option
                v-for="language in languages"
                :key="language.value"
                :label="language.label"
                :value="language.value"
            >
            </el-option>
          </el-select>
        </div>
      </div>

      <SaveAndResetButton
          v-if="(platFormName ==='airbnb'
          || platFormName === appVars.tp_slug
          || platFormName ==='tripadvisor'
          || platFormName === 'amazon'
          || platFormName === 'aliexpress'
          || platFormName ==='booking.com')"
          :platFormName="platFormName"
          :verifyPlatform="verifyPlatform"
          :reviewsinfo="reviewsinfo"
          :isDisable="getDisableState()"
          @save-reviews="saveReviews"
      />
    </div>

  </div>
</template>
<script type="text/babel">
import {CirclePlus, Key, Link, Remove} from '@element-plus/icons-vue';
import SaveAndResetButton from './SaveAndResetButton.vue';
import InputPassword from '../../../core-ui/editor/InputPassword.vue';
import GlobalLineIcon from '../../../pieces/icons/GlobalLineIcon.vue';
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";

export default {
  name: 'DownloadingForm',
  components: {
    Key,
    ShieldCheckLineIcon,
    Link,
    CirclePlus,
    Remove,
    SaveAndResetButton,
    InputPassword,
    GlobalLineIcon
  },
  props: ['value', 'exampleURL', 'apiUrl', 'sourceUrl', 'sourceText', 'verifyPlatform', 'platFormName', 'reviewsinfo', 'apiKey', 'sourceId', 'source_message', 'source_messageType', 'message', 'messageType', 'languages'],
  data(){
    return{
      type: 'text',
      addNewBusiness: false,
      credentialsType: this.platFormName === 'tripadvisor' ? 'api_key' : 'business_url',
      language: 'en',
      localApiKey: this.apiKey || '',
      localSourceId: this.sourceId || '',
      localValue: this.value || '',
    }
  },
  methods:{
    saveReviews(){
      this.$emit('save-reviews');
    },
    addBusiness(){
      this.addNewBusiness = !this.addNewBusiness;
      this.language = this.platFormName === 'booking.com' ? 'all' : 'en';
    },
    updateLanguage(value) {
      this.language = value;
      this.$emit('set-languages', value);
    },
    getDisableState() {
      if (this.platFormName === 'tripadvisor') {
        if (this.credentialsType === 'api_key') {
          return !this.localApiKey || !this.localSourceId;
        } else {
          return !this.localValue;
        }
      }
      return !this.localValue;
    }
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
    localValue: {
      handler(newVal) {
        this.$emit('url_value', newVal);
      },
      immediate: true
    },
    apiKey(newVal) {
      this.localApiKey = newVal;
    },
    sourceId(newVal) {
      this.localSourceId = newVal;
    },
    value(newVal) {
      this.localValue = newVal;
    },
    credentialsType: {
      handler(newVal) {
        this.$emit('credentialsType', newVal);
      },
      immediate: true
    },
    platFormName() {
      this.addNewBusiness = false;
      this.credentialsType =  this.platFormName === 'tripadvisor' ? 'api_key' : 'business_url';
    }
  },
  mounted() {
    this.addNewBusiness = false;
  }
}
</script>
