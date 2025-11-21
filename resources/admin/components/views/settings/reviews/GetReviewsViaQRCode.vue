<template>
  <div class="wpsr-advance-settings-main">
    <div v-if="has_pro && (hasPermission(['administrator']) || hasPermission(['wpsn_manage_qr_codes']) || hasPermission(['wpsn_full_access']))">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="wpsr-general-settings-wrapper">
        <div class="wpsr-settings-container">
          <div class="wpsr-qr-code-table-wrapper">
            <WpsrSkeleton :rows="10" />
          </div>
        </div>
      </div>

      <!-- Content -->
      <div v-else-if="!loading">
        <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
          <h1 class="wpsr-gs-header-title">{{$t('QR Codes')}}</h1>
          <el-button @click="openAddQRcodeDialog" type="success" size="small" class="wpsr_primary_btn wpsr-add-qr-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M5.25 5.25V0.75H6.75V5.25H11.25V6.75H6.75V11.25H5.25V6.75H0.75V5.25H5.25Z" fill="#ffffff"/>
            </svg>
            <span class="wpsr-qr-btn-text">{{$t('QR code')}}</span>
          </el-button>
        </div>
        <div class="wpsr-general-settings-wrapper">
          <div class="wpsr-settings-container">
            <div class="wpsr-qr-code-table-wrapper">
              <!-- Search Header -->
              <div class="wpsr-table-search-header">
                <el-input 
                  v-model="search" 
                  size="default" 
                  placeholder="Type to search QR codes..." 
                  class="wpsr-text-input wpsr-table-search-input"
                  clearable
                >
                  <template #prefix>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" fill="#9CA3AF"/>
                    </svg>
                  </template>
                </el-input>
              </div>

              <!-- Custom Table Design -->
              <div class="wpsr-custom-table">
                <!-- Table Header -->
                <div class="wpsr-table-header wpsr-table-header-qr">
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-name">
                    <span class="wpsr-table-header-text wpsr-paragraph-small">Name</span>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-url">
                    <span class="wpsr-table-header-text wpsr-paragraph-small">URL</span>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-scans">
                    <span class="wpsr-table-header-text wpsr-paragraph-small">Scans</span>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-qrcode">
                    <span class="wpsr-table-header-text wpsr-paragraph-small">QR Code</span>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-actions">
                    <span class="wpsr-table-header-text wpsr-paragraph-small">Actions</span>
                  </div>
                </div>

                <!-- Table Body -->
                <div v-if="tableData && tableData.length" class="wpsr-table-body">
                <div 
                  v-for="(item, index) in filteredTableData" 
                  :key="item.id"
                  class="wpsr-table-row"
                >
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-name">
                    <span class="wpsr-qr-table-cell-content wpsr-qr-name">{{ item.name }}</span>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-url">
                    <a :href="getURL(item)" target="_blank" class="wpsr-qr-url">
                      {{ truncateURL(getURL(item)) }}
                    </a>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-scans">
                    <span class="wpsr-qr-table-cell-content wpsr-scan-count">{{ item.scan_counter || 0 }}</span>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-qrcode">
                    <div class="wpsr-qr-code-preview">
                      <qrcode-vue :value="item.qrcode_url" size="60" level="H"></qrcode-vue>
                    </div>
                  </div>
                  <div class="wpsr-qr-table-cell wpsr-qr-table-cell-actions">
                    <div class="wpsr-table-actions">
                      <el-button 
                        size="small" 
                        type="text" 
                        @click="openEditDialog(item)"
                        class="wpsr-edit-btn"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="16" viewBox="0 0 14 16" fill="none">
                          <path d="M2.8105 10.9996L10.417 3.39307L9.3565 2.33257L1.75 9.93908V10.9996H2.8105ZM3.43225 12.4996H0.25V9.31733L8.82625 0.741074C8.9669 0.600471 9.15763 0.521484 9.3565 0.521484C9.55537 0.521484 9.7461 0.600471 9.88675 0.741074L12.0085 2.86282C12.1491 3.00347 12.2281 3.1942 12.2281 3.39307C12.2281 3.59195 12.1491 3.78268 12.0085 3.92332L3.43225 12.4996ZM0.25 13.9996H13.75V15.4996H0.25V13.9996Z" fill="#525866"/>
                        </svg>
                      </el-button>
                      <el-button 
                        size="small" 
                        type="text" 
                        @click="beforeDeleteHandler(item)"
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

                <div v-else-if="!loading && (!tableData || !tableData.length)">
                  <div class="wpsr-empty-state">
                    <div class="wpsr-empty-content">
                      <span class="wpsr-empty-state-icon">
                        <ReviewsEmptyStates/>
                      </span>
                      <h1>{{$t('No QR Code Found Yet')}}</h1>
                      <p>
                        {{ $t('Create a QR code that makes it easy for customers to leave reviews!') }}
                      </p>
                      <el-button class="wpsr_primary_btn" type="primary" size="default" @click="openAddQRcodeDialog">
                        {{ $t('Add a QR Code') }}
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
        :title="dialogTitle"
        v-model="dialogFormVisible"
        :append-to-body="true"
        :before-close="closeDialog"
        class="wpsr-modal wpsr-connection-modal wpsr-qr-code-modal"
      >
        <div class="wpsr-form-section">
            <div class="wpsr-field-group">
              <label class="wpsr-field-label">
                Name
              </label>
              <el-input
                  v-model="qrCodeInfo.name"
                  type="text"
                  autocomplete="off"
                  @input="validateName"
                  :placeholder="$t('Enter QR code name')"
                  class="wpsr-input-default wpsr-border-all-around"
                ></el-input>
              <div v-if="errorMessageForName" class="wpsr-error-message">
                <p v-html="errorMessageForName"></p>
              </div>
              <div class="wpsr-helper-text">
                <svg class="wpsr-management-info-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M6 12C2.6862 12 0 9.3138 0 6C0 2.6862 2.6862 0 6 0C9.3138 0 12 2.6862 12 6C12 9.3138 9.3138 12 6 12ZM5.4 5.4V9H6.6V5.4H5.4ZM5.4 3V4.2H6.6V3H5.4Z" fill="#99A0AE"/>
                </svg>
                <span>{{$t('A name for the QR code.')}}</span>
              </div>
            </div>
            <div class="wpsr-field-group">
              <label class="wpsr-field-label">
                Business URL
              </label>
              <el-select
                class="wpsr-qr-code-select wpsr-modal-select wpsr-select-input-field"
                v-model="qrCodeInfo.collection_form"
                placeholder="Select"
                size="large"
                filterable
                @input="validateCollectionForm"
              >
                <el-option
                  v-for="(platform, index) in availablePlatforms"
                  :key="index"
                  :label="getReviewCollectionFormLabel(platform.name, platform.platform_name)"
                  :value="platform.url"
                />
                <el-option key="custom-url" label="Custom URL" value="custom-url" />
              </el-select>
              <div v-if="errorMessageForCollectionForm" class="wpsr-error-message">
                <p v-html="errorMessageForCollectionForm"></p>
              </div>
              <div class="wpsr-helper-text">
                <svg class="wpsr-management-info-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M6 12C2.6862 12 0 9.3138 0 6C0 2.6862 2.6862 0 6 0C9.3138 0 12 2.6862 12 6C12 9.3138 9.3138 12 6 12ZM5.4 5.4V9H6.6V5.4H5.4ZM5.4 3V4.2H6.6V3H5.4Z" fill="#99A0AE"/>
                </svg>
                <span>{{$t('Select the URL you need to redirect to.')}}</span>
              </div>
            </div>
            <div class="wpsr-field-group" v-if="qrCodeInfo.collection_form === 'custom-url'">
              <label class="wpsr-field-label">
                Custom URL
              </label>
              <el-input
                v-model="qrCodeInfo.custom_url"
                type="text"
                autocomplete="off"
                @input="validateCustomURL"
                :placeholder="$t('https://example.com')"
                class="wpsr-input-default wpsr-border-all-around"
              ></el-input>
              <div v-if="errorMessageForCustomURL" class="wpsr-error-message">
                <p  v-html="errorMessageForCustomURL"></p>
              </div>
              <div class="wpsr-helper-text">
                <svg class="wpsr-management-info-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M6 12C2.6862 12 0 9.3138 0 6C0 2.6862 2.6862 0 6 0C9.3138 0 12 2.6862 12 6C12 9.3138 9.3138 12 6 12ZM5.4 5.4V9H6.6V5.4H5.4ZM5.4 3V4.2H6.6V3H5.4Z" fill="#99A0AE"/>
                </svg>
                <span>{{$t('Enter a valid URL starting with http:// or https://')}}</span>
              </div>
            </div>
            <div class="wpsr-field-group">
              <el-form-item>
              <el-button :disabled="isGenerateButtonDisabled" type="primary" @click="generateQRCode" class="wpsr_primary_btn">
                {{$t('Generate')}}
              </el-button>
              </el-form-item>
            </div>
            
            <div class="wpsr-qr-code-wrapper" v-if="qrCodeInfo.id">
              <div class="">
                <h3 class="wpsr-qr-code-title">{{$t('QR Code')}}</h3>
                <p class="wpsr-qr-code-description">{{$t('Download the QR code and distribute as needed.')}}</p>
              </div>
              <span class="wpsr-qr-code">
                <qrcode-vue class="wpsr-qr-code-image" ref="qrCode" :value="qrCodeInfo.qrcode_url" :size="dialogQrCodeSize" level="H"></qrcode-vue>
                <span class="wpsr-qr-code-download-wrapper">
                  <el-button size="small" type="default" @click="downloadQRCode('svg')">
                    <el-icon><Download /></el-icon>
                    SVG
                  </el-button>
                  <el-button size="small" type="default" @click="downloadQRCode('png')">
                    <el-icon><Download /></el-icon>
                    PNG
                  </el-button>
                </span>
              </span>
            </div>
        </div>

        <template #footer>
          <span class="dialog-footer">
            <el-button class="wpsr_default_btn" @click="dialogFormVisible = false">{{$t('Cancel')}}</el-button>
          </span>
        </template>
      </el-dialog>

      <!-- Delete Confirmation Modal -->
      <el-dialog
        v-model="showDeleteModal"
        width="30%"
        class="wpsr-confirmation-modal"
      >
        <h4>{{$t('Delete QR Code')}}</h4>
        <div class="wpsr-delete-content">
          <p>Are you sure you want to delete this QR code? This action cannot be undone.</p>
        </div>
        <template #footer>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" @click="showDeleteModal = false">Cancel</el-button>
            <el-button class="wpsr_primary_btn" type="danger" @click="deleteQRCode">Delete</el-button>
          </div>
        </template>
      </el-dialog>
    </div>
    <div v-if="!has_pro" >
      <div class="wpsr-managers-upgrade-to-pro" v-if="!has_pro">
        <img :src="assets_url+'/images/promotion/qrcode-promo.png'" alt="">
        <div class="wpsr-managers-upgrade-message">
          <h2>Upgrade to Pro and turn every scan into a 5-star opportunity!</h2>
          <p>Create branded QR codes that lead users directly to your review forms. Track scan counts, analyze campaign performance, and effortlessly build trust and credibility—online and offline.</p>
          <UpgradeToProButton/>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import UpgradeToProButton from "../../advertise/UpgradeToProButton";
import QrcodeVue from "qrcode.vue";
import { reviewsMixin } from "../../../../mixins/reviewsMixin";
import { Edit, Delete, Download, Plus } from '@element-plus/icons-vue';
import ReviewsEmptyStates from "../../../pieces/icons/ReviewsEmptyStates";

export default {
  name: "GetReviewsViaQRCode",
  mixins: [reviewsMixin],
  data() {
    return {
      loading: false,
      dialogFormVisible: false,
      errorMessageForName: "",
      errorMessageForCollectionForm: "",
      errorMessageForCustomURL: "",
      formLabelWidth: "180px",
      qrCodeInfo: {},
      tableData: [],
      availablePlatforms: [],
      search: "",
      dialogQrCodeSize: 100,
      dirty: false,
      showDeleteModal: false,
      deletingItem: null,
    };
  },
  computed: {
    filteredTableData() {
      if (!this.tableData || !Array.isArray(this.tableData)) {
        return [];
      }
      return this.search
        ? this.tableData.filter((item) =>
            item.name.toLowerCase().includes(this.search.toLowerCase())
          )
        : this.tableData;
    },
    dialogTitle() {
      return this.qrCodeInfo.id
        ? this.$t("Edit QR code")
        : this.$t("Add New QR code");
    },
    isGenerateButtonDisabled() {
      if (!this.dirty) return true;
      if (!this.qrCodeInfo.name || !this.qrCodeInfo.collection_form) return true;
      if (
        this.qrCodeInfo.collection_form === "custom-url" &&
        !this.qrCodeInfo.custom_url
      )
        return true;
      return false;
    },
  },
  components: {
    UpgradeToProButton,
    QrcodeVue,
    Edit,
    Delete,
    Download,
    Plus,
    ReviewsEmptyStates
  },
  methods: {
    closeDeletePopover(rowId){
      const popoverRef = this.$refs['delete-popover-' + rowId];
      if (popoverRef && typeof popoverRef.doClose === 'function') {
        popoverRef.doClose();
      }
    },
    validateName() {
      this.dirty = true;
      if (!this.qrCodeInfo.name || this.qrCodeInfo.name.trim() === '') {
        this.errorMessageForName = this.$t("Please enter a valid name");
        return false;
      }
      this.errorMessageForName = '';
      return true;
    },
    validateCollectionForm() {
      this.dirty = true;
      if (!this.qrCodeInfo.collection_form) {
        this.errorMessageForCollectionForm = this.$t("Please select a review collection form");
        return false;
      }
      this.errorMessageForCollectionForm = '';
      return true;
    },
    validateCustomURL() {
      this.dirty = true;
      if (this.qrCodeInfo.collection_form === 'custom-url') {
        if (!this.qrCodeInfo.custom_url || this.qrCodeInfo.custom_url.trim() === '') {
          this.errorMessageForCustomURL = this.$t("Please enter a valid URL");
          return false;
        }
        
        // Validate URL format
        const urlRegex = /^https?:\/\/.+/;
        if (!urlRegex.test(this.qrCodeInfo.custom_url)) {
          this.errorMessageForCustomURL = this.$t("Please enter a valid URL starting with http:// or https://");
          return false;
        }
      }
      this.errorMessageForCustomURL = '';
      return true;
    },
    markAsDirty() {
      this.dirty = true;
    },
    getURL(row) {
      return row.url !== "custom-url" ? row.url : row.custom_url;
    },
    truncateURL(url) {
      if (!url) return '';
      return url.length > 20 ? url.substring(0, 20) + "..." : url;
    },
    openAddQRcodeDialog() {
      this.qrCodeInfo = { name: "", collection_form: "", custom_url: "" };
      this.errorMessageForName = "";
      this.errorMessageForCollectionForm = "";
      this.errorMessageForCustomURL = "";
      this.dirty = false;
      this.dialogFormVisible = true;
    },
    openEditDialog(qrCode) {
      this.qrCodeInfo = { 
        ...qrCode,
        collection_form: qrCode.url !== "custom-url" ? qrCode.url : "custom-url",
        
      };
      this.errorMessageForName = "";
      this.errorMessageForCollectionForm = "";
      this.errorMessageForCustomURL = "";
      this.dirty = false;
      this.dialogFormVisible = true;
    },
    closeDialog() {
      this.dialogFormVisible = false;
    },
    generateQRCode() {
      if (!this.validateForm()) return;

      const request = this.qrCodeInfo.id
        ? this.$put(
            `pro/settings/get-reviews/qr-code/${this.qrCodeInfo.id}`,
            this.qrCodeInfo
          )
        : this.$post("pro/settings/get-reviews/qr-code", this.qrCodeInfo);

      this.loading = true;
      request
        .then((response) => {
          this.qrCodeInfo = { 
            ...response.data,
            collection_form: response.data.url !== "custom-url" ? response.data.url : "custom-url",
          
          };
          this.dirty = false;
          this.fetchQrCodes();
        })
        .catch((error) => this.handleError(error.responseJSON))
        .always(() => (this.loading = false));
    },
    validateForm() {
      if (!this.qrCodeInfo.name) {
        this.errorMessageForName = this.$t("Please enter a valid name");
        return false;
      }
      if (!this.qrCodeInfo.collection_form) {
        this.errorMessageForCollectionForm = this.$t(
          "Please select a review collection form"
        );
        return false;
      }
      if (
        this.qrCodeInfo.collection_form === "custom-url" &&
        !this.qrCodeInfo.custom_url
      ) {
        this.errorMessageForCustomURL = this.$t("Please enter a valid URL");
        return false;
      }
      return true;
    },
    downloadQRCode(format) {
      const canvas = this.$refs.qrCode.$el.querySelector("canvas");
      const dataURL =
        format === "svg"
          ? canvas.toDataURL("image/svg+xml")
          : canvas.toDataURL("image/png");
      const blob = this.dataURLtoBlob(dataURL);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `qrcode.${format}`;
      a.click();
      URL.revokeObjectURL(url);
    },
    dataURLtoBlob(dataURL) {
      const [header, data] = dataURL.split(",");
      const mime = header.match(/:(.*?);/)[1];
      const binary = atob(data);
      const array = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) {
        array[i] = binary.charCodeAt(i);
      }
      return new Blob([array], { type: mime });
    },
    beforeDeleteHandler(qrCode) {
      this.deletingItem = qrCode;
      this.showDeleteModal = true;
    },
    deleteQRCode() {
      if (!this.deletingItem) return;
      
      this.$del(`pro/settings/get-reviews/qr-code/${this.deletingItem.id}`)
        .then(() => {
          this.showDeleteModal = false;
          this.deletingItem = null;
          this.fetchQrCodes();
        })
       .catch((error) => this.handleError(error));
    },
    fetchQrCodes() {
      this.loading = true;
      this.$get("pro/settings/get-reviews/qr-code")
        .then((response) => (this.tableData = response.data || []))
        .catch((error) => {
          this.tableData = [];
          this.handleError(error.responseJSON);
        })
        .always(() => (this.loading = false));
    },
    getReviewCollectionFormLabel(businessName, platformName) {
      if (!businessName || !platformName) {
        return '';
      }
      if (businessName.length > 13) {
        businessName = businessName.substring(0, 20) + "..." + businessName.slice(-2);
      }
      return `${businessName} (${this.ucFirst(platformName)})`;
    },
  },
  mounted() {
    if(!this.has_pro){
      return;
    }

    this.fetchQrCodes();
    this.$get("pro/settings/get-reviews/get-review-collection-platforms")
      .then((response) => (
        
        this.availablePlatforms = response.data.filter(function (platform) {
          return !platform.platform_name.includes('testimonial');
        })
      
      ))
      .catch((error) => this.handleError(error.responseJSON));
  },
};
</script>