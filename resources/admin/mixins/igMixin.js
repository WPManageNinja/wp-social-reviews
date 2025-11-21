export const igMixin = {
    methods: {
        formatText( text ){
            let hashTagUrl = 'https://www.instagram.com/explore/tags/';
            text = this.escapeHtml(text);
            let content = this.generateURLsFromHashTag(text, hashTagUrl);
            return this.nl2br(content);
        },
        postDisplayMode(e, feed, settings) {
            let playMode = settings.post_settings.display_mode;
            let postSelector = jQuery('.wpsr-ig-playmode');

            if (playMode === 'popup') {
                e.preventDefault();
                jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
                //jQuery(".wpsr-ig-playmode").removeAttr("href");
            } else if (playMode === 'inline') {
                e.preventDefault(); // prevent default if it’s a link/button

                // Get the parent post and feedId
                let $clickedPost = jQuery(e.target).closest('div.wpsr-ig-post');
                let feedId = $clickedPost.data('index');

                if (typeof feedId === 'undefined') {
                    return;
                }

                let clickedVideo = $clickedPost.find('video').get(0);

                // Pause all other videos
                jQuery('.wpsr-ig-post-video').each(function () {
                    let videoEl = jQuery(this).get(0);
                    let $parentPost = jQuery(this).closest('.wpsr-ig-post');
                    if ($parentPost[0] !== $clickedPost[0] && videoEl) {
                        videoEl.pause();
                    }
                });

                // Play clicked video
                if (clickedVideo) {
                    clickedVideo.play().catch((err) => {
                        console.log('Video play was prevented by browser:', err);
                    });
                }
            } else if (playMode === 'instagram') {
                jQuery(postSelector).attr("target", "_blank");
                jQuery(postSelector).attr("href", feed.permalink);
            } else {
                e.preventDefault();
                //jQuery(".wpsr-ig-playmode").removeAttr("href");
            }
        }
    },
    computed:{
        responsiveClasses(){
            return this.configs.feed_settings.layout_type === 'carousel' && this.has_pro ? 'swiper-slide' : 'wpsr-col-'+this.configs.feed_settings.responsive_column_number.desktop + ' wpsr-col-sm-'+this.configs.feed_settings.responsive_column_number.tablet + ' wpsr-col-xs-'+this.configs.feed_settings.responsive_column_number.mobile;
        }
    }
}