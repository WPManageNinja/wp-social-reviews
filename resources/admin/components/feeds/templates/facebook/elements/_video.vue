<template>
  <div class="wpsr-video-container" :class="containerClass">
    <video 
      :id="videoId"
      :src="videoSrc" 
      :key="videoKey"
      :playsinline="playsinline"
      :controls="controls"
      :preload="preload"
      :class="videoClass"
      @error="handleVideoError"
      @loadstart="handleVideoLoadStart"
      @canplay="handleVideoCanPlay"
    >
      <source :src="videoSrc" :type="sourceType">
      <p>{{ fallbackText }}</p>
    </video>
    
    <div v-if="showPlayOverlay" class="wpsr-video-play-overlay">
      <div class="wpsr-video-play-icon"></div>
    </div>
    
    <div v-if="isLoading" class="wpsr-video-loading">
      <div class="wpsr-video-loading-spinner"></div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VideoPlayer',
  props: {
    videoSrc: {
      type: String,
      required: true
    },

    videoId: {
      type: String,
      default: 'wpsr-video-player'
    },

    videoKey: {
      type: String,
      default: null
    },

    playsinline: {
      type: Boolean,
      default: true
    },
    controls: {
      type: Boolean,
      default: true
    },
    preload: {
      type: String,
      default: 'metadata'
    },

    sourceType: {
      type: String,
      default: 'video/mp4'
    },

    containerClass: {
      type: String,
      default: ''
    },
    videoClass: {
      type: String,
      default: 'wpsr-video-player'
    },

    showPlayOverlay: {
      type: Boolean,
      default: false
    },
    fallbackText: {
      type: String,
      default: 'Your browser does not support the video tag.'
    },

    autoPlay: {
      type: Boolean,
      default: false
    },

    muted: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      isLoading: false,
      hasError: false
    };
  },
  watch: {
    videoSrc: {
      handler(newSrc) {
        if (newSrc) {
          this.resetVideoState();
        }
      },
      immediate: true
    }
  },
  methods: {
    handleVideoLoadStart() {
      this.isLoading = true;
      this.hasError = false;
      this.$emit('loadstart');
    },
    
    handleVideoCanPlay() {
      this.isLoading = false;
      this.$emit('canplay');
      
      if (this.autoPlay) {
        this.playVideo();
      }
    },
    
    handleVideoError(error) {
      this.isLoading = false;
      this.hasError = true;
      this.$emit('error', error);
    },

    resetVideoState() {
      this.isLoading = false;
      this.hasError = false;
    },
    
    playVideo() {
      const video = this.$el.querySelector('video');
      if (video) {
        video.play().catch(error => {
          this.$emit('play-error', error);
        });
      }
    },
    
    pauseVideo() {
      const video = this.$el.querySelector('video');
      if (video && !video.paused) {
        video.pause();
      }
    },
    
    getVideoElement() {
      return this.$el.querySelector('video');
    },
    
    isPlaying() {
      const video = this.getVideoElement();
      return video && !video.paused;
    },
    
    setVideoSource(src) {
      const video = this.getVideoElement();
      if (video) {
        video.src = src;
        this.resetVideoState();
      }
    }
  },

  mounted() {
    this.$parent.$refs[this.videoId] = this;
  },
  
  beforeDestroy() {
    this.pauseVideo();
  }
};
</script>
