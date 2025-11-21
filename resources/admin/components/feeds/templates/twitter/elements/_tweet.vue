<template>
    <p class="wpsr-tweet-text" v-html="twitterTextValidate(feed, feed.text, feed.entities && feed.entities.urls ? feed.entities.urls : [])" v-if="feed_settings && feed_settings.advance_settings.tweet_text === 'true'">
    </p>

</template>

<script type="text/babel">
import {generateUserPermalink} from "../helper";

export default {
  name: 'tweet',
  props: ['feed', 'feed_settings'],
  methods:{
    // formatTweetText
    twitterTextValidate(feed, content, urls) {
      let tweet;
      let tweetEntities = [];

      if (urls) {
        for (let i = 0; i < urls.length; i++) {
          let expanded_url = urls[i].expanded_url;
          let display_url = urls[i].display_url;
          if (expanded_url && display_url) {
            let twitterUrl = expanded_url.includes('twitter');
            if (!twitterUrl) {
              tweetEntities[i] = {
                'curText': content.substring(urls[i].start, urls[i].end + 4),
                'newText': '<a href="' + expanded_url + '" target="_blank">' + display_url + '</a> ',
              };
            }
          }
        }

        for (let i = 0; i < tweetEntities.length; i++) {
          let entity = tweetEntities[i];
          if(entity) {
            content = content.replace(entity['curText'], entity['newText']);
          }
        }

        for (let i = 0; i < urls.length; i++) {
          let expanded_url = urls[i].expanded_url;
          let display_url = urls[i].display_url;
          if (expanded_url && display_url) {
            // remove t.com urls from string
            let twitterUrl = expanded_url.includes('twitter');
            if (twitterUrl) {
              let default_url = urls[i].url;
              if (content.includes(default_url)) {
                content = content.replace(default_url, '');
              }
            }
          }
        }
      }

      let hashTagUrl = 'https://twitter.com/hashtag/';
      tweet = this.escapeHtml(tweet);
      tweet = generateUserPermalink(content);
      tweet = this.generateURLsFromHashTag(tweet, hashTagUrl);
      return this.nl2br(tweet);
    },
  }
}
</script>