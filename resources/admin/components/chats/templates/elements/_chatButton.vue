<template>
    <div class="wpsr-fm-chat-btn-wrapper">
        <div class="wpsr-fm-btn-icon">
            <a v-if="configs.channels.length === 1 && ((configs.channels[0].name !== 'whatsapp' && configs.channels[0].name !== 'sms') || configs.chat_button.prefilled_message === 'false')"  role="button"
            :style="{backgroundColor: configs.styles.channel_icon_bg_color != null ? configs.styles.channel_icon_bg_color : '' }"
             class="wpsr-fm-btn"
             :class="configs.channels.length === 1 && configs.channels[0].name ? configs.channels[0].name : ''"
            @click.prevent="openPopupWindow(configs)"
            >
              <span class="wpsr-chat-btn-text">{{configs.chat_button.button_text}}</span>
              <img v-if="configs.chat_button.display_icon === 'true' && configs.channels[0].name" :src="image_url" :alt="configs.channels[0].label">
            </a>
            <div v-if="configs.channels.length > 1">
              <span v-if="!showPreFilledInputBox && !isChannelShow" class="wpsr-fm-multiple-btn">{{configs.chat_button.button_text}}</span>
              <div class="wpsr-channels">
                <Channels
                    v-if="(!showPreFilledInputBox && configs.layout_type === 'chat_box') || (!isChannelShow && configs.layout_type === 'icons')"
                    :configs="configs"
                    :templateSettings="templateConfigs"
                    :image_url="image_url"
                    @showPreFilledMessage="setPrefilleChannel"
                />
              </div>
            </div>
  
            <div v-if="(isChannelShow && configs.layout_type === 'icons') || showPreFilledInputBox || (configs.channels.length === 1 && (configs.channels[0].name === 'whatsapp' || configs.channels[0].name === 'sms') && (configs.chat_button.prefilled_message === 'true'))" class="wpsr-prefilled-input-container"
                 :style="{backgroundColor: configs.styles.message_background_color != null ? configs.styles.message_background_color : '' }"
            >
              <div class="wpsr-prefilled-input-container-inner">
                <input :style="{color: configs.styles.message_text_color != null ? configs.styles.message_text_color : '' }" type="text" v-model="preFilledMessage" :placeholder="configs.chat_button.prefilled_placeholder_text" class="wpsr-prefilled-input">
              </div>
              <button :style="{backgroundColor: configs.styles.send_button_bg_color != null ? configs.styles.send_button_bg_color : '' }" @click="sendPreFilledMessage(configs.channels[0])" class="wpsr-prefilled-send-button">
                <svg :style="{fill: configs.styles.send_button_icon_color != null ? configs.styles.send_button_icon_color : '' }" viewBox="0 0 24 24" class="wpsr-prefilled-send-button-icon" x="0px" y="0px">
                  <title>send</title>
                  <path d="M1.101,21.757L23.8,12.028L1.101,2.3l0.011,7.912l13.623,1.816L1.112,13.845 L1.101,21.757z"></path></svg>
              </button>
            </div>
        </div>
<!--        <p v-if="templateConfigs.platform === 'messenger'">{{ $t('Chat with ') + templateConfigs.chat_header.name + $t(' in ' )  }} {{ templateConfigs.platform|ucFirst }}</p>-->
    </div>
</template>

<script type="text/babel">
    import Channels from './_channels';
    export default {
        name: 'wpsr_chat_button',
        props: ['templateConfigs', 'configs', 'image_url', 'resetBubbleIcon', 'isChannelShow', 'selectedChannelInfo'],
        components: {
          Channels
        },
        data() {
          return {
            preFilledMessage: '',
            selectedChannel: '',
            showPreFilledInputBox: false,
          }
        },

        watch: {
            'resetBubbleIcon': {
                handler(newVal, oldVal) {
                    if (oldVal) {
                      this.showPreFilledInputBox = false;
                    }
                },
                deep: true
            },
            'selectedChannel': {
                handler(newVal, oldVal) {
                    if (oldVal) {
                      this.preFilledMessage = '';
                    }
                },
                deep: true
            }
        },
        methods:{
          openPopupWindow(channel){
              // stop redirect for fluent forms
              if(channel.name === 'fluent_forms'){
                return;
              }
              if(channel.channels && channel.channels.length === 1){
                channel = channel.channels[0];
              }
              let configs = this.configs;
              let is_url = this.isUrl(channel.credential);
              let credential = (is_url || channel.name === 'microsoft-teams') ? channel.credential : channel.webUrl+channel.credential.replace("+", "");
              if(configs.settings.popup_target === 'true') {
                let params = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=800,height=500,left=100,top=100`;
                open(credential, '', params);
              } else {
                open(credential, '_blank');
              }
          },
          setPrefilleChannel(channel) {
            this.showPreFilledInputBox = true;
            this.selectedChannel = channel;
          },
          sendPreFilledMessage(channel) {
            if(this.preFilledMessage === '') {
              alert('Please enter a message');
              return;
            }
            this.selectedChannel = this.configs.channels.length > 1 ? (this.selectedChannelInfo ? this.selectedChannelInfo : this.selectedChannel) : channel;
            const message = encodeURIComponent(this.preFilledMessage);
            if(this.selectedChannel.name === 'whatsapp') {
              const whatsappUrl = `https://wa.me/${this.selectedChannel.credential}?text=${message}`;
              window.open(whatsappUrl, '_blank');
            } else if (this.selectedChannel.name === 'sms') {
              const smsUrl = `sms:${this.selectedChannel.credential}?body=${message}`;
              window.open(smsUrl, '_blank');
            } 


          }
        }
    }
</script>