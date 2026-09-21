export const popupMixin = {
    data() {
        return {
            selectedFeed: [],
            currentIndex: null,
        }
    },
    methods: {
        // dynamic.items is absent whenever the feed request returned an error.
        popupFeedItems() {
            const items = this.configs && this.configs.dynamic && this.configs.dynamic.items;
            return Array.isArray(items) ? items : [];
        },
        getPreviousFeed() {
            const items = this.popupFeedItems();
            if (this.currentIndex === null || this.currentIndex <= 0 || !items.length) {
                return;
            }
            this.currentIndex--;
            this.selectedFeed = items[this.currentIndex];
        },
        getNextFeed() {
            const items = this.popupFeedItems();
            if (this.currentIndex === null || this.currentIndex >= items.length - 1) {
                return;
            }
            this.currentIndex++;
            this.selectedFeed = items[this.currentIndex];
        },
        // Clearing the feed without the index leaves the arrows on a stale position.
        resetSelectedFeed() {
            this.selectedFeed = [];
            this.currentIndex = null;
        },
        setCurrentFeed(feed,index, settings, image_settings) {
            let playMode = settings.post_settings.display_mode;

            if (playMode === 'popup') {
                let generatedUrl = this.makeUrl(feed, settings, image_settings);
                feed.media_url = generatedUrl;
            }

            this.selectedFeed = [];
            this.selectedFeed = feed;
            this.currentIndex = index;
        },
        makeUrl(feed, settings, image_settings){
            let upload_url = this.appVars.upload_url;
            let image_format = this.appVars.image_format
            let directory = upload_url + 'instagram' +'/' +  feed.username + '/' + feed.id + '_' + settings.post_settings.resolution + '.'+ image_format;
            return image_settings.optimized_images === 'true' ? directory : feed.media_url;
        },
    }
}
