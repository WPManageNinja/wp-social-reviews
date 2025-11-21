<template>
  <div>
    <p v-if="Object.keys(this.reviewsinfo).length && !has_pro" style="text-align: center; font-size: 15px;">To add multiple businesses. <a style="display:block; color: #fff;" href="https://wpsocialninja.com/?utm_source=wp_site&amp;utm_medium=plugin&amp;utm_campaign=upgrade" target="_blank" class="wpsr_buy_pro_btn_primary wpsr_buy_pro_btn_inline">Upgrade to Pro</a></p>
    <el-button
        v-if="Object.keys(this.reviewsinfo).length && has_pro"
        style="width: 100%"
        v-model="addNewBusiness"
        :class="{'display-form': !!addNewBusiness}"
        class="wpsr_primary_btn_outline"
        type="success"
        @click.prevent="addBusiness">
      <el-icon size="18"><Link /></el-icon>
      {{ $t('Add More Business') }} {{ !has_pro ? $t(' (Pro)') : ''}}
    </el-button>

    <div v-if="(addNewBusiness || Object.keys(this.reviewsinfo).length === 0)">
        <div class="wpsr-mb-20">
          <h4>{{$t('Select your business type:')}}</h4>
          <el-radio-group v-model="business_type" @change="$emit('set-business-type', $event)">
            <el-radio class="wpsr_primary_checkbox_or_radio" value="rooms">{{$t('Rooms')}}</el-radio>
            <el-radio class="wpsr_primary_checkbox_or_radio" value="experiences">{{$t('Experiences')}}</el-radio>
          </el-radio-group>
        </div>

        <div>
          <h4>Enter your business name to get your reviews:</h4>
          <p>Open your business/product’s page on
            <strong>{{platFormName}}</strong>
            in your browser. Copy the full business name and paste it into the input field to display your reviews. [e.g. Korean Food Revolution]</p>
          <el-input
              :type="type"
              :value="businessName"
              @keyup.enter.native="saveReviews"
              placeholder="Please enter your business name"
              @input="$emit('businessName', $event)"
          >
          </el-input>
        </div>

        <SaveAndResetButton
            :platFormName="platFormName"
            :verifyPlatform="verifyPlatform"
            :reviewsinfo="reviewsinfo"
            @save-reviews="saveReviews"
        />
    </div>
  </div>
</template>

<script type="text/babel">
import SaveAndResetButton from './SaveAndResetButton';
export default {
  name: 'AirbnbForm',
  components: {
    SaveAndResetButton
  },
  props: ['value', 'viaUrl', 'businessName', 'exampleURL', 'verifyPlatform', 'platFormName', 'reviewsinfo'],
  data(){
    return{
      via_url: this.viaUrl,
      business_type: '',
      type: 'text',
      addNewBusiness: false,
    }
  },
  methods:{
    saveReviews(){
      this.$emit('save-reviews');
    },
    addBusiness(){
      this.addNewBusiness = !this.addNewBusiness;
    }
  },
  mounted() {
    this.addNewBusiness = false;
  }
}
</script>