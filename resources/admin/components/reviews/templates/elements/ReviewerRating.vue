<template>
    <div :data-rating="reviewerRating" class="wpsr-rating-wrapper" v-if="isReviewerRating === 'true' || platformName==='facebook'">
        <div class="wpsr-booking-rating-style" v-if="starStyle === 'default' && this.platformName === 'ai' && this.platformsArray && platformsArray.length === 1 && this.platformsArray.includes('booking.com')">
          <span class="review-badge" :class=[this.platformName]>{{ parseFloat(reviewerRating).toFixed(1) }}</span>
        </div>
        <template v-else-if="starStyle === 'default' && platformName !== 'facebook' && platformName !== 'booking.com' ">
            <div class="wpsr-rating" v-html="ratingIcon(Number(reviewerRating).toFixed(1))"></div>
        </template>

        <div class="wpsr-booking-rating-style" v-else-if="starStyle === 'default' && platformName === 'booking.com' && platformName !== 'facebook'">
            <span class="review-badge">{{ parseFloat(reviewerRating).toFixed(1) }}</span>
        </div>

        <div class="wpsr-rating-style-1" v-else-if="starStyle === 'style1' && platformName !== 'facebook'">
        Rated<span>{{ parseFloat(reviewerRating).toFixed(1) }}</span>
        </div>

        <div class="wpsr-rating-style-2" v-else-if="starStyle === 'style2' && platformName !== 'facebook'">
            <span>{{ parseFloat(reviewerRating).toFixed(1) }}</span>
            <div class="wpsr-rating" v-html="ratingIcon(Number(reviewerRating).toFixed(1))"></div>
        </div>

        <div class="wpsr-recommends" v-else-if="platformName === 'facebook'">
            <svg width="18" height="18"><path d="M9 14l-3.293 3.293A1 1 0 0 1 4 16.586V14h-.154c-1.337 0-1.822-.14-2.311-.4A2.726 2.726 0 0 1 .4 12.464c-.261-.488-.4-.973-.4-2.309v-6.31c0-1.336.14-1.821.4-2.31A2.726 2.726 0 0 1 1.536.4c.488-.261.973-.4 2.309-.4h10.31c1.336 0 1.821.14 2.31.4.49.262.873.646 1.134 1.135.262.489.401.974.401 2.31v6.31c0 1.336-.14 1.821-.4 2.31a2.726 2.726 0 0 1-1.135 1.134c-.489.262-.974.401-2.31.401H9zm0-5l1.454.765a.5.5 0 0 0 .726-.527l-.278-1.62 1.177-1.147a.5.5 0 0 0-.277-.853l-1.626-.236-.728-1.474a.5.5 0 0 0-.896 0l-.728 1.474-1.626.236a.5.5 0 0 0-.277.853l1.177 1.147-.278 1.62a.5.5 0 0 0 .726.527L9 9z"></path></svg>
            <span v-if="recommendationType === 'positive'">{{ $t('Recommends') }}</span>
            <span v-else>doesn't recommend</span>
        </div>
    </div>
</template>

<script type="text/babel">

export default {
    props:['isReviewerRating','starStyle','reviewerRating', 'platformName', 'recommendationType', 'platformsArray'],
    methods:{
      shouldShowBookingRating(){
        return  this.platformName === 'ai'
                && this.platformsArray
                && this.platformsArray.includes('booking.com');
      }

    }
}
</script>
