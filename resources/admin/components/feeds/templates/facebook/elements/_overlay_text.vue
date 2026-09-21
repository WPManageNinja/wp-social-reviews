<template>
  <div class="wpsr-fb-overlay-text">
    <div class="wpsr-fb-overlay-text-inner">
      <div class="wpsr-fb-overlay-platform-icon" v-if="feed_settings.display_platform_icon === 'true'">
        <i class="icon-facebook-square"></i>
      </div>

      <div class="wpsr-fb-overlay-text-body">
        <Description :feed="feed" :feed_settings="feed_settings"/>
        <URLSummaryCard
            v-if="show_summary !== false && feed.status_type === 'shared_story' && feed.attachments && feed.attachments.data"
            :feed="feed"
            :feed_settings="feed_settings"
            :image_settings="image_settings"
        />
      </div>

      <div class="wpsr-fb-overlay-text-author">
        <a target="_blank" rel="nofollow noopener" :href="feed.from.link" class="wpsr-fb-feed-author-name wpsr-fb-overlay-username" v-if="hasAuthor && feed_settings.display_author_name === 'true'">
          <span>{{ feed.from.name }}</span>
        </a>
        <Date :feed="feed" :feed_settings="feed_settings"/>
        <div class="wpsr-fb-overlay-avatar-wrapper" v-if="hasAuthor && avatarUrl && feed_settings.display_author_photo === 'true'">
          <a target="_blank" :href="feed.from.link" rel="nofollow noopener">
            <img class="wpsr-fb-overlay-avatar" :src="avatarUrl" :alt="feed.from.name" width="38" height="38">
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Date from "./_date";
import Description from "./_description";
import URLSummaryCard from "./_url_summary_card";

export default {
  name: "facebook_overlay_text",
  props: ['feed', 'feed_settings', 'image_settings', 'show_summary'],
  components: {Date, Description, URLSummaryCard},
  computed: {
    hasAuthor() {
      return !!(this.feed && this.feed.from && Object.entries(this.feed.from).length);
    },
    // Mirrors $wpsr_author_avatar in template4.php: the local copy stands on its own, so
    // gating on from.picture would hide an avatar the frontend still draws.
    avatarUrl() {
      if (!this.feed) {
        return '';
      }
      return this.feed.user_avatar
          || (this.feed.from && this.feed.from.picture && this.feed.from.picture.data && this.feed.from.picture.data.url)
          || '';
    }
  }
}
</script>
