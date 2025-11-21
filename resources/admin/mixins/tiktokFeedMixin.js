export const tiktokFeedMixin = {
    methods:{
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
            this.selectedFeed = this.feedConfig.dynamic.items[this.currentIndex];
            this.selectedFeed.from = this.feedConfig.dynamic.header.data.user;
        },
        filterFeeds(){
            this.feedItems = this.feedConfig.dynamic.items;
        },
        handleElementHtml(){
            let postSelector = jQuery('.wpsr-feed-link');
            let display_mode = this.feedConfig.feed_settings.post_settings.display_mode;
            if(display_mode === 'tiktok') {
                if (postSelector && postSelector.prop("tagName") && postSelector.prop("tagName").toLowerCase() !== "a") {
                    this.replaceHtmlElement('a');
                }
            } else {
                if (postSelector && postSelector.prop("tagName") && postSelector.prop("tagName").toLowerCase() !== "div") {
                    this.replaceHtmlElement('div');
                }
            }
        },
        replaceHtmlElement(elementType){
            jQuery(".wpsr-feed-link").replaceWith(function () {
                return jQuery(`<${elementType}/>`, {
                    html: jQuery(this).html(),
                    class: jQuery(this).attr("class")
                });
            });
        },
        postDisplayMode(e, feed, settings, index) {
            this.selectedFeed = [];
            this.currentIndex = index;
            let account_data = this.feedConfig.dynamic.header.data.user;

            let display_mode = settings.post_settings.display_mode;
            this.selectedFeed = feed;
            this.selectedFeed.from = account_data;

            if( display_mode === 'popup' ) {
                if(this.image_settings.optimized_images === 'true'){
                    this.selectedFeed.media_url = this.makeUrl(feed, settings)
                }
                jQuery('iframe').on('load', function (){
                    jQuery('.wpsr-feed-popup-loader').removeClass('wpsr-popup-loading');
                });
                jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
                jQuery('.wpsr-feed-link').removeAttr("href");
                jQuery('#wpsr-feed-popup-video-iframe').attr("src", "https://www.tiktok.com/embed/v2/" + feed.id);

            } else if( display_mode === 'tiktok') {
                let postSelector = jQuery('.wpsr-feed-link');

                let href = 'https://www.tiktok.com/@' + account_data.open_id + '/video/' + feed.id;

                jQuery(postSelector).attr("target", "_blank");
                jQuery(postSelector).attr("href", href);
            } else {
                jQuery(".wpsr-feed-link").removeAttr("href");
                jQuery(".wpsr-feed-link").removeAttr("target");
                this.replaceHtmlElement('div');
            }
        },
        makeUrl(feed, settings){
            let upload_url = this.appVars.upload_url;
            let image_format = this.appVars.image_format
            let userName = feed.user.name;
            let directory = upload_url + 'tiktok' +'/' +  userName + '/' + feed.id + '_' + settings.post_settings.resolution + '.' + image_format;
            return this.image_settings.optimized_images === 'true' ? directory : feed.media_url;
        }
    },
    mounted() {
        this.filterFeeds();
        this.$nextTick(() => {
            this.handleElementHtml();
        });
    },
    computed:{
        responsiveClasses(){
            return this.feedConfig.feed_settings.layout_type === 'carousel' && this.has_pro ? 'swiper-slide' : 'wpsr-col-'+this.feedConfig.feed_settings.responsive_column_number.desktop + ' wpsr-col-sm-'+this.feedConfig.feed_settings.responsive_column_number.tablet + ' wpsr-col-xs-'+this.feedConfig.feed_settings.responsive_column_number.mobile;
        }
    },
    watch: {
        feedConfig: {
            handler: function (val, oldVal) {
                this.filterFeeds();
            },
            deep: true
        },
        'feedConfig.feed_settings.post_settings.display_mode': {
            handler: function (newVal, oldVal) {
                if (newVal !== oldVal){
                    this.selectedFeed = [];
                    this.handleElementHtml();
                }
            },
            deep: true
        }
    }
}