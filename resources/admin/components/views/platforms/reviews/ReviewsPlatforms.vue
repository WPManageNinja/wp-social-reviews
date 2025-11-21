<template>
  <!-- social reviews - wrap with v-show -->
  <div class="wpsr-platforms-group" v-if="hasReviewsToShow">
    <el-row v-if="displayTitle">
      <el-col :span="24">
        <div class="wpsr-platform-title-wrapper">
          <h3 class="wpsr-platform-title">{{$t('Business Reviews')}}</h3>
          <ThumbsUpIcon/>
        </div>
      </el-col>
    </el-row>
    
    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="wpsr-platforms-loading">
      <div class="wpsr-platforms-skeleton-wrapper">
        <WpsrSkeleton :rows="10" />
      </div>
    </div>
    
    <!-- Content -->
    <div v-else-if="!isLoading" :class="connectionMode ? 'wpsr-platforms-list-group' : 'wpsr-reviews-platforms'">
      <el-row :gutter="connectionMode ? 0 : 20">
        <el-col v-for="(platform, index) in filteredPlatforms" :key="index" :span="connectionMode ? 24 : column" :class="connectionMode? 'wpsr-platform-col' : ''">
          <PlatformCard
              v-if="platform.platform !== 'custom'"
              :platform="platform"
              :is_enabled_platform="is_enabled_platform"
              :connectionMode="connectionMode"
              :isDarkModeActive="isDarkModeActive"
              :isFirst="index === 0"
              :isLast="index === filteredPlatforms.length - 1"
              @social-setting-modal="socialSettingModal(
                          platform.platform,
                          platform.platform_title,
                          activePlatformName,
                          platform.apiKey,
                          platform.sourceId,
                          platform.message,
                          platform.sourceText,
                          platform.apiUrl,
                          platform.sourceUrl,
                          platform.exampleURL,
                          platform.reviewsinfo,
                          platform.count,
                          platform.docs,
                          platform.privacy,
                          platform.termsAndConditions,
                          platforms.experiences,
                          platform.promotion
                      )"
          />
        </el-col>

        <!-- Create Custom Sources Section -->
        <el-col :span="6" v-if="shouldShowCustomSources">
          <div class="wpsr-custom-sources-card">
            <div class="wpsr-custom-sources-header">
              <h2 class="wpsr-custom-sources-title">
                Create Custom Sources (Beta)
                <div v-if="!has_pro">
                  <ProCrownIcon/>
                </div>
              </h2>
              <p class="wpsr-custom-sources-subtitle">Let's create sources for custom reviews</p>
            </div>

            <div class="wpsr-custom-sources-action">
              <button class="wpsr-add-source-button" @click="handleAddSource">
                <el-icon class="wpsr-plus-icon">
                  <Plus />
                </el-icon>
                Add Source
              </button>
            </div>
          </div>
        </el-col>

        <div class="wpsr-platform-setting-tabs wpsr-connection-modal">
          <template v-if="!has_pro && (platFormName !== 'google' && platFormName !== 'airbnb')">
            <el-dialog
                v-model="internalDialog"
                width="55%"
                class="wpsr-modal wpsr-upgrade-modal-wrapper"
            >
              <UpgradeToProModal
                  v-if="promotion"
                  :promotionInfo="promotion"
                  :platform="platFormName"
                  @close="internalDialog = false"
              />
            </el-dialog>
          </template>

          <template v-else>
            <el-dialog
                :title="platformTitle  + ' Configuration'"
                v-model="internalDialog"
                width="50%"
                class="wpsr-connection-modal"
            >
            <template #header>
              <div class="wpsr-connection-modal-header">
                <h4 class="wpsr-dialog-title">{{platformTitle}} {{$t('Configuration')}}</h4>
                <p class="wpsr-connection-modal-subheading">Manage and connect your {{platformTitle}} account settings</p>
              </div>
            </template>
            <div class="wpsr-connection-modal-body" :class="!Object.keys(this.reviewsinfo).length ? 'wpsr-gap-0' : ''">
              <div v-loading="acces_token_loader" element-loading-text="Please wait.">
                <div v-loading="loading"
                    element-loading-text="Please wait for a few minutes, reviews are being collected.">

                  <p class="wpsr-app-error-notice" v-if="platFormName === 'google' && !version"><strong>Notice</strong>: Google has changed their API policy, and we upgrade our APP using their new API, so you need to reconnect your Google Business to work properly.</p>
                  <!-- display google connected accounts -->
                 <div>
                   <!-- save google reviews -->
                   <GoogleApiForm
                       v-if="platFormName === 'google'"
                       :apiKey="apiKey"
                       v-model="location_id"
                       :platFormName="platFormName"
                       :locationList="locationList"
                       :acces_token_loader="acces_token_loader"
                       :reviewsinfo="reviewsinfo"
                       :verifyPlatform="verifyPlatform"
                       :addNewBusiness="addNewBusiness"
                       @update:addNewBusiness="addNewBusiness = $event"
                       @save-reviews="saveReviews"
                       @set-google-configuration="setGoogleConfiguration"
                       @clear-verification-credentials="clearVerificationCredentials"
                   />
                   <div v-if="platFormName === 'google'">
                     <MultipleBusinessInfo
                         :reviewsinfo="reviewsinfo"
                         :locationList="locationList"
                         :platFormName="platFormName"
                         @sync-loader="syncLoaderActivate"
                         @add-new-template="addNewTemplate"
                         @clear-verification-credentials="clearVerificationCredentials"
                         @toggle-add-business="handleToggleAddBusiness"
                     />
                   </div>
                 </div>



                  <!-- display facebook connected accounts -->
                  <div v-if="platFormName === 'facebook'">
                    <MultipleBusinessInfo
                        :reviewsinfo="reviewsinfo"
                        :platFormName="platFormName"
                        :pageList="pageList"
                        @sync-loader="syncLoaderActivate"
                        @add-new-template="addNewTemplate"
                        @clear-verification-credentials="clearVerificationCredentials"
                        @login-with-facebook="loginWithFacebook"
                        @toggle-add-business="handleToggleAddBusiness"
                    />
                  </div>
                  <!-- facebook form -->
                  <FacebookForm
                      v-if="platFormName === 'facebook'"
                      :platFormName="platFormName"
                      :admin_page_url="admin_page_url"
                      :pageList="pageList"
                      :reviewsinfo="reviewsinfo"
                      :verifyPlatform="verifyPlatform"
                      :credentialsType="credentialsType"
                      :isBusinessListVisible="isBusinessListVisible"
                      :acces_token_loader="acces_token_loader"
                      :addNewBusiness="addNewBusiness"
                      @update:addNewBusiness="addNewBusiness = $event"
                      v-model="page_id"
                      @save-reviews="saveReviews"
                      @set-configuration="setConfiguration"
                      @clear-verification-credentials="clearVerificationCredentials"
                  />

                  <MultipleBusinessInfo
                      v-if="platFormName === 'yelp' || platFormName === 'woocommerce' || platFormName === 'aliexpress' || url_based_platforms.includes(platFormName)"
                      :verifyPlatform="verifyPlatform"
                      :reviewsinfo="reviewsinfo"
                      :platFormName="platFormName"
                      :isDownloadablePlatform="true"
                      @sync-loader="syncLoaderActivate"
                      @add-new-template="addNewTemplate"
                      @clear-verification-credentials="clearVerificationCredentials"
                  />

                  <AliExpressForm
                      v-if="platFormName === 'aliexpress'"
                      :platFormName="platFormName"
                      :productName="productName"
                      @productName="productName = $event"
                      :apiKey="apiKey"
                      @apiKey="apiKey = $event"
                      :apiUrl="apiUrl"
                      :sourceId="sourceId"
                      @sourceId="sourceId = $event"
                      :sourceUrl="sourceUrl"
                      :sourceText="sourceText"
                      :messageType="messageType"
                      :message="message"
                      :source_messageType="source_messageType"
                      :source_message="source_message"
                      :reviewsinfo="reviewsinfo"
                      :verifyPlatform="verifyPlatform"
                      @save-reviews="saveReviews"
                  > </AliExpressForm>

                  <!-- settings for amazon, tp, aliexpress, tripadvisor, airbnb and booking.com -->
                  <DownloadingForm
                      v-if="url_based_platforms.includes(platFormName)"
                      :value="downloadReviewsUrl"
                      :exampleURL="exampleURL"
                      :platFormName="platFormName"
                      :reviewsinfo="reviewsinfo"
                      :apiKey="apiKey"
                      :apiUrl="apiUrl"
                      @apiKey="apiKey = $event"
                      :sourceId="sourceId"
                      :sourceText="sourceText"
                      :sourceUrl="sourceUrl"
                      :source_messageType="source_messageType"
                      :source_message="source_message"
                      :message="message"
                      :messageType="messageType"
                      :languages="languages"
                      @sourceId="sourceId = $event"
                      @save-reviews="saveReviews"
                      @set-languages="setLanguages"
                      @url_value="setUrlValue"
                      @credentialsType="credentialsType = $event"
                  />


                  <WoocommerceForm
                      v-if="platFormName === 'woocommerce'"
                      :platFormName="platFormName"
                      :products="productLists"
                      :sourceId="sourceId"
                      @update:sourceId="value => sourceId = value"
                      @save-reviews="saveReviews"
                      :reviewsinfo="reviewsinfo"
                  />


                  <!--                            <AirbnbForm-->
                  <!--                                v-if="platFormName === 'airbnb'"-->
                  <!--                                :exampleURL="exampleURL"-->
                  <!--                                :platFormName="platFormName"-->
                  <!--                                :businessType="businessType"-->
                  <!--                                v-model="downloadReviewsUrl"-->
                  <!--                                @set-business-type="setBusinessType"-->
                  <!--                                :businessName="businessName"-->
                  <!--                                @businessName="businessName = $event"-->
                  <!--                                :reviewsinfo="reviewsinfo"-->
                  <!--                                :verifyPlatform="verifyPlatform"-->
                  <!--                                :viaUrl.sync="viaUrl"-->
                  <!--                                @save-reviews="saveReviews"-->
                  <!--                            />-->
                  <!-- start zomato, yelp form -->
                  <GeneralApiForm
                      v-if="platFormName === 'zomato' || platFormName === 'yelp'"
                      :platFormName="platFormName"
                      :count="count"
                      @count="count = $event"
                      :apiKey="apiKey"
                      @apiKey="apiKey = $event"
                      :apiUrl="apiUrl"
                      :sourceId="sourceId"
                      @sourceId="sourceId = $event"
                      :sourceUrl="sourceUrl"
                      :sourceText="sourceText"
                      :messageType="messageType"
                      :message="message"
                      :source_messageType="source_messageType"
                      :source_message="source_message"
                      :number_messageType="number_messageType"
                      :number_message="number_message"
                      :reviewsinfo="reviewsinfo"
                      :verifyPlatform="verifyPlatform"
                      @save-reviews="saveReviews"
                  />

                  <!-- google access token -->
                  <GoogleLoginForm
                      v-if="platFormName === 'google'"
                      v-model="verificationData"
                      :locationList="locationList"
                      @login-with-google="loginWithGoogle"
                      @verify-now="verifyNow"
                  />

                  <div v-if="platFormName === 'facebook'">
                    <el-button
                        style="width: 100%"
                        v-if="(Object.keys(this.reviewsinfo).length || Object.keys(pageList).length)"
                        :class="{'display-form': reConnect}"
                        class="wpsr_primary_btn_outline"
                        type='default'
                        @click="reConnectBusiness"
                    >
                      <el-icon size="18"><Link /></el-icon>
                      {{ $t('Connect More Account') }}
                    </el-button>

                    <el-tabs v-if="((!Object.keys(this.reviewsinfo).length && !Object.keys(pageList).length) || reConnect)"  v-model="credentialsType" class="wpsr-connections-tabs">
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
                            <p v-if="(!Object.keys(this.reviewsinfo).length || reConnect)">To display Facebook pages reviews, please authorize in Facebook</p>
                            <!-- facebook login button -->
                            <div class="wpsr-modal-btn-with-info" v-if="(!Object.keys(this.reviewsinfo).length || reConnect)">
                              <el-button class="wpsr-login-with-facebook" @click.prevent="loginWithFacebook" size="default" type="primary">
                                <img :src="assets_url+'/images/icon/login-fb-btn.png'" alt="Continue with Facebook"></el-button>
                            </div>
                        </div>
                      </el-tab-pane>
                      <el-tab-pane name="manually_connect">
                        <template #label>
                          <span class="wpsr-connections-tabs-label">
                            <el-icon size="18"><User /></el-icon>
                            <div class="wpsr-connections-tabs-label-group">
                              <span>Manually Connect a Page</span>
                              <p>Connect via Token</p>
                            </div>
                          </span>
                        </template>
                        <div class="wpsr-accounts-connect-wrapper">
                          <div class="wpsr-connection-modal-input-wrapper">
                            <div class="wpsr-connection-modal-input-heading-wrapper">
                              <h4 class="wpsr-connection-modal-input-heading">
                                {{ $t('Facebook Page ID') }}
                              </h4>
                              <p class="wpsr-connection-modal-input-description"><strong>Connecting a client's account?</strong> Avoid sharing accounts password, please use our <a href="https://wpsocialninja.com/access-token-generator/?id=facebook-reviews" target="_blank">access token generator</a>.</p>
                            </div>
                            <InputPassword
                                id="source_id"
                                v-model="sourceId"
                                placeholder="Enter a page id"
                            />
                          </div>
                          <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                            <div class="wpsr-connection-modal-input-heading-wrapper">
                              <h4 class="wpsr-connection-modal-input-heading">
                                {{ $t('Facebook Access Token') }}
                              </h4>
                            </div>
                            <InputPassword
                                id="api_key"
                                v-model="apiKey"
                                placeholder="Enter a valid page access token"
                            />
                          </div>
                          <SaveAndResetButton
                              :isDisable="!(sourceId && apiKey) ? true : false"
                              :platFormName="platFormName"
                              :verifyPlatform="verifyPlatform"
                              :reviewsinfo="reviewsinfo"
                              @save-reviews="saveReviews"
                          />
                        </div>
                      </el-tab-pane>
                    </el-tabs>
                  </div>

                  <div v-if="platFormName === 'google'" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
                    <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
                    <p><strong>Note:</strong>The WP SOCIAL NINJA plugin does not store your user
                      data. It connects WordPress with your business's via API and fetch them to your
                      website. All data is stored in your database, WP Social Ninja just works as a tool
                      to fetch it to your website.</p>
                  </div>

                  <div v-if="platFormName === 'facebook'"  class="wpsr-connection-permission-wrapper">
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
                          <p>This permission allows our plugin to read user-generated content on your Facebook Page, including posts, comments, reviews, and ratings made by users or other Pages.</p>
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

                  <div v-if="(platFormName === 'facebook' && !pageList.length)" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
                    <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
                    <p>
                      <strong>Note:</strong>This does not give us permission to manage your Facebook
                      pages, it simply allows the WP SOCIAL NINJA plugin to see a list of pages and retrieve
                      their public content from the API. All the data is stored in your database, WP Social
                      Ninja just works as a tool to fetch it to your website.
                    </p>
                  </div>
                </div>

                <CreateTemplate
                    v-if="platFormName === 'yelp' || platFormName === 'woocommerce' || platFormName === 'aliexpress' || url_based_platforms.includes(platFormName)"
                    :reviewsinfo="reviewsinfo"
                    @add-new-template="addNewTemplate"
                />
                <CreateTemplate
                    v-if="(platFormName === 'google' || platFormName === 'facebook')"
                    :reviewsinfo="reviewsinfo"
                    @add-new-template="addNewTemplate"
                />
              </div>
            </div>

            <div class="wpsr-connection-modal-footer">
              <div class="wpsr-docs-help">
                <p>Need Help? </p>
                <div class="wpsr-docs-help-links">
                  <a :href="docs" target="_blank">{{ $t('Read Documentation') }}</a>
                  | <a :href="termsAndConditions" target="_blank">{{ $t('Terms & Conditions') }}</a>
                  | <a :href="privacy" target="_blank">{{ $t('Privacy Policy') }}</a>
                </div>
              </div>
            </div>
          </el-dialog>
          </template>
        </div>
      </el-row>
    </div>
    
  </div>
</template>
<script>
import UpgradeToProModal from "../../advertise/UpgradeToProModal.vue";
import InputPassword from "../../../core-ui/editor/InputPassword.vue";
import SaveAndResetButton from "./SaveAndResetButton.vue";
import AliExpressForm from "./AliExpressForm.vue";
import GoogleLoginForm from "./GoogleLoginForm.vue";
import DownloadingForm from "./DownloadingForm.vue";
import PlatformCard from "./PlatformCard.vue";
import CreateTemplate from "./CreateTemplate.vue";
import GoogleApiForm from "./GoogleApiForm.vue";
import GeneralApiForm from "./GeneralApiForm.vue";
import WoocommerceForm from "./WoocommerceForm.vue";
import MultipleBusinessInfo from "./MultipleBusinessInfo.vue";
import FacebookForm from "./FacebookForm.vue";
import { ElNotification } from 'element-plus';
import ThumbsUpIcon from "../../../pieces/icons/ThumbsUpIcon.vue";
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";
import ProCrownIcon from "../../../pieces/icons/ProCrownIcon.vue";

export default {
  name: "ReviewsPlatforms",
  components: {
    ProCrownIcon,
    ShieldCheckLineIcon,
    UpgradeToProModal,
    InputPassword,
    SaveAndResetButton,
    AliExpressForm,
    GoogleLoginForm,
    DownloadingForm,
    PlatformCard,
    CreateTemplate,
    GoogleApiForm,
    GeneralApiForm,
    WoocommerceForm,
    MultipleBusinessInfo,
    FacebookForm,
    ElNotification,
    ThumbsUpIcon
  },
  props: {
    column: {
      type: Number,
      default: 6
    },
    displayTitle: {
      type: Boolean,
      default: true
    },
    filteredPlatforms: {
      type: Array,
      default: () => []
    },
    urlParams: {
      type: Object,
      default: () => {}
    },
    configurationInfo: {
      type: Object,
      default: () => {}
    },
    connectionMode: {
      type: Boolean,
      default: false
    },
    isDarkModeActive: {
      type: Boolean,
      default: false
    },
    activeSearchQuery: {
      type: String,
      default: ''
    }
  },
  emits: ['update:reviewsShowSettingModal', 'open-editor-panel'],
  data() {
    return {
      // Add a loading state
      isLoading: true,
      admin_page_url: this.appVars.admin_page_url,
      internalDialog: false,
      tabActiveName: 'apiKey',
      message: '',
      messageType: '',
      url_based_platforms: ['airbnb', 'amazon', this.appVars.tp_slug, 'tripadvisor', 'airbnb', 'booking.com'],
      source_message: '',
      source_messageType: '',

      apiKey: '',
      sourceId: '',
      platFormName: '',
      platformTitle: '',
      activePlatformName: '',
      reviewsinfo: [],

      loading: false,
      sourceText: '',
      apiUrl: '',
      sourceUrl: '',
      exampleURL: '',
      count: 10,
      productName: '',
      number_message: '',
      number_messageType: '',
      platforms: window.WPSocialReviewsAdmin.platforms_cards,

      //feed modal settings
      pageList: [],
      page_id: '',

      // google_authorize_url: window.WPSocialReviewsAdmin.google_authorize_url,
      locationList: [],
      location_id: [],
      acces_token_loader: false,
      admin_url: this.appVars.admin_url,
      verificationData: '',
      docs: '',
      privacy: '',
      termsAndConditions: '',
      experiences: false,
      downloadReviewsUrl: '',
      verifyPlatform: false,
      is_enabled_platform: false,
      disabled: false,
      reConnect: false,
      viaUrl: false,
      productLists: [],
      errorNotice: [],
      promotion: [],
      languages: [],
      language: 'en',
      version: '',
      credentialsType : 'oauth',
      isBusinessListVisible: false,
      activePermissionPanel: [],
      addNewBusiness: false,
    }
  },
  methods: {
    isEditorMode() {
      const currentUrl = window.location.href;
      return currentUrl.includes('#/edit-template/');
    },
    handleToggleAddBusiness(isVisible) {
      this.addNewBusiness = !isVisible;
    },
    setLanguages( value ){
      this.language = value;
    },
    setUrlValue(value) {
      this.downloadReviewsUrl = value;
    },
    syncLoaderActivate(val) {
      this.acces_token_loader = val;
    },
    verifyNow() {
      this.saveConfigs();
    },
    loginWithGoogle() {
      window.open('https://accounts.google.com/o/oauth2/v2/auth?scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fbusiness.manage&access_type=offline&prompt=consent&include_granted_scopes=true&response_type=code&client_id=1066221839285-b63ib6vnhv9aed2euhtecbp2nojvq9rp.apps.googleusercontent.com&redirect_uri=https%3A%2F%2Fwpsocialninja.com%2Fgapi%2F&state=' + this.admin_page_url);
    },
    loginWithFacebook(){
      const currentUrl = window.location.href;
      const isEditor = currentUrl.includes('#/edit-template/');
      let callBackUrl;
      if (isEditor) {
        const baseUrl = window.location.origin + window.location.pathname;
        let templateId = null;
        const hashMatch = window.location.hash.match(/edit-template\/?(\d+)/);
        if (hashMatch && hashMatch[1]) {
          templateId = hashMatch[1];
        } else {
          templateId = this.$route.params.template_id || '';
        }
        callBackUrl = `${baseUrl}?page=wpsocialninja.php&edit-template&${templateId}`;
      } else {
        callBackUrl = this.admin_page_url;
      }

      window.open('https://www.facebook.com/dialog/oauth?client_id=443259723723907&redirect_uri=https://wpsocialninja.com/api/facebook-login.php&state='+encodeURIComponent(callBackUrl)+'&scope=pages_show_list,pages_read_engagement,pages_read_user_content,business_management', '_self');
    },
    socialSettingModal(
        platformName,
        platformTitle,
        activePlatformName,
        apiKey,
        sourceId,
        message,
        sourceText,
        apiUrl,
        sourceUrl,
        exampleURL,
        reviewsinfo,
        count,
        docs,
        privacy,
        termsAndConditions,
        experiences,
        promotion
    ) {
      // Always set the platform name and basic info first
      this.platFormName = platformName;
      this.platformTitle = platformTitle;

      if (!this.has_pro && (platformName !== 'google' && platformName !== 'airbnb')) {
        this.internalDialog = true;
        this.promotion = promotion;
      } else {
        this.internalDialog = true;
        this.tabActiveName = 'apiKey';
        this.apiKey = apiKey;
        this.sourceId = sourceId;
        this.message = message;
        this.reviewsinfo = reviewsinfo;
        this.getApiCredential(platformName);
        this.sourceText = sourceText;
        this.apiUrl = apiUrl;
        this.sourceUrl = sourceUrl;
        this.exampleURL = exampleURL;
        this.count = count;
        this.docs = docs;
        this.privacy = privacy;
        this.termsAndConditions = termsAndConditions;
        this.experiences = experiences;
      }
    },
    capitalize(content) {
      return content.charAt(0).toUpperCase() + content.slice(1);
    },
    // save configs
    saveConfigs() {
      this.acces_token_loader = true;

      this.$post('platforms/reviews/configs', {
        verificationData: this.verificationData,
        platform: this.platFormName
      }).then(response => {
        if (response) {
          //for facebook
          if (response.data && response.data.settings && this.platFormName === 'facebook') {
            this.pageList = response.data.settings;
            this.addNewBusiness = true;
            //this.verificationData = ''
          }
          //for google
          if (this.platFormName === 'google' && response.data.locations) {
            this.locationList = response.data.locations;
            this.apiKey = response.data.access_token;
            this.addNewBusiness = true;
          }
          if(this.platFormName === 'facebook' && response.data && response.data.business_info) {
            this.reviewsinfo = response.data.business_info
          }
          if (this.platFormName === 'google') {
            ElNotification({
              title: 'Verified',
              dangerouslyUseHTMLString: true,
              message: '<strong>' + response.data.message + '</strong><br>' +
                  'You may receive an email from Google notifying you that our plugin has been <br> granted business manage access to your account.',
              type: 'success',
              offset: 80,
              duration: 5000
            });
          } else {
            this.handleSuccess(response.data.message);
          }

          this.verificationData = '';
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {
        this.acces_token_loader = false;
      });
    },
    getApiCredential(platformName) {
      this.verificationData = '';
      this.acces_token_loader = true;
      this.$get(`platforms/reviews/configs`, {
        platform: platformName
      })
          .then(response => {
            if (response) {
              if (response.credential) {
                this.apiKey = response.credential.api_key;
                if (!this.sourceId) {
                  this.sourceId = response.credential.place_id;
                }
                this.version = response.credential.version;

                this.count = response.credential.count ? response.credential.count : 10;

                let business_id = Object.keys(response.credential)[0];
                let business_url = response.credential[business_id] !== undefined ? response.credential[business_id].url_value : '';
                if (business_id && business_url) {
                  this.downloadReviewsUrl = '';
                  this.sourceId = business_id;
                  //for all platform which has url value
                  this.verifyPlatform = true;
                } else {
                  this.downloadReviewsUrl = '';
                  //for all platform which has no url value
                  this.verifyPlatform = false;
                }
              }
              if (this.apiKey && this.sourceId && this.platFormName === 'yelp') {
                this.apiKey = '';
                this.sourceId = '';
              }

              if (this.platFormName === 'tripadvisor') {
                this.apiKey = '';
                this.sourceId = '';
              }


              if (this.platFormName === 'aliexpress') {
                this.sourceId = '';
                this.productName = '';
              }

              if (response && this.platFormName === 'facebook') {
                // Set the Facebook connection type to OAuth when opening the 'Reset/Open Other Platform' modal.
                this.credentialsType = 'oauth';
                if (!response.additional_info) {
                  this.pageList = [];
                } else if (response.additional_info) {
                  this.pageList = response.additional_info;
                }
                if (Array.isArray(this.pageList) && Object.keys(this.pageList).length && this.sourceId) {
                  //show fb saved page name
                  this.pageList.map((item, index) => {
                    if (item.id.includes(this.sourceId)) {
                      this.page_id = item.name;
                    }
                  });
                }
              }
              if (this.platFormName === 'woocommerce' && response.additional_info && response.additional_info.length) {
                this.productLists = response.additional_info;
              }
              if (this.apiKey && this.sourceId && this.platFormName === 'airbnb') {
                this.veryfiedAirbnb = true;
              }
              if (response.additional_info) {
                if (this.platFormName === 'google') {
                  this.locationList = response.additional_info.location_lists;
                  this.location_id = response.additional_info.connected_accounts;
                } else {
                  this.locationList = response.additional_info;
                }
              }

              if (response.additional_info && response.additional_info.languages) {
                this.languages = response.additional_info.languages;
              }

              if (this.platFormName === 'google' && response.business_info) {
                this.reviewsinfo = response.business_info;
              } else {
                if (response.business_info) {
                  this.reviewsinfo = response.business_info;
                }
              }
            }
          }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {
        this.acces_token_loader = false;
      });
    },
    saveReviews() {
      this.acces_token_loader = true;
      const reviewsInfoLength = Object.keys(this.reviewsinfo).length;
      const requiredMessage = "This field is required!";
      const errorType = "wpsr-error";
      const defaultApiKey = '479711fa-64ba-47ce-b63b-9c2ba8d663f9';
      const defaultSourceId = 'cfmoon64ba78';

      if (reviewsInfoLength >= 1 && !this.has_pro) {
        this.handlePro();
        return;
      }

      // Set API key and source ID for specific platforms
      const platformRequiresSourceId = ['amazon', this.appVars.tp_slug, 'airbnb', 'booking.com'];
      if (this.platFormName === 'tripadvisor') {
        if (this.credentialsType === 'business_url') {
          this.apiKey = defaultApiKey;
          this.sourceId = defaultSourceId;
        } else {
          this.downloadReviewsUrl = '';
        }
      } else if (platformRequiresSourceId.includes(this.platFormName)) {
        this.apiKey = defaultApiKey;
        this.sourceId = defaultSourceId;
      } else if (this.platFormName === 'aliexpress' || (this.sourceId && this.platFormName === 'woocommerce') || (this.sourceId && this.platFormName === 'google')) {
        this.apiKey = defaultApiKey;
      }

      // Validate API key and source ID
      if (!this.apiKey || !this.sourceId) {
        this.message = !this.apiKey ? requiredMessage : '';
        this.messageType = !this.apiKey ? errorType : '';
        this.source_message = !this.sourceId ? requiredMessage : '';
        this.source_messageType = !this.sourceId ? errorType : '';
        return;
      }

      if (isNaN(this.count)) {
        this.number_message = "Please provide a number!";
        this.number_messageType = errorType;
        return;
      }

      if (this.apiKey && this.sourceId) {
        this.loading = true;
        // if (this.platFormName === 'airbnb') {
        //     settings = {
        //         business_type: this.businessType,
        //         business_name: this.businessName,
        //         url_value: this.downloadReviewsUrl,
        //         platform: this.platFormName,
        //         via_url: this.viaUrl
        //     }
        // } else {
        //
        // }

        const settings = {
          api_key: this.apiKey,
          source_id: this.sourceId,
          product_name: this.productName,
          count: this.count,
          platform: this.platFormName,
          experiences: this.experiences,
          url_value: this.downloadReviewsUrl ? this.downloadReviewsUrl.trim() : '',
          credentialsType: this.credentialsType,
          language: this.language
        }

        this.$post('platforms/reviews', {
          settings: settings
        })
            .then(response => {
              if (response.data) {
                  if(response.data.business_info){
                    this.reviewsinfo = response.data.business_info;
                  }
                  this.handleSuccess(response.data.message);
                  this.getApiCredential(this.platFormName);
                  this.getEnabledPlatforms();
                // this.businessName = '';
              }
            })
            .catch((errors) => {
              this.handleError(errors);
            })
            .always(() => {
              this.loading = false;
              this.addNewBusiness = false;
              this.acces_token_loader = false;
              this.message = this.messageType = this.source_message = this.source_messageType = '';
            });
      }
    },
    clearVerificationCredentials(sourceId = '') {
      this.$del('platforms/reviews/configs', {
        platform: this.platFormName,
        sourceId: sourceId
      })
          .then(response => {
            if (response) {
              this.getApiCredential(this.platFormName);
              this.locationList = [];
              this.location_id = [];
              this.apiKey = '';
              this.sourceId = '';
              //this.downloadReviewsUrl = '';
              this.reviewsinfo = [];
              this.handleSuccess(response.message);
              this.getEnabledPlatforms();
            }
          })
          .catch((errors) => {
            this.handleError(errors);
          })
          .always(() => {
            this.addNewBusiness = false;
          });
    },
    addNewTemplate() {
      this.$post('templates', {
        platform: this.platFormName
      })
          .then(response => {
            if (response) {
              if (response.template_id) {
                this.$router.push({
                  name: 'edit-template',
                  params: {
                    template_id: response.template_id
                  }
                })
                // close modal after successful template creation
                this.internalDialog = false;
              }
            }
          }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    },
    getEnabledPlatforms() {
      this.$get('platforms/enabled')
          .then(response => {
            if(response){
              this.is_enabled_platform = response.platforms;
              this.errorNotice = response.notices !== undefined ? response.notices : [];
            }
          }).catch(errors => {
        this.handleError(errors);
      }).always(() => {
        this.isLoading = false;
      });
    },
    setConfiguration(value) {
      this.apiKey = value.access_token;
      this.sourceId = value.id;
      this.page_id = value.name;
    },
    setGoogleConfiguration(value) {
      this.apiKey = this.apiKey;
      this.sourceId = value;
    },
    reConnectBusiness() {
      this.reConnect = !this.reConnect;
    },
    getPlatformTitle() {
      const matched = this.platforms.find(item => item.platform === this.platFormName);
      return matched ? matched.platform_title : 'Unknown';
    },
    handleAddSource() {
      // Navigate to custom sources page
      this.$router.push({
        name: 'custom-sources'
      });
    }
  },

  computed: {
    hasReviewsToShow() {
      // Show if there are filtered platforms OR if custom sources should be shown
      return this.filteredPlatforms.length > 0 || this.shouldShowCustomSources;
    },
    shouldShowCustomSources() {
      // Don't show in editor mode
      if (this.isEditorMode()) {
        return false;
      }

      // If there's no active search, show the custom sources
      if (!this.activeSearchQuery) {
        return true;
      }

      // If there's a search, only show if it matches custom/sources related terms
      const searchTerm = this.activeSearchQuery.toLowerCase();
      const shouldShow = searchTerm.includes('custom') ||
                        searchTerm.includes('sources') ||
                        searchTerm.includes('source');

      return shouldShow;
    }
  },
  created() {
    let urlParams = this.urlParams;
    if(urlParams === '' || urlParams === undefined) return;

    const platform = urlParams.get('platform');
    let oauth_token = platform === 'google' ? urlParams.get('code') : urlParams.get('access_token');

    let templateId = null;
    const templateIdMatch = window.location.search.match(/&(\d+)&/);
    if (templateIdMatch && templateIdMatch[1]) {
      templateId = templateIdMatch[1];
    }

    this.$emit('update:reviewsShowSettingModal', true);

    if (templateId) {
      this.internalDialog = true;
      this.verificationData = oauth_token;
      this.platFormName = platform;
      this.saveConfigs();
      
      const baseUrl = window.location.origin + window.location.pathname;
      const targetUrl = `${baseUrl}?page=wpsocialninja.php#/edit-template/${templateId}?configuration=true&platform=${platform}&oauth_token=${oauth_token}`;
      window.history.replaceState({}, '', targetUrl);
      window.location.reload();
      this.internalDialog = true;

      // this.$router.push(`/edit-template/${templateId}`).then(() => {
      //   window.location.reload(); // Optional: reload after route change
      // });
    } else {
      // window.history.pushState({}, null, this.admin_page_url + '#/');
    }

    if(oauth_token){
      this.isBusinessListVisible = true
    }

  },
  mounted() {
    this.getEnabledPlatforms();
    if(this.configurationInfo && this.configurationInfo.configuration && this.configurationInfo.platform_type === 'reviews') {
      this.internalDialog = true;
      this.isBusinessListVisible = true
      this.verificationData = this.configurationInfo.oauth_token;
      this.platFormName = this.configurationInfo.platform;
      this.platformTitle = this.getPlatformTitle();
      this.saveConfigs();
      // // Clean up the URL: remove all query params from the hash
      let hash = window.location.hash || '';
      let cleanHash = hash.split('?')[0];
      let newUrl = window.location.origin + window.location.pathname + window.location.search + cleanHash;
      window.history.replaceState({}, '', newUrl);
    }
  },
}
</script>
