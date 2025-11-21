<template>
  <div class="wpsr-modal-group-btn">
    <el-button 
      :disabled="isDisable ? true : false" 
      class="wpsr_primary_btn wpsr-mt-20"
      type="success" 
      size="default" 
      @click.prevent="saveReviews(platFormName)"
    >
      {{$t('Save')}}
    </el-button>
<!--    <div v-if="(info.status && info.name) || (info.status && verifyPlatform)" v-for="(info, index) in reviewsinfo" :key="index">-->
<!--      <remove v-if="platFormName !== 'facebook' && (info.name || verifyPlatform)"-->
<!--              @on-confirm="clearVerificationCredentials(platFormName)"-->
<!--              :platform="platFormName"-->
<!--      >-->
<!--      </remove>-->
<!--    </div>-->
  </div>
</template>
<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';

export default {
  name: 'SaveAndResetButton',
  components: {
    Remove
  },
  props: ['platFormName', 'verifyPlatform', 'reviewsinfo', 'isDisable'],
  data(){
    return{
    }
  },
  methods:{
    saveReviews(){
      this.$emit('save-reviews');
    },
    // clearVerificationCredentials(){
    //   this.$emit('clear-verification-credentials');
    // }
  }
}
</script>