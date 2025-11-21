<template>
  <div class="wpsr-page-container wpsr-all-templates-page">
    <div v-if="hasPermission(['wpsn_manage_templates', 'wpsn_full_access'])">
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
                <h1 class="wpsr-page-title">{{ $t('All Templates') }}</h1>
                <p class="wpsr-page-subtitle">{{ $t('Your all templates are here') }}</p>
              </div>

              <div class="wpsr-header-right">
                <el-dropdown @command="addNewItem">
                  <el-button type="primary" class="wpsr_primary_btn">
                    <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Create Template') }}
                  </el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <template v-for="(section, key) in valid_sections" :key="key">
                        <el-dropdown-item :command="section" v-if="connectedPlatformSections && connectedPlatformSections[section]">
                          {{ `Add ${ucFirst(section)} Template` }}
                        </el-dropdown-item>
                      </template>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
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
                 <el-option label="Duplicate" value="duplicate">
                   <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                     <el-icon><DocumentCopy /></el-icon>
                     <span>Duplicate</span>
                   </span>
                 </el-option>
               </el-select>
               <el-button @click="applyBulkAction" :disabled="!selectedItems.length">Apply</el-button>
             </div>
              <div class="wpsr-controls-filter">
                <el-select class="wpsr-select-field-primary" v-model="filter_value" placeholder="All Platforms" size="default" clearable
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

          <!-- Templates Table -->
          <div class="wpsr-table-wrapper">
            <el-table
                class="wpsr-templates-table"
                :data="items"
                @selection-change="handleSelectionChange"
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
                        {{ $trimWords(scope.row.post_title, 3, true) }} <el-icon class="wpsr-ml-5 wpsr-display-on-hover"><Edit /></el-icon>
                      </strong>
                    </div>
                  </template>
                </el-table-column>

                <el-table-column label="Platform" min-width="150">
                  <template #default="scope">
                    <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                      <div class="wpsr-platform-cell" v-html="getPlatformIconsHtml(scope.row.platform_name)"></div>
                      <span class="wpsr-platform-name wpsr-ml-10">{{ validPlatforms.find(item => item.value === scope.row.platform_name)?.label ?? ""}}</span>
                    </div>
                  </template>
                </el-table-column>

                <el-table-column label="Create Date" min-width="140">
                  <template #default="scope">
                    <span class="wpsr-date">{{ wpsrDateFormat(scope.row.post_date_gmt, 'DD MMM, YYYY') }}</span>
                  </template>
                </el-table-column>

                <el-table-column label="ShortCode" min-width="280">
                  <template #default="scope">
                    <div class="wpsr-shortcode-cell">
                      <template v-if="feedPlatforms.includes(scope.row.platform_name) || scope.row.platform_name === 'testimonial'">
                        <el-tooltip effect="dark" content="Click to copy shortcode" placement="top">
                          <code class="wpsr-shortcode copy"
                                :data-clipboard-text='`[${scope.row.platform_name === "social_wall" ? "wp_social_ninja_wall_feed" : "wp_social_ninja"} id="${scope.row.ID}" platform="${scope.row.platform_name}"]`'>
                            <el-icon class="wpsr-copy-icon"><DocumentCopy /></el-icon>
                            [{{ scope.row.platform_name === 'social_wall' ? 'wp_social_ninja_wall_feed' : 'wp_social_ninja' }} id="{{ scope.row.ID }}" platform="{{ scope.row.platform_name }}"]
                          </code>
                        </el-tooltip>
                      </template>
                      <template v-else>
                        <el-tooltip effect="dark" content="Click to copy shortcode" placement="top">
                          <code class="wpsr-shortcode copy"
                                :data-clipboard-text='`[wp_social_ninja id="${scope.row.ID}" platform="reviews"]`'>
                            <el-icon class="wpsr-copy-icon"><DocumentCopy /></el-icon>
                            [wp_social_ninja id="{{ scope.row.ID }}" platform="reviews"]
                          </code>
                        </el-tooltip>
                      </template>
                    </div>
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
                          <el-dropdown-item command="edit" v-if="scope.row.platform_name">
                            <el-icon><Edit /></el-icon> Edit
                          </el-dropdown-item>
                          <el-dropdown-item command="duplicate">
                            <el-icon><DocumentCopy /></el-icon> Duplicate
                          </el-dropdown-item>
<!--                          <el-dropdown-item command="clear_cache" v-if="feedPlatforms.includes(scope.row.platform_name)">-->
<!--                            <el-icon><Coin /></el-icon> Clear Cache-->
<!--                          </el-dropdown-item>-->
                          <el-dropdown-item class="wpsr-action-delete" command="delete">
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
        <div v-else-if="!loading" class="wpsr-page-section">
          <div class="wpsr-empty-state">
            <div class="wpsr-empty-content">
              <span class="wpsr-empty-state-icon"><CreateTemplateIcon/></span>
              <h1>{{ $t('All Templates') }}</h1>
              <p v-if="Object.keys(validPlatforms).length">
                {{ $t('Start by creating a template to design your perfect layout.') }}
              </p>

              <el-dropdown v-if="Object.keys(validPlatforms).length" @command="addNewItem">
                <el-button class="wpsr_primary_btn" type="primary">
                  <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Create Template') }}
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <template v-for="(section, key) in valid_sections" :key="key">
                      <el-dropdown-item :command="section" v-if="connectedPlatformSections && connectedPlatformSections[section]">
                        {{ `Add ${ucFirst(section)} Template` }}
                      </el-dropdown-item>
                    </template>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>

              <div v-if="!Object.keys(validPlatforms).length" class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mt-20">
                <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
                <p>
                  <strong>Note:</strong>
                  {{ $t('Before creating your template you need to configure your social platform.') }}
                  <router-link to="/">{{ $t('Click Here') }}</router-link>
                  {{ $t(' to configure your settings first!') }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!--Unified Delete Confirmation Modal-->
        <el-dialog
            class="wpsr-confirmation-modal"
            v-model="show_delete_modal"
            width="40%"
            :before-close="handleDeleteClose">
          <div class="wpsr-delete-content">
            <h4 v-if="isBulkDelete">Are you sure, you want to delete {{ selectedItems.length }} template(s)?</h4>
            <h4 v-else>Are you sure you want to delete this template?</h4>
            <p v-if="isBulkDelete">All the data associated with these templates will be deleted.</p>
            <p v-else>All data associated with this template will be permanently deleted.</p>

            <div class="wpsr-delete-details">
              <template v-if="isBulkDelete">
                <p><strong>Selected Templates:</strong> {{ selectedItems.length }} item(s)</p>
                <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
              </template>
              <template v-else>
                <p><strong>Template ID:</strong> #{{ deletingItem.ID }}</p>
                <p><strong>Template Title:</strong> {{ deletingItem.post_title }}</p>
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
            <h4>Are you sure, you want to {{ bulkAction }} {{ selectedItems.length }} template(s)?</h4>
            <p v-if="bulkAction === 'enable'">This will activate the selected templates.</p>
            <p v-else-if="bulkAction === 'disable'">This will deactivate the selected templates.</p>

            <div class="wpsr-delete-details">
              <p><strong>Selected Templates:</strong> {{ selectedItems.length }} item(s)</p>
              <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
            </div>
          </div>
          <template #footer>
            <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
              <el-button class="wpsr_default_btn" @click="show_status_modal = false">Cancel</el-button>
              <el-button class="wpsr_primary_btn" @click="performStatusUpdate()">Confirm</el-button>
            </div>
          </template>
        </el-dialog>

        <!--Bulk Duplicate Confirmation Modal-->
        <el-dialog
            class="wpsr-confirmation-modal"
            v-model="show_bulk_duplicate_modal"
            width="40%">
          <div class="wpsr-delete-content">
            <h4>Are you sure, you want to duplicate {{ selectedItems.length }} template(s)?</h4>
            <p>This will create copies of the selected templates.</p>

            <div class="wpsr-delete-details">
              <p><strong>Selected Templates:</strong> {{ selectedItems.length }} item(s)</p>
              <p><strong>IDs:</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
            </div>
          </div>
          <template #footer>
            <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
              <el-button class="wpsr_default_btn" @click="show_bulk_duplicate_modal = false">Cancel</el-button>
              <el-button class="wpsr_primary_btn" @click="performBulkDuplicate()">Confirm</el-button>
            </div>
          </template>
        </el-dialog>

    </div>
    <PermissionsNotification v-if="!hasPermission(['wpsn_manage_templates', 'wpsn_full_access'])" />
  </div>
</template>

<script type="text/babel">
import {tableMixin} from "../../mixins/tableMixin";
import PermissionsNotification from "./advertise/PermissionsNotification.vue";
import CreateTemplateIcon from  '../pieces/icons/CreateTemplateIcon.vue';
import { Plus, Search, Edit, Delete, DocumentCopy, MoreFilled, WarningFilled, Coin } from '@element-plus/icons-vue';
import InfoIcon from "../pieces/icons/InfoIcon.vue";
import { PlatformIconMixin } from '../../mixins/PlatformIconMixin';

export default {
    name: 'AllTemplates',
    components: {
      InfoIcon,
      PermissionsNotification,
      Plus,
      Search,
      Edit,
      Delete,
      DocumentCopy,
      MoreFilled,
      WarningFilled,
      Coin,
      CreateTemplateIcon
    },
    mixins: [tableMixin, PlatformIconMixin],
    data() {
      return {
        valid_sections: ['reviews', 'testimonial', 'youtube', 'instagram', 'facebook_feed', 'tiktok', 'twitter'],
        feedPlatforms: ['instagram', 'facebook_feed', 'youtube', 'tiktok', 'twitter'],
        selectedItems: [],
        bulkAction: '',
        show_status_modal: false,
        isBulkStatus: false,
        isBulkDelete: false,
        show_bulk_duplicate_modal: false,
        isBulkDuplicate: false,
        loading: false,
      }
    },
    methods: {
      getEditLink(row) {
        if(row.platform_name === null) {
          return { name: 'edit-template', params: { template_id: row.ID } };
        }

        if (this.getValidPlatforms(row.platform_name).length > 1 && (row.platform_name === 'tiktok' && this.is_custom_feed_for_tiktok_activated)) {
          return { name: 'edit-template', params: { template_id: row.ID } };
        } else {
          return { name: (this.feedPlatforms.includes(row.platform_name)) ? 'edit-' + row.platform_name.replace('_', '-') + '-template' : 'edit-template', params: { template_id: row.ID } };
        }
      },

      handleSelectionChange(selection) {
        this.selectedItems = selection;
      },

      handleRowAction(command, row) {
        switch(command) {
          case 'edit':
            this.$router.push(this.getEditLink(row));
            break;
          case 'duplicate':
            this.duplicateItem(row);
            break;
          case 'clear_cache':
            this.clearCache(row);
            break;
          case 'delete':
            this.isBulkDelete = false;
            this.beforeDeleteHandler(row);
            break;
        }
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
      beforeBulkDeleteHandler() {
        this.isBulkDelete = true;
        this.show_delete_modal = true;
      },
      applyBulkAction() {
        if (!this.bulkAction || !this.selectedItems.length) return;
        
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
      clearCache(row) {
        this.loading = true;

        this.$del('settings', {
          platform: row.platform_name,
          cacheType: 'template',
          templateId: row.ID
        }).then(response => {
          if(response){
            this.loading = false;
            this.handleSuccess(response.message);
          }
        }).catch(errors => {
          this.loading = false;
          this.handleError(errors);
        });
      },
    },
    mounted() {
        this.getAll('templates');
        this.clipboard();
    }
}
</script>