<template>
    <div class="wpsr-skeleton-wrapper" :class="{ 'wpsr-skeleton-dark': isDarkMode }">
      <el-skeleton 
        :rows="rows" 
        :animated="animated"
        :loading="loading"
        :throttle="throttle"
      >
        <template v-if="customTemplate" #template>
          <slot name="template"></slot>
        </template>
      </el-skeleton>
    </div>
  </template>
  
  <script type="text/babel">
  import { DarkModeMixin } from '../../mixins/DarkModeMixin';
  
  export default {
    name: 'WpsrSkeleton',
    mixins: [DarkModeMixin],
    props: {
      rows: {
        type: Number,
        default: 15,
        validator: (value) => value > 0 && value <= 50
      },
      animated: {
        type: Boolean,
        default: true
      },
      loading: {
        type: Boolean,
        default: true
      },
      throttle: {
        type: Number,
        default: 0
      },
      customTemplate: {
        type: Boolean,
        default: false
      }
    }
  }
  </script>