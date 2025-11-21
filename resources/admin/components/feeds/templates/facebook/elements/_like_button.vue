<template>
  <a :href="account.link" target="_blank" rel="nofollow">
    <span class="wpsr-fb-feed-btn-icon">
      <svg class="wpsr-svg-fb-icon" viewBox="0 0 12 12" width="12px" height="12px" style="enable-background:new 0 0 12 12;">
          <path style="fill:#4672D2;" d="M10.4,0H1.6C0.7,0,0,0.7,0,1.6v8.8C0,11.3,0.7,12,1.6,12h4.3l0-4.3H4.8c-0.1,0-0.3-0.1-0.3-0.3l0-1.4
              c0-0.1,0.1-0.3,0.3-0.3h1.1V4.5c0-1.5,0.9-2.4,2.3-2.4h1.1c0.1,0,0.3,0.1,0.3,0.3v1.2c0,0.1-0.1,0.3-0.3,0.3l-0.7,0
              C8,3.8,7.8,4.1,7.8,4.6v1.2h1.7c0.2,0,0.3,0.1,0.3,0.3L9.6,7.5c0,0.1-0.1,0.2-0.3,0.2H7.8l0,4.3h2.6c0.9,0,1.6-0.7,1.6-1.6V1.6
              C12,0.7,11.3,0,10.4,0z"></path>
      </svg>
    </span>
    <span class="wpsr-fb-feed-btn-text">{{ settings.like_button_settings.like_button_text }}</span>
  </a>
</template>

<script type="text/babel">
export default {
  data(){
    return{}
  },
  props:['account', 'settings']
}
</script>