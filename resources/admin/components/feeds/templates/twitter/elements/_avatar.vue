<template>
    <div class="wpsr-tweet-author-avatar" :class="feed.type === 'retweeted' ? 'wpsr-has-retweet' : '' " v-if="allFeedConfigs.feed_settings.advance_settings.avatar_image === 'true'">
        <a v-if="feed.user" target="_blank" :href="'https://twitter.com/' + feed.user.username">
          <img :src="feed.user.profile_image_url" :alt="feed.user.username">
        </a>
    </div>
</template>

<script type="text/babel">
export default {
    props : ['feed','allFeedConfigs']
}
</script>