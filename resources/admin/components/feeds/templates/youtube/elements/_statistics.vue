<template>
    <div class="wpsr-yt-video-statistics" v-if="feed.statistics">
        <div class="wpsr-yt-video-statistic-item" v-if="settings.display_views_counter === 'true'">{{shortNumberFormat(feed.statistics.viewCount)}} {{ $t('Views')}} </div>
        <div class="wpsr-yt-video-statistic-item"  v-if="feed.snippet.publishedAt && settings.display_date === 'true' && feed_info.feed_type !== 'live_streams_feed'">
            <span>{{ wpsrDateFormat(feed.snippet.publishedAt, 'MMM DD YY','time_from_now') }}</span>
        </div>

        <div class="wpsr-yt-video-statistic-item"  v-if="feed.snippet.publishedAt && settings.display_date === 'true' && feed_info.feed_type === 'live_streams_feed' && feed_info.event_type !== 'live'">
            <span v-if="feed_info.event_type === 'upcoming' && feed.liveStreamingDetails && feed.liveStreamingDetails.scheduledStartTime">
                {{ 'Scheduled for ' }}
                {{ wpsrDateFormat(feed.liveStreamingDetails.scheduledStartTime, 'MM/DD/YYYY hh:mm A','human_readable') }}
            </span>
            <span v-if="feed_info.event_type === 'completed'">
                    {{ 'Streamed ' }}{{ wpsrDateFormat(feed.snippet.publishedAt, 'MMM DD YY','time_from_now') }}
            </span>
        </div>
        <div class="wpsr-yt-video-statistic-item" v-if="settings.display_likes_counter === 'true'">{{shortNumberFormat(feed.statistics.likeCount)}} {{ $t('Likes')}}</div>
        <div class="wpsr-yt-video-statistic-item" v-if="settings.display_comments_counter === 'true'">{{shortNumberFormat(feed.statistics.commentCount)}} {{ $t('Comments')}}</div>

        <div class="wpsr-yt-video-statistic-item"  v-if="feed_info.feed_type === 'live_streams_feed' && feed_info.event_type === 'live' ">
            <a v-if="feed_info.event_type === 'live'" class="wpsr-yt-video-playmode wpsr-yt-live-now-btn" @click="$emit('youtube-video-playmode', $event, feed, configs.feed_settings.video_settings.play_mode, index)">{{ 'LIVE NOW' }}</a>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'youtube_video_statistics',
    props: ['configs','feed','settings','source_settings','index','feed_info'],
}
</script>