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
          </el-form-item>
        </div>

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
                    :content="$t('Category of the') + ' ' + type + '. ' + $t('You can filter using this category.')"
                >
                  <el-icon size="18" color="var(--wpsr-icon-info-secondary-color)"><InfoFilled /></el-icon>
                </el-tooltip>
              </h3>
            </template>
            <el-input
                class="wpsr-input-default wpsr-border-all-around"
                :placeholder="options[type].category"
                v-model="review.category"
                size="small">
            </el-input>
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
                v-model="review.rating"
                color="var(--wpsr-star-color)"
            ></el-rate>

            <error-view :errors="errors" :field="'review.rating'" />
          </el-form-item>
        </div>
      </div>
      <div class="wpsr-connection-modal-footer wpsr-d-flex wpsr-jc-end wpsr-border-top">
        <el-button class="wpsr_default_btn" @click="cancelModal">Cancel</el-button>
        <el-button class="wpsr_primary_btn" :disabled="saving" v-loading="saving" type="primary" @click="addData" size="default" v-if="action === 'add'">
          {{ $t('Save')}}
        </el-button>
        <el-button class="wpsr_primary_btn" :disabled="saving" v-loading="saving" type="primary" @click="updateData" size="default" v-if="action === 'edit'">
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

      <UpgradeToProButton
          classes="wpsr_buy_pro_btn_inline"
      />

    </div>
  </div>
  <PermissionsNotification v-if="!hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])" />
  </div>
</template>

<script type="text/babel">
import WpEditor from "../core-ui/editor/WpEditor";
import PhotoUploader from "../core-ui/editor/PhotoUploader";
import Errors from './../../errors/Errors';
import ErrorView from './../../errors/errorView';
import UpgradeToProButton from '../views/advertise/UpgradeToProButton';
import PermissionsNotification from "./advertise/PermissionsNotification";
import {InfoFilled} from "@element-plus/icons-vue";

export default {
  name: 'ReviewForm',
  props: ['review', 'action', 'type'],
  components: {
    InfoFilled,
    PermissionsNotification,
    WpEditor,
    PhotoUploader,
    ErrorView,
    UpgradeToProButton
  },
  data() {
    return {
      saving: false,
      errors: new Errors(),
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
  methods: {
    cancelModal() {
      this.$emit('hideFormModal', true);
    },
    updateData() {
      this.saving = true;
      this.$put(`${this.type}s/${this.review.id}`, {
        review: this.review
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
        review: this.review
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
  mounted() {
    this.errors.record({});
  }
}
</script>