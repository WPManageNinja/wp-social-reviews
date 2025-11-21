import debounced from "lodash/debounce";
export const AISummarizerMixin = {
    data() {
        return {
            can_enable_ai_summary: false,
            isAIToggling: false
        }
    },
    methods: {
        toggleAISummary(value) {
            if (value === 'false') {
                this.template_meta.ai_summary.enabled = 'false';
                this.getUpdatedPostMetaWithReviews();
                this.isAIToggling = false;
                return;
            }

            // If enabling AI summary
            let template_id = this.$route.params.template_id;
            this.loading = true;
            
            this.$get(`templates/meta/reviews/${template_id}/can-enable-ai-summary`)
                .then(response => {
                    if(response.can_enable || response.can_enable === 'true') {
                        this.handleSuccess(response.message);
                        this.template_meta.ai_summary.enabled = 'true';
                        this.saveTemplateMeta();
                    } else {
                        this.handleError(response.message);
                        this.template_meta.ai_summary.enabled = 'false';
                    }
                }).catch(error => {
                    this.handleError(error);
                    this.template_meta.ai_summary.enabled = 'false';
                }).always(() => {
                    this.loading = false;
                    this.isUserAction = false;
                    this.isAIToggling = false;
                });
        },
        regenerateAISummaryForReviews(){
            if(this.loading){
                return;
            }
            this.loading = true;
            this.template_meta.ai_summary.force_regenerate = true;

            let that = this;
            debounced(function (){
                setTimeout(function() {
                    that.getUpdatedPostMetaWithReviews();
                }, 500);
            }, 300)();
        },
    }
}