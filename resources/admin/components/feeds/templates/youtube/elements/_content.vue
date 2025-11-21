<template>
    <div>
        <p class="wpsr-yt-video-description" v-if="display_description === 'true' ">
            {{ $excerpt( feed.snippet.description ) }}
        </p>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'youtube_video_content',
    props: ['feed','display_description'],
}
</script>