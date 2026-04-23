<template>
  <div>
    <button
        v-if="!has_pro"
        class="wpsr-upgrade-btn wpsr-extend-btn"
        @click="upgradeNow"
      >
        <span class="wpsr-crown-icon"><ProCrownIcon/></span>
        {{ upgradeButtonText }}
      </button>
  </div>
</template>
<script type="text/babel">
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

export default {
  name: 'UpgradeToProButton',
  components: {
    ProCrownIcon
  },
  props: ['classes', 'btn_label'],
  data(){
    return{

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
