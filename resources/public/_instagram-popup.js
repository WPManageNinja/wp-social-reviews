import {checkBFScrollbar, formatText} from './helper';

let $ = jQuery;
let templateId = null;
let feedId = null;
let totalFeed = null;
let currentFeed = null;
let currentPopupSettings = null;
let additionalSettings = null;
let feedImages = null;
let optimizedImages = null;
let hasGdpr = null;
let generatedUrl = null;
let imageSize = null;
let userName = null;
let uploadUrl = null;


export default {
    init: function (e, $this) {
        this.checkInstagramFeedType(e, $this);

        // Checks and ensures compatibility of the Bricksforge custom scrollbar functionality
        checkBFScrollbar();
    },
    appendFeed(parent, templateId, feedId) {
        // Check if the WPSR_InstagramFrontEndJson has valid data for templateId and feedId
        if (window.WPSR_InstagramFrontEndJson && window.WPSR_InstagramFrontEndJson[templateId] && window.WPSR_InstagramFrontEndJson[templateId][feedId]) {
            document.body.classList.add('wpsr-feed-popup-active');
    
            // Get the optimized feed IDs
            let Ids = window.wpsr_optimized_feed_ids;
    
            if (Ids && typeof Ids === 'object') {
                let validateIds = Ids;
                if (validateIds[templateId]) {
                    try {
                        let feedsArr = validateIds[templateId];
                        if (feedsArr.images_data) {
                            feedImages = feedsArr.images_data;
                        }
                    } catch (error) {
                    }
                }
            }
            currentFeed = window.WPSR_InstagramFrontEndJson[templateId][feedId];
        }
    
        // Handle additional popup and settings configurations
        if (window.WPSR_InstagramPopupSettings && window.WPSR_InstagramPopupSettings[templateId]) {
            currentPopupSettings = window.WPSR_InstagramPopupSettings[templateId];
        }
    
        if (window.WPSR_InstagramAdditionalSettings && window.WPSR_InstagramAdditionalSettings[templateId]) {
            additionalSettings = window.WPSR_InstagramAdditionalSettings[templateId];
        }
    
        // If popup settings are available, render and append the HTML
        if (currentPopupSettings) {
            let data = this.renderPopupHtml(currentFeed, additionalSettings, currentPopupSettings);
            parent.append(data);
    
            // Check the comment box height and apply scrollbar if necessary
            let commentBoxHeight = parent.find('.wpsr-feed-popup-comments-wrapper-inner').innerHeight();
            if (commentBoxHeight && commentBoxHeight > 450) {
                parent.find('.wpsr-feed-popup-comments-wrapper').toggleClass('wpsr-feed-popup-scrollbar');
            }
    
            // Auto-play the video if present
            let video = jQuery("#wpsr-feed-popup-video");
            if (video.length) {
                video[0].play();
            }
        }
    },
    
    renderPagination(e, parent, templateId, feedId) {
        e.preventDefault();

        parent.find('.wpsr-feed-popup-overlay').remove();

        this.appendFeed(parent, templateId, feedId);
        
        if( currentFeed.media_name === 'CAROUSEL_ALBUM' ) {
            this.activePopupCarousel();
        }
    },
    checkInstagramFeedType(e, identifier) {
        let parent = $('body');
        let playMode = $(identifier).data('playmode');
        templateId = $(identifier).data('template-id');
        feedId = $(identifier).data('index');
        imageSize = $(identifier).data('image_size');
        userName = $(identifier).data('user_name');
        hasGdpr = $(identifier).data('has_gdpr');
        optimizedImages = $(identifier).data('optimized_images');
        uploadUrl = window.wpsr_ajax_params.upload_url;


        if( window.WPSR_InstagramFrontEndJson && window.WPSR_InstagramFrontEndJson[templateId] ) {
            totalFeed = window.WPSR_InstagramFrontEndJson[templateId].length;
        }

        if( playMode === 'popup' ) {
            this.appendFeed($('body'), templateId, feedId);
            if( feedId === 0 ){
                parent.find('.wpsr-ig-prev-btn').addClass('wpsr-link-disable');
            }
            if( feedId === ( totalFeed - 1) ) {
                parent.find('.wpsr-ig-next-btn').addClass('wpsr-link-disable');
            }
            let $that = this;
            $('body').off('click').on( 'click', '.wpsr-ig-next-btn', function(e) {
                feedId++
                $that.renderPagination(e, parent, templateId, feedId);
                if( feedId === ( totalFeed - 1) ) {
                    parent.find('.wpsr-ig-next-btn').addClass('wpsr-link-disable');
                }
            });
            $('body').on( 'click', '.wpsr-ig-prev-btn', function(e) {
                feedId--;
                $that.renderPagination(e, parent, templateId, feedId);
                if( feedId === 0 ){
                    parent.find('.wpsr-ig-prev-btn').addClass('wpsr-link-disable');
                }
            });
            this.activePopupCarousel();
        }

        if(playMode === 'inline'){
            // let inlineModeSelector = parent.find('#wpsr-video-play-' + feedId);
            // let prevVideo = $(inlineModeSelector).parent().find('video');
            //
            // if(prevVideo.length){
            //     for (let i = 0; i <= prevVideo.length; i++){
            //         if(prevVideo[i]){
            //             prevVideo[i].pause();
            //         }
            //     }
            // }
        }
    },
    activePopupCarousel() {
        if(currentFeed.children && currentFeed.children.data) {
            new Swiper('.wpsr-feed-popup-carousel-wrapper', {
                slidesPerView: 1,
                slidesPerGroup: 1,
                pagination: {
                    el: '.wpsr-swiper-pagination',
                    clickable: true
                },
                navigation: {
                    nextEl: '.wpsr-swiper-next',
                    prevEl: '.wpsr-swiper-prev',
                }
            })
        }
    },
    renderCarousel(){
        return `<div class="wpsr-feed-popup-carousel-wrapper swiper-container">
                    <div class="swiper-wrapper">
                        ${ Object.keys(currentFeed.children.data).map(function (key) {
                                let videoAttrs = '';
                                if (currentFeed.media_url && currentFeed.media_url.includes('video_dashinit')) {
                                    videoAttrs = 'playsinline="" controls autoplay=true preload="none"';
                                }
            
                                return `<div class="swiper-slide">
                                ${ currentFeed.children.data[key].media_type === 'IMAGE' ? `<img src="${currentFeed.children.data[key].media_url}" alt="">` : '' }
                                ${ currentFeed.children.data[key].media_type === 'VIDEO' ? `<video id="wpsr-feed-popup-video" ${videoAttrs} poster="${currentFeed.children.data[key].thumbnail_url}">
                                        <source src="${currentFeed.children.data[key].media_url}" type="video/mp4"></video>` : '' }
                                </div>`
                        }).join('') }
                    </div>
                    <div class="wpsr-swiper-prev-next wpsr-swiper-next icon-arrow-circle-o-right"></div>
                    <div class="wpsr-swiper-prev-next wpsr-swiper-prev icon-arrow-circle-o-left"></div>
                    <div class="wpsr-swiper-pagination swiper-pagination"></div>
                </div>`;
    },
    renderMedia() {
        let image_format = window.wpsr_ajax_params.image_settings.image_format;
        generatedUrl = optimizedImages && !currentFeed.oembed_image_failed ? `${uploadUrl}/instagram/${currentFeed.username}/${currentFeed.id}_${imageSize}.${image_format}` : currentFeed.media_url || currentFeed.default_media;
        
        if(!currentFeed.has_carousel && currentFeed.media_name === 'VIDEO' && currentFeed.media_type === 'IMAGE') {
           return  `<a href="${currentFeed.permalink}" target="_blank">
                        <img src="${generatedUrl}" alt="">
                     </a>`;
        }

        if(!currentFeed.has_carousel && (currentFeed.media_name === 'IMAGE' || currentFeed.media_name === 'CAROUSEL_ALBUM')  && currentFeed.media_type === 'IMAGE') {
            return `<img src="${generatedUrl}" alt="${currentFeed.caption ? currentFeed.caption : ''}">`
        }

        if(currentFeed.media_type === 'VIDEO' ) {
            const is_administrator = window.wpsr_ajax_params.user_role;
            if(currentFeed.media_url && currentFeed.media_url.includes('.mp4')){
                return `<video id="wpsr-feed-popup-video" playsinline="" controls autoplay=true preload="none" src="${generatedUrl}">
                        <source src="${generatedUrl}" type="video/mp4">
                 </video>`;
            } else {
                let unavailableMessage = "This video is unavailable. It may contain copyrighted content and can only be viewed on Instagram.";

                return  is_administrator == true
                ? `<div class="wpsr-ig-no-video">
                        <a href="${currentFeed.permalink}" target="_blank">
                            <img src="${currentFeed.thumbnail_url}" alt="">
                        </a>
                       <div class="wpsr-ig-notice">
                           <p><strong>This message is only visible to admins.</strong> 
                           ${unavailableMessage}</p>
                       </div>
                   </div>`
                : `<a href="${currentFeed.permalink}" target="_blank"><img src="${currentFeed.thumbnail_url}" alt=""></a>`;
            }
            return 
        }

        if(currentFeed.media_type === 'oembed' || currentFeed.media_name === 'oembed' ) {
            const is_administrator = window.wpsr_ajax_params.user_role;
            // Check if video is unavailable due to permission error
            let unavailableMessage = 'This video is unavailable. It may contain copyrighted content and can only be viewed on Instagram.';
            if (currentFeed.permissions_error) {
                unavailableMessage = "This video cannot be embedded. It may be private or embedding has been disabled by the content owner.";
            }
            const info =  is_administrator == true
                ? `<div class="wpsr-ig-no-video">
                       <div class="wpsr-ig-notice">
                           <p><strong>This message is only visible to admins.</strong> 
                           ${unavailableMessage}
                           </p>
                       </div>
                   </div>`
                : ``;

            if(currentFeed.media_url){
                return `${info}<img src="${generatedUrl}" alt="${currentFeed.caption ? currentFeed.caption : ''}">`;
            }
            return
        }
    },
    renderUserProfilePhoto() {
        let image = false;
        if( additionalSettings.header_settings.custom_profile_photo ) {
            image = additionalSettings.header_settings.custom_profile_photo;
        }else if( currentFeed.user_avatar ) {
            image = currentFeed.user_avatar;
        }

        return currentPopupSettings.display_profile_photo === 'true' ?
            `<a class="wpsr-feed-popup-user-photo" href="https://www.instagram.com/${currentFeed.username}" target="_blank" title="${currentFeed.username}">
              ${ image ? `<img src="${image}" />` : `<img src="${additionalSettings.assets_url}/images/template/review-template/placeholder-image.png" />`}
            </a>` : '';
    },
    renderUsername() {
        return ( currentFeed.username && currentPopupSettings.display_username === 'true' ) ?
            `<div class="wpsr-feed-popup-user-name">
                  <a href="https://www.instagram.com/${currentFeed.username}" target="_blank" title="${currentFeed.username}">${currentFeed.username}</a>
            </div>` : '';
    },
    renderDate() {
        return (currentPopupSettings.display_date === 'true' && currentFeed.timestamp ) ?
            `<div class="wpsr-feed-popup-statistics-date">
                ${ typeof currentFeed.like_count !== 'undefined' ? `<span>${currentFeed.like_count} ${ ' ' } ${window.wpsr_ajax_params.likes}</span>` : '' }  
                <a href="${ currentFeed.permalink }" target="_blank">
                    <time datetime="${ currentFeed.time_ago }" title="${ currentFeed.time_ago }">${ currentFeed.time_ago  }</time>
                </a>
            </div>` : '';
    },
    renderHashtags() {
        return additionalSettings.feed_type === 'hashtag_feed' ?
            `<div class="wpsr-feed-popup-user-info">
                  <p class="wpsr-feed-popup-user-name">
                    ${formatText(additionalSettings.hash_tags, 'https://www.instagram.com/explore/tags/')}
                  </p>
            </div>` : ``;
    },
    renderCaptionAndDate() {
       return typeof currentFeed.caption !== undefined ?
            `<div class="wpsr-feed-popup-comment">
               <div class="wpsr-feed-popup-comment-inner">
               ${ currentPopupSettings.display_caption === 'true' ?
                  `<div class="wpsr-feed-popup-comment-text">
                    ${ additionalSettings.feed_type !== 'hashtag_feed' && typeof currentFeed.username !== undefined ?
                        `<a href="https://www.instagram.com/${currentFeed.username}" target="_blank" title="${currentFeed.username}">${currentFeed.username}</a>` : ``
                    }
                    <p>${formatText( currentFeed.caption, 'https://www.instagram.com/explore/tags/' )}</p>
                  </div>                     
                  ${ (typeof currentFeed.time_ago !== undefined && currentPopupSettings.display_date === 'true' && currentFeed.time_ago) ?
                      `<div class="wpsr-feed-popup-comment-meta">
                       <time datetime="${currentFeed.time_ago}" title="${currentFeed.time_ago}">${currentFeed.time_ago}</time>
                      </div>` : ''
                  }
                  ` : ''
               }
              </div>
         </div>` : '';
    },
    renderComments() {
        let comments = currentFeed.comments;
         return comments ? Object.keys(comments).map(function (key) {
           return `<div class="wpsr-feed-popup-comment">
           <div class="wpsr-feed-popup-comment-inner">
                    <div class="wpsr-feed-popup-comment-text">
                        <a href="https://www.instagram.com/${comments[key].username}" target="_blank" title="${comments[key].username}">${comments[key].username}</a>
                        <p>${formatText( comments[key].text, 'https://www.instagram.com/explore/tags/' )}</p>
                    </div>
                ${ ( currentPopupSettings.display_date === 'true' && comments[key].time_ago ) ?
                    `<div class="wpsr-feed-popup-comment-meta">
                        <time datetime="${comments[key].time_ago}" title="${ comments[key].time_ago }">${ comments[key].time_ago }</time>
                    </div>` : ''
                }
            </div>
          </div>`
         }).join('') : '';
    },
    renderCTA(currentFeed) {
      return currentPopupSettings.display_cta_btn === 'true' && !(currentFeed.shoppable_options && currentFeed.shoppable_options.show_shoppable)?
         `<div class="wpsr-feed-popup-view-post-cta">
            <a href="${currentFeed.permalink}" target="_self">
                <span class="icon-instagram"></span>
                <span>${window.wpsr_ajax_params.view_on_ig}</span>
            </a>
        </div>` : ''
    },

    renderShoppableCTA(currentFeed) {
        return currentFeed.shoppable_options && currentFeed.shoppable_options.show_shoppable ?
            `<div class="wpsr-feed-popup-view-post-cta">
                <a class="wpsr-popup-shoppable-btn" href="${currentFeed.shoppable_options.url_settings.url}" 
                target="${currentFeed.shoppable_options.url_settings.open_in_new_tab ? '_blank' : ''}">
                    ${ currentFeed.shoppable_options.url_settings.text}
                </a>
            </div>` : ''
    },

    renderPopupHtml(currentFeed, additionalSettings, currentPopupSettings) {
        let className = '';
        if( currentPopupSettings.display_sidebar === 'false' ) {
            className = 'wpsr-feed-popup-box-no-sidebar';
        }
        return `<div class="wpsr-feed-popup-overlay wpsrm-overlay wpsr_content wpsr-feed-popup-open wpsr-ig-feed-popup-box-${templateId}">
                <div class="wpsr-feed-popup-box-wraper">
                <div class="wpsr-feed-popup-box-wraper-inner wpsrm_inner ${className}">
                
                ${currentPopupSettings.display_next_prev_arrows === 'true' ?
                    `<div class="wpsr-prev-btn wpsr-ig-prev-btn"><span class="icon-angle-left"></span></div>
                     <div class="wpsr-next-btn wpsr-ig-next-btn"><span class="icon-angle-right"></span></div>` : ``
                }
                  
                  <div class="wpsr-feed-popup-close-btn wpsrm_close"></div>
                    <div class="wpsr-feed-popup-media wpsr-ig-feed-popup-media">
                        ${ (currentFeed.children && currentFeed.children.data && currentFeed.has_carousel ) ? this.renderCarousel() : this.renderMedia() }
                        
                        ${!currentFeed.has_carousel && currentFeed.media_name === 'VIDEO' && currentFeed.media_type === 'IMAGE' ? `<div class="wpsr-ig-post-type-icon wpsr-ig-post-type-${currentFeed.media_name}"></div>` : ""} 
                    </div>
                    ${ currentPopupSettings.display_sidebar === 'true' ?
                        `<div class="wpsr-feed-popup-box-content">
                            ${ additionalSettings.feed_type !== 'hashtag_feed' && (currentPopupSettings.display_profile_photo === 'true' || currentPopupSettings.display_username === 'true') ?
                                `<div class="wpsr-feed-popup-user-info">
                                      ${ this.renderUserProfilePhoto() }
                                      ${ this.renderUsername() }
                                </div>` : ''
                            }
                            ${ this.renderHashtags() }
                            <div class="wpsr-feed-popup-comments-wrapper">
                               <div class="wpsr-feed-popup-comments-wrapper-inner">
                                    ${ this.renderCaptionAndDate() }
                                    ${ this.renderComments() }
                                </div>  
                            </div>
                            ${ this.renderDate() }
                            ${ this.renderCTA(currentFeed) }
                            ${ this.renderShoppableCTA(currentFeed) }
                        </div>` : ''
                    }
                </div>
            </div>
        </div>`;
    }
}