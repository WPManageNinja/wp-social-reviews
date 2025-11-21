<template>
  <div>
    <template v-if="((Object.keys(locationList).length && platFormName === 'google') || (Object.keys(this.reviewsinfo).length === 0) )">
      <el-dialog
          title="Select a Business Location to Fetch Reviews"
          class="wpsr-inner-modal"
          v-model="dialogVisible"
          width="30%"
          append-to-body
      >
      <template #header>
        <div class="wpsr-connection-modal-header" v-if="Object.keys(locationList).length">
          <h4 class="wpsr-dialog-title">Select a Business Location to Fetch Reviews</h4>
        </div>
      </template>
      <div class="wpsr-modal-body wpsr-modal-radio-field">
        <div v-loading="acces_token_loader" element-loading-text="Please wait.">
          <el-radio-group v-model="location_id" :class="(Object.keys(locationList).length > 5 && platFormName === 'google') ? 'wpsr-scrollbar' : ''" @change="$emit('set-google-configuration', $event)" >
            <el-radio
                class="wpsr_primary_checkbox_or_radio"
                v-for="(item, key) in locationList"
                :key="key"
                :label="item.place_id"
            >
              <div class="wpsr-user-details">
                <p v-html="item.locationName"></p>
                <span v-if="item.accountName">({{item.accountName}} - {{item.accountType}})</span>
              </div>
            </el-radio>
          </el-radio-group>
        </div>
      </div>
      <div class="wpsr-modal-footer" v-if="Object.keys(locationList).length" >
        <el-popover
            ref="popoverRef"
            placement="top"
            width="420"
            :visible="visible"
            @update:visible="visible = $event"
            trigger="click"
        >
          <h4>{{$t('Are you sure you want to delete all the locations?')}}</h4>
          <p>{{$t('All the configuration associate with this account will be deleted.')}}</p>
          <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
            <el-button class="wpsr_default_btn" type="default" size="small" @click="visible = false">{{$t('Cancel')}}</el-button>
            <el-button class="wpsr_primary_btn" type="primary" size="small" @click="clearVerificationCredentials(platFormName, 'clear-locations')">{{$t('Confirm')}}</el-button>
          </div>
          <template #reference>
            <el-button class="wpsr_default_btn" type="danger" size="default">Clear Locations</el-button>
          </template>
        </el-popover>

        <el-button :disabled="!location_id.length ? true : false" class="wpsr_primary_btn"
                   size="default" type="success" @click.prevent="saveReviews(platFormName)">{{ $t('Save') }}
        </el-button>
      </div>
    </el-dialog>
    </template>
  </div>
</template>
<script type="text/babel">
import SaveAndResetButton from './SaveAndResetButton';
export default {
  name: 'GoogleApiForm',
  components: {
    SaveAndResetButton
  },
  props: [
    'value',
    'platFormName',
    'apiKey',
    'locationList',
    'acces_token_loader',
    'reviewsinfo',
    'verifyPlatform',
    'addNewBusiness'
  ],
  data(){
    return{
      location_id: [],
      visible: false,
      inner_loading: false,
    }
  },
  computed: {
    dialogVisible: {
      get() {
        return this.addNewBusiness;
      },
      set(value) {
        if (!value) {
          this.location_id = [];
        }
        this.$emit('update:addNewBusiness', value);
      }
    }
  },
  methods: {
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