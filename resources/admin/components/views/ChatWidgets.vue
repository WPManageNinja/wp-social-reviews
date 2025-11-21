<template>
  <div class="wpsr-page-container wpsr-all-templates-page">
    <div v-if="hasPermission(['wpsn_manage_chat_widgets', 'wpsn_full_access'])">
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
            <h1 class="wpsr-page-title">{{ $t('All Chat Widgets') }}</h1>
            <p class="wpsr-page-subtitle">{{ $t('Your all chat widgets are here') }}</p>
          </div>

          <div class="wpsr-header-right">
            <el-button class="wpsr_primary_btn" type="primary" @click="addNewItem('chat')">
              <el-icon class="wpsr-mr-5"><Plus /></el-icon>
              {{ $t('Add New Widget') }}
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
                <el-option label="Enable" value="publish">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><View /></el-icon>
                    <span>Enable</span>
                  </span>
                </el-option>
                <el-option label="Disable" value="draft">
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
                @keyup.enter.native="getItems"
                @clear="handleClear"
                clearable>
              <template #prefix>
                <el-icon class="el-input__icon" @click="getItems" slot="append"><Search /></el-icon>
              </template>
            </el-input>
          </div>
        </div>

        <!-- Templates Table -->
        <div class="wpsr-table-wrapper">
          <el-table
              class="wpsr-templates-table"
              :data="items"
              @selection-change="handleSelectionChange"
              @sort-change="handleTableSort"
              v-loading="duplicatingItem"
              element-loading-text="Duplicating the template.. Please wait..."
          >
            <el-table-column type="selection" width="55" align="center"></el-table-column>

            <el-table-column label="ID" width="70" prop="ID" sortable>
              <template #default="scope">
                <span class="wpsr-item-id wpsr-item-editable" @click.prevent="handleRowAction('edit', scope.row)">#{{ scope.row.ID }}</span>
              </template>
            </el-table-column>

            <el-table-column
                label="Title"
                min-width="180"
                show-overflow-tooltip
            >
              <template #default="scope" >
                <div class="wpsr-item-title-cell wpsr-item-editable" @click.prevent="handleRowAction('edit', scope.row)">
                  <strong class="wpsr-item-title wpsr-d-flex wpsr-flex-align-center">
                    {{ $trimWords(scope.row.post_title, 3, true) }}
                    <el-icon class="wpsr-ml-5 wpsr-display-on-hover"><Edit /></el-icon>
                  </strong>
                </div>
              </template>
            </el-table-column>

            <el-table-column :label="$t('Priority')" width="80">
              <template #default="scope">
                {{scope.row.menu_order}}
              </template>
            </el-table-column>

            <el-table-column label="Create Date" min-width="140">
              <template #default="scope">
                <span class="wpsr-date">{{ wpsrDateFormat(scope.row.post_date_gmt, 'DD MMM, YYYY') }}</span>
              </template>
            </el-table-column>

            <el-table-column :label="$t('Status')" prop="status" width="90" sortable>
              <template #default="scope">
                <el-switch
                    v-model="scope.row.post_status"
                    active-value="publish"
                    inactive-value="draft"
                    active-color="#13ce66"
                    inactive-color="#ff4949"
                    @change="widgetStatus(scope.row)"
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


        <div v-if="total_items" class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mt-20">
          <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
          <p>
            <strong>Note:</strong>
            One chat widget will be shown at a time based on the priority and condition match.
          </p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading" class="wpsr-page-section">
        <div class="wpsr-empty-state">
          <div class="wpsr-empty-content">
        <span class="wpsr-empty-state-icon"><CreateTemplateIcon/></span>
        <h1>{{ $t('Chat Widgets') }}</h1>
        <p>
          {{ $t('Build chat widgets for your whole site or specific pages. Add as many as you need.') }}
        </p>
        <el-button class="wpsr_primary_btn" type="primary" size="default" @click.prevent="addNewItem('chat')">
          <el-icon class="wpsr-mr-5"><Plus /></el-icon>
          {{ $t('Create Chat Widget') }}
        </el-button>
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
          <h4>Are you sure, you want to delete this Widget?</h4>
          <p>All the data associate with this widget will be deleted.</p>

          <div class="wpsr-delete-details">
            <p><strong>Widget ID:</strong> #{{ deletingItem.ID }}</p>
            <p><strong>Widget Title:</strong> {{ deletingItem.post_title }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_delete_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="danger" @click="deleteItems()">Delete Template</el-button>
          </div>
        </template>
      </el-dialog>

      <!--Bulk Delete Confirmation Modal-->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_bulk_delete_modal"
          width="40%">
        <div class="wpsr-delete-content">
          <h4>Are you sure, you want to delete {{ selectedItems.length }} widget(s)?</h4>
          <p>All the data associated with these widgets will be deleted.</p>

          <div class="wpsr-delete-details">
            <p><strong>Selected Widgets:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_bulk_delete_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="danger" @click="performBulkDelete()">Confirm</el-button>
          </div>
        </template>
      </el-dialog>

      <!--Duplicate form Confirmation Modal-->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_duplicate_modal"
          width="40%">
        <div class="wpsr-delete-content">
          <h4>Are you sure, you want to duplicate {{ selectedItems.length }} widget(s)?</h4>
          <p>This will create copies of the selected widgets.</p>

          <div class="wpsr-delete-details">
            <p><strong>Selected Widgets:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_duplicate_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="primary" @click="performDuplicate()">Confirm</el-button>
          </div>
        </template>
      </el-dialog>

      <!--Bulk Status Update Confirmation Modal-->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_status_modal"
          width="40%">
        <div class="wpsr-delete-content">
          <h4>Are you sure, you want to {{ bulkAction === 'publish' ? 'enable' : 'disable' }} {{ selectedItems.length }} widget(s)?</h4>
          <p>This will {{ bulkAction }} the selected widgets.</p>

          <div class="wpsr-delete-details">
            <p><strong>Selected Widgets:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_status_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="primary" @click="performStatusUpdate()">Confirm</el-button>
          </div>
        </template>
      </el-dialog>
    </div>
    <PermissionsNotification v-if="!hasPermission(['wpsn_manage_chat_widgets', 'wpsn_full_access'])" />
  </div>
</template>
<script type="text/babel">
import {tableMixin} from "../../mixins/tableMixin";
import PermissionsNotification from "./advertise/PermissionsNotification.vue";
import {Delete, DocumentCopy, Edit, MoreFilled, Search, CircleCheck, CircleClose} from "@element-plus/icons-vue";
import InfoIcon from "../pieces/icons/InfoIcon.vue";
import CreateTemplateIcon from "../pieces/icons/CreateTemplateIcon.vue";

export default {
    name: 'ChatWidgets',
    components: {
      Search,
      Edit,
      Delete,
      DocumentCopy,
      MoreFilled,
      InfoIcon,
      CreateTemplateIcon,
      PermissionsNotification,
      CircleCheck,
      CircleClose
    },
    mixins: [tableMixin],
    data(){
      return{
        selectedItems: [],
        bulkAction: '',
        show_delete_modal: false,
        show_duplicate_modal: false,
        show_bulk_delete_modal: false,
        show_bulk_duplicate_modal: false,
        show_status_modal: false,
        isBulkDelete: false,
        isBulkDuplicate: false,
        isBulkStatus: false,
        deletingItem: {}
      }
    },
    methods: {
        handleSelectionChange(selection) {
          this.selectedItems = selection;
        },
        handleRowAction(command, row) {
          switch(command) {
            case 'edit':
              this.$router.push({ name: 'edit-chat-widget', params: {widget_id: row.ID} })
              break;
            case 'duplicate':
              this.duplicateItem(row);
              break;
            case 'delete':
              this.beforeDeleteHandler(row);
              break;
          }
        },
        widgetStatus(widget) {
          this.selectedItems = [widget];
          this.bulkAction = widget.post_status;
          this.performStatusUpdate();
        },
        beforeDeleteHandler(widget) {
          this.deletingItem = widget;
          this.selectedItems = [widget]; // Set selectedItems for single delete
          this.isBulkDelete = false;
          this.show_delete_modal = true;
        },
        beforeBulkDeleteHandler() {
          this.isBulkDelete = true;
          this.show_bulk_delete_modal = true;
        },
        beforeBulkDuplicateHandler() {
          this.isBulkDuplicate = true;
          this.show_duplicate_modal = true;
        },
        handleDeleteClose() {
          this.show_delete_modal = false;
          this.deletingItem = {};
          this.selectedItems = []; // Clear selectedItems when closing modal
        },
        applyBulkAction() {
          if (!this.selectedItems || !this.selectedItems.length) {
            this.$message.warning('Please select items to perform bulk action');
            return;
          }

          if (!this.bulkAction) {
            this.$message.warning('Please select a bulk action');
            return;
          }

          switch(this.bulkAction) {
            case 'delete':
              this.beforeBulkDeleteHandler();
              break;
            case 'duplicate':
              this.beforeBulkDuplicateHandler();
              break;
            case 'publish':
            case 'draft':
              this.bulkStatusItems();
              break;
            default:
              this.$message.warning('Selected action is not supported yet');
          }
        },
        bulkStatusItems() {
          if (!this.selectedItems || !this.selectedItems.length) {
            this.$message.warning('Please select items to perform bulk action');
            return;
          }

          this.isBulkStatus = true;
          this.show_status_modal = true;
        },
        performStatusUpdate() {
          const selectedIds = this.selectedItems.map(item => item.ID);
          let status = this.bulkAction; // 'publish', 'draft'
          
          this.loading = true;
          
          this.$put(`${this.endpoint}`, {
            ids: selectedIds,
            status: status
          }).then(response => {
            if (response) {
              const actionText = status === 'publish' ? 'enabled' : 'disabled';
              this.handleSuccess(response.message || `Successfully ${actionText} ${selectedIds.length} widget(s)`);
              this.getItems();
              this.selectedItems = [];
              this.bulkAction = '';
            }
          }).catch(errors => {
            this.handleError(errors);
          }).always(() => {
            this.loading = false;
            this.show_status_modal = false;
            this.isBulkStatus = false;
          });
        },
    },
    mounted() {
        this.getAll('chat-widgets');
    }
}
</script>