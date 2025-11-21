<template>
    <div v-if="feed.caption && settings.post_settings.display_caption === 'true'" class="wpsr-ig-post-caption">
        <p class="wpsr-ig-post-caption-text" v-if="settings.template==='template2'" v-html="formatText($trimWords(feed.caption,settings.post_settings.trim_caption_words,has_pro))"></p>
        <p class="wpsr-ig-post-caption-text" v-else>{{ $trimWords(feed.caption,settings.post_settings.trim_caption_words,has_pro) }}</p>
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