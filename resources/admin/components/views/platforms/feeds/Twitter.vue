<template>
  <el-dialog class="wpsr-connection-modal" width="50%" :title="$t('X (Twitter) Configuration')" v-model="twitter_modal">
    <template #header>
      <div class="wpsr-connection-modal-header">
        <h4 class="wpsr-dialog-title">X (Twitter) Configuration</h4>
        <p class="wpsr-connection-modal-subheading">Manage and connect your X (Twitter) account settings</p>
      </div>
    </template>
    <div class="wpsr-connection-modal-body">
      <div v-loading="loading" element-loading-text="Please wait.">


        <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20">
          <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
          <p><strong>Notice:</strong> Due to X (Twitter) API changes, we will only provide support for the User Timeline feed. Unfortunately, we won't be able to offer Home Timeline, Hashtag, or Mentions feeds anymore. To continue using User Timeline, you will have to connect your own X (Twitter) app which charges $100/month for. You can learn more about it <a href="https://developer.twitter.com/en/products/twitter-api" target="_blank">here</a>.
          </p>
        </div>

        <div v-if="isConnected">
          <div class="wpsr-user-accounts-heading">
            <h4>{{ $t('Your Connected Account') }}</h4>
          </div>
          <div class="wpsr-connected-accounts">
            <div class="wpsr-user-profile">
              <div class="wpsr-user-profile-inner">
                <div class="wpsr-user-profile-pic">
                  <el-icon><UserFilled /></el-icon>
                </div>
                <div class="wpsr-user-details">
                  <div class="wpsr-user-details-inner">
                    <div class="wpsr-user-details-info wpsr-flex-column wpsr-flex-align-left">
                      <p>{{ screenName }}</p>
                      <h3><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>{{ $t('Successfully Connected') }}</h3>
                    </div>
                    <div class="wpsr-user-actions">
                      <remove @confirm="clearVerificationConfigs" platform="twitter"></remove>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <el-button
            style="width: 100%"
            class="wpsr_primary_btn_outline"
            type='default'
            size="default"
            :class="{'display-form': reConnect}"
            @click="reConnectAccount"
        >
          <el-icon size="18"><Link /></el-icon>
          {{ $t('Connect new account')}}
        </el-button>



        <div v-if="(reConnect || !isConnected)">
          <el-tabs v-model="api_version" class="wpsr-connections-tabs wpsr-gap-0">
            <el-tab-pane name="2">
              <template #label>
                  <span class="wpsr-connections-tabs-label">
                    <i class="wpsr-icon"><ShieldCheckLineIcon/></i>
                    <div class="wpsr-connections-tabs-label-group">
                      <span>API V2 (Recommended)</span>
                    </div>
                  </span>
              </template>
              <div class="wpsr-accounts-connect-wrapper">
                <div class="wpsr-connection-modal-input-wrapper">
                  <div class="wpsr-connection-modal-input-heading-wrapper">
                    <h4 class="wpsr-connection-modal-input-heading">
                      {{ $t('Username') }}
                      <el-tooltip
                          :raw-content="true"
                          effect="dark"
                          placement="right-start"
                          :content="$t('A Username is needed, if you want to use credentials from your own X (Twitter) App.')"
                      >
                        <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                      </el-tooltip>
                    </h4>
                  </div>
                  <el-input
                      id="username"
                      v-model="userName"
                      placeholder="Enter your username"
                      autocomplete="off"
                      class="wpsr-input-default"
                  >
                    <template #prepend>
                      <el-icon size="18"><User /></el-icon>
                    </template>
                  </el-input>
                </div>
                <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                  <div class="wpsr-connection-modal-input-heading-wrapper">
                    <h4 class="wpsr-connection-modal-input-heading">
                      {{ $t('Bearer Token') }}
                      <el-tooltip
                          :raw-content="true"
                          effect="dark"
                          placement="right-start"
                          :content="$t('A Bearer Token is needed, if you want to use credentials from your own X (Twitter) App.')"
                      >
                        <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                      </el-tooltip>
                    </h4>
                  </div>
                  <InputPassword
                      id="bearer_token"
                      v-model="accessTokenBearer"
                      placeholder="Enter your bearer token"
                      autocomplete="off"
                      class="wpsr-mb-10"
                  />
                </div>
              </div>
            </el-tab-pane>
            <el-tab-pane name="1.1">
              <template #label>
                  <span class="wpsr-connections-tabs-label">
                    <el-icon style="transform: rotate(90deg)" size="18"><Key /></el-icon>
                    <div class="wpsr-connections-tabs-label-group">
                      <span>API V1 (Deprecated Soon)</span>
                    </div>
                  </span>
              </template>
              <div class="wpsr-accounts-connect-wrapper">
                <div class="wpsr-connection-modal-input-wrapper">
                  <div class="wpsr-connection-modal-input-heading-wrapper">
                    <h4 class="wpsr-connection-modal-input-heading">
                      {{$t('Consumer Key(API Key)')}}
                      <el-tooltip
                          :raw-content="true"
                          effect="dark"
                          placement="right-start"
                          :content="$t('A Consumer Key is needed, if you want to use credentials from your own X (Twitter) App.')"
                      >
                        <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                      </el-tooltip>
                    </h4>
                  </div>
                  <InputPassword
                      id="consumer_key"
                      v-model="consumerKey"
                      placeholder="Enter your consumer key"
                      autocomplete="off"
                  />
                </div>
                <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                  <div class="wpsr-connection-modal-input-heading-wrapper">
                    <h4 class="wpsr-connection-modal-input-heading">
                      {{$t('Consumer Secret(API Secret Key)')}}
                      <el-tooltip
                          :raw-content="true"
                          effect="dark"
                          placement="right-start"
                          :content="$t('A Consumer Secret is needed, if you want to use credentials from your own X (Twitter) App.')"
                      >
                        <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                      </el-tooltip>
                    </h4>
                  </div>
                  <InputPassword
                      id="consumer_secret"
                      v-model="consumerSecret"
                      placeholder="Enter your consumer secret key"
                      autocomplete="off"
                  />
                </div>
                <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                  <div class="wpsr-connection-modal-input-heading-wrapper">
                    <h4 class="wpsr-connection-modal-input-heading">
                      {{$t('Access Token)')}}
                      <el-tooltip
                          :raw-content="true"
                          effect="dark"
                          placement="right-start"
                          :content="$t('A Access Token is needed, if you want to use credentials from your own X (Twitter) App.')"
                      >
                        <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                      </el-tooltip>
                    </h4>
                  </div>
                  <InputPassword
                      id="oauth_token"
                      v-model="oauthToken"
                      placeholder="Enter your token key"
                      autocomplete="off"
                  />
                </div>

                <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                  <div class="wpsr-connection-modal-input-heading-wrapper">
                    <h4 class="wpsr-connection-modal-input-heading">
                      {{$t('Access Token Secret)')}}
                      <el-tooltip
                          :raw-content="true"
                          effect="dark"
                          placement="right-start"
                          :content="$t('A Access Token Secret is needed, if you want to use credentials from your own X (Twitter) App.')"
                      >
                        <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                      </el-tooltip>
                    </h4>
                  </div>
                  <InputPassword
                      id="oauth_token_secret_v1"
                      v-model="oauthTokenSecret"
                      placeholder="Enter your token secret key"
                      autocomplete="off"
                  />
                </div>
              </div>
            </el-tab-pane>
          </el-tabs>
          <el-button v-if="(this.api_version === '2' || api_version === '1.1')" :disabled="isDisabledSave()" class="wpsr_primary_btn wpsr-mt-20" size="default" type="success" @click="saveConfigs">{{ $t('Save') }}</el-button>
        </div>

        <div class="wpsr-make-template-btn-wrap" v-if="isConnected">
          <h3>Let’s create an awesome template to be added to your site!</h3>
          <p>Create a stunning, fully customizable template to perfectly match your website’s style.</p>
          <el-button
              class="wpsr_primary_btn"
              type="primary"
              size="default"
              @click.prevent = "addNewTemplate"
          >
            <CreateTemplateIcon/>
            {{ $t('Add New Template') }}
          </el-button>
        </div>
      </div>
    </div>
    <div class="wpsr-connection-modal-footer">
      <div class="wpsr-docs-help">
        <p>Need Help?</p>
        <div class="wpsr-docs-help-links">
          <a href="https://wpsocialninja.com/docs/twitter-configuration-twitter-feeds-wp-social-ninja/" target="_blank">{{ $t('Read Documentation') }}</a>
          <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
          <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
        </div>
      </div>
    </div>
  </el-dialog>
</template>

<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from '../../../core-ui/editor/InputPassword'
import InfoIcon from "../../../pieces/icons/InfoIcon.vue";
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";
import CreateTemplateIcon from  '../../../pieces/icons/CreateTemplateIcon.vue';
import {UserFilled} from "@element-plus/icons-vue";

export default {
  name: 'TwitterConfig',
  props:['twitterModal'],
  data() {
    return {
      api_version: '2',
      loading: false,
      twitterConfig:{},
      consumerKey:'',
      consumerSecret:'',
      oauthTokenSecret:'',
      oauthToken:'',
      accessTokenBearer: '',
      userName: '',
      screenName:'',
      isConnected:false,
      reConnect: false,
      connectionType: false,
    }
  },

  components:{
    UserFilled,
    ShieldCheckLineIcon,
    InfoIcon,
    Remove,
    InputPassword,
    CreateTemplateIcon
  },

  methods: {
    isDisabledSave(){
      if (this.api_version === '2') {
        return !(this.userName && this.accessTokenBearer);
      } else if (this.api_version === '1.1') {
        return !(this.consumerKey && this.consumerSecret && this.oauthToken && this.oauthTokenSecret);
      }
      return false;
    },
    reConnectAccount() {
      this.reConnect = !this.reConnect;
    },

    getVerificationConfigs() {
      this.loading = true;
      this.$get('platforms/feeds/configs', {
        platform: 'twitter'
      }).then(response => {
        if(response && response.data) {
          this.twitterConfig = response.data.settings;
          if(this.twitterConfig) {
            //old configs
            let settings = this.twitterConfig.dynamic;
            if(settings) {
              this.oauthTokenSecret = settings.oauth_access_token_secret;
              this.oauthToken = settings.oauth_access_token;
              this.consumerKey = settings.consumer_key;
              this.consumerSecret = settings.consumer_secret;
              this.screenName = settings.screen_name;
              this.api_version = settings.api_version;

              this.isConnected = settings.screen_name && settings.screen_name.length;
              this.userName = settings.username;
              this.accessTokenBearer = settings.access_token_bearer;
              // this.connectionType = settings.manual_connect;
            }
          } else {
            this.consumerKey = '';
            this.consumerSecret = '';
            this.isConnected = false;
            this.connectionType = false;
            this.oauthToken = '';
            this.oauthTokenSecret = '';
          }
          this.$emit('findEnabledPlatforms');
        }
      }).catch( (errors) => {
        this.handleError(errors);
      }).always(() => {
        this.loading = false;
      });
    },

    saveVerificationConfigs(args){
      this.$post('platforms/feeds/configs', {
        settings: JSON.stringify(args),
        platform:'twitter'
      }).then(response => {
        if(response && response.data) {
          this.twitterConfig = response.data.settings;
          if(this.twitterConfig){
            this.getVerificationConfigs();
            window.history.pushState({}, null, this.admin_page_url);
          }
          this.handleSuccess(response.data.message);
        }
      }).catch( (errors) => {
        this.handleError(errors);
      }).always(() => {
        this.twitter_modal = true;
      });
    },

    saveConfigs() {
      let args = {
        'oauth_access_token': this.oauthToken,
        'oauth_access_token_secret': this.oauthTokenSecret,
        'screen_name': '',
        'username': this.userName,
        'access_token_bearer': this.accessTokenBearer,
        'platform': 'twitter',
        'consumer_key': this.consumerKey,
        'consumer_secret': this.consumerSecret,
        'api_version': this.api_version
        // 'manual_connect': this.isConnected
      };

      if(this.api_version === '1.1' && this.oauthToken && this.oauthTokenSecret && this.consumerKey && this.consumerSecret) {
        this.saveVerificationConfigs(args);
      } else if(this.api_version === '2' && this.accessTokenBearer && this.userName) {
        this.saveVerificationConfigs(args);
      } else {
        let message = 'Please fill the fields correctly!!';
        this.handleError(message);
      }
    },

    clearVerificationConfigs() {
      this.$del('platforms/feeds/configs', {
        platform: 'twitter'
      }).then(response => {
        if(response && response.data) {
          this.getVerificationConfigs();
          this.handleSuccess(response.data.message);
        }
      }).catch( (errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    },

    addNewTemplate() {
      this.$post('templates', {
        platform:'twitter'
      }).then(response => {
        if(response && response.template_id) {
            this.$router.push({
              name: 'edit-twitter-template',
              params: {
                template_id: response.template_id
              }
            });
            // close modal after successful template creation
            this.twitter_modal = false;
        }
      }).catch( (errors) => {
        this.handleError(errors);
      }).always(() => {
        
      });
    },
  },

  computed: {
    twitter_modal:{
      get() {
        return this.twitterModal
      },

      set(newVal) {
        this.$emit('twitter_settings', newVal);
      }
    }
  },

  mounted() {
    this.getVerificationConfigs();
  }
}
</script>
