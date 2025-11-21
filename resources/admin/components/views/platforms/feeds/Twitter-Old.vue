<template>
    <el-dialog :title="$t('X (Twitter) Configuration')" :visible.sync="twitter_modal">
        <div v-loading="loading" element-loading-text="Please wait.">
            <el-button
                v-if="(!isManualConnect && isConnected) || (isManualConnect && isManuallyConnected)"
                class="wpsr-connect-twitter"
                type='default'
                size="default"
                icon="el-icon-refresh"
                @click="reConnectAccount"
            >
              {{ $t('Connect new account')}}
            </el-button>
            <div v-if="(reConnect) || (!consumerKey)">
                <el-button  class="wpsr-connect-twitter" type="primary" size="default" @click.prevent = "redirectToAuthorizeUrl">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect style="fill:none;"  width="40" height="40"/><path d="M153.62,301.59c94.34,0,145.94-78.16,145.94-145.94,0-2.22,0-4.43-.15-6.63A104.36,104.36,0,0,0,325,122.47a102.38,102.38,0,0,1-29.46,8.07,51.47,51.47,0,0,0,22.55-28.37,102.79,102.79,0,0,1-32.57,12.45,51.34,51.34,0,0,0-87.41,46.78A145.62,145.62,0,0,1,92.4,107.81a51.33,51.33,0,0,0,15.88,68.47A50.91,50.91,0,0,1,85,169.86c0,.21,0,.43,0,.65a51.31,51.31,0,0,0,41.15,50.28,51.21,51.21,0,0,1-23.16.88,51.35,51.35,0,0,0,47.92,35.62,102.92,102.92,0,0,1-63.7,22A104.41,104.41,0,0,1,75,278.55a145.21,145.21,0,0,0,78.62,23"/></svg>
                  {{$t('Log in to X (Twitter) And Get My Access Token & Secret Key')}}
                </el-button>
                <p class="wpsr-twitter-feed-cradential-type-manually">
                  <span>{{$t('OR')}}</span>
                  <el-checkbox v-model="isManualConnect">{{$t('Manually enter your')}} <a href="https://wpsocialninja.com/docs/create-your-twitter-app-wp-social-ninja/" target="_blank">{{$t('X (Twitter) App')}}</a> {{$t('information')}}</el-checkbox>
                </p>
            </div>

            <div :class="(!isManualConnect && isConnected) || (isManualConnect && isManuallyConnected) ? 'wpsr-twitter-connected' : '' ">
              <!-- start of manual connection input fields -->
              <div v-if="isManualConnect && !isManuallyConnected">
                    <h4>{{$t('Consumer Key(API Key):')}}<el-tooltip class="item" effect="dark" :content="$t('A Consumer Key is needed, if you want to use credentials from your own X (Twitter) App')" placement="right-start">
                        <i class="el-icon-info"></i>
                    </el-tooltip></h4>
                    <InputPassword
                        id="consumer_key"
                        v-model="consumerKey"
                        placeholder="Enter your consumer key"
                        autocomplete="off"
                        class="wpsr-mb-20"
                    />
                    <h4>{{$t('Consumer Secret(API Secret Key):')}}<el-tooltip class="item" effect="dark" :content="$t('A Consumer Secret is needed, if you want to use credentials from your own X (Twitter) App')" placement="right-start">
                        <i class="el-icon-info"></i>
                    </el-tooltip></h4>
                    <InputPassword
                        id="consumer_secret"
                        v-model="consumerSecret"
                        placeholder="Enter your consumer secret key"
                        autocomplete="off"
                        class="wpsr-mb-20"
                    />
                </div>
                <!-- end of manual connection input fields -->

                <div v-if="isManualConnect && !isManuallyConnected">
                <h4>{{$t('Access Token:')}} <el-tooltip class="item" effect="dark" :content="$t('This will allow the plugin to connect to the X (Twitter) API')" placement="right-start">
                    <i class="el-icon-info"></i>
                </el-tooltip></h4>
                  <InputPassword
                      id="oauth_token"
                      v-model="oauthToken"
                      placeholder="Enter your token key"
                      autocomplete="off"
                      class="wpsr-mb-20"
                  />
                <h4>{{$t('Access Token Secret:')}} <el-tooltip class="item" effect="dark" :content="$t('This will allow the plugin to connect to the X (Twitter) API')" placement="right-start">
                    <i class="el-icon-info"></i>
                </el-tooltip></h4>
                  <InputPassword
                      id="oauth_token_secret"
                      v-model="oauthTokenSecret"
                      placeholder="Enter your token secret key"
                      autocomplete="off"
                      class="wpsr-mb-10"
                  />
                </div>
                <div v-if="(!isManualConnect && isConnected) || (isManualConnect && isManuallyConnected)">
                  <h4>{{ $t('Your Connected Account:') }}</h4>
                  <div class="wpsr-connected-accounts">
                    <div class="wpsr-user-profile">
                      <div class="wpsr-user-details">
                        <h3><i class="user-profile-icon el-icon-user-solid"></i> {{ screenName ? screenName : $t('Successfully Connected') }}</h3>
                      </div>
                      <div class="wpsr-user-actions">
                        <remove @confirm="clearVerificationConfigs" platform="twitter"></remove>
                      </div>
                    </div>
                  </div>
                </div>
                <el-button v-if="(isManualConnect && !isManuallyConnected)" size="default" type="success" @click="saveConfigs">{{ $t('Save') }}</el-button>

                <div class="wpsr-modal-group-btn wpsr-place-details" v-if=" (!isManualConnect && isConnected) || (isManualConnect && isManuallyConnected)">
                    <div class="wpsr-make-template-btn-wrap">
                      <p>Let’s create an awesome template to be added to your site!</p>
                      <div class="wpsr-modal-group-btn">
                        <el-button
                            class="wpsr-save-cradentials"
                            type="primary"
                            size="default"
                            @click.prevent = "addNewTemplate"
                        >
                          {{ $t('Add New Template') }}
                        </el-button>

                      </div>
                    </div>
                </div>
            </div>

<!--            <p><strong>{{$t('How WPSN Works: ')}}</strong> {{$t('Connect Your Account-> Create Your Template-> Customize Your Template-> Save Template-> Copy shortcode-> Embed it on your website.')}}</p>-->
            <p v-if="!isManualConnect"
               class="wpsr-modal-fullwidth-text wpsr-feed-info-text">
               <strong>{{ $t('Note:') }}</strong>
               {{ $t('The WP SOCIAL NINJA plugin requires "read only" access to your X (Twitter) account in order to retrieve data from the X (Twitter) API. Our plugin is only able to read your account public data and never ever edit or write to your X (Twitter) account data in any way.')}}</p>
            <p class="wpsr-docs-help">{{ $t('Need Help?') }}
              <a href="https://wpsocialninja.com/docs/twitter-configuration-twitter-feeds-wp-social-ninja/" target="_blank">{{ $t('Read Documentation') }}</a>
              | <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
              | <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
            </p>
        </div>
    </el-dialog>
</template>

<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import InputPassword from '../../../core-ui/editor/InputPassword'

export default {
    name: 'TwitterConfig',
    props:['twitterModal'],
    data(){
        return{
            loading: false,
            twitterConfig:{},
            consumerKey:'',
            consumerSecret:'',
            oauthTokenSecret:'',
            oauthToken:'',
            screenName:'',
            isManualConnect:false,
            isConnected:false,
            isManuallyConnected:false,
            authorize_url: this.appVars.twitter_authorize_url,
            admin_page_url: this.appVars.admin_page_url,
            reConnect: false,
            //credential type
            connectionType: false,
            disabled: false,

        }
    },
    components:{
        Remove,
        InputPassword
    },
    methods:{
        redirectToAuthorizeUrl(){
            window.open(this.authorize_url,"_self");
        },
        getVerificationConfigs(){
            this.loading = true;
            this.$get('platforms/feeds/configs', {
                platform: 'twitter'
            })
                .then(response => {
                    if(response.data) {
                        this.twitterConfig = response.data.settings;
                        if(this.twitterConfig){
                            //old configs
                            if(this.twitterConfig.dynamic){
                                this.oauthTokenSecret = this.twitterConfig.dynamic.oauth_access_token_secret;
                                this.oauthToken = this.twitterConfig.dynamic.oauth_access_token;
                                this.consumerKey = this.twitterConfig.dynamic.consumer_key;
                                this.consumerSecret = this.twitterConfig.dynamic.consumer_secret;
                                this.screenName = this.twitterConfig.dynamic.screen_name;

                                this.isManualConnect = this.twitterConfig.dynamic.manual_connect;
                                this.connectionType = this.twitterConfig.dynamic.manual_connect;
                                this.isManuallyConnected = false;
                                this.isConnected = false;

                                if( this.oauthToken && this.oauthTokenSecret && !this.isManualConnect){
                                    this.isManuallyConnected = false;
                                    this.isConnected = true;
                                }

                                if( this.consumerKey && this.consumerSecret && this.oauthToken && this.oauthTokenSecret && this.isManualConnect){
                                    this.isConnected = false;
                                    this.isManuallyConnected = true;
                                }
                            }
                        }else {
                            this.consumerKey = '';
                            this.consumerSecret = '';
                            this.isManualConnect = false;
                            this.connectionType = false;
                            this.oauthToken = '';
                            this.oauthTokenSecret = '';
                            this.isConnected = false;
                        }
                        this.$emit('findEnabledPlatforms');
                    }
                })
                .catch( (errors) => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        saveVerificationConfigs(args){
            this.$post('platforms/feeds/configs', {
                settings: JSON.stringify(args),
                platform:'twitter'
            })
                .then(response => {
                    if(response.data) {
                      this.twitterConfig = response.data.settings;
                      if(this.twitterConfig){
                          this.getVerificationConfigs();
                          window.history.pushState({}, null, this.admin_page_url);
                      }
                      this.handleSuccess(response.data.message);
                    }
                })
                .catch( (errors) => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.twitter_modal = true;
                });
      },
        saveConfigs() {
            if( this.oauthToken && this.oauthTokenSecret && this.consumerKey && this.consumerSecret ) {
                let args = {
                    'oauth_access_token':this.oauthToken,
                    'oauth_access_token_secret':this.oauthTokenSecret,
                    'screen_name':'',
                    'platform':'twitter',
                    'consumer_key':this.consumerKey,
                    'consumer_secret':this.consumerSecret,
                    'manual_connect':this.isManualConnect
                };
                this.saveVerificationConfigs(args);
            }else {
                let message = 'Please fill the fields correctly!!';
                this.handleError(message);
            }
        },
        clearVerificationConfigs(){
            this.$del('platforms/feeds/configs', {
                platform: 'twitter'
            })
              .then(response => {
                  if(response.data) {
                      this.isManuallyConnected = false;
                      this.getVerificationConfigs();
                      this.handleSuccess(response.data.message);
                  }
              })
              .catch( (errors) => {
                  this.handleError(errors);
              })
              .always(() => {

              });
      },
        addNewTemplate(){
            this.$post('templates',{
                platform:'twitter'
            })
              .then(response => {
                  if(response) {
                      if(response.template_id){
                          this.$router.push({
                              name: 'edit-twitter-template',
                              params: {
                                template_id: response.template_id
                              }
                          })
                      }
                  }
              }).catch( (errors) => {
                  this.handleError(errors);
              }).always(() => {

              });
        },
        reConnectAccount(){
          this.reConnect = !this.reConnect;
        }
    },
    created(){
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        let oauth_token =  urlParams.get('oauth_token');
        let oauth_token_secret =  urlParams.get('oauth_token_secret');
        let screen_name =  urlParams.get('screen_name');

        if( oauth_token && oauth_token_secret && screen_name ){
            //after get data we have to save them
            let args = {
                'oauth_access_token':oauth_token,
                'oauth_access_token_secret':oauth_token_secret,
                'screen_name':screen_name,
                'manual_connect': false,
                'consumer_key':'',
                'consumer_secret':'',
                'platform':'twitter'
            };
            this.saveVerificationConfigs(args);
        }
    },
    computed:{
        twitter_modal:{
            get(){
                return this.twitterModal
            },
            set(newVal){
                this.$emit('twitter_settings', newVal);
            }
        }
    },
    mounted(){
       this.getVerificationConfigs();
    }
}
</script>
