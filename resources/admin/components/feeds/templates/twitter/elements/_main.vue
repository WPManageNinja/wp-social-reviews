<template>
    <div>
        <div v-if="feed.type === 'retweeted' && feed.retweet_user && feed.retweet_user.username" class="wpsr-retweeted">
            <a target="_blank" :href="'https://twitter.com/'+feed.retweet_user.username+'/status/'+feed.retweet_user.id" >
                <svg viewBox="0 0 24 24" class="r-1re7ezh r-4qtqp9 r-yyyyoo r-1xvli5t r-dnmrzs r-bnwqim r-1plcrui r-lrvibr r-1xzupcd"><g><path d="M23.615 15.477c-.47-.47-1.23-.47-1.697 0l-1.326 1.326V7.4c0-2.178-1.772-3.95-3.95-3.95h-5.2c-.663 0-1.2.538-1.2 1.2s.537 1.2 1.2 1.2h5.2c.854 0 1.55.695 1.55 1.55v9.403l-1.326-1.326c-.47-.47-1.23-.47-1.697 0s-.47 1.23 0 1.697l3.374 3.375c.234.233.542.35.85.35s.613-.116.848-.35l3.375-3.376c.467-.47.467-1.23-.002-1.697zM12.562 18.5h-5.2c-.854 0-1.55-.695-1.55-1.55V7.547l1.326 1.326c.234.235.542.352.848.352s.614-.117.85-.352c.468-.47.468-1.23 0-1.697L5.46 3.8c-.47-.468-1.23-.468-1.697 0L.388 7.177c-.47.47-.47 1.23 0 1.697s1.23.47 1.697 0L3.41 7.547v9.403c0 2.178 1.773 3.95 3.95 3.95h5.2c.664 0 1.2-.538 1.2-1.2s-.535-1.2-1.198-1.2z"></path></g></svg>
            </a>
            <a target="_blank" :href="'https://twitter.com/'+feed.retweet_user.username" class="wpsr-tweet-author-name"><span>{{ feed.retweet_user.name }} {{$t('Retweeted')}}</span></a>
        </div>

        <div class="wpsr-tweet-author-info">
            <author-avatar
                v-if="feed.type === 'quoted' || allFeedConfigs.feed_settings.layout_type !== 'standard' "
                :feed="feed"
                :allFeedConfigs="allFeedConfigs"
            >
            </author-avatar>

            <AuthorLinks
                :feed="feed"
                :showAuthor="allFeedConfigs.feed_settings.advance_settings.author_name"
                :showDate="allFeedConfigs.feed_settings.advance_settings.date"
                :showUserName="allFeedConfigs.feed_settings.advance_settings.user_name"
            />

            <div class="wpsr-tweet-logo" v-if="allFeedConfigs.feed_settings.advance_settings.twitter_logo ==='true' && feed.user && feed.user.username">
                <a target="_blank" :href="'https://twitter.com/' + feed.user.username + '/status/' + feed.id">
                  <svg width="14" height="14" viewBox="0 0 14 12" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.6424 0.269775H12.5847L8.34131 5.12425L13.3333 11.7301H9.42461L6.36319 7.72369L2.86023 11.7301H0.916753L5.45545 6.53769L0.666626 0.269775H4.67454L7.44179 3.93179L10.6424 0.269775ZM9.96068 10.5664H11.0369L4.08973 1.37232H2.9348L9.96068 10.5664Z"></path>
                  </svg>
                </a>
            </div>
        </div>

        <Content 
            :feed="feed"
            :feed_settings="allFeedConfigs.feed_settings"
            :lastFeed="lastFeed"
        />
    </div>
</template>

<script type="text/babel">
import AuthorLinks from './_authorLinks';
import Content from './_content.vue';
import Actions from './_actions.vue';
import authorAvatar from './_avatar';

export default {
    props:['feed','allFeedConfigs','type','lastFeed'],
    components:{
        AuthorLinks,
        Content,
        Actions,
        authorAvatar
    },
}
</script>