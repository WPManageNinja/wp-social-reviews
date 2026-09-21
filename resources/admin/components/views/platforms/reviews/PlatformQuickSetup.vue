<template>
  <div v-if="Object.keys(reviewsinfo).length" class="wpsr-make-template-btn-wrap">
    <h3>{{ $t('Make WP Social Ninja your') }} {{ platformLabel }} {{ $t('review hub') }}</h3>
    <p>{{ $t('One click does it all: creates a review form, builds a display template with the Write a Review button already connected to it, and turns on Main Review Hub — nothing else to configure.') }}</p>
    <el-button
        class="wpsr_primary_btn"
        type="primary"
        size="default"
        :loading="loading"
        :disabled="!has_pro || loading"
        @click.prevent="runQuickSetup">
      {{ loading ? $t('Setting Up...') : $t('Set Up Review Hub') }} {{ !has_pro ? $t(' (Pro)') : ''}}
    </el-button>
  </div>
</template>

<script type="text/babel">
export default {
  name: 'PlatformQuickSetup',
  props: {
    reviewsinfo: {
      type: Object,
      default: () => ({})
    },
    platform: {
      type: String,
      required: true
    },
    platformLabel: {
      type: String,
      required: true
    }
  },
  emits: ['quick-setup-complete'],
  data() {
    return {
      loading: false
    }
  },
  methods: {
    async runQuickSetup() {
      this.loading = true;
      try {
        const response = await this.$post(`pro/settings/${this.platform}/quick-setup`);
        if (response && response.success) {
          this.handleSuccess(response.message);
          this.$emit('quick-setup-complete', response.data);
        } else {
          this.handleError(response.message || 'Failed to complete quick setup.');
        }
      } catch (error) {
        this.handleError(error.message || 'Failed to complete quick setup. Please try again.');
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>
