<template>
  <el-dialog title="Instagram Configuration" width="50%" class="wpsr-connection-modal" v-model="instagram_modal">
    <template #header>
      <div class="wpsr-connection-modal-header">
        <h4 class="wpsr-dialog-title">Instagram Configuration</h4>
        <p v-if="!Object.keys(connected_accounts).length" class="wpsr-connection-modal-subheading">Choose the type of account to connect</p>
        <p v-else class="wpsr-connection-modal-subheading">{{Object.keys(connected_accounts).length}} Account Connected.</p>
      </div>
    </template>

   <div class="wpsr-connection-modal-body" :class="!Object.keys(connected_accounts).length ? 'wpsr-gap-0' : ''">
     <div v-loading="loading" element-loading-text="Please wait.">
       <el-dialog
           title="Facebook Authorized Pages"
           class="wpsr-inner-modal"
           v-model="businessAccountsModal"
           v-if="Object.keys(businessAccounts).length"
           width="30%"
           append-to-body
       >
         <template #header>
           <div class="wpsr-connection-modal-header">
             <h4 class="wpsr-dialog-title">Facebook Authorized Pages</h4>
             <p class="wpsr-connection-modal-subheading">Choose which pages you would like to connect:</p>
           </div>
         </template>
         <div class="wpsr-modal-body">
           <div v-loading="inner_loading" element-loading-text="Please wait.">
             <div :class="Object.keys(businessAccounts).length > 5 ? 'wpsr-scrollbar' : ''">
             <el-checkbox class="wpsr_primary_checkbox_or_radio" v-if="Object.keys(businessAccounts).length > 1" :indeterminate="isIndeterminate"
                          v-model="selectAll" @change="handleCheckAllChange">
               Select all
             </el-checkbox>
             <el-checkbox-group v-model="selectedAccounts" @change="handleSelectedAccountsChange">
               <el-checkbox
                   class="wpsr_primary_checkbox_or_radio"
                   v-for="(account, index) in businessAccounts"
                   :label="account"
                   :key="account.username || index"
               >
                 <template v-if="businessAccounts[index]">
                   <div class="wpsr-user-profile-pic">
                     <img v-if="account.profile_picture_url" :src="account.profile_picture_url">
                     <img v-else :src="assets_url+'/images/template/review-template/placeholder-image.png'"/>
                   </div>
                   <div class="wpsr-user-details">
                     <p>{{ $trimWords(account.name, 8, true) }}</p>
                     <span>@{{ account.username }}</span>
                   </div>
                 </template>

               </el-checkbox>
             </el-checkbox-group>
             </div>
             <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mt-10">
               <el-icon><InfoFilled /></el-icon>
              <p>
                <strong>Note:</strong> Changing the password, updating privacy settings, or removing the page admins for the related Facebook page
                may require
                <a href="https://wpsocialninja.com/docs/how-to-reauthorize-the-wp-social-ninja-instagram-facebook-app/"
                   target="_blank">manually reauthorizing our app</a> to reconnect an account.
              </p>
             </div>
           </div>
         </div>
         <div class="wpsr-modal-footer">
           <el-button class="wpsr_default_btn" type="default" size="small" @click="businessAccountsModal = false">{{$t('Cancel')}}</el-button>
           <el-button :disabled="Object.keys(selectedAccounts).length === 0 ? true : false" class="wpsr_primary_btn"
                      size="default" type="success" @click="saveVerificationConfigs">{{ $t('Connect Accounts') }}
           </el-button>
         </div>
       </el-dialog>

       <div class="wpsr-user-accounts-heading wpsr-jc-between" v-if="connected">
         <h4>
           {{ $t('Manage Instagram Accounts') }}
           <el-tooltip
               :raw-content="true"
               effect="dark"
               placement="top-start"
               :content="`Add or remove connected Instagram accounts`"
           >
             <el-icon ><InfoFilled /></el-icon>
           </el-tooltip>
         </h4>
         <el-button
             v-if="Object.keys(businessAccounts).length"
             :class="{'display-form': !!businessAccountsModal}"
             class="wpsr_simple_btn"
             type="success"
             @click="businessAccountsModal = true">
           + {{ $t('Add More Page') }}
         </el-button>
       </div>

       <div v-if="connected_accounts" :class="Object.keys(connected_accounts).length > 5 ? 'wpsr-scrollbar' : ''">
         <div class="wpsr-connected-accounts">
          <div v-for="(account,index) in connected_accounts" :key="index">
           <div class="wpsr-user-profile">
             <div class="wpsr-user-profile-inner">
               <div class="wpsr-user-profile-pic">
                 <img v-if="account.user_avatar" :src="account.user_avatar">
                 <img v-else :src="assets_url+'/images/template/review-template/placeholder-image.png'"/>
               </div>
               <div class="wpsr-user-details">
                <div class="wpsr-user-details-inner">
                  <div class="wpsr-user-details-info wpsr-flex-column wpsr-flex-align-left">
                    <p>{{ account.username }}</p>
                    <span>{{ account.api_type === 'business' ? 'Business Advanced' : account.api_type.replace(/_/g, ' ') }}<strong v-if="account.is_private"> ( {{ 'Private' }} )</strong></span>
                  </div>
                  <div class="wpsr-user-actions">
                          <span class="wpsr-user-token-expire-time" v-if="account.is_private">
                            {{ accessTokenExpiredIn(account) }}
                            <el-tooltip class="item" effect="dark" placement="right-start">
                              <div slot="content">This account is a <strong>private</strong> account on Instagram. It needs to be manually reconnect every 60 days. If you don't refresh your account, then the backup cache will be displayed instead. <br/> Change this account to be <a
                                  href="https://www.instagram.com/accounts/privacy_and_security/" target="_blank"><strong>public</strong></a> [Account Privacy->Private Account] to have access token that are automatically refreshed.</div>
                              <i class="el-icon-info"></i>
                            </el-tooltip>
                            <el-button size="small" @click.prevent="oAuthLogin()" type="primary">Get Refresh Token</el-button>
                          </span>
                    <remove @confirm="clearVerificationConfigs(account.user_id)" platform="instagram"></remove>
                  </div>
                </div>

                <div v-if="account.has_critical_error" class="wpsr-account-error-status-wrapper">
                   <div class="wpsr-account-error-status">
                       <span>
                         <el-icon><InfoFilled /></el-icon>
                         {{ $t('Source Invalid') }}
                       </span>
                      <a href="#" @click.prevent="oAuthLogin(account.api_type)">Reconnect</a>
                    </div>
                </div>
                 <div v-else-if="account.encryption_error" class="wpsr-account-error-status-wrapper">
                   <span  class="wpsr-account-error-status">
                      <span>
                        <el-icon><InfoFilled /></el-icon>
                        {{ $t('Encryption Error') }}
                      </span>
                     <a href="#" @click.prevent="oAuthLogin(account.api_type)">Reconnect</a>
                    </span>
                 </div>
               </div>
             </div>
           </div>
         </div>
         </div>
       </div>

       <el-button
           v-if="Object.keys(connected_accounts).length"
           style="width: 100%"
           v-model="addNewAccount"
           :class="{'display-form': !!addNewAccount}"
           class="wpsr_primary_btn_outline wpsr-mt-15"
           type="success"
           @click.prevent="addAccount">
         <el-icon size="18"><Link /></el-icon>
         {{ $t('Connect More Accounts') }}
       </el-button>

       <div v-if="addNewAccount || !Object.keys(connected_accounts).length">
         <el-tabs v-model="credentialsType" class="wpsr-connections-tabs">
           <el-tab-pane name="business_basic">
             <template #label>
              <span class="wpsr-connections-tabs-label">
                <el-icon size="18"><Suitcase /></el-icon>
                <div class="wpsr-connections-tabs-label-group">
                  <span>Business Basic</span>
                  <p>Connects via Instagram</p>
                </div>
              </span>
             </template>
             <div class="wpsr-connection-info-list-wrapper">
                <ul>
                  <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><InfoFilled /></el-icon>
                    Requires Instagram Creator or Business account
                  </li>
                  <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>
                    Display profile Info, avatars, and posts
                  </li>
                  <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>
                    Connect your Instagram account
                  </li>
                  <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>
                    Does not require a Facebook page
                  </li>
                  <li><el-icon size="16" color="var(--wpsr-icon-disabled-color)"><CircleCloseFilled /></el-icon>
                    Does not display Hashtag feeds
                  </li>
                </ul>
             </div>
             <div class="wpsr-accounts-connect-wrapper">
               <el-button class="wpsr-login-with-ig-btn" @click.prevent="oAuthLogin()" size="default" type="primary">
                 <img :src="assets_url+'/images/icon/icon-instagram-small.png'" alt="Instagram">
                 {{ $t('Continue with Instagram') }}
               </el-button>
             </div>
             <div class="wpsr-connection-permission-wrapper">
               <el-collapse v-model="activePermissionPanel">
                 <el-collapse-item title="This permissions will be granted, when you connect your user account" name="1">
                   <div class="wpsr-connection-permission-item">
                     <div class="wpsr-connection-permission-item-header">
                       <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                       <span>instagram_business_basic</span>
                     </div>
                      <p>This permission is used to retrieve your Instagram account’s name, statistics, posts, and other related information from the account you’ve authorized.</p>
                   </div>
                 </el-collapse-item>
               </el-collapse>
             </div>
           </el-tab-pane>
           <el-tab-pane label="Business Advanced" name="business">
             <template #label>
              <span class="wpsr-connections-tabs-label">
                <el-icon size="18"><Briefcase /></el-icon>
                 <div class="wpsr-connections-tabs-label-group">
                    <span>Business Advanced</span>
                    <p>Connects via Facebook</p>
                 </div>
              </span>
             </template>
             <div class="wpsr-connection-info-list-wrapper">
               <ul>
                 <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><InfoFilled /></el-icon>
                   Requires Instagram Creator or Business account
                 </li>
                 <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><InfoFilled /></el-icon>
                   Requires a connected Facebook page
                 </li>
                 <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>
                   Display profile Info, avatars, and posts
                 </li>
                 <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>
                   Connect multiple Instagram accounts
                 </li>
                 <li><el-icon size="16" color="var(--wpsr-icon-info-color)"><CircleCheckFilled /></el-icon>
                   Displays Hashtag feeds
                 </li>
               </ul>
             </div>
             <div class="wpsr-accounts-connect-wrapper">
               <span v-if="credentialsType === 'business'">
                <el-button class="wpsr-login-with-facebook" @click.prevent="oAuthLogin()" size="default" type="primary">
                  <img :src="assets_url+'/images/icon/login-fb-btn.png'" alt="Continue with Facebook">
                </el-button>
              </span>
             </div>
             <div class="wpsr-connection-permission-wrapper">
               <el-collapse v-model="activePermissionPanel">
                 <el-collapse-item title="This permissions will be granted, when you connect your user account" name="1">
                   <div class="wpsr-connection-permission-item">
                     <div class="wpsr-connection-permission-item-header">
                       <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                       <span>instagram_basic</span>
                     </div>
                     <p>This permission lets us display information about the Instagram account you connect with, so it appears in the header above the feed and helps identify your connected account.</p>
                   </div>
                   <div class="wpsr-connection-permission-item">
                     <div class="wpsr-connection-permission-item-header">
                       <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                       <span>pages_show_list</span>
                     </div>
                     <p>This permission lets us fetch the names of the Instagram accounts you manage, so you can connect multiple accounts at the same time.</p>
                   </div>
                   <div class="wpsr-connection-permission-item">
                     <div class="wpsr-connection-permission-item-header">
                       <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                       <span>pages_read_engagement</span>
                     </div>
                     <p>This permission allows us to view your Page’s follower count, read its content, and access other engagement metrics.</p>
                   </div>
                   <div class="wpsr-connection-permission-item">
                     <div class="wpsr-connection-permission-item-header">
                       <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                       <span>business_management</span>
                     </div>
                     <p>This permission helps us access the names of the Instagram accounts you manage and is required alongside other permissions for full functionality.</p>
                   </div>
                 </el-collapse-item>
               </el-collapse>
             </div>
           </el-tab-pane>

           <el-tab-pane label="Account  Manually" name="manually">
             <template #label>
              <span class="wpsr-connections-tabs-label">
                <el-icon size="18"><User /></el-icon>
                 <div class="wpsr-connections-tabs-label-group">
                     <span>Account  Manually</span>
                     <p>Connect via Token</p>
                 </div>
              </span>
             </template>
             <div class="wpsr-accounts-connect-wrapper">
               <div class="wpsr-connection-modal-input-wrapper">
                 <div class="wpsr-connection-modal-input-heading-wrapper">
                   <h4 class="wpsr-connection-modal-input-heading">{{ $t('Access Token') }}
                     <el-tooltip
                         :raw-content="true"
                         effect="dark"
                         placement="right-start"
                         :content="$t('Please enter the valid Instagram Access Token.')"
                     >
                       <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                     </el-tooltip>
                   </h4>
                   <p class="wpsr-connection-modal-input-description">Connecting a client's account? Avoid sharing accounts password, please use our <a href="https://wpsocialninja.com/access-token-generator/?id=instagram-feed" target="_blank">access token generator</a>.</p>
                 </div>
                 <InputPassword class="wpsr-mb-10" type="text" size="default" v-model="instagramAccessToken"
                                placeholder="Enter a valid Access Token"></InputPassword>
               </div>
               <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                 <div class="wpsr-connection-modal-input-heading-wrapper">
                   <h4 class="wpsr-connection-modal-input-heading">
                     {{ $t('Instagram User ID') }}
                   </h4>
                 </div>
                 <el-input class="wpsr-input-default" type="text" size="default" v-model="ig_account_id" placeholder="Please enter the User ID for this Profile">
                   <template #prepend>
                     <el-icon size="18"><User /></el-icon>
                   </template>
                 </el-input>
               </div>

               <div class="wpsr-modal-group-btn wpsr-mt-20">
                 <el-button :disabled="!(instagramAccessToken && ig_account_id)" class="wpsr_primary_btn" size="default" type="success" @click="saveVerificationConfigs">
                   {{ $t('Connect this Account') }}
                 </el-button>
               </div>
             </div>
           </el-tab-pane>
         </el-tabs>
       </div>

       <div v-if="connected" class="wpsr-make-template-btn-wrap">
         <h3>Let’s create an awesome template to be added to your site!</h3>
         <p>Create a stunning, fully customizable template to perfectly match your website’s style.</p>
         <el-button class="wpsr_primary_btn" type="primary" size="default" @click.prevent="addNewFeedTemplate">
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
           <a href="https://wpsocialninja.com/docs/instagram-configuration-social-feeds-wp-social-ninja/" target="_blank">{{ $t('Read Documentation') }}</a>
           <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
           <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
        </div>
      </div>
    </div>
  </el-dialog>
</template>
<style lang="scss">

</style>
<script>
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from "../../../core-ui/editor/InputPassword.vue";
import CreateTemplateIcon from  '../../../pieces/icons/CreateTemplateIcon.vue';

export default {
  props: ['instagramModal', 'access_token', 'expires_in', 'credentials_type'],
  data() {
    return {
      activePermissionPanel: [],
      loading: false,
      inner_loading: false,
      admin_page_url: this.appVars.admin_page_url,
      type: null,
      credentialsType: 'business_basic',
      apiType: 'business_basic',
      instagramAccessToken: this.access_token,
      connected: false,

      connected_accounts: [],
      addNewAccount: false,
      isIndeterminate: true,
      _businessAccountsModal: false,
      businessAccounts: [],
      selectAll: false,
      selectedAccounts: [],
      ig_account_id: null
    }
  },

  components: {
    InputPassword,
    Remove,
    CreateTemplateIcon
  },

  methods: {
    handleCheckAllChange(value) {
      this.selectedAccounts = value ? this.businessAccounts : [];
      this.isIndeterminate = false;
    },
    handleSelectedAccountsChange(value) {
      this.selectedAccounts = value;
      // if(Object.keys(this.selectedAccounts).length > 1 && !this.has_pro ){
      //   this.handlePro();
      // }
      this.instagramAccessToken = value.access_token;
      this.ig_account_id = value.id;
      this.credentialsType = 'business';
      let checkedCount = Object.keys(value).length;
      this.selectAll = checkedCount === Object.keys(this.businessAccounts).length;
      this.isIndeterminate = checkedCount > 0 && checkedCount < Object.keys(this.businessAccounts).length;
    },
    addAccount() {
      this.addNewAccount = !this.addNewAccount;
    },
    oAuthLogin(apiType = '') {
      const currentUrl = window.location.href;
      const isEditor = currentUrl.includes('#/edit-instagram-template/');
      let callBackUrl;
      if (isEditor) {
        const baseUrl = window.location.origin + window.location.pathname;
        let templateId = null;
        const hashMatch = window.location.hash.match(/edit-instagram-template\/?(\d+)/);
        if (hashMatch && hashMatch[1]) {
          templateId = hashMatch[1];
        } else {
          templateId = this.template_id || '';
        }
        callBackUrl = `${baseUrl}?page=wpsocialninja.php&edit-instagram-template&${templateId}`;
      } else {
        callBackUrl = this.admin_page_url;
      }

      this.credentialsType = apiType !== '' ? apiType : this.credentialsType;
      if (this.credentialsType === 'business_basic'){
        let clientId = '563840396380629';
        let redirectUrl = 'https://wpsocialninja.com/api/instagram-basic-business-display-redirect.php';
        window.open('https://www.instagram.com/oauth/authorize?enable_fb_login=0&force_authentication=1&client_id='+clientId+'&redirect_uri='+redirectUrl+'&response_type=code&scope=instagram_business_basic&state=' + encodeURIComponent(callBackUrl), '_self');
      } else if (this.credentialsType === 'business') {
        let clientId = '443259723723907';
        let redirectUrl = 'https://wpsocialninja.com/api/instagram-graph-api-redirect.php';
        window.open('https://graph.facebook.com/oauth/authorize?client_id='+ clientId +'&redirect_uri='+ redirectUrl +'&scope=instagram_basic,pages_read_engagement,pages_show_list,business_management&state=' + encodeURIComponent(callBackUrl) , '_self');
      }
    },

    saveVerificationConfigs() {
      if (this.instagramAccessToken !== '' || Object.keys(this.selectedAccounts).length) {
        this.loading = true;
        this.inner_loading = true;

        if((this.expires_in && this.credentialsType !== 'business') || (this.apiType === 'business_basic' && this.credentialsType === 'manually')){
          this.apiType = 'business_basic';
        } else {
          this.apiType = 'business';
        }

        let settings = {
          credentials_type: this.credentialsType,
          api_type: this.apiType,
          access_token: this.instagramAccessToken,
          expires_in: this.expires_in,
          user_id: this.ig_account_id,
          selectedAccounts: this.selectedAccounts
        }

        this.$post('platforms/feeds/configs', {
          settings: JSON.stringify(settings),
          platform: 'instagram'
        }).then(response => {
              if (this.businessAccounts && this.expires_in === null) {
                this.businessAccountsModal = true;
              }

              if (response.data) {
                if(response.data.settings){
                  this.businessAccountsModal = false;
                }
                this.instagramAccessToken = '';
                this.ig_account_id = null;
                this.$emit('update:access_token', '');
                this.getVerificationConfigs();
                this.handleSuccess(response.data.message);
              }
            }).catch(error => {
              this.handleError(error);
            }).always(() => {
              this.loading = false;
              this.inner_loading = false;
            });
      } else {
        let message = 'Field should not be empty!';
        this.handleError(message);
      }
    },

    getVerificationConfigs() {
      const queryString = window.location.search;
      const urlParams = new URLSearchParams(queryString);
      this.type = urlParams.get('type');

      this.connected = false;
      this.$get('platforms/feeds/configs', {
        platform: 'instagram'
      }).then(response => {
        if (response.data) {
          if (response.data.business_accounts) {
            this.businessAccounts = response.data.business_accounts;
          }

          if (response.data.connected_accounts && response.data.connected_accounts.length !== 0) {
            this.connected = true;
          }

          this.connected_accounts = response.data.connected_accounts;

          this.$emit('findEnabledPlatforms');
        }

      }).catch(error => {
        this.handleError(error);
        this.businessAccounts = []
      }).always(() => {

      });
    },

    clearVerificationConfigs(user_id) {
      this.$del('platforms/feeds/configs', {
        platform: 'instagram',
        user_id: user_id
      }).then(response => {
        if (response.data) {
          this.getVerificationConfigs();
          this.$emit('update:access_token', '');
          this.apiType = 'business_basic';
          this.credentialsType = 'business_basic';
          this.instagramAccessToken = '';
          this.connected = false;
          this.handleSuccess(response.data.message);
        }
      }).catch(error => {

      }).always(() => {

      });
    },

    addNewFeedTemplate() {
      this.$post('templates', {
        platform: 'instagram'
      }).then(response => {
        if (response && response.template_id) {
          this.$router.push({
            name: 'edit-instagram-template',
            params: {
              template_id: response.template_id
            }
          })
          // close modal after successful template creation
          this.instagram_modal = false;
        }
      }).catch(error => {
        this.handleError(error);
      }).always(() => {

      });
    },

    accessTokenExpiredIn(account) {
      let expiredTime = (Number(account.expires_in) + Number(account.created_at));
      let currentTime = ((new Date()).getTime() / 1000);
      let days = (expiredTime - currentTime) / 86400;

      if (days <= 0) {
        return 'Access Token is Expired'
      }

      return 'Access Token Expires in ' + Math.floor(days) + ' Days';
    },

    checkForConfigurationParameter() {
      // Check main URL parameters
      const queryString = window.location.search;
      const urlParams = new URLSearchParams(queryString);
      let configuration = urlParams.get('configuration');
      
      // If not found in main URL, check hash parameters
      if (!configuration) {
        const hash = window.location.hash;
        const hashQuery = hash.split('?')[1];
        
        if (hashQuery) {
          const hashParams = new URLSearchParams(hashQuery);
          configuration = hashParams.get('configuration');
        }
      }
      
      return configuration === 'true';
    },

    clearConfigurationParameter() {
      const url = new URL(window.location.href);
      if (url.search) {
        // Keep only the page parameter
        const cleanUrl = url.origin + url.pathname + '?page=wpsocialninja.php' + url.hash;
        window.history.replaceState({}, '', cleanUrl);
      }
      
      // Clear from hash parameters - remove all query parameters from hash
      const hash = window.location.hash;
      if (hash.includes('?')) {
        const [base] = hash.split('?');
        window.location.hash = base;
      }
    },
    getCredentialsType() {
      const hash = window.location.hash || "";
      const queryStart = hash.indexOf("?");
      if (queryStart === -1) return null;
      const queryString = hash.slice(queryStart + 1);
      const params = new URLSearchParams(queryString);
      return params.get("credentialsType");
    }
  },

  computed: {
    instagram_modal: {
      get() {
        return this.instagramModal
      },
      set(newVal) {
        this.$emit('instagram_settings', newVal);
      }
    },
    businessAccountsModal: {
      get() {
        return this._businessAccountsModal;
      },
      set(value) {
        if (!value) {
          // Reset when modal is closed
          this.selectedAccounts = [];
          this.selectAll = false;
          this.isIndeterminate = false;
        }
        this._businessAccountsModal = value;
      }
    }
  },
  watch: {
    'instagramAccessToken': {
      handler(newVal, oldVal) {
        if (this.credentialsType === 'manually') {
          if (newVal.indexOf('EAAGTJHJgNIMB') !== -1) {
            this.apiType = 'business';
          } else {
            this.apiType = 'business_basic';
          }
        }
      },
      deep: true
    },
    'instagram_modal': {
      handler(newVal) {
        if (newVal) {
          let credentials_type = this.getCredentialsType();
          const hasConfigurationParam = this.checkForConfigurationParameter();
          if (hasConfigurationParam && credentials_type === 'business') {
            const checkBusinessAccounts = () => {
              if (this.businessAccounts && Object.keys(this.businessAccounts).length > 0) {
                this.businessAccountsModal = true;
                setTimeout(this.clearConfigurationParameter, 500);
              } else {
                setTimeout(checkBusinessAccounts, 500);
              }
            };
            setTimeout(checkBusinessAccounts, 100);
          } else if(credentials_type === 'business_basic') {
            this.instagram_modal = true;
            const checkBusinessAccounts = () => {
              if (this.businessAccounts && Object.keys(this.businessAccounts).length > 0) {
                setTimeout(this.clearConfigurationParameter, 500);
              } else {
                setTimeout(checkBusinessAccounts, 500);
              }
            };
            setTimeout(checkBusinessAccounts, 100);
          }
        }
      },
      immediate: true
    }
  },
  mounted() {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    let platform = urlParams.get('platform');
    let credentials_type = urlParams.get('credentialsType');

    if (this.instagramAccessToken) {
      this.instagram_modal = true;
      this.credentialsType = credentials_type ? credentials_type : this.credentialsType;
      this.saveVerificationConfigs();
    }
    this.getVerificationConfigs();
    this.addNewAccount = false;
  }
}
</script>