<template>
  <div>
  <div class="wpsr-connection-modal-body" v-if="hasPermission(['wpsn_manage_reviews', 'wpsn_manage_testimonials', 'wpsn_full_access'])">
    <el-form label-position="top">
      <div class="wpsr-modal-form-fields">
        <div class="wpsr-d-flex wpsr-jc-between wpsr-gap-12">
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].name }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Name of the author of the') + ' ' + type"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-input
                class="wpsr-input-default wpsr-border-all-around"
                :placeholder="options[type].name"
                v-model="review.reviewer_name"
                size="small">
            </el-input>
            <error-view :errors="errors" :field="'review.reviewer_name'" />
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].url }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Link of the person who is the author of the') + ' ' + type"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-input
                class="wpsr-input-default wpsr-border-all-around"
                :placeholder="options[type].url"
                v-model="review.reviewer_url"
                size="small">
            </el-input>
            <error-view :errors="errors" :field="'review.reviewer_url'" />
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].img }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Upload a avatar or image of the') + ' ' + type + '. ' + $t('Use a 120x120 pixel square image')"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <photo-uploader enable_clear="yes" design_mode="horizontal" v-model="review.reviewer_img"/>
            <error-view :errors="errors" :field="'review.reviewer_img'" />
          </el-form-item>
        </div>

        <div class="wpsr-d-flex wpsr-jc-between wpsr-gap-12" v-if="type === 'testimonial'">
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].author_position }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Position of the author of the') + ' ' + type"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-input
                class="wpsr-input-default wpsr-border-all-around"
                :placeholder="options[type].author_position"
                v-model="review.fields.author_position"
                size="small">
            </el-input>
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].author_company }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Company of the person who is the author of the') + ' ' + type"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-input
                class="wpsr-input-default wpsr-border-all-around"
                :placeholder="options[type].author_company"
                v-model="review.fields.author_company"
                size="small">
            </el-input>
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].author_website_logo }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Upload author website logo. Use a 120x120 pixel square image')"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <photo-uploader enable_clear="yes" design_mode="horizontal" v-model="review.fields.author_website_logo"/>
            <error-view :errors="errors" :field="'review.fields.author_website_logo'" />
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].author_website_url }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Author website URL.')"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-input
                class="wpsr-input-default wpsr-border-all-around"
                :placeholder="options[type].author_website_url"
                v-model="review.fields.author_website_url"
                size="small">
            </el-input>
            <error-view :errors="errors" :field="'review.fields.author_website_url'" />
          </el-form-item>
        </div>

        <el-form-item class="wpsr-mb-32" v-if="type === 'testimonial'" :error="videoUrlError">
          <template #label>
            <h3 class="wpsr-connection-modal-input-heading">
              {{ options[type].video_url }}
              <el-tooltip
                  effect="dark"
                  placement="right-start"
                  :raw-content="true"
                  :content="$t('Paste a video link (YouTube, Vimeo or a direct file URL), or upload a video from your media library.')"
              >
                <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
              </el-tooltip>
            </h3>
          </template>
          <video-uploader
              v-model="review.fields.video_url"
              @update:invalid="videoUrlInvalid = $event"
          />
          <error-view :errors="errors" :field="'review.fields.video_url'" />
          <!-- Outside the poster block below: a stored poster can be rejected while
               the video is a provider link, and that block is hidden in that case. -->
          <error-view :errors="errors" :field="'review.fields.video_poster'" />
        </el-form-item>

        <!-- Only uploaded files need one. YouTube and Vimeo carry their own thumbnail. -->
        <el-form-item class="wpsr-mb-32" v-if="type === 'testimonial' && needsVideoPoster">
          <template #label>
            <h3 class="wpsr-connection-modal-input-heading">
              {{ options[type].video_poster }}
              <el-tooltip
                  effect="dark"
                  placement="right-start"
                  :raw-content="true"
                  :content="$t('The image shown before the video plays. Without one an uploaded video starts as a black box.')"
              >
                <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
              </el-tooltip>
            </h3>
          </template>
          <photo-uploader enable_clear="yes" design_mode="horizontal" v-model="review.fields.video_poster"/>
        </el-form-item>

        <el-form-item class="wpsr-mb-32">
          <template #label>
            <h3 class="wpsr-connection-modal-input-heading">
              {{ options[type].title }}
              <el-tooltip
                  effect="dark"
                  placement="right-start"
                  :raw-content="true"
                  :content="$t('Add a title of the') + ' ' + type"
              >
                <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
              </el-tooltip>
            </h3>
          </template>
          <el-input
              class="wpsr-input-default wpsr-border-all-around"
              :placeholder="options[type].title"
              v-model="review.review_title"
              size="small">
          </el-input>
        </el-form-item>
        <el-form-item>
          <template #label>
            <h3 class="wpsr-connection-modal-input-heading">
              {{ options[type].text }}
              <el-tooltip
                  effect="dark"
                  placement="right-start"
                  :raw-content="true"
                  :content="$t('Add a description of the') + ' ' + type"
              >
                <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
              </el-tooltip>
            </h3>
          </template>
          <wp-editor
              v-model="review.reviewer_text"
              :height="80"
              :media_buttons="false"
          >
          </wp-editor>
          <error-view class="wpsr-mt-15" :errors="errors" :field="'review.reviewer_text'" />
        </el-form-item>

        <div class="wpsr-d-flex wpsr-jc-between wpsr-gap-12">
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].date }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Select a date of the') + ' ' + type"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <div class="block">
              <el-date-picker
                  class="wpsr-input-default wpsr-border-all-around"
                  v-model="review.review_time"
                  type="datetime"
                  placeholder="Pick a date"
                  size="small"
                  value-format="YYYY-MM-DD HH:mm:ss"
              >
              </el-date-picker>
            </div>
            <error-view :errors="errors" :field="'review.review_time'" />
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].category }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Assign one or more categories. Pick existing ones or type a new name and press enter to create it.')"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-select
                class="wpsr-input-default wpsr-border-all-around"
                v-model="review.category_ids"
                multiple
                filterable
                allow-create
                collapse-tags
                collapse-tags-tooltip
                default-first-option
                :reserve-keyword="false"
                :placeholder="options[type].category"
                size="small">
              <el-option
                  v-for="category in categories"
                  :key="category.id"
                  :label="category.name"
                  :value="category.id">
              </el-option>
            </el-select>
          </el-form-item>
          <el-form-item>
            <template #label>
              <h3 class="wpsr-connection-modal-input-heading">
                {{ options[type].rating }}
                <el-tooltip
                    effect="dark"
                    placement="right-start"
                    :raw-content="true"
                    :content="$t('Define the rating associated with the') + ' ' + type"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-rate
                clearable
                :model-value="Number(review.rating) || 0"
                @update:model-value="val => review.rating = val"
            ></el-rate>

            <error-view :errors="errors" :field="'review.rating'" />
          </el-form-item>
        </div>
      </div>
      <div class="wpsr-connection-modal-footer wpsr-d-flex wpsr-jc-end wpsr-border-top">
        <el-button class="wpsr_default_btn" @click="cancelModal">Cancel</el-button>
        <el-button class="wpsr_primary_btn" :disabled="saving || videoUrlInvalid" v-loading="saving" type="primary" @click="addData" size="default" v-if="action === 'add'">
          {{ $t('Save')}}
        </el-button>
        <el-button class="wpsr_primary_btn" :disabled="saving || videoUrlInvalid" v-loading="saving" type="primary" @click="updateData" size="default" v-if="action === 'edit'">
          {{ $t('Update') }}
        </el-button>
      </div>
    </el-form>
    <div v-if="!has_pro" class="wpsr-pro-overlay">
      <h3>Custom Reviews</h3>
      <p class="wpsr_promote_description">WP Social Ninja lets you collect customer reviews right from your website & customize the reviews to exhibit them beautifully.</p>
      <ul>
        <li>Grid/Slider/Masonry layout variation</li>
        <li>9+ templates</li>
        <li>Combine multiple platform</li>
        <li>Popular page builder widget ready</li>
        <li>Shortcode integration</li>
        <li>Responsive query</li>
        <li>Export/import custom reviews</li>
        <li>Filter by minimum rating</li>
        <li>Include/exclude specific reviews</li>
        <li>Extensive Style Option</li>
        <li>Shorten longer reviews</li>
        <li>Scroll the text in the review box</li>
        <li>Ajax Load More Pagination</li>
      </ul>
      <iframe class="wpsr-mt-20" width="660" height="360" src="https://www.youtube.com/embed/K94shMHULe0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
      <span class="pro_dialog">Upgrade to WP Social Ninja Pro and unlock all the features.</span>

      <UpgradeToProButton placement="feature_lock_custom_reviews" />

    </div>
  </div>
  <PermissionsNotification v-if="!hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])" />
  </div>
</template>

<script type="text/babel">
import WpEditor from "../core-ui/editor/WpEditor";
import PhotoUploader from "../core-ui/editor/PhotoUploader";
import VideoUploader from "../core-ui/editor/VideoUploader";
import Errors from './../../errors/Errors';
import ErrorView from './../../errors/errorView';
import UpgradeToProButton from '../views/advertise/UpgradeToProButton';
import PermissionsNotification from "./advertise/PermissionsNotification";
import {InfoFilled} from "@element-plus/icons-vue";
import { wpsrIsVideoFileUrl, wpsrIsHttpUrl } from "../../utils/videoUrl";

export default {
  name: 'ReviewForm',
  props: ['review', 'action', 'type'],
  components: {
    InfoFilled,
    PermissionsNotification,
    WpEditor,
    PhotoUploader,
    VideoUploader,
    ErrorView,
    UpgradeToProButton
  },
  data() {
    return {
      saving: false,
      // Raised by VideoUploader once the field loses focus with an unusable URL.
      videoUrlInvalid: false,
      errors: new Errors(),
      categories: [],
      options: {
        testimonial: {
          name: 'Author Name',
          url: 'Author URL',
          title: 'Title',
          text: 'Testimonial Text ',
          rating: 'Rating',
          date: 'Date',
          img: 'Author Image',
          category: 'Category',
          author_position: 'Author Position',
          author_company: 'Company Name',
          author_website_logo: 'Website Logo',
          author_website_url: 'Website URL',
          video_url: 'Video',
          video_poster: 'Video Thumbnail',
        },
        review: {
          name: 'Reviewer Name',
          url: 'Reviewer Url',
          title: 'Review Title',
          text: 'Reviewer Text ',
          rating: 'Reviewer Rating',
          date: 'Review Date',
          img: 'Reviewer Image',
          category: 'Review Category'
        }
      }
    }
  },
  computed: {
    needsVideoPoster() {
      return wpsrIsVideoFileUrl(this.review.fields && this.review.fields.video_url);
    },
    /** The server 422s a non-http URL, so it is stopped at the form instead. */
    videoUrlError() {
      return this.videoUrlInvalid
        ? this.$t('Enter a full URL that starts with http:// or https://')
        : '';
    }
  },
  methods: {
    cancelModal() {
      this.$emit('hideFormModal', true);
    },
    loadCategories() {
      // `${type}s`, not a hardcoded "reviews": this form is shared with the
      // testimonials screen, which would 403 on the reviews-prefixed route.
      this.$get(`${this.type}s/categories`).then(response => {
        // Number() is load-bearing: reviewPayload() tells an existing category from
        // a new name by `typeof value === 'number'`.
        this.categories = (response.categories || []).map(category => ({
          ...category,
          id: Number(category.id)
        }));
      }).catch(() => {});
    },
    /**
     * Split el-select's mixed ids and new names, which the server cannot tell apart
     * by value — a category named "2024" looks like id 2024.
     */
    reviewPayload() {
      const selected = Array.isArray(this.review.category_ids) ? this.review.category_ids : [];
      const fields = {...this.review.fields};

      if (!this.needsVideoPoster && fields.video_poster && !wpsrIsHttpUrl(fields.video_poster)) {
        fields.video_poster = '';
      }

      return {
        ...this.review,
        fields,
        category_ids: selected.filter(value => typeof value === 'number'),
        new_category_names: selected.filter(value => typeof value === 'string')
      };
    },
    updateData() {
      this.saving = true;
      this.$put(`${this.type}s/${this.review.id}`, {
        review: this.reviewPayload()
      }).then(response => {
        this.handleSuccess(response.message);
        this.$emit('hideFormAfterSuccess', response.review);
      }).catch(errors => {
        this.errors.record(errors.responseJSON);
      }).always(() => {
        this.saving = false;
      });
    },
    addData() {
      if (!this.has_pro) {
        this.handlePro();
        return false;
      }
      this.saving = true;
      this.$post(`${this.type}s`, {
        review: this.reviewPayload()
      }).then(response => {
        this.$emit('hideFormAfterSuccess', response.review);
        this.handleSuccess(response.message);
      }).catch(errors => {
        this.errors.record(errors.responseJSON);
      }).always(() => {
        this.saving = false;
      });
    },
  },
  /**
   * Before render, not mounted: the testimonial inputs bind straight into
   * review.fields, and a row saved without one would throw on the first render.
   */
  created() {
    const fields = this.review.fields;

    if (!fields || typeof fields !== 'object' || Array.isArray(fields)) {
      this.review.fields = {};
    }
  },
  mounted() {
    this.errors.record({});
    this.loadCategories();

    // The select binds to ids; derive them from the eager-loaded relation.
    this.review.category_ids = (this.review.categories || []).map(category => Number(category.id));
  }
}
</script>