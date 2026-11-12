<template>
  <div class="wpsr-author-website-logo-wrapper" v-if="displayWebsiteLogo === 'true' && siteLogo">
    <a class="wpsr-author-website-logo-url" :href="safeSiteUrl" target="_blank">
      <img v-if="siteLogo" class="wpsr-author-website-logo" :src="siteLogo">
    </a>
  </div>
</template>

<script type="text/babel">
import { wpsrIsHttpUrl, wpsrTrimUrl } from '../../../../utils/videoUrl';

export default {
  name: 'AuthorPosition',
  props:['siteLogo', 'siteUrl', 'displayWebsiteLogo'],
  computed: {
    // Visitor-submitted URL: only http(s) may become an href; null drops the attribute.
    safeSiteUrl() {
      return wpsrIsHttpUrl(this.siteUrl) ? wpsrTrimUrl(this.siteUrl) : null;
    }
  }
}
</script>