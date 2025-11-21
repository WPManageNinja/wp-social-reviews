let $ = jQuery;

let videoId = null;
export default {
    init: function (e, $this){
        this.checkFeedType(e, $this);
    },
    checkFeedType(e, identifier) {
        let playMode = $(identifier).data('playmode');
        let templateId = $(identifier).data('template_id');
        let feedId = $(identifier).data('index');
        let ytVideoId = $(identifier).data('videoid');
        videoId = ytVideoId;
        let parent = $('#wpsr-yt-feed-'+templateId);

        if (playMode === 'popup') {
            this.openPopUpBox(e,identifier);
        } else if( playMode === 'gallery' ){
            e.preventDefault();
            let galleryModeSelector = parent.find('.wpsr-yt-video-player-gallery');
            $(galleryModeSelector).html('<iframe src="https://www.youtube.com/embed/' + ytVideoId + '?autoplay=1&rel=0" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>');
            $('html, body').animate({
                scrollTop: parent.find(".wpsr-yt-video-player-gallery").offset().top
            }, 500);
        } else if (playMode === 'youtube') {
            let watchUrl = 'https://www.youtube.com/watch?v=' + ytVideoId + '';
            $(identifier).attr("target", "_blank");
            $(identifier).attr("href", watchUrl);
        } else {
            e.preventDefault();
            let inlineModeSelector = parent.find('#wpsr-video-play-' + feedId);
            jQuery(inlineModeSelector).parents().find(".wpsr-yt-video").find(".wpsr-yt-inline-video-iframe").replaceWith("");
            jQuery(inlineModeSelector).html('<iframe class="wpsr-yt-inline-video-iframe" src="https://www.youtube.com/embed/' + ytVideoId + '?autoplay=1&rel=0" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>');
            jQuery(inlineModeSelector).parents().find(".wpsr-yt-video").removeClass('active');
            jQuery(inlineModeSelector).parents(".wpsr-yt-video").addClass('active');
        }
    },
    openPopUpBox(e, identifier) {
        let templateId = $(identifier).data('template_id');
        let feedId     = $(identifier).data('videoid');
        let data = {
            'action': 'wpsr_youtube_popup_feed',
            'feedId': feedId,
            'templateId': templateId
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