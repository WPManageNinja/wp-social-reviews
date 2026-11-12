export const igMixin = {
    methods: {
        // Mirrors InstagramTemplateHandlerPro::renderPostTime so the editor matches the theme.
        timeAgo(timestamp) {
            if (!timestamp) return '';
            const now = new Date();
            const date = new Date(timestamp);
            const seconds = Math.floor((now - date) / 1000);
            const intervals = [
                { label: 'Year', seconds: 31536000 },
                { label: 'Month', seconds: 2592000 },
                { label: 'Week', seconds: 604800 },
                { label: 'Day', seconds: 86400 },
                { label: 'Hour', seconds: 3600 },
                { label: 'Minute', seconds: 60 },
            ];
            for (const interval of intervals) {
                const count = Math.floor(seconds / interval.seconds);
                if (count >= 1) {
                    return count + ' ' + interval.label + (count > 1 ? 's' : '') + ' Ago';
                }
            }
            return 'Just now';
        },
        formatText( text ){
            let hashTagUrl = 'https://www.instagram.com/explore/tags/';
            text = this.escapeHtml(text);
            let content = this.generateURLsFromHashTag(text, hashTagUrl);
            return this.nl2br(content);
        },
        // Highlights posts carry no position class; :nth-child in CSS decides which cards are large.
        getItemWrapperClass() {
            if (this.isHighlightsLayout) {
                return 'wpsr-highlight-item';
            }
            // Mirrors InstagramTemplateHandler::renderTemplateItemWrapper.
            if (!this.isCarouselLayout && this.configs.feed_settings.template === 'template2') {
                return 'wpsr-mb-30 ' + this.responsiveClasses;
            }
            return this.responsiveClasses;
        },
        getFeedLink(feed) {
            const settings = this.configs.feed_settings;
            if (
                ((settings.shoppable_settings.enable_shoppable === 'true' && feed.shoppable_options && feed.shoppable_options.from === 'template_settings') ||
                (settings.shoppable_settings.include_shoppable_by_hashtags === 'true' && feed.shoppable_options && feed.shoppable_options.from === 'include_shoppable_by_hashtags')) &&
                feed.shoppable_options && feed.shoppable_options.show_shoppable
            ) {
                return feed.shoppable_options.url_settings && feed.shoppable_options.url_settings.url
                    ? feed.shoppable_options.url_settings.url
                    : feed.permalink;
            }
            return feed.permalink;
        },
        // Mirrors InstagramTemplateHandler's $wpsr_has_link. Only 'instagram' mode navigates.
        // Elsewhere a click on a video would leave the editor, and preventDefault also
        // cancels the native controls.
        getFeedHref(feed) {
            if (this.configs.feed_settings.post_settings.display_mode !== 'instagram') {
                return null;
            }
            return this.getFeedLink(feed);
        },
        // Without an href the anchor is not focusable, so it needs button semantics.
        // Mirrors the tabindex/role the theme's views emit.
        getFeedTabindex(feed) {
            return this.getFeedHref(feed) ? null : '0';
        },
        getFeedRole(feed) {
            return this.getFeedHref(feed) ? null : 'button';
        },
        // Mirrors the keydown handler in resources/public/wp-social-review.js.
        activateFeedOnKey(e) {
            if (e.currentTarget.hasAttribute('href')) {
                return;
            }
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                e.currentTarget.click();
            }
        },
        getFeedTarget(feed) {
            if (feed.shoppable_options && feed.shoppable_options.url_settings && feed.shoppable_options.url_settings.open_in_new_tab) {
                return '_blank';
            }
            return '';
        },
        postDisplayMode(e, feed, settings) {
            let playMode = settings.post_settings.display_mode;
            let postSelector = jQuery('.wpsr-ig-playmode');

            if (playMode === 'popup') {
                e.preventDefault();
                jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
            } else if (playMode === 'inline') {
                let $clickedPost = jQuery(e.target).closest('div.wpsr-ig-post');
                let feedId = $clickedPost.data('index');

                if (typeof feedId === 'undefined') {
                    return;
                }

                let clickedVideo = $clickedPost.find('video').get(0);
                if (!clickedVideo) {
                    return;
                }

                if (e.target === clickedVideo) {
                    return;
                }

                e.preventDefault();

                jQuery('.wpsr-ig-post-video').each(function () {
                    let videoEl = jQuery(this).get(0);
                    let $parentPost = jQuery(this).closest('.wpsr-ig-post');
                    if ($parentPost[0] !== $clickedPost[0] && videoEl) {
                        videoEl.pause();
                    }
                });

                if (clickedVideo.paused) {
                    clickedVideo.play().catch((err) => {
                        console.log('Video play was prevented by browser:', err);
                    });
                } else {
                    clickedVideo.pause();
                }
            } else if (playMode === 'instagram') {
                jQuery(postSelector).attr("target", "_blank");
                jQuery(postSelector).attr("href", feed.permalink);
            } else {
                e.preventDefault();
            }
        }
    },
    computed:{
        responsiveClasses(){
            return this.configs.feed_settings.layout_type === 'carousel' && this.has_pro ? 'swiper-slide' : 'wpsr-col-'+this.configs.feed_settings.responsive_column_number.desktop + ' wpsr-col-sm-'+this.configs.feed_settings.responsive_column_number.tablet + ' wpsr-col-xs-'+this.configs.feed_settings.responsive_column_number.mobile;
        },
        isHighlightsLayout() {
            return this.configs.feed_settings.layout_type === 'highlights' && this.has_pro;
        },
        isCarouselLayout() {
            return this.configs.feed_settings.layout_type === 'carousel' && this.has_pro;
        },
        feedContainerClasses() {
            const classes = ['wpsr-ig-all-feed'];
            if (this.isHighlightsLayout) {
                classes.push('wpsr-ig-highlights-grid');
                classes.push('wpsr-column-gap-' + this.configs.feed_settings.column_gaps);
            } else if (this.isCarouselLayout) {
                classes.push('swiper-wrapper');
            } else {
                classes.push('wpsr-row');
                classes.push('wpsr-column-gap-' + this.configs.feed_settings.column_gaps);
            }
            return classes;
        },
        // configs.dynamic.header is absent whenever the feed request returned an error.
        popupUserAvatar() {
            const header = this.configs.dynamic && this.configs.dynamic.header;
            if (!header) {
                return '';
            }
            return header.avatar ? header.avatar.local_avatar : (header.user_avatar || '');
        },
        popupFeedLength() {
            const items = this.configs.dynamic && this.configs.dynamic.items;
            return items ? items.length : 0;
        }
    }
}
