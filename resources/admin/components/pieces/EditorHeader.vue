<template>
  <div>
    <div class="settings_header">
      <div class="settings_left">
        <div class="wpsr_back_nav">
          <router-link class="wpsr-label-small wpsr-secondary-link" :to="{name: route_name}">{{route_title}}</router-link> 
          <span class="wpsr_sep"><ArrowRightIcon/></span>
        </div>
        
        <span class="section_title wpsr-label-small wpsr-heading-text">{{ template_title }}</span>
        <el-button class="wpsr_mini wpsr_light_edit wpsr_plain_btn wpsr_icon_button" @click="title_form_visible=true">
          <EditPenIcon />
        </el-button>
      </div>
      <div class="settings_right">
        <span v-if="(platform_section !== 'chats' && route_name !== 'notifications')" class="wpsr-copy-inner-text">
          <el-tooltip
              effect="dark"
              content="Click to copy shortcode"
              placement="bottom"
          >
            <code class="copy has-icon wpsr-paragraph-small wpsr-heading-text" :data-clipboard-text='`[${dynamicShortcodeName} id="${templateId}" platform="${platform_section}"]`'>
              <CopyDocumentIcon/> [{{dynamicShortcodeName}} id="{{ templateId }}" platform="{{platform_section}}"]
            </code>
          </el-tooltip>
        </span>
        <span class="wpsr_editor_full_screen">
          <el-button class="wpsr_mini wpsr_light_edit wpsr_default_btn wpsr_icon_btn" @click.prevent="handleFullScreen">
            <FullScreenIcon />
          </el-button>
        </span>
        <el-button class="wpsr_secondary_btn" @click.prevent="$emit('update-settings')" :loading="saving">
          {{ saving ? $t('Saving') : $t('Save') }}
        </el-button>
        <ColorMode/>
      </div>
    </div>
    <!-- Template Title Change Dialog -->
    <el-dialog
      v-model="title_form_visible"
      title="Change Template Title"
      width="30%"
      class="wpsr-modal wpsr-connection-modal"
    >
      <template #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">Change Template Title</h4>
        </div>
      </template>
      <div class="wpsr-connection-modal-body" style="padding: 0;">
        <el-input
          v-model="template_title"
          @keyup.enter="changeTitle"
          autocomplete="off"
          class="wpsr-text-input"
        />
      </div>

      <template #footer>
        <span class="dialog-footer wpsr-d-flex wpsr-jc-end">
          <el-button class="wpsr_default_btn" @click="title_form_visible = false">Cancel</el-button>
          <el-button class="wpsr_secondary_btn" type="primary" @click="changeTitle">Save</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script type="text/babel">
import ColorMode from "./ColorMode.vue";
import {
  EditPen,
  ArrowRight,
  DocumentCopy,
  FullScreen,
} from '@element-plus/icons-vue'
import CopyDocumentIcon from "./icons/CopyDocumentIcon.vue";
import FullScreenIcon from "./icons/FullScreenIcon.vue";
import EditPenIcon from "./icons/EditPenIcon.vue";
import ArrowRightIcon from "./icons/ArrowRightIcon.vue";
export default {
  components: {
    ColorMode,
    CopyDocumentIcon,
    FullScreenIcon,
    EditPenIcon,
    ArrowRightIcon
  },
  props: ['title', 'platform_section', 'shortcode_name', 'route_name', 'route_title'],
  data() {
    return {
      saving: false,
      templateId: this.$route.params.template_id,
      title_form_visible: false,
      template_title: this.title,
      count: 0,
      dynamicShortcodeName: this.shortcode_name || 'wp_social_ninja'
    }
  },
  methods: {
    changeTitle() {
      if (!this.template_title) {
        let message = 'Template Title Should be Filled!';
        this.handleError(message);
      } else if (this.template_title) {
        let title_id = this.platform_section === 'chats' ? this.$route.params.widget_id : this.$route.params.template_id;
        this.$put(`templates/title/${title_id}/`, {
          template_title: this.template_title
        }).then(response => {
          if (response) {
            this.handleSuccess(response.message);
          }
        }).catch(error => {
          this.handleError(error);
        }).always(() => {
          this.title_form_visible = false;
        });
      }
    },
  },
  watch: {
    title(newTitle) {
      this.count++;
      if(this.count > 1){
        return;
      }
      this.template_title = newTitle;
    }
  },
}
</script>