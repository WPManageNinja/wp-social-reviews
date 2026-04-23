<template>
  <div>
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
          <h1 class="wpsr-page-title">{{ $t('Review Forms') }}</h1>
          <p class="wpsr-page-subtitle">{{ $t('Manage your review collection forms') }}</p>
        </div>
        <div class="wpsr-header-right">
          <el-button class="wpsr_primary_btn" size="small" type="primary" @click="createForm()">
            <el-icon class="wpsr-mr-5"><Plus /></el-icon>
            {{ $t('Create Form') }}
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
              <el-option :label="$t('Activate')" value="activate">
                <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                  <el-icon><CircleCheck /></el-icon>
                  <span>{{$t('Activate')}}</span>
                </span>
              </el-option>
              <el-option :label="$t('Deactivate')" value="deactivate">
                <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                  <el-icon><CircleClose /></el-icon>
                  <span>{{$t('Deactivate')}}</span>
                </span>
              </el-option>
              <el-option :label="$t('Delete')" value="delete">
                <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                  <el-icon><Delete /></el-icon>
                  <span>{{$t('Delete')}}</span>
                </span>
              </el-option>
            </el-select>
            <el-button @click="applyBulkAction" :disabled="!selectedItems.length">{{ $t('Apply') }}</el-button>
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

      <div class="wpsr-table-wrapper">
        <el-table
            class="wpsr-templates-table"
            :data="items"
            @selection-change="handleSelectionChange"
        >
          <el-table-column type="selection" width="50" align="center"></el-table-column>

          <el-table-column :label="$t('ID')" width="70" align="center">
            <template #default="scope">
              <span class="wpsr-item-id">#{{ scope.row.id }}</span>
            </template>
          </el-table-column>

          <el-table-column show-overflow-tooltip :label="$t('Form Title')" min-width="250">
            <template #default="scope">
              <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                <div @click.prevent="editForm(scope.row)" class="wpsr-item-editable wpsr-item-title wpsr-d-flex wpsr-flex-align-center">
                  {{ scope.row.title }}
                  <el-icon class="wpsr-ml-5 wpsr-display-on-hover"><Edit /></el-icon>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column :label="$t('Shortcode')" min-width="280">
            <template #default="scope">
              <div class="wpsr-shortcode-cell">
                <el-tooltip effect="dark" content="Click to copy shortcode" placement="top">
                  <code class="wpsr-shortcode copy"
                        :data-clipboard-text='`[wpsr_review_form id="${scope.row.id}"]`'>
                    <el-icon class="wpsr-copy-icon"><DocumentCopy /></el-icon>
                    [wpsr_review_form id="{{ scope.row.id }}"]
                  </code>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>

          <el-table-column :label="$t('Created')" min-width="140">
            <template #default="scope">
              <span class="wpsr-date">{{ wpsrDateFormat(scope.row.created_at, 'DD MMM, YYYY') }}</span>
            </template>
          </el-table-column>

          <el-table-column :label="$t('Status')" width="90" align="center">
            <template #default="scope">
              <el-switch
                  v-model="scope.row.status"
                  active-value="active"
                  inactive-value="inactive"
                  active-color="#13ce66"
                  inactive-color="#ff4949"
                  @change="(value) => toggleFormStatus(scope.row, value)"
              />
            </template>
          </el-table-column>

          <el-table-column width="70">
            <template #default="scope">
              <el-dropdown class="wpsr-actions" trigger="click" @command="(command) => handleRowAction(command, scope.row)">
                <el-button text>
                  <el-icon size="16" color="var(--wpsr-svg-primary-color)"><MoreFilled /></el-icon>
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item command="edit">
                      <el-icon><Edit /></el-icon> {{ $t('Edit') }}
                    </el-dropdown-item>
                    <el-dropdown-item command="duplicate">
                      <el-icon><CopyDocument /></el-icon> {{ $t('Duplicate') }}
                    </el-dropdown-item>
                    <el-dropdown-item command="submissions">
                      <el-icon><View /></el-icon> {{ $t('View Submissions') }}
                    </el-dropdown-item>
                    <el-dropdown-item command="delete" class="wpsr-action-delete">
                      <el-icon><Delete /></el-icon> {{ $t('Delete') }}
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
    <div v-else-if="!loading && !total_items" class="wpsr-page-section">
      <div class="wpsr-empty-state">
        <div class="wpsr-empty-content">
          <h1>{{ $t('No Review Forms') }}</h1>
          <p>{{ $t('Create a review form to start collecting reviews directly on your website.') }}</p>
          <div>
            <el-button type="primary" @click="createForm()" class="wpsr_primary_btn">
              <el-icon><Plus /></el-icon>
              {{ $t('Create Form') }}
            </el-button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <el-dialog
        class="wpsr-confirmation-modal"
        v-model="show_delete_modal"
        width="40%"
        :before-close="handleDeleteClose">
      <div class="wpsr-delete-content">
        <h4 v-if="isBulkDelete">Are you sure, you want to delete {{ selectedItems.length }} form(s)?</h4>
        <h4 v-else>Are you sure you want to delete this form?</h4>
        <p v-if="isBulkDelete">All the data associated with these forms will be deleted.</p>
        <p v-else>All data associated with this form will be permanently deleted.</p>

        <div class="wpsr-delete-details">
          <template v-if="isBulkDelete">
            <p><strong>Selected Forms:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.id).join(', ') }}</p>
          </template>
          <template v-else>
            <p><strong>Form ID:</strong> #{{ deletingItem.id }}</p>
            <p><strong>Form Title:</strong> {{ deletingItem.title }}</p>
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
  </div>
</template>

<script>
import { tableMixin } from '../../../mixins/tableMixin';
import { Plus, Delete, Edit, Search, MoreFilled, DocumentCopy, CopyDocument, View, CircleCheck, CircleClose } from '@element-plus/icons-vue';

export default {
  name: 'ReviewFormsList',
  mixins: [tableMixin],
  components: {
    Plus,
    Delete,
    Edit,
    Search,
    MoreFilled,
    DocumentCopy,
    CopyDocument,
    View,
    CircleCheck,
    CircleClose
  },
  data() {
    return {
      bulkAction: '',
      selectedItems: [],
      isBulkDelete: false
    }
  },
  mounted() {
    this.getAll('pro/review-forms');
    this.clipboard();
  },
  methods: {
    createForm() {
      this.$post('pro/review-forms', {})
        .then(response => {
          if (response.form && response.form.id) {
            this.$router.push({ name: 'review-form-edit', params: { id: response.form.id } });
          }
        })
        .catch(errors => {
          this.handleError(errors);
        });
    },
    editForm(row) {
      this.$router.push({ name: 'review-form-edit', params: { id: row.id } });
    },
    handleRowAction(command, row) {
      switch (command) {
        case 'edit':
          this.editForm(row);
          break;
        case 'duplicate':
          this.duplicateForm(row);
          break;
        case 'submissions':
          this.$router.push({ name: 'reviews', query: { platform: 'native_form', form_id: row.id } });
          break;
        case 'delete':
          this.isBulkDelete = false;
          this.deletingItem = row;
          this.show_delete_modal = true;
          break;
      }
    },
    duplicateForm(row) {
      this.$post(`pro/review-forms/${row.id}/duplicate`)
        .then(response => {
          this.handleSuccess(response.message);
          this.getItems();
        })
        .catch(errors => {
          this.handleError(errors);
        });
    },
    confirmDelete() {
      this.deleteItems();
    },
    handleSelectionChange(val) {
      this.selectedItems = val;
    },
    applyBulkAction() {
      if (!this.selectedItems.length) {
        this.$message.warning(this.$t('Please select items to perform bulk action'));
        return;
      }
      if (!this.bulkAction) {
        this.$message.warning(this.$t('Please select a bulk action'));
        return;
      }
      if (this.bulkAction === 'delete') {
        this.isBulkDelete = true;
        this.show_delete_modal = true;
      } else if (this.bulkAction === 'activate' || this.bulkAction === 'deactivate') {
        const status = this.bulkAction === 'activate' ? 'enable' : 'disable';
        const ids = this.selectedItems.map(item => item.id);
        this.$put('pro/review-forms/status-update', { ids, status })
          .then(response => {
            this.handleSuccess(response.message);
            this.bulkAction = '';
            this.selectedItems = [];
            this.getItems();
          })
          .catch(errors => {
            this.handleError(errors);
          });
      }
    },
    toggleFormStatus(row, value) {
      const status = value === 'active' ? 'enable' : 'disable';
      this.$put('pro/review-forms/status-update', {
        ids: [row.id],
        status: status
      }).then(response => {
        this.handleSuccess(response.message);
      }).catch(errors => {
        row.status = value === 'active' ? 'inactive' : 'active';
        this.handleError(errors);
      });
    },
    handleClear() {
      this.search_string = '';
      this.getItems();
    }
  }
}
</script>
