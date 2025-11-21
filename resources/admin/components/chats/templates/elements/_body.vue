<template>
    <div class="wpsr-fm-conversation">
        <div v-if="templateConfigs.chat_header.picture" class="wpsr-fm-user-picture">
            <img :src="templateConfigs.chat_header.picture" alt="">
        </div>
        <div class="wpsr-fm-chat">
            <div class="wpsr-fm-chat-bubbles">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="wpsr-fm-greeting-msg">
            <h3 v-if="templateConfigs.platform === 'whatsapp' && templateConfigs.chat_header.name">{{ templateConfigs.chat_header.name }}</h3>
            <div v-if="templateConfigs.chat_body" class="wpsr-fm-msg-text" v-html="templateConfigs.chat_body.greeting_msg"></div>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'wpsr_chat_body',
        props: ['templateConfigs'],
        mounted() {
            setTimeout(function() {
                jQuery('.wpsr-fm-greeting-msg').fadeIn(200);
                jQuery('.wpsr-fm-chat').css({'display': 'none'});
            }, 2000);
        }
    }
</script>