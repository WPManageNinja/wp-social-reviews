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
                <el-button v-if="availableSections.length === 1" type="primary" class="wpsr_primary_btn" :loading="creatingItem" @click="addNewItem(availableSections[0])">
                  <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Add %s Template', ucFirst(availableSections[0])) }}
                </el-button>
                <el-dropdown v-else-if="availableSections.length > 1" @command="addNewItem">
                  <el-button type="primary" class="wpsr_primary_btn" :loading="creatingItem">
                    <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Create Template') }}
                  </el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item v-for="section in availableSections" :key="section" :command="section">
                        {{ $t('Add %s Template', ucFirst(section)) }}
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </div>
          </div>

          <!-- Filters and Actions -->
          <div class="wpsr-table-controls">
            <div class="wpsr-controls-left">
              <div class="wpsr-controls-bulk-actions">
               <el-select class="wpsr-select-field-primary" v-model="bulkAction" :placeholder="$t('Bulk Action')" size="default" style="width: 160px;">
                 <el-option :label="$t('Bulk Actions')" value="">
                  <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                    <el-icon><Plus /></el-icon>
                    <span>{{ $t('Bulk Actions') }}</span>
                  </span>
                 </el-option>
                 <el-option :label="$t('Delete')" value="delete">
                   <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                     <el-icon><Delete /></el-icon>
                     <span>{{ $t('Delete') }}</span>
                   </span>
                 </el-option>
                 <el-option :label="$t('Duplicate')" value="duplicate">
                   <span class="wpsr-d-flex wpsr-flex-align-center wpsr-gap-8">
                     <el-icon><DocumentCopy /></el-icon>
                     <span>{{ $t('Duplicate') }}</span>
                   </span>
                 </el-option>
               </el-select>
               <el-button @click="applyBulkAction" :disabled="!selectedItems.length">{{ $t('Apply') }}</el-button>
             </div>
              <div class="wpsr-controls-filter">
                <el-select class="wpsr-select-field-primary" v-model="filter_value" :placeholder="$t('All Platforms')" size="default" clearable
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
                :placeholder="$t('Search...')"
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

                <el-table-column :label="$t('ID')" width="70" prop="ID" sortable>
                  <template #default="scope">
                    <span class="wpsr-item-id wpsr-item-editable" @click.prevent="handleRowAction('edit', scope.row)">#{{ scope.row.ID }}</span>
                  </template>
                </el-table-column>

                <el-table-column
                    :label="$t('Title')"
                    min-width="180"
                    show-overflow-tooltip
                >
                  <template #default="scope" >
                    <div class="wpsr-item-title-cell wpsr-item-editable" @click.prevent="handleRowAction('edit', scope.row)">
                      <strong class="wpsr-item-title wpsr-d-flex wpsr-flex-align-center">
                        <span dir="auto">{{ $trimWords(scope.row.post_title, 3, true) }}</span> <el-icon class="wpsr-ml-5 wpsr-display-on-hover"><Edit /></el-icon>
                      </strong>
                    </div>
                  </template>
                </el-table-column>

                <el-table-column :label="$t('Platform')" min-width="150">
                  <template #default="scope">
                    <div class="wpsr-item-group wpsr-d-flex wpsr-flex-align-center">
                      <div class="wpsr-platform-cell" v-html="getPlatformIconsHtml(scope.row.platform_name)"></div>
                      <span class="wpsr-platform-name wpsr-ml-10">{{ validPlatforms.find(item => item.value === scope.row.platform_name)?.label ?? ""}}</span>
                    </div>
                  </template>
                </el-table-column>

                <el-table-column :label="$t('Create Date')" min-width="140">
                  <template #default="scope">
                    <span class="wpsr-date">{{ wpsrDateFormat(scope.row.post_date_gmt, 'DD MMM, YYYY') }}</span>
                  </template>
                </el-table-column>

                <el-table-column :label="$t('ShortCode')" min-width="280">
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
                          <el-dropdown-item command="clear_cache" v-if="feedPlatforms.includes(scope.row.platform_name)">
                            <el-icon><Coin /></el-icon> Clear Cache
                          </el-dropdown-item>
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
                    {{ $t('Page %1$s of %2$s', page_number, Math.ceil(total / per_page)) }}
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

              <template v-if="Object.keys(validPlatforms).length">
                <el-button v-if="availableSections.length === 1" class="wpsr_primary_btn" type="primary" :loading="creatingItem" @click="addNewItem(availableSections[0])">
                  <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Add %s Template', ucFirst(availableSections[0])) }}
                </el-button>
                <el-dropdown v-else-if="availableSections.length > 1" @command="addNewItem">
                  <el-button class="wpsr_primary_btn" type="primary" :loading="creatingItem">
                    <el-icon class="wpsr-mr-5"><Plus /></el-icon>{{ $t('Create Template') }}
                  </el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item v-for="section in availableSections" :key="section" :command="section">
                        {{ $t('Add %s Template', ucFirst(section)) }}
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </template>

              <div v-if="!Object.keys(validPlatforms).length" class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mt-20">
                <InfoIcon color="var(--wpsr-icon-info-warning-color)"/>
                <p>
                  <strong>{{ $t('Note:') }}</strong>
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
            :close-on-click-modal="!loading"
            :show-close="!loading"
            width="40%"
            :before-close="handleDeleteClose">
          <div class="wpsr-delete-content">
            <h4 v-if="isBulkDelete">{{ $t('Are you sure you want to delete %s template(s)?', selectedItems.length) }}</h4>
            <h4 v-else>{{ $t('Are you sure you want to delete this template?') }}</h4>
            <p v-if="isBulkDelete">{{ $t('All the data associated with these templates will be deleted.') }}</p>
            <p v-else>{{ $t('All data associated with this template will be permanently deleted.') }}</p>

            <div class="wpsr-delete-details">
              <template v-if="isBulkDelete">
                <p><strong>{{ $t('Selected Templates:') }}</strong> {{ $t('%s item(s)', selectedItems.length) }}</p>
                <p><strong>{{ $t('IDs:') }}</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
              </template>
              <template v-else>
                <p><strong>{{ $t('Template ID:') }}</strong> #{{ deletingItem.ID }}</p>
                <p><strong>{{ $t('Template Title:') }}</strong> {{ deletingItem.post_title }}</p>
              </template>
            </div>
          </div>
          <template #footer>
            <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
              <el-button class="wpsr_default_btn" :disabled="loading" @click="show_delete_modal = false">{{ $t('Cancel') }}</el-button>
              <el-button class="wpsr_primary_btn" type="danger" :loading="loading" @click="confirmDelete()">{{ $t('Confirm') }}</el-button>
            </div>
          </template>
        </el-dialog>

        <!--Status Update Confirmation Modal-->
        <el-dialog
            class="wpsr-confirmation-modal"
            v-model="show_status_modal"
            :close-on-click-modal="!loading"
            :show-close="!loading"
            width="40%">
          <div class="wpsr-delete-content">
            <h4 v-if="bulkAction === 'enable'">{{ $t('Are you sure you want to enable %s template(s)?', selectedItems.length) }}</h4>
            <h4 v-else>{{ $t('Are you sure you want to disable %s template(s)?', selectedItems.length) }}</h4>
            <p v-if="bulkAction === 'enable'">{{ $t('This will activate the selected templates.') }}</p>
            <p v-else-if="bulkAction === 'disable'">{{ $t('This will deactivate the selected templates.') }}</p>

            <div class="wpsr-delete-details">
              <p><strong>{{ $t('Selected Templates:') }}</strong> {{ $t('%s item(s)', selectedItems.length) }}</p>
              <p><strong>{{ $t('IDs:') }}</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
            </div>
          </div>
          <template #footer>
            <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
              <el-button class="wpsr_default_btn" :disabled="loading" @click="show_status_modal = false">{{ $t('Cancel') }}</el-button>
              <el-button class="wpsr_primary_btn" :loading="loading" @click="performStatusUpdate()">{{ $t('Confirm') }}</el-button>
            </div>
          </template>
        </el-dialog>

        <!--Bulk Duplicate Confirmation Modal-->
        <el-dialog
            class="wpsr-confirmation-modal"
            v-model="show_bulk_duplicate_modal"
            :close-on-click-modal="!loading"
            :show-close="!loading"
            width="40%">
          <div class="wpsr-delete-content">
            <h4>{{ $t('Are you sure you want to duplicate %s template(s)?', selectedItems.length) }}</h4>
            <p>{{ $t('This will create copies of the selected templates.') }}</p>

            <div class="wpsr-delete-details">
              <p><strong>{{ $t('Selected Templates:') }}</strong> {{ $t('%s item(s)', selectedItems.length) }}</p>
              <p><strong>{{ $t('IDs:') }}</strong> {{ selectedItems.map(item => '#' + item.ID).join(', ') }}</p>
            </div>
          </div>
          <template #footer>
            <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
              <el-button class="wpsr_default_btn" :disabled="loading" @click="show_bulk_duplicate_modal = false">{{ $t('Cancel') }}</el-button>
              <el-button class="wpsr_primary_btn" :loading="loading" @click="performBulkDuplicate()">{{ $t('Confirm') }}</el-button>
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
    computed: {
      availableSections() {
        return this.valid_sections.filter(section => this.connectedPlatformSections && this.connectedPlatformSections[section]);
      }
    },
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