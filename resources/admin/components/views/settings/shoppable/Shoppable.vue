<template>
  <div>
    <div class="wpsr-d-flex wpsr-jc-between wpsr-flex-align-center wpsr-shoppable-wrapper" :class="has_pro ? 'wpsr-gs-header': ''"
      v-if="hasPermission(['wpsn_shoppable_settings', 'wpsn_full_access'])">
      <h1 v-if="has_item && has_pro" class="wpsr-gs-header-title">Shoppable by Hashtags</h1>
        <el-button v-if="!show_shoppable_popup && has_item && has_pro" type="primary" size="default" @click="openShoppableModal" class="wpsr_primary_btn wpsr-add-shoppable-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
            <path d="M5.25 5.25V0.75H6.75V5.25H11.25V6.75H6.75V11.25H5.25V6.75H0.75V5.25H5.25Z" fill="#ffffff"/>
          </svg>
          <span class="wpsr-shoppable-btn-text">{{ $t('Shoppable Hashtag') }}</span>
        </el-button>
      </div>

    <div :class="has_pro ? 'wpsr-general-settings-wrapper' : ''">
      <div class="wpsr-settings-container">
        <div v-if="has_pro && has_item" class="wpsr-settings-header">
          <h2 class="wpsr-shoppable-title">{{ $t('Shoppable by Hashtags') }}</h2>
          <p class="wpsr-shoppable-description">{{ $t('Automatically convert your Instagram posts into shoppable content. Add other managers giving specific permissions.') }}</p>
        </div>

        <!-- Content Area -->
        <div class="wpsr-shoppable-content">
          <!-- Loading Skeleton -->
          <div v-if="loading && !show_shoppable_popup" class="wpsr-shoppable-loading">
            <WpsrSkeleton :rows="15" />
          </div>

          <!-- Shoppable list - only show when not loading and has items -->
          <div v-else-if="!loading && has_item && has_pro" class="wpsr-shoppable-list-container">
            <div class="wpsr-shoppable-table">
              <!-- Table Header -->
              <div class="wpsr-shoppable-table-header">
                <div class="wpsr-shoppable-table-cell wpsr-shoppable-table-cell-hashtags">
                  <span class="wpsr-shoppable-table-header-text">Hashtags</span>
                </div>
                <div class="wpsr-shoppable-table-cell wpsr-shoppable-table-cell-source">
                  <span class="wpsr-shoppable-table-header-text">Source</span>
                </div>
                <div class="wpsr-shoppable-table-cell wpsr-shoppable-table-cell-actions">
                  <!-- Actions column header -->
          </div>
        </div>
  
              <!-- Table Body -->
              <div class="wpsr-shoppable-table-body">
                <div v-for="(automation, parentIndex) in shoppable_settings" :key="parentIndex">
                  <div 
                    v-for="(item, index) in automation" 
                    :key="index"
                    class="wpsr-shoppable-table-row"
                    :class="[
                      ((!item.hashtags && errors.has('hashtags') || ((item.source_type !== 'custom_url' && !item.url_settings.id && errors.has('id')) || (item.source_type === 'custom_url' && !item.url_settings.url && errors.has('id'))))) ? 'wpsr-shoppable-row-error' : ''
                    ]"
                  >
                    <div class="wpsr-shoppable-table-cell wpsr-shoppable-table-cell-hashtags">
                      <span class="wpsr-shoppable-hashtags">
                        {{ item.hashtags ? item.hashtags : 'No hashtags' }}
                      </span>
                    </div>
                    <div class="wpsr-shoppable-table-cell wpsr-shoppable-table-cell-source">
                      <span class="wpsr-shoppable-source">
                        <span v-if="item.source_type !== 'custom_url'">
                          {{ $trimWords(item.url_settings.url_title, 5, true) }}
                          ({{ ucFirst(item.source_type) }})
                        </span>
                        <span v-else>{{ $t('Custom URL') }}</span>
                      </span>
                    </div>
                    <div class="wpsr-shoppable-table-cell wpsr-shoppable-table-cell-actions">
                      <div class="wpsr-table-actions" v-if="item.hashtags">
                        <el-button 
                          type="default" 
                          size="small" 
                          @click="editHandler(index, item)"
                          class="wpsr-edit-btn"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="16" viewBox="0 0 14 16" fill="none">
                            <path d="M2.8105 10.9996L10.417 3.39307L9.3565 2.33257L1.75 9.93908V10.9996H2.8105ZM3.43225 12.4996H0.25V9.31733L8.82625 0.741074C8.9669 0.600471 9.15763 0.521484 9.3565 0.521484C9.55537 0.521484 9.7461 0.600471 9.88675 0.741074L12.0085 2.86282C12.1491 3.00347 12.2281 3.1942 12.2281 3.39307C12.2281 3.59195 12.1491 3.78268 12.0085 3.92332L3.43225 12.4996ZM0.25 13.9996H13.75V15.4996H0.25V13.9996Z" fill="#525866"/>
                          </svg>
                        </el-button>
                        <el-button 
                          type="default" 
                          size="small" 
                          @click="beforeDeleteHandler(index, item)"
                          class="wpsr-delete-btn"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M11.75 3.5H15.5V5H14V14.75C14 14.9489 13.921 15.1397 13.7803 15.2803C13.6397 15.421 13.4489 15.5 13.25 15.5H2.75C2.55109 15.5 2.36032 15.421 2.21967 15.2803C2.07902 15.1397 2 14.9489 2 14.75V5H0.5V3.5H4.25V1.25C4.25 1.05109 4.32902 0.860322 4.46967 0.71967C4.61032 0.579018 4.80109 0.5 5 0.5H11C11.1989 0.5 11.3897 0.579018 11.5303 0.71967C11.671 0.860322 11.75 1.05109 11.75 1.25V3.5ZM12.5 5H3.5V14H12.5V5ZM5.75 7.25H7.25V11.75H5.75V7.25ZM8.75 7.25H10.25V11.75H8.75V7.25ZM5.75 2V3.5H10.25V2H5.75Z" fill="#525866"/>
                          </svg>
                        </el-button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </div>
        
        <div class="wpsr-shoppable-upgrade-to-pro" v-if="!has_pro">
          <img :src="assets_url + '/images/promotion/shoppable-feed-promo.png'" alt="">
          <div class="wpsr-shoppable-upgrade-message">
            <h2>Maximize profit & conversions from your Instagram</h2>
            <p>Easily link Instagram posts to your landing pages, blog posts, products, and more. Instagram shoppable by
              hashtags increases <strong>user engagement</strong>, drives more <strong>traffic</strong>, & boosts
              <strong>conversions</strong> through user-generated content.</p>
            <UpgradeToProButton />
          </div>
        </div>

        <!-- Welcome screen - only show when not loading and no items -->
        <div v-else-if="!loading && !has_item && has_pro" class="wpsr-welcome-screen-message">
          <h2>Maximize profit & conversions from your Instagram</h2>
          <div class="wpsr-wpsr-welcome-screen-message-inner">
            <p>Easily link Instagram posts to your landing pages, blog posts, products, and more. Instagram shoppable by
              hashtags increases <strong>user engagement</strong>, drives more <strong>traffic</strong>, & boosts
              <strong>conversions</strong> through user-generated content.</p>
              <p>Let's say you have posted some pictures using a hashtag (ex., <strong>#feelgood</strong>) to promote a
              brand or product. Do you know you can showcase photos or posts from Instagram with this hashtag & directly
              link this hashtag to your product page.</p>
            <p>Now, <strong>WP Social Ninja</strong> can find every new Instagram photo with this hashtag & automatically
              link to the dedicated product page.</p>
            <p><strong>Turn followers into loyal customers!</strong></p>
            <el-button class="wpsr_primary_btn wpsr-mt-1" type="primary" size="default" @click="openShoppableModal">
              {{ $t('Add your first shoppable hashtag') }}
            </el-button>
          </div>
        </div>
      </div>

        <!-- Add/Edit Shoppable Hashtag Modal -->
        <el-dialog 
          :title="editShoppableItem ? 'Edit Shoppable Hashtags' : 'Add Shoppable Hashtags'"
          v-model="show_shoppable_popup"
          :append-to-body="true"
          :before-close="closeShoppableModal" 
          width="500px" 
          class="wpsr-modal wpsr-connection-modal wpsr-shoppable-modal"
        >
           <div class="wpsr-modal-field-group wpsr-field-group">
             <!-- Hashtags Field -->
             <div class="wpsr-modal-field-group">
               <label class="wpsr-field-label">
                 Automatically promote posts with any of these hashtags
                 <el-tooltip
                    :raw-content="true"
                    effect="dark"
                    placement="top-start"
                    content="Separate multiple hashtags using commas. ex: #hashtag1, #hashtag2."
                >
                    <el-icon ><InfoFilled /></el-icon>
                </el-tooltip>
               </label>
               <el-input 
                 type="text" 
                 v-model="shoppable_fields.hashtags" 
                 size="default"
                 placeholder="example: #hashtag1,#hashtag2"
                 class="wpsr-modal-input wpsr-input-default wpsr-text-input wpsr-input-default wpsr-border-all-around"
               />
               <error-view v-if="!shoppable_fields.hashtags" :errors="errors" :field="'hashtags'" />
             </div>

             <!-- Select Source Field -->
             <div class="wpsr-modal-field-group">
               <label class="wpsr-field-label">Select Source</label>
               <el-select 
                 v-model="shoppable_fields.source_type"  
                 placeholder="Select" 
                 size="large"
                 @change="onSourceChange(shoppable_fields.source_type)"
                 class="wpsr-modal-select wpsr-select-input-field"
               >
                 <el-option v-for="(item, index) in post_types" :key="index" :label="item.title" :value="item.name">
                 </el-option>
               </el-select>
             </div>

             <!-- URL Fields -->
             <div class="wpsr-shoppable-url-fields" v-if="shoppable_fields.source_type.length">
               <div class="wpsr-modal-field-group">
                 <label class="wpsr-field-label">
                   <span v-if="shoppable_fields.source_type === 'custom_url'">URL</span>
                   <span v-else>Link to</span>
                 </label>
                 <el-select 
                   v-if="shoppable_fields.source_type !== 'custom_url'"
                   v-model="shoppable_fields.url_settings.id" 
                   size="large" 
                   allow-create
                   default-first-option 
                   placeholder="Select or start typing..."
                   class="wpsr-qr-code-select wpsr-modal-select wpsr-select-input-field"
                 >
                   <el-option v-for="(item, index) in posts" :key="index" :label="item.title" :value="item.id">
                   </el-option>
                 </el-select>
                 <error-view v-if="!shoppable_fields.url_settings.id" :errors="errors" :field="'id'" />
                 <el-input 
                   v-if="shoppable_fields.source_type === 'custom_url'" 
                   type="text" 
                   size="default"
                   placeholder="Enter your URL" 
                   v-model="shoppable_fields.url_settings.url"
                   class="wpsr-modal-input wpsr-text-input wpsr-input-default wpsr-border-all-around"
                 />
                 <error-view v-if="!shoppable_fields.url_settings.url" :errors="errors" :field="'url'" />
               </div>

               <div class="wpsr-modal-field-group">
                 <el-checkbox v-model="shoppable_fields.url_settings.open_in_new_tab" class="wpsr-modal-checkbox">
                   Open in a new tab
                 </el-checkbox>
               </div>
             </div>

             <!-- Button Text Field -->
             <div class="wpsr-modal-field-group">
               <label class="wpsr-field-label">Button Text</label>
               <el-input 
                 placeholder="Button Text" 
                 v-model="shoppable_fields.url_settings.text" 
                 size="default"
                 class="wpsr-modal-input wpsr-input-default wpsr-text-input wpsr-input-default wpsr-border-all-around"
               />
             </div>
           </div>
          
          <template #footer>
            <div class="dialog-footer">
              <el-button @click="closeShoppableModal">{{ $t('Cancel') }}</el-button>
              <el-button type="primary" @click="saveSettings">
                {{ editShoppableItem ? $t('Update') : $t('Create') }}
              </el-button>
            </div>
          </template>
        </el-dialog>
  
        <!--Delete form Confirmation Modal-->
        <el-dialog
          v-model="show_delete_modal"
          :before-close="handleDeleteClose" 
          width="30%"
          class="wpsr-confirmation-modal"
        >
          <div class="wpsr-delete-content">
            <p>Are you sure, you want to delete this hashtag promotion?</p>
          </div>
          <template #footer>
            <div class="dialog-footer wpsr-d-flex wpsr-jc-end wpsr-mt-20">
              <el-button class="wpsr_default_btn" @click="show_delete_modal = false">Cancel</el-button>
              <el-button class="wpsr_primary_btn" type="danger" @click="deleteItem()">Confirm</el-button>
            </div>
          </template>
        </el-dialog>
      </div>
    </div>
    
    <PermissionsNotification v-if="!hasPermission(['wpsn_shoppable_settings', 'wpsn_full_access'])" />
  </div>
</template>

<script type="text/babel">
import Errors from "../../../../errors/Errors";
import ErrorView from '../../../../errors/errorView';
import UpgradeToProButton from '../../advertise/UpgradeToProButton';
import PermissionsNotification from '../../advertise/PermissionsNotification';

let fields = {
  'platform': 'instagram',
  'source_type': 'custom_url',
  'hashtags': '',
  'url_settings': {
    'url': '',
    'url_title': '',
    'id': null,
    'open_in_new_tab': true,
    'text': 'Buy Now'
  }
};

export default {
  name: 'Shoppable',
  components: {
    PermissionsNotification,
    ErrorView,
    UpgradeToProButton
  },
  data() {
    return {
      loading: false,
      save: false,
      sidebarLoading: false,
      errors: new Errors(),
      message: '',
      shoppable_settings: [
        {
          'instagram': [],
          'facebook': []
        }
      ],
      show_shoppable_popup: false,
      shoppable_fields: { ...fields },
      posts: [],
      postType: 'post',
      post_types: [],
      post_type_default: {
        'name': 'custom_url',
        'title': 'Custom URL'
      },
      platforms: {
        'instagram': 'Instagram',
        'facebook': 'Facebook'
      },
      show_delete_modal: false,
      selected_item: [],
      deletingItem: [],
      selected_index: null,
      editShoppableItem: false,
      has_item: false
    }
  },
  methods: {
    openShoppableModal() {
      this.show_shoppable_popup = true;
      let that = this;
      this.sidebarLoading = true;
      setTimeout(function () {
        that.sidebarLoading = false;
      }, 500);
      this.has_item = true;
      this.errors.record({});
      this.shoppable_fields = JSON.parse(JSON.stringify(fields));
      this.selected_item = JSON.parse(JSON.stringify(fields));
      this.selected_index = 0;
      this.shoppable_settings[this.shoppable_fields.platform].unshift(this.shoppable_fields);
    },
    saveSettings() {
      if (this.shoppable_fields.hashtags && !this.checkValidHashtag(this.shoppable_fields.hashtags)) {
        this.message = 'Please put a valid hashtag';
        this.handleError(this.message);
        this.loading = false;
        return false;
      } else {
        this.message = '';
      }

      // Add loading state
      this.loading = true;

      this.$put('shoppable', {
        settings: this.shoppable_settings,
      })
        .then(response => {
          if (response) {
            this.handleSuccess(response.message);
            this.show_shoppable_popup = false;
            this.getSettings();
          }
        })
        .catch(errors => {
          this.handleError(errors);
        })
        .then(() => {
          // Make sure to turn off loading state
          this.loading = false;
        });
    },
    getSettings(isOnChange = false) {
      if (!isOnChange) {
        this.loading = true;
      }
      this.$get('shoppable', {
        postType: this.postType
      }).then(response => {
        if (response) {
          if (!isOnChange) {
            this.shoppable_settings = response.settings;
          }
          this.has_item = response.has_item;
          if (isOnChange) {
            this.has_item = true;
          }
          this.posts = response.posts.splice(1);
          this.post_types = response.post_types;
          this.post_types.push(this.post_type_default);

          if (this.selected_item && this.selected_item.platform) {
            this.shoppable_fields = this.shoppable_settings[this.selected_item.platform][this.selected_index];
          }
        }
      }).catch(errors => {
        this.handleError(errors);
      }).always(() => {
        if (!isOnChange) {
          this.loading = false;
        }
      });
    },
    closeShoppableModal() {
      if (!this.shoppable_settings[this.shoppable_fields.platform][0].hashtags) {
        this.shoppable_settings[this.shoppable_fields.platform].shift()
      }
      this.show_shoppable_popup = false;
      this.editShoppableItem = false;
    },
    editHandler(index, item) {
      this.editShoppableItem = true;
      this.show_shoppable_popup = true;
      this.selected_index = index;
      this.selected_item = { ...item };
      this.shoppable_fields = this.shoppable_settings[item.platform][index];
      this.postType = item.source_type;
      this.getPostsByPostType(item.source_type);
    },
    getPostsByPostType(source_type) {
      this.sidebarLoading = true;
      let tempId = this.shoppable_fields.url_settings.id;
      this.shoppable_fields.url_settings.id = null;
      let that = this;
      this.$get(`shoppable/posts`, {
        postType: source_type
      }).then(response => {
        if (response) {
          this.posts = response.posts.splice(1);
          setTimeout(function () {
            that.shoppable_fields.url_settings.id = tempId;
          }, 50);
        }
      }).catch(errors => {
        this.handleError(errors);
      }).always(() => {
        setTimeout(function () {
          that.sidebarLoading = false;
        }, 100);
      });
    },
    beforeDeleteHandler(index, item) {
      this.selected_index = index;
      this.selected_item = item;
      this.show_delete_modal = true;
      this.deletingItem = item;
    },
    deleteItem() {
      this.shoppable_settings[this.selected_item.platform].splice(this.selected_index, 1);
      this.$del('shoppable', {
        settings: this.shoppable_settings,
      }).then(response => {
        if (response) {
          this.handleSuccess(response.message);
          this.getSettings();
          this.show_delete_modal = false;
          this.show_shoppable_popup = false;
        }
      })
        .catch((errors) => {
          this.handleError(errors)
        })
        .always(() => {
        });
    },
    handleDeleteClose() {
      this.show_delete_modal = false;
      this.deletingItem = []
    },
    onSourceChange(postType) {
      this.shoppable_fields.url_settings.id = null;
      this.shoppable_fields.url_settings.url = '';
      this.postType = postType;
      this.getSettings(true);
    },
    onPlatformChange(fields) {
      this.shoppable_settings[this.selected_item.platform].splice(this.selected_index, 1);
      this.shoppable_settings[fields.platform].unshift(fields);
      this.selected_index = 0;
      this.selected_item = { ...fields };
    },
    checkValidHashtag(tags) {
      let hashtags = tags.split(',');
      const isValid = hashtags.every(tag => {
        if (tag && tag.includes('#')) {
          return true;
        } else {
          return false
        }
      });
      return isValid;
    },
  },
  mounted() {
    this.getSettings();
    this.errors.record({});
  }
}
</script>