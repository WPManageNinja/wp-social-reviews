jQuery(document).ready(function($) {
    "use strict";

    let reviews_selector = $(".wpsr-reviews-wrapper");
    reviews_selector.each(function () {

        let that = $(this);
        let template_id = '.' + that.attr('class');
        let match = template_id.match(/wpsr-reviews-(\d+)/);
        var post_id = null;
        if (match) {
             post_id = match[1];
        }
        let upload_url = window.wpsr_ajax_params.upload_url;
        let element = document.querySelector(`.wpsr-reviews-${post_id}`);
        let dataPlatforms = element.getAttribute('data-platforms');
        const platforms = dataPlatforms.split(',');

        let submitData = {
            action: 'wpsr_review_resize_images',
            id: post_id,
            platforms: platforms,
            nonce: window.wpsr_ajax_params && window.wpsr_ajax_params.wpsr_nonce ? window.wpsr_ajax_params.wpsr_nonce : '',
        };

        $.ajax({
            url: window.wpsr_ajax_params.ajax_url,
            type: 'post',
            data: submitData,
        }).then(response => {
            if(response) {
                let jsonObjects = response.match(/({[^{}]+})/g);
                let image_data_arrays = JSON.parse(jsonObjects);

                if(image_data_arrays.images_data) {
                    jQuery(`.wpsr-all-reviews`).find(`.wpsr-review-template`).each(function (index) {
                        let self = $(this);
                        let source_id = self.attr("data-source_id");
                        let media_id = self.attr("data-media_id");
                        let image_resize = self.attr("data-image_resize");
                        let review_platform = self.attr("data-review_platform");
                        if (media_id) {
                            media_id = media_id.toString();
                            let image_format = window.wpsr_ajax_params.image_settings.image_format
                            if(review_platform && source_id && image_data_arrays.images_data.includes(media_id)) {
                                let image = upload_url + '/'+  review_platform + '/'+ source_id +'/' + media_id + '_' + image_resize + '.'+ image_format;
                                self.find(`.wpsr-reviewer-avatar`).attr('src', image);
                                self.find('.wpsr-reviewer-image-url').removeClass('wpsr-reviewer-image-animation');
                            }
                        }
                    });
                }
            }
        }).catch(errors => {
            console.error(errors);
        }).always(() => {

        });
    });
});