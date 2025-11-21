<template>
  <div v-if="isOpen" class="wpsr-feed-popup-overlay wpsr-review-feed-popup wpsrm-overlay wpsr_content wpsr-feed-popup-open">
    <div class="wpsr-feed-popup-box-wraper">
      <div class="wpsr-feed-popup-box-wraper-inner wpsrm_inner">
        <div v-if="images.length > 1" class="wpsr-prev-btn wpsr-review-prev-btn" @click.prevent.stop="navigateImage('prev')" :class="{'wpsr-link-disable': currentIndex === 0}">
          <span class="icon-angle-left"></span>
        </div>
        <div v-if="images.length > 1" class="wpsr-next-btn wpsr-review-next-btn" @click.prevent.stop="navigateImage('next')" :class="{'wpsr-link-disable': currentIndex === images.length - 1}">
          <span class="icon-angle-right"></span>
        </div>
        
        <div class="wpsr-feed-popup-close-btn wpsrm_close" @click="closePopup"></div>
        
        <div class="wpsr-feed-popup-media wpsr-review-popup-media">
          <div class="wpsr-review-popup-photo-media">
            <img class="wpsr-review-popup-current-image" :src="currentImage.src" :alt="currentImage.alt || 'Review Image'"/>
            <div v-if="images.length > 1" class="wpsr-review-popup-image-counter">
              {{ currentIndex + 1 }} / {{ images.length }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReviewPhotosPopup',
  props: {
    reviewId: {
      type: [Number, String],
      default: null
    },
    initialIndex: {
      type: Number,
      default: 0
    },
    images: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      isOpen: false,
      currentIndex: this.initialIndex || 0
    }
  },
  computed: {
    currentImage() {
      return this.images[this.currentIndex] || { src: '', alt: 'Review Image' };
    }
  },
  methods: {
    openPopup() {
      this.currentIndex = this.initialIndex;
      this.isOpen = true;
      document.body.classList.add('wpsr-feed-popup-active');
    },
    closePopup() {
      this.isOpen = false;
      document.body.classList.remove('wpsr-feed-popup-active');
    },
    navigateImage(direction) {
      if (direction === 'next' && this.currentIndex < this.images.length - 1) {
        this.currentIndex++;
      } else if (direction === 'prev' && this.currentIndex > 0) {
        this.currentIndex--;
      }
    },
  }
}
</script>