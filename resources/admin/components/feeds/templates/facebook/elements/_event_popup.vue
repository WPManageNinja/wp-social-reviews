<template>
  <div>
    <div class="wpsr-feed-popup-user-info wpsr-feed-popup-event-info">
      <div class="wpsr-feed-popup-group">
        <div class="wpsr-feed-popup-user-name" v-if="settings.popup_settings.display_username === 'true'">
          <a :href="'https://www.facebook.com/events/'+feed.id" target="_blank" :title="feed.name">{{ feed.name }}</a>
        </div>
      </div>
    </div>

    <div class="wpsr-feed-popup-event">
      <div class="wpsr-feed-popup-event-item-icon">
        <i class="dashicons dashicons-groups"></i>
      </div>
      <div class="wpsr-feed-popup-event-item">
        {{(feed.interested_count || 0) +( feed.attending_count || 0) + (feed.declined_count || 0) + (feed.noreply_count ||0)}}
        {{$t('People Responded')}}
      </div>
    </div>

    <div class="wpsr-feed-popup-event">
      <div class="wpsr-feed-popup-event-item-icon">
        <i class="dashicons dashicons-location"></i>
      </div>
      <div class="wpsr-feed-popup-event-item wpsr-popup-location" v-if="feed.is_online">
        {{$t('Online Event')}}
      </div>
      <div class="wpsr-feed-popup-event-item wpsr-popup-location" v-if="feed.place.name && !feed.is_online">
        {{ feed.place.name}}
      </div>
    </div>

    <div class="wpsr-feed-popup-event">
      <div class="wpsr-feed-popup-event-item-icon">
        <i class="dashicons dashicons-calendar-alt"></i>
      </div>
      <div class="wpsr-feed-popup-event-item wpsr-feed-popup-date" v-if="settings.popup_settings.display_date === 'true'" >
        {{ wpsrDateFormat(feed.start_time, 'MMM DD YYYY h:mm A') }}
        <span v-if="feed.end_time"> -
        {{ wpsrDateFormat(feed.end_time, 'MMM DD YYYY h:mm A') }}
      </span>
      </div>
    </div>

    <div class="wpsr-feed-popup-description-text" v-if="settings.popup_settings.display_caption === 'true'">
      <p v-if="feed.description" v-html="facebookTextValidate(feed.description)"></p>
    </div>

<!--    add chip-->
    <div class="wpsr-feed-popup-chip" v-if="feed.category">
      <div class="wpsr-feed-popup-chip-item">
        <span>{{ feed.category }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "event_popup",
  props: ['feed', 'settings', 'feed_type' , 'facebookTextValidate']
}
</script>