<template>
  <div class="wpsr-page-container wpsr-all-templates-page">
  <div v-if="hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])">
    <!-- Loading Skeleton -->
    <div v-if="loading" class="wpsr-page-section">
      <div class="wpsr-table-wrapper">
        <WpsrSkeleton :rows="15" />
      </div>
    </div>

    <!-- Table Content -->
    <div v-else-if="total_items" class="wpsr-page-section">
      <!-- Header Section -->
      <div class="wpsr-table-header">
        <div class="wpsr-header-left">
          <h1 class="wpsr-page-title">{{ $t('All Reviews') }}</h1>
          <p class="wpsr-page-subtitle">{{ $t('Your all reviews are here') }}</p>
        </div>
        <div class="wpsr-header-right">
          <el-button class="wpsr_primary_btn" size="small" type="primary" @click.prevent="addSources">
            <el-icon v-if="has_pro" class="wpsr-mr-5"><Plus /></el-icon>
            <ProCrownIcon v-if="!has_pro" class="wpsr-mr-5"/>
            {{ $t('Add Custom Sources') }}
          </el-button>
        </div>
      </div>
      <!-- Filters and Actions -->
      <div class="wpsr-table-controls wpsr-display-block">
        <div class="wpsr-d-flex wpsr-jc-between">
          <div class="wpsr-controls-left">
            <div class="wpsr-controls-bulk-actions">
              <el-select class="wpsr-select-field-primary" v-model="bulkAction" placeholder="Bulk Action" size="default" style="width: 160px;">
                <el-option label="Bulk Actions" value="">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><Plus /></el-icon>
                    <span>Bulk Actions</span>
                  </span>
                </el-option>
                <el-option label="Enable" value="enable">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><View /></el-icon>
                    <span>Enable</span>
                  </span>
                </el-option>
                <el-option label="Disable" value="disable">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><Hide /></el-icon>
                    <span>Disable</span>
                  </span>
                </el-option>
                <el-option label="Duplicate" value="duplicate">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><DocumentCopy /></el-icon>
                    <span>Duplicate</span>
                  </span>
                </el-option>
                <el-option label="Delete" value="delete">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><Delete /></el-icon>
                    <span>Delete</span>
                  </span>
                </el-option>
              </el-select>
              <el-button @click="applyBulkAction" :disabled="!selectedItems.length">Apply</el-button>
            </div>
            <div class="wpsr-controls-filter">
              <el-select class="wpsr-select-field-primary" v-model="filter_value" placeholder="Select Platform" size="small" clearable v-if="Object.entries(validPlatforms).length" @change="getItems" style="width: 220px;">
                <el-option
                    v-for="item in validPlatforms"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                >
                </el-option>
              </el-select>
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

        <div v-if="bulkAction === 'delete'" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-flex-align-center wpsr-mt-15">
          <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
          <p>You can delete reviews from WooCommerce, Fluent Forms, and Custom Sources only. Reviews from other connected platforms are auto-synced, so deleting them will cause them to reappear. If you don’t want to show those reviews, simply disable them instead of deleting.</p>
        </div>
        
      </div>
      <div class="wpsr-table-wrapper" v-loading.fullscreen.lock="duplicatingItem" element-loading-text="Please wait...">
        <el-table
            class="wpsr-templates-table"
            :data="items"
            @selection-change="handleSelectionChange"
            @sort-change="handleTableSort"
        >
          <el-table-column type="selection" width="55" align="center" :selectable="isRowSelectable"></el-table-column>

          <el-table-column :label="$t('ID')" width="70" prop="ID" sortable>
            <template #default="scope">
              <span class="wpsr-item-id">#{{ scope.row.id }}</span>
            </template>
          </el-table-column>

          <el-table-column show-overflow-tooltip :label="$t('Platform')" min-width="130">
            <template #default="scope">
              <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                <div class="wpsr-platform-cell" v-html="getPlatformIconsHtml(scope.row.platform_name)"></div>
                <span class="wpsr-platform-name wpsr-ml-10">{{ validPlatforms.find(item => item.value === scope.row.platform_name)?.label ?? ""}}</span>
              </div>
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
                <img class="wpsr-image-cell" v-if="scope.row.reviewer_img" :src="scope.row.reviewer_img" :height="80" :width="80"/>
                <img class="wpsr-image-cell" v-else :src="assets_url+'/images/template/review-template/placeholder-image.png'"
                     :height="80" :width="80"/>
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
              <el-dropdown v-if="(appVars.platforms_cards && appVars.platforms_cards.every(card => card.platform !== scope.row.platform_name)) || (scope.row.platform_name === 'woocommerce' || scope.row.platform_name === 'custom')" class="wpsr-actions" trigger="click" @command="(command) => handleRowAction(command, scope.row)">
                <el-button text>
                  <el-icon size="16" color="var(--wpsr-svg-primary-color)"><MoreFilled /></el-icon>
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item command="approve">
                      <el-icon v-if="scope.row.review_approved === '0'"><CircleCheck /></el-icon>
                      <el-icon v-else><CircleClose /></el-icon>
                      <span v-if="scope.row.platform_name === 'fluent_forms' || scope.row.platform_name === 'woocommerce' || scope.row.platform_name === 'custom'">
                        {{ scope.row.review_approved === '0' ? $t('Approve') : $t('Disapprove') }}
                      </span>
                      <span v-else>
                        {{ scope.row.review_approved === '0' ? $t('Enable') : $t('Disable') }}
                      </span>
                    </el-dropdown-item>

                    <el-dropdown-item command="edit">
                      <el-icon><Edit /></el-icon> Edit
                    </el-dropdown-item>
                    <el-dropdown-item command="duplicate">
                      <el-icon><DocumentCopy /></el-icon> Duplicate
                    </el-dropdown-item>
                    <el-dropdown-item command="delete" class="wpsr-action-delete">
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

      <!-- show edit review modal -->
      <el-dialog
          title="Edit Review"
          v-model="show_edit_modal"
          width="50%"
          class="wpsr-connection-modal wpsr-form-modal"
      >
        <template #header>
          <div class="wpsr-connection-modal-header">
            <h4 class="wpsr-dialog-title">Edit Review</h4>
          </div>
        </template>
        <review-form
            v-if="show_edit_modal"
            :type="'review'"
            :review="editRow"
            :action="'edit'"
            @hideFormAfterSuccess="hideFormAfterSuccess"
            @hideFormModal="hideFormModal"
        ></review-form>
      </el-dialog>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading" class="wpsr-page-section">
      <div class="wpsr-empty-state">
        <div class="wpsr-empty-content">
          <span class="wpsr-empty-state-icon-zero-width wpsr-mb-20">
            <ReviewsEmptyStates/>
          </span>
          <h1>{{ $t('All Reviews') }}</h1>
          <span class="wpsr-mb-15 wpsr-align-center wpsr-font-size-12">
            {{ $t('No reviews found yet. Please connect your review platforms and fetch reviews') }}
            <router-link to="/">{{$t('Click Here')}}</router-link>. {{ $t('Or ') }} {{ $t('You can add custom reviews manually by selecting the button below.') }}
          </span>

          <el-button class="wpsr_primary_btn" type="primary" size="default" @click.prevent = "addSources">
            <el-icon v-if="has_pro" class="wpsr-mr-5"><Plus /></el-icon>
            <ProCrownIcon v-if="!has_pro" class="wpsr-mr-5"/>
            {{ $t('Add Custom Sources') }}
          </el-button>
        </div>
      </div>
    </div>

    <!-- Add new review modal -->
    <el-dialog
      v-model="show_add_modal"
      width="50%"
      class="wpsr-connection-modal wpsr-form-modal"
    >
      <template v-if="this.has_pro" #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">Add Custom Review</h4>
        </div>
      </template>
      <review-form
          v-if="this.has_pro"
          :type="'review'"
          :review="review_fields"
          :action="'add'"
          @hideFormAfterSuccess="hideFormAfterSuccess"
          @hideFormModal="hideFormModal"
      ></review-form>
      <UpgradeToProModal
          v-else
          :promotionInfo="platform.promotion"
          :platform="'custom'"
          @close="show_add_modal = false"
      />
    </el-dialog>

    <!--Delete form Confirmation Modal-->
    <el-dialog
        class="wpsr-confirmation-modal"
        v-model="show_delete_modal"
        width="40%">
      <div class="wpsr-delete-content">
        <h4 v-if="isBulkDelete">Are you sure, you want to delete {{ selectedItems.length }} review(s)?</h4>
        <h4 v-else>Are you sure, you want to delete this review?</h4>
        <p>All the data associated with {{ isBulkDelete ? 'these reviews' : 'this review' }} will be deleted.</p>

        <div class="wpsr-delete-details" v-if="!isBulkDelete">
          <p><strong>Template ID:</strong> #{{ deletingItem.id }}</p>
          <p><strong>Template Title:</strong> {{ deletingItem.review_title }}</p>
        </div>
        
        <div class="wpsr-delete-details" v-else>
          <p><strong>Selected Reviews:</strong> {{ selectedItems.length }} item(s)</p>
          <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.id).join(', ') }}</p>
        </div>
      </div>
      <template #footer>
        <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
          <el-button class="wpsr_default_btn" @click="show_delete_modal = false">Cancel</el-button>
          <el-button class="wpsr_primary_btn" type="danger" @click="deleteItems()">Confirm</el-button>
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
          <el-button class="wpsr_primary_btn" type="primary" @click="performDuplicate()">Confirm</el-button>
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
  </div>
  <PermissionsNotification v-if="!hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])" />
  </div>
</template>

<script>
import ReviewForm from './ReviewForm.vue';
import {tableMixin} from "../../mixins/tableMixin";
import PermissionsNotification from "./advertise/PermissionsNotification.vue";
import { Search, Edit, Delete, DocumentCopy, MoreFilled, WarningFilled, InfoFilled, CircleCheck, CircleClose } from '@element-plus/icons-vue';
import ReviewsEmptyStates from "../pieces/icons/ReviewsEmptyStates.vue";
import ReviewContentModal from "../pieces/ReviewContentModal.vue";
import UpgradeToProModal from './advertise/UpgradeToProModal.vue';
import ProCrownIcon from "../pieces/icons/ProCrownIcon.vue";
import { PlatformIconMixin } from '../../mixins/PlatformIconMixin';
export default {
  name: 'AllReviews',
  mixins: [tableMixin, PlatformIconMixin],
  data() {
    return {
      actionType: 'review',
      show_add_modal: false,
      show_edit_modal: false,
      editRow: false,
      platform: null,
      fields: {
        reviewer_name: '',
        reviewer_url: '',
        review_title: '',
        reviewer_text: '',
        rating: 0,
        platform_name: 'custom',
        reviewer_img: '',
        review_time: ''
      },
      //for empty fields after two consecutive add
      review_fields: {},
      selectedItems: [],
      bulkAction: '',
      isBulkDelete: false,
      show_duplicate_modal: false,
      isBulkDuplicate: false,
      show_status_modal: false,
      isBulkStatus: false
    }
  },
  components: {
    ProCrownIcon,
    ReviewContentModal,
    ReviewsEmptyStates,
    PermissionsNotification,
    ReviewForm,
    Search,
    Edit,
    Delete,
    DocumentCopy,
    MoreFilled,
    CircleCheck,
    CircleClose,
    UpgradeToProModal
  },
  methods: {
    handleSelectionChange(selection) {
      // Check if any platform reviews are selected
      const platformReviews = selection.filter(item => 
        item.platform_name !== 'custom' && 
        item.platform_name !== 'fluent_forms' && 
        item.platform_name !== 'woocommerce'
      );
      
      if (platformReviews.length > 0) {
        // Show warning and auto-deselect platform reviews
        this.hasPlatformReviewsSelected = true;
        
        // Filter out platform reviews, keep only custom reviews
        this.selectedItems = selection.filter(item => 
          item.platform_name === 'custom' || 
          item.platform_name === 'fluent_forms' || 
          item.platform_name === 'woocommerce'
        );
        
        // Update the table selection to reflect the filtered items
        this.$nextTick(() => {
          this.$refs.reviewsTable?.clearSelection();
          this.$refs.reviewsTable?.toggleRowSelection(this.selectedItems, true);
        });
      } else {
        this.hasPlatformReviewsSelected = false;
        this.selectedItems = selection;
      }
    },
    handleRowAction(command, row) {
      switch(command) {
        case 'approve':
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
    beforeDeleteHandler(template) {
      let platforms = ['reviews', 'testimonials'];
      if(!this.has_pro && platforms.includes(this.endpoint)) {
        this.handlePro();
        return;
      }
      this.isBulkDelete = false;
      this.deletingItem = template;
      this.show_delete_modal = true;
    },
    beforeBulkDeleteHandler() {
      if (!this.has_pro) {
        this.handlePro();
        return;
      }
      
      this.isBulkDelete = true;
      this.show_delete_modal = true;
    },
    hideFormModal() {
      this.show_add_modal = false;
      this.show_edit_modal = false;
    },
    hideFormAfterSuccess(val) {
      if (val) {
        this.hideFormModal();
        this.getItems();
      }
    },
    addSources() {
      this.$router.push('/custom-sources?create=true');
    },
    handleSwitchChange(row, newValue) {
      // When newValue is '1', we want to enable (approve)
      // When newValue is '0', we want to disable (disapprove)
      const newStatus = newValue === '1' ? 'enable' : 'disable';

      // Update the row value first
      row.review_approved = newValue;
      this.updateItemStatus(row, newStatus);
    },
    beforeEditHandler(row) {
      this.editRow = row;
      if (!this.has_pro) {
        this.handlePro();
        this.show_edit_modal = false;
      } else {
        this.show_edit_modal = true;
      }
    },
    isRowSelectable(row) {
      if (this.bulkAction === 'delete') {
        return row.platform_name === 'custom' || 
               row.platform_name === 'fluent_forms' || 
               row.platform_name === 'testimonial' ||
               row.platform_name === 'woocommerce';
      }
      return true;
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

      if (this.bulkAction === 'delete') {
        const platformReviews = this.selectedItems.filter(item => 
          item.platform_name !== 'custom' && 
          item.platform_name !== 'fluent_forms' && 
          item.platform_name !== 'woocommerce'
        );

        if (platformReviews.length > 0) {
          this.selectedItems = this.selectedItems.filter(item => 
            item.platform_name === 'custom' || 
            item.platform_name === 'fluent_forms' || 
            item.platform_name !== 'woocommerce'
          );
          
          this.$nextTick(() => {
            this.$refs.reviewsTable?.clearSelection();
            this.$refs.reviewsTable?.toggleRowSelection(this.selectedItems, true);
          });
        }
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
    }
  },
  watch: {
    bulkAction(newAction) {
      if (newAction === 'delete') {
        this.$nextTick(() => {
          const platformReviews = this.selectedItems.filter(item => 
            item.platform_name !== 'custom' && 
            item.platform_name !== 'fluent_forms' && 
            item.platform_name !== 'testimonial'
          );
          
          if (platformReviews.length > 0) {
            this.selectedItems = this.selectedItems.filter(item => 
              item.platform_name === 'custom' || 
              item.platform_name === 'fluent_forms' || 
              item.platform_name === 'testimonial'
            );
            
            this.$nextTick(() => {
              this.$refs.reviewsTable?.clearSelection();
              this.$refs.reviewsTable?.toggleRowSelection(this.selectedItems, true);
            });
          }
        });
      }
    }
  },
  mounted() {
    this.getAll('reviews', { order_by: 'review_time'});
    let platforms = window.WPSocialReviewsAdmin.platforms_cards;
    this.platform = platforms.find(p => p.platform === 'custom');
  }
}
</script>