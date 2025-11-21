<template>
  <el-dialog class="wpsr-connection-modal" width="50%" title="Facebook Configuration" v-model="facebook_modal">

    <template #header>
      <div class="wpsr-connection-modal-header">
        <h4 class="wpsr-dialog-title">Facebook Configuration</h4>
        <p v-if="!Object.keys(connected_source_list).length" class="wpsr-connection-modal-subheading">Select Credentials Type</p>
        <p v-else class="wpsr-connection-modal-subheading">{{Object.keys(connected_source_list).length}} Page Connected.</p>
      </div>
    </template>

    <div class="wpsr-connection-modal-body" :class="!Object.keys(connected_source_list).length ? 'wpsr-gap-0' : ''">
    <div v-loading="loading" element-loading-text="Please wait..">

      <!-- start connected pages -->
      <div>
        <div class="wpsr-user-accounts-heading wpsr-jc-between" v-if="Object.keys(authorized_source_list).length">
           <h4>
             {{ $t('Manage Your Facebook Pages') }}
           </h4>
          <el-button
              :class="{'display-form': !!businessAccountsModal}"
              class="wpsr_simple_btn"
              type="success"
              @click="businessAccountsModal = true">
            + {{ $t('Add More Page') }}
          </el-button>
        </div>
        <div v-if="isConnected && Object.keys(connected_source_list).length" :class="Object.keys(connected_source_list).length > 5 ? 'wpsr-scrollbar' : ''">
          <div class="wpsr-connected-accounts">
            <div v-for="(page,index) in connected_source_list" :key="index">
            <div class="wpsr-user-profile">
              <div class="wpsr-user-profile-inner">
                <div class="wpsr-user-profile-pic">
                  <img v-if="page.page_id" :src="'https://graph.facebook.com/'+page.page_id+'/picture'">
                </div>

                <div class="wpsr-user-details">
                  <div class="wpsr-user-details-inner">
                    <div class="wpsr-user-details-info wpsr-flex-column wpsr-flex-align-left">
                      <p>{{ page.name }}</p>
                      <span>{{page.type}}</span>
                    </div>
                    <div class="wpsr-user-actions">
                      <remove @confirm="clearVerificationConfigs(index)" platform="facebook"></remove>
                    </div>
                  </div>

                  <div v-if="page.has_critical_error" class="wpsr-account-error-status-wrapper">
                    <div class="wpsr-account-error-status">
                        <span>
                          <el-icon><InfoFilled /></el-icon>{{ $t('Source Invalid') }}
                        </span>
                        <a href="#" @click.prevent="oAuthLogin">Reconnect</a>
                    </div>
                  </div>
                  <div v-else-if="page.encryption_error" class="wpsr-account-error-status-wrapper">
                    <div class="wpsr-account-error-status">
                      <span><el-icon><InfoFilled /></el-icon>{{ $t('Encryption Error') }}</span>
                      <a href="#" @click.prevent="oAuthLogin">Reconnect</a>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
          </div>
        </div>
        <el-button
            v-if="Object.keys(authorized_source_list).length || Object.keys(connected_source_list).length"
            style="width: 100%"
            v-model="addNewAccount"
            :class="{'display-form': !!addNewAccount}"
            class="wpsr_primary_btn_outline wpsr-mt-15"
            type="success"
            :icon="!!addNewAccount ? 'el-icon-remove-outline' : 'el-icon-circle-plus-outline'"
            @click.prevent="addAccount">
          <el-icon size="18"><Link /></el-icon>
          {{ $t('Connect More Accounts') }}
        </el-button>
      </div>
      <!-- end connected pages -->

      <!-- start add new account pages -->
      <div v-if="addNewAccount || !Object.keys(connected_source_list).length">
        <el-tabs v-model="credentialsType" class="wpsr-connections-tabs">
          <el-tab-pane name="oauth">
            <template #label>
              <span class="wpsr-connections-tabs-label">
                <i class="wpsr-icon"><ShieldCheckLineIcon/></i>
                <div class="wpsr-connections-tabs-label-group">
                  <span>OAuth 2.0</span>
                  <p>Connects via Facebook</p>
                </div>
              </span>
            </template>

            <div class="wpsr-accounts-connect-wrapper">
              <div class="wpsr-modal-btn-with-info">
                <el-button class="wpsr-login-with-facebook" @click.prevent="oAuthLogin" size="default" type="primary">
                <img :src="assets_url+'/images/icon/login-fb-btn.png'" alt="Continue with Facebook"></el-button>
              </div>
            </div>
            <div class="wpsr-connection-permission-wrapper">
              <el-collapse v-model="activePermissionPanel">
                <el-collapse-item title="This permissions will be granted, when you connect your user account" name="1">
                  <div class="wpsr-connection-permission-item">
                    <div class="wpsr-connection-permission-item-header">
                      <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                      <span>business_management</span>
                    </div>
                    <p>This permission is used to retrieve the names of the Facebook Pages you manage. It works alongside other permissions to enable features that require identifying and connecting your Pages.</p>
                  </div>
                  <div class="wpsr-connection-permission-item">
                    <div class="wpsr-connection-permission-item-header">
                      <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                      <span>pages_read_user_content</span>
                    </div>
                    <p>This permission allows our plugin to read user-generated content on your Facebook Page, including posts, comments made by users or other Pages.</p>
                  </div>
                  <div class="wpsr-connection-permission-item">
                    <div class="wpsr-connection-permission-item-header">
                      <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                      <span>pages_read_engagement</span>
                    </div>
                    <p>This permission allows us to access data about your Page, including follower counts, published content, and other engagement metrics.</p>
                  </div>
                  <div class="wpsr-connection-permission-item">
                    <div class="wpsr-connection-permission-item-header">
                      <el-icon size="16" color="var(--wpsr-icon-disabled-color)"><Key /></el-icon>
                      <span>pages_show_list</span>
                    </div>
                    <p>This permission is used to retrieve the names of the Facebook Pages you manage, enabling you to connect and manage multiple Pages at the same time.</p>
                  </div>
                </el-collapse-item>
              </el-collapse>
            </div>
            <div class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
              <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
              <p>This permission does not grant us control over your Facebook Pages. It only lets the plugin view a list of your Pages and access their public content through the API.</p>
            </div>
          </el-tab-pane>

          <el-tab-pane label="Account  Manually" name="manual">
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
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Access Token') }}
                    <el-tooltip
                        :raw-content="true"
                        effect="dark"
                        placement="right-start"
                        :content="$t('Please enter the valid Facebook Access Token.')"
                    >
                      <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                    </el-tooltip>
                  </h4>
                  <p class="wpsr-connection-modal-input-description">Connecting a client's account? Avoid sharing accounts password, please use our <a href="https://wpsocialninja.com/access-token-generator/?id=facebook-feed" target="_blank">access token generator</a>.</p>
                </div>
                <InputPassword class="wpsr-mb-10" type="text" size="default" v-model="fb_access_token" placeholder="Enter a valid Access Token"></InputPassword>
              </div>
              <div class="wpsr-modal-group-btn">
                <el-button :disabled="!fb_access_token" class="wpsr_primary_btn" size="default" type="success" @click="saveVerificationConfigs">
                  {{ $t('Connect') }}
                </el-button>
              </div>
            </div>
          </el-tab-pane>

          <el-tab-pane label="Connect Events Access Token" name="event_feed">
            <template #label>
              <span class="wpsr-connections-tabs-label">
                <el-icon size="18"><Calendar /></el-icon>
                 <div class="wpsr-connections-tabs-label-group">
                    <span>Connect Events Access Token {{!has_pro ? '(Pro)' : ''}}</span>
                    <p>Connect via Token</p>
                 </div>
              </span>
            </template>
            <div class="wpsr-accounts-connect-wrapper">
              <div class="wpsr-connection-modal-input-wrapper">
                <div class="wpsr-connection-modal-input-heading-wrapper">
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Facebook Page ID') }}
                    <el-tooltip
                        :raw-content="true"
                        effect="dark"
                        placement="right-start"
                        :content="$t('Please enter a valid Facebook Page ID.')"
                    >
                      <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                    </el-tooltip>
                  </h4>
                </div>
                <InputPassword :disabled="!has_pro" class="wpsr-mb-10" type="text" size="default" v-model="fb_page_id" placeholder="Enter a Facebook Page ID"></InputPassword>
              </div>
              <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                <div class="wpsr-connection-modal-input-heading-wrapper">
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Events Access Token') }}
                    <el-tooltip
                        :raw-content="true"
                        effect="dark"
                        placement="right-start"
                        :content="$t('Please enter a valid Facebook Event Access Token.')"
                    >
                      <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                    </el-tooltip>
                  </h4>
                  <p class="wpsr-connection-modal-input-description">
                    Due to restrictions by facebook you need to create a Facebook app and then paste that app Access Token in our Facebook Configuration.
                    Please follow the <a href="https://wpsocialninja.com/docs/facebook-events-access-token/" target="_blank">documentation</a> to connect an event feed.
                  </p>
                </div>
                <InputPassword :disabled="!has_pro" class="wpsr-mb-10" type="text" size="default" v-model="fb_event_access_token"
                               placeholder="Enter Events Access Token"></InputPassword>
              </div>
              <div class="wpsr-modal-group-btn wpsr-mt-20">
                <el-button :disabled="!fb_event_access_token || !fb_page_id" class="wpsr_primary_btn" size="default" type="success" @click="saveVerificationConfigs">
                  {{ $t('Connect') }}
                </el-button>
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
      <!-- end add new account pages -->

      <!-- start inner dialog -->
      <el-dialog
          title="Facebook Authorized Pages"
          class="wpsr-inner-modal"
          v-model="businessAccountsModal"
          v-if="Object.keys(authorized_source_list).length"
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
          <div :class="Object.keys(authorized_source_list).length > 5 ? 'wpsr-scrollbar' : ''">
          <el-checkbox
              class="wpsr_primary_checkbox_or_radio"
              v-if="Object.keys(authorized_source_list).length > 1"
             :indeterminate="isIndeterminate"
             v-model="selectAll"
             @change="handleCheckAllChange"
          >
            Select all
          </el-checkbox>
          <el-checkbox-group v-model="selectedAccounts" @change="handleSelectedAccountsChange">
            <el-checkbox
                class="wpsr_primary_checkbox_or_radio"
                v-for="(page, index) in authorized_source_list"
                :label="page"
                :key="page.page_id"
            >
              <template v-if="authorized_source_list[index]">
                <div class="wpsr-user-profile-pic">
                  <img v-if="page.page_id" :src="'https://graph.facebook.com/'+page.page_id+'/picture'">
                </div>
                <div class="wpsr-user-details">
                  <p v-if="page.name">{{page.name}}</p>
                  <span style="font-size: 12px; text-transform: uppercase">{{page.type}}</span>
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
                     size="default" type="success" @click="saveVerificationConfigs">{{ $t('Connect Pages') }}
          </el-button>
        </div>
      </el-dialog>
      <!-- end inner dialog -->

      <!-- start create template -->
      <div class="wpsr-make-template-btn-wrap" v-if="Object.keys(connected_source_list).length">
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

      <!-- end create template -->

    </div>
    </div>

    <div class="wpsr-connection-modal-footer">
      <div class="wpsr-docs-help">
        <p>Need Help?</p>
        <div class="wpsr-docs-help-links">
          <a href="https://wpsocialninja.com/docs/facebook-feed-integration-with-wp-social-ninja/" target="_blank">{{ $t('Read Documentation') }}</a>
          <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
          <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
        </div>
      </div>
    </div>
  </el-dialog>
</template>

<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from '../../../core-ui/editor/InputPassword';
import ShieldCheckLineIcon from '../../../pieces/icons/ShieldCheckLineIcon';
import CreateTemplateIcon from  '../../../pieces/icons/CreateTemplateIcon.vue';
import {Calendar} from "@element-plus/icons-vue";

export default {
  props:['facebookModal', 'access_token'],
  data(){
    return {
      loading: false,
      inner_loading: false,
      config: {},
      isConnected: false,
      connected_source_list: [],
      page_id: '',
      admin_page_url: this.appVars.admin_page_url,
      accessToken: this.access_token,
      fb_access_token: '',
      fb_page_id: '',
      fb_event_access_token: '',
      verification_configs: [],
      _businessAccountsModal: false,
      isIndeterminate: true,
      selectAll: false,
      selectedAccounts: [],
      ig_account_id: null,
      authorized_source_list: [],
      addNewAccount: false,
      hideInnerModal: false,
      credentialsType: 'oauth',
      activePermissionPanel: [],
      template_id: null,
    }
  },
  components: {
    Calendar,
    Remove,
    InputPassword,
    ShieldCheckLineIcon,
    CreateTemplateIcon
  },
  methods: {
    oAuthLogin() {
        const currentUrl = window.location.href;
        const isEditor = currentUrl.includes('#/edit-facebook-feed-template/');

        let callBackUrl;
        if (isEditor) {
          const baseUrl = window.location.origin + window.location.pathname;
          let templateId = null;
          const hashMatch = window.location.hash.match(/edit-facebook-feed-template\/?(\d+)/);
          if (hashMatch && hashMatch[1]) {
            templateId = hashMatch[1];
          } else {
            templateId = this.template_id || '';
          }
          // Construct callback URL in the format expected by the OAuth redirect API
          callBackUrl = `${baseUrl}?page=wpsocialninja.php&edit-facebook-feed-template&${templateId}`;
        } else {
          callBackUrl = this.admin_page_url;
        }

        let clientId = '443259723723907';
        let redirectUrl = 'https://wpsocialninja.com/api/facebook-feed-graph-api-redirect.php';
        window.open('https://graph.facebook.com/oauth/authorize?client_id='+ clientId +'&redirect_uri='+ redirectUrl +'&scope=pages_read_engagement,pages_read_user_content,pages_show_list,business_management&state=' + encodeURIComponent(callBackUrl), '_self');
    },
    addAccount() {
      this.addNewAccount = !this.addNewAccount;
    },
    handleCheckAllChange(value) {
      this.selectedAccounts = value ? this.authorized_source_list : [];
      this.isIndeterminate = false;
      this.accessToken = '';
    },
    handleSelectedAccountsChange(value) {
      this.selectedAccounts = value;
      this.accessToken = value.access_token;
      this.ig_account_id = value.id;
      let checkedCount = Object.keys(value).length;
      this.selectAll = checkedCount === Object.keys(this.authorized_source_list).length;
      this.isIndeterminate = checkedCount > 0 && checkedCount < Object.keys(this.authorized_source_list).length;
    },
    saveVerificationConfigs() {
      this.loading = true;
      this.inner_loading = true;

      let filteredAccessToken = '';

      if (this.credentialsType === 'manual') {
        filteredAccessToken = this.fb_access_token;
      } else if (this.credentialsType === 'event_feed') {
        filteredAccessToken = {
          access_token: this.fb_event_access_token,
          page_id: this.fb_page_id
        };
      } else {
        filteredAccessToken = this.accessToken;
      }

      let settings = {
        access_token: filteredAccessToken,
        selectedAccounts: this.selectedAccounts,
        connectionType: this.credentialsType,
      };

      this.$post('platforms/feeds/configs', {
        settings: JSON.stringify(settings),
        platform:'facebook_feed'
      }).then(response => {
          if(response.data) {
            this.getVerificationConfigs();
            this.handleSuccess(response.data.message);
            this.fb_access_token = '';
            if (this.credentialsType === 'event_feed') {
              this.fb_page_id = '';
              this.fb_event_access_token = '';
            }
          }
          if(response.data.status || this.hideInnerModal){
            this.isIndeterminate = false;
            this.selectAll =  false;
            this.businessAccountsModal = false;
          }
        }).catch(error => {
          this.handleError(error);
        }).always(() => {
          this.loading = false;
          this.inner_loading = false;
        });
    },

    getVerificationConfigs() {
      this.loading = true;
      const queryString = window.location.search;
      const urlParams = new URLSearchParams(queryString);

      this.$get('platforms/feeds/configs', {
        platform: 'facebook_feed'
      }).then(response => {
            if(response.data) {
              if(response.data.authorized_source_list){
                this.authorized_source_list = response.data.authorized_source_list;
              }

              if(response.data.connected_source_list){
                this.connected_source_list = response.data.connected_source_list;
              }

              if(response.data.settings){
                this.verification_configs = response.data.settings;
              }

              if(this.connected_source_list){
                this.isConnected = true;
              }

              // Only automatically open businessAccountsModal when templateId is present in URL
              if (this.isConnected && this.accessToken) {
                const hasTemplateId = this.checkForTemplateId();
                if (hasTemplateId) {
                  this.businessAccountsModal = true;
                  this.hideInnerModal = true;
                } else {
                  setTimeout(() => {
                    this.businessAccountsModal = true;
                    this.hideInnerModal = true;
                  }, 100);
                }
              }

              this.$emit('findEnabledPlatforms');
            }
          }).catch(error => {
            this.handleError(error);
          }).always(() => {
            this.loading = false;
          });
    },

    clearVerificationConfigs(page_id) {
      this.$del('platforms/feeds/configs', {
        platform: 'facebook_feed',
        user_id: page_id
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
        platform:'facebook_feed',
        type: 'feed'
      }).then(response => {
          if(response && response.template_id) {
            this.$router.push({
              name: 'edit-facebook-feed-template',
              params: {
                template_id: response.template_id
              }
            })
            // close modal after successful template creation
            this.facebook_modal = false;
          }
      }).catch(error => {
        this.handleError(error);
      }).always(() => {

      });
    },
    reConnectAccount(){
      this.reConnect = !this.reConnect;
    },

    checkForTemplateId() {
      // Check if templateId is present in URL (either in main query or hash)
      const queryString = window.location.search;
      const urlParams = new URLSearchParams(queryString);
      let templateId = urlParams.get('templateId');
      
      if (!templateId) {
        // Check hash parameters
        const hash = window.location.hash;
        const hashMatch = hash.match(/edit-facebook-feed-template\/?(\d+)/);
        if (hashMatch && hashMatch[1]) {
          templateId = hashMatch[1];
        }
      }
      
      return !!templateId;
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
  },
  computed: {
    facebook_modal: {
      get() {
        return this.facebookModal
      },
      set(newVal){
        this.$emit('facebook_settings', newVal);
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
    'facebook_modal': {
      handler(newVal) {
        // Only automatically open businessAccountsModal when configuration parameter is present in URL
        if (newVal && this.credentialsType === 'oauth') {
          // Check if configuration parameter is present in URL
          const hasConfigurationParam = this.checkForConfigurationParameter();
          
          if (hasConfigurationParam) {
            // Wait for business accounts data to be loaded
            const checkBusinessAccounts = () => {
              if (this.authorized_source_list && Object.keys(this.authorized_source_list).length > 0) {
                this.businessAccountsModal = true;
                // Clear configuration parameter from URL after modal opens
                this.clearConfigurationParameter();
              } else {
                setTimeout(checkBusinessAccounts, 500);
              }
            };
            // Start checking after a short delay to allow data loading to begin
            setTimeout(checkBusinessAccounts, 100);
          }
        }
      },
      immediate: true
    }
  },
  mounted(){
    if (this.accessToken !== '') {
      this.saveVerificationConfigs();
    }
   // this.businessAccountsModal = true;
    this.getVerificationConfigs();
    this.addNewAccount = false;
  }
}
</script>
