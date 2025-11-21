<template>
  <div v-if="header_settings.display_header === 'true' && Object.entries(account).length" class="wpsr-row">
    <div class="wpsr-fb-feed-header wpsr-col-12" :class="header_settings.display_profile_photo === 'false' ? 'wpsr-fb-feed-profile-pic-hide' : ''">
      <div class="wpsr-fb-feed-user-profile-banner" v-if="account.cover && header_settings.display_cover_photo === 'true'">
        <img :src="get_header_cover(account)" :alt="account.name">
      </div>
      <div class="wpsr-fb-feed-user-info-wrapper">
        <div class="wpsr-fb-feed-user-info-head">

          <div class="wpsr-fb-feed-header-info">
            <a rel="nofollow" :href="account.link" target="_blank" class="wpsr-fb-feed-user-profile-pic" v-if="account.picture && header_settings.display_profile_photo === 'true'">
              <img :src="get_header_avatar(account)" :alt="account.name">
            </a>

            <div class="wpsr-fb-feed-user-info">
              <div class="wpsr-fb-feed-user-info-name-wrapper" v-if="account.name && header_settings.display_page_name === 'true'">
                <a class="wpsr-fb-feed-user-info-name" rel="nofollow" :href="account.link" :title="account.name" target="_blank">
                  {{ account.name }}
                </a>
              </div>
              <div class="wpsr-fb-feed-user-info-description" v-if="account.about && header_settings.display_description === 'true'">
                <p> {{ account.about }}</p>
              </div>
              <div class="wpsr-fb-feed-user-statistics">
                <span v-if="account.fan_count !== 0 && header_settings.display_likes_counter === 'true'">{{ shortNumberFormat( account.fan_count ) }} {{$t('likes')}}</span>
                <span v-if="account.followers_count !== 0 && header_settings.display_followers_count === 'true'">{{ shortNumberFormat( account.followers_count ) }} {{$t('followers')}}</span>
              </div>
            </div>
          </div>

          <div class="wpsr-fb-feed-follow-button-group" v-if="has_pro">
            <div class="wpsr-fb-feed-btn" v-if="settings.like_button_settings.like_button_text && settings.like_button_settings.display_like_button === 'true' && settings.like_button_settings.like_button_position !== 'footer'">
              <LikeButton
                  :settings="settings"
                  :account="account"
              />
            </div>
            <div class="wpsr-fb-feed-btn wpsr-ml-15" v-if="settings.share_button_settings.share_button_text && settings.share_button_settings.display_share_button === 'true' && settings.share_button_settings.share_button_position !== 'footer'">
              <ShareButton
                  :settings="settings"
                  :account="account"
              />
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>
</template>

<script>
import LikeButton from './_like_button';
import ShareButton from './_share_button';
export default {
  components: {
    LikeButton,
    ShareButton
  },
  props: ['header_settings', 'account', 'settings'],
  methods: {
      get_header_avatar(account) {
        let placeholder = this.assets_url + '/images/template/review-template/placeholder-image.png';
        if(account.avatar && account.avatar.local_avatar.length) {
          return account.avatar.local_avatar;
        }

        if(account.picture && account.picture.data && account.picture.data.url.length) {
          return account.picture.data.url;
        }

        return placeholder;
      },
      get_header_cover(account){
        let placeholder = this.assets_url + '/images/template/review-template/placeholder-image.png';
        if(account.covers && account.covers.local_cover.length) {
          return account.covers.local_cover;
        }

        if(account.cover && account.cover.source.length) {
          return account.cover.source;
        }

        return placeholder;
    }
    },
}
</script>