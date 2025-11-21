<template>
    <div>
        <h3 v-if="display_title === 'true' ">
            <a class="wpsr-yt-video-playmode" @click="$emit('youtube-video-playmode', $event, feed, configs.feed_settings.video_settings.play_mode, index)">
                {{ $trimWords(feed.snippet.title, configs.feed_settings.video_settings.trim_title_words, has_pro) }}
            </a>
        </h3>
        <a v-if="configs.feed_settings.video_settings.display_channel_name === 'true' && has_pro" :href="'https://www.youtube.com/channel/'+feed.snippet.channelId" target="_blank" class="wpsr-yt-channel-title">
            {{ feed.snippet.channelTitle }}
        </a>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'youtube_video_title',
    props: ['feed','display_title', 'index', 'configs','feed_info','header'],
}
</script>