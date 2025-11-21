import {createApp} from 'vue'
import {ElNotification, ElLoading, ElMessageBox, ElIcon, ElDialog} from 'element-plus'
import OnboardApp from './OnboardApp.vue'
import Rest from '../Rest.js';

// Create standalone onboarding app
const onboardingApp = createApp(OnboardApp);

// Configure global properties
onboardingApp.config.globalProperties.appVars = window.WPSocialReviewsAdmin || {};
onboardingApp.config.globalProperties.$notify = ElNotification;
onboardingApp.config.globalProperties.$confirm = ElMessageBox.confirm;
onboardingApp.config.globalProperties.$prompt = ElMessageBox.prompt;
onboardingApp.config.globalProperties.$messageBox = ElMessageBox;

// Translation function
onboardingApp.config.globalProperties.$t = (key) => {
    const i18n = window.WPSocialReviewsAdmin?.i18n || {};
    return i18n[key] || key;
}

onboardingApp.config.globalProperties.has_pro = () => {
    return window.WPSocialReviewsAdmin?.has_pro || false;
}
onboardingApp.use(ElIcon);
onboardingApp.use(ElDialog);

function convertToText(obj) {
    const string = [];
    if (typeof (obj) === 'object' && (obj.join === undefined)) {
        for (const prop in obj) {
            string.push(convertToText(obj[prop]));
        }
    } else if (typeof (obj) === 'object' && !(obj.join === undefined)) {
        for (const prop in obj) {
            string.push(convertToText(obj[prop]));
        }
    } else if (typeof (obj) === 'function') {

    } else if (typeof (obj) === 'string') {
        string.push(obj)
    }

    return string.join('<br />')
}

// Add mixin with all methods
onboardingApp.mixin({
    methods: {
        $get: Rest.get,
        $post: Rest.post,
        $uploadFile: Rest.uploadFile,
        $put: Rest.put,
        $del: Rest.delete,
        $patch: Rest.patch,
        $handleError(response) {
            let errorMessage = '';
            if (typeof response === 'string') {
                errorMessage = response;
            } else if (response && response.message) {
                errorMessage = response.message;
            } else {
                errorMessage = convertToText(response);
            }
            if (!errorMessage) {
                errorMessage = 'Something is wrong!';
            }

            if (typeof errorMessage != 'string') {
                errorMessage = convertToText(errorMessage);
            }

            this.$notify({
                type: 'error',
                title: this.$t('Error'),
                message: errorMessage,
                dangerouslyUseHTMLString: true
            });
        },
        convertToText,
    }
});

// Auto-initialize when container exists
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('wpsr_onboarding_app');
    if (container) {
        onboardingApp.mount('#wpsr_onboarding_app');
    }
});