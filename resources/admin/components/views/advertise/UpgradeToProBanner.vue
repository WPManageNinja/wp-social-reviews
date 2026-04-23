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
export default {
  name: 'UpgradeToProButton',
  components: {
    ProCrownIcon,
    PartyIcon
  },
  props: ['classes'],
  data(){
    return{

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
      return this.upgradeBtnConfig.pro_purchase_url || 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade';
    }
  },
  methods: {
    upgradeNow() {
      window.open(this.upgradeButtonUrl, '_blank');
    }
  }
}
</script>
