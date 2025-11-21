export const templateMixin = {
    data() {
        return {
            platform: '',
            image_settings: {},
            platform_short: '',
            image_format : this.appVars.image_format
        }
    },
    methods: {
        getPlatformShortName(platform){
            const platformMap = {
                instagram: 'ig',
                facebook_feed: 'fb',
                tiktok: 'tt',
                youtube: 'yt'
            };
            return platformMap[platform] || '';
        },
        saveImages(post_id, platform, feed_type = null) {
            let upload_url = this.appVars.upload_url;
            this.platform = platform
            this.platform_short = this.getPlatformShortName(platform)
            let submitData = {
                action: 'wpsr_resize_images',
                id: post_id,
                platform: platform,
                feed_type: feed_type,
                nonce: this.appVars && this.appVars.nonce ? this.appVars.nonce : '',
            };
            let ajaxUrl = this.appVars.ajaxurl;
            
            jQuery.post({
                url: ajaxUrl,
                data: submitData,
            }).then(response => {
                if(response) {
                    let jsonObjects = response.match(/({[^{}]+})/g);
                    let image_data_arrays = JSON.parse(jsonObjects);
                    let that = this;

                    if(image_data_arrays.account_id) {
                        jQuery(`.wpsr-${that.platform_short}-header`).find(`.wpsr-${that.platform_short}-header-logo`).each(function (index) {
                            let account_id = jQuery(this).data("account_id");
                            account_id = account_id.toString();
                            // let image_format = this.appVars.image_format
                            if(account_id === image_data_arrays.account_id) {
                                let image = upload_url + '/'+ that.platform +'/' + account_id + '.' + this.image_format;
                                jQuery(this).find('img').attr('src', image);
                            }
                        });
                    }
                    
                    if(image_data_arrays.images_data) {
                        this.loadImage(image_data_arrays.images_data);
                    }
                }
            }).catch(errors => {
            }).always(() => {

            });
        },

        loadImage(image_data) {
            let upload_url = this.appVars.upload_url;
            let that = this;
            let id = null;
            let user_name = null;
            let image_size;
            let image_data_arrays = Object.values(image_data);

            jQuery(document).ready(function() {
                jQuery(`.wpsr-${that.platform_short}-all-feed`).find(`.wpsr-${that.platform_short}-post`).each(function (index) {
                id = jQuery(this).attr("data-post_id");
                image_size = jQuery(this).attr("data-image_size");
                user_name = jQuery(this).attr("data-user_name");
                id = id.toString(); //convert from integer to string id
                if (user_name && id && image_size && image_data_arrays.includes(id)) {
                    let image = upload_url + that.platform +'/' +  user_name + '/' + id + '_' + image_size + '.' + that.image_format;
                    let self = jQuery(this);
                    self.find(`.wpsr-fb-feed-image`).removeClass("wpsr-animated-background");
                    self.find(`.wpsr-fb-feed-image`).css('height: auto')
                    self.find(`.wpsr-fb-feed-image`).removeClass("wpsr-hide");
                    self.find(`.wpsr-${that.platform_short}-post-img`).attr('src', image);
                    self.find(`.wpsr-${that.platform_short}-post-img`).addClass("wpsr-show");
                    self.find(`.wpsr-${that.platform_short}-post-img`).removeClass("wpsr-hide");
                    self.find(`.wpsr-${that.platform_short}-feed-video-play`).removeClass("wpsr-hide")
                    self.find(`.wpsr-${that.platform_short}-post-media`).removeClass("wpsr-animated-background");
                }
                });
            });
        },
        updateSelectedAccountBasedOnFeedType(settings){

            if(settings.feed_settings.source_settings === undefined || settings.feed_settings.source_settings === null) {
                return settings;
            }
            
            const isEventFeed = settings.feed_type === 'event_feed';

            if(settings.feed_settings.source_settings && settings.feed_settings.source_settings.selected_accounts) {
                if(isEventFeed) {
                    settings.feed_settings.source_settings.selected_accounts_for_events = settings.feed_settings.source_settings.selected_accounts;
                    (this.feedConfig.feed_settings) ? settings.feed_settings.source_settings.selected_accounts = this.feedConfig.feed_settings.source_settings.selected_accounts:null;
                } else if(settings.feed_type === 'video_playlist_feed' || settings.feed_type === 'single_album_feed') {
                    settings.feed_settings.source_settings.selected_account = (settings.feed_settings.source_settings.selected_accounts.length > 0) ? settings.feed_settings.source_settings.selected_accounts[0] : null;
                    (this.feedConfig.feed_settings) ? settings.feed_settings.source_settings.selected_accounts = this.feedConfig.feed_settings.source_settings.selected_accounts:null;
                }else{
                    settings.feed_settings.source_settings.selected_accounts = settings.feed_settings.source_settings.selected_accounts.filter(account => {
                        return account.includes('event_feed_') === false;
                    });
                    (this.feedConfig.feed_settings) ? settings.feed_settings.source_settings.selected_accounts_for_events = this.feedConfig.feed_settings.source_settings.selected_accounts_for_events:null;
                }
            }

            return settings;
        },
        getFeedSettings(platform, postType = 'post', postId = null) {
            this.loading = true;
            this.platform = platform;
            this.platform_short = this.getPlatformShortName(platform);
            let feed_id = postId ? postId : this.$route.params.template_id;
            this.$get(`templates/meta/feeds/${feed_id}`, {
                platform: platform,
                postType: postType
            }).then(response => {
                if(response.data) {
                    if(platform !== 'tiktok' && platform !== 'instagram' && platform !== 'facebook_feed' && platform !== 'youtube' && response.data.settings && response.data.settings.dynamic && response.data.settings.dynamic.error_message) {
                      this.handleError(response.data.settings.dynamic.error_message);
                    }
                    if( response.data.settings && response.data.settings.error_message && response.data.settings.error_message.length ) {
                      this.handleError(response.data.settings.error_message);
                    }

                    this.pages =  response.data.sources;
                    this.posts = response.data.posts !== undefined ? response.data.posts.splice(1) : null;
                    this.post_types = response.data.post_types !== undefined ? response.data.post_types : null;
                    if(this.post_types) {
                        this.post_types.push(this.post_type_default);
                    }
                    //image settings for feeds
                    if(response.data.image_settings) {
                        this.image_settings = response.data.image_settings;
                        //save and show images if settings in done
                        let responseSettings = response.data.settings;
                        if((this.platform === 'instagram' || this.platform === 'facebook_feed' || this.platform === 'tiktok' || this.platform === 'youtube') && this.image_settings && this.image_settings.optimized_images === 'true') {
                            let templateId = response.data.template_details.ID;

                            if(responseSettings && responseSettings.dynamic && responseSettings.dynamic.resize_data) {
                                if(responseSettings.dynamic.resize_data.length < responseSettings.dynamic.items.length || responseSettings.dynamic.has_latest_post) {
                                    this.saveImages(templateId, this.platform);
                                }else{
                                    this.loadImage(responseSettings.dynamic.resize_data);
                                    const that = this;
                                    jQuery(document).ready(function() {
                                        jQuery(`.wpsr-${that.platform_short}-all-feed .wpsr-${that.platform_short}-post`).each(function() {
                                            var $post = jQuery(this);
                                            $post.find(`.wpsr-${that.platform_short}-post-media`).removeClass("wpsr-animated-background");
                                            $post.find(`.wpsr-${that.platform_short}-feed-image`).removeClass("wpsr-animated-background");
                                            $post.find(`.wpsr-${that.platform_short}-feed-image`).css('height', 'auto');
                                            $post.find(`.wpsr-${that.platform_short}-post-img`).addClass("wpsr-show").removeClass("wpsr-hide");
                                            $post.find(`.wpsr-${that.platform_short}-feed-video-play`).removeClass("wpsr-hide")
                                        });
                                    });
                                }
                            }
                        }
                    }

                    let updatedSettings = this.updateSelectedAccountBasedOnFeedType(response.data.settings);
                    this.feedConfig = updatedSettings;
                    this.elements = response.data.elements ? response.data.elements : null;
                    this.template_title = response.data.template_details && response.data.template_details.post_title;
                    this.counter = 0;
                    this.isEditing = false;
                }
            }).catch(error => {
                this.handleError(error);
            }).always(() => {
                this.loading = false;
            });
        },
        updateEditorSettings(platform) {
            let template_id = this.$route.params.template_id;
            this.saving = true;

            let settings = this.feedConfig;

            if(platform === 'facebook_feed'){
                if(settings.feed_settings.source_settings.feed_type === 'event_feed') {
                    settings.feed_settings.source_settings.selected_accounts = settings.feed_settings.source_settings.selected_accounts_for_events;
                } else if (
                    settings.feed_settings.source_settings.feed_type === 'video_playlist_feed' || 
                    settings.feed_settings.source_settings.feed_type === 'single_album_feed'
                ) {
                    settings.feed_settings.source_settings.selected_accounts = [settings.feed_settings.source_settings.selected_account];
                }
            }
            this.$put(`templates/meta/feeds/${template_id}`, {
                settings: JSON.stringify(settings),
                platform: platform
            }).then(response => {
                if (!response.success) {
                    this.handleError(response.data.message);
                }else{
                    if (response.data) {
                        this.handleSuccess(response.data.message);
                        this.getFeedSettings(this.platform);
                    }
                }
            }).catch((errors) => {
                this.handleError(errors);
            }).always(() => {
                this.saving = false;
            });
        },
        editEditorSettings(platform) {
            let settings = JSON.parse(JSON.stringify(this.feedConfig));
            
            if (platform === 'youtube') {
                settings['edit_mode'] = true;
            } else if (this.feedConfig.feed_settings.edit_mode === false) {
                this.feedConfig.feed_settings.edit_mode = false;
                settings['edit_mode'] = false;
            }
            this.platform_short = this.getPlatformShortName(platform)
            this.loading = true;
            let template_id = this.$route.params.template_id;

            if(platform === 'facebook_feed'){
                if(settings.feed_settings.source_settings.feed_type === 'event_feed') {
                    settings.feed_settings.source_settings.selected_accounts = settings.feed_settings.source_settings.selected_accounts_for_events;
                } else if (
                    settings.feed_settings.source_settings.feed_type === 'video_playlist_feed' ||
                    settings.feed_settings.source_settings.feed_type === 'single_album_feed'
                ) {
                    settings.feed_settings.source_settings.selected_accounts = [settings.feed_settings.source_settings.selected_account];
                }
            }

            this.$post(`templates/meta/feeds/${template_id}/edit`, {
                settings: JSON.stringify(settings),
                platform: platform
            }).then(response => {
                if (response.data) {
                    //twitter
                    let settings = response.data.settings;
                    if (settings) {
                        if (settings.error_message && settings.error_message.length) {
                            this.handleError(settings.error_message);
                        }

                        //instagram & facebook & youtube
                        if (settings.dynamic && settings.dynamic.error_message) {
                            this.handleError(settings.dynamic.error_message)
                        }
                        
                        let updatedSettings = this.updateSelectedAccountBasedOnFeedType(settings);
                        this.feedConfig = updatedSettings;
                        
                    }
                    //image settings for feeds
                    if(response.data.settings.image_settings) {
                        this.image_settings = response.data.settings.image_settings;
                        //save and show images if settings in done
                        let responseSettings = response.data.settings;
                        if((this.platform === 'facebook_feed') && this.image_settings && this.image_settings.optimized_images === 'true') {
                            if(responseSettings && responseSettings.dynamic && responseSettings.dynamic.resize_data) {
                                if(responseSettings.dynamic.resize_data.length < responseSettings.dynamic.items.length || responseSettings.dynamic.has_latest_post) {
                                    let feed_type = this.feedConfig.feed_settings.source_settings.feed_type;
                                    this.saveImages(template_id, platform, feed_type);
                                }else{
                                    const that = this;
                                    jQuery(`.wpsr-${this.platform_short}-all-feed`).find(`.wpsr-${that.platform_short}-post`).each(function (index) {
                                        let self = jQuery(this);
                                        self.find(`.wpsr-fb-feed-image`).removeClass("wpsr-animated-background");
                                        self.find(`.wpsr-fb-feed-image`).css('height: auto')
                                        self.find(`.wpsr-fb-feed-image`).removeClass("wpsr-hide");
                                        self.find(`.wpsr-${that.platform_short}-post-img`).addClass("wpsr-show");
                                        self.find(`.wpsr-${that.platform_short}-post-img`).removeClass("wpsr-hide");
                                        self.find(`.wpsr-${that.platform_short}-feed-video-play`).removeClass("wpsr-hide")
                                        self.find(`.wpsr-${that.platform_short}-post-media`).removeClass("wpsr-animated-background");
                                    });
                                }
                            }
                        }
                    }
                }
            }).catch(error => {
                this.handleError(error);
            }).always(() => {
                this.loading = false;
            });
        },
    },

    watch: {
        'feedConfig.feed_settings.responsive_column_number': {
            handler(val) {
                if(this.layoutType === 'masonry' && val !== undefined) {
                    this.runMasonry();
                }
            },
            deep:true
        },
        'feedConfig.feed_settings.carousel_settings': {
            handler(val) {
                if(this.layoutType === 'carousel' && this.counter > 0) {
                    this.activeCarousel();
                }
            },
            deep:true
        },
        'feedConfig.feed_settings.column_gaps': {
            handler(val) {
                if(this.layoutType === 'masonry' && val !== undefined) {
                    this.runMasonry();
                }
            },
            deep:true
        },
    }
}