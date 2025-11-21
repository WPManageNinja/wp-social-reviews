<template>
  <div v-if="Object.keys(reviewsinfo).length" class="wpsr-make-template-btn-wrap">
    <h3>Let’s create an awesome template to be added to your site!</h3>
    <p>Create a stunning, fully customizable template to perfectly match your website’s style.</p>
    <el-button
        class="wpsr_primary_btn"
        type="primary"
        size="default"
        @click.prevent="addNewTemplate"
    >
      <CreateTemplateIcon/>
      {{ $t('Add New Template') }}
    </el-button>
  </div>
</template>

<script type="text/babel">

import CreateTemplateIcon from "../../../pieces/icons/CreateTemplateIcon.vue";

export default {
  name: 'CreateTemplate',
  components: {CreateTemplateIcon},
  props: ['reviewsinfo'],
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