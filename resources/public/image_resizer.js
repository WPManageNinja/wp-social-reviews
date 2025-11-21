jQuery(document).ready(function($) {
    "use strict";
    //request to save images
    let platforms = [
        { name: 'instagram', selector: '.wpsr-ig-feed-wrapper' },
        { name: 'facebook_feed', selector: '.wpsr-fb-feed-wrapper' },
        { name: 'tiktok', selector: '.wpsr-tiktok-feed-wrapper' },
        { name: 'youtube', selector: '.wpsr-yt-feed-wrapper' }
    ];

    // Process each platform separately
    platforms.forEach(platformData => {
        let feeds_selector = $(platformData.selector);
        if (feeds_selector.length === 0) return;

        let platform = platformData.name;

        feeds_selector.each(function () {
            let that = $(this);
            let template_id = '#' + that.attr('id');
            let post_id = template_id.replace(/#wpsr-(fb-feed|tiktok-feed|yt-feed|ig-feed)-/, "");
            let upload_url = window.wpsr_ajax_params.upload_url;

            let submitData = {
                action: 'wpsr_resize_images',
                id: post_id,
                platform: platform,
                nonce: window.wpsr_ajax_params && window.wpsr_ajax_params.wpsr_nonce ? window.wpsr_ajax_params.wpsr_nonce : '',
            };

            let platform_short = {
                'instagram': 'ig',
                'facebook_feed': 'fb',
                'tiktok': 'tt',
                'youtube': 'yt'
            }[platform] || '';

            $.ajax({
                url: window.wpsr_ajax_params.ajax_url,
                type: 'post',
                data: submitData,
            }).then(response => {
                if(response) {
                    if(window.wpsr_optimized_feed_ids) {
                        window.wpsr_optimized_feed_ids[post_id] = response;
                    } else {
                        let data = {[post_id]: response};
                        window.wpsr_optimized_feed_ids = data;
                    }

                    let jsonObjects = response.match(/({[^{}]+})/g);
                    let image_data_arrays = JSON.parse(jsonObjects);
                    let image_format = window.wpsr_ajax_params.image_settings.image_format;

                    if(image_data_arrays.account_id) {
                        $(template_id).find('.wpsr-ig-header-logo').each(function (index) {
                            let account_id = $(this).data("account_id");
                            if (account_id) {
                                account_id = account_id.toString();
                                if(account_id === image_data_arrays.account_id) {
                                    let image = upload_url + '/instagram/' + account_id + '.'+ image_format;
                                    $(this).find('img').attr('src', image);
                                }
                            }
                        });
                    }

                    if(image_data_arrays.images_data) {
                        $(template_id).find(`.wpsr-${platform_short}-post`).each(function (index) {
                            let self = $(this);
                            let media_id = self.attr("data-post_id");
                            let image_size = self.data("image_size");
                            if (media_id) {
                                media_id = media_id.toString(); //convert from integer to string id
                                let user_name = self.attr("data-user_name");
                                if(user_name && image_data_arrays.images_data.includes(media_id)) {
                                    let image = upload_url + '/'+platform+'/' +  user_name + '/' + media_id + '_' + image_size + '.'+image_format;
                                    self.find(`.wpsr-${platform_short}-feed-image`).removeClass("wpsr-animated-background");
                                    self.find(`.wpsr-${platform_short}-feed-video-preview`).removeClass("wpsr-animated-background");
                                    self.find(`.wpsr-${platform_short}-feed-image`).css('height', 'auto');
                                    self.find(`.wpsr-${platform_short}-feed-image`).removeClass("wpsr-hide");
                                    self.find(`.wpsr-${platform_short}-post-img`).attr('src', image);
                                    self.find(`.wpsr-${platform_short}-post-img`).addClass("wpsr-show");
                                    self.find(`.wpsr-${platform_short}-post-img`).removeClass("wpsr-hide");
                                    self.find(`.wpsr-${platform_short}-post-img`).removeClass("wpsr-animated-background");
                                    self.find(`.wpsr-${platform_short}-post-media`).removeClass("wpsr-animated-background");
                                    self.find(`.wpsr-${platform_short}-feed-video-preview`).removeClass("wpsr-animated-background");
                                }
                            }
                        });
                    }
                }
            }).catch(errors => {
                console.error(errors);
            });
        });
    });
});