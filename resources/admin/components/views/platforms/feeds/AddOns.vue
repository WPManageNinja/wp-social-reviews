<template>
  <div class="wpsr-tiktok-feed-notice-error">
    <div class="wpsr-tiktok-feed-notice-upper-part">
      <img :src="assets_url+data.logo" alt="" class="wpsr-tiktok-feed-error-notice-image">
      <div class="wpsr-plugin-name-container">
        <p class="wpsr-plugin-name">
          {{data.name}}
        </p>
        <div class="wpsr-badge-block">
          <p class="wpsr-badge-text">Free</p>
        </div>
      </div>
      <div class="wpsr-plugin-team-container">
        <img :src="assets_url+'/images/icon/wp-social-icon.png'" alt="" class="wpsr-plugin-icon">
        <p>By <span>WP Social Ninja Team</span></p>
      </div>
    </div>
    <div class="wpsr-tiktok-feed-notice-bottom-part">
      <div>
        <svg class="wpsr-tiktok-feed-notice-bottom-icon" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="28" height="28" rx="6" fill="#E6F2FE"/>
          <g clip-path="url(#clip0_81_1751)">
            <path d="M20.6663 14.0001C20.6663 10.3182 17.6816 7.33341 13.9997 7.33341C10.3178 7.33341 7.33301 10.3182 7.33301 14.0001C7.33301 17.682 10.3178 20.6667 13.9997 20.6667C17.6816 20.6667 20.6663 17.682 20.6663 14.0001Z" stroke="#017EF3"/>
            <path d="M14.1608 17.3334V14C14.1608 13.6858 14.1608 13.5286 14.0632 13.431C13.9655 13.3334 13.8084 13.3334 13.4941 13.3334" stroke="#017EF3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.994 11.3334H14" stroke="#017EF3" stroke-linecap="round" stroke-linejoin="round"/>
          </g>
          <defs>
            <clipPath id="clip0_81_1751">
              <rect width="16" height="16" fill="white" transform="translate(6 6)"/>
            </clipPath>
          </defs>
        </svg>
      </div>
      <div class="wpsr-tiktok-feed-error-instruction">
        <p>{{data.description}}</p>
        <el-button class="wpsr_primary_btn wpsr-mt-20" :loading="loading" @click.prevent="activatePlugin">{{addonStatus}}</el-button>
      </div>
    </div>
  </div>
</template>
<script>
  export default {
  name: "AddOns",
  props: ['data', 'addonStatus', 'loading', 'getVerificationConfigs', 'checkAddonStatus'],
  data(){
    return{}
  } ,
  methods: {
    activatePlugin() {
      this.isLoading = true;
      this.$post('platforms/addons', {
        platform: this.data.platform,
      })
          .then(response => {
            if(response.data) {
              this.handleSuccess(response.data.message);
              this.$emit('isAddonActive', true);
              this.$emit('findEnabledPlatforms');
              this.getVerificationConfigs();
              this.checkAddonStatus();
              this.loading = false;
            }
          }).catch(error => {
        this.handleError(error);
      }).always(() => {

      });
    },
  },
};
</script>