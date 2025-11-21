import {checkBFScrollbar, formatText} from './helper';

let $ = jQuery;
let templateId = null;
let feedId = null;
let totalFeed = null;
let currentFeed = null;
let currentPopupSettings = null;
let additionalSettings = null;
let feedType = null;
let optimizedImages = null;
let imageSize = null;
let uploadUrl = null;
let hasGdpr = null;

export default {
    init: function (e, $this){
        this.checkTiktokFeedType(e, $this);

        // Checks and ensures compatibility of the Bricksforge custom scrollbar functionality
        checkBFScrollbar();
    },

    renderPopupHtml() {
        let className = '';
        if( currentPopupSettings.display_sidebar === 'false' ) {
            className = 'wpsr-feed-popup-box-no-sidebar';
        }
        let image_format = window.wpsr_ajax_params.image_settings.image_format;
        currentFeed.media_url =  uploadUrl +'/tiktok' +'/' +  currentFeed.user.name + '/' + currentFeed.id + '_' + imageSize + '.'+ image_format;
        this.renderAvatar();

        return `<div class="wpsr-feed-popup-overlay wpsr-tiktok-feed-popup wpsrm-overlay wpsr_content wpsr-feed-popup-open">
                    <div class="wpsr-feed-popup-box-wraper">
                        <div class="wpsr-prev-btn wpsr-tiktok-feed-prev-btn"><span class="icon-angle-left"></span></div>
                        <div class="wpsr-next-btn wpsr-tiktok-feed-next-btn"><span class="icon-angle-right"></span></div>
                        
                        <div class="wpsr-feed-popup-close-btn wpsrm_close"></div>
                        
                      <div class="wpsr-feed-popup-box-wraper-inner wpsrm_inner ${className} wpsr-feed-${feedType}">
                                              
                         ${currentPopupSettings.display_video === 'true' ?
                            `<div class="wpsr-feed-popup-media">
                                <div>
                                ${optimizedImages ? 
                                `<a href="${'https://www.tiktok.com/@' + currentFeed.user.id + '/video/' + currentFeed.id}" target="_blank">
                                    <img src="${currentFeed.media_url}" alt="Video">
                                    <div class="wpsr-tiktok-feed-video-play">
                                        <div class="wpsr-tiktok-feed-video-play-icon"></div>
                                    </div>
                                 </a>` : 
                                `
                                    <iframe id="wpsr-feed-popup-video-iframe" src="https://www.tiktok.com/embed/v2/${currentFeed.id}" allowtransparency="true" allowfullscreen="true" allow="${this.getAllowValue()}" >
                                    </iframe>
                                `
                                }
                                </div>
                            </div>` : ``
                        }
                        ${currentPopupSettings.display_sidebar === 'true' ?
                            `<div class="wpsr-feed-popup-box-content">
                                ${currentFeed.user ?
                                    `<div class="wpsr-feed-popup-user-info">
                                        ${this.renderAvatar()}
                                        <div class="wpsr-feed-popup-group">
                                          ${this.renderUserName()}
                                          ${ this.renderDate() }
                                        </div>
                                    </div>` : ``
                                }
                          
                                  <div class="wpsr-feed-popup-comments-wrapper">
                                    <div class="wpsr-feed-popup-comments-wrapper-inner">
                                        ${currentPopupSettings.display_caption === 'true' ?
                                            `<div class="wpsr-feed-popup-comment">
                                                <div class="wpsr-feed-popup-comment-inner">
                                                    <div class="wpsr-feed-popup-comment-text">
                                                        ${this.renderMessage()}
                                                    </div>
                                                </div>
                                            </div>` : ``
                                        }
                                    </div>
                                  </div>
                          
                                   ${currentPopupSettings.display_cta_btn === 'true' ?
                                        `<div class="wpsr-feed-popup-view-post-cta">
                                        <a href="${'https://www.tiktok.com/@' + currentFeed.user.id + '/video/' + currentFeed.id}" target="_self">
                                          <span class="wpsr-icon-tiktok-black"></span>
                                          <span> ${window.wpsr_ajax_params.view_on_tiktok} </span>
                                        </a>
                                    </div>` : ``
                                    }
                        </div>` : ``
                        }
                        
                      </div>
                    </div>
                </div>
                `
    },
    getAllowValue() {
        const allow = ['clipboard-write', 'encrypted-media', 'picture-in-picture'];
        return allow.join('; ');
    },
    renderAvatar() {
        let picture = currentFeed.user && currentFeed.user.profile_image_url ? currentFeed.user.profile_image_url : '';
        picture = optimizedImages ? currentFeed.user_avatar : picture;
        let user_name = currentFeed.user ? currentFeed.user.name : '';

        return currentPopupSettings.display_profile_photo === 'true' ?
            `<a class="wpsr-feed-popup-user-photo" href="${picture}" target="_blank" title="${user_name}">
              <img src="${picture}" alt=${user_name} />
            </a>` : '';
    },

    renderUserName() {
        let user_name = currentFeed.user ? currentFeed.user.name : '';
        let id = currentFeed.user ? currentFeed.user.id : '';
        let user_url = currentFeed.user ? currentFeed.user.profile_url : '';

        return currentPopupSettings.display_username === 'true' ?
            `<div class="wpsr-feed-popup-user-name">
                <a href="${user_url}" target="_blank" title="${user_name}">${user_name}</a>
              </div>` : '';
    },

    renderDate(){
        return (currentPopupSettings.display_date === 'true' && currentFeed.created_at ) ?
            `<div class="wpsr-feed-popup-date">
               <time datetime="${currentFeed.time_ago}" title="${currentFeed.time_ago}">${currentFeed.time_ago}</time>
            </div>` : '';
    },

    renderMessage() {
        return currentFeed.text !== undefined ? ` <p>${formatText(currentFeed.text, 'https://www.tiktok.com/tag/')}</p>` : ``;
    },

    // renderName()
    // {
    //     return currentFeed.title !== undefined ? ` <p>${currentFeed.title}</p>` : ``;
    // },

     appendFeed(parent, templateId, feedId) {
         if( window.WPSR_TiktokFrontEndJson && window.WPSR_TiktokFrontEndJson[templateId] && window.WPSR_TiktokFrontEndJson[templateId][feedId] ) {
            currentFeed = window.WPSR_TiktokFrontEndJson[templateId][feedId];
         }

        if( window.WPSR_TiktokPopupSettings && window.WPSR_TiktokPopupSettings[templateId] ) {
            currentPopupSettings = window.WPSR_TiktokPopupSettings[templateId];
        }

        if( window.WPSR_TiktokAdditionalSettings && window.WPSR_TiktokAdditionalSettings[templateId] ) {
            additionalSettings = window.WPSR_TiktokAdditionalSettings[templateId];
        }

        if( currentPopupSettings ) {

            let data = this.renderPopupHtml(currentFeed, additionalSettings, currentPopupSettings);
            parent.append(data);
            let commentBoxHeight = parent.find('.wpsr-feed-popup-comments-wrapper-inner').innerHeight();
            if (commentBoxHeight && commentBoxHeight > 450) {
                parent.find('.wpsr-feed-popup-comments-wrapper').toggleClass('wpsr-feed-popup-scrollbar');
            }

            let video = jQuery("#wpsr-feed-popup-video");
            if(video.length) {
                video[0].play();
            }
        }
    },

    renderPagination(e, parent, templateId, feedId){
        e.preventDefault();
        parent.find('.wpsr-feed-popup-overlay').remove();

        this.appendFeed(parent, templateId, feedId);
    },

    checkTiktokFeedType(e, identifier) {
        let parent = $('body');
        let playMode = $(identifier).data('playmode');
        templateId = $(identifier).data('template-id');
        feedId = $(identifier).data('index');
        feedType = $(identifier).data('feed_type');
        hasGdpr = $(identifier).data('has_gdpr');
        optimizedImages = $(identifier).data('optimized_images');
        imageSize = $(identifier).data('image_size');
        uploadUrl = window.wpsr_ajax_params.upload_url;

        if( window.WPSR_TiktokFrontEndJson && window.WPSR_TiktokFrontEndJson[templateId] && window.WPSR_TiktokFrontEndJson[templateId][feedId] ) {
            currentFeed = window.WPSR_TiktokFrontEndJson[templateId][feedId];
        }

        if( window.WPSR_TiktokFrontEndJson && window.WPSR_TiktokFrontEndJson[templateId] ){
            totalFeed = window.WPSR_TiktokFrontEndJson[templateId].length;
        }
        if( playMode === 'popup') {
            this.appendFeed($('body'), templateId, feedId);

            if( feedId === 0 ) {
                parent.find('.wpsr-tiktok-feed-prev-btn').addClass('wpsr-link-disable');
            }

            if( feedId === ( totalFeed - 1) ) {
                parent.find('.wpsr-tiktok-feed-next-btn').addClass('wpsr-link-disable');
            }

            let $that = this;
            $('body').off('click').on( 'click', '.wpsr-tiktok-feed-next-btn', function(e){
                feedId++
                $that.renderPagination(e, parent, templateId, feedId);
                jQuery('iframe, img').on('load', function (){
                    jQuery('.wpsr-feed-popup-loader').removeClass('wpsr-popup-loading');
                });
                if( feedId === ( totalFeed - 1) ) {
                    parent.find('.wpsr-tiktok-feed-next-btn').addClass('wpsr-link-disable');
                }
            });

            $('body').on( 'click', '.wpsr-tiktok-feed-prev-btn', function(e){
                feedId--;
                $that.renderPagination(e, parent, templateId, feedId);
                jQuery('iframe, img').on('load', function (){
                    jQuery('.wpsr-feed-popup-loader').removeClass('wpsr-popup-loading');
                });
                if( feedId === 0 ){
                    parent.find('.wpsr-tiktok-feed-prev-btn').addClass('wpsr-link-disable');
                }
            });

            jQuery('iframe, img').on('load', function (){
                jQuery('.wpsr-feed-popup-loader').removeClass('wpsr-popup-loading');
            });
            // jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
            jQuery('.wpsr-feed-link').removeAttr("href");

        }
    },
}