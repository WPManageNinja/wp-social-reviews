<template>
  <div class="wpsr-author-website-logo-wrapper" v-if="displayWebsiteLogo === 'true' && siteLogo">
    <a class="wpsr-author-website-logo-url" :href="siteUrl" target="_blank">
      <img v-if="siteLogo" class="wpsr-author-website-logo" :src="siteLogo">
    </a>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'AuthorPosition',
  props:['siteLogo', 'siteUrl', 'displayWebsiteLogo'],
}
</script>