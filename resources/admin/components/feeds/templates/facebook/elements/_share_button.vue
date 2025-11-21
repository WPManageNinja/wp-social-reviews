<template>
  <a class="wpsr-fb-feed-btn-share" @click.prevent="openPopupWindow(account.link)" :href="account.link" target="_blank" rel="nofollow">
    <span class="wpsr-fb-feed-btn-icon">
      <svg class="wpsr-svg-share-icon" width="12px" height="12px" viewBox="0 0 24 24">
        <path id="XMLID_31_" d="M12.7,15.3c-4.5,0.1-8.4,2.5-10.7,6c-0.2,0.3-0.6,0.5-0.9,0.5c-0.1,0-0.2,0-0.3,0c-0.5-0.1-0.8-0.6-0.8-1c0,0,0-0.1,0-0.1c0-7.1,5.7-12.9,12.7-13V5.5c0-0.5,0.3-0.9,0.7-1.1c0.2-0.1,0.4-0.1,0.6-0.1c0.3,0,0.5,0.1,0.7,0.2l8.8,6c0.3,0.2,0.5,0.6,0.5,0.9c0,0.4-0.2,0.7-0.5,1l-8.8,6.1c-0.2,0.2-0.5,0.2-0.7,0.2c-0.2,0-0.4,0-0.6-0.1c-0.4-0.2-0.7-0.7-0.7-1.1V15.3z"></path>
      </svg>
    </span>
    <span class="wpsr-fb-feed-btn-text">{{ settings.share_button_settings.share_button_text }}</span>
  </a>
</template>

<script type="text/babel">
export default {
  data(){
    return{}
  },
  props:['account', 'settings'],
  methods: {
    openPopupWindow(url) {
      let sharer_url = 'https://facebook.com/sharer/sharer.php?u='+url;
      let params = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=800,height=500,left=100,top=100`;
      open(sharer_url, '', params);
    }
  }
}
</script>