<template>
  <div v-if="header_settings.display_header === 'true' && Object.entries(user).length" class="wpsr-row">
    <div class="wpsr-tiktok-feed-header wpsr-col-12" :class="header_settings.display_profile_photo === 'false' ? 'wpsr-tiktok-feed-profile-pic-hide' : ''">
      <div class="wpsr-tiktok-feed-user-info-wrapper">
        <div class="wpsr-tiktok-feed-user-info-head">

          <div class="wpsr-tiktok-feed-header-info">
            <a rel="nofollow" :href="user.profile_deep_link" target="_blank" class="wpsr-tiktok-feed-user-profile-pic" v-if="account && header_settings.display_profile_photo === 'true'">
              <img :src="get_header_avatar(account)" :alt="account.name">
            </a>

            <div class="wpsr-tiktok-feed-user-info">
              <div class="wpsr-tiktok-feed-user-info-name-wrapper" v-if="user.display_name && header_settings.display_page_name === 'true'">
                <a class="wpsr-tiktok-feed-user-info-name" rel="nofollow" :href="user.profile_deep_link" :title="account.name" target="_blank">
                  {{ user.display_name }}
                </a>
              </div>
              <div class="wpsr-tiktok-feed-user-info-description" v-if="has_pro && user.bio_description && header_settings.display_description === 'true'">
                <p> {{ user.bio_description }}</p>
              </div>
              <div class="wpsr-tiktok-feed-user-statistics" v-if="has_pro">
<!--                  <span  v-if="account.video_count !== 0 && header_settings.display_posts_counter === 'true'">{{ shortNumberFormat( account.video_count ) }} Posts </span>-->
                  <span v-if="header_settings.display_likes_counter === 'true'">
                    <strong>{{ shortNumberFormat( user.likes_count ) }}</strong> {{$t('Likes')}}
                  </span>
                  <span v-if="header_settings.display_followers_counter === 'true'">
                    <strong>{{ shortNumberFormat( user.follower_count ) }}</strong> {{$t('Followers')}}
                  </span>
                  <span v-if="header_settings.display_following_counter === 'true'">
                    <strong>{{ shortNumberFormat( user.following_count ) }}</strong> {{$t('Following')}}
                  </span>
              </div>
            </div>
          </div>

          <div class="wpsr-tiktok-feed-follow-button-group" v-if="has_pro && settings.follow_button_settings.display_follow_button === 'true' && settings.follow_button_settings.follow_button_position !== 'footer'">
              <div class="wpsr-tiktok-feed-btn">
                <a :href="user.profile_deep_link" target="_blank">
                  {{ settings.follow_button_settings.follow_button_text }}
                </a>
              </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: ['header_settings', 'account', 'settings', 'translations'],
  methods: {
    get_header_avatar(account) {
      if(account.avatar && account.avatar.local_avatar.length) {
        return account.avatar.local_avatar;
      }
      if(account.data && account.data.user && account.data.user.avatar_url && account.data.user.avatar_url.length) {
        return account.data.user.avatar_url;
      }
    }
  },
  computed: {
    user() {
      return this.account && this.account.data && this.account.data.user ? this.account.data.user : {};
    }
  }
}
</script>