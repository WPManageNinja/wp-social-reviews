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
                            <span><i class="el-icon-info"></i>{{ $t('Source Invalid') }}</span>
                            <a href="#" @click.prevent="oAuthLogin(account)">Reconnect</a>
                          </div>
                      </div>
                      <div v-else-if="account.encryption_error" class="wpsr-account-error-status-wrapper">
                        <div class="wpsr-account-error-status">
                            <span><i class="el-icon-info"></i>{{ $t('Encryption Error') }}</span>
                            <a href="#" @click.prevent="oAuthLogin(account)">Reconnect</a>
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
                <h3>Get Your TikTok Access Code</h3>
                <p>Simply click the button below to instantly receive your TikTok access code—it only takes one click.</p>
              </div>
              <div class="wpsr-connection-modal-input-heading-wrapper">
                <div class="wpsr-modal-btn-with-info wpsr-mb-20">
                  <el-button
                      class="wpsr-login-with-tiktok"
                      @click.prevent="oAuthLogin"
                      size="default"
                      type="primary"
                  >
                    <img :src="assets_url+'/images/icon/icon-tiktok.png'"  alt="Continue with TikTok">
                    <p>{{ $t('Continue with TikTok') }}</p>
                  </el-button>
                </div>
                <h4 class="wpsr-connection-modal-input-heading">
                  {{ $t('Access Code') }}
                  <el-tooltip
                      :raw-content="true"
                      effect="dark"
                      placement="right-start"
                      :content="$t('Please enter a valid access code.')"
                  >
                    <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                  </el-tooltip>
                </h4>
              </div>
              <InputPassword
                  class="wpsr-mb-10"
                  type="text"
                  size="default"
                  v-model="tiktok_access_code"
                  placeholder="Enter a valid access code"
              />
              <div class="wpsr-modal-group-btn">
                <el-button :disabled="!tiktok_access_code" class="wpsr_primary_btn" size="default" type="success" @click="saveVerificationConfigs">
                  {{ $t('Connect') }}
                </el-button>
              </div>
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
  </div>
</template>

<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from '../../../core-ui/editor/InputPassword';
import CreateTemplateIcon from  '../../../pieces/icons/CreateTemplateIcon.vue';
import AddOns from "./AddOns";

export default {
  props:['tiktokModal', 'access_token'],
  data(){
    return {
      loading: false,
      inner_loading: false,
      reConnect: false,
      isConnected: false,
      config: {},
      connected_source_list: [],
      accessToken: this.access_token,
      tiktok_access_code: '',
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
    InputPassword,
    CreateTemplateIcon
  },
  methods: {
    oAuthLogin(account) {
      if(account.encryption_error){
        this.reConnect = true;
      }
      // let url = 'https://www.tiktok.com/v2/auth/authorize/?client_key='+this.user_credentials.client_id+'&response_type=code&scope=user.info.basic,video.list&redirect_uri='+this.user_credentials.redirect_uri;
      let url = 'https://www.tiktok.com/v2/auth/authorize/?client_key=aw4cddbhcvsbl34m&response_type=code&scope=user.info.profile,user.info.stats,video.list&redirect_uri=https://wpsocialninja.com/api/tiktok_callback';
      window.open(url, '_blank');
    },
    saveVerificationConfigs(){
      this.loading = true;
      this.inner_loading = true;
      let access_code = this.tiktok_access_code;

      let settings = {
        access_code
      };

      this.$post('platforms/feeds/configs', {
        settings: JSON.stringify(settings),
        platform:'tiktok'
      })
          .then(response => {
            if(response.data) {
              this.getVerificationConfigs();
              this.handleSuccess(response.data.message);
              this.tiktok_access_code = '';
            }
          }).catch(error => {
        this.handleError(error);
      }).always(() => {
        this.loading = false;
        this.inner_loading = false;
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
    getSettingsLink() {
      const currentUrl = window.location.href;
      return currentUrl + 'settings/tiktok-settings';
    },
  },

  mounted(){
    if (this.accessToken !== '') {
      this.saveVerificationConfigs();
    }
    this.getVerificationConfigs();

    if(!this.isNinjaTiktokFeedActive){
      this.checkAddonStatus();
    }
  }
}
</script>