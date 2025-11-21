<template>
  <div>
    <el-button
        style="width: 100%"
        v-if="Object.keys(locationList).length"
        :class="{'display-form': reConnect}"
        class="wpsr_primary_btn_outline"
        type='default'
        @click="reConnectBusiness"
    >
      <el-icon size="18"><Link /></el-icon>
      {{ $t('Connect More Account')}}
    </el-button>

    <div v-if="reConnect || Object.keys(locationList).length === 0">
      <div class="wpsr-accounts-connect-wrapper">
        <div class="wpsr-connection-modal-input-wrapper">
          <div class="wpsr-connection-button-info-wrapper">
            <h3>Get Your Google Access Code</h3>
            <p>Simply click the button below to instantly receive your Google access code—it only takes one click.</p>
          </div>
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <el-button
                class="wpsr-mb-20"
                type='primary'
                size="default"
                @click="loginWithGoogle"
            >
              {{ $t('Sign in And Get Google Access Code')}}
            </el-button>
            <h4 class="wpsr-connection-modal-input-heading">
              {{ $t('Access Code') }}
            </h4>
          </div>
          <InputPassword
              :value="apiKeyValue"
              v-model="apiKeyValue"
              placeholder="Enter your access code"
          />
          <el-button
              size="small"
              type="success"
              class="wpsr_primary_btn wpsr-mt-20"
              :disabled="!apiKeyValue"
              @click="verifyNow"
          >
            {{ $t('Verify Code')}}
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>
<script type="text/babel">
import InputPassword from '../../../core-ui/editor/InputPassword';
export default {
  name: 'GoogleLoginForm',
  components: {InputPassword},
  props: ['value', 'locationList'],
  emits: ['input', 'verify-now', 'login-with-google', 'update:modelValue'],
  data(){
    return{
      reConnect: false,
      apiKeyValue: this.modelValue || '',
    }
  },
  watch: {
    value(newVal) {
      this.apiKeyValue = newVal;
    },
    apiKeyValue(newVal) {
      
      this.$emit('update:modelValue', newVal);
      
    }
  },
  methods:{
    loginWithGoogle(){
      this.$emit('login-with-google');
    },
    verifyNow(){
      this.$emit('verify-now');
    },
    reConnectBusiness(){
      this.reConnect = !this.reConnect;
    }
  }
}
</script>