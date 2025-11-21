<template>
    <div class="wpsr-ig-footer">
        <div class="wpsr-ig-follow-btn" v-if="has_pro && settings.follow_button_settings.display_follow_button === 'true' && settings.follow_button_settings.follow_button_position !== 'header'">
            <a :href="'https://www.instagram.com/'+connected_ids[account_to_show].username" target="_blank">
                {{ settings.follow_button_settings.follow_button_text }}
            </a>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    props:['connected_ids', 'settings','account_to_show']
}
</script>