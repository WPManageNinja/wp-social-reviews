<template>
    <span :data-time="reviewTime" class="wpsr-review-date" v-if="isReviewTime == 'true'">
        {{ wpsrDateFormat(reviewTime) }}
    </span>
</template>

<script type="text/babel">
import {reviewsMixin} from '../../../../mixins/reviewsMixin';

export default {
    props:['isReviewTime','reviewTime','templateMeta'],
    mixins: [reviewsMixin],
    methods: {
    }
}
</script>