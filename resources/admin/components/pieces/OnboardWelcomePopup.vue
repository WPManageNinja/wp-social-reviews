<template>
  <transition name="fade">
    <div class="wpsr-onboard-popup">
      <div class="wpsr-onboard-popup-content">
        <div class="wpsr-onboard-popup-logo">
          <img :src="assetsUrl + '/images/icon/wp-social-icon.png'" alt="">
        </div>
        <h3>{{ title }}</h3>
        <p>{{ description }}</p>
      </div>
    </div>
  </transition>
</template>

<script>
import { DarkModeMixin } from '../../mixins/DarkModeMixin';

export default {
  name: 'OnboardWelcomePopup',
  mixins: [DarkModeMixin],
  computed: {
    assetsUrl() {
      return this.assets_url || this.appVars?.assets_url || window.WPSocialReviewsAdmin?.assets_url || '';
    },
    isFromOnboarding() {
      return this.$route?.query?.onboarding === 'true';
    },
    title() {
      return this.isFromOnboarding 
        ? 'Welcome to Your Template Editor'
        : '';
    },
    description() {
      return this.isFromOnboarding
        ? 'Start customizing your template to match your brand and style with ease.'
        : 'Please wait while we load your template data...';
    }
  }
}
</script>