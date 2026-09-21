/** Minimal faithful Phase 24 copy from resources/public/twitter-card.js. */
const wpsrPhase24TwitterCardFixture = {
    wpsrSafeUrl(url) {
        if (typeof url !== 'string' || url === '') {
            return '';
        }
        try {
            let protocol = new URL(url, window.location.href).protocol;
            return (protocol === 'http:' || protocol === 'https:') ? url : '';
        } catch (e) {
            return '';
        }
    },

    wpsrAddTwitterCards(tcObj, index) {
        let $that = this;
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
                        const $masonryElement = gridId.find('.wpsr-twitter-all-tweets');
                        if ($masonryElement.length && typeof $masonryElement.imagesLoaded === 'function') {
                            $masonryElement.imagesLoaded(function () {
                                if ($masonryElement.length && typeof $masonryElement.masonry === 'function') {
                                    $masonryElement.masonry({
                                        itemSelector: '.wpsr-col-' + column,
                                    });
                                }
                            });
                        }
                    }
                    let domainUrl = (typeof link !== 'undefined') ? link.replace(/^https?\:\/\//i, "").split('/')[0] : '';

                    // Card fields are scraped from third-party pages, so every value
                    // below is attacker-controlled. Build the card with DOM APIs
                    // rather than concatenating into an HTML string: attributes set
                    // via .attr()/.css() and text set via .text() are never parsed
                    // as markup, so a quote in a title, image URL or alt text cannot
                    // break out of its context.
                    let $card = $('<a>', {
                        'class': 'wpsr-twitter-card wpsr-tc-type-' + twitter_card['twitter:card'],
                        target: '_blank'
                    }).attr('href', $that.wpsrSafeUrl(link));

                    let cardImage = $that.wpsrSafeUrl(twitter_card['twitter:image']);
                    if (cardImage) {
                        $('<div>', {'class': 'wpsr-tc-image'})
                            .css('background-image', 'url("' + $that.wpsrCssUrlEscape(cardImage) + '")')
                            .append($('<img>').attr({
                                src: cardImage,
                                alt: twitter_card['twitter:image:alt'] || ''
                            }))
                            .appendTo($card);
                    }

                    $('<div>', {'class': 'wpsr-tc-summary-info'})
                        .append($('<p>', {'class': 'wpsr-tc-heading'})
                            .text(twitter_card['twitter:title'].substr(0, 48) + titleIndicator))
                        .append($('<p>', {'class': 'wpsr-tc-desc'})
                            .text(twitter_card['twitter:description'].substring(0, 100) + desIndicator))
                        .append($('<p>', {'class': 'wpsr-tc-url'}).text(domainUrl))
                        .appendTo($card);

                    $self.find('.wpsr-tweet-text').append($card);
                }
            }
            $self.removeClass('wpsr-has-card-url');
            $(this).addClass('wpsr-tc-active');
            // remove the url from the tweet content — match on the href property
            // instead of interpolating the URL into a jQuery selector string,
            // which a quote or bracket in the URL would otherwise corrupt.
            $self.find('.wpsr-tweet-text').find('a.wpsr-link-tweet').filter(function () {
                return $(this).attr('href') === link;
            }).hide();
            return wpsrFeeds[index].tcLinksToCheck;
        });
    }
};
