export const fbFeedMixin = {
    data() {
        return {
            selectedFeed: [],
            currentIndex: null,
            hasAlbumFeed : false,
            selectedAlbumFeed : [],
            feedItems: [],
        }
    },
    methods:{
        getPostId(feed, feed_type){
            return feed_type === 'album_feed' ? feed.photos.data[0].id : feed.id;
        },
        getPreviousFeed(){
            if( this.currentIndex !== null ) {
                this.currentIndex--;
                this.handleControlButton();
            }
        },
        getNextFeed() {
            if( this.currentIndex !== null ) {
                this.currentIndex++;
                this.handleControlButton();
            }
        },
        handleControlButton() {
            if (this.feedConfig.feed_settings.source_settings.feed_type === 'album_feed'){
                this.selectedFeed = {
                    ...this.selectedAlbumFeed,
                    ...this.selectedAlbumFeed.photos.data[this.currentIndex]
                }
            } else {
                this.selectedFeed = this.feedConfig.dynamic.items[this.currentIndex];
            }

            if(this.image_settings.optimized_images === 'true'){
                let generatedUrl = this.makeUrl(this.selectedFeed, this.feedConfig.feed_settings, this.image_settings);
                this.selectedFeed.media_url = generatedUrl;
            }
        },
        handleAlbumBreadCrumbs() {
            this.hasAlbumFeed = false;
        },
        handleAlbumClick(feed) {
            this.selectedAlbumFeed = feed;
            this.hasAlbumFeed = true;
        },
        postDisplayMode(e, feed, settings, index, image_settings) {
            this.selectedFeed = [];
            this.currentIndex = index;

            let display_mode = settings.post_settings.display_mode;
            let feed_type = settings.source_settings.feed_type;

            if( feed_type === 'album_feed' ) {
                this.selectedFeed = {
                    ...this.selectedAlbumFeed,
                    ...feed
                }
            } else {
                this.selectedFeed = feed;
            }

            if( display_mode === 'popup' ) {
                if(feed.status_type === 'shared_story'){
                    return false;
                } else {
                    let generatedUrl = this.makeUrl(feed, settings, image_settings);
                    this.selectedFeed.media_url = generatedUrl;
                    jQuery('iframe').on('load', function (){
                        jQuery('.wpsr-feed-popup-loader').removeClass('wpsr-popup-loading');
                    });
                    jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
                    jQuery('.wpsr-feed-link').removeAttr("href");
                }
            } else if( display_mode === 'facebook') {
                let postSelector = jQuery('.wpsr-feed-link');

                let href = '';
                if(feed_type === 'photo_feed'){
                    href = feed.link;
                } else if(feed_type === 'album_feed') {
                    let urlObject = this.selectedFeed.photos.data.filter((photo) => photo.source === this.selectedFeed.source);
                    href = urlObject[0].link;
                } else if(feed_type === 'video_feed' || feed_type === 'video_playlist_feed') {
                    href = (feed_type === 'video_feed' || feed_type === 'video_playlist_feed' || feed_type === 'event_feed') ? 'https://www.facebook.com'+feed.permalink_url : feed.permalink_url;
                } else if(feed_type === 'event_feed'){
                    href =  'https://www.facebook.com/'+feed.id;
                } else if(feed_type === 'timeline_feed') {
                    // Handle timeline feeds (including those with videos)
                    if (feed.permalink_url) {
                        // Check if permalink_url already contains the full Facebook URL
                        if (feed.permalink_url.startsWith('http')) {
                            href = feed.permalink_url;
                        } else {
                            href = 'https://www.facebook.com' + feed.permalink_url;
                        }
                    } else {
                        href = feed.link;
                    }
                }

                if(feed.status_type === 'shared_story'){
                    return false;
                }

                jQuery(postSelector).attr("target", "_blank");
                jQuery(postSelector).attr("href", href);
            } else {
                jQuery(".wpsr-feed-link").removeAttr("href");
            }
        },
        makeUrl(feed, settings, image_settings){
            const media_url = feed.media_url;
            const isPlaceholder = media_url && media_url.includes('placeholder');

            if (isPlaceholder && image_settings.optimized_images === 'true') {
                return feed.media_url;
            }

            let upload_url = this.appVars.upload_url;
            let image_format = this.appVars.image_format
            let directory = upload_url + 'facebook_feed' +'/' +  feed.page_id + '/' + feed.id + '_' + settings.post_settings.resolution + '.'+ image_format;
            return image_settings.optimized_images === 'true' ? directory : feed.media_url;
        },
        filterFeeds(){
            if(this.feedConfig.feed_settings.source_settings.feed_type === 'album_feed'){
                if(this.feedConfig.dynamic.items !== undefined){
                    this.feedItems = this.feedConfig.dynamic.items.filter((item) => item.photos !== undefined);
                }
            } else {
                this.feedItems = this.feedConfig.dynamic.items;
            }
        },
    },
    computed:{
        responsiveClasses(){
            return this.feedConfig.feed_settings.layout_type === 'carousel' && this.has_pro ? 'swiper-slide' : 'wpsr-col-'+this.feedConfig.feed_settings.responsive_column_number.desktop + ' wpsr-col-sm-'+this.feedConfig.feed_settings.responsive_column_number.tablet + ' wpsr-col-xs-'+this.feedConfig.feed_settings.responsive_column_number.mobile;
        },
        generatePhotoAlbumFeedClass() {
            let templateMeta = this.feedConfig.feed_settings;
            let displayPosts = (templateMeta.filters && templateMeta.filters.display_posts) || [];
            
            if (!Array.isArray(displayPosts) || displayPosts.length === 0) {
                return '';
            }
        
            const allowedCombinations = [
                ['album'],
                ['photo'],
                ['album', 'photo'].sort()
            ];
        
            const sortedDisplayPosts = [...displayPosts].sort();
            const arraysEqual = (arr1, arr2) => arr1.length === arr2.length && arr1.every((val, index) => val === arr2[index]);
            const isValidCombination = allowedCombinations.some(combination => arraysEqual(sortedDisplayPosts, combination));
        
            return isValidCombination ? 'wpsr-fb-feed-item-zero-padding' : '';
        }
        
    },
    mounted() {
        this.filterFeeds();
    },
    watch: {
        feedConfig: {
            handler: function (val, oldVal) {
                this.filterFeeds();
            },
            deep: true
        },
        'feedConfig.feed_settings.source_settings.feed_type': {
            handler: function () {
                this.hasAlbumFeed = false;
            },
            deep: true
        }
    }
}