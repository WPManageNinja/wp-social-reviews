<template>
    <div v-if="header_settings.show_header === 'true' && Object.entries(header).length" class="wpsr-row">
      <div class="wpsr-twitter-feed-header wpsr-col-12">
          <div class="wpsr-twitter-user-profile-banner" v-if="header_settings.show_banner_image === 'true' && header.profile_banner_url">
              <img :src="header.profile_banner_url" alt="">
          </div>
          <div class="wpsr-twitter-user-info-wrapper">
              <div class="wpsr-twitter-user-info-head">
                  <a rel="nofollow" :href="'https://twitter.com/'+header.username" target="_blank" class="wpsr-twitter-user-profile-pic" v-if="header_settings.show_avatar === 'true' && header.profile_image_url">
                      <img :src="header.profile_image_url" alt="">
                  </a>

                  <a rel="nofollow" :href="'https://twitter.com/intent/follow?screen_name='+header.username" target="_blank" wpsr-link="follow" class="wpsr-twitter-user-follow-btn" v-if="settings.display_follow_button === 'true' && settings.follow_button_position !== 'footer'">
                    <svg width="14" height="14" viewBox="0 0 14 12" xmlns="http://www.w3.org/2000/svg">
                      <path d="M10.6424 0.269775H12.5847L8.34131 5.12425L13.3333 11.7301H9.42461L6.36319 7.72369L2.86023 11.7301H0.916753L5.45545 6.53769L0.666626 0.269775H4.67454L7.44179 3.93179L10.6424 0.269775ZM9.96068 10.5664H11.0369L4.08973 1.37232H2.9348L9.96068 10.5664Z"></path>
                    </svg>
                      <span>{{ settings.follow_button_text }}</span>
                  </a>
              </div>
              <div class="wpsr-twitter-user-info">
                  <div class="wpsr-twitter-user-info-name-wrapper">
                      <a class="wpsr-twitter-user-info-name" v-if="header_settings.show_name === 'true'" rel="nofollow" :href="'https://twitter.com/'+header.username" :title="header.name" target="_blank">
                          {{ header.name }}
                      <span class="wpsr-tweeter-user-verified" title="Verified account" v-if="header.verified">
                         <svg viewBox="0 0 24 24" aria-label="Verified account">
                              <g><path d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.71-3.998-3.818-3.998-.47 0-.92.084-1.336.25C14.818 2.415 13.51 1.5 12 1.5s-2.816.917-3.437 2.25c-.415-.165-.866-.25-1.336-.25-2.11 0-3.818 1.79-3.818 4 0 .494.083.964.237 1.4-1.272.65-2.147 2.018-2.147 3.6 0 1.495.782 2.798 1.942 3.486-.02.17-.032.34-.032.514 0 2.21 1.708 4 3.818 4 .47 0 .92-.086 1.335-.25.62 1.334 1.926 2.25 3.437 2.25 1.512 0 2.818-.916 3.437-2.25.415.163.865.248 1.336.248 2.11 0 3.818-1.79 3.818-4 0-.174-.012-.344-.033-.513 1.158-.687 1.943-1.99 1.943-3.484zm-6.616-3.334l-4.334 6.5c-.145.217-.382.334-.625.334-.143 0-.288-.04-.416-.126l-.115-.094-2.415-2.415c-.293-.293-.293-.768 0-1.06s.768-.294 1.06 0l1.77 1.767 3.825-5.74c.23-.345.696-.436 1.04-.207.346.23.44.696.21 1.04z">
                              </path></g>
                          </svg>
                      </span>
                      </a>
                      <a v-if="header_settings.show_user_name === 'true'" class="wpsr-twitter-user-info-username" rel="nofollow" :href="'https://twitter.com/'+header.username" target="_blank">
                          @{{ header.username }}
                      </a>
                  </div>
                  <div class="wpsr-twitter-user-bio" v-if="header.description && header_settings.show_description === 'true'">
                      <p v-html="formatContent(header.description)">
                      </p>
                  </div>
                  <div class="wpsr-twitter-user-contact" v-if="header_settings.show_location === 'true' && header.location ">
                      <i class="el-icon-location-outline"></i> <span>{{ header.location }}</span>
                  </div>
                  <div class="wpsr-twitter-user-statistics">
                      <div class="wpsr-twitter-user-statistics-item" v-if="header_settings.show_total_tweets === 'true'">
                          <span class="wpsr-twitter-user-statistics-item-name">{{$t('Tweets')}}</span>
                          <span class="wpsr-twitter-user-statistics-item-data">{{ shortNumberFormat( header.public_metrics.tweet_count ) }}</span>
                      </div>
                      <div class="wpsr-twitter-user-statistics-item" v-if="header_settings.show_following === 'true'">
                          <span class="wpsr-twitter-user-statistics-item-name">{{$t('Following')}}</span>
                          <span class="wpsr-twitter-user-statistics-item-data">{{ shortNumberFormat( header.public_metrics.following_count ) }}</span>
                      </div>
                      <div class="wpsr-twitter-user-statistics-item" v-if="header_settings.show_followers === 'true'">
                          <span class="wpsr-twitter-user-statistics-item-name">{{$t('Followers')}}</span>
                          <span class="wpsr-twitter-user-statistics-item-data">{{ shortNumberFormat( header.public_metrics.followers_count ) }}</span>
                      </div>
                  </div>
              </div>
          </div>
      </div>
    </div>
</template>

<script type="text/babel">
import {generateUserPermalink} from "../helper";

export default {
    props:['header','header_settings','settings'],
    methods : {
      formatContent(content){
            let hashTagUrl = 'https://twitter.com/hashtag/';
            content = this.escapeHtml(content);
            content = this.generateURLsFromText(content);
            content = generateUserPermalink(content);
            content = this.generateURLsFromHashTag(content, hashTagUrl);
            return content;
        }
    }
}
</script>