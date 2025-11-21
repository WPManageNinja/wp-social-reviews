<template>
  <div>
    <button
        v-if="!has_pro"
        class="wpsr-upgrade-btn wpsr-extend-btn"
        @click="upgradeNow"
      >
        <span class="wpsr-crown-icon"><ProCrownIcon/></span>
        {{ btn_label || 'Upgrade to Pro' }}
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
  methods: {
    upgradeNow() {
      window.open('https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade', '_blank');
    }
  }
}
</script>