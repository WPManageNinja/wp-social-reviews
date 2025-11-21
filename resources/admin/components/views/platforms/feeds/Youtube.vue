<template>
    <el-dialog class="wpsr-connection-modal" width="50%"  title="YouTube Configuration" v-model="youtube_modal">
      <template #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">YouTube Configuration</h4>
          <p class="wpsr-connection-modal-subheading">Manage and connect your YouTube account settings</p>
        </div>
      </template>
      <div class="wpsr-connection-modal-body" :class="!isConnected ? 'wpsr-gap-0' : ''">
      <div v-loading="loading" element-loading-text="Please wait.">
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
                      <el-icon><UserFilled /></el-icon>
                    </div>
                    <div class="wpsr-user-details">
                      <div class="wpsr-user-details-inner">
                        <div class="wpsr-user-details-info wpsr-flex-align-center">
                          <h3>{{ $t('Successfully Connected') }}</h3>
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
                        <h3>Get Your Google Access Code</h3>
                        <p>Simply click the button below to instantly receive your Google access code—it only takes one click.</p>
                      </div>
                      <div class="wpsr-connection-modal-input-heading-wrapper">
                        <el-button class="wpsr-mb-20" type="primary" size="default" @click="loginGoogle">{{ $t('Sign In And Get Google Access Code') }}</el-button>
                        <h4 class="wpsr-connection-modal-input-heading">
                          {{ $t('Access Code') }}
                        </h4>
                      </div>
                      <InputPassword
                          id="access_code"
                          v-model="accessCode"
                          placeholder="Enter your access code"
                          @keyup.enter.native="saveVerificationConfigs"
                      />
                    </div>
                  </div>
                </el-tab-pane>
<!--                <el-tab-pane name="manually_connect">-->
<!--                  <template #label>-->
<!--                    <span class="wpsr-connections-tabs-label">-->
<!--                       <el-icon size="18"><Key /></el-icon>-->
<!--                      <div class="wpsr-connections-tabs-label-group">-->
<!--                        <span>Account Manually</span>-->
<!--                        <p>Connect via Token</p>-->
<!--                      </div>-->
<!--                    </span>-->
<!--                  </template>-->
<!--                  <div class="wpsr-accounts-connect-wrapper">-->
<!--                    <div class="wpsr-connection-modal-input-wrapper">-->
<!--                      <div class="wpsr-connection-modal-input-heading-wrapper">-->
<!--                        <h4 class="wpsr-connection-modal-input-heading">-->
<!--                          {{ $t('Access Token') }}-->
<!--                          <el-tooltip-->
<!--                              :raw-content="true"-->
<!--                              effect="dark"-->
<!--                              placement="right-start"-->
<!--                              :content="$t('Please enter a valid Access Token.')"-->
<!--                          >-->
<!--                            <el-icon size="18" color="var(&#45;&#45;wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>-->
<!--                          </el-tooltip>-->
<!--                        </h4>-->
<!--                      </div>-->
<!--                      <InputPassword-->
<!--                          id="access_token"-->
<!--                          v-model="accessToken"-->
<!--                          placeholder="Enter a valid access token"-->
<!--                          @keyup.enter.native="saveVerificationConfigs"-->
<!--                      />-->
<!--                    </div>-->
<!--                  </div>-->
<!--                </el-tab-pane>-->
              </el-tabs>
            </div>

          <el-button
              v-if="!isConnected || reConnect"
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
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from '../../../core-ui/editor/InputPassword';
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";
import {ElNotification} from "element-plus";
import CreateTemplateIcon from "../../../pieces/icons/CreateTemplateIcon.vue";

export default {
    data(){
        return {
            loading: false,
            config: {},
            credentialsType: 'api_key',
            accessCode:'',
            accessToken:'',
            oauthAccessToken: false,
            oauthAccessCode: false,
            manualAccessToken: false,
            isConnected: false,
            hasApikey: false,
            apiKey: '',
            channelId: '',
            disabled: false,
            reConnect: false,
        }
    },
    components:{
        CreateTemplateIcon,
        ShieldCheckLineIcon,
        Remove,
        InputPassword,
        ElNotification
    },
    props:['youtubeModal'],
    methods: {
      getDisableState() {
        if (this.credentialsType === 'api_key') {
          return !this.apiKey;
        } else if (this.credentialsType === 'oauth2.0') {
          return !this.accessCode;
        }
        return false;
      },
      loginGoogle(){
            window.open('https://accounts.google.com/o/oauth2/v2/auth?access_type=offline&prompt=consent&client_id=324795500171-938tfnrb8bfna4n9hrfmtncq17vnhpf7.apps.googleusercontent.com&redirect_uri=https%3A%2F%2Fwpsocialninja.com%2Fgapi%2F&response_type=code&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fyoutube.readonly');
        },
        saveVerificationConfigs(){
              this.loading = true;
              let settings = {
                  accessCode: this.accessCode,
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
                      // if(response.data.settings && response.data.settings.credentials_type !== undefined){
                      //     this.credentialsType = response.data.settings.credentials_type;
                      // }

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
                          this.accessCode = '';
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
                      this.accessCode = '';
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
                      // close modal after successful template creation
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