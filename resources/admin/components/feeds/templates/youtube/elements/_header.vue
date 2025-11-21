<template>
    <div class="wpsr-yt-header" :class="layout_type !== 'carousel' ? 'wpsr-gap-'+columnGaps : ''" v-if="settings.display_header === 'true' && Object.entries(header).length">
      <div v-if="settings && settings.display_banner === 'true'" class="wpsr-yt-header-banner">
            <img v-if="settings.custom_banner" class="wpsr-yt-header-banner-desktop" :src="settings.custom_banner" />
            <img v-else class="wpsr-yt-header-banner-desktop" :src="get_header_cover(header)" />
      </div>
        <div class="wpsr-yt-header-inner">
            <div class="wpsr-yt-header-logo" v-if="settings.display_logo === 'true'">
                <a target="_blank" :href="'https://www.youtube.com/channel/'+header.items[0].id">
                  <img :src="get_header_avatar(header)" :alt="header.items[0].snippet.title">
                </a>
            </div>
            <div class="wpsr-yt-header-info">
                <div class="wpsr-yt-header-channel-name" v-if="settings.display_name === 'true'">
                    <a target="_blank" :href="'https://www.youtube.com/channel/'+header.items[0].id" :title="header.items[0].snippet.title">{{header.items[0].snippet.title}}</a>
                </div>
                <div v-if="header.items[0].statistics && has_pro" class="wpsr-yt-header-channel-statistics">
                    <div class="wpsr-yt-header-statistic-item" v-if="settings.display_subscriber_counter === 'true'">{{shortNumberFormat(header.items[0].statistics.subscriberCount)}} {{$t('Subscribers')}}</div>
                    <div class="wpsr-yt-header-statistic-item" v-if="settings.display_videos_counter === 'true'">{{shortNumberFormat(header.items[0].statistics.videoCount)}} {{$t('Videos')}}</div>
                    <div class="wpsr-yt-header-statistic-item" v-if="settings.display_views_counter === 'true'">{{shortNumberFormat(header.items[0].statistics.viewCount)}} {{$t('Views')}}</div>
                </div>
                <div v-if="header.items[0].snippet.description && settings.display_description === 'true' && has_pro" class="wpsr-yt-header-channel-description">
                    <p>{{header.items[0].snippet.description}}</p>
                </div>
            </div>
            <div class="wpsr-yt-header-subscribe-btn"  v-if="subscribe_button_settings.display_subscribe_button === 'true' && subscribe_button_settings.subscribe_button_position !== 'footer' && has_pro">
                <a :href="'https://www.youtube.com/channel/'+header.items[0].id+'?sub_confirmation=1'" target="_blank">
                    {{ subscribe_button_settings.subscribe_button_text }}
                </a>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import {ytMixin} from "../../../../../mixins/ytMixin";

    export default {
        name: 'youtube_header',
        props:['header', 'layout_type', 'settings', 'columnGaps','subscribe_button_settings'],
        mixins: [ytMixin],
        methods: {
          get_header_cover(account){
            let placeholder = this.assets_url + '/images/template/review-template/placeholder-image.png';
            if(account.covers && account.covers.local_cover.length) {
              return account.covers.local_cover;
            }

            if(account.items && account.items.length) {
              return account.items &&
                     account.items[0] &&
                     account.items[0].brandingSettings &&
                     account.items[0].brandingSettings.image &&
                     account.items[0].brandingSettings.image.bannerExternalUrl
                ? account.items[0].brandingSettings.image.bannerExternalUrl
                : '';
            }

            return placeholder;
          }
        }
    }
</script>