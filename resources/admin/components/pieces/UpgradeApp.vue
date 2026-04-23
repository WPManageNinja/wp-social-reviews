<template>
    <div v-if="!this.has_pro" class="wpsr-upgrade-section">
        <span class="wpsr-upgrade-title wpsr-menu-text-medium">Upgrade Your App</span>
        <button class="wpsr-upgrade-btn" @click="upgradeNow">
            <span class="wpsr-upgrade-crown">
            <ProCrownIcon :width="20" :height="20" />
            </span>
            {{ upgradeBtnConfig.text || 'Upgrade Now' }}
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
    computed: {
        upgradeBtnConfig() {
            return {
                text: 'Upgrade Now',
                pro_purchase_url: 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade',
                ...(this.appVars?.upgrade_btn_config || {})
            };
        }
    },
    methods: {
        upgradeNow() {
            window.open(this.upgradeBtnConfig.pro_purchase_url, '_blank');
        }
    }
}
</script>
