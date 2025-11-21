<template>
  <div>
    <div>
      <div class="wpsr-swiper-prev-next wpsr-swiper-next swiper-button-next" v-if="options.navigation === 'arrow' || options.navigation === 'both'"></div>
      <div class="wpsr-swiper-prev-next wpsr-swiper-prev swiper-button-prev" v-if="options.navigation === 'arrow' || options.navigation === 'both'"></div>
    </div>
    <div v-if="options.navigation === 'dot' || options.navigation === 'both'" class="wpsr-swiper-pagination swiper-pagination"></div>
  </div>
</template>

<script>
export default {
  props: ['options']
}
</script>