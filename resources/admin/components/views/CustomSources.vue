<template>
  <div class="wpsr-page-container wpsr-all-templates-page">
    <div v-if="!has_pro" class="wpsr-managers-upgrade-to-pro" style="height: 100vh;">
      <img :src="assets_url+'/images/promotion/custom-sources.png'" alt="">
      <div class="wpsr-managers-upgrade-message">
        <h2>Add Reviews from Any Platform You Want</h2>
        <p>Build unlimited custom review feeds with your branding & ratings. Show them exactly how you want.</p>
        <div>
          <UpgradeToProButton/>
        </div>
      </div>
    </div>

    <div v-if="has_pro && hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])">
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
            <h1 class="wpsr-page-title">{{ $t('All Custom Sources') }}</h1>
            <p class="wpsr-page-subtitle">{{ $t('Manage all your custom sources from here') }}</p>
          </div>
          <div class="wpsr-header-right">
            <el-button class="wpsr_primary_btn" size="small" type="primary" @click="showAddSourceDialog = true">
              <el-icon class="wpsr-mr-5"><Plus /></el-icon>
              {{ $t('Add Custom Source') }} {{ !has_pro ? $t('(Pro)') : ''}}
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
              <el-select class="wpsr-select-field-primary" v-model="filter_value" placeholder="All Sources" size="default" clearable
                         v-if="Object.entries(validPlatforms).length" @change="getItems" style="width: 220px;">
                <el-option
                    v-for="item in validPlatforms"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value">
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

        <div class="wpsr-table-wrapper">
          <el-table
              class="wpsr-templates-table"
              :data="items"
              @selection-change="handleSelectionChange"
          >
            <el-table-column type="selection" width="50" align="center"></el-table-column>
            <el-table-column show-overflow-tooltip :label="$t('Sources')" min-width="200">
              <template #default="scope">
                <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                  <div @click.prevent="handleRowAction('edit', scope.row)" class="wpsr-item-editable wpsr-platform-cell wpsr-d-flex wpsr-flex-align-center">
                    <img v-if="scope.row.logo" class="wpsr-cell-logo" :src="scope.row.logo" alt="">
                    <div v-if="scope.row.title" @click.prevent="handleRowAction('edit', scope.row)" :class="scope.row.logo ? 'wpsr-ml-10' : ''" class="wpsr-item-editable wpsr-cell-logo-secondary wpsr-item-title wpsr-d-flex wpsr-flex-align-center">
                      {{ $trimWords(scope.row.title, 3, true) }}
                      <el-icon class="wpsr-ml-5 wpsr-display-on-hover"><Edit /></el-icon>
                    </div>
                    <div v-else :class="scope.row.logo ? 'wpsr-ml-10' : ''" class="wpsr-cell-logo-secondary" v-html="getPlatformIconsHtml(scope.row.name)"></div>
                  </div>

                </div>
              </template>
            </el-table-column>

            <el-table-column show-overflow-tooltip :label="$t('Source ID')" width="100">
              <template #default="scope">
                <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                  <strong class="wpsr-platform-name">{{ scope.row.type === 'fluent_forms' ? scope.row.form_id : scope.row.id }}</strong>
                </div>
              </template>
            </el-table-column>

            <el-table-column show-overflow-tooltip :label="$t('Source Type')" min-width="150">
              <template #default="scope">
                <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                  <strong class="wpsr-platform-name">{{ ucFirst(scope.row.type) }}</strong>
                </div>
              </template>
            </el-table-column>

            <el-table-column :label="$t('Reviews')" width="90">
              <template #default="scope">
                <span class="wpsr-date">{{ numberWithCommas(scope.row.reviews_count) }}</span>
              </template>
            </el-table-column>

            <el-table-column :label="$t('Connected on')" min-width="140">
              <template #default="scope">
                <span class="wpsr-date">{{ wpsrDateFormat(scope.row.created_at, 'DD MMM, YYYY') }}</span>
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
                        <el-icon><Edit /></el-icon> Edit
                      </el-dropdown-item>
                      <div v-if="scope.row.type === 'fluent_forms'">
                        <el-dropdown-item command="edit_form">
                          <el-icon><Edit /></el-icon> Edit Form
                        </el-dropdown-item>
                        <el-dropdown-item v-if="scope.row.ff_integration_url" command="edit_form_integration">
                          <el-icon><Edit /></el-icon> Edit Integration Settings
                        </el-dropdown-item>
                      </div>
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
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && !total_items" class="wpsr-page-section">
        <div class="wpsr-empty-state">
          <div class="wpsr-empty-content">
            <span class="wpsr-empty-state-icon-zero-width wpsr-mb-20">
              <CustomSourceEmptyStates/>
            </span>
            <h1>{{ $t('No Sources') }}</h1>
            <p>
              {{ $t('You can add sources to get custom reviews easily, click below button to add sources.') }}
            </p>
            <div class="wpsr-no-sources-actions">
              <el-button type="primary" @click="showAddSourceDialog = true" class="wpsr_primary_btn">
                <el-icon><Plus /></el-icon>
                Add Source
              </el-button>
            </div>
          </div>
        </div>
      </div>

      <!-- Custom Source Name Dialog -->
      <div class="wpsr-custom-source-general-settings">
        <el-dialog
          title="Create Custom Source"
          v-model="showAddSourceDialog"
          width="680px"
          class="wpsr-connection-modal wpsr-form-modal"
        >
          <template #header>
            <div class="wpsr-connection-modal-header">
              <h4 class="wpsr-dialog-title">General Settings</h4>
            </div>
          </template>
          <div class="wpsr-connection-modal-body">
            <div class="wpsr-modal-form-fields" v-if="!showTemplateSelection">
              <div class="wpsr-d-flex">
                <el-form-item>
                  <template #label>
                    <h3 class="wpsr-connection-modal-input-heading">Choose Source Type</h3>
                  </template>
                  <el-radio-group v-model="sourceForm.type" @change="handleSourceTypeChange">
                    <el-radio value="custom">Custom</el-radio>
                    <el-radio value="fluent_forms">Fluent Forms</el-radio>
                  </el-radio-group>
                </el-form-item>
              </div>
              <div class="wpsr-d-flex" v-if="sourceForm.type === 'custom'">
                <el-form-item>
                  <template #label>
                    <h3 class="wpsr-connection-modal-input-heading">Name Your Custom Source</h3>
                  </template>
                  <el-input
                    class="wpsr-input-default wpsr-border-all-around"
                    v-model="sourceForm.name"
                    placeholder="Enter Custom Source Name"
                    @keyup.enter="handleCreateSource"
                    ref="sourceNameInput"
                  />
                </el-form-item>
              </div>

              <div v-if="sourceForm.type === 'custom'" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-flex-align-center wpsr-mb-30">
                <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
                <p>Next create reviews with rating, review text, review title, photo etc.</p>
              </div>
              <div v-if="!need_installation && sourceForm.type === 'fluent_forms'" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-flex-align-center wpsr-mb-30">
                <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
                <p>Next choose Fluent form template</p>
              </div>

              <div class="wpsr-install-addon" v-if="sourceForm.type === 'fluent_forms' && need_installation" v-loading="installing_ff" element-loading-text="Installing Fluent Forms...">
                  <h3>Start Collecting Reviews with Fluent Forms</h3>
                  <p>Easily gather customer reviews using Fluent Forms. Connect it with WP Social Ninja and showcase authentic feedback that builds trust.</p>
                  <el-button class="wpsr_primary_btn el-button wpsr-mt-20" @click="installFF()" type="success">{{$t('Activate Fluent Forms Plugin')}}</el-button>
              </div>
            </div>

            <!-- Template Selection for Fluent Forms -->
            <div v-if="showTemplateSelection" class="wpsr-modal-form-fields wpsr-template-selection">
              <div>
                <div class="wpsr-d-flex wpsr-mb-10">
                  <h3 class="wpsr-connection-modal-input-heading">Select a Template</h3>
                </div>
                <div v-loading="fetching" element-loading-text="Fetching templates...">
                  <div class="wpsr-template-grid" element-loading-text="Loading templates...">
                    <div
                        v-for="template in formTemplates"
                        :key="template.id"
                        class="wpsr-template-card"
                        :class="{ 'selected': selectedTemplate === template.id }"
                        @click="selectTemplate(template.id)"
                    >
                      <div class="wpsr-template-preview">
                        <ClassicReviewFormIcon v-if="template.id === 'classic_review_form'"/>
                        <ModernReviewFormIcon v-if="template.id === 'modern_review_form'" />
                      </div>
                      <div class="wpsr-template-info">
                        <h4>{{ template.label }}</h4>
                      </div>
                    </div>
                  </div>
                  <div class="wpsr-template-alternative-option">
                    <span class="wpsr-template-alternative-or">or</span>
                    <h3 class="wpsr-connection-modal-input-heading">Use Existing Fluent Form</h3>
                    <el-input
                        class="wpsr-input-default wpsr-border-all-around"
                        placeholder="Enter Fluent Form ID"
                        v-model="manualFormId"
                        @focus="clearTemplateSelection"
                        @input="validateFormId"
                        type="number"
                        min="1"
                        size="small">
                    </el-input>
                    <p>This will link an existing Fluent Form instead of creating a new one.</p>
                  </div>
                  <div v-if="selectedTemplate || manualFormId" class="wpsr-alert wpsr-alert-info wpsr-d-flex wpsr-flex-align-center wpsr-mb-30">
                    <el-icon size="18" color="var(--wpsr-svg-secondary-color)"><InfoFilled /></el-icon>
                    <p>This form will be created in Fluent Forms and you can customize anytime.</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="wpsr-connection-modal-footer wpsr-d-flex wpsr-jc-end wpsr-border-top">
              <el-button v-if="showTemplateSelection" @click="goBackToSourceForm" class="wpsr_default_btn">Back</el-button>
              <el-button v-else @click="showAddSourceDialog = false" class="wpsr_default_btn">Cancel</el-button>
              <el-button
                type="primary"
                @click="handleCreateSource"
                :loading="loading"
                :disabled="getButtonDisabled()"
                class="wpsr_primary_btn"
              >
                {{ getButtonText() }}
              </el-button>
            </div>
          </div>
        </el-dialog>
      </div>
      <!-- Unified Delete Confirmation Modal -->
      <el-dialog
          class="wpsr-confirmation-modal"
          v-model="show_delete_modal"
          width="40%"
          :before-close="handleDeleteClose">
        <div class="wpsr-delete-content">
          <h4 v-if="isBulkDelete">Are you sure you want to delete {{ selectedItems.length }} source(s)?</h4>
          <h4 v-else>Are you sure you want to delete this source?</h4>
          <p>All reviews linked to {{ isBulkDelete ? 'these sources' : 'this source' }} will be permanently deleted. This action cannot be undone.</p>
          
          <div v-if="isBulkDelete" class="wpsr-delete-details">
            <p><strong>Selected Sources:</strong> {{ selectedItems.length }} item(s)</p>
            <p><strong>Source Names:</strong> {{ selectedItems.map(item => item.title || item.name).join(', ') }}</p>
          </div>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="show_delete_modal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="danger" @click="confirmDelete()">Delete {{ isBulkDelete ? 'Sources' : 'Source' }}</el-button>
          </div>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script>
import { Upload, Plus, InfoFilled, Delete } from '@element-plus/icons-vue'
import { tableMixin } from "../../mixins/tableMixin";
import PhotoUploader from "../core-ui/editor/PhotoUploader.vue";
import UpgradeToProButton from "./advertise/UpgradeToProButton.vue";
import CustomSourceEmptyStates from "../pieces/icons/CustomSourceEmptyStates.vue";
import { PlatformIconMixin } from '../../mixins/PlatformIconMixin';
import ClassicReviewFormIcon from "../pieces/icons/ClassicReviewFormIcon.vue";
import ModernReviewFormIcon from "../pieces/icons/ModernReviewFormIcon.vue";

export default {
  name: 'CustomSources',
  mixins: [tableMixin, PlatformIconMixin],
  components: {
    ModernReviewFormIcon,
    ClassicReviewFormIcon,
    CustomSourceEmptyStates,
    UpgradeToProButton,
    PhotoUploader,
    Upload,
    Plus,
    InfoFilled,
    Delete
  },
  data() {
    return {
      loading: false,
      sources: [],
      bulkAction: '',
      showAddSourceDialog: false,
      showFluentFormTemplateDialog: false,
      showTemplateSelection: false,
      selectedFilter: 'all',
      selectedTemplate: '',
      manualFormId: null,
      sourceForm: {
        name: '',
        type: 'custom'
      },
      selectedItems: [],
      installing_ff: false,
      need_installation: !this.appVars.has_fluent_form,
      formTemplates: [],
      fluentFormData: [],
      fetching: false,
      isBulkDelete: false,
      show_delete_modal: false,
      deletingItem: {},
    };
  },
  mounted() {
    if (this.has_pro) {
      this.getAll('pro/custom-sources');
    }

    if (this.$route.query.create) {
      this.showAddSourceDialog = true;
      this.$router.replace({ 
        path: this.$route.path
      });
    }
  },
  methods: {
    installFF() {
      this.installing_ff = true;
      this.$post('platforms/addons', {
        platform: 'fluent_forms',
      })
          .then(response => {
            if(response.data.is_installed){
              this.fetchFormTemplates();
              this.handleSuccess(response.data.message);
              this.need_installation = false;
            } else {
              this.need_installation = true;
            }
          })
          .catch((error) => {
            this.handleError(error);
          })
          .finally(() => {
            this.installing_ff = false;
          });
    },
    fetchFormTemplates() {
      this.fetching = true;
      this.$get('pro/custom-sources/forms/templates')
          .then(response => {
            this.formTemplates = response;
          })
          .catch((errors) => {
            this.handleError(errors);
          })
          .always(() => {
            this.fetching = false;
          });
    },
    handleSourceTypeChange(type) {
      // Reset form when type changes
      if (type !== this.sourceForm.type) {
        this.sourceForm.name = '';
      }
    },

    handleCreateSource() {
      if (this.sourceForm.type === 'custom' && !this.sourceForm.name.trim()) {
        this.handleError('Please enter a source name');
        return;
      }

      if (this.sourceForm.type === 'fluent_forms') {
        if (!this.showTemplateSelection) {
          // Show template selection within the same modal
          this.showTemplateSelection = true;
          this.fetchFormTemplates();
        } else {
          // Create the fluent form source
          this.createFluentFormSource();
        }
      } else {
        // Create custom source directly
        this.createSource();
      }
    },

    createSource() {
      this.loading = true;
      this.$post('pro/custom-sources', {
        type: this.sourceForm.type,
        name: this.sourceForm.name.trim(),
        label: this.sourceForm.name.trim()
      })
        .then(response => {
          if (response && response.source_id) {
            this.showAddSourceDialog = false;
            this.sourceForm.name = '';
            this.sourceForm.type = 'custom';
            this.$router.push({
              name: 'edit-custom-source',
              params: { template_id: response.source_id }
            });
          }
        })
        .catch(error => {
          this.handleError(error);
        })
        .always(() => {
          this.loading = false;
        });
    },

    selectTemplate(templateId) {
      this.selectedTemplate = templateId;
      // Clear manual form ID when template is selected
      this.manualFormId = '';
    },
    clearTemplateSelection() {
      // Clear selected template when user focuses on manual form ID input
      this.selectedTemplate = '';
    },
    validateFormId(value) {
      // Remove any non-numeric characters
      const numericValue = value.replace(/[^0-9]/g, '');
      this.manualFormId = numericValue;
    },
    createFluentFormSource() {
      // Use manual form ID if provided, otherwise use selected template
      const formId = this.manualFormId || this.selectedTemplate;
      if (!formId) {
        this.handleError('Please select a template or enter a form ID');
        return;
      }
      // Validate manual form ID is a positive number
      if (this.manualFormId && (isNaN(this.manualFormId) || parseInt(this.manualFormId) <= 0)) {
        this.handleError('Please enter a valid form ID (positive number)');
        return;
      }

      this.loading = true;
      this.$post('pro/custom-sources', {
        type: 'fluent_forms',
        name: 'fluent_forms',
        label: 'Fluent Forms',
        is_manual_form: !!this.manualFormId,
        form_id: formId
      })
        .then(response => {
          if (response && response.source_id) {
            this.showFluentFormTemplateDialog = false;
            this.resetSourceForm();
            this.$message.success('Fluent Form source created successfully');
            this.$router.push({
              name: 'edit-custom-source',
              params: { template_id: response.source_id }
            });
          }
        })
        .catch(error => {
          this.handleError(error);
        })
        .always(() => {
          this.loading = false;
        });
    },

    goBackToSourceForm() {
      this.showTemplateSelection = false;
      this.selectedTemplate = '';
    },

    resetSourceForm() {
      this.sourceForm = {
        name: '',
        type: 'custom'
      };
      this.selectedTemplate = '';
      this.manualFormId = null;
      this.showTemplateSelection = false;
    },

    getButtonDisabled() {
      if (this.sourceForm.type === 'custom') {
        return !this.sourceForm.name.trim();
      }
      if (this.showTemplateSelection) {
        return !this.selectedTemplate && !this.manualFormId;
      }
      if( this.sourceForm.type === 'fluent_forms' && this.need_installation) {
        return true;
      }
      return false;
    },

    getButtonText() {
      if (this.showTemplateSelection) {
        return 'Create Source';
      }
      return this.sourceForm.type === 'fluent_forms' ? 'Next' : 'Create Source';
    },

    handleRowAction(command, row) {
      switch(command) {
        case 'edit_form':
          // Open Fluent Forms editor in new tab
          window.open(row.ff_form_edit_url, '_blank');
          break;
        case 'edit_form_integration':
          window.open(row.ff_integration_url, '_blank');
          break;
        case 'edit':
          this.$router.push({ name: 'edit-custom-source', params: { template_id: row.id } });
          break;
        case 'delete':
          this.beforeDeleteHandler(row);
          break;
      }
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
        default:
          this.$notify.warning('Selected action is not supported yet');
      }
    },
    handleSelectionChange(selection) {
      this.selectedItems = selection;
    },
    beforeBulkDeleteHandler() {
      if (!this.has_pro) {
        this.handlePro();
        return;
      }
      
      this.isBulkDelete = true;
      this.show_delete_modal = true;
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
    performBulkDelete() {
      const selectedIds = this.selectedItems.map(item => item.id);
      this.loading = true;
      
      this.$del(this.endpoint, { ids: selectedIds })
          .then(response => {
            if (response) {
              this.handleSuccess(response.message || `Successfully deleted ${selectedIds.length} source(s)`);
              this.getItems();
              this.selectedItems = [];
              this.bulkAction = '';
            }
          }).catch(errors => {
            this.handleError(errors);
          }).always(() => {
            this.loading = false;
            this.show_delete_modal = false;
            this.isBulkDelete = false;
          });
    },
  },
  watch: {
    showAddSourceDialog(val) {
      if (val) {
        this.$nextTick(() => {
          this.$refs.sourceNameInput?.focus();
        });
      } else {
        // Reset form when dialog closes
        this.resetSourceForm();
      }
    }
  }
};
</script>