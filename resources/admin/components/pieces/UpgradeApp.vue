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
import { wpsrUpgradeUrl } from '../../utils/upgradeUrl';

export default {
    name: "UpgradeApp",
    components: {
        ProCrownIcon
    },
    computed: {
        upgradeBtnConfig() {
            return {
                text: 'Upgrade Now',
                ...(this.appVars?.upgrade_btn_config || {}),
                // Rendered in the editor sidebar, so it reports as sidebar_cta
                // rather than sharing the generic upgrade placement.
                pro_purchase_url: wpsrUpgradeUrl('sidebar_cta')
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
