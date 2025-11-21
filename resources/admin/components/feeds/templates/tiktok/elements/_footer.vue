<template>
  <div class="wpsr-tiktok-feed-footer wpsr-tiktok-feed-follow-button-group">
    <div class="wpsr-tiktok-feed-btn wpsr-ml-15"
         v-if="has_pro &&
         settings.follow_button_settings.follow_button_text &&
         settings.follow_button_settings.display_follow_button === 'true' &&
         settings.follow_button_settings.follow_button_position !== 'header'"
    >
        <a :href="account.profile_deep_link" target="_blank">
          {{ settings.follow_button_settings.follow_button_text }}
        </a>
    </div>

  </div>
</template>

<script type="text/babel">
export default {
  data(){
    return{}
  },
  props:['account', 'settings']
}
</script>