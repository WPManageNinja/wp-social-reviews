<template>
    <div class="wpsr-yt-popup-overlay wpsrm-overlay">
        <div class="wpsr-yt-popup-box-wraper">
            <div class="wpsr-yt-popup-box-wraper-inner">
                <div class="wpsr-yt-popup-loader" v-if="image_settings.has_gdpr !== 'true' && image_settings.optimized_images !== 'true'">
                    <div class="wpsr-spinner-animation"></div>
                </div>
                <div class="wpsr-yt-popup-close-btn wpsrm_close"></div>
                <div class="wpsr-yt-popup-video-preview wpsr-yt-video-preview" v-if="image_settings.has_gdpr === 'true' && image_settings.optimized_images === 'true'">
                    <img id="wpsr-yt-popup-video-iframe" :src="feed.media_url ? feed.media_url : feed.default_media" alt="" @click="handleImageClick()">
                    <svg v-if="configs.feed_settings.video_settings.display_play_icon === 'true'" class="wpsr-yt-play-icon" viewBox="0 0 68 48">
                      <g fill-rule="evenodd">
                        <path class="wpsr-yt-play-icon-color-1" d="M31.386 0h5.873c2.423.06 4.849.08 7.273.153 3.306.094 6.614.219 9.914.46 1.23.092 2.46.2 3.684.35.936.121 1.875.253 2.79.491a8.56 8.56 0 0 1 4.23 2.623 8.597 8.597 0 0 1 1.9 3.66c.52 2.09.755 4.24.95 6.382v19.415c-.193 2.209-.424 4.424-.932 6.586a8.575 8.575 0 0 1-6.352 6.415c-.918.211-1.854.334-2.788.445-2.585.29-5.185.436-7.782.56a367.25 367.25 0 0 1-11.351.307c-.449.014-.9-.017-1.345.036h-4.26c-5.366-.045-10.733-.139-16.094-.417-2.57-.145-5.145-.305-7.696-.666-.912-.138-1.83-.294-2.697-.616a8.596 8.596 0 0 1-4.698-4.222c-.388-.764-.628-1.592-.802-2.428-.423-2.006-.64-4.047-.813-6.087-.242-2.984-.348-5.978-.39-8.971v-1.06c.037-2.699.129-5.397.323-8.09.17-2.245.386-4.493.825-6.704.138-.67.289-1.342.54-1.98.92-2.382 2.935-4.322 5.365-5.117.517-.172 1.052-.275 1.588-.368C9.988.93 11.348.802 12.708.684 14.985.5 17.267.382 19.55.29c2.926-.116 5.854-.187 8.782-.233C29.349.03 30.369.042 31.386 0"></path>
                        <path class="wpsr-yt-play-icon-color-2" fill="#fff" d="M27.381 13.692c5.937 3.412 11.869 6.832 17.802 10.25-5.934 3.416-11.865 6.837-17.802 10.25-.002-6.834-.002-13.667 0-20.5z">
                        </path>
                      </g>
                    </svg>
                </div>
                <div class="wpsr-yt-popup-video-player" v-else-if="feed.snippet">
                   <iframe id="wpsr-yt-popup-video-iframe" :src="'https://www.youtube.com/embed/'+[ feed.snippet.resourceId && feed.snippet.resourceId.videoId ? feed.snippet.resourceId.videoId : feed.id ]+'?loop='+[settings.video_loop && settings.video_loop === 'true' ? 1 : 0]+'&rel=0&autoplay='+[settings.autoplay && settings.autoplay === 'true' ? 1 : 0]" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
                <div class="wpsr-yt-popup-box-content" v-if="has_pro">
                    <h1 class="wpsr-yt-popup-video-title" v-if="feed.snippet && settings.display_title === 'true'">{{feed.snippet.title}}</h1>
                    <div class="wpsr-yt-popup-video-info" v-if="feed.statistics">
                        <div class="wpsr-yt-popup-video-info-left" v-if="feed && feed.statistics">
                            <span v-if="settings.display_views_counter === 'true'" class="wpsr-yt-popup-video-views">{{feed.statistics.viewCount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")}} {{ $t('Views')}}</span>
                            <span v-if="settings.display_date === 'true'" class="wpsr-yt-popup-video-date">{{ wpsrDateFormat(feed.snippet.publishedAt, 'MMM DD, YYYY') }}</span>
                        </div>
                        <div class="wpsr-yt-popup-video-info-right" v-if="feed.statistics && feed">
                        <span v-if="settings.display_likes_counter === 'true'" class="wpsr-yt-popup-video-likes">
                            <svg viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet" focusable="false" class="wpsr-yt-like-icon">
                                <g class="wpsr-yt-like-icon">
                                <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1.91l-.01-.01L23 10z" class="wpsr-yt-like-icon"></path>
                                </g>
                            </svg>
                            {{shortNumberFormat(feed.statistics.likeCount)}}
                        </span>
                        <span v-if="settings.display_dislikes_counter === 'true'" class="wpsr-yt-popup-video-dislikes">
                             <svg viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet" focusable="false" class="wpsr-yt-dislike-icon">
                                <g class="wpsr-yt-dislike-icon">
                                <path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v1.91l.01.01L1 14c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z" class="wpsr-yt-dislike-icon"></path>
                                </g>
                            </svg>
                            {{shortNumberFormat(feed.statistics.dislikeCount)}}
                        </span>
                        </div>
                    </div>

                    <div class="wpsr-yt-popup-video-meta" v-if="feed.snippet">
                        <div v-if="settings.display_channel_logo === 'true' && header" class="wpsr-yt-popup-video-meta-channel-logo">
                            <a target="_blank" :href="'https://www.youtube.com/channel/'+header.items[0].id">
                              <img :src="get_header_avatar(header)" :alt="header.items[0].snippet.title">
                            </a>
                        </div>
                        <div class="wpsr-yt-popup-video-meta-info">
                            <a v-if="settings.display_channel_name === 'true' && header" class="wpsr-yt-popup-video-meta-channel-name" target="_blank" :href="'https://www.youtube.com/channel/'+header.items[0].id" :title="header.items[0].snippet.title">{{header.items[0].snippet.title}}</a>
                            <a v-if="settings.display_channel_name === 'true' && !header" class="wpsr-yt-popup-video-meta-channel-name" target="_blank" :href="'https://www.youtube.com/channel/'+feed.snippet.channelId" :title=" feed.snippet.channelTitle">{{ feed.snippet.channelTitle }}</a>
                            <span v-if="settings.display_subscribers_counter === 'true' && header" class="wpsr-yt-popup-video-meta-channel-subscriber-count">{{shortNumberFormat(header.items[0].statistics.subscriberCount)}} Subscribers</span>
                            <p v-if="settings.display_description === 'true'" class="wpsr-yt-popup-video-meta-description wpsr_show_less_content" v-html="formatText(feed.snippet.description)"></p>
                        </div>
                        <div v-if="settings.display_subscribe_button === 'true' && header" class="wpsr-yt-popup-video-meta-btn">
                            <a :href="'https://www.youtube.com/channel/'+header.items[0].id+'?sub_confirmation=1'" target="_blank">{{ $t('Subscribe')}}</a>
                        </div>
                    </div>

                    <div class="wpsr-yt-popup-video-comments" v-if="feed.comment && feed.comment.items && settings.display_comments === 'true'">
                        <div class="wpsr-yt-popup-video-comment" v-for="(comment, index) in feed.comment.items" :key="index">
                            <div class="wpsr-yt-popup-video-comment-profile-pic">
                                <a :href="comment.snippet.topLevelComment.snippet.authorChannelUrl" target="_blank">
                                  <img :src="comment.snippet.topLevelComment.snippet.authorProfileImageUrl" :alt="comment.snippet.topLevelComment.snippet.authorDisplayName">
                                </a>
                            </div>
                            <div class="wpsr-yt-popup-video-comment-info">
                                <div class="wpsr-yt-popup-video-comment-info-header">
                                    <a :href="comment.snippet.topLevelComment.snippet.authorChannelUrl" target="_blank" class="wpsr-yt-popup-video-comment-info-header-username">{{comment.snippet.topLevelComment.snippet.authorDisplayName}}</a>
                                    <span class="wpsr-yt-popup-video-comment-info-header-time">{{wpsrDateFormat(comment.snippet.topLevelComment.snippet.publishedAt , 'MMM DD YY','time_from_now') }}</span>
                                </div>
                                <div class="wpsr-yt-popup-video-comment-text">
                                    <p class="wpsr-yt-popup-video-comment-text-inner wpsr_show_less_content" v-html="formatText( comment.snippet.topLevelComment.snippet.textOriginal )"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import {ytMixin} from "../../../../../mixins/ytMixin";

export default {
    name: 'youtube_popup_box',
    props: ['feed', 'header', 'settings', 'image_settings', 'configs'],
    mixins: [ytMixin],
    methods: {
        closePopUpBox(e) {
            let closeBtn = jQuery(e.target).closest('.wpsr-yt-popup-close-btn');
            let wrapperInner = jQuery(e.target).has('.wpsr-yt-popup-box-wraper-inner');

            if((closeBtn && closeBtn.length) || ( wrapperInner && wrapperInner.length && !this.has_pro) || (wrapperInner && wrapperInner.length && wrapperInner.find('.wpsr-yt-popup-box-content').children().length && this.has_pro)) {
                jQuery('.wpsr-yt-popup-overlay').removeClass('wpsr-yt-popup-open');
                jQuery("#wpsr-yt-popup-video-iframe").attr("src", jQuery("#wpsr-yt-popup-video-iframe").attr("src").replace("autoplay=1", "autoplay=0"));
            }
        },
        formatText(text) {
            text = this.escapeHtml(text);
            let content = this.generateURLsFromText(text);
            content     = this.htmlSubstring(content);
            return this.nl2br(content);
        },
        handleImageClick() {
          window.open('https://www.youtube.com/watch?v=' + this.feed.id, '_blank');
        }
    },
    mounted() {
        document.addEventListener('click', this.closePopUpBox);

        // Initialize Read More/Less functionality
        setTimeout(() => {
            jQuery(document).off('click', '.wpsr_read_more, .wpsr_read_less');
            jQuery(document).on('click', '.wpsr_read_more', function() {
                jQuery(this).closest('.wpsr-yt-popup-video-meta-description, .wpsr-yt-popup-video-comment-text-inner')
                    .removeClass('wpsr_show_less_content')
                    .addClass('wpsr_show_more_content');
            });

            jQuery(document).on('click', '.wpsr_read_less', function() {
                jQuery(this).closest('.wpsr-yt-popup-video-meta-description, .wpsr-yt-popup-video-comment-text-inner')
                    .removeClass('wpsr_show_more_content')
                    .addClass('wpsr_show_less_content');
            });
        }, 500);
    },
    beforeUnmount() {
        document.removeEventListener('click', this.closePopUpBox);
    }
}
</script>