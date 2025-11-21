<template>
    <div v-if="!this.has_pro" class="wpsr-upgrade-section">
        <span class="wpsr-upgrade-title wpsr-menu-text-medium">Upgrade Your App</span>
        <button class="wpsr-upgrade-btn" @click="upgradeNow">
            <span class="wpsr-upgrade-crown">
            <ProCrownIcon :width="20" :height="20" />
            </span>
            Upgrade Now
        </button>
    </div>
</template>
<script type="text/babel">
import ProCrownIcon from "../../components/pieces/icons/ProCrownIcon"
export default {
    name: "UpgradeApp",
    components: {
        ProCrownIcon
    },
    methods: {
        upgradeNow() {
            window.open('https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade', '_blank');
        }
    }
}
</script>