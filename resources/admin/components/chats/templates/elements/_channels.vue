<template>
  <div>
    <div v-for="(channel, key) in configs.channels" :key="key" class="wpsr-channel-item">
      <span v-if="configs.layout_type === 'icons' && channel.label !== ''" class="wpsr-channel-name">{{channel.label}}</span>
      <a role="button"
         :style="{backgroundColor: configs.styles.channel_icon_bg_color != null ? configs.styles.channel_icon_bg_color : '' }"
         class="wpsr-channel-btn"
         :class="channel.name"
         @click.prevent="openPopupWindow(channel, configs)"
         :href="isUrl(channel.credential) ? channel.credential : channel.webUrl+channel.credential"
      >
      <img v-if="configs.chat_button.display_icon === 'true'" 
           :src="getChannelImage(channel)" 
           :alt="channel.label">
      </a>
      <p v-if="configs.layout_type !== 'icons' && configs.settings.show_label === 'true'" class="wpsr-channel-label">{{channel.label}}</p>
    </div>
  </div>
</template>

<script type="text/babel">
import icons from './_icons';
export default {
  name: 'channels',
  props: ['configs', 'templateSettings', 'image_url'],
  components:{
    icons,
  },
  data() {
    return {
      prefilledPlatform: ['whatsapp', 'sms'],
    }
  },
  methods: {
    getChannelImage(channel) {
      if (channel.name === 'fluent_forms') {
        return this.assets_url + '/images/svg/fluent_forms_official.svg';
      }
      return this.assets_url + '/images/svg/' + channel.name + '.svg';
    },
    openPopupWindow(channel, channelConfigs) {
      if(channelConfigs.chat_button.prefilled_message === 'true' && this.prefilledPlatform.includes(channel.name)) {
        if(channelConfigs.channels.length > 1) {
          this.$emit('showPreFilledMessage', channel);
        } else {
          this.$emit('showPreFilledMessage', channel);
        }
        return;
      }
      // stop redirect for fluent forms
      if(channel.name === 'fluent_forms') {
        return;
      }
      let configs = this.configs;
      let is_url = this.isUrl(channel.credential);
      let credential = is_url ? channel.credential : channel.webUrl+channel.credential.replace("+", "");

      if(configs.settings.popup_target === 'true') {
        let params = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=800,height=500,left=100,top=100`;
        open(credential, '', params)
      } else {
        let target = channel.name === 'phone' ? '_self' : '_blank';
        open(credential, target);
      }
    }
  }
}
</script>