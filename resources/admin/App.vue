<template>
    <div class="wrap">
        <div class="wpsr_social_review_admin" :class="!has_pro ? 'wpsr-upgrade-app' : ''">
<!--            <OnboardApp v-if="showOnboarding"  :forceShowOnboarding="showOnboarding" @completed="handleOnboardingCompleted" />-->

            <!-- Show onboarding if not completed -->
            <div id="wpsr_onboarding_app" v-if="showOnboarding"></div>
            <!-- Show main app if onboarding is completed -->
            <router-view v-else />
        </div>
    </div>
</template>
<script type="text/babel">
// import OnboardApp from './Onboarding/OnboardApp.vue';

export default {
    name: 'WPSocialReviewsApp',
    components: {
       // OnboardApp
    },
    data() {
        return {
            showOnboarding: false
        }
    },
    mounted() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
        this.checkOnboardingStatus();

        // Listen for onboarding completion event
        window.addEventListener('onboarding-completed', this.handleOnboardingCompleted);

        if(this.showOnboarding){
          jQuery('.toplevel_page_wpsocialninja').addClass('wpsr-social-review-onboarding-active');
        }
    },
    beforeUnmount() {
      window.removeEventListener('onboarding-completed', this.handleOnboardingCompleted);
    },
    methods: {
        checkOnboardingStatus() {
          const shouldShow = this.appVars && this.appVars.is_onboarded;

            // Check if user needs to see onboarding
            if (shouldShow) {
                this.showOnboarding = true;
            }
        },
        handleOnboardingCompleted(event) {
            const route = event.detail;
            this.showOnboarding = false;

            // If a route object is provided, navigate to it
            if (route && typeof route === 'object') {
                this.$router.push(route);
                jQuery('.toplevel_page_wpsocialninja').removeClass('wpsr-social-review-onboarding-active');
            }
        },
    },
}
</script>
