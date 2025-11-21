<template>
  <div class="wpsr-general-settings-wrapper">
    <div class="wpsr-tools-export-import">
      <el-row class="wpsr-setting-header">
        <el-col :md="24">
          <h3>Import Data</h3>
          <p>
            Select the Social Ninja export file (.csv for Reviews and Testimonials, .json for Templates) you would like to import. When you click the Import button below,
            Social Ninja will import the data. To import a template file, make sure to select "Templates" from the dropdown and upload a JSON file.
          </p>
        </el-col>
      </el-row>

      <div class="wpsr-feed-settings-content">
        <!-- Select Import Type -->
        <div class="wpsr-setting-row-no-border">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Select Import Type') }}</h3>
            <p class="wpsr-setting-description">
              Choose the data type to upload a Social Ninja export file from your computer.
            </p>
          </div>
          <div class="wpsr-setting-right">
            <el-select v-model="selectedImport" placeholder="Select Import Type" class="wpsr-text-input">
              <el-option label="Custom Reviews (Deprecated Soon)" value="custom"></el-option>
              <el-option label="Custom Sources Reviews" value="custom_sources"></el-option>
              <el-option label="Testimonials" value="testimonial"></el-option>
              <el-option label="Feed and Reviews Template" value="template"></el-option>
              <el-option label="Notification Popups Template" value="notifications"></el-option>
              <el-option label="Chat Widgets" value="chat-widget"></el-option>
              <el-option label="Judge.me Reviews" value="judge-me"></el-option>
            </el-select>
          </div>
        </div>
          <!--Select File-->
          <div class="wpsr-setting-row-no-border">
            <div class="wpsr-setting-left">
              <h3 class="wpsr-setting-title">{{ $t('Select File') }}</h3>
              <p class="wpsr-setting-description">
                Upload your export file from Social Ninja or other supported platforms.
              </p>
            </div>
            <div class="wpsr-setting-right">
              <input :disabled="!has_pro ? true : false" type="file" class="wpsr-file-upload" id="fileUpload" @change="hasFile" @click="clear">
            </div>
          </div>

          <!-- Source for Import -->
          <div v-if="selectedImport === 'custom_sources'" class="wpsr-setting-row-no-border">
            <div class="wpsr-setting-left">
              <h3 class="wpsr-setting-title">{{ $t('Where would you like to import the reviews?') }}</h3>
              <p class="wpsr-setting-description">
                Choose the source to which you want to import the reviews.
              </p>
            </div>
            <div class="wpsr-setting-right">
              <el-select
                v-model="selectedSourceId"
                placeholder="Select a source"
                clearable
                filterable
                remote
                :remote-method="searchCustomSources"
                class="wpsr-text-input"
                style="width: 100%"
                :loading="loadingSources"
              >
                <el-option
                  v-for="source in customSources"
                  :key="source.id"
                  :label="`${source.title || source.name} (ID: ${source.form_id || source.id})`"
                  :value="source.form_id || source.id"
                >
                  <span style="float: left">{{ source.title || source.name }}</span>
                  <span style="float: right; color: #8492a6; font-size: 13px">Source ID: {{ source.form_id || source.id }}</span>
                </el-option>
              </el-select>
            </div>
          </div>

          <div class="wpsr-setting-row-no-border wpsr-setting-no-border-action-btn">
            <div class="wpsr-setting-left"></div>
            <div class="wpsr-setting-right">
              <el-button :disabled="(!has_pro || !hasData) ? true : false" size="default" class="pull-left wpsr_primary_btn wpsr-tools-btn" type="success" @click="upload" :loading="loading">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg>
                <span class="wpsr-text">Import</span>
              </el-button>
            </div>
          </div>

        <p v-if="selectedImport === 'judge-me'" class="wpsr-ml-20">
          If your reviews contain images, The images will be downloaded by a background process to reduce pressure on the server. so it will take some time before they appear.
          Need Help? <a href="https://wpsocialninja.com/docs/migrate-from-judge-me" target="_blank"> Check Our Documentation.</a>
        </p>

      <div v-if="selectedImport === 'custom' || selectedImport === 'testimonial' || selectedImport === 'judge-me'" class="wpsr-mt-20 wpsr-ml-20 wpsr-mr-20 wpsr-pb-20">
        <div>
        <div class="wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
            <h3 class="wpsr-heading-text">
              {{ $t('CSV Header Structure') }}
            </h3>
            <el-button type="primary" size="small" class="wpsr_primary_btn" style="float: right" @click="download">
              <el-icon><Download /></el-icon>
              {{ $t('Download Sample CSV') }}
            </el-button>
        </div>

         <div class="wpsr-table-container">
           <el-table v-if="sampleData.length" border :data="sampleData">
             <el-table-column v-for="column in columns"
                 :prop="column"
                 :label="column"
                 :key="column"
                 :min-width="125"
                 show-overflow-tooltip
             ></el-table-column>
           </el-table>
          </div>
         </div>
      </div>
    </div>
    </div>
  </div>
</template>

<script>
import { Check, InfoFilled, Download } from '@element-plus/icons-vue'

export default {
  name: "ImportReviews",
  components: {
    Check,
    InfoFilled,
    Download
  },
  data() {
    return {
      loading: false,
      sampleData: [],
      columns: [],
      selectedImport: 'custom',
      file: '',
      hasData: false,
      isWooCommerceInstalled: false,
      selectedSourceId: '',
      customSources: [],
      loadingSources: false,
      searchTimeout: null
    }
  },

  methods: {
    download() {
      let rows = [1, 2];
      let samples = [];
      let headers = this.columns;

      samples.push(headers);

      const sampleDataConfig = {
        review_time: (rowIndex) => '2022-05-26',
        review_date: (rowIndex) => '2022-05-26',
        reply_date: (rowIndex) => '2022-05-26',
        rating: (rowIndex) => '5',
        title: (rowIndex) => `Sample Review Title ${rowIndex + 1}`,
        body: (rowIndex) => `Sample review body text for review ${rowIndex + 1}`,
        platform_name: (rowIndex) => 'woocommerce',
        source_id: (rowIndex) => `sample_id_${rowIndex + 1}`,
        review_id: (rowIndex) => `sample_id_${rowIndex + 1}`,
        review_approved: (rowIndex) => '1',
        recommendation_type: (rowIndex) => 'positive',
        fields: (rowIndex) => '{}',
        category: (rowIndex) => 'sample_category',
        default: (rowIndex, headerIndex) => `content_${rowIndex}_${headerIndex}`
      };

      rows.forEach(function (row, rowIndex) {
        let item = [];

        headers.forEach(function (header, headerIndex) {
          let column_value;

          if (sampleDataConfig[header]) {
            column_value = sampleDataConfig[header](rowIndex);
          } else {
            column_value = sampleDataConfig.default(rowIndex, headerIndex);
          }
          
          item.push(column_value);
        });

        samples.push(item);
      });

      let csv = "data:text/csv;charset=utf-8,";

      samples.forEach(function (item, index) {
        let dataString = item.join(",");
        csv += index < samples.length ? dataString + "\n" : dataString;
      });

      let encodedUri = encodeURI(csv);
      let link = document.createElement("a");
      link.setAttribute("href", encodedUri);

      let filename = "sample.csv";
      link.setAttribute("download", filename);

      document.body.appendChild(link);
      link.click();
    },
    updateColumns(type) {
      const commonColumns = ['reviewer_name', 'reviewer_url', 'review_title', 'reviewer_text', 'review_time', 'rating', 'reviewer_img'];
      const testimonialSpecificColumns = ['category', 'author_company', 'author_position', 'author_website_logo', 'author_website_url'];
      const judgeMeColumns = ['title', 'body', 'rating', 'review_date', 'source', 'curated', 'reviewer_name', 'reviewer_email', 'product_id', 'product_handle', 'reply', 'reply_date', 'picture_urls', 'ip_address', 'location'];

      if (type === 'testimonial') {
        this.columns = [...commonColumns, ...testimonialSpecificColumns];
      } else if (type === 'judge-me') {
        this.columns = [...judgeMeColumns];
      } else {
        this.columns = [...commonColumns];
      }
    },
    setData() {
      const sampleValues = {
        review_time: '2022-05-26',
        review_date: '2022-05-26',
        reply_date: '2022-05-26',
        rating: '5',
        title: 'Sample Review Title',
        body: 'Sample review body text',
        platform_name: this.selectedImport,
        source_id: 'sample_id',
        review_id: 'sample_id',
        review_approved: '1',
        recommendation_type: 'positive',
        fields: '{}',
        category: 'sample_category'
      };

      let row = {};
      this.columns.forEach(item => {
        row[item] = sampleValues[item] || 'column value';
      });

      this.sampleData = Array(3).fill(row);
    },
    clear() {
      jQuery('#fileUpload').val('');
    },
    upload() {
      let that = this;
      this.file = jQuery('#fileUpload')[0].files[0];
  
      if (!this.file) {
        that.loading = false;
        return;
      }

      let formData = new FormData();
      formData.append('file', this.file);
      formData.append('action', 'wpsr_import_data');
      formData.append('type', this.selectedImport);

      // Add source ID if provided
      if (this.selectedSourceId) {
        formData.append('source_id', this.selectedSourceId);
      }

      this.loading = true;
      jQuery.ajax({
        url: ajaxurl,
        data: formData,
        type: 'POST',
        contentType: false,
        processData: false,
        success: function (response) {
          that.clear();
          that.handleSuccess(response.data.message)
        },
        error: function (error) {
          that.handleError(error)
        }
      }).always(function () {
        that.loading = false;
      });
    },
    hasFile() {
      const fileInput = jQuery('#fileUpload')[0];
      this.file = fileInput ? fileInput.files : [];
      if (this.selectedImport === 'custom_sources') {
        this.hasData = this.file.length > 0 && !!this.selectedSourceId;
      } else {
        this.hasData = this.file.length > 0;
      }
    },
    loadCustomSources(query = '') {
      if (this.loadingSources) {
        return; // Already loading
      }

      this.loadingSources = true;
      const params = { per_page: 100 };
      if (query && query.trim()) {
        params.search = query.trim();
      }

      this.$get('pro/custom-sources', params)
        .then(response => {
          if (response && response.items && response.items.data) {
            this.customSources = response.items.data;
          } else {
            this.customSources = [];
          }
        })
        .catch(error => {
          this.handleError(error);
          this.customSources = [];
        })
        .always(() => {
          this.loadingSources = false;
        });
    },
    searchCustomSources(query) {
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = null;
      }

      if (!query || !query.trim()) {
        this.loadCustomSources('');
        return;
      }

      const trimmedQuery = query.trim();
      
      if (trimmedQuery.length < 3) {
        return;
      }

      this.searchTimeout = setTimeout(() => {
        this.loadCustomSources(trimmedQuery);
      }, 300);
    }
  },
  mounted() {
    this.updateColumns(this.selectedImport);
    this.setData();
    this.file = jQuery('#fileUpload')[0].files;
    if(this.file.length === 0) {
      this.hasData = false;
    }
  },
  beforeDestroy() {
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = null;
    }
  },
  watch: {
    selectedImport(newVal) {
      this.selectedSourceId = '';
      this.customSources = [];
      this.hasData = false;
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = null;
      }
      this.updateColumns(newVal);
      this.setData();
      this.clear();
    },
    selectedSourceId() {
      // Update hasData when source selection changes for custom_sources
      if (this.selectedImport === 'custom_sources') {
        const fileInput = jQuery('#fileUpload')[0];
        const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        this.hasData = hasFile && !!this.selectedSourceId;
      }
    }
  }
}
</script>