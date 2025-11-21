<template>
  <el-tooltip
    class="box-item"
    effect="dark"
    :content="tooltipContent || 'Verified Customer'"
    placement="top"
  >
    <span v-if="(enableVerifiedBadge !== 'false' || enableVerifiedBadge === 'true') && (isReviewerName === 'true')" class="wpsr-verified-review" aria-label="Verified Customer">
      <div class="verified-badge-star">
        <div class="checkmark"></div>
      </div>
    </span>
  </el-tooltip>
</template>

<script>
export default {
  props: {
    enableVerifiedBadge: {
      type: String,
      default: ''
    },
    isReviewerName: {
      type: String,
      default: ''
    },
    tooltipContent: {
      type: String,
      default: 'Verified Customer'
    }
  }
}
</script>
