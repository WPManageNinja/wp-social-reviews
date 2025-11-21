<template>
  <div>
    <ReviewsPlatformNotice :platFormName="platFormName" />
  <div>
    <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20" v-if="platFormName === appVars.tp_slug">
      <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
      <p>
        <strong>Note</strong>
        Before displaying the {{appVars.tp_slug}} reviews, please ensure that you have obtained the necessary permission to showcase reviews on your website.
        Take a moment to review their privacy policy for a better understanding of this requirement.
      </p>
    </div>

    <div class="wpsr-user-accounts-heading" :class="platFormName === 'google' || platFormName === 'facebook' ? 'wpsr-jc-between' : ''">

      <h4 v-if="Object.keys(reviewsinfo).length && platFormName !== 'facebook' && platFormName !== 'woocommerce' && platFormName !== 'google'">{{$t('Your Connected Business Accounts')}}</h4>

      <h4 v-if="Object.keys(reviewsinfo).length && platFormName === 'woocommerce'">{{$t('Your Connected Products')}}</h4>

      <h4 v-if="platFormName === 'google' && Object.keys(locationList).length && !Object.keys(reviewsinfo).length">
        {{$t('Your Location List')}}
      </h4>
      <h4 v-if="platFormName === 'google' && Object.keys(reviewsinfo).length">
        {{$t('Your Connected Business Accounts')}}
      </h4>

      <h4 v-if="platFormName === 'facebook' && Object.keys(pageList).length && !Object.keys(reviewsinfo).length">
        {{ $t('Your Authorized Businesses List') }}
      </h4>
      <h4 v-if="platFormName === 'facebook' && Object.keys(reviewsinfo).length">
        {{ $t('Manage Your Facebook Pages') }}
      </h4>

      <el-button
          v-if="( (platFormName === 'google' && Object.keys(locationList).length) || (platFormName === 'facebook' && Object.keys(pageList).length))"
          v-model="addNewBusiness"
          :class="{'display-form': !!addNewBusiness}"
          class="wpsr_simple_btn"
          type="success"
          @click.prevent="addBusiness">
        + {{ Object.keys(reviewsinfo).length ? $t('Add More Business') : $t('Add Business') }} {{ !has_pro ? $t(' (Pro)') : ''}}
      </el-button>
    </div>

    <div v-if="Object.keys(reviewsinfo).length" :class="Object.keys(reviewsinfo).length > 3 ? 'wpsr-scrollbar' : ''">
      <div class="wpsr-connected-accounts">
        <div v-for="info in reviewsinfo">
        <div class="wpsr-user-profile">
          <div class="wpsr-user-profile-inner">
          <div class="wpsr-user-details">
            <div class="wpsr-user-details-inner">
              <div class="wpsr-user-details-info wpsr-flex-column wpsr-flex-align-left">
                <p v-if="info.name">
                  {{ $trimWords(info.name, 7, true) }} {{ platFormName === 'airbnb' ? '('+ info.place_id +')' : ''}}
                  <span v-if="info.average_rating && platFormName === 'booking.com'"  class="booking_dot_com">
                   {{ parseFloat(info.average_rating).toFixed(1) }}
                  </span>
                </p>
                <p v-else-if="info.url">{{ info.url }}</p>
                <p class="wpsr-rating" v-if="info.average_rating && platFormName !== 'booking.com'">
                  <template style="display: block;">
                    <div class="wpsr-rating" v-html="ratingIcon(Number(info.average_rating).toFixed(1))"></div>
                  </template>
                </p>
              </div>
              <div class="wpsr-user-actions">
                <el-tooltip class="item" effect="dark" content="Manually Sync Reviews" placement="top-start">
                  <el-button @click="manuallySyncReviews(platFormName, info)" class="wpsr_default_btn wpsr_icon_btn wpsr_mini">
                    <el-icon ><Refresh /></el-icon>
                  </el-button>
                </el-tooltip>
                <remove v-if="!info.is_imported" @confirm="clearVerificationCredentials(platFormName, info.place_id)" :platform="platFormName"></remove>
              </div>
            </div>
            <div v-if="info.has_critical_error" class="wpsr-account-error-status-wrapper">
              <div class="wpsr-account-error-status">
                <span>
                  <el-icon><InfoFilled /></el-icon>{{ $t('Source Invalid') }}
                </span>
                <a href="#" @click="loginWithFacebook">Reconnect</a>
              </div>
            </div>
            <div v-else-if="info.encryption_error" class="wpsr-account-error-status-wrapper">
              <div class="wpsr-account-error-status">
                 <span>
                    <el-icon><InfoFilled /></el-icon>{{ $t('Encryption Error') }}
                 </span>
                 <a href="#" @click.prevent="loginWithFacebook">Reconnect</a>
              </div>
            </div>
          </div>

          </div>
        </div>
      </div>
      </div>
    </div>
  </div>
  </div>

  <el-dialog
      v-model="showUpgradeModal"
      width="45%"
      class="wpsr-modal"
  >
    <UpgradeToProModal
        v-if="showUpgradeModal"
        :platform="selectedPlatform"
        :feature_type="selectedFeatureType"
        @close="showUpgradeModal = false"
    />
  </el-dialog>
</template>
<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import ReviewsPlatformNotice from '../../../pieces/ReviewsPlatformNotice';
import InfoIcon from "../../../pieces/icons/InfoIcon.vue";
import TrashcanIcon from "../../../pieces/icons/TrashcanIcon.vue";
import UpgradeToProModal from  '../../advertise/UpgradeToProModal.vue';

export default {
  name: 'MultipleBusinessInfo',
  props: ['reviewsinfo', 'platFormName', 'verifiedPlatform', 'verifyPlatform', 'isDownloadablePlatform', 'pageList', 'locationList'],
  emits: ['sync-loader', 'add-new-template', 'clear-verification-credentials', 'login-with-facebook', 'toggle-add-business'],
  components: {
    TrashcanIcon,
    InfoIcon,
    Remove,
    ReviewsPlatformNotice,
    UpgradeToProModal
  },
  data(){
    return{
      addNewBusiness: false,
      showUpgradeModal: false,
      selectedPlatform: 'general',
      selectedFeatureType: 'default'
    }
  },
  methods:{
    showProModal() {
      if (this.has_pro) return;

      this.selectedPlatform = 'general';
      this.selectedFeatureType = 'default';
      this.showUpgradeModal = true;
    },
    addBusiness(){
      if(!this.has_pro) {
        this.showProModal();
        this.addNewBusiness = false;
      } else {
        this.$emit('toggle-add-business', this.addNewBusiness);
      }
    },
    clearVerificationCredentials(platFormName, sourceId){
      this.$emit('clear-verification-credentials', sourceId);
    },
    loginWithFacebook(){
      this.$emit('login-with-facebook');
    },
    manuallySyncReviews(platFormName, credentials) {
      this.$emit('sync-loader', true);
      this.$post('platforms/reviews/configs/manually-sync-reviews',{
        platform: platFormName,
        credentials: credentials
      })
          .then(response => {
            if (response) {
              this.handleSuccess(response.data.message);
            }
          })
          .catch( (errors) => {
            this.handleError(errors);
          })
          .always(() => {
            this.$emit('sync-loader', false);
          })
    }
  }
}
</script>