import { createApp } from 'vue';
import App from './App';
import {routes} from './router/routes';
import { createRouter, createWebHashHistory } from 'vue-router';
import ElementPlus from './elements';
import moment from 'moment';
import 'moment-duration-format';
import WPSocialReviews from './WPSocialReviews';
import WpsrSkeleton from './components/global/skeleton.vue';

const wpSocialReviews = new WPSocialReviews();

const router = createRouter({
    history: createWebHashHistory(),
    routes: wpSocialReviews.applyFilters('wpsr_global_vue_routes', routes),
    linkActiveClass: 'active'
});

router.beforeEach((to, from, next) => {
    if (to.meta) {
        document.title = 'WP Social Ninja :: ' + to.meta.title;
    } else {
        document.title = 'WP Social Ninja';
    }
    next();
});

// Create the app
const app = createApp(App);

// Use Element Plus
app.use(ElementPlus);

// Add global properties
app.config.globalProperties.$rest = wpSocialReviews.$rest;
app.config.globalProperties.$get = wpSocialReviews.$get;
app.config.globalProperties.$post = wpSocialReviews.$post;
app.config.globalProperties.$del = wpSocialReviews.$del;
app.config.globalProperties.$put = wpSocialReviews.$put;
app.config.globalProperties.$patch = wpSocialReviews.$patch;
app.config.globalProperties.appVars = window.WPSocialReviewsAdmin;
app.config.globalProperties.has_pro = !!window.WPSocialReviewsAdmin.has_pro;
app.config.globalProperties.is_custom_feed_for_tiktok_activated = !!window.WPSocialReviewsAdmin.has_custom_feed_for_tiktok;
app.config.globalProperties.is_custom_feed_for_tiktok_installed = !!window.WPSocialReviewsAdmin.is_custom_feed_for_tiktok_activated;
app.config.globalProperties.assets_url = window.WPSocialReviewsAdmin.assets_url;
app.config.globalProperties.assets_url_pro = window.WPSocialReviewsAdmin.assets_url_pro;
app.config.globalProperties.hasPermission = wpSocialReviews.hasPermission;

// Add mixin with all methods
app.mixin({
    data() {
        return {
            pro_purchase_url: 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade',
        }
    },
    computed: {
        // Map hasPro (camelCase) to has_pro (snake_case) global property
        hasPro() {
            return this.has_pro || false;
        }
    },
    methods: {
        // WordPress hooks
        addFilter: wpSocialReviews.addFilter,
        applyFilters: wpSocialReviews.applyFilters,
        doAction: wpSocialReviews.doAction,
        addAction: wpSocialReviews.addAction,

        // Event handling methods
        $on: wpSocialReviews.$on,
        $off: wpSocialReviews.$off,
        $emit: wpSocialReviews.$emit,

        // Translation
        $t: wpSocialReviews.$t,

        // HTTP methods
        $get: wpSocialReviews.$get,
        $post: wpSocialReviews.$post,
        $del: wpSocialReviews.$del,
        $put: wpSocialReviews.$put,
        $patch: wpSocialReviews.$patch,

        // Utility methods
        handleError: wpSocialReviews.handleError,
        convertToText: wpSocialReviews.convertToText,
        slugify: wpSocialReviews.slugify,
        ucFirst: wpSocialReviews.ucFirst,
        wpsrDateFormat: wpSocialReviews.wpsrDateFormat,
        handleFullScreen: wpSocialReviews.handleFullScreen,
        checkFullScreen: wpSocialReviews.checkFullScreen,
        hasPermission: wpSocialReviews.hasPermission,
        handleSuccess: wpSocialReviews.handleSuccess,
        handlePro: wpSocialReviews.handlePro,
        clipboard: wpSocialReviews.clipboard,
        isUrl: wpSocialReviews.isUrl,
        nl2br: wpSocialReviews.nl2br,
        parseDuration: wpSocialReviews.parseDuration,
        shortNumberFormat: wpSocialReviews.shortNumberFormat,
        generateURLsFromHashTag: wpSocialReviews.generateURLsFromHashTag,
        escapeHtml: wpSocialReviews.escapeHtml,
        generateURLsFromText: wpSocialReviews.generateURLsFromText,
        numberWithCommas: wpSocialReviews.numberWithCommas,
        $getFirstWord: wpSocialReviews.$getFirstWord,
        $excerpt: wpSocialReviews.$excerpt,
        $trimWords: wpSocialReviews.$trimWords,
        $contentLanguage: wpSocialReviews.$contentLanguage,
        $addReadMoreBtn: wpSocialReviews.$addReadMoreBtn,
        truncateHTML: wpSocialReviews.truncateHTML,
        $showAjaxError: wpSocialReviews.$showAjaxError,
        get_platform_icon: wpSocialReviews.get_platform_icon,
        hasSelectedPlatformMultipleBusiness: wpSocialReviews.hasSelectedPlatformMultipleBusiness,
        show_platform_icon: wpSocialReviews.show_platform_icon,
        convertToPercentage: wpSocialReviews.convertToPercentage,
        ratingIcon: wpSocialReviews.ratingIcon,

        // Moment.js methods
        moment: moment,
        // longLocalDate: wpSocialReviews.longLocalDate,
        // longLocalDateTime: wpSocialReviews.longLocalDateTime,
        // dateTimeFormat: wpSocialReviews.dateTimeFormat,
        // localDate: wpSocialReviews.localDate,
        // humanDiffTime: wpSocialReviews.humanDiffTime,
        // waitingTime: wpSocialReviews.waitingTime
    }
});

// Register global components
app.component('WpsrSkeleton', WpsrSkeleton);

app.use(router);
const vueApp = app.mount('#wpsocialreviewsapp');
window.WPSocialReviews = vueApp;