<template>
  <div class="wpsr-page-container wpsr-all-templates-page">
  <div v-if="hasPermission(['wpsn_manage_testimonials', 'wpsn_full_access'])">
    <!-- Loading Skeleton -->
    <div v-if="loading" class="wpsr-page-section">
      <div class="wpsr-table-wrapper">
        <WpsrSkeleton :rows="15" />
      </div>
    </div>

    <!-- Table Content -->
    <div v-else-if="total_items && has_pro" class="wpsr-page-section">
      <!-- Header Section -->
      <div class="wpsr-table-header">
        <div class="wpsr-header-left">
          <h1 class="wpsr-page-title">{{ $t('All Testimonials') }}</h1>
          <p class="wpsr-page-subtitle">{{ $t('Your all testimonials are here') }}</p>
        </div>
        <div class="wpsr-header-right">
          <el-button class="wpsr_primary_btn" size="small" type="primary" @click.prevent="beforeAddHandler">
            <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Add Testimonial') }} {{ !has_pro ? $t('(Pro)') : ''}}
          </el-button>
        </div>
      </div>
      <!-- Filters and Actions -->
      <div class="wpsr-table-controls">
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
            class="wpsr-templates-table"
            :data="items"
            @sort-change="handleTableSort"
            @selection-change="handleSelectionChange"
        >
          <el-table-column type="selection" width="55" align="center"></el-table-column>

          <el-table-column :label="$t('ID')" width="70" prop="ID" sortable>
            <template #default="scope">
              <span class="wpsr-item-id">#{{ scope.row.id }}</span>
            </template>
          </el-table-column>

          <el-table-column show-overflow-tooltip :label="$t('Company Details')" min-width="150">
            <template #default="scope">
              <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                <img class="wpsr-image-cell wpsr-mr-10" v-if="scope.row.fields.author_website_logo" :src="scope.row.fields.author_website_logo" height="80" width="80"/>
                <div class="wpsr-item-group wpsr-d-flex wpsr-flex-column">
                  <span class="wpsr-platform-name wpsr-text-ellipsis">{{ scope.row.fields.author_company }}</span>
                  <span class="wpsr-text-content wpsr-text-ellipsis">{{ scope.row.fields.author_website_url}}</span>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column :label="$t('Testimonial')" min-width="200">
            <template #default="scope">
              <strong class="wpsr-item-title">{{ scope.row.review_title }}</strong>
              <ReviewContentModal
                  :content="scope.row.reviewer_text"
                  :title="scope.row.review_title"
                  :rating="scope.row.rating"
                  :reviewerName="scope.row.reviewer_name"
                  :reviewerImg="scope.row.reviewer_img"
                  :date="scope.row.review_time"
              />
            </template>
          </el-table-column>

          <el-table-column show-overflow-tooltip :label="$t('Author')" min-width="150">
            <template #default="scope">
              <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                <img class="wpsr-image-cell wpsr-mr-10" v-if="scope.row.reviewer_img" :src="scope.row.reviewer_img" height="80" width="80"/>
                <img class="wpsr-image-cell wpsr-mr-10" v-else :src="assets_url+'/images/template/review-template/placeholder-image.png'"
                     height="80" width="80"/>
                <div class="wpsr-item-group wpsr-d-flex wpsr-flex-column">
                  <strong class="wpsr-item-title wpsr-text-ellipsis">{{ scope.row.reviewer_name }}</strong>
                  <span class="wpsr-text-content wpsr-text-ellipsis">{{ scope.row.fields.author_position }}</span>
                </div>
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
              <el-dropdown class="wpsr-actions" trigger="click" @command="(command) => handleRowAction(command, scope.row)">
                <el-button text>
                  <el-icon size="16" color="var(--wpsr-svg-primary-color)"><MoreFilled /></el-icon>
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item command="approve" v-if="scope.row.fields && scope.row.fields.is_fluent_forms">
                      <el-icon v-if="scope.row.review_approved === '0'"><CircleCheck /></el-icon>
                      <el-icon v-else><CircleClose /></el-icon>
                      {{ scope.row.review_approved === '0' ? $t('Approve') : $t('Disapprove') }}
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

      <!-- show edit testimonial modal -->
      <el-dialog
          title="Edit Testimonial"
          v-model="show_edit_modal"
          width="50%"
          class="wpsr-connection-modal wpsr-form-modal"
      >
        <review-form
            v-if="show_edit_modal"
            :type="'testimonial'"
            :review="editRow"
            :action="'edit'"
            @hideFormAfterSuccess="hideFormAfterSuccess"
            @hideFormModal="hideFormModal"
        ></review-form>
      </el-dialog>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading && has_pro && !total_items" class="wpsr-page-section">
      <div class="wpsr-empty-state">
        <div class="wpsr-empty-content">
          <span class="wpsr-empty-state-icon">
            <ReviewsEmptyStates/>
          </span>
          <h1>{{$t('No Testimonial Found Yet')}}</h1>
          <p>
            {{ $t('You haven’t added any testimonials. Add one now to start showcasing them in your template.') }}
          </p>
          <el-button class="wpsr_primary_btn" type="primary" size="default" @click.prevent="beforeAddHandler">
            {{ $t('Add a Testimonial') }}
          </el-button>
        </div>
      </div>
    </div>

    <div v-if="!has_pro" class="wpsr-promote-lp wpsr-promote-testimonial-lp">
      <TestimonialLandingPage/>
    </div>

    <!-- Add new testimonial modal -->
    <el-dialog
        title="Add Testimonial"
        v-model="show_add_modal"
        width="50%"
        class="wpsr-connection-modal wpsr-form-modal"
    >
      <template v-if="this.has_pro" #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">Add Testimonial</h4>
        </div>
      </template>
      <review-form
          v-if="show_add_modal"
          :type="'testimonial'"
          :review="testimonial_fields"
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
        <h4 v-if="isBulkDelete">Are you sure, you want to delete {{ selectedItems.length }} testimonial(s)?</h4>
        <h4 v-else>Are you sure, you want to delete this testimonial?</h4>
        <p>All the data associated with {{ isBulkDelete ? 'these testimonials' : 'this testimonial' }} will be deleted.</p>

        <div class="wpsr-delete-details">
          <template v-if="isBulkDelete">
            <p><strong>Selected Testimonials:</strong> {{ selectedItems.length }} item(s)</p>
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
        <h4>Are you sure, you want to {{ bulkAction }} {{ selectedItems.length }} testimonial(s)?</h4>
        <p v-if="bulkAction === 'enable'">This will activate the selected testimonials.</p>
        <p v-else-if="bulkAction === 'disable'">This will deactivate the selected testimonials.</p>

        <div class="wpsr-delete-details">
          <p><strong>Selected Testimonials:</strong> {{ selectedItems.length }} item(s)</p>
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
        <h4>Are you sure, you want to duplicate {{ selectedItems.length }} testimonial(s)?</h4>
        <p>This will create copies of the selected testimonials.</p>

        <div class="wpsr-delete-details">
          <p><strong>Selected Testimonials:</strong> {{ selectedItems.length }} item(s)</p>
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
  </div>
  <PermissionsNotification v-if="!hasPermission(['wpsn_manage_testimonials', 'wpsn_full_access'])" />
  </div>
</template>

<script type="text/babel">
import ReviewForm from './ReviewForm';
import TestimonialLandingPage from './advertise/TestimonialLandingPage';
import {tableMixin} from "../../mixins/tableMixin";
import PermissionsNotification from "./advertise/PermissionsNotification.vue";
import { Search, Edit, Delete, DocumentCopy, MoreFilled, CircleCheck, CircleClose } from '@element-plus/icons-vue';
import ReviewsEmptyStates from "../pieces/icons/ReviewsEmptyStates.vue";
import ReviewContentModal from "../pieces/ReviewContentModal.vue";

export default {
  name: 'AllTestimonials',
  mixins: [tableMixin],
  data() {
    return {
      actionType: 'testimonial',
      show_add_modal: false,
      show_edit_modal: false,
      editRow: false,
      columns: {
        reviewer_name: '',
        reviewer_url: '',
        review_title: '',
        reviewer_text: '',
        rating: 0,
        platform_name: 'testimonial',
        reviewer_img: '',
        review_time: '',
        fields: {
          author_position: '',
          author_company: '',
          author_website_logo: '',
          author_website_url: ''
        }
      },
      //for empty fields after two consecutive add
      testimonial_fields: {},
      selectedItems: [],
      bulkAction: '',
      show_status_modal: false,
      isBulkStatus: false,
      show_delete_modal: false,
      isBulkDelete: false,
      deletingItem: {},
      duplicatingItem: false,
      show_duplicate_modal: false,
      isBulkDuplicate: false
    }
  },
  components: {
    ReviewContentModal,
    ReviewsEmptyStates,
    PermissionsNotification,
    TestimonialLandingPage,
    ReviewForm,
    Search,
    Edit,
    Delete,
    DocumentCopy,
    MoreFilled,
    CircleCheck,
    CircleClose
  },
  methods: {
    handleSwitchChange(row, newValue) {
      // When newValue is '1', we want to enable (approve)
      // When newValue is '0', we want to disable (disapprove)
      const newStatus = newValue === '1' ? 'enable' : 'disable';

      // Update the row value first
      row.review_approved = newValue;
      this.updateItemStatus(row, newStatus);
    },
    handleSelectionChange(selection) {
      this.selectedItems = selection;
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

    beforeAddHandler() {
      if (!this.has_pro) {
        this.handlePro();
        this.show_add_modal = false;
      } else {
        this.show_add_modal = true;
      }
      //for empty fields after two consecutive add
      let fields = JSON.parse(JSON.stringify(this.columns));
      this.testimonial_fields = fields;
      this.errors = [];
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
  },
  mounted() {
    this.getAll('testimonials');
  }
}
</script>