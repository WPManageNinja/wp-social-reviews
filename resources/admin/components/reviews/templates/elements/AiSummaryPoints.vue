<template>
    <div :class="['wpsr-review-content', 'wpsr-review-summary-list', 'wpsr_add_read_more_list', 'wpsr_show_less_list', { 'wpsr-ai-summary-fixed-height': isExpanded && template_meta.contentType !== 'content_in_scrollbar'}]">
        <ul :class="['wpsr-ai-review-summary-list', {'wpsr-ai-review-summary-list-scroll': template_meta.contentType === 'content_in_scrollbar'}]">
            <li v-for="point in summaryList">
                <span v-if="isValidPoint(point)" class="wpsr-summary-point">
                    <span class="wpsr-list-style-check"></span>
                    <span class="wpsr-text">
                        <p v-html="point"></p>
                    </span>
                </span>
            </li>
        </ul>
        <span v-if="shouldShowReadMore()" class='wpsr_read_more_list' :class=" isExpanded ? 'wpsr_read_less': 'wpsr_read_more' " @click="toggleExpand">
            {{ isExpanded ? $t('Read Less') : $t('Read More') }}
        </span>
    </div>
</template>

<script>
import { jsLibraryMixin } from "../../../../mixins/jsLibraryMixin";

export default {
    props: ['review', 'template_meta'],
    mixins: [jsLibraryMixin],
    data() {
        return {
            isExpanded: !this.template_meta.ai_summary.display_readmore,
            summaryList: [],
            currentString: "",
            currentIndex: 0,
            isAnimating: false
        };
    },
    methods: {
        isValidPoint(point) {
            return point && point !== '...';
        },
        toggleExpand() {
            this.isExpanded = !this.isExpanded;
            this.updateSummaryList();
            if (this.isExpanded) {
                this.$emit('expandContent', true);
            }
        },
        addReadMoreButtonToUnorderedList(content, length) {
            if (this.isExpanded || !this.template_meta.ai_summary.display_readmore || length >= this.summaryListLength(content)) {
                return content;
            }
            let excerptList = [];
            let totalWords = 0;
            for (let i = 0; i < content.length; i++) {
                let item = content[i];
                let countWord = item.split(" ");
                if (totalWords + countWord.length > length) {
                    let excerpt = countWord.slice(0, length - totalWords).join(" ");
                    excerptList.push(excerpt + "...");
                    break;
                } else {
                    excerptList.push(item);
                    totalWords += countWord.length;
                }
            }
            return excerptList;
        },
        summaryListLength(content) {
            return content.reduce((sum, item) => sum + item.split(" ").length, 0);
        },
        shouldShowReadMore() {
            const totalWords = this.summaryListLength(this.review.summary_list);
            return this.template_meta.ai_summary.display_readmore && totalWords > this.template_meta.content_length;
        },
        updateSummaryList() {
            if (this.isExpanded) {
                this.summaryList = [...this.review.summary_list];
            } else {
                this.summaryList = this.addReadMoreButtonToUnorderedList(
                    [...this.review.summary_list],
                    this.template_meta.content_length
                );
            }
        },
        animateSummaryList(){

            if (this.isAnimating) return; // Prevent multiple animations
            this.isAnimating = true;

            let listIndex = 0;
            let charIndex = 0;

            let animatedList = [];

            animatedList = this.addReadMoreButtonToUnorderedList(
                [...this.review.summary_list],
                this.template_meta.content_length,
                this.template_meta.content_language
            );

            if(this.template_meta.ai_summary.text_typing_animation === false){
                this.summaryList = animatedList;
                this.isAnimating = false;
                return;

            }
            const interval = setInterval(() => {
                if (listIndex < animatedList.length) {
                    if (charIndex < animatedList[listIndex].length) {
                        this.currentString += animatedList[listIndex][charIndex];
                        const newList = [...this.summaryList];
                        newList[listIndex] = this.currentString;
                        this.summaryList = newList;
                        charIndex++;
                    } else {
                        listIndex++;
                        charIndex = 0;
                        this.currentString = "";
                    }
                } else {
                    clearInterval(interval);
                    this.isAnimating = false; // Reset flag after animation completes
                    if (this.template_meta.templateType === 'masonry') {
                        this.loading = true;
                        let that = this;
                        setTimeout(() => {
                            that.activeMasonry('.wpsr-active-masonry-layout', '.wpsr-all-reviews');
                        }, 100);
                    }
                }
            }, 7);
        }
    },
    mounted() {
        this.updateSummaryList();
        this.animateSummaryList();
    },
    watch: {
        'template_meta.ai_summary.display_readmore': {
            handler() {
                this.updateSummaryList();
                if (!this.isAnimating) { // Ensure animation is not already running
                    this.animateSummaryList();
                }
            },
            deep: true
        },
        'template_meta.content_length': {
            handler() {
                this.updateSummaryList();
                if (!this.isAnimating) { // Ensure animation is not already running
                    this.animateSummaryList();
                }
            },
            deep: true
        }
    }
}
</script>
