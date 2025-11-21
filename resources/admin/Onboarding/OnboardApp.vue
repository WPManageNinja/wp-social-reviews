<template>
    <div class="wpsr-onboarding-wrapper">
        <!-- Show onboarding flow if not completed -->
<!--        <Onboard v-if="showOnboarding" @completed="onOnboardingCompleted" />-->

        <Onboard v-if="showOnboarding"/>

        <!-- Default to onboarding if no specific state -->
<!--        <Onboard v-else @completed="onOnboardingCompleted" />-->
    </div>
</template>

<script>
import Onboard from "./Components/Onboard.vue";

export default {
    name: 'OnboardApp',
    components: {
        Onboard
    },
    props: {
        forceShowOnboarding: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            showOnboarding: true,
            debug: true // Enable debug mode
        }
    },
    watch: {
        forceShowOnboarding(newVal) {
            if (newVal) {
                this.showOnboardingFlow();
            }
        }
    },
    mounted() {
        // Remove WordPress notices
        jQuery('.update-nag, #wpbody-content > .updated, #wpbody-content > .error').remove();
    },
    methods: {
        // onOnboardingCompleted(route) {
        //     // Handle onboarding completion
        //     this.showOnboarding = false;
        //     // Emit event for parent components
        //    this.$emit('completed', route);
        // },
        showOnboardingFlow() {
            this.showOnboarding = true;
        },
    }
}
</script>
