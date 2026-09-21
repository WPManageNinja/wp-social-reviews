<template>
  <div v-if="Object.keys(reviewsinfo).length" class="wpsr-make-template-btn-wrap">
    <h3>{{ secondary ? $t('Start from a blank template') : $t('Let’s create an awesome template to be added to your site!') }}</h3>
    <p>{{ secondary ? $t('Creates an empty template only — you’ll need to manually create a review form, connect the Write a Review button, and turn on Main Review Hub yourself.') : $t('Create a stunning, fully customizable template to perfectly match your website’s style.') }}</p>
    <el-button
        :class="secondary ? 'wpsr_primary_btn_outline' : 'wpsr_primary_btn'"
        :type="secondary ? 'default' : 'primary'"
        size="default"
        @click.prevent="addNewTemplate"
    >
      <CreateTemplateIcon :color="secondary ? 'var(--wpsr-secondary-menu-active-text, #5525D9)' : 'white'"/>
      {{ $t('Add New Template') }}
    </el-button>
  </div>
</template>

<script type="text/babel">

import CreateTemplateIcon from "../../../pieces/icons/CreateTemplateIcon.vue";

export default {
  name: 'CreateTemplate',
  components: {CreateTemplateIcon},
  props: {
    reviewsinfo: {
      type: Object,
      default: () => ({})
    },
    // De-emphasized when a Quick Setup action is already shown alongside this
    // (WooCommerce/FluentCart) — Quick Setup already creates a template as
    // part of its own flow, so this becomes the manual fallback, not a
    // second co-equal primary action.
    secondary: {
      type: Boolean,
      default: false
    }
  },
  data(){
    return{

    }
  },
  methods:{
    addNewTemplate(){
      this.$emit('add-new-template');
    }
  }
}
</script>