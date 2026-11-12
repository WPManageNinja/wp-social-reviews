<template>
    <AuthRedirectOverlay
        :visible="googleAuthLoading"
        :title="`${$t('Redirecting to')} ${authServiceHost}`"
        :subtitle="$t('You will be redirected to our authentication portal. This may take up to 5 seconds.')"
    />

    <el-dialog class="wpsr-connection-modal" width="50%"  title="YouTube Configuration" v-model="youtube_modal">
      <template #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">YouTube Configuration</h4>
          <p class="wpsr-connection-modal-subheading">Manage and connect your YouTube account settings</p>
        </div>
      </template>
      <div class="wpsr-connection-modal-body" :class="!isConnected ? 'wpsr-gap-0' : ''">
      <div v-loading="loading" element-loading-text="Please wait.">
            <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mb-20" v-if="isConnected && config.needs_reconnect">
              <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
              <p>
                <strong>{{ $t('Notice') }}</strong>
                {{ $t('This YouTube connection uses our previous authorization app, which we will retire soon. Click Connect new account → OAuth 2.0 → Connect YouTube Channel and sign in with the same Google account to switch. Your templates and settings stay as they are.') }}
              </p>
            </div>
            <div v-if="(isConnected && credentialsType === 'oauth2.0')
                || ( isConnected && credentialsType === 'manually_connect')
                || (isConnected && credentialsType === 'api_key')
            ">
              <div class="wpsr-user-accounts-heading">
                <h4>{{ $t('Your Account') }}</h4>
              </div>
              <div class="wpsr-connected-accounts">
                <div class="wpsr-user-profile">
                  <div class="wpsr-user-profile-inner">
                    <div class="wpsr-user-profile-pic">
                      <img v-if="config.user_avatar" :src="config.user_avatar" :alt="config.user_name || 'YouTube'">
                      <el-icon v-else><UserFilled /></el-icon>
                    </div>
                    <div class="wpsr-user-details">
                      <div class="wpsr-user-details-inner">
                        <div class="wpsr-user-details-info">
                          <h3>{{ config.user_name || $t('Successfully Connected') }} </h3>
                        </div>
                        <div class="wpsr-user-actions">
                          <remove @confirm="clearVerificationConfigs" platform="youtube"></remove>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <el-button
                v-if="(isConnected)"
                style="width: 100%"
                class="wpsr_primary_btn_outline"
                type='success'
                :class="{'display-form': reConnect}"
                @click="reConnectAccount"
            >
              <el-icon size="18"><Link /></el-icon>
              {{ $t('Connect new account')}}
            </el-button>

            <div v-if="reConnect || !isConnected" class="wpsr-fb-page-list wpsr-mb-20">
              <el-tabs v-model="credentialsType" class="wpsr-connections-tabs wpsr-gap-0">
                <el-tab-pane name="api_key">
                  <template #label>
                    <span class="wpsr-connections-tabs-label">
                       <el-icon style="transform: rotate(90deg)" size="18"><Key /></el-icon>
                      <div class="wpsr-connections-tabs-label-group">
                        <span>API Key (Recommended)</span>
                        <p>Connect via Token</p>
                      </div>
                    </span>
                  </template>
                  <div class="wpsr-accounts-connect-wrapper">
                    <div class="wpsr-connection-modal-input-wrapper">
                      <div class="wpsr-connection-modal-input-heading-wrapper">
                        <h4 class="wpsr-connection-modal-input-heading">
                          {{ $t('API Key') }}
                          <el-tooltip
                              :raw-content="true"
                              effect="dark"
                              placement="right-start"
                              :content="$t('Please enter a valid Api Key.')"
                          >
                            <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                          </el-tooltip>
                        </h4>
                        <p class="wpsr-connection-modal-input-description">Learn more about how to create and add an API Key by exploring our detailed <a href="https://wpsocialninja.com/docs/youtube-configuration-youtube-feeds-wp-social-ninja/#6-toc-title" target="_blank">instructions</a>.</p>
                      </div>
                      <InputPassword
                          id="api_key"
                          v-model="apiKey"
                          placeholder="Enter your api key"
                          @keyup.enter.native="saveVerificationConfigs"
                      />
                    </div>
                  </div>
                </el-tab-pane>
                <el-tab-pane name="oauth2.0">
                  <template #label>
                    <span class="wpsr-connections-tabs-label">
                      <i class="wpsr-icon"><ShieldCheckLineIcon/></i>
                      <div class="wpsr-connections-tabs-label-group">
                        <span>OAuth 2.0 (Deprecated Soon)</span>
                        <p>Connect via Google</p>
                      </div>
                    </span>
                  </template>
                  <div class="wpsr-accounts-connect-wrapper">
                    <div class="wpsr-connection-modal-input-wrapper">
                      <div class="wpsr-connection-button-info-wrapper">
                        <h3>{{ $t('Display Your YouTube Videos') }}</h3>
                        <p>{{ $t('Connect your YouTube account to show videos, playlists, and live streams from your channels.') }}</p>
                      </div>
                      <div class="wpsr-connection-modal-input-heading-wrapper">
                        <el-button class="wpsr-mb-20" type="primary" size="default" :loading="googleAuthLoading" :disabled="googleAuthLoading" @click="loginGoogle">{{ $t('Connect YouTube Channel') }}</el-button>
                        <p class="wpsr-connect-redirect-note">{{ $t('You will be redirected to WP Social Ninja, then to Google to approve access.') }}</p>
                      </div>
                    </div>
                  </div>
                </el-tab-pane>
              </el-tabs>
            </div>

          <el-button
              v-if="(!isConnected || reConnect) && credentialsType !== 'oauth2.0'"
              size="default"
              type="success"
              class="wpsr_primary_btn"
              @click="saveVerificationConfigs"
              :disabled="getDisableState()"
          >
            {{ $t('Save') }}
          </el-button>

          <div v-if="credentialsType === 'oauth2.0'" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
            <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
            <p><strong>Note:</strong>The WP SOCIAL NINJA plugin OAuth2.0 credentials type requires "read only" access to your YouTube account in order to retrieve data from the YouTube API. Our plugin is only able to read your account public data and never ever edit or write to your YouTube account data in any way.</p>
          </div>

          <div class="wpsr-make-template-btn-wrap" v-if="isConnected">
            <h3>Let’s create an awesome template to be added to your site!</h3>
            <p>Create a stunning, fully customizable template to perfectly match your website’s style.</p>
            <div class="wpsr-modal-group-btn">
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
      </div>

      <div class="wpsr-connection-modal-footer">
        <div class="wpsr-docs-help">
          <p>Need Help? </p>
          <div class="wpsr-docs-help-links">
            <a href="https://wpsocialninja.com/docs/youtube-configuration-youtube-feeds-wp-social-ninja/" target="_blank">{{ $t('Read Documentation') }}</a>
            <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
            <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
          </div>
        </div>
      </div>

    </el-dialog>
</template>

<script type="text/babel">
import AuthRedirectOverlay from '../../../core-ui/AuthRedirectOverlay';
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from '../../../core-ui/editor/InputPassword';
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";
import InfoIcon from "../../../pieces/icons/InfoIcon.vue";
import {ElNotification} from "element-plus";
import CreateTemplateIcon from "../../../pieces/icons/CreateTemplateIcon.vue";
import { wpsrBuildAuthReturnUrl as buildWpsrAuthReturnUrl, wpsrSubmitAuthForm } from "../../../../utils";

export default {
    data(){
        return {
            loading: false,
            googleAuthLoading: false,
            config: {},
            credentialsType: 'api_key',
            accessToken:'',
            oauthAccessToken: false,
            oauthAccessCode: false,
            manualAccessToken: false,
            isConnected: false,
            hasApikey: false,
            admin_page_url: this.appVars.admin_page_url,
            apiKey: '',
            channelId: '',
            disabled: false,
            reConnect: false,
        }
    },
    components:{
        AuthRedirectOverlay,
        InfoIcon,
        CreateTemplateIcon,
        ShieldCheckLineIcon,
        Remove,
        InputPassword,
        ElNotification
    },
    props: {
        youtubeModal: {
            type: Boolean,
            default: false
        },
        wpsrAuthReturnUrlBuilder: {
            type: Function,
            default: null
        }
    },
    emits: ['youtube_settings', 'findEnabledPlatforms'],
    methods: {
      getDisableState() {
        if (this.credentialsType === 'api_key') {
          return !this.apiKey;
        }
        return false;
      },
      wpsrBuildAuthReturnUrl(routeName, authPlatform = '') {
            const fallbackUrl = this.admin_page_url || this.appVars.admin_page_url;

            if (typeof this.wpsrAuthReturnUrlBuilder === 'function') {
                return this.wpsrAuthReturnUrlBuilder(routeName, authPlatform, fallbackUrl);
            }

            const templateId = this.$route && this.$route.params ? this.$route.params.template_id : '';
            return buildWpsrAuthReturnUrl(routeName, authPlatform, fallbackUrl, templateId);
        },
      loginGoogle(){
            this.googleAuthLoading = true;
            this.$post('platforms/feeds/configs/prepare', {
                platform: 'youtube',
                provider: 'google',
                flow: 'youtube',
                return_url: this.wpsrBuildAuthReturnUrl('edit-youtube-template', 'youtube'),
                source: 'YouTube'
            }).then(response => {
                if (response && response.data && response.data.action) {
                    wpsrSubmitAuthForm(response.data.action, response.data.fields);
                } else {
                    this.googleAuthLoading = false;
                }
            }).catch(error => {
                this.googleAuthLoading = false;
                this.handleError(error);
            });
        },
        saveVerificationConfigs(){
              this.loading = true;
              let settings = {
                  accessToken: this.accessToken,
                  credentialsType: this.credentialsType,
                  apiKey: this.apiKey,
              };
              this.$post('platforms/feeds/configs', {
                settings: JSON.stringify(settings),
                platform:'youtube'
              })
                .then(response => {
                    if(response.data) {
                        this.getVerificationConfigs();

                        if(this.credentialsType === 'oauth2.0'){
                          ElNotification({
                            title: 'Verified',
                            dangerouslyUseHTMLString: true,
                            message: '<strong>'+response.data.message+'</strong><br>' +
                                'You may receive an email from Google notifying you that our plugin has been <br> granted read-access to your account.',
                            type: 'success',
                            offset: 80,
                            duration: 0
                          });
                        } else {
                          this.handleSuccess(response.data.message);
                        }
                    }
                }).fail(error => {
                    this.handleError(error);
                }).always(() => {
                    this.loading = false;
                });
        },

        getVerificationConfigs(){
            this.loading = true;
            this.$get('platforms/feeds/configs', {
              platform: 'youtube'
            })
              .then(response => {
                  if(response.data) {
                      this.config = response.data.settings;

                      this.oauthAccessToken = false;
                      this.oauthAccessCode = false;
                      this.manualAccessToken = false;
                      this.hasApikey = false;
                      this.reConnect = false;

                      if( response.data.settings.access_code && !this.manualAccessToken ){
                          this.accessToken = '';
                      }

                      if( response.data.settings.access_token && response.data.settings.credentials_type === 'oauth2.0'){
                          this.oauthAccessToken = true;
                          this.oauthAccessCode = true;
                      }

                      else if( response.data.settings.access_token && response.data.settings.credentials_type === 'manually_connect'){
                          this.manualAccessToken = true;
                      }

                      else if( response.data.settings.api_key && response.data.settings.credentials_type === 'api_key' ){
                          this.hasApikey = true;
                          this.apiKey = '';
                      }
                      if(response.data.settings.access_token || response.data.settings.api_key){
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

        clearVerificationConfigs(){
            this.$del('platforms/feeds/configs', {
              platform: 'youtube'
            })
              .then(response => {
                  if(response.data) {
                      this.credentialsType = 'api_key';
                      this.accessToken = '';
                      this.apiKey = '';
                      this.channelId = '';
                      this.oauthAccessToken = false;
                      this.oauthAccessCode = false;
                      this.manualAccessToken = false;
                      this.hasApikey = false;
                      this.isConnected = false;
                      this.handleSuccess(response.data.message);
                  }
              }).catch(error => {
                this.handleError(error);
              }).always(() => {

              });
        },

        addNewTemplate(){
            this.$post('templates',{
              platform:'youtube'
            })
              .then(response => {
                  if(response && response.template_id) {
                      this.$router.push({
                          name: 'edit-youtube-template',
                          params: {
                            template_id: response.template_id
                          }
                      })
                      this.youtube_modal = false;
                  }
              }).catch(error => {
                this.handleError(error);
              }).always(() => {

              });
        },
        reConnectAccount(){
            this.reConnect = !this.reConnect;
        }
    },
    computed:{
        authServiceHost() {
            try { return new URL(this.appVars.auth_service_url).hostname; } catch (e) { return this.appVars.auth_service_url; }
        },
        youtube_modal:{
            get(){
                return this.youtubeModal
            },
            set(newVal){
                this.$emit('youtube_settings', newVal);
            }
        }
    },

    mounted(){
        this.getVerificationConfigs();
    }
}
</script>
