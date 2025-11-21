<template>
  <div>
    <template v-if="( (Object.keys(pageList).length && platFormName === 'facebook') || (Object.keys(this.reviewsinfo).length === 0) )">
      <el-dialog
          title="Select a page to fetch reviews"
          class="wpsr-inner-modal"
          v-model="dialogVisible"
          width="30%"
          append-to-body
      >
      <template #header>
        <div class="wpsr-connection-modal-header" v-if="Object.keys(pageList).length">
          <h4 class="wpsr-dialog-title">Select a page to fetch reviews</h4>
        </div>
      </template>

      <div class="wpsr-modal-body wpsr-modal-radio-field">
        <div v-loading="acces_token_loader" element-loading-text="Please wait.">
          <el-radio-group :class="(Object.keys(pageList).length > 5 && platFormName === 'facebook') ? 'wpsr-scrollbar' : ''" v-model="page_id" @change="$emit('set-configuration', $event)">
            <el-radio
                class="wpsr_primary_checkbox_or_radio"
                v-for="item in pageList"
                :key="item.id"
                :value="item"
            >
              <div class="wpsr-user-details">
                <p>{{item.name}}</p>
                <span>{{$t('Page')}}</span>
              </div>
            </el-radio>
          </el-radio-group>

          <div class="wpsr-alert wpsr-alert-warning wpsr-d-flex wpsr-mt-20 wpsr-mb-15">
            <el-icon><InfoFilled /></el-icon>
            <p>
              <strong>Note:</strong> Changing the password, updating privacy settings, or removing the page admins for the related Facebook page
              may require
              <a href="https://wpsocialninja.com/docs/how-to-reauthorize-the-wp-social-ninja-instagram-facebook-app/"
                 target="_blank">manually reauthorizing our app</a> to reconnect an account.
            </p>
          </div>
        </div>
      </div>

      <div class="wpsr-modal-footer" v-if="Object.keys(pageList).length">
        <el-popover
            ref="popoverRef"
            placement="top"
            width="420"
            :visible="visible"
            @update:visible="visible = $event"
            trigger="click"
        >
          <h4>{{$t('Are you sure you want to delete all the pages?')}}</h4>
          <p>{{$t('All the configuration associate with this account will be deleted.')}}</p>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" type="default" size="small" @click="visible = false">{{$t('Cancel')}}</el-button>
            <el-button class="wpsr_primary_btn" type="primary" size="small" @click="clearVerificationCredentials(platFormName, 'clear-locations')">{{$t('Confirm')}}</el-button>
          </div>
          <template #reference>
            <el-button class="wpsr_default_btn" type="danger" size="default">Clear Pages</el-button>
          </template>
        </el-popover>

        <SaveAndResetButton
            v-if="platFormName ==='facebook'"
            :platFormName="platFormName"
            :verifyPlatform="verifyPlatform"
            :reviewsinfo="reviewsinfo"
            :isDisable="!page_id"
            @save-reviews="saveReviews"
        />
      </div>
    </el-dialog>
    </template>
  </div>
</template>
<script type="text/babel">
import SaveAndResetButton from './SaveAndResetButton';
export default {
  name: 'FacebookForm',
  components: {
    SaveAndResetButton
  },
  props: ['value', 'platFormName', 'admin_page_url', 'pageList', 'reviewsinfo', 'verifyPlatform', 'credentialsType', 'acces_token_loader', 'addNewBusiness'],
  data(){
    return{
      visible: false,
      page_id: ''
    }
  },
  computed: {
    dialogVisible: {
      get() {
        return this.addNewBusiness;
      },
      set(value) {
        if (!value) {
          this.page_id = '';
        }
        this.$emit('update:addNewBusiness', value);
      }
    }
  },
  methods:{
    clearVerificationCredentials(platFormName, sourceId) {
      this.visible = false;
      this.$emit('clear-verification-credentials', sourceId);
    },
    saveReviews(){
      this.$emit('save-reviews');
    },
  }
}
</script>