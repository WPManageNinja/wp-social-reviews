<template>
    <div class="wpsr-twitter-footer"  v-if="settings.follow_button_settings.display_follow_button === 'true' && settings.follow_button_settings.follow_button_position !== 'header'">
        <div class="wpsr-twitter-follow-btn">
            <a rel="nofollow" :href="'https://twitter.com/intent/follow?screen_name='+footer.screen_name" target="_blank" wpsr-link="follow" class="wpsr-twitter-user-follow-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18">
                    <path d="M16.07 5.388c.219 4.83-3.395 10.216-9.79 10.216A9.765 9.765 0 0 1 1 14.06a6.94 6.94 0 0 0 5.1-1.421 3.446 3.446 0 0 1-3.218-2.385c.54.103 1.07.073 1.555-.06-1.656-.33-2.8-1.82-2.763-3.41.465.258.996.412 1.56.43A3.432 3.432 0 0 1 2.17 2.628a9.788 9.788 0 0 0 7.1 3.589C8.766 4.068 10.4 2 12.624 2c.99 0 1.885.417 2.513 1.084a6.925 6.925 0 0 0 2.188-.833 3.452 3.452 0 0 1-1.515 1.9 6.9 6.9 0 0 0 1.978-.54 6.96 6.96 0 0 1-1.718 1.777z"></path>
                </svg>
                <span>{{ settings.follow_button_settings.follow_button_text }}</span>
            </a>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        props:['footer','settings']
    }
</script>