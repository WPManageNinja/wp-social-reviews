<template>
  <div>
    <Photo
        :feed="feed"
        :feed_type="feed_type"
        :feed_settings="feed_settings"
        :image_settings="image_settings"
    />
    <div class="wpsr-fb-feed-video-info">
      <Title
          :feed="feed"
          :feed_settings="feed_settings"
      />
      <div class="wpsr-fb-feed-video-statistics" v-if="feed_settings.display_date === 'true'">
        <div class="wpsr-fb-feed-video-statistic-item">
          <Date
              :feed="feed"
              :feed_settings="feed_settings"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Photo from "./_photo";
import Title from "./_title";
import Date from "./_date";
export default {
  name: 'videos',
  props: ['feed', 'feed_type', 'feed_settings', 'image_settings'],
  components: {
    Photo,
    Title,
    Date
  },
  data(){
    return{}
  },
}
</script>