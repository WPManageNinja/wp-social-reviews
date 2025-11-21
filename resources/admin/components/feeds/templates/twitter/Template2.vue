<template>
  <div
      class="wpsr-container wpsr-twitter-feed-wrapper wpsr-twitter-card-wrapper"
      :class="[
            'wpsr-column-gap-'+feedConfigs.feed_settings.column_gaps,
            feedConfigs.feed_settings.layout_type === 'carousel' ? 'wpsr-twitter-carousel' : '',
            feedConfigs.feed_settings.advance_settings.equal_height === 'true' ? 'wpsr-twitter-equal-height' : '',
            ' wpsr-tw-feed-template-'+this.$route.params.template_id
        ]"
      v-if="feedConfigs.dynamic"
  >
    <div v-if="feedConfigs.dynamic.errors">
      {{ feedConfigs.dynamic.errors[0] }}
    </div>
    <div v-else class="wpsr-twitter-slider-activate wpsr-twitter-masonry-activate">
      <Header
          v-if="has_pro && feedConfigs.dynamic.header && feedConfigs.feed_settings.additional_settings.feed_type !== 'hashtag'"
          :header="feedConfigs.dynamic.header"
          :header_settings="feedConfigs.feed_settings.header_settings"
          :settings="feedConfigs.feed_settings.follow_button_settings"
      />

      <div :class="feedConfigs.feed_settings.layout_type === 'carousel' ? 'wpsr-twitter-wrapper-inner' : ''">
        <div :class="[feedConfigs.feed_settings.layout_type === 'carousel' ? 'swiper-container' : '']">
          <div class="wpsr-twitter-all-tweets" :class="[feedConfigs.feed_settings.layout_type === 'carousel' ? 'swiper-wrapper' : 'wpsr-row']">
            <div  :class="feedConfigs.feed_settings.layout_type !== 'carousel' ? 'wpsr-col-'+feedConfigs.feed_settings.responsive_column_number.desktop + ' wpsr-col-sm-'+feedConfigs.feed_settings.responsive_column_number.tablet + ' wpsr-col-xs-'+feedConfigs.feed_settings.responsive_column_number.mobile : 'swiper-slide'" v-for="(feed, index) in items" :key="index">
              <div class="wpsr-twitter-tweet">
                <div class="wpsr-twitter-author-tweet">
                  <!-- for normal feed / retweet feed -->
                  <main-feed
                      :feed="feed"
                      :allFeedConfigs="feedConfigs"
                      :type="'non-quoted'"
                      :lastFeed="feedConfigs.dynamic.length===index+1"
                      @click="setCurrentFeed(feed,index)"
                  >
                  </main-feed>
                  <!-- for quoted tweet -->
  <!--                  v-if="feedConfigs.feed_settings.advance_settings.show_quoted_tweet === 'true' && ( feed.is_quote_status && feed.quoted_status ) || (feed.retweeted_status && feed.retweeted_status.is_quote_status)"-->
                  <div class="wpsr-quoted-tweet" v-for="extra_tweet in feed.extra_tweets">
                    <main-feed
                        :feed="extra_tweet"
                        :allFeedConfigs="feedConfigs"
                        :type="'quoted'"
                        :lastFeed="feedConfigs.dynamic.length === index+1"
                        @click="setCurrentFeed(extra_tweet, index)"
                    >
                    </main-feed>
                  </div>

                  <Actions
                      :feed="feed"
                      :actionTarget="feedConfigs.feed_settings.advance_settings.tweet_action_target"
                      :showLike="feedConfigs.feed_settings.advance_settings.show_like_action"
                      :showReply="feedConfigs.feed_settings.advance_settings.show_reply_action"
                      :showRetweet="feedConfigs.feed_settings.advance_settings.show_retweet_action"
                  />
                </div>
              </div>
            </div>
            
          </div>
        </div>

        <swiper-navs :options="feedConfigs.feed_settings.carousel_settings" v-if="feedConfigs.feed_settings.layout_type === 'carousel'"></swiper-navs>
      </div>

      <Footer
          v-if="has_pro && feedConfigs.header && feedConfigs.feed_settings.additional_settings.feed_type !== 'hashtag'"
          :footer="feedConfigs.header"
          :settings="feedConfigs.feed_settings"
      />

      <Popup
          :feed="selectedFeed && selectedFeed.retweeted_status?selectedFeed.retweeted_status:selectedFeed"
          :feedLength="feedConfigs.dynamic.length"
          :currentIndex="currentIndex"
          :settings="feedConfigs.feed_settings"
          @on-previous="getPreviousFeed"
          @on-next="getNextFeed"
      />
    </div>
  </div>
</template>

<script type="text/babel">
import Footer from './elements/_footer';
import Header from './elements/_header';
import AuthorLinks from './elements/_authorLinks.vue';
import Content from './elements/_content.vue';
import Actions from './elements/_actions.vue';
import MainFeed from './elements/_main';
import AuthorAvatar from './elements/_avatar';
import Popup from './elements/_popup';
import SwiperNavs from "../../../core-ui/editor/SwiperNavs";

export default {
  inheritAttrs: false,
  props: ['feedConfigs'],
  data() {
    return {
      selectedFeed: [],
      currentIndex: null,
    }
  },

  computed: {
    items() {
      if (this.feedConfigs && this.feedConfigs.dynamic && Array.isArray(this.feedConfigs.dynamic.items)) {
        const showRetweets = this.feedConfigs.feed_settings.advance_settings.show_retweeted_tweet === 'true';
        return this.feedConfigs.dynamic.items.filter(item => {
          if (!item) {
            return false;
          }
          return showRetweets || !item.retweeted_status;
        });
      }
      return [];
    }
  },

  components: {
    MainFeed,
    Header,
    AuthorLinks,
    Content,
    Actions,
    AuthorAvatar,
    Popup,
    Footer,
    SwiperNavs
  },

  methods: {
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

    findNextPrevFeed(unary) {
      let feeds = this.items, feed, totalFeeds = feeds ? feeds.length : 0;
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

    setCurrentFeed(feed,index) {
      this.selectedFeed = [];
      this.selectedFeed = feed;
      this.currentIndex = index;
    }
  }
}
</script>