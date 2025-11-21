<template>
  <div v-loading="loading" element-loading-text="loading..." class="wpsr-general-settings-wrapper">
    <div class="wpsr-tools-export-import">
      <el-row class="wpsr-setting-header">
        <el-col :md="24">
          <h3>Export Data</h3>
          <p>
            Select the type of data you want to export and click the Export button below.
            A file will be created for you to save to your computer. Reviews and Testimonials will be exported in a <b>CSV</b> format,
            while Templates will be exported in a <b>JSON</b> format. Once you've saved the downloaded file,
            you can use the Import tool to import the data.
          </p>
        </el-col>
      </el-row>

      <div class="wpsr-feed-settings-content">
        <!-- Select Import Type -->
        <div class="wpsr-setting-row-no-border">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Select Data Type') }}</h3>
            <p class="wpsr-setting-description">
              Choose the data type to Download a Social Ninja export file on your computer.
            </p>
          </div>
          <div class="wpsr-setting-right">
            <el-select v-model="selectedExport" placeholder="Select Export Type" @change="fetchTemplates" class="wpsr-text-input">
              <el-option label="Custom Reviews (Deprecated Soon)" value="custom"></el-option>
              <el-option label="Testimonials" value="testimonial"></el-option>
              <el-option label="Feed and Reviews Template" value="template"></el-option>
              <el-option label="Notification Popups Template" value="notifications"></el-option>
              <el-option label="Chat Widgets" value="chat-widget"></el-option>
            </el-select>
          </div>
        </div>

        <div v-if="selectedExport === 'template' || selectedExport === 'notifications' || selectedExport === 'chat-widget'" class="wpsr-setting-row-no-border">
          <div class="wpsr-setting-left">
            <h3 class="wpsr-setting-title">{{ $t('Select Feed') }}</h3>
            <p class="wpsr-setting-description">
              Select the feed you want to export.
            </p>
          </div>
          <div class="wpsr-setting-right">
            <el-select v-model="selectedTemplate" filterable placeholder="Select a template" class="wpsr-text-input">
              <el-option v-for="template in templates" :key="template.ID" :label="template.post_title+'('+template.ID+')'" :value="template.ID">
              </el-option>
            </el-select>
          </div>
        </div>

        <div class="wpsr-setting-row-no-border wpsr-setting-no-border-action-btn">
          <div class="wpsr-setting-left">
            
          </div>
          <div class="wpsr-setting-right">
            <el-button :disabled="!has_pro || ((selectedExport === 'template' || selectedExport === 'notifications' || selectedExport === 'chat-widget') && !selectedTemplate) ? true : false" size="default" class="pull-left wpsr_primary_btn wpsr-tools-btn" type="success" @click="doExport">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
              </svg>
              <span class="wpsr-text">Export</span>
            </el-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Check, InfoFilled } from '@element-plus/icons-vue'

export default {
  name: "ExportReviews",
  components: {
    Check,
    InfoFilled
  },
  data() {
    return {
      validPlatforms: [],
      loading: false,
      selectedExport: 'custom',
      selectedTemplate: null,
      templates: [],
    }
  },
  watch: {
    selectedExport() {
      this.selectedTemplate = null;
    }
  },
  methods: {
    fetchTemplates() {
      const templateTypes = ['template', 'notifications', 'chat-widget'];
      if (templateTypes.includes(this.selectedExport)) {
        this.loading = true;
        this.$get('templates',{
          search: '',
          filter: '',
          exclude_woocommerce: true,
          per_page: 500,
          templateType: this.selectedExport,
        }).then(response => {
          this.templates = response.items.data;
          this.loading = false;
        });
      }
    },
    downloadLink() {
      let url = `${ajaxurl}?action=wpsr_export_data&type=${this.selectedExport}`;
      if (this.selectedExport === 'template' || this.selectedExport === 'notifications' || this.selectedExport === 'chat-widget' && this.selectedTemplate) {
        let selectedTemplateObject = this.templates.find(template => template.ID === this.selectedTemplate);
        const platformName = selectedTemplateObject ? selectedTemplateObject.platform_name : '';
        url += `&templateID=${this.selectedTemplate}&platformName=${platformName}`;
      }
      return url;
    },
    doExport() {
      let url = this.downloadLink();
      location.href = url;
    },
  },
  created() {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    let data = urlParams.get('data');
    if(data === 'empty'){
      this.handleError('Data are empty');
      window.history.pushState({}, null, this.appVars.admin_page_url+'#/tools/export');
    }
  },
}
</script>