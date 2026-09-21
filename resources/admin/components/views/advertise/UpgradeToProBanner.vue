<template>
    <div v-if="!has_pro" class="wpsr-upgrade-pro">
        <div class="wpsr-upgrade-content-wrapper">
            <span class="wpsr-upgrade-emoji">
            <PartyIcon />
            </span>
            <span class="wpsr-upgrade-text">Unlock more features today.</span>
        </div>
        <a :href="upgradeButtonUrl" target="_blank" class="wpsr-upgrade-link">{{ upgradeButtonText }}</a>
    </div>
</template>
<script type="text/babel">
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';
import PartyIcon from '../../pieces/icons/PartyIcon';
import { wpsrUpgradeUrl } from '../../../utils/upgradeUrl';

export default {
  name: 'UpgradeToProBanner',
  components: {
    ProCrownIcon,
    PartyIcon
  },
  props: {
    // Rendered inside several different editor panels, so the caller names the
    // locked feature it is promoting.
    placement: {
      type: String,
      default: 'upgrade_page'
    }
  },
  computed: {
    upgradeBtnConfig() {
      return this.appVars?.upgrade_btn_config || {};
    },
    upgradeButtonText() {
      return this.upgradeBtnConfig.text || 'Upgrade Now';
    },
    upgradeButtonUrl() {
      return wpsrUpgradeUrl(this.placement);
    }
  },
  methods: {
    upgradeNow() {
      window.open(this.upgradeButtonUrl, '_blank');
    }
  }
}
</script>
