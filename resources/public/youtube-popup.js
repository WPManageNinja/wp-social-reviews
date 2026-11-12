import { wpsrInitInjectedContent } from './helper.js';

let $ = jQuery;

let videoId = null;

function wpsrYtIframe(ytVideoId, iframeClass) {
    return '<iframe class="' + iframeClass + '" src="https://www.youtube.com/embed/' + ytVideoId + '?autoplay=1&rel=0" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>';
}

// Latest request token and in-flight xhr per feed. One video plays per feed, so a newer
// selection supersedes any pending request, or its late callback would refill a cleared
// slot and start a hidden autoplaying player.
const wpsrPlayerRequests = {};

/**
 * Fill a player slot with the clicked video. Fluent Player markup comes from the
 * server, since only PHP can run the shortcode.
 *
 * @param {jQuery} slot
 * @param {number|string} templateId
 * @param {string} ytVideoId
 * @param {string} iframeClass
 * @param {boolean} useFluentPlayer
 * @param {number|string} duration video length in seconds, for "layers at end"
 */
function wpsrPlayVideo(slot, templateId, ytVideoId, iframeClass, useFluentPlayer, duration) {
    let feed = wpsrPlayerRequests[templateId] || (wpsrPlayerRequests[templateId] = {token: 0, xhr: null});

    // Bump the token so older callbacks bail, and abort their request if still pending.
    let requestToken = ++feed.token;
    if (feed.xhr) {
        feed.xhr.abort();
        feed.xhr = null;
    }

    if (!useFluentPlayer) {
        slot.html(wpsrYtIframe(ytVideoId, iframeClass));
        return;
    }

    feed.xhr = $.post(wpsr_popup_params.ajax_url, {
        action: 'wpsr_youtube_fluent_player',
        videoId: ytVideoId,
        templateId: templateId,
        // Read off the clicked thumbnail so the server skips a per-click feed lookup.
        duration: duration || 0,
        security: wpsr_popup_params.wpsr_nonce
    }).done(function (response) {
        // A newer selection already replaced this one; its slot may be cleared.
        if (requestToken !== feed.token) {
            return;
        }
        feed.xhr = null;
        slot.addClass('wpsr-fluent-player-slot').html(response.data.html);
        wpsrInitInjectedContent(slot, templateId, 'yt');
    }).fail(function (jqXHR, textStatus) {
        // Ignore our own abort and any request a newer selection superseded.
        if (textStatus === 'abort' || requestToken !== feed.token) {
            return;
        }
        feed.xhr = null;
        // Fluent Player became unavailable after this page was rendered.
        slot.removeClass('wpsr-fluent-player-slot').html(wpsrYtIframe(ytVideoId, iframeClass));
    });
}

export default {
    init: function (e, $this){
        this.checkFeedType(e, $this);
    },
    checkFeedType(e, identifier) {
        let playMode = $(identifier).data('playmode');
        let templateId = $(identifier).data('template_id');
        let feedId = $(identifier).data('index');
        let ytVideoId = $(identifier).data('videoid');
        let duration = $(identifier).data('duration');
        videoId = ytVideoId;
        let parent = $('#wpsr-yt-feed-'+templateId);
        let useFluentPlayer = parent.data('fluent_player') === true;

        if (playMode === 'popup') {
            this.openPopUpBox(e,identifier);
        } else if( playMode === 'gallery' ){
            e.preventDefault();
            let galleryModeSelector = parent.find('.wpsr-yt-video-player-gallery');
            wpsrPlayVideo(galleryModeSelector, templateId, ytVideoId, '', useFluentPlayer, duration);
            // Templates 4 & 5 highlight the thumbnail whose video is in the gallery slot.
            parent.find('.wpsr-yt-video').removeClass('wpsr-yt-gallery-active');
            $(identifier).closest('.wpsr-yt-video').addClass('wpsr-yt-gallery-active');
            $('html, body').animate({
                scrollTop: galleryModeSelector.offset().top
            }, 500);
        } else if (playMode === 'youtube') {
            let watchUrl = 'https://www.youtube.com/watch?v=' + ytVideoId + '';
            $(identifier).attr("target", "_blank");
            $(identifier).attr("href", watchUrl);
        } else {
            e.preventDefault();
            let inlineModeSelector = parent.find('#wpsr-video-play-' + feedId);
            // Only one inline video plays at a time.
            parent.find('.wpsr-yt-video-player').not(inlineModeSelector).empty().removeClass('wpsr-fluent-player-slot');
            parent.find('.wpsr-yt-video').removeClass('active');
            inlineModeSelector.parents('.wpsr-yt-video').addClass('active');
            wpsrPlayVideo(inlineModeSelector, templateId, ytVideoId, 'wpsr-yt-inline-video-iframe', useFluentPlayer, duration);
        }
    },
    /**
     * Open the popup for one video. The overlay is rebuilt on each open, so a stable
     * slot key tears the last one down.
     *
     * @param {Event} e
     * @param {Element} identifier clicked thumbnail
     */
    openPopUpBox(e, identifier) {
        let templateId = $(identifier).data('template_id');
        let feedId     = $(identifier).data('videoid');
        let data = {
            'action': 'wpsr_youtube_popup_feed',
            'feedId': feedId,
            'templateId': templateId,
            'security': wpsr_popup_params.wpsr_nonce
        };
        $.ajax({
            url: wpsr_popup_params.ajax_url,
            type: 'POST',
            data: data,
            beforeSend: function () {
                $('#wpsr-yt-feed-'+templateId).find('.wpsr-yt-popup-overlay').remove();
            },
            success: function (data) {

                $('body').append(data);

                wpsrInitInjectedContent($("body > .wpsr-yt-popup-overlay").last(), templateId, 'popup');

                if( $(".wpsr-yt-popup-video-meta-description").length !== 0 ) {
                    let ytContent = $(".wpsr-yt-popup-video-meta-description").html();
                    let ytExcerpt = window.wpsrHelper.htmlSubstring(ytContent, 100);
                    if( ytExcerpt !== undefined ) {
                        $(".wpsr-yt-popup-video-meta-description").html(ytExcerpt.replace(/\n/g, "<br>"));
                    }
                }

                if( $(".wpsr-yt-popup-video-comment-text-inner").length !== 0 ) {
                    $(".wpsr-yt-popup-video-comment-text-inner").each(function () {
                        let ytComment = $(this).html();
                        let ytCommentExcerpt = window.wpsrHelper.htmlSubstring(ytComment, 150);
                        if( ytCommentExcerpt !== undefined ){
                            $(this).html(ytCommentExcerpt.replace(/\n/g, "<br>"));
                        }
                    });
                }
            },
            complete: function () {
            },
            error: function () {
            },
        });

        $(document).on('click', '.wpsr-yt-popup-video-preview', function (e) {
            window.open('https://www.youtube.com/watch?v=' + videoId, '_blank');
        });
    }
}
