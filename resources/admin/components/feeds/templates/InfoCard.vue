<template>
    <div class="wpsr-feed-info-box">
        <p class="wpsr-feed-info-title" v-html="info.title"></p>
        <p class="wpsr-feed-info-description" v-html="info.description"></p>
    </div>
  </template>
  
  <script type="text/babel">
  export default {
    name: "InfoCard",
    props: ['info']
  }
  </script>