<template>
  <div class="wpsr-page-container wpsr-all-templates-page">
    <div v-if="!has_pro" class="wpsr-managers-upgrade-to-pro" style="height: 100vh;">
      <img :src="assets_url+'/images/promotion/native-forms.png'" alt="">
      <div class="wpsr-managers-upgrade-message">
        <h2>{{ $t('Collect Reviews Directly on Your Website') }}</h2>
        <p>{{ $t('Create review forms, collect submissions, and moderate reviews — all from your site.') }}</p>
        <div>
          <UpgradeToProButton/>
        </div>
      </div>
    </div>

    <div v-if="has_pro && hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])">
      <ReviewFormsList />
    </div>

    <PermissionsNotification v-if="has_pro && !hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])" />
  </div>
</template>

<script>
import UpgradeToProButton from '../advertise/UpgradeToProButton.vue';
import PermissionsNotification from '../advertise/PermissionsNotification.vue';
import ReviewFormsList from './ReviewFormsList.vue';

export default {
  name: 'ReviewFormsIndex',
  components: {
    UpgradeToProButton,
    PermissionsNotification,
    ReviewFormsList
  }
}
</script>
