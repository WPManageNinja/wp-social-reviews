<template>
  <el-button
      type="normal"
      size="small"
      @click.prevent="selectWallChannel"
      :class="channel.name"
  >
    <img :src="getPlatformImage(channel.name)" alt="">
    <span class="wpsr-social-channel-label">{{ channel.displayName }}</span>
  </el-button>
</template>

<script>
export default {
  name: "SocialWallChannelButton",
  props: ['channel', 'assets_url', 'selectWallChannel'],
  methods: {
    getPlatformImage(channel){
      let channelName = channel === 'facebook_feed' ? 'facebook-small' : channel;
      return this.assets_url+'/images/icon/icon-'+channelName+'.png';
    }
  }
}
</script>