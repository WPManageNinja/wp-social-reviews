<template>
    <div class="wpsr-reviewer-image" v-if="isImage === 'true'">
        <a v-if="enableExternalLink === 'true'" class="wpsr-reviewer-image-url" :class="platformName !== 'amazon' && platformName !=='testimonial' && platformName !=='custom' && platformName !== 'fluent_forms' && image_settings.optimized_images === 'true' && imageUrl && !mediaUrl ? 'wpsr-reviewer-image-animation' : ''" :href="platformName === 'facebook' ? 'https://www.facebook.com/'+sourceId+'/reviews' : reviewerUrl" target="_blank">
            <img v-if="imageUrl && platformName !== 'amazon'" class="wpsr-reviewer-avatar wpsr-reviewer-img" :src="platformName === 'fluent_forms' ? imageUrl : (mediaUrl || imageUrl)" />
            <img v-else class="wpsr-reviewer-avatar" :src="assets_url+'/images/template/review-template/placeholder-image.png'" />
        </a>
        <span v-if="enableExternalLink === 'false' || enableExternalLink === false" class="wpsr-reviewer-image-url" :class="platformName !== 'amazon' && image_settings.optimized_images === 'true' && imageUrl && !mediaUrl ? 'wpsr-reviewer-image-animation' : ''">
          <img class="wpsr-reviewer-avatar wpsr-reviewer-img" v-if="imageUrl" :src="mediaUrl || imageUrl" />
          <img class="wpsr-reviewer-avatar wpsr-reviewer-img" v-else-if="platformName === 'ai'" :src="mediaUrl" />
          <img class="wpsr-reviewer-avatar" v-else :src="assets_url+'/images/template/review-template/placeholder-image.png'" />
        </span>
    </div>
</template>

<script type="text/babel">
import { commonMixin } from '../../../../mixins/commonMixin';
export default {
  mixins: [commonMixin],
  props:['isImage','reviewerUrl','imageUrl', 'mediaUrl', 'sourceId', 'platformName', 'enableExternalLink', 'image_settings'],
}
</script>