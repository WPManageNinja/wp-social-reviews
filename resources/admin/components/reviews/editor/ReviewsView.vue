<template>
    <div class="wpsr_editor" v-if="template_meta" :class="{ 'wpsr-blurred': showWelcomePopup }">
        <editor-header
            :title="template_title"
            :platform_section="types"
            :route_name="template_meta.templateType === 'notification' ? 'notifications' : 'templates'"
            :route_title="template_meta.templateType === 'notification' ? 'Notification Popups' : 'All Templates'"
            @update-settings="saveTemplateMeta"
        ></editor-header>
        <div class="wpsr_editor_wrapper" :class="'wpsr-active-'+template_meta.templateType+'-editor-wrapper'">
            <el-container>
                <el-main v-loading="loading" class :class="[
                      has_pro && template_meta.templateType !== 'notification' ? 'wpsr-editor-view-port' : '',
                      !has_pro && 'wpsr-reviews-spacing-top'
                    ]">
                  <div v-if="template_meta.templateType !== 'notification'">
                    <EditorViewInfoBar
                        type=""
                        :templateName="getTemplateName()"
                        :editorInfo="getEditorInfo()"
                        @handleDevice="handleDevice"

                    />
                  </div>
                  <DemoTemplateAlert v-if="template_meta && template_meta.display_onboarding_info" />
                  <div>
                    <div v-if="errors && !allFilteredReviews.length" v-for="(error, index ) in errors" :key="index">
                      <ErrorCard :error_message="error.error_message"/>
                    </div>
                    <div  v-if="allFilteredReviews.length">
                      <div id="wpsr-reviews-grid"
                            class="wpsr-reviews-wrapper"
                            :class="[
                              template_meta.templateType === 'badge' ? 'wpsr-reviews-layout-badge' : '',
                              template_meta.templateType === 'notification' ? 'wpsr-reviews-layout-notification' : '',
                              template_meta.templateType === 'notification' ? 'wpsr-'+template_meta.notification_settings.notification_position : '',
                              `wpsr-reviews-${this.$route.params.template_id}`
                            ]"
                      >
                        <a class="wpsr-popup-close" href="#" v-if="template_meta.templateType === 'badge'">
                          <svg viewBox="0 0 16 16" style="fill: rgb(255, 255, 255);">
                            <path d="M3.426 2.024l.094.083L8 6.586l4.48-4.479a1 1 0 011.497 1.32l-.083.095L9.414 8l4.48 4.478a1 1 0 01-1.32 1.498l-.094-.083L8 9.413l-4.48 4.48a1 1 0 01-1.497-1.32l.083-.095L6.585 8 2.106 3.522a1 1 0 011.32-1.498z"></path>
                          </svg>
                        </a>
                        <a class="wpsr-popup-collapse" href="#" v-if="template_meta.templateType === 'notification'">
                          <i class="icon-angle-right"></i>
                        </a>
                        <ReviewsContainer
                            v-if="(template_meta.templateType === 'badge'
                                && template_meta.badge_settings.display_mode === 'popup')
                                || template_meta.templateType !== 'badge'"
                            :template="template_meta.template"
                            :businessInfo='business_info'
                            :template_meta="template_meta"
                            :reviews="revised_filtered_reviews"
                            @contentExpand="expandContent"
                            @rating-filter="handleRatingFilter"
                            @load-more="loadMoreReviews"
                            :image_settings="image_settings"
                            :errors="errors"
                            :infos="infos"
                            :hasMoreReviews="hasMoreReviews"
                            :loadMoreLoading="loadMoreLoading"
                        ></ReviewsContainer>
                      </div>

                      <Notification
                          v-if="template_meta.templateType === 'notification'"
                          :template_meta="template_meta"
                          :reviews="revised_filtered_reviews"
                          :businessInfo='business_info'
                          :class="`wpsr-reviews-notification-${this.$route.params.template_id}`"
                      >
                      </Notification>
                      <div v-if="template_meta.templateType === 'notification'" class="wpsr-placeholder-wrapper">
                        <img class="wpsr-placeholder-image" :src="assets_url+ '/images/template/editor-placeholder.png'" alt="Placeholder Image">
                      </div>
                      <component
                          v-if="template_meta.templateType === 'badge'"
                          :is="badge_component"
                          :template_meta="template_meta"
                          :businessInfo='business_info'
                      />
                    </div>
                    <div v-if="!allFilteredReviews.length && !errors.length">
                      <div class="wpsr-placeholder-wrapper">
                        <img class="wpsr-placeholder-image" :src="assets_url+ '/images/icon/placeholder-template.png'" alt="Placeholder Image">
                      </div>
                    </div>
                  </div>
                </el-main>

                <el-aside class="wpsr-editor-sidebar">
                  <UpgradeApp />
                  <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
                    <el-tab-pane label="General" name="general" class="wpsr-element-tab-item">
                      <el-collapse accordion v-model="activeNames">
                        <ReviewsEditor
                            :template_meta = "template_meta"
                            :post_types = "post_types"
                            :categories = "categories"
                            :type = "type"
                            :types = "types"
                            :platforms = "platforms"
                            :all_reviews = "allAvailableReviews.length > 0 ? allAvailableReviews : allFilteredReviews"
                            :business_info = "all_business_info"
                            :pages = "pages"
                            :image_settings = image_settings
                            :country_list = "country_list"
                            :can_enable_ai_summary = "can_enable_ai_summary"
                            :available_grid_skins="available_grid_skins"
                            @fetchReviews = "fetchReviews"
                            @saveTemplateMeta = "saveTemplateMeta"
                            @handleDevice="handleDevice"
                            @toggleAISummary = "toggleAISummary"
                            @regenerateAISummaryForReviews = "regenerateAISummaryForReviews"
                            @update:template_meta="($event) => template_meta = $event"
                        >
                        </ReviewsEditor>
                      </el-collapse>
                    </el-tab-pane>

                    <el-tab-pane v-if="!template_meta.platform.includes('testimonial')" label="Connection" name="connection" class="wpsr-element-tab-item wpsr-all-platforms">
                      <ReviewsConnections
                          platformActiveTab="reviews"
                          :filteredPlatforms="reviws_platforms"
                          :configurationInfo="hasConfigurationInformation"
                          @update:activeTab="val => activeTab = val"
                      />
                    </el-tab-pane>

                    <el-tab-pane :label="!has_pro ? $t('Style') + ' ' : $t('Style')" name="style" class="wpsr-element-tab-item">
                      <template v-if="!has_pro" #label>
                        <span>{{$t('Style')}}</span>
                        <span style="vertical-align: middle; margin-left: 4px;">
                          <ProCrownIcon :width="18" :height="18" />
                        </span>
                      </template>

                      <el-collapse accordion v-model="activeNames" v-if="template_meta">
                        <div class="wpsr-styling-divider"/>
                        <ReviewsStyleEditor
                            v-if="template_meta.styles_config"
                            :feed_config="template_meta.styles_config"
                            :template_meta="template_meta"
                            :elements="elements"
                            @templateCss="templateCss"
                        />
                      </el-collapse>
                    </el-tab-pane>
                  </el-tabs>

                </el-aside>
            </el-container>
        </div>
    </div>

    <OnboardWelcomePopup
        v-if="showWelcomePopup"
    />

</template>

<script type="text/babel">
import ErrorCard from "../../feeds/templates/ErrorCard";
import ReviewsEditor from "./editor-ui/ReviewsEditor";
import StyleSection from '../../core-ui/editor/StyleSection';
import ReviewsContainer from '../templates/ReviewsContainer';
import FeedEditorGroup from '../../core-ui/editor/EditorGroup';
import Badge1 from "../templates/Badge1";
import Notification from "../templates/Notification";
import {commonMixin} from "../../../mixins/commonMixin";
import {reviewsMixin} from "../../../mixins/reviewsMixin";
import {jsLibraryMixin} from "../../../mixins/jsLibraryMixin";
import EditorHeader from "../../pieces/EditorHeader";
import ReviewsStyleEditor from "./editor-ui/ReviewsStyleEditor";
import EditorViewInfoBar from '../../core-ui/editor/EditorViewInfoBar';
import UpgradeToProButton from '../../views/advertise/UpgradeToProButton';
import {helperStyle} from "../../../mixins/helperStyle";
import { AISummarizerMixin } from "../../../mixins/AISummarizerMixin";
import { markRaw } from 'vue';
import ReviewsPlatforms from "../../views/platforms/reviews/ReviewsPlatforms.vue";
import ReviewsConnections from "./editor-ui/ReviewsConnections.vue";
import ProCrownIcon from "../../pieces/icons/ProCrownIcon"
import UpgradeApp from "../../pieces/UpgradeApp.vue";
import OnboardWelcomePopup from "../../pieces/OnboardWelcomePopup.vue";
import DemoTemplateAlert from "../../pieces/DemoTemplateAlert.vue";

export default {
    name: "ReviewsView",
    mixins: [commonMixin, reviewsMixin, jsLibraryMixin, helperStyle, AISummarizerMixin],
    components: {
        ReviewsConnections,
        ReviewsPlatforms,
        ReviewsStyleEditor,
        EditorHeader,
        ReviewsEditor,
        StyleSection,
        ReviewsContainer,
        Badge1,
        Notification,
        FeedEditorGroup,
        EditorViewInfoBar,
        UpgradeToProButton,
        ErrorCard,
        ProCrownIcon,
        UpgradeApp,
        OnboardWelcomePopup,
        DemoTemplateAlert
    },
    data() {
        return {
            type: '',
            types: '',
            pages: [],
            country_list: [],
            post_types: [],
            loading: false,
            activeNames: ['1'],
            platforms: [],
            badge_component: markRaw(Badge1),
            available_grid_skins:[],
            templateId: '',
            allFilteredReviews:[],
            allAvailableReviews:[],
            business_info:[],
            template_meta: false,
            previous_platforms: [],
            saving: false,
            all_business_info: [],
            categories: [],
            errors: [],
            infos: [],
            activeTab: 'general',
            elements: [],
            style: null,
            responsiveBarDevice: 'desktop',
            resized_images: [],
            image_settings: [],
            revised_filtered_reviews: [],
            isUpdating: false,
            updateTimeout: null,
            reviws_platforms: window.WPSocialReviewsAdmin.platforms_cards,
            hasConfigurationInformation: {},
            showWelcomePopup: false,
            isInitialLoad: false,
            currentRatingFilter: null, // Store current rating filter
            // Load more functionality
            loadMoreLoading: false,
            currentPage: 1,
            hasMoreReviews: false,
            totalReviews: 0,
            allLoadedReviews: [] // Store all loaded reviews
        }
    },
    methods: {
        handleDevice (val, activeDevice) {
          this.$emit('handleDevice' , val, activeDevice);
          let template_type = this.template_meta.templateType;
          this.curTemplateType(template_type);
          if (template_type === 'slider') {
            this.responsiveBarDevice = val;
            this.activeCarousel();
          }
        },
        expandContent(val) {
            let that = this;
            if (this.templateTypeValue === 'masonry') {
                this.activeMasonry('.wpsr-active-masonry-layout', '.wpsr-all-reviews');
            } else {
                this.loading = false;
            }
        },
        $showMoreLess() {
          jQuery(document).off("click", ".wpsr_read_more, .wpsr_read_less");
          let that = this;

          jQuery(document).on("click", ".wpsr_read_more, .wpsr_read_less", function() {
              jQuery(this).closest(".wpsr_add_read_more").toggleClass("wpsr_show_less_content wpsr_show_more_content");
              jQuery(this).closest(".wpsr-yt-popup-video-meta-description").toggleClass("wpsr_show_less_content wpsr_show_more_content");

              if (that.templateTypeValue == 'masonry') {
                  setTimeout(() => {
                      let column = jQuery('.wpsr-active-masonry-layout').data("column");
                      jQuery('.wpsr-active-masonry-layout').masonry({
                        itemSelector: '.wpsr-col-'+column+'',
                      });
                  },100)
              }
          });
        },
        setSkins() {
            let reviewsSkins = [
              {
                key: 'grid1',
                title: 'Vega',
                img: this.assets_url + '/images/template/review-template/template1.png'
              },
              {
                key: 'grid2',
                title: 'Spica',
                img: this.assets_url + '/images/template/review-template/template2.png'
              },
              {
                key: 'grid3',
                title: 'Sirius',
                img: this.assets_url + '/images/template/review-template/template3.png'
              },
              {
                key: 'grid4',
                title: 'Rigel',
                img: this.assets_url + '/images/template/review-template/template4.png'
              },
              {
                key: 'grid5',
                title: 'Procyon',
                img: this.assets_url + '/images/template/review-template/template5.png'
              },
              {
                key: 'grid6',
                title: 'Pollux',
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/review-template/template6.png'
              },
              {
                key: 'grid7',
                title: 'Mimosa',
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/review-template/template7.png'
              },
              {
                key: 'grid8',
                title: 'Hadar',
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/review-template/template8.png'
              },
              {
                key: 'grid9',
                title: 'Deneb',
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/review-template/template9.png'
              },
              {
                key: 'grid10',
                title: 'Polaris',
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/review-template/template10.png'
              }
            ];

            let testimonialSkins = [
              {
                key: 'testimonial1',
                title: 'Testimonial 1',
                img: this.assets_url + '/images/template/testimonial-template/template1.png'
              },
              {
                key: 'testimonial2',
                title: 'Testimonial 2',
                img: this.assets_url + '/images/template/testimonial-template/template2.png'
              },
            ];

            this.available_grid_skins = {
              'reviews_skins': reviewsSkins,
              'testimonial_skins': testimonialSkins,
            }
        },

        curTemplateType(newValue) {
            this.templateTypeValue = newValue;
            let that = this;
            this.loading = true;
            if (newValue === 'slider') {
                setTimeout(() => {
                    that.activeCarousel();
                },500);
            } else if (newValue === 'masonry') {
                setTimeout(() => {
                    that.activeMasonry('.wpsr-active-masonry-layout', '.wpsr-all-reviews');
                },500);
            } else {
              setTimeout(function() {
                that.destroy();
              }, 500);
            }
        },

        fetchReviews() {
          if ( this.template_meta.platform.length > 1 && !this.has_pro ) {
            this.handlePro();
            let platform = this.template_meta.platform[0];
            this.template_meta.platform = [];
            this.template_meta.platform.push(platform);
          }

          let that = this;
          setTimeout(function() {
            that.getUpdatedPostMetaWithReviews();
          }, 500);
        },

        getPlatforms(){
            this.$get(`platforms`)
                .then(response => {
                    if(response) {
                      let platforms = response.platforms;
                      this.platforms = Object.entries(platforms)
                        .filter(([value, label]) => value !== 'testimonial')
                        .map(([value, label]) => ({
                          value,
                          label
                        }));
                        // let valid_platforms = response.platforms
                        // let platforms = ['yelp', 'woocommerce', 'facebook', 'booking.com', 'aliexpress', this.appVars.tp_slug, 'amazon', 'tripadvisor', 'airbnb', 'google', 'fluent_forms', 'custom']
                        // platforms.forEach((platform) => {
                        //     if (valid_platforms[platform]) {
                        //       this.platforms.push({'value': platform, 'label': valid_platforms[platform]})
                        //     }
                        // })
                    }
                }).catch( (errors) => {
                    this.handleError(errors);
                }).always(() => {

                });
        },
        saveTemplateMeta() {
            let template_id = this.$route.params.template_id;
            this.saving = true;

            // Create a new object to ensure reactivity
            const templateMeta = JSON.parse(JSON.stringify(this.template_meta));

            this.$put(`templates/meta/reviews/${template_id}`, {
                template_meta: JSON.stringify(templateMeta)
            }).then(response => {
                if(response) {
                    this.handleSuccess(response.message);
                    this.getPostMetaWithReviews();
                }
            }).catch(error => {
                this.handleError(error);
            }).always(() => {
                this.saving = false;
            });
        },
        formatData(response) {
          let template_details  = response.template_details;
          this.template_title   = template_details.post_title;
          this.templateId       = template_details.ID;
          let template_meta     = response.template_meta;
          this.infos            = response.infos;

          if (template_meta) {
            this.template           = template_meta.template;

            // Preserve current filter settings to prevent UI reset
            const currentFilterByTitle = this.template_meta ? this.template_meta.filterByTitle : null;
            const currentSelectedIncList = this.template_meta ? this.template_meta.selectedIncList : null;
            const currentSelectedExcList = this.template_meta ? this.template_meta.selectedExcList : null;

            this.template_meta      = template_meta;

            // Restore current filter settings if they exist
            if (currentFilterByTitle !== null) {
              this.template_meta.filterByTitle = currentFilterByTitle;
            }
            if (currentSelectedIncList !== null) {
              this.template_meta.selectedIncList = currentSelectedIncList;
            }
            if (currentSelectedExcList !== null) {
              this.template_meta.selectedExcList = currentSelectedExcList;
            }

            this.previous_platforms = template_meta.platform;
          }

          let business_info   = response.business_info;
          this.business_info  = [];
          if (business_info) {
            this.business_info = business_info;
          }

          //all business lists for filter by business
          if (response.all_business_info && response.all_business_info.platforms) {
            this.all_business_info = response.all_business_info.platforms;
          }

          let filtered_reviews    = response.filtered_reviews;
          this.allFilteredReviews = filtered_reviews;

          // Store all available reviews for include/exclude dropdowns
          if (response.all_reviews) {
            this.allAvailableReviews = response.all_reviews;
          }

          // Initialize load more state
          this.allLoadedReviews = [...filtered_reviews];
          this.currentPage = 1;

          // Update load more state based on current filter settings
          this.updateLoadMoreState();

          this.handleReviewerName(filtered_reviews);
        },

        handleReviewerName(reviews) {
          const {
            reviewer_name: shouldShowName,
            reviewer_name_format: nameFormat = 'full-name'
          } = this.template_meta;

          if (shouldShowName !== 'true') {
            this.revised_filtered_reviews = reviews;
            return;
          }

          const revisedReviews = reviews.map((review) => {
            const fullName = (review.reviewer_name || '').trim();

            if (!fullName) {
              return { ...review, reviewer_name: '' };
            }

            const nameParts = fullName.split(/\s+/);
            const firstName = nameParts[0] || '';
            const lastName = nameParts[1] || '';

            let formattedName;

            switch (nameFormat) {
              case 'first-name':
                formattedName = firstName;
                break;

              case 'first-name-last-initial':
                formattedName = `${firstName} ${lastName ? `${lastName[0].toUpperCase()}.` : ''}`.trim();
                break;

              case 'initials-only':
                const firstInitial = firstName ? `${firstName[0].toUpperCase()}.` : '';
                const lastInitial = lastName ? `${lastName[0].toUpperCase()}.` : '';
                formattedName = `${firstInitial} ${lastInitial}`.trim();
                break;

              case 'full-name':
              default:
                formattedName = fullName;
                break;
            }

            return {
              ...review,
              reviewer_name: formattedName
            };
          });

          this.revised_filtered_reviews = revisedReviews;
        },

        getUpdatedPostMetaWithReviews() {
            this.template_meta['previous_platforms'] = this.previous_platforms;
            this.loading = true;
            let templateId = this.$route.params.template_id;

            this.$post(`templates/meta/reviews/${templateId}/edit`,{
                template_meta: JSON.stringify(this.template_meta)
            }).then(response => {
              if(response && response.template_meta) {
                if(response && response.image_settings.optimized_images === 'true') {
                  if(response.resize_data.length < response.filtered_reviews.length) {
                      this.saveReviewImages(templateId, response.template_meta['platform']);
                  }
                }
                this.formatData(response);
              }
              if(response.ai_summary_errors !== undefined) {
                this.handleError(response.ai_summary_errors);
              }
            }).catch((errors) => {
              this.handleError(errors);
            }).always(() => {
                this.loading = false;
            });
        },

        getPostMetaWithReviews() {

            this.loading = true;
            let template_id = this.$route.params.template_id;

            let url = `templates/meta/reviews/${template_id}`;

            if(this.isFirstLoad) {
                url += `/first-round/${this.isFirstLoad}`;
            }

            this.$get(url)
                .then(response => {
                    if(response && response.template_meta){
                      this.type   = response.template_details.post_name.includes('testimonial') ? 'testimonial' : 'review';
                      this.types  = this.type === 'review' ? 'reviews' : this.type;

                      this.formatData(response);

                        this.pages        = response.pages;
                        this.country_list = response.country_list;
                        this.post_types   = response.post_types;
                        this.categories   = response.categories;
                        this.elements     = response.elements;
                        this.errors       = response.errors;
                        this.infos       = response.infos;
                        this.can_enable_ai_summary = response.can_enable_ai_summary;
                        if(response.ai_summary_errors !== undefined) {
                          this.handleError(response.ai_summary_errors);
                        }

                        this.resized_images = response.resized_images;
                        this.image_settings = response.image_settings;
                        let resizedArray = Object.values(response.resized_images);
                        if(response && resizedArray && this.image_settings && this.image_settings.optimized_images === 'true') {
                          if(response && resizedArray.length < response.filtered_reviews.length) {
                            this.saveReviewImages(template_id, response.template_meta['platform']);
                          }
                        }
                      return response.needs_immediate_update;
                    }
                }).then((needsImmidiateUpdate) => {
                  if(needsImmidiateUpdate) {
                    this.getUpdatedPostMetaWithReviews();
                  } else{
                    this.loading = false;
                  }
                }).catch(errors => {
                    this.handleError(errors);
                }).always(() => {
                    this.loading = false;
                    this.isFirstLoad = false;
                    this.isEditing = false;
                });
        },

        templateCss(val , val2) {
          this.template_meta['styles'] = val;
          this.template_meta['responsive_styles'] =  val2;

          var style = '';
          var tabletStyle;
          val2.forEach((styleVal) => {
            if(styleVal){
              tabletStyle = styleVal.replace('@media screen and (max-width: 960px){', '.is-tablet-active ');
              style += tabletStyle.replace('@media screen and (max-width: 480px){', '.is-mobile-active ');
            }
            style = style.replace('}}', '}');
            style = style.replace('}}', '}');
            this.style = style;
          });
        },

        saveReviewImages(templateId, platforms){
          if(platforms.includes('testimonial')){
            return;
          }

          let submitData = {
              action: 'wpsr_review_resize_images',
              id: templateId,
              platforms: platforms,
              resize_data: this.resized_images,
              nonce: this.appVars && this.appVars.nonce ? this.appVars.nonce : ''
          };
          let ajaxUrl = this.appVars.ajaxurl;

          jQuery.post({
              url: ajaxUrl,
              data: submitData,
          }).then(response => {
            if(response) {
              let jsonObjects = response.match(/({[^{}]+})/g);
              let image_data_arrays = JSON.parse(jsonObjects);
              if(image_data_arrays.images_data) {
                this.loadImage(image_data_arrays.images_data);
              }
            }
          }).catch(errors => {
          }).always(() => {

          });
        },
        loadImage(image_data){
          let upload_url = this.appVars.upload_url;
          let that = this;
          let id = null;
          let source_id = null;
          let review_platform = null;
          let image_size;
          let image_data_arrays = Object.values(image_data);
          let image_format = this.appVars.image_format
          jQuery(document).ready(function() {
              jQuery(`.wpsr-all-reviews`).find(`.wpsr-review-template`).each(function (index) {
                id = jQuery(this).attr("data-media_id");
                source_id = jQuery(this).attr("data-source_id");
                image_size = jQuery(this).attr("data-image_size");
                review_platform = jQuery(this).attr("data-review_platform");
                id = id ? id.toString() : null; //convert from integer to string id

                if (review_platform && id && image_size && image_data_arrays.includes(id)) {
                    let image = upload_url +  review_platform + '/'+ source_id +'/' + id + '_' + image_size + '.' + image_format;
                    let self = jQuery(this);
                    self.find(`.wpsr-reviewer-img`).attr('src', image);
                    self.find('.wpsr-reviewer-image-url').removeClass('wpsr-reviewer-image-animation');
                }
              });
          });
        },
        getEditorInfo(){
          return 'Showing ' + this.revised_filtered_reviews?.length + ' out of ' + this.revised_filtered_reviews?.length + ' Reviews';
        },
        getTemplateName(){
          const match = this.available_grid_skins['reviews_skins'].find(element => element.key === this.template_meta.template);
          return match ? match.title : '';
        },
        handleRatingFilter(filterData) {
          // Store the current rating filter
          this.currentRatingFilter = filterData;
        },

        loadMoreReviews() {
          if (this.loadMoreLoading || !this.hasMoreReviews || this.template_meta.templateType === 'notification') {
            return;
          }

          this.loadMoreLoading = true;
          const nextPage = this.currentPage + 1;
          const templateId = this.$route.params.template_id;

          this.$post(`templates/meta/reviews/${templateId}/load-more`, {
            template_meta: JSON.stringify(this.template_meta),
            page: nextPage
          }).then(response => {
            if (response && response.reviews && response.reviews.length > 0) {
              // Add new reviews to the existing list
              this.allLoadedReviews = [...this.allLoadedReviews, ...response.reviews];

              // Update the filtered reviews with all loaded reviews
              this.allFilteredReviews = [...this.allLoadedReviews];
              this.handleReviewerName(this.allFilteredReviews);

              // Update pagination state
              this.currentPage = nextPage;
              this.hasMoreReviews = response.has_more;
              this.totalReviews = response.total_reviews;
            } else {
              // No more reviews to load
              this.hasMoreReviews = false;
            }
          }).catch(error => {
            this.handleError(error);
          }).always(() => {
            this.loadMoreLoading = false;
          });
        },

        updateLoadMoreState() {
          const filterByTitle = this.template_meta.filterByTitle || 'all';

          if (filterByTitle === 'include' || filterByTitle === 'exclude') {
            // For include/exclude, all matching reviews are loaded initially - no load more needed
            this.hasMoreReviews = false;
            this.totalReviews = this.allFilteredReviews.length;
          } else {
            // Normal pagination behavior for 'all' filter
            let paginate = this.template_meta.paginate || 6;
            // Check if pagination is enabled and if there might be more reviews
            if (this.template_meta) {
              // Show load more if we have exactly the paginate amount (suggests more might be available)
              // or if we have more reviews available than currently displayed
              this.hasMoreReviews = this.allFilteredReviews.length >= paginate;
              this.totalReviews = 0; // Will be updated from load more response
            } else {
              // Pagination is disabled
              this.hasMoreReviews = false;
             // this.totalReviews = this.allFilteredReviews.length;
            }
          }
        },
    },

    watch: {
        'loading': {
            handler(val) {
                // Only show popup on initial load, not on subsequent loads
                if (val && !this.isInitialLoad) {
                  this.showWelcomePopup = true;
                  this.isInitialLoad = true;
                } else if (!val) {
                  this.showWelcomePopup = false;
                }
            },
            immediate: true
        },
        'template_meta.reviewer_name_format'(newVal, oldVal) {
          if (newVal) {
            this.handleReviewerName(this.allFilteredReviews);
          }
        },

        'template_meta.filterByTitle'(newVal, oldVal) {
          // Reload reviews when filterByTitle changes
          // Only trigger if not during initial load and values actually changed
          if (newVal !== oldVal && oldVal !== undefined && !this.isFirstLoad) {
            if (newVal === 'include' || newVal === 'exclude') {
              this.getUpdatedPostMetaWithReviews();
              // Update load more state to disable it for include/exclude
              this.updateLoadMoreState();
            }
          }
        },

        'template_meta': {
            handler(newVal) {
                if (newVal && !this.isUpdating) {
                    this.isUpdating = true;

                    // Debounce the update to prevent too frequent updates
                    if (this.updateTimeout) {
                        clearTimeout(this.updateTimeout);
                    }

                    this.updateTimeout = setTimeout(() => {
                        if (this.template_meta.templateType === 'masonry') {
                            this.loading = true;
                            let that = this;
                            setTimeout(() => {
                                that.activeMasonry('.wpsr-active-masonry-layout', '.wpsr-all-reviews');
                                that.isUpdating = false;
                            }, 100);
                        } else {
                            if (this.template_meta.templateType === 'badge' && this.template_meta.badge_settings.display_mode !== 'popup') {
                                jQuery('#wpsr-reviews-grid').removeClass('active');
                            }
                            this.isUpdating = false;
                        }
                    }, 300); // 300ms debounce
                }
            },
            deep: true
        },
        'template_meta.templateType': {
            handler(val, oldTemplate) {
                this.templateTypeValue = val;
                this.curTemplateType(val);

                if((oldTemplate === 'badge' || oldTemplate === 'notification' || oldTemplate === 'slider') && (val === 'grid' || val === 'masonry')) {
                  if(this.template_meta.responsive_column_number){
                      this.template_meta.responsive_column_number.desktop = '4';
                      this.template_meta.responsive_column_number.tablet = '4';
                  }
                }

                if(val === 'badge' || val === 'notification') {                  
                  if(this.template_meta.responsive_column_number){
                    this.template_meta.responsive_column_number.desktop = '12';
                    this.template_meta.responsive_column_number.tablet = '12';
                  }
                }
            },
            deep: true,
        },
        'template_meta.template': {
          handler(val, oldVal) {
            let template_type = this.template_meta.templateType;
            this.curTemplateType(template_type);

            // Only set default columns to 12 when switching TO grid10/template10 from a different template
            // Don't override if user has explicitly set values after initial load
            if ((val === 'grid10' || val === 'template10') && (oldVal !== 'grid10' && oldVal !== 'template10')) {

              if (this.template_meta.responsive_column_number) {
                if (!this.template_meta.responsive_column_number.desktop) {
                  this.template_meta.responsive_column_number.desktop = '12';
                }
                if (!this.template_meta.responsive_column_number.tablet) {
                  this.template_meta.responsive_column_number.tablet = '12';
                }
                if (!this.template_meta.responsive_column_number.mobile) {
                  this.template_meta.responsive_column_number.mobile = '12';
                }
              }
            }
          },
          deep: true
        },
        'template_meta.responsive_column_number.desktop': {
          handler(val) {
            let template_type = this.template_meta.templateType;
            this.curTemplateType(template_type);
          },
          deep: true
        },
        'template_meta.responsive_column_number.tablet': {
          handler(val) {
            let template_type = this.template_meta.templateType;
            this.curTemplateType(template_type);
          },
          deep: true
        },
        'template_meta.responsive_column_number.mobile': {
          handler(val) {
            let template_type = this.template_meta.templateType;
            this.curTemplateType(template_type);
          },
          deep: true
        },
        'template_meta.carousel_settings': {
          handler(val) {
            let template_type = this.template_meta.templateType;
            this.curTemplateType(template_type);
          },
          deep: true
        },
    },
    computed: {
      css() {
        if(this.style) {
          if(this.styleTag) {
            this.styleTag.remove()
          }
          this.styleTag = document.createElement('style');
          this.styleTag.type = 'text/css';
          this.styleTag.id = 'wp-social-reviews-editor-responsive-css';
          this.styleTag.appendChild(document.createTextNode(this.style));
          document.head.appendChild(this.styleTag);
        }
      }
    },
    mounted() {
        // firstLoad is used to detect if the page is loaded for the first time.
        // based on that ai summary is generated.
        // if IsFirstLoad is true there is no ai summary cached in the database
        // it will skip generating the ai summary for letting the editor page load faster.

        this.isFirstLoad = true;

        this.getPostMetaWithReviews();
        this.getPlatforms();
        this.checkFullScreen();
        let that = this;
        setTimeout(function() {
            that.$showMoreLess();
        },100);
        const $body = jQuery('body');
        $body.addClass('is-wpsr-editor');
        this.clipboard();

        const handleDeviceCallback = (val, activeDevice) => {
            this.handleDevice(val, activeDevice);
        };

        // Store callback reference
        this._handleDeviceCallback = handleDeviceCallback;

        // Use the global mixin's event methods
        this.$on('handleDeviceResponsive', handleDeviceCallback);

        // Check for 'configuration' param in hash and set activeTab to 'connection' if present
        let hash = window.location.hash || '';
        let queryString = hash.split('?')[1] || '';
        const urlParams = new URLSearchParams(queryString);
        if (urlParams.has('configuration')) {
          this.activeTab = 'connection';
          const platform = urlParams.get('platform');
          const oauthToken = urlParams.get('oauth_token');

          this.hasConfigurationInformation = {
              configuration: true,
              platform: platform || null,
              oauth_token: oauthToken || null,
              platform_type: 'reviews'
          };
        }

        this.checkAndEditorWelcomePopup();
        this.setSkins();
    },
    beforeUnmount() {
        // Clean up event listener
        if (this._handleDeviceCallback) {
            this.$off('handleDeviceResponsive', this._handleDeviceCallback);
        }
    }
}
</script>