<template>
    <div class="wpsr-youtube-footer">
        <div class="wpsr-yt-header-subscribe-btn"   v-if="settings.display_subscribe_button === 'true' && settings.subscribe_button_position !== 'header'">
            <a :href="'https://www.youtube.com/channel/'+header.items[0].id+'?sub_confirmation=1'" target="_blank">
                {{ settings.subscribe_button_text }}
            </a>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'youtube_footer',
        props:['header', 'settings','account_to_show']
    }
</script>