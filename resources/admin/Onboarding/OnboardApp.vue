<template>
    <div class="wpsr-onboarding-wrapper">
        <Onboard v-if="showOnboarding"/>
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
            debug: true
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
        showOnboardingFlow() {
            this.showOnboarding = true;
        },
    }
}
</script>
