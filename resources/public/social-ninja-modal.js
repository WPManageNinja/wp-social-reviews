import TwitterPopup from './twitter-popup.js';
import YoutubePopup from './youtube-popup.js';
import InstagramPopup from './_instagram-popup';
import FacebookPopup from './_facebook-popup';
import TiktokPopup from './_tiktok-popup';
import ReviewPhotos from './_review-photos';

import Helper from './helper.js';

window.wpsrHelper = Helper;

jQuery(document).ready(function ($) {
    //render youtube popup
    $(document).on('click', '.wpsr-yt-video-playmode', function (e) {
        YoutubePopup.init(e, this);
    });

    // render twitter popup html
    $(document).on('click', '.wpsr-twitter-playmode', function (e) {
        TwitterPopup.init(e, this);
    });

    // render instagram popup html
    $(document).on('click', '.wpsr-ig-playmode', function (e) {
        InstagramPopup.init(e, this);
    });

    //render facebook popup html
    $(document).on('click', '.wpsr-fb-feed-playmode', function (e) {
        FacebookPopup.init(e, this);
    });

    $(document).on('click', '.wpsr-tiktok-feed-playmode, .wpsr-tiktok-feed-video-playmode, .wpsr-tiktok-icon-position', function (e) {
        TiktokPopup.init(e, this);
    });

    // render instagram popup html
    $(document).on('click', '.wpsr-review-photos-playmode', function (e) {
        ReviewPhotos.init(e, this);
    });

    $(document).on('click', function (e) {
        if ($(e.target).closest(".wpsrm_close").length ||  $(e.target).has(".wpsrm_inner").length) {
            let video = jQuery("#wpsr-feed-popup-video");
            if(video.length) {
                video[0].pause();
            }

            $('.wpsrm-overlay').remove();
            document.body.classList.remove('wpsr-feed-popup-active');
        }
    });

    //Read More and Read Less Click Event binding
    $(document).on("click", ".wpsr_read_more, .wpsr_read_less", function () {
        $(this).closest(".wpsr-yt-popup-video-meta-description, .wpsr-yt-popup-video-comment-text-inner").toggleClass("wpsr_show_less_content wpsr_show_more_content");
        if ($(this).closest('.wpsr-row').hasClass('wpsr-active-masonry-layout') || $(this).closest('.wpsr-review-fixed-height-wrap').hasClass('wpsr-active-masonry-layout')) {
            let column = $('.wpsr-active-masonry-layout').data("column");
            $('.wpsr-active-masonry-layout').masonry({
                itemSelector: '.wpsr-col-' + column + '',
            });
        }
    });
});