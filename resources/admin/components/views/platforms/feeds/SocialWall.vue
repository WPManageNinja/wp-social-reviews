<template>
  <div>
    <div>
      <el-dialog class="wpsr-tiktok-modal" title="Social Wall Configuration" :visible.sync="social_wall_modal">
        <div v-loading="loading" element-loading-text="Please wait.">
          <p style="font-size: 14px;"><strong>Note:</strong> Before setting up a Social Wall Feed, ensure you’ve created templates for your feed platforms.</p>
          <!-- start create template -->
          <div class="wpsr-modal-group-btn wpsr-place-details">
            <div
                class="wpsr-make-template-btn-wrap"
            >
              <p>Let’s create an awesome template to be added to your site!</p>
              <div class="wpsr-modal-group-btn">
                <el-button
                    class="wpsr-save-cradentials"
                    type="primary"
                    size="default"
                    @click.prevent="addNewTemplate"
                >
                  {{ $t('Add New Template') }}
                </el-button>
              </div>
            </div>
          </div>
          <!-- end create template -->

          <p class="wpsr-docs-help">Need Help? <a
              href="https://wpsocialninja.com/docs/tiktok-feed-activation/"
              target="_blank">{{ $t('Read Documentation') }}</a>
            | <a href="https://wpsocialninja.com/terms-conditions/" target="_blank">Terms & Conditions</a>
            | <a href="https://wpsocialninja.com/privacy-policy/" target="_blank">Privacy Policy</a>
          </p>
        </div>
      </el-dialog>
    </div>
  </div>
</template>

<script type="text/babel">
import Remove from '../../../core-ui/editor/RemoveConfirm';
import AddOns from "./AddOns";

export default {
  props:['socialWallModal'],
  data(){
    return {
      loading: false,
      inner_loading: false,
    }
  },
  components:{
    AddOns,
    Remove
  },
  methods: {
    addNewTemplate(){
      this.$post('templates',{
        platform: 'social_wall',
        type: 'feed'
      })
          .then(response => {
            if(response && response.template_id) {
              this.$router.push({
                name: 'edit-social-wall-template',
                params: {
                  template_id: response.template_id
                }
              })
              // close modal after successful template creation
              this.social_wall_modal = false;
            }
          }).catch(error => {
        this.handleError(error);
      }).always(() => {

      });
    },
  },
  computed:{
    social_wall_modal:{
      get(){
        return this.socialWallModal
      },
      set(newVal) {
        this.$emit('social_wall_settings', newVal);
      }
    },
  },

  mounted(){

  }
}
</script>