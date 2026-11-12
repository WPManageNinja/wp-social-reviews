<template>
  <!-- social reviews - wrap with v-show -->
  <div class="wpsr-platforms-group" v-if="hasReviewsToShow">
    <AuthRedirectOverlay
        :visible="facebookAuthLoading"
        :title="`${$t('Redirecting to')} ${authServiceHost}`"
        :subtitle="$t('You will be redirected to our authentication portal. This may take up to 5 seconds.')"
    />
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
                Create Custom Sources
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
                         :deprecationNotice="deprecationNotice"
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
                      v-if="platFormName === 'yelp' || platFormName === 'woocommerce' || platFormName === 'fluent-cart' || platFormName === 'aliexpress' || url_based_platforms.includes(platFormName)"
                      :verifyPlatform="verifyPlatform"
                      :reviewsinfo="reviewsinfo"
                      :platFormName="platFormName"
                      :hasFluentCart="hasFluentCartInstalled"
                      :hasWooCommerce="hasWooCommerceInstalled"
                      :isDownloadablePlatform="true"
                      :productsCount="fluentCartProductsCount"
                      :pagination="reviewsPagination"
                      :loadingMore="loadingMoreReviews"
                      :deprecationNotice="deprecationNotice"
                      @sync-loader="syncLoaderActivate"
                      @add-new-template="addNewTemplate"
                      @clear-verification-credentials="clearVerificationCredentials"
                      @products-connected="getApiCredential(platFormName)"
                      @load-more="loadMoreReviews"
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
                      :connectMode="connectMode"
                      :bookingHotel="bookingHotel"
                      :businessLimitNotice="businessLimitNotice"
                      @sourceId="sourceId = $event"
                      @save-reviews="saveReviews"
                      @set-languages="setLanguages"
                      @url_value="setUrlValue"
                      @booking-hotel="bookingHotel = $event"
                      @credentialsType="credentialsType = $event"
                  />


                  <WoocommerceForm
                      v-if="platFormName === 'woocommerce'"
                      :platFormName="platFormName"
                      :hasProducts="hasWooProducts"
                      :hasWooCommerce="hasWooCommerceInstalled"
                      :sourceId="sourceId"
                      @update:sourceId="value => sourceId = value"
                      @save-reviews="saveReviews"
                      :reviewsinfo="reviewsinfo"
                  />

                  <FluentCartForm
                      v-if="platFormName === 'fluent-cart'"
                      :platFormName="platFormName"
                      :hasFluentCart="hasFluentCartInstalled"
                      :hasFluentCartProducts="hasFluentCartProducts"
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
                      :locationList="locationList"
                      :google-auth-loading="googleAuthLoading"
                      :auth-service-url="appVars.auth_service_url"
                      @login-with-google="loginWithGoogle"
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
                              <span>{{ $t('Quick Connect') }}</span>
                              <p>{{ $t('Recommended · One-click setup') }}</p>
                            </div>
                          </span>
                        </template>
                        <div class="wpsr-accounts-connect-wrapper">
                            <p v-if="(!Object.keys(this.reviewsinfo).length || reConnect)">{{ $t('Connect your Facebook account to display reviews from the Pages you manage.') }}</p>
                            <!-- facebook login button -->
                            <div class="wpsr-modal-btn-with-info" v-if="(!Object.keys(this.reviewsinfo).length || reConnect)">
                              <el-button class="wpsr-login-with-facebook" @click.prevent="loginWithFacebook" size="default" type="primary" :loading="facebookAuthLoading" :disabled="facebookAuthLoading">
                                <FacebookMarkIcon/>
                                {{ $t('Connect Facebook Account') }}
                              </el-button>
                            </div>
                            <p class="wpsr-connect-redirect-note" v-if="(!Object.keys(this.reviewsinfo).length || reConnect)">{{ $t('You will be redirected to WP Social Ninja, then to Facebook to approve access.') }}</p>
                        </div>
                      </el-tab-pane>
                      <el-tab-pane name="manually_connect">
                        <template #label>
                          <span class="wpsr-connections-tabs-label">
                            <el-icon size="18"><User /></el-icon>
                            <div class="wpsr-connections-tabs-label-group">
                              <span>{{ $t('Manual Setup') }}</span>
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


                  <div v-if="(platFormName === 'facebook' && !pageList.length)" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-mt-20">
                    <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
                    <p>
                      <strong>{{ $t('Note:') }}</strong>
                      {{ $t('WP Social Ninja does not manage or post to your Facebook account. It only reads the public content you approve. Tokens and data are stored on your own site — our connection service only passes the authorization through and never stores your data.') }}
                    </p>
                  </div>
                </div>

                <PlatformQuickSetup
                    v-if="platFormName === 'woocommerce' || platFormName === 'fluent-cart'"
                    :reviewsinfo="reviewsinfo"
                    :platform="platFormName"
                    :platformLabel="platFormName === 'woocommerce' ? 'WooCommerce' : 'FluentCart'"
                    @quick-setup-complete="onQuickSetupComplete"
                />
                <div
                    v-if="(platFormName === 'woocommerce' || platFormName === 'fluent-cart') && Object.keys(reviewsinfo).length"
                    class="wpsr-quick-setup-or-divider"
                >
                  <span>{{ $t('or') }}</span>
                </div>
                <CreateTemplate
                    v-if="platFormName === 'yelp' || platFormName === 'woocommerce' || platFormName === 'fluent-cart' || platFormName === 'aliexpress' || url_based_platforms.includes(platFormName)"
                    :reviewsinfo="reviewsinfo"
                    :secondary="platFormName === 'woocommerce' || platFormName === 'fluent-cart'"
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

          <!-- A saved property is keyed by its old page link, not by a hotel id, so only the
               admin can say whether this hotel is one of them. -->
          <el-dialog
              v-model="legacyPromptVisible"
              width="42%"
              class="wpsr-modal wpsr-legacy-choice-modal"
              :close-on-click-modal="false"
          >
            <template #header>
              <div class="wpsr-connection-modal-header">
                <h4 class="wpsr-dialog-title">{{ $t('Is this hotel already on your site?') }}</h4>
              </div>
            </template>

            <p class="wpsr-legacy-choice-note">{{ legacyPromptMessage }}</p>

            <el-radio-group v-model="legacyChoice" class="wpsr-legacy-choice-list">
              <el-radio
                  v-for="property in legacyProperties"
                  :key="property.place_id"
                  :value="property.place_id"
              >
                <span class="wpsr-legacy-choice-name">
                  {{ property.name }}
                  <em v-if="property.suggested">{{ $t('looks like a match') }}</em>
                </span>
                <span class="wpsr-legacy-choice-meta">
                  {{ property.review_count }} {{ $t('saved reviews would be replaced') }}
                </span>
                <span class="wpsr-legacy-choice-meta" v-if="property.url">{{ property.url }}</span>
              </el-radio>
              <el-radio :value="legacyNewChoice">
                <span class="wpsr-legacy-choice-name">{{ $t('No, this is a new property') }}</span>
                <span class="wpsr-legacy-choice-meta">{{ $t('Nothing you already saved is changed') }}</span>
              </el-radio>
            </el-radio-group>

            <template #footer>
              <el-button @click="legacyPromptVisible = false">{{ $t('Cancel') }}</el-button>
              <el-button type="primary" :disabled="!legacyChoice" @click="confirmLegacyChoice">
                {{ $t('Continue') }}
              </el-button>
            </template>
          </el-dialog>
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
import PlatformQuickSetup from "./PlatformQuickSetup.vue";
import GoogleApiForm from "./GoogleApiForm.vue";
import GeneralApiForm from "./GeneralApiForm.vue";
import WoocommerceForm from "./WoocommerceForm.vue";
import MultipleBusinessInfo from "./MultipleBusinessInfo.vue";
import FacebookForm from "./FacebookForm.vue";
import { ElNotification } from 'element-plus';
import ThumbsUpIcon from "../../../pieces/icons/ThumbsUpIcon.vue";
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";
import FacebookMarkIcon from "../../../pieces/icons/FacebookMarkIcon.vue";
import ProCrownIcon from "../../../pieces/icons/ProCrownIcon.vue";
import FluentCartForm from "./FluentCartForm.vue";
import AuthRedirectOverlay from "../../../core-ui/AuthRedirectOverlay.vue";
import { wpsrBuildAuthReturnUrl as buildWpsrAuthReturnUrl, wpsrSubmitAuthForm } from "../../../../utils";

export default {
  name: "ReviewsPlatforms",
  components: {
    FacebookMarkIcon,
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
    PlatformQuickSetup,
    GoogleApiForm,
    GeneralApiForm,
    WoocommerceForm,
    MultipleBusinessInfo,
    FacebookForm,
    ElNotification,
    ThumbsUpIcon,
    FluentCartForm,
    AuthRedirectOverlay
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
    },
    showCustomSources: {
      type: Boolean,
      default: true
    },
    wpsrAuthReturnUrlBuilder: {
      type: Function,
      default: null
    }
  },
  emits: ['update:reviewsShowSettingModal', 'open-editor-panel', 'auth-configuration-consumed'],
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
      reviewsinfo: {},
      reviewsPerPage: 10,
      reviewsPagination: null,
      loadingMoreReviews: false,
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
      bookingHotel: null,
      connectMode: 'url',
      legacyPromptVisible: false,
      legacyPromptMessage: '',
      legacyProperties: [],
      legacyChoice: '',
      legacyChoiceKey: 'legacy_choice',
      legacyNewChoice: 'new',
      legacySettings: null,
      verifyPlatform: false,
      is_enabled_platform: false,
      disabled: false,
      reConnect: false,
      viaUrl: false,
      hasWooProducts: false,
      hasWooCommerceInstalled: !!this.appVars.hasWooCommerce,
      hasFluentCartInstalled: !!this.appVars.hasFluentCart,
      hasFluentCartProducts: false,
      fluentCartProductsCount: 0,
      errorNotice: [],
      promotion: [],
      languages: [],
      deprecationNotice: '',
      businessLimitNotice: '',
      language: 'en',
      version: '',
      credentialsType : 'oauth',
      isBusinessListVisible: false,
      addNewBusiness: false,
      openBusinessListAfterCredentialLoad: false,
      googleAuthLoading: false,
      facebookAuthLoading: false,
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
    wpsrBuildAuthReturnUrl(routeName, authPlatform = '') {
      if (typeof this.wpsrAuthReturnUrlBuilder === 'function') {
        return this.wpsrAuthReturnUrlBuilder(routeName, authPlatform, this.admin_page_url);
      }

      const templateId = this.$route && this.$route.params ? this.$route.params.template_id : '';
      return buildWpsrAuthReturnUrl(routeName, authPlatform, this.admin_page_url, templateId);
    },
    loginWithGoogle() {
      if (this.googleAuthLoading) {
        return;
      }

      this.googleAuthLoading = true;
      this.$post('platforms/reviews/configs/prepare', {
        platform: 'google',
        flow: 'reviews',
        return_url: this.wpsrBuildAuthReturnUrl('edit-template', 'google'),
        source: 'Google Reviews'
      }).then(response => {
        if (response && response.data && response.data.action) {
          wpsrSubmitAuthForm(response.data.action, response.data.fields);
        } else {
          this.googleAuthLoading = false;
        }
      }).catch(errors => {
        this.googleAuthLoading = false;
        this.handleError(errors);
      });
    },
    loginWithFacebook(){
      if (this.facebookAuthLoading) {
        return;
      }

      // NOTE: Facebook reviews is a Pro-only platform. This button only renders in the has_pro
      // branch (non-Pro users get UpgradeToProModal). The prepare/consume handlers for
      // platform: 'facebook' are registered by FacebookBusiness ($authProvider = 'facebook')
      // in wp-social-ninja-pro, so the 'no auth handler registered' path is unreachable here.
      this.facebookAuthLoading = true;
      this.$post('platforms/reviews/configs/prepare', {
        platform: 'facebook',
        flow: 'reviews',
        return_url: this.wpsrBuildAuthReturnUrl('edit-template', 'facebook'),
        source: 'Facebook Reviews'
      }).then(response => {
        if (response && response.data && response.data.action) {
          wpsrSubmitAuthForm(response.data.action, response.data.fields);
        } else {
          this.facebookAuthLoading = false;
        }
      }).catch(errors => {
        this.facebookAuthLoading = false;
        this.handleError(errors);
      });
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
      this.reviewsPagination = null;
      this.businessLimitNotice = '';

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
      const requestParams = {
        platform: platformName
      };
      // Only the WooCommerce/FluentCart connected-products list can run into
      // the thousands — paginate just that request (always page 1 on initial
      // load; loadMoreReviews() fetches subsequent pages), other platforms
      // keep the existing full-list response.
      if (platformName === 'woocommerce' || platformName === 'fluent-cart') {
        requestParams.per_page = this.reviewsPerPage;
      }
      this.$get(`platforms/reviews/configs`, requestParams)
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
              if (this.platFormName === 'woocommerce' && response.additional_info) {
                this.hasWooProducts = response.additional_info.has_products || false;
              }
              if (this.apiKey && this.sourceId && this.platFormName === 'airbnb') {
                this.veryfiedAirbnb = true;
              }
              if (response.additional_info) {
                if (this.platFormName === 'google') {
                  this.locationList = response.additional_info.location_lists;
                  this.location_id = response.additional_info.connected_accounts;
                  if (this.openBusinessListAfterCredentialLoad && Object.keys(this.locationList || {}).length) {
                    this.addNewBusiness = true;
                  }
                } else {
                  this.locationList = response.additional_info;
                }
              }

              if (response.additional_info && response.additional_info.languages) {
                this.languages = response.additional_info.languages;
              }

              this.deprecationNotice = (response.additional_info && response.additional_info.deprecation_notice) || '';
              this.businessLimitNotice = (response.additional_info && response.additional_info.business_limit_notice) || '';
              this.connectMode = (response.additional_info && response.additional_info.connect_mode) || 'url';

              if (this.platFormName === 'fluent-cart' && response.additional_info) {
                this.hasFluentCartProducts = !!response.additional_info.has_products;
                this.fluentCartProductsCount = response.additional_info.products_count || 0;
              } else if (this.platFormName === 'fluent-cart') {
                this.hasFluentCartProducts = false;
                this.fluentCartProductsCount = 0;
              }

              if (this.platFormName === 'google' && response.business_info) {
                this.reviewsinfo = response.business_info;
              } else {
                if (response.business_info) {
                  this.reviewsinfo = response.business_info;
                }
              }

              this.reviewsPagination = response.pagination || null;
            }
          }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {
        this.openBusinessListAfterCredentialLoad = false;
        this.acces_token_loader = false;
      });
    },
    loadMoreReviews() {
      if (this.loadingMoreReviews || !this.reviewsPagination || this.reviewsPagination.page >= this.reviewsPagination.total_pages) {
        return;
      }
      this.loadingMoreReviews = true;
      const nextPage = this.reviewsPagination.page + 1;
      this.$get(`platforms/reviews/configs`, {
        platform: this.platFormName,
        page: nextPage,
        per_page: this.reviewsPerPage
      }).then(response => {
        if (response && response.business_info) {
          // Append the next page onto the currently loaded connected products,
          // rather than replacing — this drives the scroll-to-load-more list.
          this.reviewsinfo = { ...this.reviewsinfo, ...response.business_info };
        }
        if (response && response.pagination) {
          this.reviewsPagination = response.pagination;
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {
        this.loadingMoreReviews = false;
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
      } else if (this.platFormName === 'aliexpress' || (this.sourceId && this.platFormName === 'woocommerce') || (this.sourceId && this.platFormName === 'fluent-cart') || (this.sourceId && this.platFormName === 'google')) {
        this.apiKey = defaultApiKey;
      }

      if (this.platFormName === 'booking.com' && this.connectMode === 'search' && !this.bookingHotel) {
        this.message = 'Please pick your hotel from the search results.';
        this.messageType = errorType;
        this.acces_token_loader = false;
        return;
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

        if (this.platFormName === 'booking.com' && this.connectMode === 'search' && this.bookingHotel) {
          settings.hotel_id = this.bookingHotel.hotel_id;
          settings.ufi = this.bookingHotel.ufi;
          settings.hotel_name = this.bookingHotel.name;
          settings.hotel_country = this.bookingHotel.country;
          settings.hotel_location = this.bookingHotel.location;
        }

        this.submitReviewSettings(settings);
      }
    },
    submitReviewSettings(settings) {
      this.loading = true;

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
                this.bookingHotel = null;
              // this.businessName = '';
            }
          })
          .catch((errors) => {
            if (!this.openLegacyChoice(errors, settings)) {
              this.handleError(errors);
            }
          })
          .always(() => {
            this.loading = false;
            this.acces_token_loader = false;
            // Leave the connect panel open behind the question, so Cancel returns the admin
            // to the hotel they picked.
            if (!this.legacyPromptVisible) {
              this.addNewBusiness = false;
            }
            this.message = this.messageType = this.source_message = this.source_messageType = '';
          });
    },
    /**
     * The save was held back until the admin says which saved property this hotel is.
     * Returns false when the error is an ordinary one, so the caller can report it.
     */
    openLegacyChoice(errors, settings) {
      const data = errors && errors.responseJSON ? errors.responseJSON.data : null;
      const prompt = data ? data.replace_prompt : null;

      if (!prompt || !prompt.properties || !prompt.properties.length) {
        return false;
      }

      const suggested = prompt.properties.find(property => property.suggested);

      this.legacySettings = settings;
      this.legacyProperties = prompt.properties;
      this.legacyPromptMessage = data.message || '';
      this.legacyChoiceKey = prompt.choice_key || 'legacy_choice';
      this.legacyNewChoice = prompt.new_choice || 'new';
      this.legacyChoice = suggested ? suggested.place_id : this.legacyNewChoice;
      this.legacyPromptVisible = true;

      return true;
    },
    confirmLegacyChoice() {
      if (!this.legacyChoice || !this.legacySettings) {
        return;
      }

      const settings = {...this.legacySettings};
      settings[this.legacyChoiceKey] = this.legacyChoice;

      this.legacyPromptVisible = false;
      this.acces_token_loader = true;
      this.submitReviewSettings(settings);
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
              this.reviewsinfo = {};
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
    onQuickSetupComplete(data) {
      if (data && data.template_id) {
        this.$router.push({
          name: 'edit-template',
          params: {
            template_id: data.template_id
          }
        });
        // close modal after successful quick setup, same as addNewTemplate()
        this.internalDialog = false;
      }
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
    },
    applyAuthConfiguration(info) {
      if (!info || !info.configuration || info.platform_type !== 'reviews') {
        return;
      }

      this.internalDialog = true;
      this.isBusinessListVisible = true;
      this.verificationData = info.oauth_token;
      this.platFormName = info.platform;
      this.platformTitle = this.getPlatformTitle();

      if (info.auth_consumed) {
        this.applyConsumedAuthConfiguration(info);
      } else {
        this.saveConfigs();
      }

      // One-shot: tell the parent to drop the trigger so a tab-switch re-mount
      // does not reopen this modal. The reviews tab is v-if-gated, so the parent's
      // configurationInfo (not any child-local flag) is the state that survives.
      this.$emit('auth-configuration-consumed');

      let hash = window.location.hash || '';
      let cleanHash = hash.split('?')[0];
      let newUrl = window.location.origin + window.location.pathname + window.location.search + cleanHash;
      window.history.replaceState({}, '', newUrl);
    },
    applyConsumedAuthConfiguration(info) {
      if (info.platform === 'google') {
        const locations = info.locations || {};

        if (info.load_existing || !Object.keys(locations).length) {
          this.openBusinessListAfterCredentialLoad = true;
          this.getApiCredential(this.platFormName);
          return;
        }

        this.locationList = locations;
        this.location_id = [];
        this.addNewBusiness = Object.keys(locations).length > 0;
        this.acces_token_loader = false;

        if (!Object.keys(locations).length) {
          this.handleError('No Google Business Profile locations were found for this Google account.');
        }

        return;
      }

      if (info.platform === 'facebook') {
        const pages = info.settings || {};

        // Connection type is always OAuth when returning from the auth bridge.
        this.credentialsType = 'oauth';
        this.pageList = pages;

        if (info.business_info && Object.keys(info.business_info).length) {
          this.reviewsinfo = info.business_info;
        }

        // Open the page-selection modal so the user can pick a page to fetch reviews from.
        this.addNewBusiness = Object.keys(pages).length > 0;
        this.acces_token_loader = false;

        // Refresh connected business state alongside the freshly stored pages list.
        this.getApiCredential(this.platFormName);

        if (!Object.keys(pages).length) {
          this.handleError('No Facebook pages were found for this account.');
        }

        return;
      }

      this.getApiCredential(this.platFormName);
    }
  },

  computed: {
    authServiceHost() {
      try { return new URL(this.appVars.auth_service_url).hostname; } catch (e) { return this.appVars.auth_service_url; }
    },
    hasReviewsToShow() {
      // Show if there are filtered platforms OR if custom sources should be shown
      return this.filteredPlatforms.length > 0 || this.shouldShowCustomSources;
    },
    shouldShowCustomSources() {
      // Don't show in editor mode
      // Also don't show if parent disabled custom_sources via screen options
      if (!this.showCustomSources) {
        return false;
      }

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
  watch: {
    configurationInfo: {
      handler(value) {
        this.applyAuthConfiguration(value);
      },
      deep: true
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
    this.applyAuthConfiguration(this.configurationInfo);
  },
}
</script>
