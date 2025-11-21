jQuery(document).ready(function ($) {
    //start
    let allElement = $('.wpsr-album-cover-photo-wrapper-inner');
    let prevClasses = [];

    allElement.each(function (index, element) {
        element.addEventListener('click', function (e) {
            e.preventDefault();
            let clickedItem = $(e.target).closest('.wpsr-album-cover-photo-wrapper-inner');
            if (!clickedItem.hasClass('active')){
                clickedItem.addClass('active');
            }

            let feedId = $(clickedItem).attr('id');
            let templateId = $(clickedItem).attr('data-template-id');

            $('.wpsr-album-cover-photo , .wpsr-fb-albums-feed-info').hide();
            let allDivs  = $('.wpsr-album-cover-photo-wrapper');
            allDivs.each(function (index, element) {
                if($(element).find('div.wpsr-album-cover-photo-wrapper-inner.active').length === 0) {
                    $(element).parent().hide();
                }
            });

            $(`#wpsr-album-${feedId}`).css('display', 'block');
            let ele = $(e.target).closest('.wpsr-album-cover-photo-wrapper');
            if (ele.length > 0) {
                prevClasses = ele.parent().attr('class').split(' ');
                ele.parent().removeClass();
                ele.parent().removeAttr('class');
                ele.parent().css('width', '100%');
            }

            let countChildElem = document.getElementById(`wpsr-album-feed-${feedId}`).childElementCount;

            if (countChildElem < 1) {
                jQuery.get(wpsr_ajax_params.ajax_url, {
                    action: 'wpsr_facebook_album_photo',
                    template_id: templateId,
                    feed_id: feedId,
                }).then(response => {
                    if (response) {
                        let activeAlbums = $(`#wpsr-album-feed-${feedId}`);
                        activeAlbums[0].innerHTML = response;
                    }
                }).catch(errors => {
                    console.error(errors);
                }).always(() => {

                });
            }
        })
    });
    //end

    let breadcrumb = $('.wpsr-album-cover-photo-wrapper');
    breadcrumb.each(function (index, element) {
        $(element).on('click', '.wpsr-fb-feed-bread-crumbs-album', function (e) {
            handleBreadcrumb(e);
        })
    });

    const handleBreadcrumb = (e) => {
        e.preventDefault();
        $('.wpsr-fb-feed-image , .wpsr-fb-albums-feed-info').show();
        $('.wpsr-fb-feed-album-wrapper').css('display', 'none');

        let activeAlbums = $('.wpsr-album-cover-photo-wrapper-inner.active');
        activeAlbums.each(function (index, element) {
            $(element).removeClass('active');
        });

        let allDivs  = $('.wpsr-album-cover-photo-wrapper');
        allDivs.each(function (index, element) {
            $(element).parent().show();
        });

        let ele = $(e.target).closest('.wpsr-album-cover-photo-wrapper');
        if (ele.length > 0) {
            ele.parent().removeClass();
            ele.parent().addClass(prevClasses.join(' '));
        }
    };

});