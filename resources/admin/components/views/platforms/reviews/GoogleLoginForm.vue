<template>
  <div>
    <AuthRedirectOverlay
        :visible="googleAuthLoading"
        :title="`${$t('Redirecting to')} ${authServiceHost}`"
        :subtitle="$t('You will be redirected to our authentication portal. This may take up to 5 seconds.')"
    />

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
            <h3>{{ $t('Display Your Google Reviews') }}</h3>
            <p>{{ $t('Connect your Google account to show reviews from the locations you manage.') }}</p>
          </div>
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <el-button
                class="wpsr-mb-20"
                type='primary'
                size="default"
                :loading="googleAuthLoading"
                :disabled="googleAuthLoading"
                @click="loginWithGoogle"
            >
              {{ $t('Connect Google Business Profile')}}
            </el-button>
            <p class="wpsr-connect-redirect-note">{{ $t('You will be redirected to WP Social Ninja, then to Google to approve access.') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script type="text/babel">
import AuthRedirectOverlay from '../../../core-ui/AuthRedirectOverlay';
export default {
  name: 'GoogleLoginForm',
  components: {AuthRedirectOverlay},
  props: ['locationList', 'googleAuthLoading', 'authServiceUrl'],
  emits: ['login-with-google'],
  data(){
    return{
      reConnect: false,
    }
  },
  computed: {
    authServiceHost() {
      try { return new URL(this.authServiceUrl).hostname; } catch (e) { return this.authServiceUrl; }
    },
  },
  methods:{
    loginWithGoogle(){
      if (this.googleAuthLoading) {
        return;
      }

      this.$emit('login-with-google');
    },
    reConnectBusiness(){
      this.reConnect = !this.reConnect;
    }
  }
}
</script>
