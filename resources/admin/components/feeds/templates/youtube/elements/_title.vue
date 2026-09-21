<template>
    <div>
        <h3 v-if="display_title === 'true' " class="wpsr-yt-video-title">
            <a class="wpsr-yt-video-playmode" tabindex="0" role="button" @keydown="wpsrActivateOnKey" @click="$emit('youtube-video-playmode', $event, feed, configs.feed_settings.video_settings.play_mode, index)">
                {{ $trimWords(feed.snippet.title, configs.feed_settings.video_settings.trim_title_words, has_pro) }}
            </a>
        </h3>
        <a v-if="configs.feed_settings.video_settings.display_channel_name === 'true' && has_pro" :href="'https://www.youtube.com/channel/'+feed.snippet.channelId" target="_blank" class="wpsr-yt-channel-title">
            {{ feed.snippet.channelTitle }}
        </a>
    </div>
</template>

<script type="text/babel">
import {ytMixin} from "../../../../../mixins/ytMixin";
export default {
    name: 'youtube_video_title',
    mixins: [ytMixin],
    props: ['feed','display_title', 'index', 'configs','feed_info','header'],
}
</script>