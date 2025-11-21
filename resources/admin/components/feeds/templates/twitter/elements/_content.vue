<template>
    <div class="wpsr-tweet-content"
          :class="[
              activateTwitterCard(feed, feed_settings) ? 'wpsr-has-card-url':'',
              feed.media && feed.media[0] ? get_media_class(feed.media[0], feed_settings) : ''
          ]"
          :data-cardurl="activateTwitterCard(feed, feed_settings)"
           :data-id="feed.id"
    >
      <!-- feed text -->
      <tweet :feed="feed" :feed_settings="feed_settings"></tweet>

      <!-- feed media -->
      <feed-media :feed="feed" :feed_settings="feed_settings"></feed-media>

      <!-- feed external url -->
      <external-url v-if="feed.entities && feed.entities.urls && feed.entities.urls.length" :external_urls="feed.entities.urls"></external-url>
    </div>
</template>

<script type="text/babel">
    import tweet from './_tweet';
    import externalUrl from './_externalUrl.vue';
    import feedMedia from './_feedMedia.vue';
    export default {
        name: 'wpsr_twitter_content',
        props: ['feed', 'type', 'feed_settings', 'lastFeed'],
        components:{
          externalUrl,
          feedMedia,
          tweet,
        },
        data() {
            return {
                className : '',
                showMedia : true,
                Feeds : [],
            }
        },
        methods: {
            get_media_class(media, settings) {
              let media_class = "wpsr-feed-" + media.type;
              if(media.type === 'animated_gif' && settings.advance_settings.show_tweet_gif === 'true') return media_class;
              if(media.type === 'video' &&  settings.advance_settings.show_tweet_video === 'true') return media_class;
              if(media.type === 'photo' &&  settings.advance_settings.show_tweet_image === 'true') return media_class;
              return '';
            },

            activateTwitterCard(feed, settings){
              if( settings.advance_settings.show_twitter_card === 'true' ){
                let url = '';
                if( (feed.entities && feed.entities.urls && feed.entities.urls.length && feed.entities.urls[0].expanded_url ) ){
                  let twitter_card_url = feed.entities.urls[0].expanded_url;
                  let ssl_only = twitter_card_url.replace('http:', 'https:');
                  let has_string = jQuery('*:contains("https://bit.ly")');
                  url = twitter_card_url.replace('&', '038');
                }
                if( url ) {
                    return url;
                }else {
                    return false;
                }
              }else {
                  return false;
              }
            },

            createFeed(el, checks) {
                let feed = {
                  tcLinksToCheck: el.find('.wpsr-has-card-url').length,
                  element: el,
                  checksRemaining: checks
                };
                return feed;
            },

            tweetLinkRetriever(el, index){
              let urls = [];
              el.find('.wpsr-has-card-url').each(function () {
                //urls.push($(this).attr('data-cardurl'));
                if (!jQuery(this).hasClass('wpsr-tc-active')) {
                  urls.push({url: jQuery(this).attr('data-cardurl'), id:jQuery(this).attr('data-id')});
                }
              });
              return urls;
            },

            cardGenerator(index){
              let twitterCardUrls = this.tweetLinkRetriever(this.Feeds[index].element, index);
              this.Feeds[index].checksRemaining--;
              if (twitterCardUrls && twitterCardUrls.length > 0) {
                this.$post({
                  action: 'wpsocial_review_admin_ajax_feed',
                  route: 'save_twitter_cards',
                  urls: twitterCardUrls,
                })
                  .then(data => {
                    if (data.substring(0, 1) !== '<' && data.substring(0, 200).indexOf('<meta') === -1) {
                        let urlObject = jQuery.parseJSON(data);
                        if (urlObject && urlObject.hasOwnProperty('error')) {

                        } else {
                            this.addTwitterCards(urlObject, index);
                        }
                    }
                  })
                  .fail(error => {
                  })
                  .always(() => {

                  });
              }
            },

            //twitter card
            addTwitterCards(tcObj, index) {

            let $checkLink = this.Feeds[index].element.find('.wpsr-has-card-url');

            $checkLink.each(function () {
              let $self = jQuery(this),
                link = $self.attr('data-cardurl'),
                id = $self.attr('data-id');

              if (tcObj.hasOwnProperty(id)) {
                let youtube = (link.indexOf('youtube.com/watch') > -1) ? true : false,
                  youtu = (link.indexOf('youtu.be') > -1) ? true : false,
                  youtubeembed = (link.indexOf('youtube.com/embed') > -1) ? true : false,
                  vimeo = (link.indexOf('vimeo') > -1) ? true : false,
                  soundcloud = (link.indexOf('soundcloud.com') > -1) ? true : false,
                  active_card_video = false;

                if (( youtube || youtu || youtubeembed || vimeo || soundcloud) ) {
                  active_card_video = true;
                }

                let twitter_card = tcObj[id]['twitter_card'];

                let titleIndicator = ( twitter_card['twitter:title'] && twitter_card['twitter:title'].length >= 48 ) ? '...' : '';
                let desIndicator = (twitter_card['twitter:description'] && twitter_card['twitter:description'].length >= 100 ) ? '...' : '';

                if (( (twitter_card['twitter:card'] === 'summary_large_image' || twitter_card['twitter:card'] === 'summary')  ) || ( twitter_card['twitter:card'] === 'player' && active_card_video )) {

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
              jQuery(this).addClass('wpsr-tc-active');

              // remove the url from the tweet content
              if ($self.find('.wpsr-tweet-text').find('a[href="' + link + '"]').length) {
                $self.find('.wpsr-tweet-text').find('a.wpsr-link-tweet[href="' + link + '"]').hide();
              }
            });

            setTimeout(function(){
              let isfound = jQuery('.masonry').length;
              if(isfound) {
                let id = jQuery('.wpsr-twitter-masonry-activate');
                id.masonry('reload');
              }
            },1000)
          }
        },
    }
</script>