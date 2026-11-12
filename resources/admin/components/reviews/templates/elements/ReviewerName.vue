<template>
   <div class="wpsr-reviewer-name-wrapper">
     <a v-if="enableExternalLink === 'true' && isReviewerName === 'true'" class="wpsr-reviewer-name-url" :href="platformName === 'facebook' ? 'https://www.facebook.com/'+sourceId+'/reviews' : safeReviewerUrl" target="_blank">
       <span class="wpsr-reviewer-name" dir="auto" v-html="reviewerName"></span>
       <VerifiedBadge :enableVerifiedBadge="enableVerifiedBadge" :isReviewerName="isReviewerName" :tooltipContent="verifiedBadgeTooltipText" />
     </a>
      <span class="wpsr-reviewer-name-url" v-if="(enableExternalLink === false || (enableExternalLink === false || enableExternalLink === 'false')) && isReviewerName === 'true'">
          <span class="wpsr-reviewer-name" dir="auto" v-html="reviewerName"></span>
          <VerifiedBadge :enableVerifiedBadge="enableVerifiedBadge" :isReviewerName="isReviewerName" :tooltipContent="verifiedBadgeTooltipText" />
      </span>

   </div>
</template>

<script type="text/babel">
import VerifiedBadge from './VerifiedBadge.vue'
import { wpsrIsHttpUrl, wpsrTrimUrl } from '../../../../utils/videoUrl';

export default {
    props:['isReviewerName','reviewerName','reviewerUrl', 'sourceId', 'platformName', 'enableExternalLink', 'enableVerifiedBadge', 'verifiedBadgeTooltipText'],
    components: {
      VerifiedBadge
    },
    computed: {
      // Visitor-submitted URL: only http(s) may become an href; null drops the attribute.
      safeReviewerUrl() {
        return wpsrIsHttpUrl(this.reviewerUrl) ? wpsrTrimUrl(this.reviewerUrl) : null;
      }
    }
}
</script>