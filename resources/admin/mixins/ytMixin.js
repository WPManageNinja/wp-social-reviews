export const ytMixin = {
    data() {
        return {
            selectedFeed: []
        }
    },
    methods: {
        setCurrentFeed(feed) {
            this.selectedFeed = [];
            this.selectedFeed = feed;
        },
        youtubeVideoPlayMode(e, feed, playMode, index) {
            let videoId = (feed.snippet.resourceId && feed.snippet.resourceId.videoId) ? feed.snippet.resourceId.videoId : feed.id;
            if (playMode === 'popup') {
                jQuery('.wpsr-yt-popup-overlay').removeClass('wpsr-yt-popup-open');
                jQuery('.wpsr-yt-popup-overlay').addClass('wpsr-yt-popup-open');
                jQuery(".wpsr-yt-video-playmode").removeAttr("href");
            } else if (playMode === 'gallery') {
                e.preventDefault();
                let galleryModeSelector = jQuery('.wpsr-yt-video-player-gallery');
                jQuery(galleryModeSelector).html('<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>');

                jQuery('html, body').animate({
                    scrollTop: jQuery(".wpsr-yt-video-player-gallery").offset().top
                }, 500);
            } else if (playMode === 'youtube') {
                let videoSelector = jQuery('.wpsr-yt-video-playmode');
                let watchUrl = 'https://www.youtube.com/watch?v=' + videoId;

                jQuery(videoSelector).attr("target", "_blank");
                jQuery(videoSelector).attr("href", watchUrl);
            } else {
                e.preventDefault();
                let inlineModeSelector = jQuery('#wpsr-video-play-' + index);
                jQuery(inlineModeSelector).parents().find(".wpsr-yt-video").find(".wpsr-yt-inline-video-iframe").replaceWith("");
                jQuery(inlineModeSelector).html('<iframe class="wpsr-yt-inline-video-iframe" src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>');
                jQuery(inlineModeSelector).parents().find(".wpsr-yt-video").removeClass('active');
                jQuery(inlineModeSelector).parents(".wpsr-yt-video").addClass('active');
            }
        },
        htmlSubstring(text) {
            if (!text) return '';

            // Get translated strings for Read More/Less if available
            const readMoreText = this.$t ? this.$t('Read More') : 'Read More';
            const readLessText = this.$t ? this.$t('Read Less') : 'Read Less';

            // Character limit for truncation
            const characterLimit = 100;

            // If text is shorter than the limit, return it as is
            if (text.length <= characterLimit) {
                return text;
            }

            // Regular expression to match HTML tags
            const tagRegex = /<([^>\s]*)[^>]*>/g;
            let match;
            let stack = [];
            let lastIndex = 0;
            let result = '';
            let charCount = 0;
            let indexMap = [];

            // Process HTML tags and track their positions
            while ((match = tagRegex.exec(text)) !== null && charCount < characterLimit) {
                // Get text between last tag and this one
                const textBetween = text.substring(lastIndex, match.index);

                // Count only visible characters (not tags)
                const visibleChars = Math.min(textBetween.length, characterLimit - charCount);

                // Add text between tags to result
                result += textBetween.substring(0, visibleChars);
                charCount += visibleChars;

                // Store tag positions for later analysis
                indexMap.push({ 'first': match.index, 'second': tagRegex.lastIndex });

                // Add the tag to result
                result += match[0];

                // Update last position
                lastIndex = tagRegex.lastIndex;
            }

            // Add remaining text if we haven't reached the limit
            if (charCount < characterLimit) {
                const remainingText = text.substring(lastIndex);
                const visibleChars = Math.min(remainingText.length, characterLimit - charCount);
                result += remainingText.substring(0, visibleChars);
            }

            // Find a good breaking point based on tag positions
            let breakPoint = characterLimit;
            for (let i = 1; i < indexMap.length; i++) {
                if (indexMap[i - 1].first < characterLimit && indexMap[i].second > characterLimit) {
                    breakPoint = indexMap[i].second;
                    break;
                }
            }

            // Create excerpt and hidden content
            const excerpt = text.substring(0, breakPoint);
            const hideContent = text.substring(breakPoint);

            // Create final content with read more/less buttons
            const addContent = excerpt +
                "<span class='wpsr_add_read_more_slice_content'>" + hideContent + "</span>" +
                "<span class='wpsr_read_more' tabindex='0'>" + readMoreText + "</span>" +
                "<span class='wpsr_read_less' tabindex='0'>" + readLessText + "</span>";

            return addContent;
        },
        get_header_avatar(account) {
            if(account.avatar && account.avatar.local_avatar.length) {
                return account.avatar.local_avatar;
            }
            if(account.items && account.items.length) {
                return account.items[0].snippet.thumbnails.high.url
            }
        }
    },
    computed: {
        responsiveClasses(){
           return this.configs.feed_settings.layout_type === 'carousel' && this.has_pro ? 'swiper-slide' : 'wpsr-col-'+this.configs.feed_settings.responsive_column_number.desktop + ' wpsr-col-sm-'+this.configs.feed_settings.responsive_column_number.tablet + ' wpsr-col-xs-'+this.configs.feed_settings.responsive_column_number.mobile
        }
    }
}