let $ = jQuery;
let wpsrFeeds = {};

export default {
    init: function (){
        let $that = this;
        $('.wpsr-twitter-card-wrapper').each(function (index) {
            wpsrFeeds[index] = $that.wpsrCreateFeed($(this), 2);
            $that.wpsrTwitterCardGenerator(index);
        });
    },
    wpsrCreateFeed(el, checks) {
        let feed = {
            tcLinksToCheck: el.find('.wpsr-has-card-url').length,
            element: el,
            checksRemaining: checks
        };
        return feed;
    },
    wpsrTweetLinkRetriever(el, index) {
        let urls = [];
        el.find('.wpsr-has-card-url').each(function () {
            //urls.push($(this).attr('data-cardurl'));
            if (!$(this).hasClass('wpsr-tc-active')) {
                urls.push({url: $(this).attr('data-cardurl'), id:$(this).attr('data-id')});
            }
        });
        return urls;
    },
    wpsrAddTwitterCards(tcObj, index) {
        let checkLinks = wpsrFeeds[index].element.find('.wpsr-has-card-url');
        checkLinks.each(function () {
            let $self = $(this),
                link = $self.attr('data-cardurl'),
                id = $self.attr('data-id');
            wpsrFeeds[index].tcLinksToCheck--;
            if (tcObj.hasOwnProperty(id)) {
                let youtube = (link.indexOf('youtube.com/watch') > -1),
                    youtu = (link.indexOf('youtu.be') > -1),
                    youtube_embed = (link.indexOf('youtube.com/embed') > -1),
                    vimeo = (link.indexOf('vimeo') > -1),
                    soundcloud = (link.indexOf('soundcloud.com') > -1),
                    active_card_video = false;

                if (( youtube || youtu || youtube_embed || vimeo || soundcloud) && $(this).hasClass('wpsr-active-video-card')) {
                    active_card_video = true;
                }

                let twitter_card = tcObj[id]['twitter_card'];
                let titleIndicator = ( twitter_card['twitter:title'] && twitter_card['twitter:title'].length >= 48 ) ? '...' : '';
                let desIndicator = (twitter_card['twitter:description'] && twitter_card['twitter:description'].length >= 100 ) ? '...' : '';

                if (( (twitter_card['twitter:card'] === 'summary_large_image' || twitter_card['twitter:card'] === 'summary')  ) || ( twitter_card['twitter:card'] === 'player' && active_card_video )) {
                    // activate imagesLoaded to set loadmore button on the bottom
                    let templateId = $('.wpsr-twitter-feed-wrapper').data("template-id");
                    let gridId = $('#wpsr-twitter-tweet-' + templateId);
                    let templateType = gridId.data('template-type');
                    let column = gridId.data("column");

                    if( templateType === 'masonry' ) {
                        gridId.find('.wpsr-twitter-all-tweets').imagesLoaded(function () {
                            gridId.find('.wpsr-twitter-all-tweets').masonry({
                                itemSelector: '.wpsr-col-' + column,
                            });
                        });
                    }
                    let imgHtml = '',
                        domainUrl = (typeof link !== 'undefined') ? link.replace(/^https?\:\/\//i, "").split('/')[0] : '';
                    if (twitter_card.hasOwnProperty('twitter:image') && twitter_card['twitter:image'] !== '') {
                        imgHtml = '<div class="wpsr-tc-image" style="background-image: url(' + twitter_card['twitter:image'] + ')"><img src="' + twitter_card['twitter:image'] + '" alt="' + twitter_card['twitter:image:alt'] + '"></div>'
                    }

                    $self.find('.wpsr-tweet-text').append('<a class="wpsr-twitter-card wpsr-tc-type-' + twitter_card['twitter:card'] + '" href="' + link + '" target="_blank">' +
                        imgHtml +
                        '<div class="wpsr-tc-summary-info">' +
                        '<p class="wpsr-tc-heading">' + twitter_card['twitter:title'].substr(0, 48) + titleIndicator + '</p>' +
                        '<p class="wpsr-tc-desc">' + twitter_card['twitter:description'].substring(0, 100) + desIndicator + '</p>' +
                        '<p class="wpsr-tc-url">' + domainUrl + '</p>' +
                        '</div>' +
                        '</a>');
                }
            }
            $self.removeClass('wpsr-has-card-url');
            $(this).addClass('wpsr-tc-active');
            // remove the url from the tweet content
            if ($self.find('.wpsr-tweet-text').find('a[href="' + link + '"]').length) {
                $self.find('.wpsr-tweet-text').find('a.wpsr-link-tweet[href="' + link + '"]').hide();
            }
            return wpsrFeeds[index].tcLinksToCheck;
        });
    },
    wpsrTwitterCardGenerator(index) {
        let twitterCardUrls = this.wpsrTweetLinkRetriever(wpsrFeeds[index].element, index);
        wpsrFeeds[index].checksRemaining--;
        let $that = this;
        if (twitterCardUrls.length > 0) {
            jQuery.ajax({
                url: wpsr_ajax_params.ajax_url,
                type: 'post',
                data: {
                    action: 'wpsr_twitter_cards',
                    wpsr_urls: twitterCardUrls,
                    security: wpsr_ajax_params.wpsr_nonce
                },
                success: function (data) {
                    if (data.substring(0, 1) !== '<' && data.substring(0, 200).indexOf('<meta') === -1) {
                        let urlObject = jQuery.parseJSON(data);
                        if (urlObject && urlObject.hasOwnProperty('error')) {
                        } else {
                            $that.wpsrAddTwitterCards(urlObject, index);
                            window.dispatchEvent(new Event('resize'));
                        }
                    }
                }
            });
        }
    },
}