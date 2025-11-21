<template>
  <div class="wpsr-fb-feed-footer wpsr-fb-feed-follow-button-group">
    <div class="wpsr-fb-feed-btn"
         v-if="has_pro &&
         settings.like_button_settings.like_button_text &&
         settings.like_button_settings.display_like_button === 'true' &&
         settings.like_button_settings.like_button_position !== 'header'"
    >
      <LikeButton
          :settings="settings"
          :account="account"
      />
    </div>

    <div class="wpsr-fb-feed-btn wpsr-ml-15"
         v-if="has_pro &&
         settings.share_button_settings.share_button_text &&
         settings.share_button_settings.display_share_button === 'true' &&
         settings.share_button_settings.share_button_position !== 'header'"
    >
      <ShareButton
          :settings="settings"
          :account="account"
      />
    </div>

  </div>
</template>

<script type="text/babel">
import LikeButton from './_like_button';
import ShareButton from './_share_button';
export default {
  data(){
    return{}
  },
  components: {
    LikeButton,
    ShareButton
  },
  props:['account', 'settings']
}
</script>