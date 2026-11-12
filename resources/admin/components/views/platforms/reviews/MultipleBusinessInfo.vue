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

    <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20" v-if="deprecationNotice">
      <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
      <p>
        <strong>{{$t('Notice')}}</strong>
        {{ deprecationNotice }}
      </p>
    </div>

    <div v-if="platFormName !== 'woocommerce' || hasWooCommerce" class="wpsr-user-accounts-heading" :class="platFormName === 'google' || platFormName === 'facebook' || platFormName === 'fluent-cart' || platFormName === 'woocommerce' ? 'wpsr-jc-between' : ''">

      <h4 v-if="Object.keys(reviewsinfo).length && platFormName !== 'facebook' && platFormName !== 'woocommerce' && platFormName !== 'fluent-cart' && platFormName !== 'google'">{{$t('Your Connected Business Accounts')}}</h4>

      <h4 v-if="(platFormName === 'woocommerce' && hasWooCommerce)">{{$t('Your Connected WooCommerce Products')}}</h4>
      <h4 v-if="(platFormName === 'fluent-cart' && hasFluentCart)">{{$t('Your Connected FluentCart Products')}}</h4>

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
          v-if="(platFormName === 'google') || (platFormName === 'facebook' && Object.keys(pageList).length)"
          v-model="addNewBusiness"
          :class="{'display-form': !!addNewBusiness}"
          class="wpsr_simple_btn"
          type="success"
          @click.prevent="addBusiness">
        + {{ Object.keys(reviewsinfo).length ? $t('Add More Business') : $t('Add Business') }} {{ !has_pro ? $t(' (Pro)') : ''}}
      </el-button>

      <el-button
          v-if="platFormName === 'fluent-cart' && hasUnconnectedProducts"
          class="wpsr_simple_btn"
          type="success"
          :loading="connectingAll"
          :disabled="!has_pro || connectingAll"
          @click.prevent="connectAllProducts">
        {{ connectingAll ? $t('Connecting...') : $t('Connect All Products') }} {{ !has_pro ? $t(' (Pro)') : ''}}
      </el-button>

      <el-button
          v-if="platFormName === 'woocommerce' && hasWooCommerce"
          class="wpsr_simple_btn"
          type="success"
          :loading="syncingWooReviews"
          :disabled="!has_pro || syncingWooReviews"
          @click.prevent="syncWooReviews">
        {{ syncingWooReviews ? $t('Syncing...') : $t('Sync All Reviews') }} {{ !has_pro ? $t(' (Pro)') : ''}}
      </el-button>
    </div>

    <div
        v-if="Object.keys(reviewsinfo).length"
        ref="scrollContainer"
        :class="Object.keys(reviewsinfo).length > 3 ? 'wpsr-scrollbar' : ''"
        @scroll="onScroll">
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
                <el-tooltip class="item" effect="dark" :content="platFormName === 'booking.com' || platFormName === appVars.tp_slug ? $t('Fetch the latest reviews now. Syncs are limited, and reviews also update automatically.') : $t('Fetch the latest reviews now')" placement="top-start">
                  <el-button @click="manuallySyncReviews(platFormName, info)" class="wpsr_default_btn wpsr_icon_btn wpsr_mini">
                    <el-icon ><Refresh /></el-icon>
                  </el-button>
                </el-tooltip>
                <remove v-if="!info.is_imported && platFormName !== 'fluent-cart'" @confirm="clearVerificationCredentials(platFormName, info.place_id)" :platform="platFormName"></remove>
                <el-tooltip v-if="platFormName === 'fluent-cart'" effect="dark" :content="$t('Disconnect product')" placement="top-start">
                  <remove
                      @confirm="clearVerificationCredentials(platFormName, info.place_id)"
                      :platform="platFormName"
                      :message="$t('Disconnect this product? Its reviews are kept and appear again if you reconnect it or a customer submits a new review. To delete reviews, go to All Reviews.')"
                  ></remove>
                </el-tooltip>
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
            <div v-else-if="getProductOverrideNotice(info)" class="wpsr-account-error-status-wrapper">
              <el-tooltip
                  effect="dark"
                  :content="getProductOverrideNotice(info).tooltip"
                  placement="top-start"
              >
                <div class="wpsr-account-error-status">
                  <span>
                    <el-icon><InfoFilled /></el-icon>{{ getProductOverrideNotice(info).label }}
                  </span>
                  <a v-if="getProductOverrideNotice(info).url" :href="getProductOverrideNotice(info).url" target="_blank">{{ $t('Review') }}</a>
                </div>
              </el-tooltip>
            </div>
          </div>

          </div>
        </div>
      </div>
      </div>
      <div v-if="loadingMore" class="wpsr-reviews-load-more">
        <el-icon class="is-loading"><Loading /></el-icon> {{ $t('Loading more...') }}
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
  props: ['reviewsinfo', 'platFormName', 'verifiedPlatform', 'verifyPlatform', 'isDownloadablePlatform', 'pageList', 'locationList', 'productsCount', 'hasFluentCart', 'hasWooCommerce', 'pagination', 'loadingMore', 'deprecationNotice'],
  emits: ['sync-loader', 'add-new-template', 'clear-verification-credentials', 'login-with-facebook', 'toggle-add-business', 'products-connected', 'load-more'],
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
      selectedFeatureType: 'default',
      connectingAll: false,
      syncingWooReviews: false,
      wooSyncProgressInterval: null
    }
  },
  computed: {
    hasUnconnectedProducts() {
      if (!this.productsCount) {
        return false;
      }
      const connectedCount = this.pagination ? this.pagination.total : Object.keys(this.reviewsinfo).length;
      return this.productsCount > connectedCount;
    }
  },
  methods:{
    onScroll(event) {
      if (!this.pagination || this.loadingMore || this.pagination.page >= this.pagination.total_pages) {
        return;
      }
      const el = event.target;
      const scrolledNearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 40;
      if (scrolledNearBottom) {
        this.$emit('load-more');
      }
    },
    getProductOverrideNotice(info) {
      if (this.platFormName === 'fluent-cart' && info.has_fluentcart_integration_override) {
        return {
          label: this.$t('Overridden in FluentCart'),
          tooltip: this.$t('A FluentCart-side integration is configured for this product and overrides the settings here.'),
          url: info.fluentcart_integration_url
        };
      }
      if (this.platFormName === 'woocommerce' && info.has_woocommerce_product_override) {
        return {
          label: this.$t('Has Product-Level Override'),
          tooltip: this.$t('This product has its own template selected under Product Data → WP Social Ninja, which overrides your global Main Review Hub setting.'),
          url: info.woocommerce_override_url
        };
      }
      return null;
    },
    showProModal() {
      if (this.has_pro) return;

      this.selectedPlatform = 'general';
      this.selectedFeatureType = 'default';
      this.showUpgradeModal = true;
    },
    addBusiness(){
      if( Object.keys(this.reviewsinfo).length === 0 ) {
        this.$emit('toggle-add-business', this.addNewBusiness);
        return;
      }
      if (!this.has_pro) {
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
    async connectAllProducts() {
      this.connectingAll = true;
      try {
        const response = await this.$post('pro/settings/fluent-cart/connect-all-products');
        if (response && response.success) {
          this.handleSuccess(response.message);
          this.$emit('products-connected', response.data);
        } else {
          this.handleError(response.message || 'Failed to connect products.');
        }
      } catch (error) {
        this.handleError(error.message || 'Failed to connect all products. Please try again.');
      } finally {
        this.connectingAll = false;
      }
    },
    async syncWooReviews() {
      this.syncingWooReviews = true;
      try {
        const response = await this.$post('pro/settings/woocommerce/import-reviews');
        if (response && response.success) {
          this.handleSuccess(response.message);
          this.startWooSyncPolling();
        } else {
          this.handleError(response.message || 'Failed to start sync.');
          this.syncingWooReviews = false;
        }
      } catch (error) {
        this.handleError(error.message || 'Failed to start sync. Please try again.');
        this.syncingWooReviews = false;
      }
    },
    // Keeps the button's loading state on for the whole background sync, not
    // just the initial trigger request — and picks up a sync already running
    // (e.g. started from Advanced Settings) when this modal is opened.
    async checkWooSyncProgress() {
      try {
        const response = await this.$get('pro/settings/woocommerce/import-progress');
        const isActive = !!(response && response.success && response.data && response.data.is_active);
        const wasActive = this.syncingWooReviews;
        this.syncingWooReviews = isActive;
        if (isActive) {
          this.startWooSyncPolling();
        } else {
          this.stopWooSyncPolling();
          if (wasActive) {
            // Sync just finished — refresh the connected-products list, same
            // event FluentCart's "Connect All Products" already uses.
            this.$emit('products-connected');
          }
        }
      } catch (error) {
        // Silent — non-critical background check.
      }
    },
    startWooSyncPolling() {
      if (this.wooSyncProgressInterval) {
        return;
      }
      this.checkWooSyncProgress();
      this.wooSyncProgressInterval = setInterval(() => {
        this.checkWooSyncProgress();
      }, 3000);
    },
    stopWooSyncPolling() {
      if (this.wooSyncProgressInterval) {
        clearInterval(this.wooSyncProgressInterval);
        this.wooSyncProgressInterval = null;
      }
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
  },
  watch: {
    platFormName: {
      immediate: true,
      handler(newVal) {
        if (newVal === 'woocommerce') {
          this.checkWooSyncProgress();
        } else {
          this.stopWooSyncPolling();
        }
      }
    }
  },
  beforeUnmount() {
    this.stopWooSyncPolling();
  }
}
</script>
