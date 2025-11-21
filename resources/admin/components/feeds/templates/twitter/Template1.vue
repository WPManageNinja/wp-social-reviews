<template>
    <div class="wpsr-twitter-tweets-wrapper wpsr-twitter-card-wrapper" :class="[feedConfigs && feedConfigs.dynamic && feedConfigs.feed_settings && feedConfigs.feed_settings.layout_type ? 'wpsr-twitter-'+feedConfigs.feed_settings.layout_type : '', ' wpsr-tw-feed-template-'+this.$route.params.template_id]" v-if="feedConfigs && feedConfigs.dynamic">
        <div v-if="feedConfigs.dynamic.errors">
            {{ feedConfigs.dynamic.errors[0] }}
        </div>
        <div v-else>
            <Header
                v-if="has_pro && feedConfigs.dynamic.header && feedConfigs.feed_settings && feedConfigs.feed_settings.additional_settings && feedConfigs.feed_settings.additional_settings.feed_type !== 'hashtag'"
                :header="feedConfigs.dynamic.header"
                :header_settings="feedConfigs.feed_settings.header_settings"
                :settings="feedConfigs.feed_settings.follow_button_settings"
            />

            <div class="wpsr-twitter-all-tweets">
                <template v-if="feedConfigs && feedConfigs.dynamic && feedConfigs.dynamic.items">
                    <div class="wpsr-twitter-tweet" 
                         v-for="(feed, index) in feedConfigs.dynamic.items" 
                         :key="index"
                         >
                        <span v-if="feed && ((feed.type !== 'retweeted' && feedConfigs.feed_settings.advance_settings.show_retweeted_tweet === 'false') || 
                               (feedConfigs.feed_settings.advance_settings.show_retweeted_tweet === 'true'))">
                            <author-avatar :feed="feed" :allFeedConfigs="feedConfigs"></author-avatar>
                            <div class="wpsr-twitter-author-tweet">
                                <!-- for normal feed / retweet feed -->
                                <main-feed @click="setCurrentFeed(feed,index)" :feed="feed" :allFeedConfigs="feedConfigs" :type="'non-quoted'" :lastFeed="feedConfigs.dynamic.items && feedConfigs.dynamic.items.length===index+1"></main-feed>
                                <!-- for quoted tweet -->
                                <span v-if="feed && feed.extra_tweets && feed.extra_tweets.length > 0">
                                    <div v-for="extra_tweet in feed.extra_tweets" :key="extra_tweet && extra_tweet.id">
                                        <div v-if="feedConfigs.feed_settings.advance_settings.show_quoted_tweet === 'true' && extra_tweet && extra_tweet.type === 'quoted'" class="wpsr-quoted-tweet">
                                            <main-feed
                                                :feed="extra_tweet" 
                                                :allFeedConfigs="feedConfigs" 
                                                :type="'quoted'"
                                                :lastFeed="feedConfigs.dynamic.items && feedConfigs.dynamic.items.length === index+1"
                                                @click="setCurrentFeed(extra_tweet, index)"
                                            >
                                            </main-feed>
                                        </div>
                                    </div>
                                </span>
                                

                                <Actions
                                    v-if="feed"
                                    :feed="feed"
                                    :actionTarget="feedConfigs.feed_settings.advance_settings.tweet_action_target"
                                    :showLike="feedConfigs.feed_settings.advance_settings.show_like_action"
                                    :showReply="feedConfigs.feed_settings.advance_settings.show_reply_action"
                                    :showRetweet="feedConfigs.feed_settings.advance_settings.show_retweet_action"
                                />
                            </div>
                        </span>
                    </div>
                </template>
            </div>

            <Footer 
                v-if="has_pro && feedConfigs.header && feedConfigs.feed_settings && feedConfigs.feed_settings.additional_settings && feedConfigs.feed_settings.additional_settings.feed_type !== 'hashtag'"
                :footer="feedConfigs.header"
                :settings="feedConfigs.feed_settings" 
            />

            <Popup
                v-if="selectedFeed"
                :feed="selectedFeed && selectedFeed.retweeted_status ? selectedFeed.retweeted_status : selectedFeed"
                :feedLength="feedConfigs.dynamic.items && feedConfigs.dynamic.items.length"
                :currentIndex="currentIndex"
                :settings="feedConfigs.feed_settings"
                @on-previous="getPreviousFeed"
                @on-next="getNextFeed"
            />
        </div>
    </div>
</template>

<script>
import Footer from './elements/_footer';
import Header from './elements/_header';
import AuthorLinks from './elements/_authorLinks.vue';
import Content from './elements/_content.vue';
import Actions from './elements/_actions.vue';
import MainFeed from './elements/_main';
import AuthorAvatar from './elements/_avatar';
import Popup from './elements/_popup';

export default {
    inheritAttrs: false,
    props: ['feedConfigs'],
    data(){
        return {
            selectedFeed: [],
            currentIndex: null,
        }
    },
    components:{
        MainFeed,
        Header,
        AuthorLinks,
        Content,
        Actions,
        AuthorAvatar,
        Popup,
        Footer
    },
    methods: {
        tweetType(feed, feedConfigs){
            if(feed.retweeted_status && feedConfigs.feed_settings.advance_settings.show_retweeted_tweet === 'true'){
                return false;
            }
        },

        getPreviousFeed() {
            if(this.currentIndex !== null) {
                let feed = this.findNextPrevFeed('decrement');
                this.selectedFeed = feed;
                if(!feed) {
                    jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
                }
            }
        },

        getNextFeed() {
            if(this.currentIndex !== null) {
                let feed = this.findNextPrevFeed('increment');
                this.selectedFeed = feed;
                if(!feed) {
                    jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
                }
            }
        },

        //findNextPrevFeed
        findNextPrevFeed(unary) {
          let feeds = this.feedConfigs.dynamic.items, feed, totalFeeds = feeds ? feeds.length : 0;
          if(!feeds || !totalFeeds) return;
          while(1) {
            if (unary == 'increment') {
              this.currentIndex++;
            } else {
              this.currentIndex--;
            }

            feed = feeds[this.currentIndex];

            if(this.currentIndex < 0 || this.currentIndex >= totalFeeds || !feed) break;

            if(feed && feed.media && feed.media.length) {
                break;
            }
          }

          return feed;
        },

        setCurrentFeed( feed,index ){
            this.selectedFeed = [];
            this.selectedFeed = feed;
            this.currentIndex = index;
        },

        twitterPostDisplayMode(e, feed, playMode, index) {
            if( playMode === 'popup' ){
                jQuery('.wpsr-feed-popup-overlay').toggleClass('wpsr-feed-popup-open');
                jQuery(".wpsr-ig-playmode").removeAttr("href");
            } else if( playMode === 'instagram'){
                let postSelector = jQuery('.wpsr-ig-playmode');
                jQuery(postSelector).attr("target", "_blank");
                jQuery(postSelector).attr("href", feed.permalink);
            }
        }
    },
}
</script>