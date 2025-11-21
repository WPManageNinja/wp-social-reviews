<template>
    <div :class="settings.layout_type !== 'carousel' ? 'wpsr-gap-'+settings.column_gaps : ''" class="wpsr-ig-header" v-if="settings.header_settings.display_header==='true' && header.username">
      <div class="wpsr-ig-header-inner" v-if="header">
            <div class="wpsr-ig-header-logo" v-if="settings.header_settings.display_avatar === 'true'" :data-account_id="header.account_id">
                <a target="_blank" :href="'https://www.instagram.com/'+header.username">
                  <img :src="get_header_avatar(settings, header)" />
                </a>
            </div>
            <div class="wpsr-ig-header-info">
                <div class="wpsr-ig-header-name" v-if="settings.header_settings.display_username === 'true'">
                    <a target="_blank" :href="'https://www.instagram.com/'+header.username" :title="header.username">
                        @{{  header.username   }}
                    </a>
                </div>
                <div class="wpsr-ig-header-statistics" v-if="has_pro && ( (typeof header.media_count !== 'undefined' ) || ( typeof header.followers_count !== 'undefined' ))">
                    <div class="wpsr-ig-header-statistic-item" v-if="header.media_count && settings.header_settings.display_posts_counter === 'true'"><strong>{{shortNumberFormat(header.media_count)}}</strong> {{ $t('Posts') }}</div>
                    <div class="wpsr-ig-header-statistic-item" v-if="header.followers_count && settings.header_settings.display_followers_counter === 'true'"><strong>{{shortNumberFormat(header.followers_count)}}</strong> {{ $t('Followers') }}</div>
                </div>
                <h3 class="wpsr-ig-header-fullname" v-if="header.name && settings.header_settings.display_name === 'true'">{{ header.name }}</h3>
                <div class="wpsr-ig-header-description" v-if="settings.header_settings.display_description === 'true'">
                    <p v-if="has_pro && settings.header_settings.custom_profile_bio_text" v-html="nl2br(settings.header_settings.custom_profile_bio_text)"></p>
                    <p v-if="!settings.header_settings.custom_profile_bio_text && header.biography" v-html="nl2br(header.biography)"></p>
                </div>
            </div>
            <div class="wpsr-ig-follow-btn" v-if="has_pro && settings.follow_button_settings.display_follow_button === 'true' && settings.follow_button_settings.follow_button_position !== 'footer'">
                <a :href="'https://www.instagram.com/'+header.username" target="_blank">
                    {{ settings.follow_button_settings.follow_button_text }}
                </a>
            </div>
        </div> 
    </div>
</template>

<script type="text/babel">
export default {
    props:['connected_ids','header','settings','account_to_show','user_avatar'],
    methods: {
      get_header_avatar(settings, header) {
        let placeholder = this.assets_url + '/images/template/review-template/placeholder-image.png';

        if(this.has_pro && settings.header_settings && settings.header_settings.custom_profile_photo.length) {
          return settings.header_settings.custom_profile_photo;
        }

        if(this.user_avatar) {
          return this.user_avatar;
        }

        if(header.user_avatar && header.user_avatar.length) {
          return header.user_avatar;
        }

        return placeholder;
      }
    }
}
</script>