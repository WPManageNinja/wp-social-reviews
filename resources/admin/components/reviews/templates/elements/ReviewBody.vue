<template>
    <span>
        <AiSummaryPoints
            v-if="
                review.summary_list 
                && review.summary_list.length > 0
                && template_meta.ai_summary.style === 'list'
            "
            :review="review"
            :template_meta="template_meta"
            @expandContent="expandContent"
        />

        <ReviewerText
            v-else
            :isReviewerText="template_meta.isReviewerText"
            :reviewerText="review.reviewer_text"
            :reviewFields="review.fields"
            :contentLanguage="template_meta.contentLanguage"
            :contentType="contentType"
            :contentLength="template_meta.content_length"
            :isAiSummary="template_meta.ai_summary.enabled === 'true' && review.platform_name === 'ai'"
            :isAiReadmoreEnabled="template_meta.ai_summary.display_readmore"
            :isTextTypingAnimationEnabled="template_meta.ai_summary.text_typing_animation"
            :review="review"
            @expandContent="expandContent"
        />
    </span>
</template>

<script type="text/babel">

import ReviewerText from './ReviewerText';
import AiSummaryPoints from './AiSummaryPoints';

export default {
    props:['count','review','template_meta'],
    components:{
        ReviewerText,
        AiSummaryPoints
    },
    data(){
        return{
            strArr:[],
            isClicked:[]
        }
    },

    methods:{
        expandContent(val){
            this.$emit('expandContent',true);
        }
    }, 
    computed:{
        contentType(){
            if(
                this.template_meta.contentType === 'excerpt' 
                && !this.template_meta.ai_summary.display_readmore
                && this.review.platform_name === 'ai'
            
            ){
                return 'full_content';
            }

            return this.template_meta.contentType;
        }
    }
}
</script>
