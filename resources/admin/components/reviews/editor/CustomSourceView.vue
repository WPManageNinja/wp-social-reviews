<template>
  <GlobalMainNav />
  <div class="wpsr-page-container wpsr-all-templates-page">
    <div v-if="hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="wpsr-page-section">
        <div class="wpsr-table-wrapper">
          <WpsrSkeleton :rows="15" />
        </div>
      </div>

      <!-- Table Content -->
      <div class="wpsr-page-section">
        <!-- Breadcrumb Navigation -->
        <div class="wpsr-breadcrumb">
          <router-link to="/custom-sources" class="wpsr-breadcrumb-link">Sources</router-link>
          <span class="wpsr-breadcrumb-separator"><el-icon><ArrowRight /></el-icon></span>
          <span class="wpsr-breadcrumb-current">{{ $trimWords(settingsForm.source_label, 3, true) || $trimWords(sourceData.post_title, 3, true) }}</span>
        </div>

        <!-- Header Section -->
        <div class="wpsr-table-header">
          <div class="wpsr-header-left wpsr-d-flex wpsr-flex-align-center">
            <div v-if="settingsForm.logo">
              <img :src="settingsForm.logo" alt="">
            </div>
            <div>
              <h1 class="wpsr-page-title">{{ $trimWords(settingsForm.source_label, 3, true) || $trimWords(sourceData.post_title, 3, true) }}</h1>
              <p class="wpsr-page-subtitle">{{ $t('Manage and view all reviews') }}</p>
            </div>
          </div>
          <div class="wpsr-header-right">
            <el-button v-if="settingsForm && settingsForm.form_id" class="wpsr_default_btn" size="small" type="primary" @click="generateQRcode">
              <img style="width: 14px; height: 14px;" class="wpsr-mr-5 wpsr-qr-code-icon" :src="assets_url+'/images/icon/get_a_review.png'" alt="">
              Get Reviews via QR Code
            </el-button>

            <router-link to="/tools/import" class="el-button wpsr_default_btn wpsr_default_btn_primary">
              <el-icon class="wpsr-mr-5"><Upload /></el-icon>
              Import
            </router-link>

            <el-button class="wpsr_default_btn wpsr_default_btn_primary" @click="exportCustomReviews" :disabled="!has_pro">
              <el-icon class="wpsr-mr-5"><Download /></el-icon>
              Export
            </el-button>

            <el-button class="wpsr_default_btn wpsr_default_btn_primary" size="small" type="primary" @click="showGeneralSettingsDialog = true">
              <el-icon class="wpsr-mr-5"><Setting /></el-icon>
              General Settings
            </el-button>

            <el-button class="wpsr_default_btn wpsr_default_btn_primary" size="small" type="primary" @click="addNewItem">
              <el-icon class="wpsr-mr-5"><Plus /></el-icon>
              Create Template
            </el-button>

            <el-button size="small" type="primary" class="wpsr_primary_btn" @click.prevent="beforeAddHandler">
              <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Add Custom Review') }} {{ !has_pro ? $t('(Pro)') : ''}}
            </el-button>
          </div>
        </div>

        <div v-if="total">
          <!-- Filters and Actions -->
          <div class="wpsr-table-controls">
            <div class="wpsr-controls-left">
              <div class="wpsr-controls-bulk-actions">
                <el-select class="wpsr-select-field-primary" v-model="bulkAction" placeholder="Bulk Action" size="default" style="width: 160px;">
                  <el-option label="Enable" value="enable">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><CircleCheck /></el-icon>
                    <span>Enable</span>
                  </span>
                  </el-option>
                  <el-option label="Disable" value="disable">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><CircleClose /></el-icon>
                    <span>Disable</span>
                  </span>
                  </el-option>
                  <el-option label="Delete" value="delete">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><Delete /></el-icon>
                    <span>Delete</span>
                  </span>
                  </el-option>
                  <el-option label="Duplicate" value="duplicate">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><DocumentCopy /></el-icon>
                    <span>Duplicate</span>
                  </span>
                  </el-option>
                </el-select>
                <el-button @click="applyBulkAction" :disabled="!selectedItems.length">Apply</el-button>
              </div>
            </div>
            <div class="wpsr-controls-right">
              <el-input
                  class="wpsr-input-default"
                  v-model="search_string"
                  placeholder="Search..."
                  size="default"
                  style="width: 200px;"
                  @keyup.enter="getItems()"
                  @clear="handleClear"
                  clearable>
                <template #prefix>
                  <el-icon class="el-input__icon" @click="getItems()"><Search /></el-icon>
                </template>
              </el-input>
            </div>
          </div>

          <div class="wpsr-table-wrapper" v-loading.fullscreen.lock="duplicatingItem" element-loading-text="Please wait...">
            <el-table
                ref="customSourceTable"
                class="wpsr-templates-table"
                :data="items"
                @selection-change="handleSelectionChange"
                @sort-change="handleTableSort"
            >
              <el-table-column type="selection" width="50" align="center"></el-table-column>

              <el-table-column :label="$t('ID')" width="70" prop="ID" sortable>
                <template #default="scope">
                  <span class="wpsr-item-id">#{{ scope.row.id }}</span>
                </template>
              </el-table-column>

              <el-table-column :label="$t('Review')" min-width="200">
                <template #default="scope">
                  <strong class="wpsr-item-title">{{ scope.row.review_title }}</strong>
                  <ReviewContentModal
                      :content="scope.row.reviewer_text"
                      :title="scope.row.review_title"
                      :rating="scope.row.rating"
                      :reviewerName="scope.row.reviewer_name"
                      :reviewerImg="scope.row.reviewer_img"
                      :date="scope.row.review_time"
                      :platformIcon="getPlatformIconsHtml(scope.row.platform_name)"
                      :word-limit="10"
                  />
                </template>
              </el-table-column>

              <el-table-column show-overflow-tooltip :label="$t('Reviewer')" min-width="150">
                <template #default="scope">
                  <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                    <img class="wpsr-image-cell" v-if="scope.row.reviewer_img" :src="scope.row.reviewer_img" height="80" width="80"/>
                    <img class="wpsr-image-cell" v-else :src="assets_url+'/images/template/review-template/placeholder-image.png'"
                         height="80" width="80"/>
                    <span class="wpsr-platform-name wpsr-ml-10">{{ scope.row.reviewer_name }}</span>
                  </div>
                </template>
              </el-table-column>

              <el-table-column :label="$t('Rating')" width="80">
                <template #default="scope">
                  <span class="wpsr-rating">{{ scope.row.rating }}</span>
                </template>
              </el-table-column>

              <el-table-column :label="$t('Create Date')" min-width="120" sortable>
                <template #default="scope">
                  <span class="wpsr-date">{{ wpsrDateFormat(scope.row.review_time, 'DD MMM, YYYY') }}</span>
                </template>
              </el-table-column>

              <el-table-column :label="$t('Status')" width="90">
                <template #default="scope">
                  <el-switch
                      v-model="scope.row.review_approved"
                      active-value="1"
                      inactive-value="0"
                      active-color="#13ce66"
                      inactive-color="#ff4949"
                      @change="(value) => handleSwitchChange(scope.row, value)"
                  >
                  </el-switch>
                </template>
              </el-table-column>

              <el-table-column width="70" align="center">
                <template #default="scope">
                  <el-dropdown  class="wpsr-actions" trigger="click" @command="(command) => handleRowAction(command, scope.row)">
                    <el-button text>
                      <el-icon size="16" color="var(--wpsr-svg-primary-color)"><MoreFilled /></el-icon>
                    </el-button>
                    <template #dropdown>
                      <el-dropdown-menu>
                        <el-dropdown-item command="approve">
                          <el-icon v-if="scope.row.review_approved === '0'"><CircleCheck /></el-icon>
                          <el-icon v-else><CircleClose /></el-icon>
                          <span>
                        {{ scope.row.review_approved === '0' ? $t('Approve') : $t('Disapprove') }}
                        </span>
                        </el-dropdown-item>

                        <el-dropdown-item command="edit">
                          <el-icon><Edit /></el-icon> Edit
                        </el-dropdown-item>
                        <el-dropdown-item command="duplicate">
                          <el-icon><DocumentCopy /></el-icon> Duplicate
                        </el-dropdown-item>
                        <el-dropdown-item command="delete">
                          <el-icon><Delete /></el-icon> Delete
                        </el-dropdown-item>
                      </el-dropdown-menu>
                    </template>
                  </el-dropdown>
                </template>
              </el-table-column>

            </el-table>
            <!-- Pagination -->
            <div class="wpsr-table-pagination">
              <div class="wpsr-pagination-info">
                  <span class="wpsr-pagination-text">
                    Page {{ page_number }} of {{ Math.ceil(total / per_page) }}
                  </span>
              </div>
              <el-pagination
                  background
                  @size-change="handleSizeChange"
                  @current-change="handleCurrentChange"
                  :current-page="page_number"
                  :page-size="per_page"
                  :page-sizes="pageSizes"
                  layout="sizes, prev, pager, next"
                  :total="total">
              </el-pagination>
            </div>
          </div>
        </div>
        <!-- Empty State -->
        <div v-else-if="!loading && !total">
            <div class="wpsr-empty-state">
              <div class="wpsr-empty-content">
              <span class="wpsr-empty-state-icon-zero-width wpsr-mb-20">
                <ReviewsEmptyStates/>
              </span>
                <h1>{{ $t('No Reviews in This Source') }}</h1>
                <p>
                  {{ $t('Currently there is no custom reviews, You can add custom reviews from the below button.') }}
                </p>
                <div class="wpsr-no-sources-actions wpsr-d-flex">
                  <router-link to="/tools/import" class="el-button wpsr_default_btn wpsr_default_btn_primary">
                    <el-icon class="wpsr-mr-5"><Upload /></el-icon>
                    Import
                  </router-link>
                  <el-button size="small" type="primary" class="wpsr_primary_btn" @click.prevent="beforeAddHandler">
                    <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Add Custom Review') }} {{ !has_pro ? $t('(Pro)') : ''}}
                  </el-button>
                </div>
              </div>
            </div>
          </div>
      </div>

      <!-- show edit review modal -->
      <el-dialog
          title="Edit Review"
          v-model="showEditReviewModal"
          width="50%"
          class="wpsr-connection-modal wpsr-form-modal"
      >
        <template #header>
          <div class="wpsr-connection-modal-header">
            <h4 class="wpsr-dialog-title">Edit Review</h4>
          </div>
        </template>
        <review-form
            v-if="showEditReviewModal"
            :type="'review'"
            :review="editRow"
            :action="'edit'"
            @hideFormAfterSuccess="hideFormAfterSuccess"
            @hideFormModal="hideFormModal"
        ></review-form>
      </el-dialog>

      <!-- Add new review modal -->
      <el-dialog
          title="Add Custom Review"
          v-model="showAddReviewDialog"
          width="50%"
          class="wpsr-connection-modal wpsr-form-modal"
      >
        <template #header>
          <div class="wpsr-connection-modal-header">
            <h4 class="wpsr-dialog-title">Add Custom Review</h4>
          </div>
        </template>
        <review-form
            v-if="showAddReviewDialog"
            :type="'review'"
            :review="review_fields"
            :action="'add'"
            @hideFormAfterSuccess="hideFormAfterSuccess"
            @hideFormModal="hideFormModal"
        ></review-form>
      </el-dialog>

      <!--Unified Delete Confirmation Modal-->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_delete_modal"
          width="40%"
          :before-close="handleDeleteClose">
        <div class="wpsr-delete-content">
          <h4 v-if="isBulkDelete">Are you sure, you want to delete {{ selectedItems.length }} review(s)?</h4>
          <h4 v-else>Are you sure, you want to delete this review?</h4>
          <p>All the data associated with {{ isBulkDelete ? 'these reviews' : 'this review' }} will be deleted.</p>

          <div class="wpsr-delete-details">
            <template v-if="isBulkDelete">
              <p><strong>Selected Reviews:</strong> {{ selectedItems.length }} item(s)</p>
              <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.id).join(', ') }}</p>
            </template>
            <template v-else>
              <p><strong>Template ID:</strong> #{{ deletingItem.id }}</p>
              <p><strong>Template Title:</strong> {{ deletingItem.review_title }}</p>
            </template>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_delete_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="danger" @click="confirmDelete()">Confirm</el-button>
          </div>
        </template>
      </el-dialog>

      <!--Status Update Confirmation Modal-->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_status_modal"
          width="40%">
        <div class="wpsr-delete-content">
          <h4>Are you sure, you want to {{ bulkAction }} {{ selectedItems.length }} review(s)?</h4>
          <p v-if="bulkAction === 'enable'">This will activate the selected reviews.</p>
          <p v-else-if="bulkAction === 'disable'">This will deactivate the selected reviews.</p>

          <div class="wpsr-delete-details">
            <p><strong>Selected Reviews:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.id).join(', ') }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_status_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" @click="performStatusUpdate()">Confirm</el-button>
          </div>
        </template>
      </el-dialog>

      <!--Duplicate form Confirmation Modal-->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_duplicate_modal"
          width="40%">
        <div class="wpsr-delete-content">
          <h4>Are you sure, you want to duplicate {{ selectedItems.length }} review(s)?</h4>
          <p>This will create copies of the selected reviews.</p>

          <div class="wpsr-delete-details">
            <p><strong>Selected Reviews:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.id).join(', ') }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_duplicate_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="primary" @click="performBulkDuplicate()">Confirm</el-button>
          </div>
        </template>
      </el-dialog>
    </div>
  </div>

  <div class="wpsr-custom-source-general-settings">
    <div class="wpsr-custom-source-general-settings-content">
        <!-- General Settings Dialog -->
        <el-dialog
          title="General Settings"
          v-model="showGeneralSettingsDialog"
          width="500px"
          class="wpsr-connection-modal wpsr-form-modal"
        >
          <template #header>
            <div class="wpsr-connection-modal-header">
              <h4 class="wpsr-dialog-title">General Settings</h4>
            </div>
          </template>
          <div class="wpsr-connection-modal-body">
            <div class="wpsr-modal-form-fields">

              <div class="wpsr-d-flex">
                <!-- Source Logo -->
                <el-form-item>
                  <template #label>
                    <h3 class="wpsr-connection-modal-input-heading">Source Logo</h3>
                  </template>
                  <div class="wpsr-logo-upload">
                    <photo-uploader enable_clear="yes" design_mode="horizontal" v-model="settingsForm.logo"/>
                  </div>
                </el-form-item>
              </div>

              <div class="wpsr-d-flex">
                <!-- Source Name -->
                <el-form-item>
                  <template #label>
                    <h3 class="wpsr-connection-modal-input-heading">Source Label</h3>
                  </template>
                  <el-input
                      class="wpsr-input-default wpsr-border-all-around"
                      v-model="settingsForm.source_label"
                      placeholder="WordPress"
                      >
                  </el-input>
                </el-form-item>
              </div>

              <div class="wpsr-d-flex">
                <!-- Source link URL -->
                <el-form-item>
                  <template #label>
                    <h3 class="wpsr-connection-modal-input-heading">Source link URL</h3>
                  </template>
                  <el-input
                      class="wpsr-input-default wpsr-border-all-around"
                      v-model="settingsForm.source_url"
                      placeholder="http://www.example.com"
                  >
                  </el-input>
                </el-form-item>
              </div>

<!--              <div class="wpsr-d-flex">-->
<!--                &lt;!&ndash; Source Privacy policy link &ndash;&gt;-->
<!--                <el-form-item>-->
<!--                  <template #label>-->
<!--                    <h3 class="wpsr-connection-modal-input-heading">Privacy policy link</h3>-->
<!--                  </template>-->
<!--                  <el-input-->
<!--                      class="wpsr-input-default wpsr-border-all-around"-->
<!--                      v-model="settingsForm.privacy_policy_url"-->
<!--                      placeholder="http://www.example.com/privacy-policy"-->
<!--                  >-->
<!--                  </el-input>-->
<!--                </el-form-item>-->
<!--              </div>-->

            </div>
            <div class="wpsr-connection-modal-footer wpsr-d-flex wpsr-jc-end wpsr-border-top">
              <el-button @click="showGeneralSettingsDialog = false" class="wpsr_default_btn">
                Cancel
              </el-button>
              <el-button
                  type="primary"
                  @click="saveGeneralSettings"
                  :loading="savingSettings"
                  class="wpsr_primary_btn"
              >
                Save Settings
              </el-button>
            </div>
          </div>
        </el-dialog>
    </div>
  </div>
</template>

<script>
import GlobalMainNav from '../../global/Index.vue';
import {
  Upload, Download, Setting, Plus, ArrowDown, Search, MoreFilled, Delete, CircleCheck, CircleClose, DocumentCopy
} from "@element-plus/icons-vue";
import ReviewForm from "../../views/ReviewForm.vue";
import {tableMixin} from "../../../mixins/tableMixin";
import ReviewContentModal from "../../pieces/ReviewContentModal.vue";
import PhotoUploader from "../../core-ui/editor/PhotoUploader.vue";
import ReviewsEmptyStates from "../../pieces/icons/ReviewsEmptyStates.vue";
import {PlatformIconMixin} from "../../../mixins/PlatformIconMixin";

export default {
  name: 'CustomSourceView',
  mixins: [tableMixin, PlatformIconMixin],
  components: {
    ReviewsEmptyStates,
    PhotoUploader,
    ReviewContentModal,
    ReviewForm,
    GlobalMainNav,
    Upload, Download, Setting, Plus, ArrowDown, Search, MoreFilled, Delete, CircleCheck, CircleClose, DocumentCopy
  },
  data() {
    return {
      editRow: false,
      sourceData: {},
      reviews: [],
      loadingReviews: false,
      searchQuery: '',
      currentPage: 1,
      pageSize: 8,
      totalReviews: 0,
      showAddReviewDialog: false,
      showEditReviewModal: false,
      saving: false,
      reviewForm: {
        reviewer_name: '',
        reviewer_url: '',
        review_title: '',
        reviewer_text: '',
        rating: 0,
        platform_name: '',
        reviewer_img: '',
        review_time: ''
      },
      //for empty fields after two consecutive add
      review_fields: {},
      showGeneralSettingsDialog: false,
      savingSettings: false,
      settingsForm: {
        logo: '',
        source_name: '',
        source_type: '',
        source_label: '',
        source_url: '',
        form_id: null,
        privacy_policy_url: ''
      },
      filter_value: '',
      selectedItems: [],
      bulkAction: '',
      isBulkDelete: false,
      show_delete_modal: false,
      deletingItem: {},
      show_status_modal: false,
      isBulkStatus: false,
      show_duplicate_modal: false,
      isBulkDuplicate: false,
    };
  },
  computed: {
  },
  mounted() {
    //this.loadReviews();
    this.loadSourceData();
  },
  methods: {
    generateQRcode(){
      const url = `${this.appVars.admin_url}?page=wpsocialninja.php#/settings/get-reviews`;
      window.open(url, '_blank');
    },
    handleCurrentChange(val) {
      this.page_number = val;
      this.$router.push({
        path: this.$route.params.template_id,
        query: {
          page: val,
          per_page: this.per_page
        }
      }).catch(() => {});
      this.getItems();
    },
    addNewItem() {
      let platform = this.settingsForm.source_name === 'fluent_forms' ? 'fluent_forms' : this.settingsForm.source_name;
      let payload = { platform: platform };
      // Check if this is a Fluent Forms source
      if (this.settingsForm.source_name === 'fluent_forms' || this.filter_value === 'fluent_forms') {
        payload.form_id = this.settingsForm.form_id;
      }

      this.$post('templates', payload).then(response => {
        if ( response ) {
          if (response.template_id) {
            let routeParams = {
              name: 'edit-template',
              params: {
                template_id: response.template_id,
              }
            };

            this.$router.push(routeParams);
          }
        }
      }).catch(errors => {
        this.handleError(errors);
      }).always(() => {

      });
    },
    loadSourceData() {
      const sourceId = this.$route.params.template_id;
      if (sourceId) {
        this.loading = true;
        this.$get(`pro/custom-sources/${sourceId}`)
            .then(response => {
              this.sourceData = response.source;
              this.settingsForm.source_type = response.settings.source_settings.type;
              this.settingsForm.form_id = response.settings.source_settings.form_id || null;
              this.settingsForm.source_name = response.settings.source_settings.name || '';
              this.settingsForm.source_label = response.settings.source_settings.label || '';
              let ID = this.settingsForm.form_id || sourceId;

              if (response.business_info && response.business_info[ID]) {
                const businessData = response.business_info[ID];
                this.settingsForm = {
                  logo: businessData.logo || '',
                  source_name: businessData.platform_name || response.settings.source_settings.name,
                  source_label: businessData.platform_label || response.settings.source_settings.label,
                  source_url: businessData.url || '',
                  privacy_policy_url: businessData.privacy_policy_url || '',
                  form_id: this.settingsForm.form_id || null
                };
              }

              if(this.settingsForm.form_id !== null){
                this.getAll('reviews', { order_by: 'review_time', type: 'custom_review', sourceId: this.settingsForm.form_id});
              } else {
                this.getAll('reviews', { order_by: 'review_time', type: 'custom_review', sourceId: sourceId});
              }

            })
            .catch(error => {
              this.handleError(error);
              this.loading = false;
            });
      }
    },
    hideFormModal() {
      this.showAddReviewDialog = false;
      this.showEditReviewModal = false;
    },
    beforeEditHandler(row) {
      this.editRow = row;
      this.editRow.platform_name = this.settingsForm.source_name;
      if(this.settingsForm.form_id){
        this.editRow.source_id = this.settingsForm.form_id;
      } else {
        this.editRow.source_id = this.$route.params.template_id ? this.$route.params.template_id : null;
      }

      if (!this.has_pro) {
        this.handlePro();
        this.showEditReviewModal = false;
      } else {
        this.showEditReviewModal = true;
      }
    },
    hideFormAfterSuccess(val) {
      if (val) {
        this.hideFormModal();
        this.getItems();
        //this.loadReviews();
      }
    },
    beforeAddHandler() {
      this.showAddReviewDialog = true;
      //for empty fields after two consecutive add
      this.reviewForm.platform_name = this.settingsForm.source_name;

      if(this.settingsForm.form_id){
        this.reviewForm.source_id = this.settingsForm.form_id;
      } else {
        this.reviewForm.source_id = this.$route.params.template_id ? this.$route.params.template_id : null;
      }

      let fields = JSON.parse(JSON.stringify(this.reviewForm));
      this.review_fields = fields;
      this.errors = [];
    },
    handleSelectionChange(selection) {
      this.selectedItems = selection;
    },
    saveGeneralSettings() {
      this.savingSettings = true;
      const sourceId = this.$route.params.template_id;

      this.$post(`pro/custom-sources/${sourceId}/settings`, {
        'settings': this.settingsForm
      })
        .then(response => {
          this.$message.success('Settings saved successfully');
          this.showGeneralSettingsDialog = false;
          // Update source data
          this.sourceData = { ...this.sourceData, ...this.settingsForm };
        })
        .catch(error => {
          this.handleError(error);
        })
        .always(() => {
          this.savingSettings = false;
        });
    },
    handleRowAction(command, row) {
      switch(command) {
        case 'approve':
          // use updateItemStatus with proper toggle logic
          const newStatus = row.review_approved === '0' ? 'enable' : 'disable';
          this.updateItemStatus(row, newStatus);
          break;
        case 'edit':
          this.beforeEditHandler(row);
          break;
        case 'duplicate':
          this.duplicateItem(row);
          break;
        case 'delete':
          this.beforeDeleteHandler(row);
          break;
      }
    },
    handleSwitchChange(row, newValue) {
      // When newValue is '1', we want to enable (approve)
      // When newValue is '0', we want to disable (disapprove)
      const newStatus = newValue === '1' ? 'enable' : 'disable';

      // Update the row value first
      row.review_approved = newValue;
      this.updateItemStatus(row, newStatus);
    },
    applyBulkAction() {
      if (!this.selectedItems || !this.selectedItems.length) {
        this.$notify.warning('Please select items to perform bulk action');
        return;
      }

      if (!this.bulkAction) {
        this.$notify.warning('Please select a bulk action');
        return;
      }

      switch(this.bulkAction) {
        case 'delete':
          this.beforeBulkDeleteHandler();
          break;
        case 'duplicate':
          this.beforeBulkDuplicateHandler();
          break;
        case 'enable':
        case 'disable':
          this.bulkStatusItems();
          break;
        default:
          this.$notify.warning('Selected action is not supported yet');
      }
    },
    beforeBulkDeleteHandler() {
      if (!this.has_pro) {
        this.handlePro();
        return;
      }
      
      this.isBulkDelete = true;
      this.show_delete_modal = true;
    },
    beforeBulkDuplicateHandler() {
      if (!this.has_pro) {
        this.handlePro();
        return;
      }
      
      this.isBulkDuplicate = true;
      this.show_duplicate_modal = true;
    },
    bulkStatusItems() {
      if (!this.selectedItems || !this.selectedItems.length) {
        this.$notify.warning('No items selected for status update');
        return;
      }

      this.isBulkStatus = true;
      this.show_status_modal = true;
    },
    beforeDeleteHandler(row) {
      this.deletingItem = row;
      this.isBulkDelete = false;
      this.show_delete_modal = true;
    },
    handleDeleteClose() {
      this.show_delete_modal = false;
      this.deletingItem = {};
      this.isBulkDelete = false;
    },
    confirmDelete() {
      if (this.isBulkDelete) {
        this.performBulkDelete();
      } else {
        this.deleteItems();
      }
    },
    exportCustomReviews() {
      if (!this.has_pro) {
        this.handlePro();
        return;
      }

      // Build the export URL with dynamic type
      let url = `${ajaxurl}?action=wpsr_export_data&type=${this.settingsForm.source_name}`;

      // Add source ID filter if available
      const sourceId = this.settingsForm.form_id || this.$route.params.template_id;
      if (sourceId) {
        url += `&sourceId=${sourceId}`;
      }
      location.href = url;
    },
  },
};
</script>
