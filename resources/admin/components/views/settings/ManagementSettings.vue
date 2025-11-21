<template>
  <div class="wpsr-advance-settings-main">
    <div v-if="hasPermission(['administrator']) && has_pro">
      <!-- Loading Skeleton -->
      <div v-if="fetching" class="wpsr-general-settings-wrapper">
        <div class="wpsr-settings-container">
          <div class="wpsr-manager-table-wrapper">
            <WpsrSkeleton :rows="10" />
          </div>
        </div>
      </div>

      <!-- Content -->
      <div v-else-if="!fetching">
        <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
          <h1 class="wpsr-gs-header-title">{{$t('Management Settings')}}</h1>
          <el-button @click="addManager" type="success" size="small" class="wpsr_primary_btn wpsr-add-management-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M5.25 5.25V0.75H6.75V5.25H11.25V6.75H6.75V11.25H5.25V6.75H0.75V5.25H5.25Z" fill="#ffffff"/>
            </svg>
            <span class="wpsr-management-btn-text">{{$t('New Manager')}}</span>
          </el-button>
        </div>
        <div class="wpsr-general-settings-wrapper">
          <div class="wpsr-settings-container">
            <div class="wpsr-settings-header">
              <h3 class="wpsr-management-title">{{ $t('Managers') }}</h3>
              <p class="wpsr-management-description">{{ $t('Admins get full access and assign custom permissions to other managers.') }}</p>
            </div>
            <div class="wpsr-manager-table-wrapper">            
              <div class="wpsr-managers-table">
                <div class="wpsr-management-table-header">
                  <div class="wpsr-col-id">ID</div>
                  <div class="wpsr-col-email">Email</div>
                  <div class="wpsr-col-permissions">Permissions</div>
                  <div class="wpsr-col-actions"></div>
                </div>
                
                <div v-if="tableData.length" class="wpsr-table-body">
                <div 
                  v-for="(manager, index) in filteredTableData" 
                  :key="manager.id || index"
                  class="wpsr-management-table-row"
                >
                  <div class="wpsr-col-id">
                    <span class="wpsr-manager-id">#{{ String(manager.id || index + 1).padStart(3, '0') }}</span>
                  </div>
                  <div class="wpsr-col-email">
                    <span class="wpsr-manager-email">{{ manager.email }}</span>
                  </div>
                  <div class="wpsr-col-permissions">
                    <div class="wpsr-permissions-container">
                      <span 
                        v-for="(role, roleIndex) in getVisiblePermissions(manager.permissions)" 
                        :key="roleIndex"
                        class="wpsr-permission-tag"
                      >
                        {{ readable(role) }}
                      </span>
                      <div 
                        v-if="getHiddenPermissionsCount(manager.permissions) > 0"
                        class="wpsr-permission-more-container"
                        @mouseenter="showTooltip(manager.id, $event)"
                        @mouseleave="hideTooltip"
                      >
                        <span class="wpsr-permission-more">
                          +{{ getHiddenPermissionsCount(manager.permissions) }}
                        </span>
                        <div 
                          v-show="showCustomTooltip === manager.id"
                          class="wpsr-custom-tooltip"
                          :style="tooltipStyle"
                        >
                          <div class="wpsr-tooltip-permissions">
                            <div 
                              v-for="(role, roleIndex) in getHiddenPermissions(manager.permissions)" 
                              :key="roleIndex"
                              :class="['wpsr-tooltip-permission-tag', { 'wpsr-full-width': isLongPermission(readable(role)) }]"
                            >
                              {{ readable(role) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="wpsr-col-actions">
                    <el-dropdown 
                      trigger="click" 
                      placement="bottom-end"
                      @command="handleAction"
                      class="wpsr-manager-actions"
                    >
                      <el-button type="default" class="wpsr-actions-button">
                        <el-icon><MoreFilled /></el-icon>
                      </el-button>
                      <template #dropdown>
                        <el-dropdown-menu>
                          <el-dropdown-item 
                            :command="{action: 'edit', manager: manager}"
                            class="wpsr-dropdown-item"
                          >
                            <el-icon><Edit /></el-icon>
                            Edit Manager
                          </el-dropdown-item>
                          
                          <el-dropdown-item 
                            :command="{action: 'delete', manager: manager}"
                            class="wpsr-dropdown-item wpsr-delete-item"
                          >
                            <el-icon><Delete /></el-icon>
                            Delete Manager
                          </el-dropdown-item>
                        </el-dropdown-menu>
                      </template>
                    </el-dropdown>
                  </div>
                </div>
              </div>

                <div v-else-if="!fetching && !tableData.length">
                  <div class="wpsr-empty-state">
                    <div class="wpsr-empty-content">
                      <span class="wpsr-empty-state-icon">
                        <ReviewsEmptyStates/>
                      </span>
                      <h1>{{$t('No Manager found yet')}}</h1>
                      <p>
                        {{ $t('To continue, you\'ll need to add a manager.') }}
                      </p>
                      <el-button class="wpsr_primary_btn" type="primary" size="default" @click="addManager">
                        {{ $t('Add a Manager') }}
                      </el-button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <el-dialog
          :title="(editing_manager.id) ? $t('Edit Manager') : $t('Add New Manager')"
          v-model="dialogFormVisible"
          :append-to-body="true"
          :close-on-click-modal="false"
          width="600px"
          class="wpsr-manager-dialog wpsr-modal wpsr-connection-modal">

          <div class="wpsr-form-section">
            <div class="wpsr-field-group">
              <label class="wpsr-field-label">
                User Email
              </label>
              <el-input
                  :disabled="editing_manager.id ? true : false"
                  v-model="editing_manager.email"
                  type="email"
                  autocomplete="off"
                  :placeholder="$t('User Email Address')"
                  class="wpsr-email-input wpsr-input-default wpsr-border-all-around"
                  @input="validateEmail"
                  @blur="validateEmail"
              ></el-input>
              <div v-if="errorMessage" class="wpsr-error-message">
                <p>{{ errorMessage }}</p>
              </div>
              <div class="wpsr-helper-text">
                <svg class="wpsr-management-info-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M6 12C2.6862 12 0 9.3138 0 6C0 2.6862 2.6862 0 6 0C9.3138 0 12 2.6862 12 6C12 9.3138 9.3138 12 6 12ZM5.4 5.4V9H6.6V5.4H5.4ZM5.4 3V4.2H6.6V3H5.4Z" fill="#99A0AE"/>
                </svg>

                <span>Please provide email address of your existing system user</span>
              </div>
            </div>
          </div>
          
          <div class="wpsr-form-section">
            <div class="wpsr-field-group">
              <label class="wpsr-field-label">Permissions</label>
              <el-checkbox-group v-model="editing_manager.permissions" class="wpsr-permissions-grid">
                <div v-for="role in permissions" :key="role.slug" class="wpsr-permission-item wpsr_primary_checkbox_or_radio">
                  <el-checkbox
                      :value="role.slug"
                      class="wpsr-permission-checkbox">
                    {{ role.title }}
                  </el-checkbox>
                </div>
              </el-checkbox-group>
            </div>
          </div>
          <template #footer>
            <span class="dialog-footer">
              <el-button
                class="wpsr_default_btn"
                @click="dialogFormVisible = false"
              >
                {{ $t("Cancel") }}
              </el-button>
               <el-button
                 class="wpsr_primary_btn"
                 :disabled="saving"
                 @click="handleManager"
               >
                 {{ $t("Confirm") }}
               </el-button>
            </span>
          </template>
      </el-dialog>
    </div>
    <div v-if="!has_pro" >
      <div class="wpsr-managers-upgrade-to-pro" v-if="!has_pro">
        <img :src="assets_url+'/images/promotion/managers-promo.png'" alt="">
        <div class="wpsr-managers-upgrade-message">
          <h2>WPSN Managers - Roles and Permissions</h2>
          <p class="wpsr-mb-10">Amplify your WP Social Ninja experience by effortlessly adding new users to your plugin dashboard. For example, User X accesses only the reviews section, and User Y seamlessly manages the testimonial module.</p>
          <UpgradeToProButton/>
        </div>
      </div>
    </div>
  </div>
</template>

<script type="text/babel">
import FeedEditorGroup from '../../core-ui/editor/EditorGroup';
import UpgradeToProButton from "../advertise/UpgradeToProButton.vue";
import ReviewsEmptyStates from "../../pieces/icons/ReviewsEmptyStates";
import { Plus, Edit, Delete, Search, MoreFilled, Setting } from '@element-plus/icons-vue'
export default {
  name: 'ManagementSettings',
  computed: {
    filteredTableData() {
      if (!this.search) return this.tableData;
      const searchLower = this.search.toLowerCase();
      return this.tableData.filter(row =>
        row.email && row.email.toLowerCase().includes(searchLower)
      );
    },
  },
  data() {
    return {
      fetching: false,
      saving: false,
      dialogFormVisible: false,
      errorMessage: '',
      formLabelWidth: '120px',
      permissions: {},
      editing_manager: {},
      keepFormData : false,
      tableData: [],
      search: '',
      showCustomTooltip: false,
      tooltipStyle: {},
      formRules: {
        email: [
          { required: true, message: 'Please enter a valid email address', trigger: 'blur' },
          { type: 'email', message: 'Please enter a valid email address', trigger: 'blur' }
        ]
      }
    }
  },
  components: {
    UpgradeToProButton,
    FeedEditorGroup,
    ReviewsEmptyStates
  },
  methods: {
    readable(permission) {
      if (this.permissions[permission]) {
        return this.permissions[permission].title;
      }
      return permission;
    },
    validateEmail() {
      if (!this.editing_manager.email || this.editing_manager.email.trim() === '') {
        this.errorMessage = 'Please enter a valid email address';
        return false;
      }
      
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(this.editing_manager.email)) {
        this.errorMessage = 'Please enter a valid email address';
        return false;
      }
      
      this.errorMessage = '';
      return true;
    },
    addManager() {
      if (!this.keepFormData) {
        this.editing_manager = {
          email: '',
          permissions: []
        }
        this.errorMessage = '';
      }
      this.dialogFormVisible = true;
      // Reset form validation state when opening dialog
      this.$nextTick(() => {
        if (this.$refs.managerForm) {
          this.$refs.managerForm.clearValidate();
        }
      });
    },
    fetchManagers() {
      this.fetching = true;
      this.$get('pro/settings/managers')
        .then(response => {
          this.permissions = response.permissions;
          this.tableData = response.managers.data;
        })
        .catch((errors) => {
          this.handleError(errors.responseJSON)
        })
        .always(() => {
          this.fetching = false;
        });
    },
    handleManager() {
      this.errorMessage = '';

      if (!this.editing_manager.email || this.editing_manager.email.trim() === '') {
        this.errorMessage = 'Please enter a valid email address';
        return;
      }
      
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(this.editing_manager.email)) {
        this.errorMessage = 'Please enter a valid email address';
        return;
      }
      
      if (this.editing_manager.permissions.length === 0) {
        this.errorMessage = 'Please select at least one role';
        return;
      }

      this.saving = true;

      let $request;

      if (this.editing_manager.id) {
        $request = this.$put('pro/settings/managers', {
          formData: this.editing_manager
        });
      } else{
        $request = this.$post('pro/settings/managers', {
          formData: this.editing_manager
        });
      }

      $request.then(response => {
            this.handleSuccess(response.message);
            this.fetchManagers();
            this.keepFormData = false;
            this.dialogFormVisible = false;
            this.$refs.managerForm.resetFields();
          })
          .catch((errors) => {
            this.handleError(errors.responseJSON);
            this.keepFormData = true;
          })
          .always(() => {
            this.saving = false;
          });
    },
    initEdit(manager) {
      this.dialogFormVisible = true;
      this.editing_manager = manager;
      this.errorMessage = '';

      this.$nextTick(() => {
        if (this.$refs.managerForm) {
          this.$refs.managerForm.clearValidate();
        }
      });
    },
    closeDeletePopover(index){
      const popover = this.$refs['popover-' + index];
      this.$refs.popover.hide();
      // if (popover && typeof popover.hide === 'function') {
      //   popover.hide();
      // }
    },
    removeManager(manager) {
      this.$del(`pro/settings/managers/${manager.id}`)
          .then(response => {
            this.handleSuccess(response.message);
            this.fetchManagers();
          })
          .catch((errors) => {
            this.handleError(errors);
          });
    },
    getVisiblePermissions(permissions) {
      if (!permissions || permissions.length === 0) return [];
      // Show first 3 permissions, similar to the image
      return permissions.slice(0, 3);
    },
    getHiddenPermissionsCount(permissions) {
      if (!permissions || permissions.length <= 3) return 0;
      return permissions.length - 3;
    },
    getHiddenPermissions(permissions) {
      if (!permissions || permissions.length <= 3) return [];
      return permissions.slice(3);
    },
    isLongPermission(permissionName) {
      // Check if permission name is longer than 20 characters
      return permissionName && permissionName.length > 20;
    },
    showTooltip(managerId, event) {
      this.showCustomTooltip = managerId;
      
      // Find the actual +X span element
      const plusElement = event.currentTarget.querySelector('.wpsr-permission-more');
      const targetRect = plusElement ? plusElement.getBoundingClientRect() : event.target.getBoundingClientRect();
      
      const tooltipWidth = 300;
      
      // Position tooltip directly above the +X count element
      let left = targetRect.left + (targetRect.width / 2) - (tooltipWidth / 2);
      let top = targetRect.top - 8; // Position tooltip 8px above the +X element
      
      // Adjust if tooltip goes off screen horizontally
      if (left < 10) left = 10;
      if (left + tooltipWidth > window.innerWidth - 10) {
        left = window.innerWidth - tooltipWidth - 10;
      }
      
      // If not enough space above, show below the element
      if (top < 10) {
        top = targetRect.bottom + 8;
      }
      
      this.tooltipStyle = {
        left: left + 'px',
        top: top + 'px'
      };
    },
    hideTooltip() {
      this.showCustomTooltip = false;
      this.tooltipStyle = {};
    },
    handleAction(command) {
      const { action, manager } = command;
      
      switch (action) {
        case 'edit':
          this.initEdit(manager);
          break;
        case 'permissions':
          // Open permissions management dialog
          this.initEdit(manager);
          break;
        case 'delete':
          this.removeManager(manager);
          break;
      }
    },
  },
  mounted() {
    if (this.has_pro) {
      this.fetchManagers();
    }
  }
}
</script>
