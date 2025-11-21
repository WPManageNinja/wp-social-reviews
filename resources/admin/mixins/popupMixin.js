export const popupMixin = {
    data() {
        return {
            selectedFeed: [],
            currentIndex: null,
        }
    },
    methods: {
        getPreviousFeed() {
            if(this.currentIndex !== null) {
                this.currentIndex--;
                this.selectedFeed = this.configs.dynamic.items[this.currentIndex];
            }
        },
        getNextFeed() {
            if(this.currentIndex !== null) {
                this.currentIndex++;
                this.selectedFeed = this.configs.dynamic.items[this.currentIndex];
            }
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