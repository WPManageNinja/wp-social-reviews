<template>
  <div>
    <div v-if="isNinjaTiktokFeedActive">
      <el-dialog class="wpsr-connection-modal" width="50%" title="TikTok Configuration" v-model="tiktok_modal">
        <template #header>
          <div class="wpsr-connection-modal-header">
            <h4 class="wpsr-dialog-title">TikTok Configuration</h4>
            <p v-if="!Object.keys(connected_source_list).length" class="wpsr-connection-modal-subheading">Connect your accounts</p>
            <p v-else class="wpsr-connection-modal-subheading">{{Object.keys(connected_source_list).length}} Account Connected.</p>
          </div>
        </template>

        <div class="wpsr-connection-modal-body">
        <div v-loading="loading" element-loading-text="Please wait.">

<!--          <div v-if="isButtonDisabled" class="wpsr-credentials-message wpsr-mb-20">-->
<!--            Please enable app settings and configure your TikTok app credentials <a :href="getSettingsLink" target="_blank"> from TikTok settings page</a>.-->
<!--            Once you've configured your credentials, you'll be able to connect your TikTok account.-->
<!--          </div>-->

          <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20" v-if="needsReconnect">
            <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
            <p>
              <strong>{{ $t('Notice') }}</strong>
              {{ $t('We have moved Custom Feed for TikTok to a new TikTok app. Click Reconnect next to each marked account and sign in with the same TikTok account, otherwise its feed will stop updating. The account then shows as a new account. Select it in each TikTok template that uses the old account, then remove the old account.') }}
            </p>
          </div>

          <!-- start connected pages -->
          <div v-if="isConnected && Object.keys(connected_source_list).length">
            <div class="wpsr-user-accounts-heading">
              <h4>{{ $t('Your Connected Accounts') }}</h4>
              <el-tooltip
                  :raw-content="true"
                  effect="dark"
                  placement="top-start"
                  :content="`Add or remove connected TikTok accounts`"
              >
                <el-icon ><InfoFilled /></el-icon>
              </el-tooltip>
            </div>

            <div :class="Object.keys(connected_source_list).length > 5 ? 'wpsr-scrollbar' : ''">
              <div class="wpsr-connected-accounts">
                <div v-for="(account,index) in connected_source_list" :key="index">
                <div class="wpsr-user-profile">
                  <div class="wpsr-user-profile-inner">
                    <div class="wpsr-user-profile-pic">
                      <img v-if="account.avatar_url" :src="account.avatar_url">
                    </div>
                    <div class="wpsr-user-details">
                      <div class="wpsr-user-details-inner">
                        <div class="wpsr-user-details-info">
                          <p>{{ account.display_name }}</p>
                        </div>
                        <div class="wpsr-user-actions">
                          <remove @confirm="clearVerificationConfigs(account.open_id)" platform="tiktok"></remove>
                        </div>
                      </div>
                      <div v-if="account.has_critical_error" class="wpsr-account-error-status-wrapper">
                          <div class="wpsr-account-error-status">
                            <span><el-icon><InfoFilled /></el-icon>{{ $t('Source Invalid') }}</span>
                            <a href="#" @click.prevent="oAuthLogin(account)">Reconnect</a>
                          </div>
                      </div>
                      <div v-else-if="account.encryption_error" class="wpsr-account-error-status-wrapper">
                        <div class="wpsr-account-error-status">
                            <span><el-icon><InfoFilled /></el-icon>{{ $t('Encryption Error') }}</span>
                            <a href="#" @click.prevent="oAuthLogin(account)">Reconnect</a>
                        </div>
                      </div>
                      <div v-else-if="account.needs_reconnect" class="wpsr-account-error-status-wrapper">
                        <div class="wpsr-account-error-status">
                            <span><el-icon><InfoFilled /></el-icon>{{ $t('Reconnect required') }}</span>
                            <a href="#" @click.prevent="oAuthLogin(account)">{{ $t('Reconnect') }}</a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              </div>
            </div>
          </div>
          <!-- end connected pages -->

          <el-button
              v-if="isConnected && Object.keys(connected_source_list).length"
              class="wpsr_primary_btn_outline wpsr-mt-15"
              type='success'
              style="width: 100%"
              :class="{'display-form': !reConnect}"
              @click="reConnectAccount"
          >
            <el-icon size="18"><Link /></el-icon>
            {{ $t('Connect More Accounts')}}
          </el-button>

          <div class="wpsr-accounts-connect-wrapper" :class="Object.keys(connected_source_list).length !== 0 ? 'wpsr-mt-20' : ''" v-if="reConnect || Object.keys(connected_source_list).length === 0">
            <div class="wpsr-connection-modal-input-wrapper">
              <div class="wpsr-connection-button-info-wrapper">
                <h3>{{ $t('Display Your TikTok Videos') }}</h3>
                <p>{{ $t('Connect your TikTok account to show your videos and profile info on your site.') }}</p>
              </div>
              <div class="wpsr-modal-btn-with-info">
                <el-button
                    class="wpsr-login-with-tiktok"
                    @click.prevent="oAuthLogin()"
                    size="default"
                    type="primary"
                    :loading="tiktokAuthLoading"
                    :disabled="tiktokAuthLoading"
                >
                  <img :src="assets_url+'/images/icon/icon-tiktok.png'"  alt="TikTok">
                  <p>{{ $t('Connect TikTok Account') }}</p>
                </el-button>
              </div>
              <p class="wpsr-connect-redirect-note">{{ $t('You will be redirected to WP Social Ninja, then to TikTok to approve access.') }}</p>
            </div>
          </div>

          <!-- start create template -->
          <div class="wpsr-make-template-btn-wrap" v-if="Object.keys(connected_source_list).length">
              <h3>Let’s create an awesome template to be added to your site!</h3>
              <p>Create a stunning, fully customizable template to perfectly match your website’s style.</p>
              <el-button class="wpsr_primary_btn" type="primary" size="default" @click.prevent="addNewTemplate">
                <CreateTemplateIcon/>
                {{ $t('Add New Template') }}
              </el-button>
          </div>
          <!-- end create template -->
        </div>
        </div>

        <div class="wpsr-connection-modal-footer">
          <div class="wpsr-docs-help">
            <p>Need Help?</p>
            <div class="wpsr-docs-help-links">
            <a href="https://wpsocialninja.com/docs/tiktok-feed-activation/" target="_blank">{{ $t('Read Documentation') }}</a>
            <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
            <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
            </div>
          </div>
        </div>

      </el-dialog>
    </div>

    <div v-else>
      <el-dialog class="wpsr-modal-addon" v-model="tiktok_modal">
        <AddOns
            :data="addOnsData"
            :addonStatus="addonStatus"
            :loading="loading"
            :getVerificationConfigs="getVerificationConfigs"
            :checkAddonStatus="checkAddonStatus"
            @isAddonActive="manageNinjaTiktokActive"
            @findEnabledPlatforms="$emit('findEnabledPlatforms')"
        />
      </el-dialog>
    </div>
    <AuthRedirectOverlay
        :visible="tiktokAuthLoading"
        :title="`${$t('Redirecting to')} ${authServiceHost}`"
        :subtitle="$t('You will be redirected to our authentication portal. This may take up to 5 seconds.')"
    />
  </div>
</template>

<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import CreateTemplateIcon from  '../../../pieces/icons/CreateTemplateIcon.vue';
import AuthRedirectOverlay from '../../../core-ui/AuthRedirectOverlay.vue';
import InfoIcon from '../../../pieces/icons/InfoIcon.vue';
import AddOns from "./AddOns";
import { wpsrSubmitAuthForm } from "../../../../utils";

export default {
  props:['tiktokModal', 'access_token'],
  emits: ['tiktok_settings', 'findEnabledPlatforms'],
  data(){
    return {
      loading: false,
      inner_loading: false,
      reConnect: false,
      isConnected: false,
      tiktokAuthLoading: false,
      admin_page_url: this.appVars.admin_page_url,
      config: {},
      connected_source_list: [],
      accessToken: this.access_token,
      addonStatus: 'Install and Activate The Plugin',
      isNinjaTiktokFeedActive: this.appVars.is_custom_feed_for_tiktok_activated,
      isNinjaTiktokFeedInstalled: this.appVars.is_custom_feed_for_tiktok_installed,
      addOnsData: {
        'platform' : 'tiktok',
        'slug' : 'custom-feed-for-tiktok',
        'name' : 'Custom Feed for TikTok',
        'logo' : '/images/icon/icon-tiktok.png',
        'description' : 'Integrate Custom Feed for TikTok Plugin with WP Social Ninja. Display engaging TikTok feeds that boost conversions. Easy setup, full customization.'
      },
    }
  },
  components:{
    AddOns,
    Remove,
    CreateTemplateIcon,
    AuthRedirectOverlay,
    InfoIcon
  },
  methods: {
    oAuthLogin(account = {}) {
      if (this.tiktokAuthLoading) return;
      if (account && account.encryption_error) {
        this.reConnect = true;
      }
      this.tiktokAuthLoading = true;

      try {
        const hash = window.location.hash;
        const hashMatch = hash.match(/edit-tiktok-template\/?(\d+)/);
        if (hashMatch && hashMatch[1]) {
          window.sessionStorage.setItem('wpsn_auth_return_route', JSON.stringify({
            name: 'edit-tiktok-template',
            templateId: hashMatch[1],
          }));
        }
      } catch (e) {}

      this.$post('platforms/feeds/configs/prepare', {
        platform:   'tiktok',
        provider:   'tiktok',
        flow:       'feed',
        return_url: this.admin_page_url,
      }).then(response => {
        if (response && response.data && response.data.action) {
          wpsrSubmitAuthForm(response.data.action, response.data.fields);
        } else {
          this.tiktokAuthLoading = false;
        }
      }).catch(error => {
        this.tiktokAuthLoading = false;
        this.handleError(error);
      });
    },

    getVerificationConfigs() {
      if (!this.isNinjaTiktokFeedActive){
        return;
      }
      this.loading = true;

      this.$get('platforms/feeds/configs', {
        platform: 'tiktok'
      }).then(response => {
        if(response.data) {
          if(response.data.connected_source_list){
            this.connected_source_list = response.data.connected_source_list;
          }

          if(response.data.app_settings){
            this.user_credentials = response.data.app_settings;
          }

          if(this.connected_source_list){
            this.isConnected = true;
          }
          this.$emit('findEnabledPlatforms');
        }
      }).catch(error => {
        this.handleError(error);
      }).always(() => {
        this.loading = false;
      });
    },

    clearVerificationConfigs(access_token) {
      this.$del('platforms/feeds/configs', {
        platform: 'tiktok',
        user_id: access_token
      }).then(response => {
        if(response.data) {
          this.getVerificationConfigs();
          this.accessToken = '';
          this.connected = false;
          this.handleSuccess(response.data.message);
        }
      }).catch(error => {
        this.handleError(error);
      }).always(() => {

      });
    },

    addNewTemplate(){
      this.$post('templates',{
        platform:'tiktok',
        type: 'feed'
      })
          .then(response => {
            if(response && response.template_id) {
              this.$router.push({
                name: 'edit-tiktok-template',
                params: {
                  template_id: response.template_id
                }
              })
            }
            this.tiktok_modal = false;
          }).catch(error => {
        this.handleError(error);
      }).always(() => {

      });
    },

    reConnectAccount(){
      this.reConnect = !this.reConnect;
    },
    manageNinjaTiktokActive(val){
      this.isNinjaTiktokFeedActive = val;
      this.appVars.is_custom_feed_for_tiktok_activated = true;
    },
    checkAddonStatus() {
      if( !this.isNinjaTiktokFeedInstalled ){
        this.addonStatus = 'Click Here to Install & Activate the Plugin';
      } else if( !this.isNinjaTiktokFeedActive ){
        this.addonStatus = 'Activate the Plugin';
      }
    },
  },
  computed:{
    tiktok_modal:{
      get(){
        return this.tiktokModal
      },
      set(newVal){
        this.$emit('tiktok_settings', newVal);
      }
    },
    needsReconnect() {
      return Object.values(this.connected_source_list || {}).some(account => account && account.needs_reconnect);
    },
    getSettingsLink() {
      const currentUrl = window.location.href;
      return currentUrl + 'settings/tiktok-settings';
    },
    authServiceHost() {
      try { return new URL(this.appVars.auth_service_url).hostname; } catch (e) { return this.appVars.auth_service_url; }
    },
  },

  mounted(){
    this.getVerificationConfigs();

    if(!this.isNinjaTiktokFeedActive){
      this.checkAddonStatus();
    }
  }
}
</script>