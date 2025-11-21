<template>
  <div>
    <div class="wpsr-review-platform" v-if="template_meta.isPlatformIcon === 'true' && platformName !== 'custom' && appVars.platforms_cards.some(card => card.platform === platformName)">
      <img v-if="platformName == appVars.tp_slug && template_meta.display_tp_brand === 'true'" class="wpsr-review-platform-icon" :src="appVars.tp_icon" :alt="platformName">
      <img v-else-if="platformName !== appVars.tp_slug" class="wpsr-review-platform-icon" :src="getPlatformIcon(platformName)" :alt="platformName">
    </div>
    <div class="wpsr-review-platform" v-if="template_meta.isPlatformIcon === 'true' && customLogo && customLogo !== ''">
      <img class="wpsr-review-platform-icon" :src="customLogo" :alt="platformName">
    </div>
  </div>

</template>

<script type="text/babel">
import { PlatformIconMixin } from '../../../../mixins/PlatformIconMixin';

export default {
    mixins: [PlatformIconMixin],
    props:['platformName', 'template_meta', 'customLogo']
}
</script>
