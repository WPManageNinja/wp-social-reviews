<template>
  <div class="wpsr-review-content-modal-wrap">
    <p class="wpsr-text-modal-content" v-html="truncatedContent"></p>
    <a
        v-if="isTextTruncated"
        href="#"
        class="wpsr-read-more"
        @click.prevent="showReviewDetails"
    >
      {{ $t('See More') }}
    </a>

    <el-dialog
        v-model="showReviewContent"
        width="40%"
        class="wpsr-review-content-modal"
        append-to-body
        destroy-on-close
    >
      <template #header>
        <div class="wpsr-review-content-modal-header wpsr-connected-accounts">
          <span class="wpsr-rating">
            <template style="display: block;">
              <div class="wpsr-rating" v-html="ratingIcon(Number(rating).toFixed(1))"></div>
            </template>
          </span>
        </div>
      </template>
      <div class="wpsr-review-content-modal-body">
        <h3 v-if="title">{{title}}</h3>
        <p v-if="content" v-html="content"></p>
      </div>
      <div class="wpsr-review-content-modal-footer">
        <div class="wpsr-reviewer-details">
          <img v-if="reviewerImg" :src="reviewerImg" :alt="reviewerName" class="wpsr-reviewer-img">
          <div class="wpsr-review-meta">
            <h4 v-if="reviewerName" class="wpsr-reviewer-name">{{ reviewerName }}</h4>
            <span class="wpsr-review-date">{{ wpsrDateFormat(date, 'DD MMM, YYYY') }}</span>
          </div>
        </div>
        <span class="wpsr-platform-icon" v-html="platformIcon"></span>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'ReviewContentModal',
  props: {
    content: {
      type: String,
      required: true
    },
    title: {
      type: String,
      default: ''
    },
    wordLimit: {
      type: Number,
      default: 10
    },
    rating: {
      type: [Number, String],
      default: 0
    },
    reviewerName: {
      type: String,
      default: ''
    },
    reviewerImg: {
      type: String,
      default: ''
    },
    date: {
      type: String,
      default: ''
    },
    platformIcon: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      showReviewContent: false
    }
  },
  computed: {
    truncatedContent() {
      const words = this.content.split(' ');
      if (words.length > this.wordLimit) {
        return words.slice(0, this.wordLimit).join(' ') + '...';
      }
      return this.content;
    },
    isTextTruncated() {
      return this.content.split(' ').length > this.wordLimit;
    }
  },
  methods: {
    showReviewDetails() {
      this.showReviewContent = true;
    }
  }
}
</script>