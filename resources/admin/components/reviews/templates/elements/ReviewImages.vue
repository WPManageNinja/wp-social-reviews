<template>
  <div v-if="reviewImages.length" class="wpsr-review-images">
    <img
        v-for="(image, index) in reviewImages"
        :key="index"
        class="wpsr-review-image wpsr-review-photos-playmode"
        :src="image"
        alt="Review Image"
        :data-template-id="templateId"
        :data-review-id="reviewId"
        :data-image-index="index"
        :data-reviewer-name="reviewerName"
        :data-review-text="reviewText"
        :data-rating="rating"
        :data-review-time="reviewTime"
        :data-platform-name="platformName"
        @click="openImagePopup(index)"
    />
    
    <review-photos-popup 
      ref="photosPopup"
      :review-id="reviewId"
      :images="formattedImages"
      :initial-index="currentImageIndex"
    />
  </div>
</template>

<script type="text/babel">
import ReviewPhotosPopup from './ReviewPhotosPopup.vue';

export default {
  name: 'ReviewImages',
  components: {
    ReviewPhotosPopup
  },
  props: {
    'reviewImages': {
      type: Array,
      default: () => []
    },
    'templateId': {
      type: [String, Number],
      default: null
    },
    'reviewId': {
      type: [String, Number],
      default: null
    },
    'reviewerName': {
      type: String,
      default: ''
    },
    'reviewText': {
      type: String,
      default: ''
    },
    'rating': {
      type: [String, Number],
      default: null
    },
    'reviewTime': {
      type: String,
      default: ''
    },
    'platformName': {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      currentImageIndex: 0
    }
  },
  computed: {
    formattedImages() {
      return this.reviewImages.map((src, index) => ({
        src,
        alt: `Review Image ${index + 1}`
      }));
    }
  },
  methods: {
    openImagePopup(index) {
      this.currentImageIndex = index;
      this.$nextTick(() => {
        this.$refs.photosPopup.openPopup();
      });
    }
  }
}
</script>