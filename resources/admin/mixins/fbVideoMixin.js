// Methods only, so components that cannot take commonMixin or fbFeedMixin
// (editor lifecycle hooks, colliding data keys) can still share these.
export const fbVideoMixin = {
    methods: {
        // Only facebook knows what it will frame, so trust the graph field and
        // treat a missing one (the /posts edge omits it) as embeddable.
        wpsrIsEmbeddable(feed) {
            return !feed || feed.embeddable !== false;
        },
        // href must be encoded, otherwise a permalink carrying its own query
        // string (/watch/?v=123) is truncated by the plugin endpoint.
        wpsrVideoEmbedUrl(videoUrl, args = {}) {
            if (!videoUrl) {
                return '';
            }

            let params = new URLSearchParams({href: videoUrl, autoplay: 1, ...args});

            return `https://www.facebook.com/plugins/video.php?${params.toString()}`;
        }
    }
}
