<template>
    <div class="wpsr-yt-post"
         :data-post_id="feed.id"
        :data-user_name="feed.snippet.channelId"
        :data-image_size="configs.feed_settings.video_settings.resolution"
    >
        <span class="wpsr-yt-video-player" :id="'wpsr-video-play-'+index"></span>
        <a
            class="wpsr-yt-video-preview wpsr-yt-video-playmode wpsr-yt-post-media"
            @click="$emit('youtube-video-playmode', $event, feed, configs.feed_settings.video_settings.play_mode, index)"
            :title="feed.snippet.title"
            :class="(image_settings.optimized_images  === 'true' && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''"
        >
            <img v-if="image_settings.optimized_images !== 'true'" class="wpsr-yt-post-img" :class="get_image_class(feed.media_url, image_settings)" :src="feed.default_media" :alt="feed.snippet.title">
            <img v-if="image_settings.optimized_images === 'true'" class="wpsr-yt-post-img" :class="get_image_class(feed.media_url, image_settings)" :src="feed.media_url" :alt="feed.snippet.title">
            <svg v-if="configs.feed_settings.video_settings.display_play_icon === 'true'" class="wpsr-yt-play-icon" viewBox="0 0 68 48">
                <g fill-rule="evenodd">
                    <path class="wpsr-yt-play-icon-color-1" d="M31.386 0h5.873c2.423.06 4.849.08 7.273.153 3.306.094 6.614.219 9.914.46 1.23.092 2.46.2 3.684.35.936.121 1.875.253 2.79.491a8.56 8.56 0 0 1 4.23 2.623 8.597 8.597 0 0 1 1.9 3.66c.52 2.09.755 4.24.95 6.382v19.415c-.193 2.209-.424 4.424-.932 6.586a8.575 8.575 0 0 1-6.352 6.415c-.918.211-1.854.334-2.788.445-2.585.29-5.185.436-7.782.56a367.25 367.25 0 0 1-11.351.307c-.449.014-.9-.017-1.345.036h-4.26c-5.366-.045-10.733-.139-16.094-.417-2.57-.145-5.145-.305-7.696-.666-.912-.138-1.83-.294-2.697-.616a8.596 8.596 0 0 1-4.698-4.222c-.388-.764-.628-1.592-.802-2.428-.423-2.006-.64-4.047-.813-6.087-.242-2.984-.348-5.978-.39-8.971v-1.06c.037-2.699.129-5.397.323-8.09.17-2.245.386-4.493.825-6.704.138-.67.289-1.342.54-1.98.92-2.382 2.935-4.322 5.365-5.117.517-.172 1.052-.275 1.588-.368C9.988.93 11.348.802 12.708.684 14.985.5 17.267.382 19.55.29c2.926-.116 5.854-.187 8.782-.233C29.349.03 30.369.042 31.386 0"></path>
                    <path class="wpsr-yt-play-icon-color-2" fill="#fff" d="M27.381 13.692c5.937 3.412 11.869 6.832 17.802 10.25-5.934 3.416-11.865 6.837-17.802 10.25-.002-6.834-.002-13.667 0-20.5z">
                    </path>
                </g>
            </svg>

            <span v-if="has_pro && feed_info.feed_type === 'live_streams_feed' && configs.feed_settings.video_settings.display_duration === 'true'">
                <span v-if="feed_info.event_type === 'upcoming'" class="wpsr-yt-video-duration">{{ 'LIVE' }}</span>
                <span v-if="feed_info.event_type === 'completed' && feed.contentDetails" class="wpsr-yt-video-duration">{{ parseDuration(feed.contentDetails.duration) }}</span>
            </span>

            <span v-else-if="has_pro && configs.feed_settings.video_settings.display_duration === 'true' && feed.contentDetails" class="wpsr-yt-video-duration">
                {{ parseDuration(feed.contentDetails.duration) }}
            </span>
        </a>
    </div>
</template>

<script type="text/babel">

import {commonMixin} from "../../../../../mixins/commonMixin";
export default {
    name: 'youtube_preview_image',
    mixins: [commonMixin],
    props: ['feed', 'index', 'configs','feed_info', 'image_settings'],
}
</script>