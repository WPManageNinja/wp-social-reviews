<template>
  <div>
    <button
        v-if="!has_pro"
        class="wpsr-upgrade-btn wpsr-extend-btn"
        :class="classes"
        @click="upgradeNow"
      >
        <span class="wpsr-crown-icon"><ProCrownIcon/></span>
        {{ upgradeButtonText }}
      </button>
  </div>
</template>
<script type="text/babel">
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';
import { wpsrUpgradeUrl } from '../../../utils/upgradeUrl';

export default {
  name: 'UpgradeToProButton',
  components: {
    ProCrownIcon
  },
  props: {
    // Applied to the button itself. The component's root is a wrapper div, so a
    // plain class= from the caller lands there via attribute fallthrough and
    // never reaches the button.
    classes: String,
    btn_label: String,
    // This button is reused across many screens, so the caller names the spot it
    // sits in. Without it every click reports the same placement and we cannot
    // tell which locked feature drove the upgrade.
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
      return this.upgradeBtnConfig.text || this.btn_label || 'Upgrade to Pro';
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
