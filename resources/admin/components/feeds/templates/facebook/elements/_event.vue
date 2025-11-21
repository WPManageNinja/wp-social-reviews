<template>
  <div class="wpsr-fb-event_feed-wrapper">
      <div v-if="feed_settings.display_event_photo === 'true'" class="wpsr-fb-feed-image" :class="(image_settings.optimized_images === 'true' && isPlaceholder(feed.media_url)) ? 'wpsr-animated-background' : ''">
      <a :href="'https://www.facebook.com/events/'+feed.id" class="wpsr-feed-link"  target="_blank" rel="nofollow">
        <img v-if="image_settings.optimized_images !== 'true'" :src="(feed.cover && feed.cover.source) || (feed.media_url)" :alt="feed.name"/>
        <img v-else-if="!isPlaceholder(feed.media_url)" :src="feed.media_url" :alt="feed.name" class="wpsr-fb-post-img" :class="get_image_class(feed.media_url, image_settings)"/>
        <img v-else :src="feed.media_url" :alt="feed.name"/>
      </a>
    </div>
      <a :href="'https://www.facebook.com/events/'+feed.id" class="wpsr-feed-link" target="_blank" rel="nofollow">
    <div class="wpsr-fb-events-feed-info" >
      <span class="wpsr-fb-feed-time" v-if="feed_settings.display_date === 'true'">
         {{ wpsrDateFormat(feed.start_time, 'MMM DD YYYY h:mm A') }}
        <span v-if="feed.end_time"> -
         {{ wpsrDateFormat(feed.end_time, 'MMM DD YYYY h:mm A') }}
        </span>
      </span>
      <div class="wpsr-fb-feed-event-name" v-if="feed.name && feed_settings.display_event_name === 'true'">
        <h4>
          {{ feed.name }}
        </h4>
      </div>
      <span class="wpsr-fb-feed-event-place" v-if="!feed.is_online && feed.place && feed_settings.display_event_location === 'true'">
        {{ feed.place.name }} {{ feed.place.location ? feed.place.location.country : '' }}
      </span>
      <span class="wpsr-fb-feed-event-place" v-if="feed.is_online">
        Online
      </span>
      <span class="wpsr-fb-feed-event-count" v-if="feed_settings.display_event_interest === 'true'" >
        {{ shortNumberFormat( feed.interested_count) }} {{$t('Interested')}}.
        {{ shortNumberFormat( feed.attending_count) }}  {{feed.end_time ? $t('Going') : $t('Went') }}
      </span>
    </div>
      </a>
  </div>
</template>

<script>
import { commonMixin } from '../../../../../mixins/commonMixin';
export default {
  mixins: [commonMixin],
  props: ['feed', 'feed_type', 'feed_settings', 'image_settings'],
}
</script>