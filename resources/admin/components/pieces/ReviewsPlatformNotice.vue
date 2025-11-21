<template>
  <div>
    <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20 wpsr-mt-20 wpsr-ml-10 wpsr-mr-10" v-if="platFormName === 'amazon'">
      <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
      <p>
        <strong style="display:block;">Notice: Amazon Reviews Temporarily Unavailable</strong>

        We’re currently working on a solution to bring Amazon reviews back soon.
        In the meantime, you can use our <router-link to="/tools/import">Import Reviews</router-link> or <router-link to="/custom-sources">Custom Sources</router-link> feature to display your Amazon reviews without interruption.
      </p>
    </div>

    <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20" v-if="platFormName === 'airbnb'">
        <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
        <p>
          <strong style="display:block;">Notice: API Key, API Secret Key Configuration Required</strong>
          Before pasting your business URL, you need to configure a API Key, API Secret Key
          using filter in your website's theme or plugin settings. This step is
          essential for the system to properly access and display your reviews.
          <a
              href="https://wpsocialninja.com/docs/airbnb-configuration-social-reviews-wp-social-ninja/"
              target="_blank"
          >Setup Guide</a
          >
        </p>
    </div>

    <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20" v-if="platFormName === 'booking.com'">
      <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
      <p>
        <strong class="wpsr-d-flex">Notice: Cookie Configuration Required</strong>
        Before pasting your business URL, you need to configure a cookie
        filter in your website's theme or plugin settings. This step is
        essential for the system to properly access and display your reviews.
        <a class="wpsr_simple_btn" href="https://wpsocialninja.com/docs/booking-com-configuration-social-reviews-wp-social-ninja/" target="_blank">Setup Guide</a>
      </p>
    </div>
  </div>
</template>
<script type="text/babel">
import InfoIcon from "./icons/InfoIcon.vue";

export default {
  name: "ReviewsPlatformNotice",
  components: {InfoIcon},
  props: ["platFormName"],
  computed: {
    adminReviewsUrl() {
      return window.location.origin + '/wp-admin/admin.php?page=wpsocialninja.php#/reviews';
    },
    adminExportUrl() {
      return window.location.origin + '/wp-admin/admin.php?page=wpsocialninja.php#/tools/import';
    }
  }
};
</script>
