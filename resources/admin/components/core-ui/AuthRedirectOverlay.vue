<template>
  <div v-if="visible" class="wpsr-auth-redirect-overlay">
    <div class="wpsr-auth-redirect-modal">
      <div class="wpsr-auth-redirect-dots">
        <span class="wpsr-auth-redirect-dot"></span>
        <span class="wpsr-auth-redirect-dot"></span>
        <span class="wpsr-auth-redirect-dot"></span>
      </div>
      <h3 class="wpsr-auth-redirect-title">{{ title }}</h3>
      <p class="wpsr-auth-redirect-subtitle">{{ subtitle }}</p>
    </div>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'AuthRedirectOverlay',
  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      default: 'Redirecting...',
    },
    subtitle: {
      type: String,
      default: '',
    },
  },
}
</script>
