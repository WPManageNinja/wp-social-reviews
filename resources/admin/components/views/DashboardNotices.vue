<template>
  <div>
    <div class="wpsr_dashboard_notice" v-if="showNotice && !displayProUpdateNotice">
      <div id="wpsr_review_notices" class="wpsr_review_notice">
        <div class="wpsr_review_notice-aside"></div>
        <p>Thank you for using WP Social Ninja. We would be very grateful if you could share your experience and leave a review for us in <a target="_blank" href="https://wordpress.org/support/plugin/wp-social-reviews/reviews/#new-post">WordPress.org</a>. Your reviews inspire us to keep improving the plugin and delivering a better user experience.</p>
        <div class="wpsr_review_notice_actions">
          <a class="wpsr_review_notice_deserve_it" target="_blank" href="https://wordpress.org/support/plugin/wp-social-reviews/reviews/#new-post">👍 Yes, You Deserve It</a>
          <a @click="updateStatus('already_rated', '1')">🙌 Already Rated!</a>
          <a @click="updateStatus('remind_me', '1')">🔔 Remind Me Later</a>
          <a @click="updateStatus('rescue_me', '1')">💔 No Thanks</a>
        </div>
      </div>
    </div>

<!--    <div class="wpsr_dashboard_notice" v-if="!has_pro && showOptInNotice && !displayProUpdateNotice">-->
<!--      <div id="wpsr_review_notices" class="wpsr_review_notice">-->
<!--        <div class="wpsr_review_notice-aside"></div>-->
<!--        <p>Claim your 50% discount on the Pro add-on by giving WP Social Ninja access to non-sensitive diagnostic data and usage information. <span @click="whatWeCollect = !whatWeCollect">(What we collect)</span></p>-->
<!--        <p style="margin-top: 10px;" v-if="whatWeCollect">Site name and URL, server environment details (php, mysql, server, WordPress versions), site language, number of active and inactive plugins name and email address to send you the discount coupon. No sensitive data is tracked.</p>-->
<!--        <div class="wpsr_review_notice_actions">-->
<!--          <a class="wpsr_review_notice_deserve_it" @click="updateStatus('opt_in', '1')">Sure, i'd like to help</a>-->
<!--          <a @click="updateStatus('opt_in', '0')">No Thanks</a>-->
<!--        </div>-->
<!--      </div>-->
<!--    </div>-->


    <div class="wpsr_dashboard_notice wpsr_dashboard_newsletter" v-if="!has_pro && !displayProUpdateNotice && showNewsletter">
      <div id="wpsr_review_notices" class="wpsr_review_notice">
        <div class="wpsr_review_notice-aside"></div>
        <h3>Subscribe to Our Newsletter</h3>
        <p class="wpsr-mb-10">Subscribe to our Newsletter today and unlock 50% OFF on the pro add-on & get other exclusive social media marketing tips!</p>
        <div class="wpsr_review_notice_actions">
          <div class="wpsr_dashboard_newsletter_input_field">
            <span class="label">Full Name</span>
            <el-input v-model="userData.name"  placeholder="Enter your full name"></el-input>
          </div>
          <div class="wpsr_dashboard_newsletter_input_field">
            <span class="label">Email</span>
            <el-input v-model="userData.email" placeholder="Enter your email address"></el-input>
          </div>
         <div class="wpsr-mt-20">
           <el-button :disabled="!userData.name || !userData.email ? true : false" class="wpsr_newsletter_subscribe" @click="updateSubscribeForm" slot="append">
             {{ $t('Subscribe') }}
           </el-button>
         </div>
        </div>
      </div>
      <a class="wpsr_hide_pro_upgrade_notice" @click="updateStatus('hide_newsletter', '1')">
        <i class="el-icon-circle-close"></i>
      </a>
    </div>

    <div style="padding: 5px 10px 5px 15px;" class="wpsr_dashboard_notice wpsr_dashboard_newsletter" v-if="!has_pro && !displayProUpdateNotice && message">
      <p style="font-size: 16px; color: #67c23a;">{{message}}</p>
    </div>


    <div class="wpsr_dashboard_notice" v-if="has_pro && showProUpdateNotice">
      <div id="wpsr_review_notices" class="wpsr_review_notice">
        <div class="wpsr_review_notice-aside"></div>
        <h3>Update WP Social Ninja Pro Plugin</h3>
        <p>
          We've restructured our WP Social Ninja Pro plugin files to improve code efficiency. To ensure all Pro features work correctly, please update the
          <a :href="admin_url.replace('admin.php', 'plugins.php')+'?s=wp-social-ninja-pro&plugin_status=all'">WP Social Ninja Pro</a> version. If you face any issues, please open a support ticket <a href="https://wpmanageninja.com/support-tickets/" target="_blank">here</a>. Our support team is always here to help you.
        </p>
      </div>
      <a class="wpsr_hide_pro_upgrade_notice" @click="updateStatus('hide_pro_upgrade_notice', '1')">
        <i class="el-icon-circle-close"></i>
      </a>
    </div>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'DashboardNotices',
  props: ['displayNotice', 'displayOptInNotice', 'displayProUpdateNotice', 'displayNewsletter', 'userData'],
  data() {
    return {
      showNotice: this.displayNotice,
      whatWeCollect: false,
      showOptInNotice: this.displayOptInNotice,
      showProUpdateNotice: this.displayProUpdateNotice,
      showNewsletter: this.displayNewsletter,
      admin_url: this.appVars.admin_url,
      message: ''
    }
  },
  methods: {
    updateStatus(notice_type, value){
        let args = {
          notice_type : notice_type,
          value : value
        };
        this.$post('platforms/dashboard-notices', {
          args: args
        }).then(response => {
          if(response.data){
            this.showNotice = response.data.displayNotice;
            this.showOptInNotice = response.data.displayOptInNotice;
            this.showProUpdateNotice = response.data.displayProUpdateNotice;
            this.showNewsletter = response.data.displayNewsletter;
          }
        }).catch((errors) => {
          this.handleError(errors);
        }).always(() => {

        });
    },
    updateSubscribeForm(){
      this.$post('platforms/subscribe', {
        args: this.userData
      }).then(response => {
        if(response){
            this.showNewsletter = response.displayNewsletter;
            this.message = response.message;
        }
      }).catch((errors) => {
        this.handleError(errors);
      }).always(() => {

      });
    }
  },
  mounted() {
  }
}
</script>
