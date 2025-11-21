<template>
    <div :style="cssVars" class="wpsr-fm-chat-wrapper"
         :class="[
              configs.settings.chat_bubble_position ? 'wpsr-fm-bubble-position-'+configs.settings.chat_bubble_position : '',
              templateConfigs.template ? 'wpsr-fm-chat-'+templateConfigs.template : '',
              configs.layout_type === 'icons' ? 'wpsr-chat-icons-layout' : ''
          ]"
     v-if="templateConfigs && configs.channels.length">
        <div v-if="shouldShowChatBox" class="wpsr-fm-chat-box" :class="configs.settings.display_greeting === 'true' ? 'wpsr-fm-chat-box-display' : ''">
            <div @click="hideFMChatBox" class="wpsr-fm-chat-close"></div>
            <chatHeader :templateConfigs="templateConfigs" :configs="configs"/>
            <div class="wpsr-fm-chat-room">
               <span class="wpsr-fm-time" v-if="templateConfigs.platform === 'messenger'">10:00AM</span>
                <chatBody :templateConfigs="templateConfigs"></chatBody>
                <chatButton :isChannelShow="isChannelShow" :resetBubbleIcon="resetBubbleIcon" :templateConfigs="templateConfigs" :image_url="image_url" :configs="configs" :selectedChannelInfo="selectedChannel"></chatButton>                
            </div>
        </div>

        <div
            :class="configs.channels.length > 1 ? 'wpsr-channels-icons' : 'wpsr-channel-icon'"
            v-if="configs.layout_type === 'icons'"
        >
          <Channels
              :configs="configs"
              :templateSettings="templateConfigs"
              :image_url="image_url"
              @showPreFilledMessage="handleChatBox"
          />
        </div>

        <div v-if="(configs.channels.length > 1 || configs.layout_type === 'chat_box')" class="wpsr-fm-chat-bubble">
            <a @click="showFMChatBox"
               class="wpsr-fm-bubble-btn"
               :class="[
                   templateConfigs.chat_bubble.cb_button_text ? 'wpsr-fm-bubble-btn-has-text' : '',
                  configs.channels.length === 1 && configs.channels[0].name ? configs.channels[0].name : ''
               ]"
               :style="{backgroundColor: configs.styles.widget_icon_bg_color != null ? configs.styles.widget_icon_bg_color : '' }"
            >
              <img v-if="configs.layout_type !== 'icons' && configs.channels.length === 1 && configs.channels[0].name" :src="image_url" :alt="configs.channels[0].label" @error="handleImageError">
              <img v-if="configs.channels.length > 1 && ((!hideBubbleIcon && configs.layout_type === 'icons') || configs.layout_type === 'chat_box')" :src="templateConfigs.chat_bubble.cb_button_icon === '' ? assets_url+'/images/icon/chat-icon/icon1.svg' : image_url">
                <span v-if="hideBubbleIcon && configs.layout_type === 'icons'" class="wpsr-chat-icons-closee">
                    <svg viewBox="0 0 16 16" style="fill: rgb(255, 255, 255);">
                      <path d="M3.426 2.024l.094.083L8 6.586l4.48-4.479a1 1 0 011.497 1.32l-.083.095L9.414 8l4.48 4.478a1 1 0 01-1.32 1.498l-.094-.083L8 9.413l-4.48 4.48a1 1 0 01-1.497-1.32l.083-.095L6.585 8 2.106 3.522a1 1 0 011.32-1.498z"></path>
                    </svg>
                </span>
               <span>{{ templateConfigs.chat_bubble.cb_button_text }}</span>
            </a>
        </div>
    </div>
</template>

<script type="text/babel">
    import chatHeader from './elements/_header';
    import chatBody from './elements/_body';
    import Channels from './elements/_channels';
    import chatButton from './elements/_chatButton';

    export default {
        props: ['configs'],
        components:{
            chatHeader,
            chatBody,
            chatButton,
            Channels
        },
        data() {
          return {
            hideBubbleIcon: false,
            image_url: '',
            showPrefilledInputBox: false,
            showWhatsAppInputBox: false,
            resetBubbleIcon: 1,
            selectedChannel: '',
            isChannelShow: false,
          }
        },
        methods: {
            showChatBox(){
              this.isChannelShow = true
              if(this.configs.layout_type === 'icons' && this.configs.channels.length > 1){
                jQuery('.wpsr-channels-icons').css({'visibility': 'hidden'});
              }
              setTimeout(function() {
                jQuery('.wpsr-channels-icons').toggleClass('wpsr-fm-chat-box-display');
                jQuery('.wpsr-channels-icon').toggleClass('wpsr-fm-chat-box-display');
                jQuery('.wpsr-fm-chat-box').toggleClass('wpsr-fm-chat-box-display');
              }, 200);
            },
            showFMChatBox() {
                this.resetBubbleIcon = ++this.resetBubbleIcon;
                this.hideBubbleIcon = !this.hideBubbleIcon;

                if(this.configs.layout_type !== 'icons') {
                  jQuery('.wpsr-fm-chat-box').toggleClass('wpsr-fm-chat-box-display');
                } else {
                  jQuery('.wpsr-channels-icon').toggleClass('wpsr-fm-chat-box-display');
                  jQuery('.wpsr-channels-icons').toggleClass('wpsr-fm-chat-box-display');
                  jQuery('.wpsr-fm-chat-box').removeClass('wpsr-fm-chat-box-display');
                }
                jQuery('.wpsr-channels-icons').css({'visibility': 'visible'});
                this.setTimeOut();
            },

            hideFMChatBox() {
                const $chatBox = jQuery('.wpsr-fm-chat-box');
                $chatBox.addClass('closing');
                setTimeout(() => {
                    $chatBox.removeClass('closing wpsr-fm-chat-box-display');
                    if (this.configs.layout_type === 'icons') {
                        this.hideBubbleIcon = false;
                        this.showPrefilledInputBox = false;
                        jQuery('.wpsr-channels-icons').css({'visibility': 'hidden'});
                    }
                    jQuery('.wpsr-channels-icons').removeClass('wpsr-fm-chat-box-display');
                    jQuery('.wpsr-fm-chat-bubble').removeClass('active');
                }, 300);
            },

            setTimeOut(delay=2000) {
                setTimeout(function() {
                    jQuery('.wpsr-fm-greeting-msg').fadeIn(200);
                    jQuery('.wpsr-fm-chat').css({'display': 'none'});
                }, delay);
            },

            handleBubbleIcon(val) {
              const channels = this.configs.channels;

              if(!channels.length) return;

              const chatBubble = val.chat_bubble;
              const defaultIcon = this.assets_url + '/images/svg/' + channels[0].name + '.svg';

              if (chatBubble.cb_button_icon === '') {
                this.image_url = defaultIcon;
              } else if (chatBubble.cb_button_icon === 'custom' && chatBubble.cb_custom_icon !== '') {
                this.image_url = chatBubble.cb_custom_icon;
              } else if (chatBubble.cb_button_icon === 'custom' && chatBubble.cb_custom_icon === ''){
                this.image_url = defaultIcon;
              } else {
                this.image_url = this.assets_url + '/images/icon/chat-icon/' + chatBubble.cb_button_icon + '.svg';
              }
            },
            handleChatBox(channel) {
              this.selectedChannel = channel;
              this.showPrefilledInputBox = true;
              this.hideBubbleIcon = !this.hideBubbleIcon;
              this.showChatBox()
            },
            handleImageError(event) {
              event.target.src = this.assets_url + '/images/svg/' + this.configs.channels[0].name + '.svg';
            }
        },
        watch: {
          configs: {
                handler(val) {
                    let showPromt = val.settings.display_greeting;
                    if( showPromt === 'true' ) {
                        setTimeout(function() {
                            jQuery('.wpsr-fm-greeting-msg').fadeIn(200);
                            jQuery('.wpsr-fm-chat').css({'display': 'none'});
                        }, 2000);
                    }
                },
                deep: true
            },
            'configs.layout_type': {
              handler(newVal, oldVal) {
                if( newVal === 'icons' && oldVal !== 'icons')
                this.hideBubbleIcon = false;

                if(jQuery('.wpsr-fm-chat-bubble').hasClass('active')) {
                  jQuery('.wpsr-fm-chat-bubble').removeClass('active');
                }
              },
              deep: true
            },
            templateConfigs: {
              handler: function (val) {
                this.handleBubbleIcon(val);
              },
              deep: true
            }
        },
        mounted() {
          this.handleBubbleIcon(this.templateConfigs);
        },
        computed: {
            templateConfigs() {
                return this.configs[this.configs.template];
            },
            cssVars() {
                return {
                  '--scroll-position': this.configs.settings.chat_bubble_scroll_position + 'px',
                  '--sp-margin-bottom': 80 + this.configs.settings.chat_bubble_scroll_position + 'px',
                  '--wpsn-chat-close-btn-color': this.configs.styles.close_button_color || '#1d2129',
                }
            },
            shouldShowChatBox() {
              const data = this.configs.layout_type === 'chat_box' || 
                    (this.configs.layout_type === 'icons' && this.showPrefilledInputBox);
              return data;
            },
        }
    }
</script>

<style scoped>
.wpsr-fm-chat-wrapper.wpsr-chat-messenger.wpsr-fm-bubble-position-top-right,
.wpsr-fm-chat-wrapper.wpsr-chat-messenger.wpsr-fm-bubble-position-top-left{
  top: var(--scroll-position);
}
.wpsr-fm-chat-wrapper.wpsr-chat-messenger.wpsr-fm-bubble-position-top-right,
.wpsr-fm-chat-wrapper.wpsr-chat-messenger.wpsr-fm-bubble-position-top-left{
  top: var(--scroll-position);
}
.wpsr-fm-chat-wrapper.wpsr-chat-telegram.wpsr-fm-bubble-position-top-right,
.wpsr-fm-chat-wrapper.wpsr-chat-telegram.wpsr-fm-bubble-position-top-left{
  top: var(--scroll-position);
}
.wpsr-chat-messenger.wpsr-fm-bubble-position-top-left .wpsr-fm-chat-box-display,
.wpsr-chat-messenger.wpsr-fm-bubble-position-top-right .wpsr-fm-chat-box-display{
  top: var(--sp-margin-bottom);
}
.wpsr-chat-whatsapp.wpsr-fm-bubble-position-top-left .wpsr-fm-chat-box-display,
.wpsr-chat-whatsapp.wpsr-fm-bubble-position-top-right .wpsr-fm-chat-box-display
{
  top: var(--sp-margin-bottom);
}

.wpsr-chat-telegram.wpsr-fm-bubble-position-top-left .wpsr-fm-chat-box-display,
.wpsr-chat-telegram.wpsr-fm-bubble-position-top-right .wpsr-fm-chat-box-display
{
  top: var(--sp-margin-bottom);
}

.wpsr-fm-chat-wrapper.wpsr-chat-messenger.wpsr-fm-bubble-position-bottom-right,
.wpsr-fm-chat-wrapper.wpsr-chat-messenger.wpsr-fm-bubble-position-bottom-left{
  bottom: var(--scroll-position);
}
.wpsr-fm-chat-wrapper.wpsr-chat-whatsapp.wpsr-fm-bubble-position-bottom-right,
.wpsr-fm-chat-wrapper.wpsr-chat-whatsapp.wpsr-fm-bubble-position-bottom-left{
  bottom: var(--scroll-position);
}
.wpsr-fm-chat-wrapper.wpsr-chat-telegram.wpsr-fm-bubble-position-bottom-right,
.wpsr-fm-chat-wrapper.wpsr-chat-telegram.wpsr-fm-bubble-position-bottom-left{
  bottom: var(--scroll-position);
}

.wpsr-chat-messenger.wpsr-fm-bubble-position-bottom-right .wpsr-fm-chat-box-display,
.wpsr-chat-messenger.wpsr-fm-bubble-position-bottom-left .wpsr-fm-chat-box-display{
  margin-bottom: var(--sp-margin-bottom);
}
.wpsr-chat-whatsapp.wpsr-fm-bubble-position-bottom-right .wpsr-fm-chat-box-display,
.wpsr-chat-whatsapp.wpsr-fm-bubble-position-bottom-left .wpsr-fm-chat-box-display{
  margin-bottom: var(--sp-margin-bottom);
}
.wpsr-chat-telegram.wpsr-fm-bubble-position-bottom-right .wpsr-fm-chat-box-display,
.wpsr-chat-telegram.wpsr-fm-bubble-position-bottom-left .wpsr-fm-chat-box-display{
  margin-bottom: var(--sp-margin-bottom);
}
.wpsr-fm-chat-box.closing {
  opacity: 0;
  transform: translateY(30px);
  pointer-events: none;
}
</style>