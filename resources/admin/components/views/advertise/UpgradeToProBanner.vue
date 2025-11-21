<template>
    <div v-if="!has_pro" class="wpsr-upgrade-pro">
        <div class="wpsr-upgrade-content-wrapper">
            <span class="wpsr-upgrade-emoji">
            <PartyIcon />
            </span>
            <span class="wpsr-upgrade-text">Unlock more features today.</span>
        </div>
        <a href="https://wpsocialninja.com/?utm_source=wp_site&amp;utm_medium=plugin&amp;utm_campaign=upgrade" target="_blank" class="wpsr-upgrade-link">Upgrade Now</a>
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
  methods: {
    upgradeNow() {
      window.open('https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade', '_blank');
    }
  }
}
</script>