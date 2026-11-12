<template>
    <div v-if="feed.caption && settings.post_settings.display_caption === 'true'" class="wpsr-ig-post-caption" dir="auto">
        <p class="wpsr-ig-post-caption-text" v-html="formatText($trimWords(feed.caption,settings.post_settings.trim_caption_words,true))"></p>
    </div>
</template>

<script type="text/babel">
import {igMixin} from "../../../../../mixins/igMixin";
export default {
    name: 'instagram_caption',
    props: ['feed','settings'],
    mixins: [igMixin]
}
</script>